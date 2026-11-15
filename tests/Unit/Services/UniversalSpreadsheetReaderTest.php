<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Support\UniversalSpreadsheetReader;
use Tests\TestCase;
use ZipArchive;

final class UniversalSpreadsheetReaderTest extends TestCase
{
    public function test_le_xls_biff_com_celulas_ficticias(): void
    {
        $linhas = UniversalSpreadsheetReader::read(base_path('tests/Fixtures/importacao/relatorio-ficticio.xls'));

        self::assertCount(28, $linhas);
        self::assertSame('99-9999 / 0001', $linhas[26][0]);
        self::assertSame('4 - CADEIRA AZUL', $linhas[26][3]);
        self::assertSame('SALA DE TESTE', $linhas[27][15]);
    }

    public function test_le_csv_com_dados_ficticios_e_preserva_celulas(): void
    {
        $caminho = base_path('tests/Fixtures/importacao/exemplo-leitor.csv');

        self::assertSame($caminho, UniversalSpreadsheetReader::ensureCsv($caminho));
        self::assertSame([
            ['Código', 'Descrição', 'Localidade'],
            ['001', 'Cadeira azul', 'Sala de teste'],
            ['002', 'Mesa branca', 'Escritório de teste'],
        ], UniversalSpreadsheetReader::read($caminho));
    }

    public function test_xlsx_corrompido_gera_erro_acionavel_em_vez_de_ler_binario_como_csv(): void
    {
        $caminho = sys_get_temp_dir() . '/planilha-corrompida-' . uniqid('', true) . '.xlsx';
        file_put_contents($caminho, "PK\x03\x04\x14\x00\x00\x00conteudo-truncado\x00\x01\x02");

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('corrompido');

            UniversalSpreadsheetReader::read($caminho);
        } finally {
            @unlink($caminho);
        }
    }

    public function test_xlsx_zip_sem_aba_legivel_gera_erro_acionavel(): void
    {
        $caminho = sys_get_temp_dir() . '/planilha-sem-aba-' . uniqid('', true) . '.xlsx';
        $zip = new ZipArchive();

        self::assertSame(true, $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        $zip->close();

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('aba legível');

            UniversalSpreadsheetReader::read($caminho);
        } finally {
            @unlink($caminho);
        }
    }

    public function test_le_xlsx_preservando_linhas_vazias_para_numero_da_linha_original(): void
    {
        $caminho = sys_get_temp_dir() . '/planilha-com-linha-vazia-' . uniqid('', true) . '.xlsx';
        $caminhoConvertido = null;
        $zip = new ZipArchive();

        self::assertSame(true, $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
              <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
              <Default Extension="xml" ContentType="application/xml"/>
              <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
              <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            </Types>
            XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
              <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
            </Relationships>
            XML);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
              <sheets><sheet name="Inventário" sheetId="1" r:id="rId1"/></sheets>
            </workbook>
            XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
              <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
            </Relationships>
            XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
              <sheetData>
                <row r="1"><c r="A1" t="inlineStr"><is><t>Código</t></is></c></row>
                <row r="3"><c r="A3" t="inlineStr"><is><t>Item com erro</t></is></c></row>
              </sheetData>
            </worksheet>
            XML);
        $zip->close();

        try {
            $linhas = UniversalSpreadsheetReader::read($caminho);

            self::assertCount(3, $linhas);
            self::assertSame([], $linhas[1]);
            self::assertSame(['Item com erro'], $linhas[2]);

            $caminhoConvertido = UniversalSpreadsheetReader::ensureCsv($caminho);
            self::assertNotSame($caminho, $caminhoConvertido);
            self::assertCount(3, UniversalSpreadsheetReader::read($caminhoConvertido));
        } finally {
            if (is_string($caminhoConvertido)) {
                @unlink($caminhoConvertido);
            }
            @unlink($caminho);
        }
    }

    public function test_le_a_primeira_aba_declarada_no_workbook_mesmo_quando_nao_e_sheet1(): void
    {
        $caminho = sys_get_temp_dir() . '/planilha-abas-reordenadas-' . uniqid('', true) . '.xlsx';
        $zip = new ZipArchive();

        self::assertSame(true, $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
              <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
              <Default Extension="xml" ContentType="application/xml"/>
              <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
              <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
              <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            </Types>
            XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
              <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
            </Relationships>
            XML);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
              <sheets>
                <sheet name="Inventário" sheetId="2" r:id="rId2"/>
                <sheet name="Arquivo" sheetId="1" r:id="rId1"/>
              </sheets>
            </workbook>
            XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
              <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
              <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
            </Relationships>
            XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Arquivo antigo</t></is></c></row></sheetData></worksheet>
            XML);
        $zip->addFromString('xl/worksheets/sheet2.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Código do inventário</t></is></c></row></sheetData></worksheet>
            XML);
        $zip->close();

        try {
            self::assertSame([['Código do inventário']], UniversalSpreadsheetReader::read($caminho));
        } finally {
            @unlink($caminho);
        }
    }
}
