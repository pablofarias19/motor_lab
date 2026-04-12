<?php
/**
 * generar_informe.php — Generador de informe PDF del análisis de riesgo laboral
 *
 * Recibe el UUID de un análisis guardado, recupera todos los datos de la BD
 * y genera un PDF profesional usando la librería FPDF del sistema /document.
 *
 * Reutiliza:
 *   - FPDF: /document/fpdf.php (sin copiar la librería)
 *   - Patrón de generación PDF: /document/telegrama_pdf.php
 *   - Función utf8_to_latin1 para codificación correcta de caracteres
 *
 * Método:   GET (?uuid=XXXX) o POST
 * Salida:   application/pdf (inline en navegador)
 */

// ─── Buffer de salida — DEBE ir antes de cualquier output ────────────────────
ob_start();
$requestStart = microtime(true);

// ─── Dependencias del módulo ──────────────────────────────────────────────────
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/DatabaseManager.php';

$logPdfRequest = static function (string $estado, int $httpCode, array $contexto = []) use ($requestStart): void {
    ml_log_metric('api.generar_informe', [
        'estado' => $estado,
        'http_code' => $httpCode,
        'duracion_ms' => round((microtime(true) - $requestStart) * 1000, 2),
    ] + $contexto);
};

// ─── Verificar que FPDF existe (reutilizamos la del sistema /document) ────────
if (!file_exists(ML_FPDF_PATH)) {
    $logPdfRequest('fpdf_missing', 500, ['path' => ML_FPDF_PATH]);
    ob_end_clean();
    http_response_code(500);
    die('Error: librería FPDF no encontrada en ' . ML_FPDF_PATH);
}
require_once ML_FPDF_PATH;

// ─── Función de conversión UTF-8 → Latin1 para FPDF ─────────────────────────
// FPDF requiere ISO-8859-1. Los caracteres Unicode fuera del rango Latin-1
// (como el em dash —, comillas tipográficas, etc.) se reemplazan por equivalentes
// ASCII antes de la conversión para evitar el símbolo ?.
function pdf_latin1(string $str): string {
    // Paso 1: reemplazar caracteres Unicode que NO existen en ISO-8859-1
    $unicode   = [
        "\xE2\x80\x94",  // — em dash (U+2014)
        "\xE2\x80\x93",  // – en dash (U+2013)
        "\xE2\x80\xA6",  // … ellipsis (U+2026)
        "\xE2\x80\x9C",  // " comilla doble izq (U+201C)
        "\xE2\x80\x9D",  // " comilla doble der (U+201D)
        "\xE2\x80\x98",  // ' comilla simple izq (U+2018)
        "\xE2\x80\x99",  // ' comilla simple der / apostrofo (U+2019)
        "\xE2\x80\xA2",  // • bullet (U+2022)
        "\xE2\x89\xA5",  // ≥ mayor o igual (U+2265)
        "\xE2\x89\xA4",  // ≤ menor o igual (U+2264)
        "\xC3\x97",      // × multiplicacion (U+00D7) → esta SÍ está en Latin-1, pero por si acaso
        "\xE2\x80\x8B",  // zero-width space (U+200B)
    ];
    $ascii     = [
        ' - ',  // em dash → guion con espacios
        '-',    // en dash → guion
        '...',  // ellipsis → tres puntos
        '"',    // comilla izq → comilla recta
        '"',    // comilla der → comilla recta
        "'",    // comilla simple izq → apóstrofo
        "'",    // comilla simple der → apóstrofo
        '*',    // bullet → asterisco
        '>=',   // ≥
        '<=',   // ≤
        'x',    // ×
        '',     // zero-width space → vacío
    ];
    $str = str_replace($unicode, $ascii, $str);

    // Paso 2: convertir UTF-8 → ISO-8859-1 con el método disponible
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
        return $converted !== false ? $converted : utf8_decode($str);
    }
    if (function_exists('utf8_decode')) {
        return utf8_decode($str);
    }
    // Fallback manual para caracteres españoles más comunes
    return str_replace(
        ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ','ü','Ü','¡','¿','°'],
        [chr(225),chr(233),chr(237),chr(243),chr(250),chr(193),chr(201),chr(205),chr(211),chr(218),chr(241),chr(209),chr(252),chr(220),chr(161),chr(191),chr(176)],
        $str
    );
}

