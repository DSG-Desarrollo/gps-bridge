<?php

namespace App\Services;

class WialonMapper
{
    public static function mapToCempro(array $unit, array $position): array
    {
        $pos = $position['pos'] ?? [];
        $lmsg = $position['lmsg'] ?? [];

        $unix = (int) ($pos['t'] ?? $position['lmsg']['rt'] ?? time());
        $unit = isset($unit['wa_name']) ? $unit['wa_name'] : $position['nm'];

        $lat = isset($pos['y']) ? (float)($pos['y']) : null;
        $lon = isset($pos['x']) ? (float)($pos['x']) : null;
        $speed = isset($pos['s']) ? (float)($pos['s']) : 0.0;
        $heading = isset($pos['c']) ? (float)($pos['c']) : 0.0;

        return [
            'timestamp' => self::formatTimestamp($unix),
            'id'        => (string) ($unit ?? 'unknown'),
            'lat'       => $lat === null ? null : self::roundCoord($lat),
            'lon'       => $lon === null ? null : self::roundCoord($lon),
            'kmph'      => $speed,
            'heading'   => $heading,
            'event'     => self::mapEvent($position),
            'gps'       => self::hasGpsSignal($position)
        ];
    }

    private static function formatTimestamp(int $unix): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $unix);
    }

    private static function roundCoord(float $value): float
    {
        // max 7 decimals as requested
        return round($value, 7);
    }

    private static function hasGpsSignal(array $position): bool
    {
        $p   = $position['lmsg']['p'] ?? [];
        $pos = $position['pos'] ?? [];

        // 1️⃣ gps_acc: indicador directo de fix
        if (array_key_exists('gps_acc', $p)) {
            return (int)$p['gps_acc'] === 1;
        }

        // 2️⃣ hdop: precisión del GPS (menor es mejor)
        if (array_key_exists('hdop', $p)) {
            $hdop = (float)$p['hdop'];

            // Valores estándar: <= 5 aceptable
            return $hdop > 0 && $hdop <= 5.0;
        }

        // 3️⃣ pos.f: flags de posición (fallback)
        if (array_key_exists('f', $pos)) {
            return (int)$pos['f'] > 0;
        }

        // 4️⃣ Sin información suficiente → GPS inválido
        return false;
    }

    /**
     * Temporary basic event logic
     * Can be improved later
     */
    private static function mapEvent(array $pos): int
    {
        // speed = 0 -> stop
        if (($pos['s'] ?? 0) == 0) {
            return 1; // Parada
        }

        return 0; // Sin evento
    }
}
