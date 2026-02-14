<?php

namespace App\Services;

/**
 * Class WialonMapper
 *
 * Servicio estático encargado de transformar la estructura de datos
 * obtenida desde Wialon al formato requerido por el servicio Cempro.
 *
 * Dispositivos soportados:
 * - GL/Concox  → identificados por presencia de report_type en lmsg.p
 * - Teltonika  → identificados por presencia de io_caused en lmsg.p
 *
 * @package App\Services
 */
class WialonMapper
{
    // -------------------------------------------------------------------------
    // Constantes de eventos Cempro
    // -------------------------------------------------------------------------

    public const EVENT_NONE                 = 0;
    public const EVENT_STOP                 = 1;
    public const EVENT_IGN_OFF              = 2;
    public const EVENT_IGN_ON               = 3;
    public const EVENT_BATTERY_DISCONNECTED = 4;
    public const EVENT_PANIC                = 5;
    public const EVENT_GPS_JAMMING          = 6;
    public const EVENT_TOW                  = 7;
    public const EVENT_TAMPER               = 8;
    public const EVENT_CRASH                = 9;
    public const EVENT_HARSH_BRAKE          = 10;
    public const EVENT_HARSH_ACCELERATION   = 11;
    public const EVENT_HARSH_TURN           = 12;
    public const EVENT_ANTI_THEFT           = 13;

    // -------------------------------------------------------------------------
    // Mapa de report_type (GL/Concox) → evento Cempro
    //
    // Solo para report_type con mapeo 1:1 directo.
    // GTSHA y GTIGN tienen lógica adicional en resolveEvent().
    // -------------------------------------------------------------------------

    /** @var array<string, int> */
    private const REPORT_TYPE_EVENT_MAP = [
        'GTIGF' => self::EVENT_IGN_OFF,
        'GTPFA' => self::EVENT_IGN_OFF,              // Power-off = IGN OFF
        'GTPNA' => self::EVENT_IGN_ON,               // Power-on  = IGN ON
        'GTBTC' => self::EVENT_BATTERY_DISCONNECTED, // Battery cut
        'GTTOW' => self::EVENT_TOW,
        'GTTMP' => self::EVENT_TAMPER,
        'GTCRA' => self::EVENT_CRASH,
        'GTGSS' => self::EVENT_GPS_JAMMING,
        'GTANT' => self::EVENT_ANTI_THEFT,
    ];

    // -------------------------------------------------------------------------
    // Mapa de io_caused (Teltonika) → evento Cempro
    //
    // io_caused = ID del parámetro que DISPARÓ el mensaje.
    // Usar io_caused es la forma correcta de detectar eventos en Teltonika.
    // NO leer io_1_405 directamente — ese campo está en todos los mensajes
    // con su estado actual, no indica que el evento ocurrió ahora.
    //
    // IDs Teltonika FMB/FMC confirmados:
    //   239 → Ignición (io_1_239: 0=OFF, 1=ON) — manejo especial
    //   247 → Botón de pánico (Digital Input)
    //   385 → Eco/Driving score — NO mapear (no es evento de seguridad)
    //   405 → Green Driving score — NO es pánico (bug corregido)
    //   412 → Tamper detect
    // -------------------------------------------------------------------------

    /** @var array<int, int> */
    private const TELTONIKA_IO_EVENT_MAP = [
        247 => self::EVENT_PANIC,
        412 => self::EVENT_TAMPER,
    ];

    // -------------------------------------------------------------------------
    // Método principal
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $unit
     * @param array<string, mixed> $position
     * @return array<string, mixed>
     */
    public static function mapToCempro(array $unit, array $position): array
    {
        $pos  = $position['pos']   ?? [];
        $lmsg = $position['lmsg'] ?? [];

        $timestamp = $pos['t'] ?? $lmsg['rt'] ?? time();
        $vehicleId = $unit['remote_id'] ?? $position['nm'] ?? '';

        $lat     = $pos['y'] ?? 0.0;
        $lon     = $pos['x'] ?? 0.0;
        $speed   = $pos['s'] ?? 0.0;
        $heading = $pos['c'] ?? 0.0;

        return [
            'timestamp' => self::formatTimestamp((int) $timestamp),
            'id'        => (string) $vehicleId,
            'lat'       => round((float) $lat,     7),
            'lon'       => round((float) $lon,     7),
            'kmph'      => round((float) $speed,   1),
            'heading'   => round((float) $heading, 1),
            'event'     => self::resolveEvent($position),
            'gps'       => self::hasGpsSignal($position),
        ];
    }

    // -------------------------------------------------------------------------
    // Resolución de eventos
    // -------------------------------------------------------------------------