// ─── Obtener UUID desde GET o POST ───────────────────────────────────────────
$uuid = trim($_GET['uuid'] ?? $_POST['uuid'] ?? '');

if (empty($uuid) || !preg_match('/^[a-f0-9\-]{36}$/', $uuid)) {
    $logPdfRequest('uuid_invalido', 400);
    ob_end_clean();
    http_response_code(400);
    die('UUID inválido o no proporcionado.');
}

// ─── Recuperar análisis de la BD ─────────────────────────────────────────────
$analisis = \App\Support\AnalysisSessionStore::fetch($uuid);

try {
    if (!$analisis) {
        $db       = new DatabaseManager();
        $analisis = $db->obtenerAnalisisPorUUID($uuid);
    }

    if (!$analisis) {
        $logPdfRequest('no_encontrado', 404, ['uuid' => $uuid]);
        ob_end_clean();
        http_response_code(404);
        die('Análisis no encontrado.');
    }

} catch (Exception $e) {
    $logPdfRequest('bd_error', 500, ['uuid' => $uuid, 'error' => $e->getMessage()]);
    ob_end_clean();
    http_response_code(500);
    die('Error al recuperar los datos del análisis.');
}

// ─── Decodificar JSONs almacenados ────────────────────────────────────────────
$datosLaborales = json_decode($analisis['datos_laborales'], true) ?? [];
$documentacion  = json_decode($analisis['documentacion_json'], true) ?? [];
$situacion      = json_decode($analisis['situacion_json'], true) ?? [];
$irilPayload    = ml_parse_iril_payload(json_decode($analisis['iril_detalle'], true) ?? []);
$exposicion     = json_decode($analisis['exposicion_json'], true) ?? [];
$escenariosData = ml_parse_escenarios_payload(
    json_decode($analisis['escenarios_json'], true) ?? [],
    $analisis['escenario_recomendado'] ?? 'C'
);

$irilDetalle = $irilPayload['detalle'];
$irilScore = $irilPayload['score'] > 0 ? $irilPayload['score'] : floatval($analisis['iril_score']);
$nivelIril = is_array($irilPayload['nivel']) ? $irilPayload['nivel'] : ml_nivel_iril($irilScore);
$escenarios = $escenariosData['escenarios'];
$escRecomendado = $escenariosData['recomendado'];

// ─────────────────────────────────────────────────────────────────────────────
// CLASE PDF PERSONALIZADA — extiende FPDF con header y footer del estudio
// ─────────────────────────────────────────────────────────────────────────────
class InformeLaboralPDF extends FPDF {

    private string $uuid;
    private string $tipoUsuario;

    public function __construct(string $uuid, string $tipoUsuario) {
        parent::__construct('P', 'mm', 'A4');
        $this->uuid       = $uuid;
        $this->tipoUsuario = $tipoUsuario;
        $this->SetTitle(pdf_latin1('Análisis de Riesgo Laboral — Farias Ortiz'));
        $this->SetAuthor(pdf_latin1('Estudio Farias Ortiz'));
        $this->SetCreator(pdf_latin1('Motor de Riesgo Laboral v1.0'));
    }

    /** Encabezado de cada página */
    public function Header(): void {
        // Línea superior azul
        $this->SetFillColor(42, 100, 182); // --primary #2a64b6
        $this->Rect(0, 0, 210, 12, 'F');

        // Título en el encabezado
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 2);
        $this->Cell(0, 8, pdf_latin1('MOTOR DE RIESGO LABORAL — ESTUDIO FARIAS ORTIZ'), 0, 1, 'L');

