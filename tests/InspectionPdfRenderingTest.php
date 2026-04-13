<?php
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/config/IrilEngine.php';
require_once dirname(__DIR__) . '/config/EscenariosEngine.php';
require_once dirname(__DIR__) . '/config/ExposicionEngine.php';
require_once __DIR__ . '/TestCase.php';

final class InspectionPdfRenderingTest extends TestCase
{
    public function run(): void
    {
        $this->ensureFakeFpdfLibrary();

        $inspectionPdf = $this->renderPdf([
            'tipo_usuario' => 'empleador',
            'tipo_conflicto' => 'riesgo_inspeccion',
            'datos_laborales' => [
                'salario' => 500000,
                'salario_recibo' => 250000,
                'provincia' => 'CABA',
                'categoria' => 'Administrativo',
                'cct' => '130/75',
                'cantidad_empleados' => 12,
                'tipo_registro' => 'deficiente_salario',
                'razon_social' => 'ACME SA',
                'cuit' => '30-12345678-9',
            ],
            'documentacion' => [
                'registrado_afip' => 'si',
                'tiene_contrato' => 'si',
                'pago_bancario' => 'si',
                'tiene_telegramas' => 'si',
            ],
            'situacion' => [
                'meses_no_registrados' => 8,
                'inspeccion_previa' => 'si',
                'chk_alta_sipa' => 'si',
                'chk_libro_art52' => 'no',
                'chk_recibos_cct' => 'no',
                'chk_art_vigente' => 'si',
                'chk_examenes' => 'no',
                'chk_epp_rgrl' => 'no',
                'falta_f931_art' => 'si',
                'fraude_evasion_sistematica' => 'si',
                'dias_desde_reglamentacion' => 45,
                'modalidad_pago_regularizacion' => 'plan_corto',
                'cuotas_regularizacion' => 3,
                'aplica_blanco_laboral' => 'si',
                'obligacion_cancelada_antes_2024_03_31' => 'si',
                'hay_apropiacion_indebida' => 'si',
                'honorarios_estimados' => 100000,
                'activos_regularizables' => [
                    ['tipo' => 'dinero', 'ubicacion' => 'argentina', 'valor_usd' => 200000],
                ],
                'tipo_cambio_regularizacion' => 1000,
                'etapa_regularizacion' => 1,
            ],
            'contacto' => [
                'email' => 'pdf-risk@example.com',
            ],
        ]);

        $inspectionSearch = $this->normalizeText($inspectionPdf);
        $this->assertTrue(str_contains($inspectionSearch, 'informe preventivo de inspeccion arca'), 'El PDF de riesgo de inspección debe incluir la sección ARCA.');
        $this->assertTrue(str_contains($inspectionSearch, 'matriz de riesgo laboral'), 'El PDF de riesgo de inspección debe incluir la matriz laboral.');
        $this->assertTrue(str_contains($inspectionSearch, 'recomendacion final: defensa estructurada'), 'El PDF de riesgo de inspección debe exponer la recomendación laboral final.');
        $this->assertTrue(str_contains($inspectionSearch, 'recomendacion principal: regularizacion inmediata'), 'El PDF de riesgo de inspección debe exponer la recomendación ARCA principal.');
        $this->assertTrue(str_contains($inspectionSearch, 'probabilidad inspeccion: critico'), 'El PDF de riesgo de inspección debe exponer la probabilidad crítica laboral.');
        $this->assertTrue(str_contains($inspectionSearch, 'fundamentos de los montos laborales'), 'El PDF de riesgo de inspección debe explicar los motivos de los montos laborales.');
        $this->assertTrue(str_contains($inspectionSearch, 'consideraciones legales relevantes (laboral)'), 'El PDF de riesgo de inspección debe incluir consideraciones legales laborales.');
        $this->assertTrue(str_contains($inspectionSearch, 'ley bases no 27.742: aplica'), 'El PDF de riesgo de inspección debe indicar aplicabilidad legal en el bloque ARCA.');

        $auditPdf = $this->renderPdf([
            'tipo_usuario' => 'empleador',
            'tipo_conflicto' => 'auditoria_preventiva',
            'datos_laborales' => [
                'salario' => 500000,
                'provincia' => 'CABA',
                'categoria' => 'Administrativo',
                'cct' => '130/75',
                'cantidad_empleados' => 18,
            ],
            'documentacion' => [
                'registrado_afip' => 'no',
                'pago_bancario' => 'no',
            ],
            'situacion' => [
                'meses_no_registrados' => 12,
                'meses_en_mora' => 8,
                'probabilidad_condena' => 0.8,
                'aplica_blanco_laboral' => 'si',
            ],
            'contacto' => [
                'email' => 'pdf-audit@example.com',
            ],
        ]);

        $auditSearch = $this->normalizeText($auditPdf);
        $this->assertTrue(str_contains($auditSearch, 'auditoria preventiva'), 'El PDF de auditoría debe incluir el bloque de auditoría preventiva.');
        $this->assertTrue(str_contains($auditSearch, 'recomendacion accion: regularizar (alta conveniencia)'), 'El PDF de auditoría debe incluir la recomendación de acción.');
        $this->assertTrue(str_contains($auditSearch, 'texto estrategico: el costo de regularizar espontaneamente es significativamente menor que enfrentar un litigio.'), 'El PDF de auditoría debe incluir el texto estratégico esperado.');
    }

