<?php

namespace App\Services;

class WialonMapper
{
    public static function mapToCempro(array $unit, array $position): array
    {
        $pos = $position['pos'] ?? [];
        $lmsg = $position['lmsg'] ?? [];

        $unix = (int) ($pos['t'] ?? $lmsg['rt'] ?? time());
        $unit = isset($unit['remote_id']) ? $unit['remote_id'] : $position['nm'];

        $lat = isset($pos['y']) ? (float)($pos['y']) : 0.0;
        $lon = isset($pos['x']) ? (float)($pos['x']) : 0.0;
        $speed = isset($pos['s']) ? (float)($pos['s']) : 0.0;
        $heading = isset($pos['c']) ? (float)($pos['c']) : 0.0;

        return [
            'timestamp' => self::formatTimestamp($unix),
            'id'        => (string) $unit,
            'lat'       => round($lat, 7),      // ✅ Asegurar float con 7 decimales
            'lon'       => round($lon, 7),      // ✅ Asegurar float con 7 decimales
            'kmph'      => round($speed, 1),    // ✅ Asegurar float con 1 decimal
            'heading'   => round($heading, 1),  // ✅ Asegurar float con 1 decimal
            'event'     => (int) self::mapEvent($position), // ✅ Asegurar integer
            'gps'       => (bool) self::hasGpsSignal($position), // ✅ Asegurar boolean
        ];
    }

    private static function formatTimestamp(int $unix): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $unix);
    }

    private static function roundCoord(?float $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        return round($value, 7);
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