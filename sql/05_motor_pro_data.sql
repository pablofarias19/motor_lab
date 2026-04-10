-- ---------------------------------------------------------
-- MOTOR LABORAL PRO - MIGRATION DUMP A MYSQL
-- Generado automáticamente desde Python/SQLite
-- ---------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Estructura de pro_ccts
DROP TABLE IF EXISTS `pro_ccts`;
CREATE TABLE `pro_ccts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cct_codigo` varchar(50) NOT NULL,
  `cct_nombre` varchar(255) NOT NULL,
  `antiguedad` text,
  `presentismo` text,
  `adicionales` text,
  `notas` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cct_codigo` (`cct_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para pro_ccts
LOCK TABLES `pro_ccts` WRITE;
INSERT INTO `pro_ccts` (`cct_codigo`, `cct_nombre`, `antiguedad`, `presentismo`, `adicionales`, `notas`) VALUES ('130/75', 'Empleados de Comercio', '1% por año aniversario (Art. 24)', '8.33% sobre la remuneración mensual (Art. 40)', 'Fallo de Caja (Cajeros A/B/C), Choferes, Vidrieristas', 'Jornada legal de 48 hs. Fuerte incidencia de sumas no remunerativas pactadas en paritarias que luego se incorporan al básico.'),
('260/75', 'Metalúrgico (UOM)', '1% por año (hasta 20 años) s/ básico', 'Premio por asistencia y puntualidad variable según rama', 'Título técnico, turno, insalubridad. Ingreso Mínimo Global de Referencia (IMGR).', 'Se divide por ramas de actividad. Pago quincenal (obrero) y mensual (empleado). El IMGR establece un piso que ninguna categoría puede perforar.'),
('76/75', 'Construcción (UOCRA)', 'Asistencia perfecta (20% s/básico quincenal)', 'Asistencia perfecta = 20% del básico. Fuerte impacto si se pierde.', 'Zonificación, altura, profundidad, aislamiento.', 'Régimen especial (Ley 22.250). NO aplica Ley de Contrato de Trabajo para despido (tiene el Fondo de Cese Laboral — libreta de aportes). Ojo con intimaciones: no aplican multas LCT.'),
('40/89', 'Camioneros', '1% por año (Art. 6.1.1)', 'No general (reemplazado por fijos de rama)', 'Kilometraje, viáticos (Arts. 71/72), cruce de frontera, horas extra al 100% sáb/dom.', 'Régimen de remuneración complejo. Los viáticos son remunerativos para SAC e indemnizaciones de manera proporcional.'),
('389/04', 'Gastronomía y Hotelería (UTHGRA)', '1% a 1.5% anual (según zona/año)', '10% del básico (Asistencia Perfecta Art. 11.2)', 'Alimentación (falla de provisión = 10% adicional), Plus Zona desfavorable, Plus Temporada.', 'Alta conflictividad por "media jornada" (art. 92 ter LCT) mal registrada. Convenio que se divide transversalmente por estrellas del local/hotel.'),
('122/75', 'Sanidad (FATSA Clínicas/Sanatorios)', '2% anual (Art. 10)', 'No tiene plus genérico, pero rigen descansos rotativos estrictos', 'Título profesional (Enfermería), Área cerrada (UTI/UCO), Riesgo.', 'Enfermería en terapia intensiva tiene reducción de jornada a 6 horas por insalubridad. Las horas por encima de eso son al 100%.'),
('18/75', 'Bancarios', '1.5% a 2% anual progresivo, remunerativo', 'No general, pero rigen bonos por cumplimiento y objetivos', 'Título, falla de caja, función jerárquica, zona desfavorable.', 'Jornada legal de 7.5 hs. Tienen el "Bono del Día del Bancario" (Noviembre) que es muy significativo y debe prorratearse para el despido/SAC.'),
('264/95', 'Seguros (Actividad Aseguradora)', '1% por año (Art. 13)', 'Premio por asistencia', 'Refrigerio/Almuerzo (muy alto), Título universitario, Idioma.', 'Sueldos elevados en comparación con comercio. El adicional por refrigerio es casi siempre remunerativo y pactado en paritarias constantemente.'),
('281/96', 'Maestranza (SOM)', '1% del básico por año', 'Asistencia Perfecta = 15% a 20% (muy relevante en el sueldo)', 'Limpieza en altura, lavado de alfombras, recolección de residuos médicos.', 'Se contrata mucho por jornadas de 4 horas (media jornada real). La pérdida del presentismo impacta enormemente, fuente de litigios frecuentes.'),
('Ley 26.727', 'Trabajo Agrario (UATRE)', '1% de 1 a 10 años. 1.5% a partir de 10 años.', 'No rige por convenio, sino por acuerdos provinciales de la CNTA.', 'Zonificación, vivienda provista, alimentación, tareas riesgosas (agroquímicos).', 'Régimen propio (Ley 26.727). No aplica la LCT salvo supletorio. Indemnización especial mínima de 2 meses de sueldo (Art. 76). Prohibición del uso de empresas de servicios temporarios.'),
('248/95', 'ASIMRA (Supervisores Metalúrgicos)', '1% por año (Art. 18)', 'Premio asistencia / puntualidad propio de rama', 'Título Secundario/Universitario, Turnos Rotativos.', 'Complementario a UOM. Salarios jerárquicos elevados. Frecuentes gratificaciones extraordinarias y sumas no remunerativas en paritarias.'),
('27/88', 'SMATA (Mecánicos y Afines FAATRA)', '1% por año', 'Premio por Asistencia (10% a 15% s/básico)', 'Título, Viáticos, Tareas Peligrosas.', 'Paritarias muy dinámicas (bimestrales/trimestrales). Incorporación constante de sumas no remunerativas al básico. Diferenciar bien horas normales de extras.'),
('244/94', 'Alimentación (Industria FTIA)', '1% anual acumulativo', 'Premio Asistencia Perfecta (muy estricto)', 'Turnos rotativos, Trabajo nocturno, Adicional Zona (ej. Patagonia).', 'Líneas de producción continuas. Fuerte peso del presentismo y el trabajo en días de descanso (sábados 13hs en adelante al 100%).'),
('371/03', 'Estaciones de Servicio (SOESGyPE Nación)', '2% por año aniversario', 'Asistencia Perfecta (10% s/básico)', 'Manejo de Fondos (Falla de Caja).', 'Régimen de turnos rotativos y trabajo nocturno habitual. El manejo de fondos es fijo y remunerativo.'),
('666/13', 'Estaciones de Servicio Córdoba (SINPECOR)', '1.5% anual (revisar año de ingreso)', 'Asistencia y Puntualidad', 'Manejo de caja, Sumas "No Remunerativas" fijadas en paritarias recurrentes.', 'CCT específico provincial (Córdoba). Históricamente negocian un % "no remunerativo" alto (12-15%) que incide en el cálculo de adicionales y aguinaldo. Clave para liquidación.'),
('STVN Nación', 'Viales Nacionales (Dirección Nacional Vialidad)', 'Compensación por años de servicio', 'Sujeto a normativa de Empleo Público Nacional', 'Zona Desfavorable (muy alto en Patagonia), Título, Riesgo.', 'Régimen de Empleo Público. Escalas congeladas por largos periodos recientemente, con exigencias de reapertura paritaria. Viáticos y movilidad son fundamentales.'),
('SIVIALCO', 'Viales Provincia de Córdoba (SiViCo)', '1% a 2% según régimen provincial', 'Asistencia atada a presentismo estatal', 'Bonificación especial por tarea vial, Desarraigo Campamento.', 'Empleo Provincial. Las paritarias suelen establecer actualizaciones bimestrales atadas al IPC (inflación de Córdoba) como gatillo.'),
('644/12', 'Petroleros Privados (Neuquén/Río Negro/La Pampa)', 'Acorde a antigüedad en empresa/yacimiento', 'Bono por presentismo y paz social', 'Adicional Zona Vaca Muerta (85%), Vianda, Horas Viaje, Bono Productividad.', 'Sueldos más altos del país. El "Salario Bruto Conformado Mensual Mínimo de Referencia" supera $1.740.000. Alta incidencia de sumas exentas de Ganancias.'),
('76/75 Z-B', 'Construcción UOCRA (Zona B Neuquén/Río Negro/Chubut)', 'Asistencia perfecta (20% s/básico quincenal)', 'Sumamente relevante. Se pierde ante ausencias injustificadas.', 'Zonificación B (Aumento superior al básico nacional Zona A), Altura, Campamento.', 'A diferencia de Buenos Aires (Zona A), en Zona B (Patagonia) el valor hora es un 20% más alto en promedio. Régimen Ley 22.250 (Libreta de Cese).'),
('SEP-CBA', 'SEP (Empleados Públicos Pcia. Córdoba)', 'Porcentaje acumulativo anualmente según escalafón', 'Asistencia Perfecta / Adicional Función', 'Riesgo, Insalubridad (Salud), Tareas Específicas, Alta carga Funcional.', 'NO APLICA LCT (Art. 2). Es régimen de Empleo Público. Las escalas y paritarias se ajustan directamente por IPC (Inflación de Córdoba). Alta litigiosidad por Riesgos de Trabajo (LRT sí aplica).'),
('SUOEM', 'SUOEM (Municipalidad de Córdoba)', '2% del básico por año acumulado', 'Bonificación propia municipal por dedicación', 'Prolongación de jornada (7ma hora), Cargas Familiares, Riesgo.', 'Empleo Público Municipal. Los salarios más altos de la región para estamentos del Estado. Paritarias con indexación salarial garantizada que impacta en pasivos y activos.');
UNLOCK TABLES;