        // Subtítulo en el encabezado
        $this->SetFont('Arial', '', 8);
        $this->SetXY(10, 8);
        $this->Cell(100, 5, pdf_latin1('www.fariasortiz.com.ar'), 0, 0, 'L');
        $this->Cell(90, 5, pdf_latin1('UUID: ' . $this->uuid), 0, 0, 'R');

        $this->SetTextColor(0, 0, 0);
        $this->Ln(10);
    }

    /** Pie de página con disclaimer legal */
    public function Footer(): void {
        $this->SetY(-18);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(120, 120, 120);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        $this->MultiCell(0, 4,
            pdf_latin1('AVISO LEGAL: Este informe es de carácter estructural y preventivo. NO constituye asesoramiento legal definitivo, no garantiza resultado judicial ni sustituye decisión jurisdiccional. Se recomienda consulta con profesional habilitado ante IRIL ≥ 3. Generado: ' . date('d/m/Y H:i')),
            0, 'C'
        );
        $this->Cell(0, 4, pdf_latin1('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    /** Título de sección con fondo gris claro */
    public function seccion(string $titulo): void {
        $this->Ln(4);
        $this->SetFillColor(232, 240, 254); // azul muy claro
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(30, 74, 139); // --primary-dark
        $this->Cell(0, 8, pdf_latin1(' ' . strtoupper($titulo)), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Ln(2);
    }

    /** Fila de dato clave-valor */
    public function fila(string $clave, string $valor): void {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(55, 6, pdf_latin1($clave . ':'), 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->MultiCell(0, 6, pdf_latin1($valor), 0, 'L');
    }
}

// ─── Generar el PDF ───────────────────────────────────────────────────────────
try {
    $pdf = new InformeLaboralPDF($uuid, $analisis['tipo_usuario']);
    $pdf->AliasNbPages();
    $pdf->SetMargins(15, 18, 15);
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    // ── Portada / Datos del análisis ─────────────────────────────────────────
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(30, 74, 139);
    $pdf->Cell(0, 10, pdf_latin1('INFORME DE ANÁLISIS ESTRATÉGICO LABORAL'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, pdf_latin1('Fecha de análisis: ' . date('d/m/Y H:i', strtotime($analisis['fecha_creacion']))), 0, 1, 'C');
    $pdf->Ln(6);

    // ── Perfil del usuario ────────────────────────────────────────────────────
    $pdf->seccion('1. Perfil del Análisis');

    $pdf->fila('Perfil del solicitante', ucfirst($analisis['tipo_usuario']));
    $pdf->fila('Tipo de conflicto', ml_conflicto_label($analisis['tipo_conflicto']));
    $pdf->fila('Provincia', $datosLaborales['provincia'] ?? 'No especificada');
    $pdf->fila('Antigüedad', round(($datosLaborales['antiguedad_meses'] ?? 0) / 12, 1) . ' años (' . ($datosLaborales['antiguedad_meses'] ?? 0) . ' meses)');
    $pdf->fila('Salario base mensual', ml_formato_moneda($datosLaborales['salario'] ?? 0));
    if (!empty($datosLaborales['categoria'])) $pdf->fila('Categoría laboral', $datosLaborales['categoria']);
    if (!empty($datosLaborales['cct']))       $pdf->fila('Convenio colectivo', $datosLaborales['cct']);

    // ── Índice IRIL ───────────────────────────────────────────────────────────
    $pdf->seccion('2. Índice de Riesgo Institucional Laboral (IRIL)');

    $pdf->SetFont('Arial', 'B', 22);
    $nivelColor = $nivelIril['nivel'];
    if ($irilScore < 2.0) $pdf->SetTextColor(39, 174, 96);
    elseif ($irilScore < 3.0) $pdf->SetTextColor(243, 156, 18);
    elseif ($irilScore < 4.0) $pdf->SetTextColor(230, 126, 34);
    else $pdf->SetTextColor(231, 76, 60);

    $pdf->Cell(0, 12, pdf_latin1($irilScore . ' / 5.0 — Nivel ' . $nivelIril['nivel']), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFont('Arial', 'I', 9);
    $pdf->MultiCell(0, 5, pdf_latin1($nivelIril['descripcion']), 0, 'C');
    $pdf->Ln(4);

    // Desglose por dimensión
    if (!empty($irilDetalle)) {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(85, 6, pdf_latin1('Dimensión'), 'B', 0);
        $pdf->Cell(20, 6, 'Valor', 'B', 0, 'C');
        $pdf->Cell(20, 6, 'Peso', 'B', 0, 'C');
        $pdf->Cell(0,  6, pdf_latin1('Descripción'), 'B', 1);
        $pdf->SetFont('Arial', '', 8);

        $labels = [
            'saturacion_tribunalicia' => pdf_latin1('Saturación tribunalicia'),
            'complejidad_probatoria'  => pdf_latin1('Complejidad probatoria'),
            'volatilidad_normativa'   => pdf_latin1('Volatilidad normativa'),
            'riesgo_costas'           => pdf_latin1('Riesgo de costas'),
            'riesgo_multiplicador'    => pdf_latin1('Riesgo multiplicador'),
        ];

        foreach ($irilDetalle as $key => $dim) {
            $pdf->Cell(85, 5, $labels[$key] ?? pdf_latin1($key), 0, 0);
            $pdf->Cell(20, 5, number_format($dim['valor'], 1), 0, 0, 'C');
            $pdf->Cell(20, 5, $dim['peso'], 0, 0, 'C');
            $pdf->Cell(0,  5, pdf_latin1($dim['descripcion']), 0, 1);
        }
    }

    // ── Exposición económica ──────────────────────────────────────────────────
    if (!empty($exposicion['conceptos'])) {
        $pdf->seccion('3. Exposición Económica Estimada');
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->MultiCell(0, 4, pdf_latin1('Estimación estructural bajo LCT. NO garantiza resultado. Sujeto a negociación y resolución judicial.'), 0, 'J');
        $pdf->Ln(3);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(120, 6, pdf_latin1('Concepto'), 'B', 0);
        $pdf->Cell(0, 6, pdf_latin1('Monto Estimado'), 'B', 1, 'R');
        $pdf->SetFont('Arial', '', 9);

        foreach ($exposicion['conceptos'] as $key => $concepto) {
            if (isset($concepto['aplica']) && $concepto['aplica'] === false) continue;
            $pdf->Cell(120, 5, pdf_latin1($concepto['descripcion']), 0, 0);
            $pdf->Cell(0, 5, ml_formato_moneda($concepto['monto']), 0, 1, 'R');
            if (!empty($concepto['condicion'])) {
                $pdf->SetFont('Arial', 'I', 7);
                $pdf->SetTextColor(120, 120, 120);
                $pdf->Cell(10, 4, '', 0, 0);
                $pdf->Cell(0, 4, pdf_latin1('  Condición: ' . $concepto['condicion']), 0, 1);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Arial', '', 9);
            }
        }

        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(120, 7, pdf_latin1('TOTAL BASE (sin multas)'), 'T', 0);
        $pdf->Cell(0, 7, ml_formato_moneda($exposicion['total_base']), 'T', 1, 'R');
        $pdf->Cell(120, 7, pdf_latin1('TOTAL CON MULTAS (máxima exposición)'), 0, 0);
        $pdf->Cell(0, 7, ml_formato_moneda($exposicion['total_con_multas']), 0, 1, 'R');
    }

    if (!empty($exposicion['analisis_empresa']) && is_array($exposicion['analisis_empresa'])) {
        $pdf->seccion('4. Diagnóstico específico para empresa');

        foreach ($exposicion['analisis_empresa'] as $modulo => $detalle) {
            if (!is_array($detalle)) {
                continue;
            }

            $titulo = match ($modulo) {
                'art_empresa' => 'Contingencia ART empresa',
                'solidaridad' => 'Responsabilidad solidaria',
                'auditoria' => 'Auditoría preventiva',
                'inspeccion' => 'Riesgo de inspección',
                default => ucfirst(str_replace('_', ' ', $modulo)),
            };

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 6, pdf_latin1($titulo), 0, 1);
            $pdf->SetFont('Arial', '', 8);

            foreach ($detalle as $clave => $valor) {
                if (is_array($valor)) {
                    continue;
                }

                $texto = is_bool($valor) ? ($valor ? 'Sí' : 'No') : (string) $valor;
                $pdf->MultiCell(0, 4, pdf_latin1(ucfirst(str_replace('_', ' ', $clave)) . ': ' . $texto), 0, 'L');
            }

            $pdf->Ln(1);
        }
    }

    // ── Sección ART específica (solo accidente_laboral con ART) ────────────────
    $esArtPDF = ($analisis['tipo_conflicto'] === 'accidente_laboral')
        && (($situacion['tiene_art'] ?? 'no') === 'si');

    if ($esArtPDF) {
        $pdf->seccion('Análisis de Contingencia ART');

        $etiquetasContPDF = [
            'accidente_tipico' => 'Accidente de trabajo (típico)',
            'in_itinere' => 'Accidente in itinere (trayecto)',
            'enfermedad_profesional' => 'Enfermedad profesional',
        ];
        $etiquetasCMPDF = [
            'no_iniciada' => 'No iniciado',
            'en_tramite' => 'En trámite',
            'dictamen_emitido' => 'Dictamen emitido',
            'homologado' => 'Acuerdo homologado',
        ];

        $pdf->fila('Tipo de contingencia', $etiquetasContPDF[$situacion['tipo_contingencia'] ?? ''] ?? 'No especificada');
        $pdf->fila('Incapacidad', ($situacion['porcentaje_incapacidad'] ?? 0) . '% — ' . ucfirst(str_replace('_', ' ', $situacion['incapacidad_tipo'] ?? 'permanente definitiva')));
        $pdf->fila('Estado Comisión Médica', $etiquetasCMPDF[$situacion['comision_medica'] ?? ''] ?? 'No iniciado');
        if (!empty($situacion['fecha_siniestro'])) {
            $pdf->fila('Fecha del siniestro', date('d/m/Y', strtotime($situacion['fecha_siniestro'])));
        }
        $pdf->Ln(3);

        // Tabla comparativa ART vs Civil
        $montoTarifaPDF = $exposicion['conceptos']['prestacion_art_tarifa']['monto'] ?? 0;
        $montoCivilPDF = $exposicion['conceptos']['estimacion_civil_mendez']['monto'] ?? 0;

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(42, 100, 182);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(60, 6, '', 1, 0, 'C', true);
        $pdf->Cell(50, 6, pdf_latin1('Tarifa ART (Ley 24.557)'), 1, 0, 'C', true);
        $pdf->Cell(0, 6, pdf_latin1('Acción Civil (Méndez)'), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 9);

        $pdf->Cell(60, 6, pdf_latin1('Monto estimado'), 1, 0);
        $pdf->Cell(50, 6, ml_formato_moneda($montoTarifaPDF), 1, 0, 'R');
        $pdf->Cell(0, 6, ml_formato_moneda($montoCivilPDF), 1, 1, 'R');

        $pdf->Cell(60, 6, pdf_latin1('Duración estimada'), 1, 0);
        $pdf->Cell(50, 6, '3-12 meses', 1, 0, 'R');
        $pdf->Cell(0, 6, '36-60 meses', 1, 1, 'R');

        $pdf->Cell(60, 6, pdf_latin1('Riesgo procesal'), 1, 0);
        $pdf->Cell(50, 6, 'Bajo-Medio', 1, 0, 'R');
        $pdf->Cell(0, 6, 'Alto', 1, 1, 'R');

        $pdf->SetFont('Arial', 'B', 9);
        $diferenciaPDF = $montoCivilPDF - $montoTarifaPDF;
        $pdf->Cell(60, 6, pdf_latin1('Diferencia'), 1, 0);
        $pdf->Cell(0, 6, ($diferenciaPDF > 0 ? '+' : '') . ml_formato_moneda($diferenciaPDF) . pdf_latin1(' vía civil'), 1, 1, 'R');
        $pdf->SetFont('Arial', '', 9);

        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(180, 60, 60);
        $pdf->MultiCell(0, 4, pdf_latin1('ADVERTENCIA: La opción civil (Art. 4 Ley 26.773) es EXCLUYENTE. Optar por la vía civil implica renunciar al cobro de la tarifa ART.'), 0, 'J');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        // Normativa aplicable
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 5, pdf_latin1('Normativa aplicable:'), 0, 1);
        $pdf->SetFont('Arial', '', 7);
        $normas = ['Ley 24.557 (Riesgos del Trabajo)', 'Ley 26.773 (Régimen de ordenamiento de la reparación)', 'Ley 27.348 (Complementaria de ART — Comisiones Médicas)', 'Decreto 659/96 (Baremo de incapacidades)'];
        foreach ($normas as $norma) {
            $pdf->Cell(5, 4, '', 0, 0);
            $pdf->Cell(0, 4, pdf_latin1('* ' . $norma), 0, 1);
        }
    }

    // ── Escenarios estratégicos ───────────────────────────────────────────────
    if (!empty($escenarios)) {
        $pdf->AddPage();
        $pdf->seccion('Escenarios Estratégicos Comparativos');

        $pdf->SetFont('Arial', 'I', 8);
        $pdf->MultiCell(0, 4, pdf_latin1('El sistema presenta escenarios estructurales comparativos. No recomienda resultado. La decisión corresponde al profesional y al cliente.'), 0, 'J');
        $pdf->Ln(3);

        // Tabla comparativa rápida
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(42, 100, 182);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(30, 6, 'Escenario', 1, 0, 'C', true);
        $pdf->Cell(35, 6, pdf_latin1('Beneficio Est.'), 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Costo Est.', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Duración', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Riesgo', 1, 0, 'C', true);
        $pdf->Cell(0,  6, pdf_latin1('Interv.'), 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);

        $letras = ['A', 'B', 'C', 'D'];
        foreach ($letras as $letra) {
            if (!isset($escenarios[$letra])) continue;
            $esc = $escenarios[$letra];
            $esRecomendado = ($letra === $escRecomendado);

            if ($esRecomendado) {
                $pdf->SetFillColor(232, 240, 254);
                $fill = true;
            } else {
                $fill = false;
            }

            $pdf->SetFont('Arial', $esRecomendado ? 'B' : '', 8);
            $nombre = $letra . '. ' . ($esc['nombre'] ?? '');
            $pdf->Cell(30, 6, pdf_latin1($nombre), 1, 0, 'L', $fill);
            $pdf->Cell(35, 6, ml_formato_moneda($esc['beneficio_estimado'] ?? 0), 1, 0, 'R', $fill);
            $pdf->Cell(35, 6, ml_formato_moneda($esc['costo_estimado'] ?? 0), 1, 0, 'R', $fill);
            $duracion = ($esc['duracion_min_meses'] ?? 0) . '-' . ($esc['duracion_max_meses'] ?? 0) . 'm';
            $pdf->Cell(20, 6, $duracion, 1, 0, 'C', $fill);
            $pdf->Cell(20, 6, ($esc['riesgo_institucional'] ?? 0) . '/5', 1, 0, 'C', $fill);
            $pdf->Cell(0,  6, pdf_latin1(ucfirst($esc['nivel_intervencion'] ?? '')), 1, 1, 'C', $fill);
        }

        if ($escRecomendado) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetTextColor(30, 74, 139);
            $pdf->Cell(0, 5, pdf_latin1('* Escenario estructuralmente sugerido en base a VAE (Valor Ajustado Estratégico). No implica garantía de resultado.'), 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }

        // Detalle de cada escenario
        foreach ($letras as $letra) {
            if (!isset($escenarios[$letra])) continue;
            $esc = $escenarios[$letra];

            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 10);
            $esRecomendado = ($letra === $escRecomendado);
            $titulo = 'Escenario ' . $letra . ' — ' . ($esc['nombre'] ?? '');
            if ($esRecomendado) $titulo .= ' (*)';
            $pdf->Cell(0, 7, pdf_latin1($titulo), 'B', 1);

            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(0, 5, pdf_latin1($esc['descripcion'] ?? ''), 0, 'J');
            $pdf->Ln(2);

            // Ventajas
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, pdf_latin1('Ventajas:'), 0, 1);
            $pdf->SetFont('Arial', '', 8);
            foreach ($esc['ventajas'] ?? [] as $v) {
                $pdf->Cell(5, 4, '', 0, 0);
                $pdf->Cell(0, 4, pdf_latin1('+ ' . $v), 0, 1);
            }

            // Desventajas
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(0, 5, pdf_latin1('Consideraciones:'), 0, 1);
            $pdf->SetFont('Arial', '', 8);
            foreach ($esc['desventajas'] ?? [] as $d) {
                $pdf->Cell(5, 4, '', 0, 0);
                $pdf->Cell(0, 4, pdf_latin1('- ' . $d), 0, 1);
            }

            if (!empty($esc['nota'])) {
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'I', 8);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->MultiCell(0, 4, pdf_latin1('Nota: ' . $esc['nota']), 0, 'J');
                $pdf->SetTextColor(0, 0, 0);
            }
        }
    }

    // ── Cierre y firma ────────────────────────────────────────────────────────
    $pdf->AddPage();
    $pdf->seccion('5. Pasos Sugeridos');
    $pdf->SetFont('Arial', '', 10);

    $pasos = $irilScore >= 3.0 ? [
        'Consulte urgentemente con un profesional habilitado.',
        'No responda telegramas ni tome acciones sin asesoramiento previo.',
        'Reúna toda la documentación disponible (recibos, telegramas, contratos).',
        'Si hay plazos de prescripción próximos, actúe de inmediato.',
        'Solicite una consulta formal con el Estudio Farias Ortiz.',
    ] : [
        'Evalúe los escenarios presentados con tranquilidad.',
        'Consulte con un profesional antes de tomar decisiones definitivas.',
        'Reúna la documentación disponible para respaldar su posición.',
        'Considere la negociación como primer camino si el IRIL es bajo.',
        'El Estudio Farias Ortiz está disponible para asesoramiento.',
    ];

    foreach ($pasos as $i => $paso) {
        $pdf->Cell(8, 6, ($i + 1) . '.', 0, 0);
        $pdf->MultiCell(0, 6, pdf_latin1($paso), 0, 'J');
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(30, 74, 139);
    $pdf->Cell(0, 8, pdf_latin1('ESTUDIO FARIAS ORTIZ — ASESORES LEGALES'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, pdf_latin1('Dr. Pablo Nicolás Farías — MP 1-33775 (Buenos Aires y CABA)'), 0, 1, 'C');
    $pdf->Cell(0, 6, pdf_latin1('WA (CABA): +54 11 6848-0793 | WA (CBA): +54 351 261-9599'), 0, 1, 'C');
    $pdf->Cell(0, 6, pdf_latin1('estudio@fariasortiz.com.ar | pablofarias19@gmail.com | www.fariasortiz.com.ar'), 0, 1, 'C');

    // ─── Enviar PDF al navegador ──────────────────────────────────────────────
    while (ob_get_level() > 0) ob_end_clean();

    $nombreArchivo = 'analisis_laboral_' . substr($uuid, 0, 8) . '_' . date('Ymd') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $logPdfRequest('ok', 200, ['uuid' => $uuid]);
    $pdf->Output('I', $nombreArchivo);
    exit();

} catch (Exception $e) {
    $logPdfRequest('render_error', 500, ['uuid' => $uuid, 'error' => $e->getMessage()]);
    while (ob_get_level() > 0) ob_end_clean();
    ml_logear('Error generando PDF: ' . $e->getMessage(), 'error', 'error.log');
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error al generar el informe PDF. Por favor intente nuevamente.';
    exit();
}