    private function renderPdf(array $payload): string
    {
        $projectRoot = $this->projectRoot();
        $service = new App\Services\AnalysisService(
            new class extends App\Database\DatabaseManager {
                public function __construct()
                {
                }

                public function insertarAnalisis(string $uuid, string $tipoUsuario, string $tipoConflicto, array $datosLaborales, array $documentacion, array $situacion, string $email = ''): int
                {
                    throw new RuntimeException('db offline');
                }
            }
        );

        $result = $service->procesar($payload);
        $record = App\Support\AnalysisSessionStore::fetch($result['uuid']);
        $this->assertNotNull($record, 'Se esperaba encontrar el análisis temporal para renderizar el PDF.');

        $wrapperPath = sys_get_temp_dir() . '/motor_lab_pdf_wrapper_' . bin2hex(random_bytes(6)) . '.php';
        $recordPath = sys_get_temp_dir() . '/motor_lab_pdf_record_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($recordPath, json_encode($record, JSON_UNESCAPED_UNICODE));
        $wrapper = <<<PHP
<?php
require '{$projectRoot}/tests/bootstrap.php';
\$record = json_decode((string) file_get_contents(\$argv[1]), true);
App\Support\AnalysisSessionStore::remember(is_array(\$record) ? \$record : []);
\$_GET['uuid'] = \$argv[2];
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['SCRIPT_NAME'] = '/api/generar_informe.php';
\$_SERVER['SCRIPT_FILENAME'] = '{$projectRoot}/api/generar_informe.php';
require '{$projectRoot}/api/generar_informe.php';
PHP;
        file_put_contents($wrapperPath, $wrapper);

        $output = [];
        $exitCode = 0;
        exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($wrapperPath) . ' '
            . escapeshellarg($recordPath) . ' '
            . escapeshellarg($result['uuid']) . ' 2>&1',
            $output,
            $exitCode
        );

        @unlink($wrapperPath);
        @unlink($recordPath);

        $this->assertSame(0, $exitCode, 'La generación del PDF debe completar sin errores.');

        $pdfText = implode("\n", $output);
        if (function_exists('mb_convert_encoding')) {
            $pdfText = mb_convert_encoding($pdfText, 'UTF-8', 'ISO-8859-1');
        } elseif (function_exists('iconv')) {
            $converted = iconv('ISO-8859-1', 'UTF-8//IGNORE', $pdfText);
            if ($converted !== false) {
                $pdfText = $converted;
            }
        }
        $this->assertTrue(
            str_contains($pdfText, '%PDF-STUB'),
            'Se esperaba salida del renderizador PDF de prueba. Salida recibida: ' . substr($pdfText, 0, 200)
        );

        return $pdfText;
    }

    private function ensureFakeFpdfLibrary(): void
    {
        $dir = dirname(ML_FPDF_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $fakeFpdf = <<<'PHP'
<?php
class FPDF
{
    protected array $buffer = [];
    protected int $page = 0;

    public function __construct(...$args)
    {
    }

    public function __call($name, $arguments)
    {
        return null;
    }

    public function AddPage($orientation = '', $size = '', $rotation = 0): void
    {
        $this->page++;
        if (method_exists($this, 'Header')) {
            $this->Header();
        }
    }

    public function Cell($w = 0, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = ''): void
    {
        $this->buffer[] = (string) $txt;
        if ($ln > 0) {
            $this->buffer[] = "\n";
        }
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false): void
    {
        $this->buffer[] = (string) $txt;
        $this->buffer[] = "\n";
    }

    public function Ln($h = null): void
    {
        $this->buffer[] = "\n";
    }

    public function PageNo(): int
    {
        return max(1, $this->page);
    }

    public function Output($dest = '', $name = '', $isUtf8 = false)
    {
        $content = "%PDF-STUB\n" . implode("\n", $this->buffer);
        if ($dest === 'S') {
            return $content;
        }

        echo $content;
        return '';
    }
}
PHP;

        file_put_contents(ML_FPDF_PATH, $fakeFpdf);
    }

    private function projectRoot(): string
    {
        return realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    }

    private function normalizeText(string $text): string
    {
        $text = strtolower($text);
        $converted = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : false;
        if ($converted !== false) {
            $text = $converted;
        }

        $normalized = preg_replace('/\s+/', ' ', $text);
        return trim($normalized ?? $text);
    }
}