-- Estructura de pro_escalas_salariales
DROP TABLE IF EXISTS `pro_escalas_salariales`;
CREATE TABLE `pro_escalas_salariales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cct_codigo` varchar(50) NOT NULL,
  `categoria` varchar(255) NOT NULL,
  `vigencia_desde` date NOT NULL,
  `vigencia_hasta` date DEFAULT NULL,
  `basico` decimal(15,2) NOT NULL,
  `adicionales_json` json DEFAULT NULL,
  `fuente` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_escala_cct` (`cct_codigo`),
  CONSTRAINT `fk_escala_cct` FOREIGN KEY (`cct_codigo`) REFERENCES `pro_ccts` (`cct_codigo`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para pro_escalas_salariales
LOCK TABLES `pro_escalas_salariales` WRITE;
INSERT INTO `pro_escalas_salariales` (`cct_codigo`, `categoria`, `vigencia_desde`, `vigencia_hasta`, `basico`, `adicionales_json`, `fuente`) VALUES ('130/75', 'Maestranza A', '2026-01-01', NULL, 480000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Maestranza B', '2026-01-01', NULL, 495000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Administrativo A', '2026-01-01', NULL, 510000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Administrativo B', '2026-01-01', NULL, 530000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Administrativo C', '2026-01-01', NULL, 555000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Cajero A', '2026-01-01', NULL, 520000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Cajero B', '2026-01-01', NULL, 540000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Vendedor A', '2026-01-01', NULL, 525000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Vendedor B', '2026-01-01', NULL, 550000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Auxiliar Especializado A', '2026-01-01', NULL, 535000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('130/75', 'Auxiliar Especializado B', '2026-01-01', NULL, 560000.0, '{}', 'FAECyS — Escala enero 2026 (referencial)'),
('260/75', 'Peón', '2026-01-01', NULL, 520000.0, '{}', 'UOM — Escala enero 2026 (referencial)'),
('260/75', 'Medio Oficial', '2026-01-01', NULL, 545000.0, '{}', 'UOM — Escala enero 2026 (referencial)'),
('260/75', 'Oficial', '2026-01-01', NULL, 575000.0, '{}', 'UOM — Escala enero 2026 (referencial)'),
('260/75', 'Oficial Especializado', '2026-01-01', NULL, 610000.0, '{}', 'UOM — Escala enero 2026 (referencial)'),
('260/75', 'Oficial Múltiple', '2026-01-01', NULL, 640000.0, '{}', 'UOM — Escala enero 2026 (referencial)'),
('260/75', 'Administrativo 1ra', '2026-01-01', NULL, 560000.0, '{}', 'UOM — Escala enero 2026 (referencial)'),
('260/75', 'Administrativo 2da', '2026-01-01', NULL, 530000.0, '{}', 'UOM — Escala enero 2026 (referencial)'),
('76/75', 'Ayudante', '2026-01-01', NULL, 490000.0, '{}', 'UOCRA — Escala enero 2026 (referencial)'),
('76/75', 'Medio Oficial', '2026-01-01', NULL, 520000.0, '{}', 'UOCRA — Escala enero 2026 (referencial)'),
('76/75', 'Oficial', '2026-01-01', NULL, 560000.0, '{}', 'UOCRA — Escala enero 2026 (referencial)'),
('76/75', 'Oficial Especializado', '2026-01-01', NULL, 600000.0, '{}', 'UOCRA — Escala enero 2026 (referencial)'),
('76/75', 'Capataz', '2026-01-01', NULL, 650000.0, '{}', 'UOCRA — Escala enero 2026 (referencial)'),
('40/89', 'Peón General', '2026-01-01', NULL, 580000.0, '{}', 'FEDCAM — Escala enero 2026 (referencial)'),
('40/89', 'Conductor Liviano', '2026-01-01', NULL, 650000.0, '{}', 'FEDCAM — Escala enero 2026 (referencial)'),
('40/89', 'Conductor Pesado', '2026-01-01', NULL, 720000.0, '{}', 'FEDCAM — Escala enero 2026 (referencial)'),
('40/89', 'Conductor Especial', '2026-01-01', NULL, 780000.0, '{}', 'FEDCAM — Escala enero 2026 (referencial)'),
('40/89', 'Acompañante', '2026-01-01', NULL, 560000.0, '{}', 'FEDCAM — Escala enero 2026 (referencial)'),
('389/04', 'Peón General', '2026-01-01', NULL, 470000.0, '{}', 'UTHGRA — Escala enero 2026 (referencial)'),
('389/04', 'Ayudante de Cocina', '2026-01-01', NULL, 490000.0, '{}', 'UTHGRA — Escala enero 2026 (referencial)'),
('389/04', 'Mozo/a', '2026-01-01', NULL, 495000.0, '{}', 'UTHGRA — Escala enero 2026 (referencial)'),
('389/04', 'Cocinero', '2026-01-01', NULL, 530000.0, '{}', 'UTHGRA — Escala enero 2026 (referencial)'),
('389/04', 'Jefe de Cocina', '2026-01-01', NULL, 580000.0, '{}', 'UTHGRA — Escala enero 2026 (referencial)'),
('389/04', 'Recepcionista', '2026-01-01', NULL, 510000.0, '{}', 'UTHGRA — Escala enero 2026 (referencial)'),
('389/04', 'Conserje', '2026-01-01', NULL, 500000.0, '{}', 'UTHGRA — Escala enero 2026 (referencial)'),
('122/75', 'Mucama/o', '2026-01-01', NULL, 460000.0, '{}', 'FATSA — Escala enero 2026 (referencial)'),
('122/75', 'Administrativo/a de 1ra', '2026-01-01', NULL, 510000.0, '{}', 'FATSA — Escala enero 2026 (referencial)'),
('122/75', 'Enfermero/a', '2026-01-01', NULL, 580000.0, '{}', 'FATSA — Escala enero 2026 (referencial)'),
('122/75', 'Instrumentadora', '2026-01-01', NULL, 620000.0, '{}', 'FATSA — Escala enero 2026 (referencial)'),
('122/75', 'Técnico/a (Rayos/Hemoterapia)', '2026-01-01', NULL, 635000.0, '{}', 'FATSA — Escala enero 2026 (referencial)'),
('18/75', 'Auxiliar Grado 1 (Inicial)', '2026-01-01', NULL, 1200000.0, '{}', 'La Bancaria — Escala enero 2026 (referencial)'),
('18/75', 'Auxiliar Grado 2', '2026-01-01', NULL, 1280000.0, '{}', 'La Bancaria — Escala enero 2026 (referencial)'),
('18/75', 'Cajero (con falla)', '2026-01-01', NULL, 1350000.0, '{}', 'La Bancaria — Escala enero 2026 (referencial)'),
('18/75', 'Jefe de Sección', '2026-01-01', NULL, 1600000.0, '{}', 'La Bancaria — Escala enero 2026 (referencial)'),
('18/75', 'Gerente de Sucursal', '2026-01-01', NULL, 2100000.0, '{}', 'La Bancaria — Escala enero 2026 (referencial)'),
('264/95', 'Grupo 1 (Maestranza)', '2026-01-01', NULL, 710000.0, '{}', 'SSN / Sindicato del Seguro — Escala enero 2026 (referencial)'),
('264/95', 'Grupo 2 (Auxiliar)', '2026-01-01', NULL, 760000.0, '{}', 'SSN / Sindicato del Seguro — Escala enero 2026 (referencial)'),
('264/95', 'Grupo 3 (Administrativo)', '2026-01-01', NULL, 820000.0, '{}', 'SSN / Sindicato del Seguro — Escala enero 2026 (referencial)'),
('264/95', 'Grupo 4 (Especializado)', '2026-01-01', NULL, 900000.0, '{}', 'SSN / Sindicato del Seguro — Escala enero 2026 (referencial)'),
('264/95', 'Grupo 5 (Jefe Sección)', '2026-01-01', NULL, 1050000.0, '{}', 'SSN / Sindicato del Seguro — Escala enero 2026 (referencial)');
INSERT INTO `pro_escalas_salariales` (`cct_codigo`, `categoria`, `vigencia_desde`, `vigencia_hasta`, `basico`, `adicionales_json`, `fuente`) VALUES ('281/96', 'Oficial', '2026-01-01', NULL, 430000.0, '{}', 'SOM — Escala enero 2026 (referencial)'),
('281/96', 'Coordinador', '2026-01-01', NULL, 460000.0, '{}', 'SOM — Escala enero 2026 (referencial)'),
('281/96', 'Supervisor', '2026-01-01', NULL, 510000.0, '{}', 'SOM — Escala enero 2026 (referencial)'),
('281/96', 'Especializado (Vidrieros)', '2026-01-01', NULL, 480000.0, '{}', 'SOM — Escala enero 2026 (referencial)'),
('Ley 26.727', 'Peón Único', '2026-01-01', NULL, 450000.0, '{}', 'CNTA / UATRE — Escala enero 2026 (referencial)'),
('Ley 26.727', 'Peón Especializado (Arador/Ordeñador)', '2026-01-01', NULL, 490000.0, '{}', 'CNTA / UATRE — Escala enero 2026 (referencial)'),
('Ley 26.727', 'Tractorista / Maquinista', '2026-01-01', NULL, 530000.0, '{}', 'CNTA / UATRE — Escala enero 2026 (referencial)'),
('Ley 26.727', 'Encargado', '2026-01-01', NULL, 580000.0, '{}', 'CNTA / UATRE — Escala enero 2026 (referencial)'),
('Ley 26.727', 'Administrativo', '2026-01-01', NULL, 480000.0, '{}', 'CNTA / UATRE — Escala enero 2026 (referencial)'),
('248/95', 'Cat. 1 (Administrativo)', '2026-01-01', NULL, 780000.0, '{}', 'ASIMRA — Escala enero 2026 (referencial)'),
('248/95', 'Cat. 2 (Técnico)', '2026-01-01', NULL, 850000.0, '{}', 'ASIMRA — Escala enero 2026 (referencial)'),
('248/95', 'Cat. 3 (Supervisor de 2da)', '2026-01-01', NULL, 930000.0, '{}', 'ASIMRA — Escala enero 2026 (referencial)'),
('248/95', 'Cat. 4 (Supervisor de 1ra)', '2026-01-01', NULL, 1059880.0, '{}', 'ASIMRA — Escala enero 2026 (referencial)'),
('27/88', 'Maestranza', '2026-01-01', NULL, 540000.0, '{}', 'SMATA/FAATRA — Escala 2025/2026 (referencial)'),
('27/88', 'Ayudante', '2026-01-01', NULL, 580000.0, '{}', 'SMATA/FAATRA — Escala 2025/2026 (referencial)'),
('27/88', 'Medio Oficial', '2026-01-01', NULL, 630000.0, '{}', 'SMATA/FAATRA — Escala 2025/2026 (referencial)'),
('27/88', 'Oficial de Primera', '2026-01-01', NULL, 720000.0, '{}', 'SMATA/FAATRA — Escala 2025/2026 (referencial)'),
('27/88', 'Oficial Inspector', '2026-01-01', NULL, 810000.0, '{}', 'SMATA/FAATRA — Escala 2025/2026 (referencial)'),
('244/94', 'Medio Oficial', '2026-01-01', NULL, 560000.0, '{}', 'FTIA — Escala 2026 (estimada)'),
('244/94', 'Oficial', '2026-01-01', NULL, 610000.0, '{}', 'FTIA — Escala 2026 (estimada)'),
('244/94', 'Oficial General', '2026-01-01', NULL, 670000.0, '{}', 'FTIA — Escala 2026 (estimada)'),
('244/94', 'Mantenimiento (Inicial)', '2026-01-01', NULL, 700000.0, '{}', 'FTIA — Escala 2026 (estimada)'),
('371/03', 'Operario de Playa', '2026-01-01', NULL, 680000.0, '{}', 'SOESGyPE — Escala 2025/2026 (referencial)'),
('371/03', 'Operario de MiniShop', '2026-01-01', NULL, 680000.0, '{}', 'SOESGyPE — Escala 2025/2026 (referencial)'),
('371/03', 'Administrativo', '2026-01-01', NULL, 690000.0, '{}', 'SOESGyPE — Escala 2025/2026 (referencial)'),
('371/03', 'Encargado de Turno', '2026-01-01', NULL, 720000.0, '{}', 'SOESGyPE — Escala 2025/2026 (referencial)'),
('666/13', 'Operario de Playa', '2026-01-01', NULL, 790000.0, '{}', 'SINPECOR/FECAC — Escala base 2025 (proyectada)'),
('666/13', 'Operario Lavador', '2026-01-01', NULL, 800000.0, '{}', 'SINPECOR/FECAC — Escala base 2025 (proyectada)'),
('666/13', 'Administrativo', '2026-01-01', NULL, 810000.0, '{}', 'SINPECOR/FECAC — Escala base 2025 (proyectada)'),
('666/13', 'Encargado de Turno', '2026-01-01', NULL, 820000.0, '{}', 'SINPECOR/FECAC — Escala base 2025 (proyectada)'),
('STVN Nación', 'Cat. 1 (Inicial Operativo)', '2026-01-01', NULL, 580000.0, '{}', 'STVN — Escala Sectorial'),
('STVN Nación', 'Cat. 8 (Medio)', '2026-01-01', NULL, 710000.0, '{}', 'STVN — Escala Sectorial'),
('STVN Nación', 'Cat. 15 (Jerárquico)', '2026-01-01', NULL, 950000.0, '{}', 'STVN — Escala Sectorial'),
('SIVIALCO', 'Peón Caminero', '2026-01-01', NULL, 620000.0, '{}', 'SIVIALCO Pcia. Córdoba'),
('SIVIALCO', 'Maquinista Vial', '2026-01-01', NULL, 780000.0, '{}', 'SIVIALCO Pcia. Córdoba'),
('SIVIALCO', 'Administrativo', '2026-01-01', NULL, 650000.0, '{}', 'SIVIALCO Pcia. Córdoba'),
('644/12', 'Boca de Pozo (Peón)', '2026-01-01', NULL, 1740000.0, '{}', 'Sindicato Petroleros Privados (Vaca Muerta)'),
('644/12', 'Medio Oficial', '2026-01-01', NULL, 1950000.0, '{}', 'Sindicato Petroleros Privados (Vaca Muerta)'),
('644/12', 'Enganchador / Maquinista', '2026-01-01', NULL, 2300000.0, '{}', 'Sindicato Petroleros Privados (Vaca Muerta)'),
('644/12', 'Recorredor', '2026-01-01', NULL, 2500000.0, '{}', 'Sindicato Petroleros Privados (Vaca Muerta)'),
('76/75 Z-B', 'Ayudante (Hora)', '2026-01-01', NULL, 4274.0, '{}', 'UOCRA — Escalas Zona B (dic 2025/ene 2026)'),
('76/75 Z-B', 'Medio Oficial (Hora)', '2026-01-01', NULL, 4660.0, '{}', 'UOCRA — Escalas Zona B (dic 2025/ene 2026)'),
('76/75 Z-B', 'Oficial (Hora)', '2026-01-01', NULL, 5100.0, '{}', 'UOCRA — Escalas Zona B (dic 2025/ene 2026)'),
('76/75 Z-B', 'Oficial Especializado (Hora)', '2026-01-01', NULL, 5847.0, '{}', 'UOCRA — Escalas Zona B (dic 2025/ene 2026)'),
('SEP-CBA', 'Cat. 1 (Ingreso Administrativo)', '2026-01-01', NULL, 650000.0, '{}', 'SEP Cba / Escalas IPC'),
('SEP-CBA', 'Enfermero/a Profesional', '2026-01-01', NULL, 820000.0, '{}', 'SEP Cba / Escalas IPC'),
('SEP-CBA', 'Cat. Específica (Mando Medio)', '2026-01-01', NULL, 980000.0, '{}', 'SEP Cba / Escalas IPC'),
('SUOEM', 'Cat. 1 (Inicial Operativo/Admin)', '2026-01-01', NULL, 1800000.0, '{}', 'Gobierno Abierto / SUOEM'),
('SUOEM', 'Cat. Intermedia (Administrativo)', '2026-01-01', NULL, 2300000.0, '{}', 'Gobierno Abierto / SUOEM'),
('SUOEM', 'Jefatura / Profesional', '2026-01-01', NULL, 3000000.0, '{}', 'Gobierno Abierto / SUOEM');
UNLOCK TABLES;

-- Estructura de pro_normativa
DROP TABLE IF EXISTS `pro_normativa`;
CREATE TABLE `pro_normativa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ley` varchar(100) NOT NULL,
  `articulo` varchar(100) DEFAULT NULL,
  `texto` text NOT NULL,
  `estado` varchar(50) DEFAULT 'vigente',
  `fecha_modificacion` date DEFAULT NULL,
  `notas` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para pro_normativa
LOCK TABLES `pro_normativa` WRITE;
INSERT INTO `pro_normativa` (`ley`, `articulo`, `texto`, `estado`, `fecha_modificacion`, `notas`) VALUES ('LCT 20.744', 'Art. 14 bis CN', '[Protección constitucional del trabajo]
El trabajo en sus diversas formas gozará de la protección de las leyes, las que asegurarán al trabajador: condiciones dignas y equitativas de labor; jornada limitada; descanso y vacaciones pagados; retribución justa; salario mínimo vital y móvil; igual remuneración por igual tarea; participación en las ganancias de las empresas; protección contra el despido arbitrario; estabilidad del empleado público; organización sindical libre y democrática.', 'vigente', NULL, 'Base constitucional de todo el derecho laboral argentino. Principio protectorio.'),
('LCT 20.744', 'Art. 1', '[Fuentes de regulación]
El contrato de trabajo y la relación de trabajo se rigen: a) Por esta ley. b) Por las leyes y estatutos profesionales. c) Por las convenciones colectivas de trabajo. d) Por la voluntad de las partes. e) Por los usos y costumbres.', 'vigente', NULL, 'Jerarquía normativa: Ley > CCT > Contrato individual. Principio de norma más favorable.'),
('LCT 20.744', 'Art. 4', '[Concepto de trabajo]
Constituye trabajo, a los fines de esta ley, toda actividad lícita que se preste en favor de quien tiene la facultad de dirigirla, mediante una remuneración. El contrato de trabajo tiene como principal objeto la actividad productiva y creadora del hombre en sí.', 'vigente', NULL, 'Define subordinación como nota tipificante. Actividad + dirección + remuneración = relación laboral.'),
('LCT 20.744', 'Art. 7', '[Condiciones menos favorables — Nulidad]
Las partes, en ningún caso, pueden pactar condiciones menos favorables para el trabajador que las dispuestas en las normas legales, convenciones colectivas de trabajo o laudo con fuerza de tales, o que resulten contrarias a las mismas.', 'vigente', NULL, 'Principio de irrenunciabilidad. Orden público laboral.'),
('LCT 20.744', 'Art. 9', '[Principio de la norma más favorable — In dubio pro operario]
En caso de duda sobre la aplicación de normas legales o convencionales prevalecerá la más favorable al trabajador. Si la duda recayese en la interpretación o alcance de la ley, o en apreciación de la prueba en los casos concretos, los jueces o encargados de aplicarla se decidirán en el sentido más favorable al trabajador.', 'vigente', NULL, 'Triple operatividad: norma más favorable, condición más beneficiosa, in dubio pro operario.'),
('LCT 20.744', 'Art. 10', '[Conservación del contrato]
En caso de duda las situaciones deben resolverse en favor de la continuidad o subsistencia del contrato.', 'vigente', NULL, 'Principio de continuidad. Preferencia por la estabilidad.'),
('LCT 20.744', 'Art. 12', '[Irrenunciabilidad]
Será nula y sin valor toda convención de partes que suprima o reduzca los derechos previstos en esta ley, los estatutos profesionales, las convenciones colectivas de trabajo, ya sea al tiempo de su celebración o de su ejecución, o del ejercicio de derechos provenientes de su extinción.', 'vigente', NULL, 'Irrenunciabilidad absoluta. Excepción: conciliación ante autoridad competente (Art. 15 LCT).'),
('LCT 20.744', 'Art. 21', '[Contrato de trabajo]
Habrá contrato de trabajo, cualquiera sea su forma o denominación, siempre que una persona física se obligue a realizar actos, ejecutar obras o prestar servicios en favor de la otra y bajo la dependencia de ésta, durante un período determinado o indeterminado de tiempo, mediante el pago de una remuneración.', 'vigente', NULL, 'Primacía de la realidad sobre las formas. No importa cómo se denomine, importa qué es.'),
('LCT 20.744', 'Art. 22', '[Relación de trabajo]
Habrá relación de trabajo cuando una persona realice actos, ejecute obras o preste servicio en favor de otra, bajo la dependencia de ésta en forma voluntaria y mediante el pago de una remuneración, cualquiera sea el acto que le dé origen.', 'vigente', NULL, 'La relación de trabajo puede existir sin contrato formal.'),
('LCT 20.744', 'Art. 23', '[Presunción de existencia del contrato de trabajo]
El hecho de la prestación de servicios hace presumir la existencia de un contrato de trabajo, salvo que por las circunstancias, las relaciones o causas que lo motiven se demostrase lo contrario. Esa presunción operará igualmente aun cuando se utilicen figuras no laborales, para caracterizar al contrato, y en tanto que por las circunstancias no sea dado calificar de empresario a quien presta el servicio.', 'vigente — MODIFICADO por Ley 27.802 (2026)', NULL, 'CLAVE: La Ley 27.802 incorporó 3 controles que debilitan la presunción (facturación + pago bancario + contrato escrito). Si los 3 están presentes, la presunción NO opera automáticamente. Ver Art. 23 Ley 27.802.'),
('LCT 20.744', 'Art. 26', '[Empleador]
Se considera "empleador" a la persona física o conjunto de ellas, o jurídica, tenga o no personalidad jurídica propia, que requiera los servicios de un trabajador.', 'vigente', NULL, 'Concepto amplio. Incluye personas humanas y jurídicas.'),
('LCT 20.744', 'Art. 29', '[Interposición y mediación — Fraude]
Los trabajadores que habiendo sido contratados por terceros con vista a proporcionarlos a las empresas, serán considerados empleados directos de quien utilice su prestación. En tal supuesto, y cualquiera que sea el acto o estipulación que al efecto concierten, los terceros contratantes y la empresa para la cual los trabajadores presten o hayan prestado servicios responderán solidariamente de todas las obligaciones emergentes de la relación laboral.', 'vigente', NULL, 'Fraude laboral por interposición de persona. El trabajador es empleado directo de quien usa su trabajo. Solidaridad entre intermediario y empresa usuaria.'),
('LCT 20.744', 'Art. 30', '[Subcontratación y delegación — Solidaridad]
Quienes cedan total o parcialmente a otros el establecimiento o explotación habilitado a su nombre, o contraten o subcontraten, cualquiera sea el acto que le dé origen, trabajos o servicios correspondientes a la actividad normal y específica propia del establecimiento, dentro o fuera de su ámbito, deberán exigir a sus contratistas o subcontratistas el adecuado cumplimiento de las normas relativas al trabajo y los organismos de seguridad social.', 'vigente — MODIFICADO por Ley 27.802 (2026)', NULL, 'CLAVE: La Ley 27.802 definió 5 controles de exención para el principal (CUIL, aportes, pago directo, CBU, ART). Si cumple los 5, queda EXENTO de responsabilidad solidaria. Ver análisis en sistema.'),
('LCT 20.744', 'Art. 103', '[Concepto de remuneración]
A los fines de esta ley, se entiende por remuneración la contraprestación que debe percibir el trabajador como consecuencia del contrato de trabajo. Dicha remuneración no podrá ser inferior al salario mínimo vital.', 'vigente', NULL, 'Base para todo cálculo indemnizatorio. Incluye todo concepto remunerativo.'),
('LCT 20.744', 'Art. 103 bis', '[Beneficios sociales]
Se denominan beneficios sociales a las prestaciones de naturaleza jurídica de seguridad social, no remunerativas, no dinerarias, no acumulables ni sustituibles en dinero, que brinda el empleador al trabajador por sí o por medio de terceros.', 'vigente', NULL, 'Diferencia clave entre remunerativo y no remunerativo. Impacta en base de cálculo indemnizatorio.'),
('LCT 20.744', 'Art. 121/122/123', '[Sueldo Anual Complementario (SAC / Aguinaldo)]
Art. 121: Se entiende por SAC la doceava parte del total de las remuneraciones. Art. 122: Se abona en dos cuotas: 30/06 y 18/12 de cada año. Art. 123: Extinción del contrato: el trabajador tiene derecho al SAC proporcional al tiempo trabajado en el semestre.', 'vigente', NULL, 'SAC = mejor remuneración mensual devengada / 2 x (días trabajados en el semestre / días del semestre). Siempre se debe al extinguir el contrato. Base: mejor remuneración normal y habitual.'),
('LCT 20.744', 'Art. 150', '[Licencia ordinaria — Duración de vacaciones]
El trabajador gozará de un período mínimo y continuado de descanso anual remunerado: a) 14 días corridos con antigüedad menor a 5 años. b) 21 días corridos con antigüedad de 5 a 10 años. c) 28 días corridos con antigüedad de 10 a 20 años. d) 35 días corridos con antigüedad mayor a 20 años.', 'vigente', NULL, 'La antigüedad se computa al 31/12 del año que correspondan. Se requiere haber prestado servicios la MITAD de los días hábiles del año calendario. Si no se alcanza, 1 día cada 20 trabajados.'),
('LCT 20.744', 'Art. 156', '[Indemnización por vacaciones no gozadas]
Cuando por cualquier causa se produjera la extinción del contrato de trabajo, el trabajador tendrá derecho a percibir una indemnización equivalente al salario correspondiente al período de descanso proporcional a la fracción del año trabajada.', 'vigente', NULL, 'Se calcula igual que las vacaciones pero con base en el salario al momento de la extinción.'),
('LCT 20.744', 'Art. 196', '[Jornada de trabajo — Determinación]
La extensión de la jornada de trabajo es uniforme para toda la Nación y se regirá por la ley 11.544, con exclusión de toda disposición provincial en contrario.', 'vigente', NULL, 'Ley 11.544: 8 hs diarias / 48 hs semanales. Nocturna: 7 hs. Insalubre: 6 hs.'),
('LCT 20.744', 'Art. 201', '[Horas extraordinarias]
El empleador deberá abonar al trabajador que prestare servicios en horas suplementarias, medie o no autorización del organismo administrativo competente, un recargo del 50% calculado sobre el salario habitual, si se tratare de días comunes, y del 100% en días sábado después de las 13 horas, domingo y feriados.', 'vigente', NULL, 'Horas extra: 50% días hábiles, 100% sábados/domingos/feriados. Base de cálculo: salario habitual.'),
('LCT 20.744', 'Art. 231', '[Preaviso — Plazos]
El contrato de trabajo no podrá ser disuelto por voluntad de una de las partes, sin previo aviso, o en su defecto, indemnización además de la que corresponda al trabajador por su antigüedad en el empleo. El preaviso, cuando las partes no lo fijen en un plazo mayor, deberá darse con la anticipación siguiente: a) Por el trabajador, de QUINCE (15) días. b) Por el empleador, de QUINCE (15) días cuando el trabajador se encontrare en período de prueba; de UN MES cuando la antigüedad no exceda de CINCO (5) años y de DOS MESES cuando fuere superior.', 'vigente', NULL, 'Si no se otorga preaviso, corresponde indemnización sustitutiva. Se integra con SAC proporcional sobre el preaviso.'),
('LCT 20.744', 'Art. 232', '[Indemnización sustitutiva del preaviso]
La parte que omita el preaviso o lo otorgue de modo insuficiente deberá abonar a la otra una indemnización sustitutiva equivalente a la remuneración que correspondería al trabajador durante los plazos señalados en el artículo 231.', 'vigente', NULL, 'Equivale al salario de 1 o 2 meses según antigüedad (< o > 5 años).'),
('LCT 20.744', 'Art. 233', '[Integración del mes de despido]
Los plazos del artículo 231 correrán a partir del primer día del mes siguiente al de la notificación del preaviso. Cuando la extinción del contrato de trabajo dispuesta por el empleador se produzca sin preaviso y en fecha que no coincida con el último día del mes, la indemnización sustitutiva debida al trabajador se integrará con una suma igual a los salarios por los días faltantes hasta el último día del mes en el que se produjera el despido.', 'vigente', NULL, 'Si despide el día 15, paga del 16 al 30/31 como integración. Genera SAC proporcional.'),
('LCT 20.744', 'Art. 242', '[Justa causa del despido]
Una de las partes podrá hacer denuncia del contrato de trabajo en caso de inobservancia por parte de la otra de las obligaciones resultantes del mismo que configuren injuria y que, por su gravedad, no consienta la prosecución de la relación.', 'vigente', NULL, 'La valoración de la injuria la hace el juez. Proporcionalidad entre falta y sanción. Contemporaneidad: la sanción debe ser inmediata al conocimiento de la falta.'),
('LCT 20.744', 'Art. 245', '[Indemnización por antigüedad o despido]
En los casos de despido dispuesto por el empleador sin justa causa, habiendo o no mediado preaviso, éste deberá abonar al trabajador una indemnización equivalente a UN MES de sueldo por cada año de servicio o fracción mayor de TRES meses, tomando como base la mejor remuneración mensual, normal y habitual devengada durante el último año o durante el tiempo de prestación de servicios si éste fuera menor.', 'vigente', NULL, 'FÓRMULA: Mejor remuneración × años de antigüedad (mínimo 1). BASE: mejor remuneración NORMAL y HABITUAL del último año. TOPE: 3 x salario CCT promedio mensual del puesto. PISO: 67% del primer mes de la base (fallo Vizzoti CSJN). NO incluye SAC, vacaciones no gozadas ni previsión horas extra.'),
('LCT 20.744', 'Art. 246', '[Despido indirecto]
Cuando el trabajador hiciese denuncia del contrato de trabajo fundado en justa causa, tendrá derecho a las indemnizaciones previstas en los artículos 232, 233 y 245.', 'vigente', NULL, 'El trabajador se considera despedido por culpa del empleador. Requiere intimación previa (telegrama). Debe acreditar la injuria.'),
('LCT 20.744', 'Art. 247', '[Despido por fuerza mayor o disminución de trabajo]
En los casos en que el despido fuese dispuesto por causa de fuerza mayor o por falta o disminución de trabajo no imputable al empleador fehacientemente justificada, el trabajador tendrá derecho a percibir una indemnización equivalente a la MITAD de la prevista en el artículo 245.', 'vigente', NULL, 'Indemnización reducida al 50%. Carga probatoria: empleador. Orden de antigüedad inverso.'),
('LCT 20.744', 'Art. 248', '[Indemnización por fallecimiento del trabajador]
En caso de muerte del trabajador, las personas enumeradas en el artículo 38 del Decreto-ley 18.037/69 tendrán derecho, mediante la sola acreditación del vínculo, en el orden y prelación allí establecido, a percibir una indemnización igual a la prevista en el artículo 247 de esta ley.', 'vigente', NULL, 'Indemnización = 50% del Art. 245. Beneficiarios: cónyuge, hijos, ascendientes.'),
('LCT 20.744', 'Art. 255', '[Reingreso del trabajador — Deducción]
La antigüedad del trabajador se establecerá conforme a lo dispuesto en los artículos 18 y 19 de esta ley, pero si el trabajador que reingresa a las órdenes del mismo empleador hubiere recibido gratificación por cese de servicios en la oportunidad anterior, la suma correspondiente será deducida de la que le corresponda.', 'vigente', NULL, 'Evita doble cobro. Deduce la indemnización anterior del mismo empleador.'),
('LCT 20.744', 'Art. 80', '[Deber de entregar certificados de trabajo]
Cuando el contrato de trabajo se extinguiere por cualquier causa, el empleador estará obligado a entregar al trabajador un certificado de trabajo. Si el empleador no hiciera entrega de la constancia o del certificado previstos dentro de los TREINTA (30) días corridos de extinguido el contrato de trabajo, el trabajador podrá requerir el cumplimiento. En caso de incumplimiento, el empleador será sancionado con una indemnización a favor del trabajador equivalente a TRES veces la mejor remuneración mensual, normal y habitual devengada.', 'vigente', NULL, 'Multa: 3 sueldos. Requisitos: intimar por TCL DESPUÉS de 30 días. No fue derogada por Ley Bases 27.742. Certificado incluye: datos personales, categoría, remuneraciones, aportes.'),
('LCT 20.744', 'Art. 256', '[Prescripción — Plazo]
Prescriben a los DOS (2) años las acciones relativas a créditos provenientes de las relaciones individuales de trabajo y, en general, de disposiciones de convenios colectivos, laudos con eficacia de convenios colectivos y disposiciones legales o reglamentarias del Derecho del Trabajo.', 'vigente', NULL, 'PLAZO CRÍTICO: 2 años desde que cada crédito es exigible. Se interrumpe por actuaciones administrativas (SECLO) o judiciales. Se suspende por interpelación fehaciente (telegrama) por 6 meses.'),
('LCT 20.744', 'Art. 260', '[Pago insuficiente]
El pago insuficiente de obligaciones originadas en las relaciones laborales efectuado por un empleador será considerado como entrega a cuenta del total adeudado, aunque se reciba sin reservas, y quedará expedita la acción del trabajador para reclamar el pago de la diferencia que correspondiere, por todo el tiempo de la prescripción.', 'vigente', NULL, 'Protege diferencias salariales retroactivas. El cobro sin reserva NO implica renuncia. Clave para reclamos por mala categorización, horas extra impagas, etc.'),
('Ley 27.802', 'Art. 1', '[Objeto]
La presente ley tiene por objeto simplificar y modernizar el régimen sancionatorio del empleo no registrado, promoviendo la formalización laboral y sustituyendo el sistema de multas de las Leyes 24.013 y 25.323.', 'vigente', NULL, 'Deroga explícitamente los arts. 8, 9, 10 y 15 de la Ley 24.013 y los arts. 1 y 2 de la Ley 25.323. Establece un régimen nuevo basado en fraude y daño, no en multas.'),
('Ley 27.802', 'Art. 23 LCT (mod.)', '[Presunción de existencia — Controles de desactivación]
El hecho de la prestación de servicios hace presumir la existencia de un contrato de trabajo. Sin embargo, cuando se acrediten conjuntamente los siguientes elementos, la presunción NO operará automáticamente: 1) Existencia de facturación regular por parte del prestador de servicios. 2) Pagos bancarios documentados al prestador. 3) Contrato escrito formalizado entre las partes.', 'vigente', NULL, 'CLAVE DEL SISTEMA: Los 3 controles son ACUMULATIVOS. Si falta 1, la presunción OPERA. Si los 3 están presentes, se invierte la carga: el trabajador debe probar subordinación efectiva. No elimina la relación laboral, solo cambia la carga probatoria. Facturación: monotributo, servicios, etc. Pago bancario: transferencias, no efectivo. Contrato escrito: locación de servicios, locación de obra, etc.'),
('Ley 27.802', 'Análisis: Presunción — 0 controles', '[Escenario: Sin documentación contradictoria]
Si no existen facturación, pago bancario ni contrato escrito: PRESUNCIÓN MÁXIMA. La relación laboral se presume en los términos más amplios. Recomendación: Acreditar subordinación por testigos, horarios, órdenes de trabajo.', 'vigente', NULL, 'Caso más favorable para el trabajador. Posición procesal fuerte.'),
('Ley 27.802', 'Análisis: Presunción — 3 controles', '[Escenario: 3 elementos presentes (no opera)]
Si existen los 3 controles (facturación + pago bancario + contrato escrito): PRESUNCIÓN NO OPERA. Sin embargo, la relación laboral puede existir igual. El trabajador debe PROBAR: subordinación jurídica, económica y técnica. Elementos: órdenes, horario impuesto, exclusividad, herramientas del principal, etc.', 'vigente', NULL, 'No significa que NO hay relación laboral. Solo que la carga probatoria se invierte. Doctrina: "Primacía de la realidad" sigue vigente (Art. 14 LCT).'),
('Ley 27.802', 'Art. 30 LCT (mod.)', '[Exención de solidaridad — 5 controles]
El principal que contrate o subcontrate trabajos o servicios correspondientes a la actividad normal y específica quedará EXENTO de responsabilidad solidaria cuando acredite haber ejercido los siguientes controles: 1) Verificación del CUIL de los trabajadores del contratista. 2) Control de aportes a la seguridad social (SRT). 3) Pago de nómina directo a los trabajadores (no a intermediarios). 4) Verificación de CBU del trabajador. 5) Cobertura ART vigente del contratista.', 'vigente', NULL, 'Los 5 controles son ACUMULATIVOS para la exención total. Si faltan controles: solidaridad PROPORCIONAL al riesgo. 5/5 = EXENTO. 4/5 = Factor 2. 3/5 = Factor 3. 2/5 = Factor 4. 1/5 = Factor 5. 0/5 = Factor 6.'),
('Ley 27.802', 'Fraude Laboral', '[Nuevo régimen de fraude — Sustituye multas]
El encuadramiento fraudulento de una relación laboral bajo figuras no laborales (monotributo, locación de servicios, cooperativas, etc.) constituye fraude laboral en los términos del Art. 14 LCT. Consecuencias: - Se considera relación laboral plena desde el inicio real. - Registración retroactiva obligatoria. - Daños y perjuicios adicionales (daño complementario). - NO aplican las multas de las leyes derogadas (24.013 / 25.323).', 'vigente', NULL, 'Cambio de paradigma: de "multa tarifada" a "daño real acreditado". El juez puede fijar el daño complementario según la situación concreta. Más flexible para el juez, pero requiere mayor prueba del trabajador.'),
('Ley 27.802', 'Daño Complementario', '[Reparación por daño real del empleo no registrado]
El trabajador que acredite que la falta de registración o la registración deficiente le causó un daño concreto podrá reclamar la reparación del daño complementario, que incluye: - Pérdida de aportes jubilatorios no efectuados. - Pérdida de cobertura de obra social. - Daño moral por la situación de informalidad. - Lucro cesante por la diferencia entre lo cobrado y lo debido.', 'vigente', NULL, 'Reemplaza las multas fijas de la Ley 24.013. Ventaja: puede superar las multas antiguas si el daño es grande. Desventaja: requiere prueba del daño concreto (no es automático). El juez evalúa cada caso. No hay topes tarifados.'),
('Ley 24.013 (DEROGADA parcialmente)', 'Arts. 8, 9, 10, 15', '[Multas por empleo no registrado — DEROGADAS]
Art. 8: Multa = 25% de las remuneraciones devengadas desde el inicio. Art. 9: Deficiente registración de fecha = diferencia de remuneraciones. Art. 10: Deficiente registración de remuneración = 25% diferencia. Art. 15: Duplicación de multas si no registra tras intimación.', 'DEROGADO por Ley 27.802 (2026)', NULL, 'PRECAUCIÓN: Para hechos ANTERIORES a la vigencia de la Ley 27.802, las multas de la 24.013 pueden seguir siendo aplicables bajo el principio de derecho transitorio (norma más favorable / ultractividad). Consultar caso por caso.'),
('Ley 25.323 (DEROGADA)', 'Arts. 1 y 2', '[Incremento indemnizatorio — DEROGADO]
Art. 1: Las indemnizaciones previstas por los Arts. 245, 232, 233 LCT serán incrementadas al doble cuando se trate de una relación laboral no registrada. Art. 2: Incremento del 50% si el empleador no paga indemnización y el trabajador debe iniciar acción judicial o administrativa.', 'DEROGADO por Ley 27.802 (2026)', NULL, 'El Art. 2 (50% por mora) también fue derogado. Ahora la reparación es por "daño complementario" real, no tarifado. Para hechos anteriores: consultar derecho transitorio.'),
('Ley 24.557', 'Art. 6', '[Contingencias cubiertas]
1. Se consideran contingencias: a) Accidentes de trabajo: todo acontecimiento súbito y violento ocurrido por el hecho o en ocasión del trabajo, o en el trayecto (in itinere). b) Enfermedades profesionales: aquellas incluidas en el listado elaborado por el Poder Ejecutivo Nacional. 2. No se consideran enfermedades profesionales las ajenas al listado, salvo que se demuestre causalidad directa entre agente de riesgo y enfermedad.', 'vigente', NULL, 'In itinere: trayecto habitual domicilio-trabajo-domicilio. Sin desvíos por interés personal. Enfermedades NO listadas pueden reclamarse judicialmente demostrando causalidad (SCJN: "Silva, Facundo" y otros).'),
('Ley 24.557', 'Art. 11', '[Régimen de incapacidades]
Los damnificados percibirán las siguientes prestaciones dinerarias: a) Incapacidad Laboral Temporaria (ILT): salario completo durante el período de recuperación, con un máximo de 2 años. b) Incapacidad Laboral Permanente (ILP): prestación de pago mensual o pago único según el grado de incapacidad.', 'vigente', NULL, 'ILT: primeros 10 días paga el empleador, luego la ART. Máximo 2 años. Si persiste, se evalúa ILP. ILP Parcial Provisoria (<50%): pago mensual = salario × %incapacidad × 13/12. ILP Total (>66%): adicional por gran invalidez.'),
('Ley 24.557', 'Art. 14.2.a', '[Prestación por Incapacidad Permanente Definitiva (IPD)]
Cuando el porcentaje de incapacidad sea permanente y definitivo, el damnificado percibirá un pago único según la fórmula: 53 × IBM × (65/Edad) × (%Incapacidad/100) donde IBM = Ingreso Base Mensual.', 'vigente — complementado por Ley 26.773', NULL, 'Fórmula LRT: 53 × IBM × (65/Edad) × %Incap. IBM: promedio de remuneraciones sujetas a aportes de los 12 meses anteriores. Ley 26.773 agrega pisos mínimos actualizados por RIPTE. ADVERTENCIA: los pisos se recalculan periódicamente (último: Sep 2025). Si la fórmula da menos que el piso RIPTE, se aplica el piso.'),
('Ley 24.557', 'Art. 15', '[Prestaciones por IPP (Incapacidad Parcial Permanente)]
Si el porcentaje de incapacidad es inferior al 50% y definitiva: pago único. Si es igual o superior al 50% e inferior al 66%: pago mensual complementario de la prestación jubilatoria. Si es igual o superior al 66%: prestación como incapacidad total.', 'vigente', NULL, 'Rangos clave: <50% IPP: pago único (fórmula art. 14.2.a). 50-65% IPP: pago mensual complementario. >=66%: gran invalidez (adicional art. 17). Los pisos RIPTE aplican a cada rango.'),
('Ley 24.557', 'Art. 17', '[Gran invalidez]
El damnificado declarado gran inválido percibirá las prestaciones correspondientes a los artículos 14.2 y 15, según el caso, con más una prestación de pago mensual adicional equivalente a 3 MOPRE. En ningún caso esta prestación será inferior al monto del SMVM.', 'vigente', NULL, 'Gran invalidez: >=66% de incapacidad + necesidad de asistencia permanente. Triple prestación: pago único + mensual + adicional por gran invalidez. Piso RIPTE específico: actualmente ~$9.040.000 (2025).'),
('Ley 24.557', 'Art. 18', '[Muerte del trabajador]
Los derechohabientes accederán a la pensión por fallecimiento prevista en el régimen previsional. Además, percibirán un pago único según la fórmula del artículo 14.2.a, con los pisos actualizados.', 'vigente', NULL, 'Piso RIPTE específico muerte: ~$6.780.000 (2025). Beneficiarios: cónyuge, hijos menores.'),
('Ley 27.348', 'Art. 1', '[Procedimiento ante Comisiones Médicas]
Los trabajadores siniestrados deberán someterse al trámite ante las Comisiones Médicas jurisdiccionales como instancia administrativa PREVIA y OBLIGATORIA para acceder a la vía judicial.', 'vigente', NULL, 'Paso obligatorio antes de demandar judicialmente. Comisión Médica Jurisdiccional (CMJ) determina: %incapacidad, tratamiento, alta. Si no hay acuerdo: recurso ante Comisión Médica Central (CMC) o vía judicial. PLAZO para impugnar: varía por provincia (CABA: 30 días, Bs.As.: 90 días).'),
('Ley 27.348', 'Art. 2', '[Recurso contra dictamen de CM]
El trabajador que no estuviere de acuerdo con el dictamen de la Comisión Médica Jurisdiccional podrá interponer recurso ante la Comisión Médica Central o ante el Juzgado Federal de Primera Instancia con competencia en el domicilio de la CMJ que intervino.', 'vigente', NULL, 'Dos vías de recurso: 1) CMC: recurso administrativo (más rápido). 2) Justicia Federal: recurso judicial (peritaje independiente). PLAZO CABA: 30 días desde notificación del dictamen. PLAZO Buenos Aires: 90 días. CADUCIDAD: si no se recurre en plazo, el dictamen queda firme.'),
('Ley 26.773', 'Art. 8', '[Pisos mínimos con ajuste RIPTE]
Los importes por incapacidad laboral permanente y muerte del trabajador serán actualizados semestralmente por el índice RIPTE (Remuneraciones Imponibles Promedio de los Trabajadores Estables). La ART no podrá abonar una suma inferior al piso mínimo vigente.', 'vigente', NULL, 'Pisos actuales (ref. Sep 2025): IPP parcial: $2.260.000 IPD: $4.520.000 Gran invalidez: $9.040.000 Muerte: $6.780.000 Se actualizan por Res. SRT con base RIPTE semestral.');
INSERT INTO `pro_normativa` (`ley`, `articulo`, `texto`, `estado`, `fecha_modificacion`, `notas`) VALUES ('Ley 26.773', 'Art. 4', '[Opción excluyente — Vía civil]
El damnificado o sus derechohabientes podrán optar de modo excluyente entre la reparación tarifada del sistema de riesgos del trabajo o la acción civil por daños y perjuicios en la vía judicial civil.', 'vigente', NULL, 'OPCIÓN EXCLUYENTE: si elige vía civil, PIERDE la tarifa ART. Vía civil permite: daño moral, daño estético, lucro cesante integral. Fórmulas civiles: Méndez, Vuotto, Marshall. La civil puede dar montos 2x-3x superiores pero con mayor riesgo probatorio.'),
('Ley 24.557', 'Art. 6.3 — Preexistencias', '[Incapacidad preexistente — Fórmula de Balthazard]
Las incapacidades preexistentes se computan según la fórmula de Balthazard: Incapacidad Resultante = 1 - (1 - Incapacidad Actual) / (1 - Preexistencia). La ART solo responde por la incapacidad NO preexistente.', 'vigente', NULL, 'Fórmula Balthazard: permite calcular incapacidad NETA descontando preexistencia. Ejemplo: Incapacidad total 40%, preexistencia 20%: Neta = 1 - (1-0.40)/(1-0.20) = 1 - 0.60/0.80 = 1 - 0.75 = 25%. La ART responde por 25%, no por 40%.'),
('Ley 27.742 (Ley Bases)', 'Art. 76 — Período de Prueba', '[Extensión del período de prueba]
Se modifica el Art. 92 bis de la LCT. El período de prueba se establece en SEIS (6) meses, extensible a OCHO (8) meses por CCT para empresas de 6 a 100 trabajadores, y a DOCE (12) meses por CCT para empresas de hasta 5 trabajadores.', 'vigente desde 09/07/2024', NULL, 'Antes: 3 meses universales. Ahora: 6 meses base, 8/12 por CCT. Durante el período de prueba: despido sin causa sin indemnización. Impacto en cálculos: si el despido ocurre en período de prueba, NO corresponde Art. 245. Solo preaviso de 15 días.'),
('Ley 27.742 (Ley Bases)', 'Art. 80 — Fondo de Cese', '[Sistema de Fondo de Cese Laboral Sectorial]
Se habilita la creación de un Fondo de Cese Laboral por CCT, que sustituye al régimen indemnizatorio de los Arts. 232, 233 y 245 LCT. El fondo se nutre de aportes mensuales del empleador. El trabajador accede al fondo al producirse el cese laboral.', 'vigente — pendiente reglamentación por CCT', NULL, 'Similar al sistema UOCRA (Ley 22.250). Requiere adhesión por CCT. No es obligatorio: cada CCT decide si lo implementa. Si el CCT NO adhiere, sigue el régimen general de Arts. 232/233/245. IMPORTANTE: pocos CCTs han implementado esto aún.'),
('Ley 27.742 (Ley Bases)', 'Art. 78 — Trabajador Independiente con Colaboradores', '[Nueva figura: Trabajador independiente con hasta 3 colaboradores]
Se crea la figura del trabajador independiente que puede contar con hasta TRES (3) trabajadores independientes para llevar adelante un emprendimiento productivo, sin que entre ellos exista relación de dependencia.', 'vigente — sujeto a reglamentación', NULL, 'Figura controvertida. Riesgo de encubrimiento de relación laboral. Los "colaboradores" no tienen derechos laborales ni previsionales plenos. Doctrina laboral: evaluar subordinación real (Art. 23 LCT / Ley 27.802). Si hay órdenes, horario, exclusividad = relación laboral encubierta.'),
('Ley 27.742 (Ley Bases)', 'Art. 81 — Bloqueos y piquetes', '[Causal de despido por bloqueo, piquete o toma]
Se considera justa causa de despido la participación activa en bloqueos o tomas de establecimientos, cuando impidan el ingreso o egreso de personas o bienes al establecimiento.', 'vigente', NULL, 'Amplia causal de despido con causa. Requiere participación "activa". La mera participación pasiva o sindical NO debería configurar causal. Conflicto con derecho de huelga (Art. 14 bis CN). Sujeto a control judicial.'),
('Ley 27.742 (Ley Bases)', 'Fecha de corte', '[Aplicación temporal — 09/07/2024]
La Ley 27.742 entró en vigencia el 09/07/2024. Las modificaciones aplican a hechos posteriores a esa fecha. Los derechos adquiridos bajo el régimen anterior se mantienen.', 'vigente', NULL, 'Para el motor: validar si fecha_extinción > 09/07/2024. Si es ANTERIOR: aplica régimen completo anterior (multas 24.013, período prueba 3 meses, etc.). Si es POSTERIOR: aplica Ley Bases + Ley 27.802. CLAVE para derecho transitorio.'),
('Decreto 1694/2009', 'Reglamentación LRT', '[Reglamentación Ley 26.773 — Pisos RIPTE]
Establece el mecanismo de actualización semestral de los pisos mínimos de prestaciones dinerarias de la LRT mediante el índice RIPTE. La SRT publica los montos actualizados mediante resolución.', 'vigente', NULL, 'Los pisos se actualizan: Enero y Julio de cada año. Publicación: Resolución SRT en Boletín Oficial.'),
('Decreto 351/1979', 'Reglamentación Ley 19.587', '[Higiene y Seguridad en el Trabajo]
Reglamentación de la Ley 19.587 de Higiene y Seguridad en el Trabajo. Establece condiciones mínimas de prevención: iluminación, ventilación, ruido, protección contra incendios, señalización, provisión de EPP, servicios de medicina laboral, exámenes preocupacionales y periódicos.', 'vigente', NULL, 'El incumplimiento del Decreto 351/79 es prueba instrumental importante en juicios por accidentes laborales. Si el empleador no cumplía con prevención, aumenta responsabilidad y puede habilitar vía civil. Incluye anexos técnicos con valores límite de exposición a agentes.'),
('Decreto 467/2014', 'Reglamentación Ley 26.773', '[Procedimiento de cobro de prestaciones LRT]
Reglamenta el trámite para la percepción de las prestaciones dinerarias: plazo de pago de 15 días desde la homologación del acuerdo, modalidad de pago mediante depósito bancario a nombre del damnificado.', 'vigente', NULL, 'La ART tiene 15 días para pagar desde que se homologa.'),
('Decreto 49/2014', 'Listado de Enfermedades Profesionales', '[Actualización del Listado de Enfermedades Profesionales]
Actualiza el listado del Art. 6.2 de la Ley 24.557, incorporando nuevas enfermedades profesionales y sus agentes de riesgo. Include trastornos musculoesqueléticos, hipoacusia, enfermedades respiratorias por exposición a agentes, entre otras.', 'vigente', NULL, 'El listado es cerrado. Enfermedades NO listadas requieren prueba pericial de causalidad directa. Tendencia jurisprudencial: ampliar vía judicial.'),
('Res. SRT 886/2015', 'Protocolo de exámenes médicos', '[Exámenes médicos obligatorios]
Establece el protocolo de exámenes médicos en salud: a) Preocupacional: obligatorio ANTES del ingreso. b) Periódico: según agentes de riesgo del puesto. c) De egreso: optativo, dentro de 10 días del cese. d) Por ausencias prolongadas. e) Previos a transferencia de actividad.', 'vigente', NULL, 'El examen preocupacional es OBLIGATORIO. Sin él, el empleador no puede alegar preexistencias. Es clave en juicios por accidentes para determinar estado de salud al ingreso.'),
('Res. SRT 905/2015', 'Procedimiento CM', '[Procedimiento ante Comisiones Médicas]
Reglamenta el procedimiento ante las Comisiones Médicas jurisdiccionales: Plazos, presentación de documentación, pericias, notificaciones, audiencias médicas y dictamen final.', 'vigente', NULL, 'Plazo para citación: dentro de los 10 días de la presentación. El dictamen incluye: diagnóstico, incapacidad %, causalidad, tratamiento. Recurso: dentro del plazo provincial.'),
('CSJN', 'Vizzoti c/ AMSA (2004)', '[Inconstitucionalidad del tope Art. 245 LCT]
La CSJN declaró inconstitucional el tope del Art. 245 LCT cuando reduce la base de cálculo en más del 33%. Estableció que el tope no puede representar menos del 67% de la mejor remuneración mensual, normal y habitual.', 'vigente — leading case', NULL, 'PISO VIZZOTI: 67% de la mejor remuneración. Si el tope CCT reduce la base a menos del 67%, se aplica el 67%. Aplicable a todos los despidos sin causa. Ejemplo: salario $1.000.000, tope CCT $500.000 → se aplica $670.000 (67%).'),
('CSJN', 'Aquino c/ Cargo S.A. (2004)', '[Opción por vía civil en accidentes laborales]
La CSJN habilitó la vía civil para reclamos por accidentes de trabajo, declarando la inconstitucionalidad del Art. 39.1 LRT que vedaba la acción civil. Abrió el camino para la reparación integral.', 'vigente — leading case', NULL, 'Fundamento: la tarifa LRT puede ser insuficiente para reparar el daño. Hoy regulado por Art. 4 Ley 26.773 (opción excluyente). Clave para el escenario D del sistema (Acción Civil Complementaria).'),
('CSJN', 'González c/ Polimat (2010)', '[Rubros no remunerativos en base de cálculo]
La CSJN estableció que las sumas abonadas como no remunerativas (mediante acuerdos paritarios) deben incluirse en la base de cálculo de la indemnización del Art. 245 LCT cuando tienen naturaleza remuneratoria.', 'vigente — leading case', NULL, 'Impacta en la base de cálculo: todo lo que tiene naturaleza salarial debe incluirse como remunerativo. Los acuerdos que "disfrazan" aumentos como no remunerativos son cuestionables.'),
('CSJN', 'Castillo c/ Cerámica Alberdi (2004)', '[Competencia federal vs. local en reclamos LRT]
Declaró la inconstitucionalidad del Art. 46 de la LRT que otorgaba competencia federal exclusiva, permitiendo optar por la justicia provincial.', 'vigente — leading case (complementado por Ley 27.348)', NULL, 'Post Ley 27.348: primero Comisión Médica, luego justicia federal O provincial. La opción del trabajador por la justicia provincial es constitucional.');
UNLOCK TABLES;

-- Estructura de pro_jurisprudencia
DROP TABLE IF EXISTS `pro_jurisprudencia`;
CREATE TABLE `pro_jurisprudencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caratula` varchar(255) NOT NULL,
  `tribunal` varchar(100) NOT NULL,
  `fecha` date NOT NULL,
  `tema` varchar(255) NOT NULL,
  `resumen` text DEFAULT NULL,
  `relevancia` varchar(50) DEFAULT NULL,
  `tipo_conflicto` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para pro_jurisprudencia
LOCK TABLES `pro_jurisprudencia` WRITE;
INSERT INTO `pro_jurisprudencia` (`caratula`, `tribunal`, `fecha`, `tema`, `resumen`, `relevancia`, `tipo_conflicto`) VALUES ('Vizzoti, Carlos Alberto c/ AMSA S.A.', 'CSJN', '2004-09-14', 'Tope Art. 245 LCT', 'Declaró inconstitucional el tope del Art. 245 LCT cuando reduce la base de cálculo en más del 33%. Estableció PISO VIZZOTI: 67% de la mejor remuneración mensual, normal y habitual.', 'fundamental', 'despido_sin_causa'),
('Aquino, Isacio c/ Cargo Servicios Industriales S.A.', 'CSJN', '2004-09-21', 'Opción vía civil en accidentes laborales', 'Habilitó la acción civil por daños y perjuicios en accidentes laborales, declarando inconstitucional el Art. 39.1 LRT que vedaba la vía civil. Fundamento: reparación integral vs. tarifa.', 'fundamental', 'accidente_laboral'),
('González, Martín Nicolás c/ Polimat S.A.', 'CSJN', '2010-07-19', 'Sumas no remunerativas en base de cálculo', 'Las sumas pactadas como no remunerativas en acuerdos paritarios deben incluirse en la base de cálculo del Art. 245 LCT cuando tienen naturaleza salarial real.', 'alta', 'diferencias_salariales'),
('Castillo, Ángel Santos c/ Cerámica Alberdi S.A.', 'CSJN', '2004-09-07', 'Competencia justicia provincial vs. federal en LRT', 'Declaró inconstitucional la competencia federal exclusiva del Art. 46 LRT. El trabajador puede optar por la justicia provincial en reclamos ART.', 'alta', 'accidente_laboral'),
('Milone, Juan Antonio c/ Asociart S.A.', 'CSJN', '2004-10-26', 'Inconstitucionalidad pago en renta LRT', 'Declaró inconstitucional el sistema de pago periódico (renta) para incapacidades >= 50% de la LRT. Dispuso pago único.', 'alta', 'accidente_laboral'),
('Pérez, Raúl Aníbal c/ Disco S.A.', 'CSJN', '2009-09-01', 'Concepto de remuneración — Vales de almuerzo', 'Los vales de almuerzo y canasta tienen naturaleza remuneratoria y deben incluirse en la base de cálculo indemnizatorio y previsional.', 'alta', 'diferencias_salariales'),
('Díaz, Paulo Vicente c/ Cervecería y Maltería Quilmes S.A.', 'CSJN', '2013-03-04', 'Discriminación antisindical — Reinstalación', 'Estableció que la tutela contra la discriminación opera incluso sin ser delegado gremial. Operatividad directa de la Ley 23.592 (antidiscriminación) con reinstalación al puesto.', 'alta', 'despido_sin_causa'),
('Rica, Carlos Martín c/ Hospital Alemán', 'CSJN', '2018-04-24', 'Médicos de guardia — Relación de dependencia', 'Médicos que realizan guardias asignadas por la institución, con horarios impuestos y bajo subordinación jurídica, son empleados en relación de dependencia. La facturación por monotributo no excluye la laboralidad.', 'alta', 'trabajo_no_registrado');
UNLOCK TABLES;

SET FOREIGN_KEY_CHECKS = 1;
