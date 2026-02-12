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
 * - Determinar tipo de evento.
 * - Determinar estado de señal GPS.
 * - Formatear timestamp en estándar ISO 8601 UTC.
 *
 * Esta clase no mantiene estado interno y opera de forma estática.
 *
 * @package App\Services
 */
class WialonMapper
{
    /**
     * Mapea datos de Wialon al formato esperado por Cempro.
     *
     * Reglas aplicadas:
     * - Timestamp: prioridad pos.t > lmsg.rt > time()
     * - Coordenadas redondeadas a 6 decimales
     * - Velocidad y heading redondeados a 1 decimal
     * - Evento determinado por velocidad
     * - GPS determinado por múltiples validaciones
     *
     * @param array<string, mixed> $unit     Datos de la unidad almacenada localmente.
     * @param array<string, mixed> $position Datos retornados por Wialon.
     *
     * @return array<string, mixed> Payload listo para enviar a Cempro.
     */
    public static function mapToCempro(array $unit, array $position): array
    {
        $pos = $position['pos'] ?? [];
        $lmsg = $position['lmsg'] ?? [];

        // Extraer timestamp (prioridad: pos.t > lmsg.rt > now)
        $timestamp = $pos['t'] ?? $lmsg['rt'] ?? time();

        // Extraer ID del vehículo
        $vehicleId = $unit['remote_id'] ?? $position['nm'] ?? '';

        // Extraer coordenadas y datos de movimiento
        $lat = $pos['y'] ?? 0.0;
        $lon = $pos['x'] ?? 0.0;
        $speed = $pos['s'] ?? 0.0;
        $heading = $pos['c'] ?? 0.0;

        return [
            'timestamp' => self::formatTimestamp((int) $timestamp),
            'id'        => (string) $vehicleId,
            'lat'       => round((float) $lat, 6),
            'lon'       => round((float) $lon, 6),
            'kmph'      => round((float) $speed, 1),
            'heading'   => round((float) $heading, 1),
            'event'     => self::getEventType($pos),
            'gps'       => self::hasGpsSignal($position),
        ];
    }

    /**
     * Formatea un timestamp UNIX a formato ISO 8601 en UTC.
     *
     * Ejemplo de salida:
     * 2026-02-11T21:54:41.000Z
     *
     * @param int $unix Timestamp en formato UNIX.
     *
     * @return string Timestamp formateado en estándar ISO 8601 UTC.
     */
    private static function formatTimestamp(int $unix): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z', $unix);
    }

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
    private static function hasGpsSignal(array $position): bool
    {
        $params = $position['lmsg']['p'] ?? [];
        $pos = $position['pos'] ?? [];

        // Método 1: Verificar gps_acc (Concox/GL)
        if (isset($params['gps_acc'])) {
            return (int) $params['gps_acc'] === 1;
        }

        // Método 2: Verificar HDOP (Teltonika)
        if (isset($params['hdop'])) {
            $hdop = (float) $params['hdop'];
            return $hdop > 0 && $hdop <= 5.0;
        }

        // Método 3: Verificar flag de GPS válido
        if (isset($pos['f'])) {
            return (int) $pos['f'] > 0;
        }

        return false;
    }

    /**
     * Determina el tipo de evento basado en la velocidad.
     *
     * Convención utilizada:
     * - 0 = En movimiento
     * - 1 = Parada (velocidad = 0)
     *
     * @param array<string, mixed> $pos Subestructura "pos" de Wialon.
     *
     * @return int Tipo de evento.
     */
    private static function getEventType(array $pos): int
    {
        $speed = $pos['s'] ?? 0;

        return ($speed == 0) ? 1 : 0;
    }
}
