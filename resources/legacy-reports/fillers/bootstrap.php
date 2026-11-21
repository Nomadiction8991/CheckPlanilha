<?php

declare(strict_types=1);

use App\Support\ReportDateFormatter;

if (!function_exists('appReportFormatarDataRelatorio')) {
    function appReportFormatarDataRelatorio($valor): string
    {
        return ReportDateFormatter::format((string)$valor);
    }
}

if (!function_exists('appReportPreencherCampoPorName')) {
    function appReportPreencherCampoPorName(string $html, string $name, $valor): string
    {
        if ($valor === null || $valor === '') {
            return $html;
        }

        $valor = htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
        $valor = addcslashes($valor, '\\$');

        $patternTextarea = '/(<textarea[^>]*name=["\']' . preg_quote($name, '/') . '["\'][^>]*>).*?(<\/textarea>)/s';
        $html = preg_replace($patternTextarea, '${1}' . $valor . '${2}', $html);

        $patternInputWithValue = '/(<input[^>]*name=["\']' . preg_quote($name, '/') . '["\'][^>]*value=["\'])[^"\']*(["\'])/';
        $html = preg_replace($patternInputWithValue, '${1}' . $valor . '${2}', $html);

        $patternInputWithoutValue = '/(<input[^>]*name=["\']' . preg_quote($name, '/') . '["\'][^>]*)(\/?>)/';
        $html = preg_replace($patternInputWithoutValue, '$1 value="' . $valor . '"$2', $html);

        return $html;
    }
}

if (!function_exists('appReportAplicarPreenchimentoRelatorio')) {
    function appReportAplicarPreenchimentoRelatorio(string $formulario, string $html, array $produto, array $planilha): string
    {
        $fillerPath = __DIR__ . '/' . basename($formulario) . '.php';
        if (!is_file($fillerPath)) {
            return $html;
        }

        $preencher = require $fillerPath;
        if (!is_callable($preencher)) {
            return $html;
        }

        $html = (string) $preencher($html, $produto, $planilha);
        $html = appReportPreencherCampoPorName($html, 'n_relatorio', $planilha['codigo'] ?? '');

        $sanitizedHtml = preg_replace('/\s+placeholder=(["\']).*?\1/i', '', $html);

        return $sanitizedHtml === null ? $html : $sanitizedHtml;
    }
}
