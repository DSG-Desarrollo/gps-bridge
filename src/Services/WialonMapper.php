<?php

namespace App\Services;

/**
 * Class WialonMapper
 *
 * Servicio estático encargado de transformar la estructura de datos
 * obtenida desde Wialon al formato requerido por el servicio Cempro.
 *
 * Responsabilidades:
 * - Extraer información relevante desde la estructura de Wialon.
 * - Normalizar datos (coordenadas, velocidad, heading).
 * - Determinar tipo de evento mediante resolución desde sensores y mensajes.
 * - Determinar estado de señal GPS.
 * - Formatear timestamp en estándar ISO 8601 UTC.
 *
 * Esta clase no mantiene estado interno y opera de forma estática.
 *
 * @package App\Services
 */
class WialonMapper
{
    // -------------------------------------------------------------------------
    // Constantes de eventos Cempro
    // -------------------------------------------------------------------------

    /** Sin evento registrado */
    public const EVENT_NONE = 0;

    /** Vehículo detenido (velocidad = 0) */
    public const EVENT_STOP = 1;

    /** Ignición apagada (ign = 0 en lmsg.p) */
    public const EVENT_IGN_OFF = 2;

    /** Ignición encendida (ign = 1 en lmsg.p) */
    public const EVENT_IGN_ON = 3;

    /** Batería externa desconectada (bat_status = 0 o pwr_ext ausente/bajo) */
    public const EVENT_BATTERY_DISCONNECTED = 4;

    /** Botón de pánico activado (io_1_405 = 1 en lmsg.p) */
    public const EVENT_PANIC = 5;

    /** Interferencia de señal GPS (report_type = GTGSS o gps_signal = 0) */
    public const EVENT_GPS_JAMMING = 6;

    /** Remolque detectado (report_type = GTTOW) */
    public const EVENT_TOW = 7;

    /** Dispositivo comprometido / tamper (report_type = GTTMP o io_1_412 = 1) */
    public const EVENT_TAMPER = 8;

    /** Accidente detectado (report_type = GTCRA) */
    public const EVENT_CRASH = 9;

    /** Frenado brusco (report_type = GTSHA, sharpBrake) */
    public const EVENT_HARSH_BRAKE = 10;

    /** Aceleración brusca (report_type = GTSHA, sharpAcceleration) */
    public const EVENT_HARSH_ACCELERATION = 11;

    /** Giro brusco (report_type = GTSHA, sharpTurn) */
    public const EVENT_HARSH_TURN = 12;

    /** Alarma anti-robo activada (report_type = GTANT) */
    public const EVENT_ANTI_THEFT = 13;

    // -------------------------------------------------------------------------
    // Mapa de report_type (GL/Concox) a evento Cempro
    // -------------------------------------------------------------------------

    /**
     * Prefijos de report_type de dispositivos GL/Concox mapeados a eventos Cempro.
     * Los report_type con comportamiento especial (e.g. GTSHA) se resuelven
     * por lógica adicional en resolveEvent().
     *
     * @var array<string, int>
     */
    private const REPORT_TYPE_EVENT_MAP = [
        'GTIGN' => self::EVENT_IGN_ON,   // Ignición ON  (se refina con parámetro ign)
        'GTIGF' => self::EVENT_IGN_OFF,  // Ignición OFF
        'GTPFA' => self::EVENT_IGN_OFF,  // Power-off (equivalente a IGN OFF)
        'GTPNA' => self::EVENT_IGN_ON,   // Power-on  (equivalente a IGN ON)
        'GTBTC' => self::EVENT_BATTERY_DISCONNECTED, // Battery cut / cable cortado
        'GTTOW' => self::EVENT_TOW,
        'GTTMP' => self::EVENT_TAMPER,
        'GTCRA' => self::EVENT_CRASH,
        'GTGSS' => self::EVENT_GPS_JAMMING,
        'GTANT' => self::EVENT_ANTI_THEFT,
        'GTSHA' => self::EVENT_NONE,     // Conducción agresiva: se discrimina abajo
    ];

    // -------------------------------------------------------------------------
    // Método principal
    // -------------------------------------------------------------------------

