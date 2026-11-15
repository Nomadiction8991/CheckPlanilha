<?php

declare(strict_types=1);

namespace App\Support;

use Exception;
use RuntimeException;
use ZipArchive;

final class UniversalSpreadsheetReader
{
    /**
     * Garante que o arquivo fornecido esteja no formato CSV legível.
     * Se for um arquivo binário XLS ou XLSX, converte automaticamente para CSV
     * e retorna o caminho do arquivo convertido.
     * Se já for CSV, retorna o próprio caminho.
     */
    public static function ensureCsv(string $filePath): string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return $filePath;
        }

        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return $filePath;
        }
        $magic = (string) fread($handle, 8);
        fclose($handle);

        $isXls = str_starts_with($magic, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
        $isXlsx = str_starts_with($magic, "PK\x03\x04");

        if (!$isXls && !$isXlsx) {
            return $filePath;
        }

        $rows = self::read($filePath);

        $dir = dirname($filePath);
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $csvPath = $dir . DIRECTORY_SEPARATOR . $filename . '_converted.csv';

        $fp = @fopen($csvPath, 'w');
        if ($fp === false) {
            $csvPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '_' . uniqid() . '_converted.csv';
            $fp = @fopen($csvPath, 'w');
        }

        if ($fp === false) {
            return $filePath;
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row, ',', '"', '');
        }
        fclose($fp);

        return $csvPath;
    }

    /**
     * Lê qualquer arquivo de planilha (CSV, XLS ou XLSX) e retorna uma matriz de linhas.
     *
     * @return array<int, array<int, string>>
     */
    public static function read(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("Arquivo não encontrado ou ilegível: {$filePath}");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new Exception("Não foi possível abrir o arquivo: {$filePath}");
        }
        $magic = (string) fread($handle, 8);
        fclose($handle);

        if (str_starts_with($magic, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return self::readXls($filePath);
        }

        if (str_starts_with($magic, "PK\x03\x04")) {
            return self::readXlsx($filePath);
        }

        return self::readCsv($filePath);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function readCsv(string $filePath): array
    {
        $content = (string) file_get_contents($filePath);
        if ($content === '') {
            return [];
        }

        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = (string) mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $delimiter = self::detectCsvDelimiter($content);

        $stream = fopen('php://memory', 'r+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(static fn($c) => trim((string) $c), $row);
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Escolhe o separador pelo CSV parseado (respeita campos entre aspas), não pela
     * contagem de caracteres crus.
     *
     * @param array<int, string> $candidates
     */
    public static function detectCsvDelimiter(string $content, array $candidates = [',', ';']): string
    {
        $bestDelimiter = ',';
        $bestFrequency = 0;
        $bestColumnCount = 1;

        foreach ($candidates as $candidate) {
            $stream = fopen('php://memory', 'r+');
            if ($stream === false) {
                continue;
            }
            fwrite($stream, $content);
            rewind($stream);

            $columnFrequencies = [];
            for ($line = 0; $line < 20 && ($row = fgetcsv($stream, 0, $candidate, '"', '')) !== false; $line++) {
                $columnCount = count($row);
                if ($columnCount > 1) {
                    $columnFrequencies[$columnCount] = ($columnFrequencies[$columnCount] ?? 0) + 1;
                }
            }
            fclose($stream);

            foreach ($columnFrequencies as $columnCount => $frequency) {
                if ($frequency > $bestFrequency || ($frequency === $bestFrequency && $columnCount > $bestColumnCount)) {
                    $bestDelimiter = $candidate;
                    $bestFrequency = $frequency;
                    $bestColumnCount = $columnCount;
                }
            }
        }

        return $bestDelimiter;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function readXlsx(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            // Arquivo com assinatura ZIP mas sem estrutura válida (upload truncado ou
            // corrompido). Lê-lo como CSV despejaria bytes binários como linhas.
            throw new RuntimeException(
                'O arquivo XLSX parece corrompido ou incompleto. Abra a planilha no Excel, salve de novo como .xlsx e envie outra vez.'
            );
        }

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $xml = @simplexml_load_string($ssXml);
            if ($xml !== false && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $t = '';
                        foreach ($si->r as $r) {
                            $t .= (string) $r->t;
                        }
                        $sharedStrings[] = $t;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        $zeroFormatWidths = [];
        $stylesXml = $zip->getFromName('xl/styles.xml');
        if ($stylesXml !== false) {
            $styles = @simplexml_load_string($stylesXml);
            if ($styles !== false) {
                $customFormats = [];
                foreach ($styles->numFmts->numFmt ?? [] as $format) {
                    $formatCode = (string) $format['formatCode'];
                    if (preg_match('/^0+$/', $formatCode) === 1) {
                        $customFormats[(int) $format['numFmtId']] = strlen($formatCode);
                    }
                }

                $styleIndex = 0;
                foreach ($styles->cellXfs->xf ?? [] as $style) {
                    $formatId = (int) $style['numFmtId'];
                    if (isset($customFormats[$formatId])) {
                        $zeroFormatWidths[$styleIndex] = $customFormats[$formatId];
                    }
                    $styleIndex++;
                }
            }
        }

        $sheetXml = false;
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml !== false && $relationshipsXml !== false) {
            $workbook = @simplexml_load_string($workbookXml);
            $relationships = @simplexml_load_string($relationshipsXml);
            $firstSheet = $workbook !== false ? ($workbook->sheets->sheet[0] ?? null) : null;

            if ($firstSheet !== null && $relationships !== false) {
                $sheetAttributes = $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $relationshipId = (string) ($sheetAttributes['id'] ?? '');

                foreach ($relationships->Relationship ?? [] as $relationship) {
                    if ((string) $relationship['Id'] !== $relationshipId) {
                        continue;
                    }

                    $target = ltrim((string) $relationship['Target'], '/');
                    $worksheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                    $sheetXml = $zip->getFromName($worksheetPath);
                    break;
                }
            }
        }

        if ($sheetXml === false) {
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        }
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat && str_starts_with((string) $stat['name'], 'xl/worksheets/sheet') && str_ends_with((string) $stat['name'], '.xml')) {
                    $sheetXml = $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException(
                'O arquivo XLSX não contém uma aba legível. Abra a planilha no Excel, salve de novo como .xlsx e envie outra vez.'
            );
        }

        $xml = @simplexml_load_string($sheetXml);
        if ($xml === false || !isset($xml->sheetData)) {
            throw new RuntimeException(
                'A aba do arquivo XLSX está inválida ou incompleta. Abra a planilha no Excel, salve de novo como .xlsx e envie outra vez.'
            );
        }

        $rows = [];
        /** @var \SimpleXMLElement $sheetData */
        $sheetData = $xml->sheetData;
        if (isset($sheetData->row)) {
            foreach ($sheetData->row as $row) {
                $rIndex = max(0, (int) $row['r'] - 1);
                $rowCells = [];
                if (isset($row->c)) {
                    foreach ($row->c as $cell) {
                        $r = (string) $cell['r'];
                        preg_match('/^([A-Z]+)/', $r, $m);
                        $colLetters = $m[1] ?? 'A';
                        $colIdx = self::lettersToColumnIndex($colLetters);

                        $val = '';
                        $type = (string) $cell['t'];
                        if ($type === 's' && isset($cell->v)) {
                            $sIndex = (int) $cell->v;
                            $val = $sharedStrings[$sIndex] ?? '';
                        } elseif ($type === 'inlineStr' && isset($cell->is)) {
                            if (isset($cell->is->t)) {
                                $val = (string) $cell->is->t;
                            } elseif (isset($cell->is->r)) {
                                foreach ($cell->is->r as $run) {
                                    $val .= (string) ($run->t ?? '');
                                }
                            }
                        } elseif (isset($cell->v)) {
                            $val = (string) $cell->v;
                            $styleIndex = (int) ($cell['s'] ?? 0);
                            if (isset($zeroFormatWidths[$styleIndex])) {
                                $val = self::applyZeroFormat($val, $zeroFormatWidths[$styleIndex]);
                            }
                        }
                        $rowCells[$colIdx] = trim($val, " \t\n\r\0\x0B");
                    }
                }

                if (!empty($rowCells)) {
                    $maxCol = max(array_keys($rowCells));
                    $denseRow = [];
                    for ($c = 0; $c <= $maxCol; $c++) {
                        $denseRow[$c] = $rowCells[$c] ?? '';
                    }
                    $rows[$rIndex] = $denseRow;
                } else {
                    $rows[$rIndex] = [];
                }
            }
        }

        ksort($rows);
        if ($rows === []) {
            return [];
        }

        $linhasComVazias = [];
        $ultimaLinha = max(array_keys($rows));
        for ($indice = 0; $indice <= $ultimaLinha; $indice++) {
            $linhasComVazias[] = $rows[$indice] ?? [];
        }

        return $linhasComVazias;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function readXls(string $filePath): array
    {
        $data = (string) file_get_contents($filePath);
        if (strlen($data) < 512) {
            return [];
        }

        $unpackSec = unpack('v', substr($data, 30, 2));
        $secSize = 1 << ($unpackSec ? (int) $unpackSec[1] : 9);
        $msat = [];
        for ($i = 0; $i < 109; $i++) {
            $u = unpack('V', substr($data, 76 + $i * 4, 4));
            $msat[] = $u ? (int) $u[1] : 0;
        }

        $fat = [];
        foreach ($msat as $s) {
            if ($s < 0xFFFFFFFE) {
                $offset = $secSize + $s * $secSize;
                for ($j = 0; $j < ($secSize / 4); $j++) {
                    $u = unpack('V', substr($data, $offset + $j * 4, 4));
                    $fat[] = $u ? (int) $u[1] : 0;
                }
            }
        }

        $unpackDir = unpack('V', substr($data, 48, 4));
        $firstDirSec = $unpackDir ? (int) $unpackDir[1] : 0;
        $dirData = '';
        $curr = $firstDirSec;
        while ($curr < 0xFFFFFFFE && isset($fat[$curr])) {
            $dirData .= substr($data, $secSize + $curr * $secSize, $secSize);
            $curr = $fat[$curr];
        }

        $miniFat = [];
        $miniFatStart = unpack('V', substr($data, 60, 4));
        $miniFatSectorCount = unpack('V', substr($data, 64, 4));
        $miniFatSector = $miniFatStart ? (int) $miniFatStart[1] : 0xFFFFFFFE;
        $miniFatCount = $miniFatSectorCount ? min((int) $miniFatSectorCount[1], count($fat)) : 0;
        for ($sector = 0; $sector < $miniFatCount && $miniFatSector < 0xFFFFFFFE && isset($fat[$miniFatSector]); $sector++) {
            $miniFatData = substr($data, $secSize + $miniFatSector * $secSize, $secSize);
            for ($offset = 0; $offset + 4 <= strlen($miniFatData); $offset += 4) {
                $entry = unpack('V', substr($miniFatData, $offset, 4));
                $miniFat[] = $entry ? (int) $entry[1] : 0xFFFFFFFE;
            }
            $miniFatSector = $fat[$miniFatSector];
        }

        $wbStartSec = null;
        $wbSize = 0;
        $rootStartSec = null;
        $rootSize = 0;
        $totalEntries = (int) (strlen($dirData) / 128);
        for ($i = 0; $i < $totalEntries; $i++) {
            $chunk = substr($dirData, $i * 128, 128);
            if (strlen($chunk) < 128) {
                break;
            }
            $uLen = unpack('v', substr($chunk, 64, 2));
            $nameLen = $uLen ? (int) $uLen[1] : 0;
            $nameRaw = substr($chunk, 0, $nameLen);
            $name = (string) @iconv('UTF-16LE', 'UTF-8//IGNORE', $nameRaw);
            $name = trim($name, "\x00");

            $entryType = ord($chunk[66]);
            if ($entryType === 0x05) {
                $uSec = unpack('V', substr($chunk, 116, 4));
                $uSize = unpack('V', substr($chunk, 120, 4));
                $rootStartSec = $uSec ? (int) $uSec[1] : null;
                $rootSize = $uSize ? (int) $uSize[1] : 0;
            }

            if ($name === 'Workbook' || $name === 'Book') {
                $uSec = unpack('V', substr($chunk, 116, 4));
                $uSize = unpack('V', substr($chunk, 120, 4));
                $wbStartSec = $uSec ? (int) $uSec[1] : 0;
                $wbSize = $uSize ? (int) $uSize[1] : 0;
                break;
            }
        }

        if ($wbStartSec === null) {
            return [];
        }

        $miniStreamCutoff = unpack('V', substr($data, 56, 4));
        $miniStreamCutoff = $miniStreamCutoff ? (int) $miniStreamCutoff[1] : 4096;
        $wbData = '';
        if ($wbSize < $miniStreamCutoff && $rootStartSec !== null) {
            $miniStream = '';
            $curr = $rootStartSec;
            while ($curr < 0xFFFFFFFE && isset($fat[$curr])) {
                $miniStream .= substr($data, $secSize + $curr * $secSize, $secSize);
                $curr = $fat[$curr];
            }
            $miniStream = substr($miniStream, 0, $rootSize);
            $miniSectorSize = 1 << ((unpack('v', substr($data, 32, 2))[1] ?? 6));
            $curr = $wbStartSec;
            while ($curr < 0xFFFFFFFE && isset($miniFat[$curr])) {
                $wbData .= substr($miniStream, $curr * $miniSectorSize, $miniSectorSize);
                $curr = $miniFat[$curr];
            }
        } else {
            $curr = $wbStartSec;
            while ($curr < 0xFFFFFFFE && isset($fat[$curr])) {
                $wbData .= substr($data, $secSize + $curr * $secSize, $secSize);
                $curr = $fat[$curr];
            }
        }
        $wbData = substr($wbData, 0, $wbSize);

        $cells = [];
        $dataLen = strlen($wbData);
        $sharedStrings = [];
        $recordPos = 0;
        while ($recordPos <= $dataLen - 4) {
            $recordCode = unpack('v', substr($wbData, $recordPos, 2));
            $recordLength = unpack('v', substr($wbData, $recordPos + 2, 2));
            $code = $recordCode ? (int) $recordCode[1] : 0;
            $length = $recordLength ? (int) $recordLength[1] : 0;
            if ($recordPos + 4 + $length > $dataLen) {
                break;
            }

            if ($code === 0x00FC) { // SST: Shared String Table
                $segments = [substr($wbData, $recordPos + 4, $length)];
                $nextPos = $recordPos + 4 + $length;
                while ($nextPos <= $dataLen - 4) {
                    $nextCode = unpack('v', substr($wbData, $nextPos, 2));
                    $nextLength = unpack('v', substr($wbData, $nextPos + 2, 2));
                    if (($nextCode ? (int) $nextCode[1] : 0) !== 0x003C) {
                        break;
                    }
                    $continueLength = $nextLength ? (int) $nextLength[1] : 0;
                    if ($nextPos + 4 + $continueLength > $dataLen) {
                        break;
                    }
                    $segments[] = substr($wbData, $nextPos + 4, $continueLength);
                    $nextPos += 4 + $continueLength;
                }
                $sharedStrings = self::parseBiffSharedStrings($segments);
                break;
            }

            $recordPos += 4 + $length;
        }

        $maxRow = 0;
        $maxCol = 0;
        $sheetInicio = self::posicaoPrimeiraAbaXls($wbData);
        $pos = $sheetInicio ?? 0;
        $textoFormulaPendente = null;

        while ($pos < $dataLen - 4) {
            $uCode = unpack('v', substr($wbData, $pos, 2));
            $uLen = unpack('v', substr($wbData, $pos + 2, 2));
            $code = $uCode ? (int) $uCode[1] : 0;
            $length = $uLen ? (int) $uLen[1] : 0;
            $rec = substr($wbData, $pos + 4, $length);

            // Um registro STRING só vale para a FORMULA imediatamente anterior.
            $textoFormula = $textoFormulaPendente;
            $textoFormulaPendente = null;

            if ($code === 0x000A && $sheetInicio !== null) { // EOF: fim da primeira aba
                break;
            }

            if ($code === 0x0006 && strlen($rec) >= 14) { // FORMULA: resultado calculado da célula
                $uR = unpack('v', substr($rec, 0, 2));
                $uC = unpack('v', substr($rec, 2, 2));
                $row = $uR ? (int) $uR[1] : 0;
                $col = $uC ? (int) $uC[1] : 0;
                $resultado = substr($rec, 6, 8);
                $maxRow = max($maxRow, $row);
                $maxCol = max($maxCol, $col);
                if (substr($resultado, 6, 2) !== "\xFF\xFF") {
                    $numVal = unpack('d', $resultado);
                    $cells[$row][$col] = self::formatarNumeroXls($numVal ? (float) $numVal[1] : 0.0, $col);
                } elseif (ord($resultado[0]) === 0x00) {
                    // Resultado textual: o texto vem no STRING logo depois.
                    $textoFormulaPendente = [$row, $col];
                }
            } elseif ($code === 0x0207 && $textoFormula !== null && strlen($rec) >= 3) { // STRING: texto do resultado
                [$row, $col] = $textoFormula;
                $cch = unpack('v', substr($rec, 0, 2));
                $strLen = $cch ? (int) $cch[1] : 0;
                if ((ord($rec[2]) & 1) !== 0) {
                    $val = (string) @iconv('UTF-16LE', 'UTF-8//IGNORE', substr($rec, 3, $strLen * 2));
                } else {
                    $val = (string) @iconv('ISO-8859-1', 'UTF-8//IGNORE', substr($rec, 3, $strLen));
                }
                $cells[$row][$col] = trim($val, " \t\n\r\0\x0B");
                $maxRow = max($maxRow, $row);
                $maxCol = max($maxCol, $col);
            } elseif ($code === 0x0204 && strlen($rec) >= 8) { // LABEL
                $uR = unpack('v', substr($rec, 0, 2));
                $uC = unpack('v', substr($rec, 2, 2));
                $uS = unpack('v', substr($rec, 6, 2));
                $row = $uR ? (int) $uR[1] : 0;
                $col = $uC ? (int) $uC[1] : 0;
                $strLen = $uS ? (int) $uS[1] : 0;
                $val = '';
                if (strlen($rec) > 8) {
                    $flags = ord($rec[8]);
                    if ($flags & 1) {
                        $rawStr = substr($rec, 9, $strLen * 2);
                        $val = (string) @iconv('UTF-16LE', 'UTF-8//IGNORE', $rawStr);
                    } else {
                        $rawStr = substr($rec, 9, $strLen);
                        $val = (string) @iconv('ISO-8859-1', 'UTF-8//IGNORE', $rawStr);
                    }
                }
                $cells[$row][$col] = trim($val, " \t\n\r\0\x0B");
                $maxRow = max($maxRow, $row);
                $maxCol = max($maxCol, $col);
            } elseif ($code === 0x00FD && strlen($rec) >= 10) { // LABELSST
                $uR = unpack('v', substr($rec, 0, 2));
                $uC = unpack('v', substr($rec, 2, 2));
                $uSst = unpack('V', substr($rec, 6, 4));
                $row = $uR ? (int) $uR[1] : 0;
                $col = $uC ? (int) $uC[1] : 0;
                $sstIndex = $uSst ? (int) $uSst[1] : -1;
                $cells[$row][$col] = trim($sharedStrings[$sstIndex] ?? '', " \t\n\r\0\x0B");
                $maxRow = max($maxRow, $row);
                $maxCol = max($maxCol, $col);
            } elseif ($code === 0x0203 && strlen($rec) >= 14) { // NUMBER
                $uR = unpack('v', substr($rec, 0, 2));
                $uC = unpack('v', substr($rec, 2, 2));
                $uNum = unpack('d', substr($rec, 6, 8));
                $row = $uR ? (int) $uR[1] : 0;
                $col = $uC ? (int) $uC[1] : 0;
                $numVal = $uNum ? (float) $uNum[1] : 0.0;
                $cells[$row][$col] = self::formatarNumeroXls($numVal, $col);
                $maxRow = max($maxRow, $row);
                $maxCol = max($maxCol, $col);
            } elseif ($code === 0x027E && strlen($rec) >= 10) { // RK: número compactado em 4 bytes
                $uR = unpack('v', substr($rec, 0, 2));
                $uC = unpack('v', substr($rec, 2, 2));
                $row = $uR ? (int) $uR[1] : 0;
                $col = $uC ? (int) $uC[1] : 0;
                $cells[$row][$col] = self::formatarNumeroXls(self::decodificarRk(substr($rec, 6, 4)), $col);
                $maxRow = max($maxRow, $row);
                $maxCol = max($maxCol, $col);
            } elseif ($code === 0x00BD && strlen($rec) >= 10) { // MULRK: vários RK consecutivos na mesma linha
                $uR = unpack('v', substr($rec, 0, 2));
                $uFirst = unpack('v', substr($rec, 2, 2));
                $row = $uR ? (int) $uR[1] : 0;
                $firstCol = $uFirst ? (int) $uFirst[1] : 0;
                $cellCount = intdiv(strlen($rec) - 6, 6);
                for ($i = 0; $i < $cellCount; $i++) {
                    $col = $firstCol + $i;
                    $cells[$row][$col] = self::formatarNumeroXls(
                        self::decodificarRk(substr($rec, 4 + $i * 6 + 2, 4)),
                        $col
                    );
                    $maxRow = max($maxRow, $row);
                    $maxCol = max($maxCol, $col);
                }
            }

            $pos += 4 + $length;
        }

        $rows = [];
        for ($r = 0; $r <= $maxRow; $r++) {
            $denseRow = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $denseRow[$c] = $cells[$r][$c] ?? '';
            }
            $rows[$r] = $denseRow;
        }

        return $rows;
    }

    /**
     * Posição, no stream do Workbook, do BOF da primeira aba (BOUNDSHEET do tipo planilha).
     * Retorna null quando o arquivo não declara nenhuma aba de planilha.
     */
    private static function posicaoPrimeiraAbaXls(string $wbData): ?int
    {
        $dataLen = strlen($wbData);
        $pos = 0;
        while ($pos <= $dataLen - 4) {
            $uCode = unpack('v', substr($wbData, $pos, 2));
            $uLen = unpack('v', substr($wbData, $pos + 2, 2));
            $code = $uCode ? (int) $uCode[1] : 0;
            $length = $uLen ? (int) $uLen[1] : 0;

            if ($code === 0x0085 && $length >= 6) { // BOUNDSHEET: uma entrada por aba, na ordem das abas
                $rec = substr($wbData, $pos + 4, $length);
                $uPos = unpack('V', substr($rec, 0, 4));
                if ($uPos && ord($rec[5]) === 0x00) {
                    return (int) $uPos[1];
                }
            }

            if ($code === 0x000A) { // EOF das globais: as abas já foram declaradas
                break;
            }
            $pos += 4 + $length;
        }

        return null;
    }

    /**
     * Decodifica o campo RK do BIFF (4 bytes): bit 0 = divide por 100, bit 1 = inteiro de 30 bits;
     * caso contrário, os 30 bits altos de um double IEEE 754.
     */
    private static function decodificarRk(string $bytes): float
    {
        $rk = unpack('V', $bytes)[1];
        $dividirPor100 = ($rk & 0x01) !== 0;

        if (($rk & 0x02) !== 0) {
            $valor = (float) ($rk >> 2 & 0x3FFFFFFF);
            if ($rk & 0x80000000) {
                $valor -= 0x40000000;
            }
        } else {
            $valor = (float) unpack('d', pack('V2', 0, $rk & 0xFFFFFFFC))[1];
        }

        return $dividirPor100 ? $valor / 100 : $valor;
    }

    /** Formata número de célula XLS: data serial na coluna de data, inteiro sem casas decimais, ou valor cru. */
    private static function formatarNumeroXls(float $numVal, int $col): string
    {
        // Detectar se é data serial do Excel (ex: 41274 = 31/12/2012)
        if ($numVal >= 10000 && $numVal <= 70000 && floor($numVal) == $numVal && $col == 19) {
            return gmdate('d/m/Y', (int) (($numVal - 25569) * 86400));
        }

        if (floor($numVal) == $numVal && abs($numVal) < 1e15) {
            return (string) (int) $numVal;
        }

        return (string) $numVal;
    }

    private static function lettersToColumnIndex(string $letters): int
    {
        $letters = strtoupper(trim($letters));
        $len = strlen($letters);
        $col = 0;
        for ($i = 0; $i < $len; $i++) {
            $col = $col * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return max(0, $col - 1);
    }

    private static function applyZeroFormat(string $value, int $width): string
    {
        if (preg_match('/^(-?)(\d+)$/', $value, $matches) !== 1) {
            return $value;
        }

        return $matches[1] . str_pad($matches[2], $width, '0', STR_PAD_LEFT);
    }

    /** @return array<int, string> */
    private static function parseBiffSharedStrings(array $segments): array
    {
        if ($segments === [] || strlen($segments[0]) < 8) {
            return [];
        }

        $counts = unpack('Vtotal/Vunique', substr($segments[0], 0, 8));
        $stringCount = min((int) ($counts['unique'] ?? 0), 100000);
        $segmentIndex = 0;
        $segmentOffset = 8;
        $readBytes = static function (int $length) use (&$segments, &$segmentIndex, &$segmentOffset): ?string {
            $result = '';
            while ($length > 0) {
                while (isset($segments[$segmentIndex]) && $segmentOffset >= strlen($segments[$segmentIndex])) {
                    $segmentIndex++;
                    $segmentOffset = 0;
                }
                if (!isset($segments[$segmentIndex])) {
                    return null;
                }
                $available = strlen($segments[$segmentIndex]) - $segmentOffset;
                $take = min($length, $available);
                $result .= substr($segments[$segmentIndex], $segmentOffset, $take);
                $segmentOffset += $take;
                $length -= $take;
            }

            return $result;
        };
        $strings = [];

        for ($index = 0; $index < $stringCount; $index++) {
            $length = $readBytes(2);
            $flagByte = $readBytes(1);
            if ($length === null || $flagByte === null) {
                break;
            }
            $lengthValue = unpack('v', $length);
            $characterCount = $lengthValue ? (int) $lengthValue[1] : 0;
            $flags = ord($flagByte);

            $richRunCount = 0;
            if (($flags & 0x08) !== 0) {
                $runs = $readBytes(2);
                if ($runs === null) {
                    break;
                }
                $runCount = unpack('v', $runs);
                $richRunCount = $runCount ? (int) $runCount[1] : 0;
            }

            $extensionLength = 0;
            if (($flags & 0x04) !== 0) {
                $extension = $readBytes(4);
                if ($extension === null) {
                    break;
                }
                $extensionSize = unpack('V', $extension);
                $extensionLength = $extensionSize ? (int) $extensionSize[1] : 0;
            }

            $bytesPerCharacter = ($flags & 0x01) !== 0 ? 2 : 1;
            $text = '';
            $remainingCharacters = $characterCount;
            while ($remainingCharacters > 0) {
                while (isset($segments[$segmentIndex]) && $segmentOffset >= strlen($segments[$segmentIndex])) {
                    $segmentIndex++;
                    $segmentOffset = 0;
                    if (!isset($segments[$segmentIndex])) {
                        break 2;
                    }
                    $continuationFlags = $readBytes(1);
                    if ($continuationFlags === null) {
                        break 2;
                    }
                    $bytesPerCharacter = (ord($continuationFlags) & 0x01) !== 0 ? 2 : 1;
                }

                $availableBytes = strlen($segments[$segmentIndex]) - $segmentOffset;
                $availableCharacters = intdiv($availableBytes, $bytesPerCharacter);
                if ($availableCharacters === 0) {
                    $segmentOffset = strlen($segments[$segmentIndex]);
                    continue;
                }
                $takeCharacters = min($remainingCharacters, $availableCharacters);
                $part = $readBytes($takeCharacters * $bytesPerCharacter);
                if ($part === null) {
                    break;
                }
                // Cada trecho usa a codificação do seu próprio registro (CONTINUE pode trocar 8 bits por UTF-16).
                $text .= $bytesPerCharacter === 2
                    ? (string) @iconv('UTF-16LE', 'UTF-8//IGNORE', $part)
                    : (string) @iconv('Windows-1252', 'UTF-8//IGNORE', $part);
                $remainingCharacters -= $takeCharacters;
            }

            if ($remainingCharacters > 0) {
                break;
            }
            $strings[] = $text;
            if ($readBytes($richRunCount * 4 + $extensionLength) === null) {
                break;
            }
        }

        return $strings;
    }
}
