<?php

namespace App\Services;

class WialonMapper
{
    /**
     * Mapea datos de Wialon al formato esperado por Cempro
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
            'timestamp' => self::formatTimestamp($timestamp),
            'id' => (string) $vehicleId,
            'lat' => round($lat, 6),
            'lon' => round($lon, 6),
            'kmph' => round($speed, 1),
            'heading' => round($heading, 1),
            'event' => self::getEventType($pos),
            'gps' => self::hasGpsSignal($position),
        ];
    }
    
    /**
     * Formatea timestamp a ISO 8601 como espera Cempro
     */
    private static function formatTimestamp(int $unix): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z', $unix);
    }
    
    /**
     * Determina si el vehículo tiene señal GPS válida
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
        
        // Por defecto: sin señal GPS
        return false;
    }
    
    /**
     * Determina el tipo de evento según velocidad
     */
    private static function getEventType(array $pos): int
    {
        $speed = $pos['s'] ?? 0;
        
        // 0 = Sin evento (en movimiento)
        // 1 = Parada (velocidad = 0)
        return ($speed == 0) ? 1 : 0;
    }
}