    /**
     * Mapea datos de Wialon al formato esperado por Cempro.
     *
     * Reglas aplicadas:
     * - Timestamp: prioridad pos.t > lmsg.rt > time()
     * - Coordenadas redondeadas a 7 decimales (precisión máxima requerida)
     * - Velocidad y heading redondeados a 1 decimal
     * - Evento determinado por resolveEvent()
     * - GPS determinado por hasGpsSignal()
     *
     * @param array<string, mixed> $unit     Datos de la unidad almacenada localmente.
     * @param array<string, mixed> $position Datos retornados por Wialon.
     *
     * @return array<string, mixed> Payload listo para enviar a Cempro.
     */
    public static function mapToCempro(array $unit, array $position)
    {
        $pos   = $position['pos']   ?? [];
        $lmsg  = $position['lmsg'] ?? [];

        // Timestamp: prioridad pos.t > lmsg.rt > now
        $timestamp = $pos['t'] ?? $lmsg['rt'] ?? time();

        // ID remoto del vehículo (proviene de la unidad local)
        $vehicleId = $unit['remote_id'] ?? $position['nm'] ?? '';

        // Coordenadas y datos de movimiento
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
     * Determina el código de evento Cempro a partir de los datos de Wialon.
     *
     * Orden de prioridad (de mayor a menor):
     *
     *  1. report_type en lmsg.p  → eventos de dispositivos GL/Concox vía mapa
     *     GTSHA se discrimina por el sub-campo sha_behavior_type:
     *       - 1 = frenado brusco
     *       - 2 = aceleración brusca
     *       - 3 = giro brusco
     *
     *  2. io_caused en lmsg.p (Teltonika) → eventos por I/O digital:
     *       - io_1_405 = 1  → Botón de pánico
     *       - io_1_412 = 1  → Tamper / dispositivo comprometido
     *       - Ignición OFF/ON inferida desde io_caused=5 + io_1_239
     *
     *  3. ign en lmsg.p (campo directo GL/Concox):
     *       - 0 → Ignición OFF
     *       - 1 → Ignición ON
     *
     *  4. bat_status en lmsg.p:
     *       - 0 → Batería desconectada
     *
     *  5. Velocidad desde pos.s:
     *       - 0 → Parada
     *
     *  6. Fallback → Sin evento (0)
     *
     * @param array<string, mixed> $position Datos completos retornados por Wialon.
     *
     * @return int Código de evento Cempro (0–13).
     */
    public static function resolveEvent(array $position)
    {
        $params = $position['lmsg']['p'] ?? [];
        $pos    = $position['pos']       ?? [];

        // ------------------------------------------------------------------
        // 1. report_type (GL/Concox)
        // ------------------------------------------------------------------
        $reportType = strtoupper(trim($params['report_type'] ?? ''));

        if ($reportType !== '') {

            // GTSHA: conducción agresiva — discriminar sub-tipo
            if ($reportType === 'GTSHA') {
                return self::resolveHarshDriving($params);
            }

            // GTIGN: ignición — refinar con campo ign si está disponible
            if ($reportType === 'GTIGN') {
                $ign = isset($params['ign']) ? (int) $params['ign'] : -1;
                if ($ign === 0) {
                    return self::EVENT_IGN_OFF;
                }
                if ($ign === 1) {
                    return self::EVENT_IGN_ON;
                }
                // Sin campo ign → asumir encendida (GTIGN = trigger de encendido)
                return self::EVENT_IGN_ON;
            }

            if (array_key_exists($reportType, self::REPORT_TYPE_EVENT_MAP)) {
                $mapped = self::REPORT_TYPE_EVENT_MAP[$reportType];
                // Si el mapa devuelve NONE para este report_type, continuar
                if ($mapped !== self::EVENT_NONE) {
                    return $mapped;
                }
            }
        }

        // ------------------------------------------------------------------
        // 2. I/O digital Teltonika (io_caused presente)
        // ------------------------------------------------------------------
        if (isset($params['io_caused'])) {

            // Botón de pánico
            if (isset($params['io_1_405']) && (int) $params['io_1_405'] === 1) {
                return self::EVENT_PANIC;
            }

            // Tamper / dispositivo comprometido
            if (isset($params['io_1_412']) && (int) $params['io_1_412'] === 1) {
                return self::EVENT_TAMPER;
            }

            // Ignición ON/OFF vía io_caused=5 + io_1_239 (entrada digital ignición)
            // io_1_239 es el parámetro estándar de ignición en Teltonika FMB series
            if (isset($params['io_1_239'])) {
                return ((int) $params['io_1_239'] === 1)
                    ? self::EVENT_IGN_ON
                    : self::EVENT_IGN_OFF;
            }
        }

        // ------------------------------------------------------------------
        // 3. Campo ign directo (GL/Concox, presente en mensajes INF/periódicos)
        // ------------------------------------------------------------------
        if (isset($params['ign'])) {
            $ign = (int) $params['ign'];
            if ($ign === 1) {
                return self::EVENT_IGN_ON;
            }
            if ($ign === 0) {
                return self::EVENT_IGN_OFF;
            }
        }

        // ------------------------------------------------------------------
        // 4. Estado de batería (bat_status = 0 → desconectada)
        // ------------------------------------------------------------------
        if (isset($params['bat_status']) && (int) $params['bat_status'] === 0) {
            return self::EVENT_BATTERY_DISCONNECTED;
        }

        // ------------------------------------------------------------------
        // 5. Velocidad: parada
        // ------------------------------------------------------------------
        $speed = $pos['s'] ?? null;
        if ($speed !== null && (float) $speed === 0.0) {
            return self::EVENT_STOP;
        }

        // ------------------------------------------------------------------
        // 6. Fallback: sin evento
        // ------------------------------------------------------------------
        return self::EVENT_NONE;
    }

    /**
     * Resuelve el sub-tipo de conducción agresiva para report_type = GTSHA.
     *
     * El campo sha_behavior_type indica:
     *   1 → Frenado brusco
     *   2 → Aceleración brusca
     *   3 → Giro brusco
     *
     * Si el campo no está disponible se retorna EVENT_NONE ya que
     * no es posible discriminar el tipo sin información adicional.
     *
     * @param array<string, mixed> $params Contenido de lmsg.p.
     *
     * @return int Código de evento Cempro.
     */
    private static function resolveHarshDriving(array $params)
    {
        $behaviorType = isset($params['sha_behavior_type'])
            ? (int) $params['sha_behavior_type']
            : null;

        return match ($behaviorType) {
            1       => self::EVENT_HARSH_BRAKE,
            2       => self::EVENT_HARSH_ACCELERATION,
            3       => self::EVENT_HARSH_TURN,
            default => self::EVENT_NONE,
        };
    }

    // -------------------------------------------------------------------------
    // Señal GPS
    // -------------------------------------------------------------------------

    /**
     * Determina si el vehículo tiene señal GPS válida.
     *
     * Métodos de validación (en orden de prioridad):
     *
     * 1. gps_acc (Concox/GL):
     *    - 1 = GPS válido
     *    - 0 = Sin señal
     *
     * 2. hdop (Teltonika):
     *    - Se considera válido si 0 < hdop <= 5.0
     *
     * 3. Flag f en pos:
     *    - Mayor a 0 indica señal válida
     *
     * Si ninguno aplica, se considera sin señal GPS.
     *
     * @param array<string, mixed> $position Datos completos de posición Wialon.
     *
     * @return bool True si existe señal GPS válida.
     */
    private static function hasGpsSignal(array $position)
    {
        $params = $position['lmsg']['p'] ?? [];
        $pos    = $position['pos']       ?? [];

        // Método 1: gps_acc (GL/Concox)
        if (isset($params['gps_acc'])) {
            return (int) $params['gps_acc'] === 1;
        }

        // Método 2: HDOP (Teltonika)
        if (isset($params['hdop'])) {
            $hdop = (float) $params['hdop'];
            return $hdop > 0 && $hdop <= 5.0;
        }

        // Método 3: Flag de posición válida
        if (isset($pos['f'])) {
            return (int) $pos['f'] > 0;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Utilidades
    // -------------------------------------------------------------------------

    /**
     * Formatea un timestamp UNIX a formato ISO 8601 en UTC.
     *
     * Ejemplo de salida: 2026-02-11T21:54:41.000Z
     *
     * @param int $unix Timestamp en formato UNIX.
     *
     * @return string Timestamp formateado en estándar ISO 8601 UTC.
     */
    private static function formatTimestamp(int $unix)
    {
        return gmdate('Y-m-d\TH:i:s.000\Z', $unix);
    }
}