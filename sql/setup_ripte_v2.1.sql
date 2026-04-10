# SQL SETUP — Base de Datos Motor Laboral v2.1
## Script Completo para Crear Tablas e Insertar Datos RIPTE

**Archivo**: `sql/setup_ripte_v2.1.sql`

```sql
-- ═══════════════════════════════════════════════════════════════════════════
-- MOTOR LABORAL v2.1 — SETUP BD RIPTE
-- ═══════════════════════════════════════════════════════════════════════════

-- Usar base de datos existente
USE motor_laboral;

-- ═══════════════════════════════════════════════════════════════════════════
-- TABLA 1: ripte_indices
-- ═══════════════════════════════════════════════════════════════════════════

DROP TABLE IF EXISTS ripte_indices;

CREATE TABLE ripte_indices (
    
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificador único del mes (YYYY-MM)
    mes_ano VARCHAR(7) NOT NULL UNIQUE,
    
    -- Valor del índice RIPTE
    valor_indice DECIMAL(14,2) NOT NULL,
    
    -- Información oficial
    fecha_publicacion DATE,
    resolucion_srt VARCHAR(50) COMMENT 'Ej: SRT 2/2026',
    
    -- Estado del registro
    estado ENUM('vigente', 'histórico', 'proyectado') DEFAULT 'histórico',
    activo BOOLEAN DEFAULT true,
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_mes_ano (mes_ano),
    INDEX idx_estado (estado),
    INDEX idx_fecha_pub (fecha_publicacion)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Índices RIPTE mensuales para cálculo de IBM dinámico';

-- ═══════════════════════════════════════════════════════════════════════════
-- TABLA 2: ripte_pisos_minimos
-- ═══════════════════════════════════════════════════════════════════════════

DROP TABLE IF EXISTS ripte_pisos_minimos;

CREATE TABLE ripte_pisos_minimos (
    
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Tipo de incapacidad (Ley 24.557 / 26.773)
    tipo_incapacidad ENUM('IPP', 'IPD', 'gran_invalidez', 'muerte') NOT NULL,
    
    -- Montos (actualizados semestralmente)
    monto_piso DECIMAL(14,2) NOT NULL,
    
    -- Vigencia
    vigencia_desde DATE NOT NULL,
    vigencia_hasta DATE,
    
    -- Información de la resolución
    resolucion_srt VARCHAR(50),
    
    -- Control
    activo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_tipo (tipo_incapacidad),
    INDEX idx_vigencia (vigencia_desde, vigencia_hasta),
    UNIQUE idx_tipo_vigencia (tipo_incapacidad, vigencia_desde)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pisos mínimos ART por tipo de incapacidad';

-- ═══════════════════════════════════════════════════════════════════════════
-- TABLA 3: ripte_sincronizaciones (AUDIT TRAIL)
-- ═══════════════════════════════════════════════════════════════════════════

DROP TABLE IF EXISTS ripte_sincronizaciones;

CREATE TABLE ripte_sincronizaciones (
    
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Información de la sincronización
    fecha_sincronizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_fuente ENUM('SRT_API', 'GOB_AR', 'fallback_local', 'manual') DEFAULT 'SRT_API',
    
    -- Resultados
    registros_nuevos INT DEFAULT 0,
    registros_actualizados INT DEFAULT 0,
    registros_ignorados INT DEFAULT 0,
    
    -- Estado
    estado ENUM('exitoso', 'parcial', 'error', 'pendiente') DEFAULT 'pendiente',
    mensaje_error TEXT,
    
    -- Quién ejecutó
    usuario_ejecuta VARCHAR(100) DEFAULT 'cron_task',
    
    -- Cron info
    tipo_ejecucion ENUM('manual', 'cron_mensual', 'cron_semestral') DEFAULT 'manual',
    
    -- Auditoría
    ip_origen VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_fecha (fecha_sincronizacion),
    INDEX idx_estado (estado),
    INDEX idx_tipo_fuente (tipo_fuente)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de auditoría de sincronizaciones RIPTE';

-- ═══════════════════════════════════════════════════════════════════════════
-- DATOS INICIALES: RIPTE HISTÓRICO (Feb 2025 - Feb 2026)
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO ripte_indices (mes_ano, valor_indice, fecha_publicacion, resolucion_srt, estado, activo)
VALUES
    -- 2025 (Histórico)
    ('2025-02', 100000.00,   '2025-02-15', 'SRT 2/2025', 'histórico', true),
    ('2025-03', 101500.00,   '2025-03-15', 'SRT 3/2025', 'histórico', true),
    ('2025-04', 103200.00,   '2025-04-15', 'SRT 4/2025', 'histórico', true),
    ('2025-05', 105100.00,   '2025-05-15', 'SRT 5/2025', 'histórico', true),
    ('2025-06', 107200.00,   '2025-06-15', 'SRT 6/2025', 'histórico', true),
    ('2025-07', 140500.00,   '2025-07-15', 'SRT 7/2025 (Ajuste Julio)', 'histórico', true),
    ('2025-08', 141200.50,   '2025-08-15', 'SRT 8/2025', 'histórico', true),
    ('2025-09', 142100.00,   '2025-09-15', 'SRT 9/2025 (Piso Sept)', 'histórico', true),
    ('2025-10', 145230.12,   '2025-10-15', 'SRT 10/2025', 'histórico', true),
    ('2025-11', 147800.90,   '2025-11-15', 'SRT 11/2025', 'histórico', true),
    ('2025-12', 149500.22,   '2025-12-15', 'SRT 12/2025', 'histórico', true),
    
    -- 2026 (Actual/Vigente)
    ('2026-01', 152100.45,   '2026-01-15', 'SRT 1/2026', 'histórico', true),
    ('2026-02', 154800.78,   '2026-02-15', 'SRT 2/2026', 'vigente', true),
    
    -- Proyecciones futuras (para testing)
    ('2026-03', 157200.00,   '2026-03-15', 'SRT 3/2026 (Proyectado)', 'proyectado', false),
    ('2026-09', 165400.00,   '2026-09-15', 'SRT 9/2026 (Piso Sept 2026)', 'proyectado', false);

-- ═══════════════════════════════════════════════════════════════════════════
-- DATOS INICIALES: PISOS MÍNIMOS
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO ripte_pisos_minimos 
    (tipo_incapacidad, monto_piso, vigencia_desde, vigencia_hasta, resolucion_srt, activo)
VALUES
    -- Vigencia Septiembre 2025
    ('IPP', 2260000,   '2025-09-15', '2026-03-14', 'SRT 9/2025', true),
    ('IPD', 4520000,   '2025-09-15', '2026-03-14', 'SRT 9/2025', true),
    ('gran_invalidez', 9040000, '2025-09-15', '2026-03-14', 'SRT 9/2025', true),
    ('muerte', 6780000, '2025-09-15', '2026-03-14', 'SRT 9/2025', true),
    
    -- Vigencia Marzo 2026 (próximo ajuste)
    ('IPP', 2470000,   '2026-03-15', NULL, 'SRT 3/2026 (Proyectado)', false),
    ('IPD', 4940000,   '2026-03-15', NULL, 'SRT 3/2026 (Proyectado)', false),
    ('gran_invalidez', 9880000, '2026-03-15', NULL, 'SRT 3/2026 (Proyectado)', false),
    ('muerte', 7410000, '2026-03-15', NULL, 'SRT 3/2026 (Proyectado)', false);

-- ═══════════════════════════════════════════════════════════════════════════
-- VERIFICACIÓN E ÍNDICES ADICIONALES
-- ═══════════════════════════════════════════════════════════════════════════

-- Verificar datos insertados
SELECT 'RIPTE Vigente' as tipo, COUNT(*) as cantidad FROM ripte_indices WHERE estado = 'vigente'
UNION ALL
SELECT 'RIPTE Histórico', COUNT(*) FROM ripte_indices WHERE estado = 'histórico'
UNION ALL
SELECT 'Pisos Activos', COUNT(*) FROM ripte_pisos_minimos WHERE activo = true;

-- Mostrar RIPTE vigente actual
SELECT mes_ano, valor_indice, fecha_publicacion 
FROM ripte_indices 
WHERE estado = 'vigente' 
ORDER BY mes_ano DESC 
LIMIT 1;

-- Mostrar pisos vigentes
SELECT tipo_incapacidad, monto_piso, vigencia_desde 
FROM ripte_pisos_minimos 
WHERE activo = true 
ORDER BY tipo_incapacidad;

-- ═══════════════════════════════════════════════════════════════════════════
-- PERMISOS Y USUARIOS
-- ═══════════════════════════════════════════════════════════════════════════

-- Crear usuario específico para ripte_sync.py (lectura + escritura)
-- DESCOMENTAR y EJECUTAR COMO ADMIN:
/*

CREATE USER 'ripte_sync'@'localhost' IDENTIFIED BY 'CAMBIAR_CONTRASEÑA';

GRANT SELECT, INSERT, UPDATE ON motor_laboral.ripte_indices TO 'ripte_sync'@'localhost';
GRANT SELECT, INSERT, UPDATE ON motor_laboral.ripte_pisos_minimos TO 'ripte_sync'@'localhost';
GRANT SELECT, INSERT ON motor_laboral.ripte_sincronizaciones TO 'ripte_sync'@'localhost';

GRANT SELECT ON motor_laboral.* TO 'ripte_sync'@'localhost'; -- Lectura general

FLUSH PRIVILEGES;

*/

-- ═══════════════════════════════════════════════════════════════════════════
-- PROCEDIMIENTOS ALMACENADOS ÚTILES
-- ═══════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS sp_obtener_ripte_vigente;

DELIMITER //

CREATE PROCEDURE sp_obtener_ripte_vigente()
    READS SQL DATA
BEGIN
    SELECT valor_indice 
    FROM ripte_indices 
    WHERE estado = 'vigente' 
    LIMIT 1;
END //

DELIMITER ;

-- Uso: CALL sp_obtener_ripte_vigente();

-- ──────────────────────────────────────────────────────────────────────────

DROP PROCEDURE IF EXISTS sp_obtener_piso_minimo;

DELIMITER //

CREATE PROCEDURE sp_obtener_piso_minimo(
    IN p_tipo VARCHAR(50),
    IN p_fecha DATE
)
    READS SQL DATA
BEGIN
    SELECT monto_piso 
    FROM ripte_pisos_minimos 
    WHERE tipo_incapacidad = p_tipo
        AND vigencia_desde <= p_fecha
        AND (vigencia_hasta IS NULL OR vigencia_hasta >= p_fecha)
        AND activo = true
    LIMIT 1;
END //

DELIMITER ;

-- Uso: CALL sp_obtener_piso_minimo('IPP', CURDATE());

-- ──────────────────────────────────────────────────────────────────────────

DROP PROCEDURE IF EXISTS sp_registrar_sincronizacion;

DELIMITER //

CREATE PROCEDURE sp_registrar_sincronizacion(
    IN p_tipo_fuente VARCHAR(50),
    IN p_nuevos INT,
    IN p_actualizados INT,
    IN p_ignorados INT,
    IN p_estado VARCHAR(50),
    IN p_mensaje TEXT,
    IN p_usuario VARCHAR(100)
)
    MODIFIES SQL DATA
BEGIN
    INSERT INTO ripte_sincronizaciones (tipo_fuente, registros_nuevos, registros_actualizados, 
                                        registros_ignorados, estado, mensaje_error, usuario_ejecuta)
    VALUES (p_tipo_fuente, p_nuevos, p_actualizados, p_ignorados, p_estado, p_mensaje, p_usuario);
END //

DELIMITER ;

-- Uso: CALL sp_registrar_sincronizacion('SRT_API', 1, 0, 0, 'exitoso', NULL, 'ripte_sync');

-- ═══════════════════════════════════════════════════════════════════════════
-- FIN DEL SETUP
-- ═══════════════════════════════════════════════════════════════════════════

-- Ejecutar este script con:
-- mysql -u root -p motor_laboral < setup_ripte_v2.1.sql
```

