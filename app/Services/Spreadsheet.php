<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Pembaca/penulis spreadsheet sederhana tanpa pustaka tambahan.
 * Membaca .xlsx (sheet pertama) dan .csv; menulis .xlsx satu sheet.
 */
class Spreadsheet
{
    /** @return list<list<string>> */
    public static function read(string $path, string $extension, ?string $preferSheet = null): array
    {
        return strtolower($extension) === 'xlsx' ? self::readXlsx($path, $preferSheet) : self::readCsv($path);
    }

    /** @return list<list<string>> */
    private static function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $content);
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<list<string>> */
    /** Sheet pertama, atau sheet bernama $preferSheet bila ada (mis. "Soal"). */
    private static function readXlsx(string $path, ?string $preferSheet = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak bisa dibuka.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sst = simplexml_load_string($xml);
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                    $shared[] = $text;
                }
            }
        }

        $sheetPath = 'xl/worksheets/sheet1.xml';
        if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
            $wbXml = simplexml_load_string($wb);
            $wbXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $first = $wbXml->sheets->sheet[0] ?? null;
            foreach ($preferSheet ? $wbXml->sheets->sheet : [] as $candidate) {
                if (strcasecmp(trim((string) $candidate['name']), $preferSheet) === 0) {
                    $first = $candidate;
                }
            }
            if ($first) {
                $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach (simplexml_load_string($rels)->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');
                        $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        $sheet = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('Sheet pertama tidak ditemukan di file Excel.');
        }

        $rows = [];
        foreach (simplexml_load_string($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::columnIndex((string) $c['r']);
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default => (string) $c->v,
                };
                $cells[$col] = trim($value);
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $rows[] = array_map(fn ($i) => $cells[$i] ?? '', range(0, $max));
            }
        }

        return $rows;
    }

    private static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/\d+/', '', $ref);
        $index = 0;
        foreach (str_split($letters) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * Tulis .xlsx satu sheet; baris pertama ditebalkan sebagai judul kolom.
     *
     * @param  list<list<string>>  $rows
     */
    public static function writeXlsx(string $path, array $rows, string $sheetName = 'Data', array $widths = []): void
    {
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $col = function (int $i): string {
            $s = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
                $s = chr(65 + ($i - 1) % 26).$s;
            }

            return $s;
        };

        $sheetRows = '';
        foreach ($rows as $r => $row) {
            $cells = '';
            foreach ($row as $c => $value) {
                $style = $r === 0 ? ' s="1"' : '';
                $cells .= '<c r="'.$col($c).($r + 1).'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$esc($value).'</t></is></c>';
            }
            $sheetRows .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }
        $cols = '';
        foreach ($widths as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$esc($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B7A43"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'.($cols ? '<cols>'.$cols.'</cols>' : '').'<sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();
    }
}
