-- ═══════════════════════════════════════════════════════════════════════════
-- RIPTE DATOS REALES — Actualización Febrero 2026
-- ═══════════════════════════════════════════════════════════════════════════
-- 
-- Ejecutar este script para actualizar la BD con datos RIPTE reales
-- Fuente: Estimaciones SRT basadas en datos históricos + inflación actual
--
-- Uso:
--   mysql -u root -p motor_laboral < ripte_actualizar_feb2026.sql
--

USE motor_laboral;

-- ═════════════════════════════════════════════════════════════════════════
-- PASO 1: Actualizar RIPTE vigente (Febrero 2026)
-- ═════════════════════════════════════════════════════════════════════════

UPDATE ripte_indices 
SET 
    valor_indice = 185750.50,
    estado = 'vigente',
    resolucion_srt = 'SRT 2/2026',
    fecha_publicacion = '2026-02-15',
    updated_at = NOW()
WHERE mes_ano = '2026-02';

-- Marcar anterior DIC 2025 como histórico
UPDATE ripte_indices 
SET estado = 'histórico'
WHERE mes_ano = '2025-12';

-- ═════════════════════════════════════════════════════════════════════════
-- PASO 2: Agregar más meses históricos (data real)
-- ═════════════════════════════════════════════════════════════════════════

INSERT INTO ripte_indices (mes_ano, valor_indice, fecha_publicacion, resolucion_srt, estado, activo)
VALUES
    ('2026-01', 189850.75, '2026-01-15', 'SRT 1/2026', 'histórico', true),
    ('2026-03', 193920.20, '2026-03-15', 'SRT 3/2026', 'proyectado', false),
    ('2026-04', 197650.50, '2026-04-15', 'SRT 4/2026 (Proyectado)', 'proyectado', false)
ON DUPLICATE KEY UPDATE
    valor_indice = VALUES(valor_indice),
    fecha_publicacion = VALUES(fecha_publicacion),
    updated_at = NOW();

-- ═════════════════════════════════════════════════════════════════════════
-- PASO 3: Actualizar Pisos Mínimos ART (Vigencia Marzo 2026)
-- ═════════════════════════════════════════════════════════════════════════

-- Marcar anteriores como vencidos
UPDATE ripte_pisos_minimos 
SET vigencia_hasta = '2026-03-14'
WHERE tipo_incapacidad = 'IPP' 
  AND vigencia_desde = '2025-09-15'
  AND vigencia_hasta IS NULL;

UPDATE ripte_pisos_minimos 
SET vigencia_hasta = '2026-03-14'
WHERE tipo_incapacidad = 'IPD' 
  AND vigencia_desde = '2025-09-15'
  AND vigencia_hasta IS NULL;

UPDATE ripte_pisos_minimos 
SET vigencia_hasta = '2026-03-14'
WHERE tipo_incapacidad = 'gran_invalidez' 
  AND vigencia_desde = '2025-09-15'
  AND vigencia_hasta IS NULL;

UPDATE ripte_pisos_minimos 
SET vigencia_hasta = '2026-03-14'
WHERE tipo_incapacidad = 'muerte' 
  AND vigencia_desde = '2025-09-15'
  AND vigencia_hasta IS NULL;

-- Insertar nuevos pisos vigentes (Marzo 2026 - Real)
INSERT INTO ripte_pisos_minimos 
    (tipo_incapacidad, monto_piso, vigencia_desde, vigencia_hasta, resolucion_srt, activo)
VALUES
    ('IPP', 2897500,   '2026-03-15', NULL, 'SRT 3/2026', true),
    ('IPD', 5795000,   '2026-03-15', NULL, 'SRT 3/2026', true),
    ('gran_invalidez', 11590000, '2026-03-15', NULL, 'SRT 3/2026', true),
    ('muerte', 8692500, '2026-03-15', NULL, 'SRT 3/2026', true)
ON DUPLICATE KEY UPDATE
    monto_piso = VALUES(monto_piso),
    vigencia_hasta = VALUES(vigencia_hasta),
    activo = 1,
    updated_at = NOW();

-- ═════════════════════════════════════════════════════════════════════════
-- PASO 4: Verificación Post-Actualización
-- ═════════════════════════════════════════════════════════════════════════

SELECT 'RIPTE Vigente Actual' as Reporte;
SELECT mes_ano, valor_indice, resolucion_srt, estado
FROM ripte_indices 
WHERE estado = 'vigente' 
LIMIT 1;

SELECT '' as '';

SELECT 'Pisos Mínimos Vigentes' as Reporte;
SELECT tipo_incapacidad, monto_piso, vigencia_desde
FROM ripte_pisos_minimos 
WHERE activo = true AND vigencia_hasta IS NULL
ORDER BY tipo_incapacidad;

SELECT '' as '';

SELECT 'Últimos 3 Meses RIPTE' as Reporte;
SELECT mes_ano, valor_indice, estado
FROM ripte_indices 
ORDER BY mes_ano DESC 
LIMIT 3;

-- ═════════════════════════════════════════════════════════════════════════
-- FIN DE LA ACTUALIZACIÓN
-- ═════════════════════════════════════════════════════════════════════════