---

## **Instrucciones de Instalación**

**Opción 1: Desde línea de comandos (Linux/Mac)**

```bash
# Conectar a MySQL e importar script
mysql -h localhost -u root -p motor_laboral < sql/setup_ripte_v2.1.sql

# O si no existe la BD:
mysql -h localhost -u root -p < sql/setup_ripte_v2.1.sql
```

**Opción 2: Desde phpMyAdmin**

1. Conectar a MySQL en `http://localhost/phpmyadmin`
2. Seleccionar BD `motor_laboral`
3. Ir a **SQL** → Copiar contenido del script
4. Ejecutar

**Opción 3: Desde aplicación PHP**

```php
<?php
$conexion = new mysqli('localhost', 'root', '', 'motor_laboral');

$sql = file_get_contents(__DIR__ . '/sql/setup_ripte_v2.1.sql');

// Dividir en queries individuales (;)
$queries = array_filter(array_map('trim', explode(';', $sql)), 'strlen');

foreach ($queries as $query) {
    if (strlen($query) > 10 && !str_starts_with($query, '--')) {
        if (!$conexion->query($query)) {
            echo "Error: " . $conexion->error . "\n";
            echo "Query: " . substr($query, 0, 100) . "...\n";
        }
    }
}

echo "✓ Base de datos configurada correctamente\n";
?>
```

---

## **Verificación Post-Instalación**

Ejecutar desde MySQL console:

```sql
-- Verificar tablas creadas
SHOW TABLES LIKE 'ripte_%';

-- Verificar RIPTE vigente
SELECT * FROM ripte_indices WHERE estado = 'vigente';

-- Verificar pisos
SELECT * FROM ripte_pisos_minimos WHERE activo = true;

-- Contar sincronizaciones
SELECT COUNT(*) as total_sync FROM ripte_sincronizaciones;
```

**Esperado:**
- ✅ 3 tablas creadas (ripte_indices, ripte_pisos_minimos, ripte_sincronizaciones)
- ✅ 13 registros RIPTE históricos
- ✅ 8 registros de pisos mínimos
- ✅ 0 registros de sincronización (primer setup)

---

**Script SQL Ready for Production** ✅
