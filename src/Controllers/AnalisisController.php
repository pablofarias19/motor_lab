<?php
namespace App\Controllers;

use App\Services\AnalysisService;

class AnalisisController
{
    private AnalysisService $analysisService;

    public function __construct(?AnalysisService $analysisService = null)
    {
        $this->analysisService = $analysisService ?? new AnalysisService();
    }

    public function procesar(array $input): array
    {
        return $this->analysisService->procesar($input);
    }
}
