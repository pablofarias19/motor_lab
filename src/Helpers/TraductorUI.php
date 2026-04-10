<?php
namespace App\Helpers;

/**
 * TraductorUI - Modula los títulos y textos de la interfaz
 * según si el usuario es "empleado" o "empleador".
 */
class TraductorUI {
    
    private static array $diccionario = [
        'indemnizacion' => [
            'empleado' => 'Tu indemnización',
            'empleador' => 'Exposición indemnizatoria estimada'
        ],
        'liquidacion_final' => [
            'empleado' => 'Liquidación final',
            'empleador' => 'Costo total de desvinculación'
        ],
        'riesgo_pago' => [
            'empleado' => 'Riesgo de que no te paguen',
            'empleador' => 'Riesgo de incumplimiento judicial'
        ],
        'monto_total' => [
            'empleado' => 'Monto que deberías cobrar',
            'empleador' => 'Monto potencial reclamable en tu contra'
        ],
        'derechos' => [
            'empleado' => 'Derechos laborales vulnerados',
            'empleador' => 'Posibles incumplimientos normativos'
        ],
        'probabilidad' => [
            'empleado' => 'Probabilidad de ganar el juicio',
            'empleador' => 'Probabilidad de resultado adverso'
        ],
        'estrategia' => [
            'empleado' => 'Estrategia para reclamar',
            'empleador' => 'Estrategia de mitigación de riesgo'
        ],
        'deuda' => [
            'empleado' => 'Deuda laboral',
            'empleador' => 'Pasivo laboral contingente'
        ],
        'intereses' => [
            'empleado' => 'Intereses a tu favor',
            'empleador' => 'Intereses de actualización del pasivo'
        ],
        'multas' => [
            'empleado' => 'Multas e indemnizaciones adicionales',
            'empleador' => 'Agravantes económicos aplicables'
        ]
    ];

    /**
     * Retorna el copy correcto según el rol del usuario
     */
    public static function traducir(string $clave, string $rol): string {
        $rol = strtolower($rol) === 'empleador' ? 'empleador' : 'empleado';
        
        // Retorna el texto si existe la clave, sino devuelve la clave misma como fallback
        return self::$diccionario[$clave][$rol] ?? $clave;
    }
}