    /**
     * Determina el código de evento Cempro.
     *
     * Prioridad:
     *  1. report_type GL/Concox (GTSHA → sha_behavior_type, GTIGN → ign, resto → mapa)
     *  2. io_caused Teltonika   (239=ignición, 247=pánico, 412=tamper)
     *  3. ign directo           (mensajes periódicos GL/Concox sin report_type)
     *  4. bat_status = 0        (batería desconectada)
     *  5. pos.s = 0             (parada)
     *  6. EVENT_NONE            (fallback)
     *
     * @param array<string, mixed> $position
     */
    public static function resolveEvent(array $position): int
    {
        $params = $position['lmsg']['p'] ?? [];
        $pos    = $position['pos']       ?? [];

        // 1. report_type — GL/Concox
        $reportType = strtoupper(trim($params['report_type'] ?? ''));

        if ($reportType !== '') {

            if ($reportType === 'GTSHA') {
                return self::resolveHarshDriving($params);
            }

            if ($reportType === 'GTIGN') {
                $ign = isset($params['ign']) ? (int) $params['ign'] : 1;
                return ($ign === 0) ? self::EVENT_IGN_OFF : self::EVENT_IGN_ON;
            }

            if (isset(self::REPORT_TYPE_EVENT_MAP[$reportType])) {
                return self::REPORT_TYPE_EVENT_MAP[$reportType];
            }

            // report_type reconocido por el dispositivo pero no mapeado aquí
            return self::EVENT_NONE;
        }

        // 2. io_caused — Teltonika
        if (isset($params['io_caused'])) {
            return self::resolveEventTeltonika($params, $pos);
        }

        // 3. ign directo (mensajes periódicos GL/Concox sin report_type)
        if (isset($params['ign'])) {
            return ((int) $params['ign'] === 1)
                ? self::EVENT_IGN_ON
                : self::EVENT_IGN_OFF;
        }

        // 4. Batería desconectada
        if (isset($params['bat_status']) && (int) $params['bat_status'] === 0) {
            return self::EVENT_BATTERY_DISCONNECTED;
        }

        // 5. Parada por velocidad
        if (isset($pos['s']) && (float) $pos['s'] === 0.0) {
            return self::EVENT_STOP;
        }

        return self::EVENT_NONE;
    }

    /**
     * Resuelve eventos para dispositivos Teltonika usando io_caused.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $pos
     */
    private static function resolveEventTeltonika(array $params, array $pos): int
    {
        $ioCaused = (int) $params['io_caused'];

        // Ignición: io_caused = 239, leer valor actual de io_1_239
        if ($ioCaused === 239) {
            $ignValue = isset($params['io_1_239']) ? (int) $params['io_1_239'] : 0;
            return ($ignValue === 1) ? self::EVENT_IGN_ON : self::EVENT_IGN_OFF;
        }

        // Eventos directos (pánico, tamper)
        if (isset(self::TELTONIKA_IO_EVENT_MAP[$ioCaused])) {
            return self::TELTONIKA_IO_EVENT_MAP[$ioCaused];
        }

        // io_caused no mapeado (ej: 405=eco driving, 385=green score)
        // Reportar parada si aplica, sino sin evento
        if (isset($pos['s']) && (float) $pos['s'] === 0.0) {
            return self::EVENT_STOP;
        }

        return self::EVENT_NONE;
    }

    /**
     * Resuelve conducción agresiva para report_type = GTSHA.
     * sha_behavior_type: 1=frenado, 2=aceleración, 3=giro
     *
     * @param array<string, mixed> $params
     */
    private static function resolveHarshDriving(array $params): int
    {
        $behaviorType = isset($params['sha_behavior_type'])
            ? (int) $params['sha_behavior_type']
            : 0;

        switch ($behaviorType) {
            case 1:  return self::EVENT_HARSH_BRAKE;
            case 2:  return self::EVENT_HARSH_ACCELERATION;
            case 3:  return self::EVENT_HARSH_TURN;
            default: return self::EVENT_NONE;
        }
    }

    // -------------------------------------------------------------------------
    // Señal GPS
    // -------------------------------------------------------------------------

    /**
     * Determina si el vehículo tiene señal GPS válida.
     *
     * Prioridad:
     * 1. gps_acc (GL/Concox): 1=válido, 0=sin señal
     * 2. hdop (Teltonika): válido si 0 < hdop <= 5.0
     * 3. pos.f (Wialon flag): > 0 indica posición GPS válida
     *
     * @param array<string, mixed> $position
     */
    private static function hasGpsSignal(array $position): bool
    {
        $params = $position['lmsg']['p'] ?? [];
        $pos    = $position['pos']       ?? [];

        if (isset($params['gps_acc'])) {
            return (int) $params['gps_acc'] === 1;
        }

        if (isset($params['hdop'])) {
            $hdop = (float) $params['hdop'];
            return $hdop > 0 && $hdop <= 5.0;
        }

        if (isset($pos['f'])) {
            return (int) $pos['f'] > 0;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Utilidades
    // -------------------------------------------------------------------------

    /** Formatea UNIX timestamp a ISO 8601 UTC. Ejemplo: 2026-02-11T21:54:41.000Z */
    private static function formatTimestamp(int $unix): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z', $unix);
    }
}
