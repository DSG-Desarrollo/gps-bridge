<?php

namespace App\Services;

class WialonMapper
{
    public static function mapToCempro(array $unit, array $position)
    {
        var_dump($position);
        $pos = $position['pos'] ?? [];
        $lmsg = $position['lmsg'] ?? [];
        $unix = (int) ($pos['t'] ?? $lmsg['rt'] ?? time());
        $unit = isset($unit['remote_id']) ? $unit['remote_id'] : $position['nm'];

        $lat = isset($pos['y']) ? (float)($pos['y']) : 0.0;
        $lon = isset($pos['x']) ? (float)($pos['x']) : 0.0;
        $speed = isset($pos['s']) ? (float)($pos['s']) : 0.0;
        $heading = isset($pos['c']) ? (float)($pos['c']) : 0.0;

        return array(
            'timestamp' => self::formatTimestamp($unix),
            'id'        => (string) $unit,
            'lat'       => self::ensureFloat(round($lat, 6)),      // ✅ Fuerza float
            'lon'       => self::ensureFloat(round($lon, 6)),      // ✅ Fuerza float
            'kmph'      => self::ensureFloat(round($speed, 1)),    // ✅ Fuerza float
            'heading'   => self::ensureFloat(round($heading, 1)),  // ✅ Fuerza float
            'event'     => (int) self::mapEvent($position),
            'gps'       => (bool) self::hasGpsSignal($position),
        );
    }

    /**
     * Asegura que un número se serialice como float en JSON
     */
    private static function ensureFloat(float $value): float
    {
        // Fuerza que siempre tenga al menos un decimal
        return (float) number_format($value, strpos((string)$value, '.') !== false ? strlen(substr(strrchr((string)$value, '.'), 1)) : 1, '.', '');
    }

    private static function formatTimestamp(int $unix): string
    {
        // ISO 8601 con milisegundos fijos
        return gmdate('Y-m-d\TH:i:s', $unix) . '.000Z';
    }

    private static function hasGpsSignal(array $position): bool
    {
        $p   = $position['lmsg']['p'] ?? [];
        $pos = $position['pos'] ?? [];

        if (array_key_exists('gps_acc', $p)) {
            return (int)$p['gps_acc'] === 1;
        }

        if (array_key_exists('hdop', $p)) {
            $hdop = (float)$p['hdop'];
            return $hdop > 0 && $hdop <= 5.0;
        }

        if (array_key_exists('f', $pos)) {
            return (int)$pos['f'] > 0;
        }

        return false;
    }

    private static function mapEvent(array $pos): int
    {
        if (($pos['s'] ?? 0) == 0) {
            return 1; // Parada
        }
        return 0; // Sin evento
    }
}
