<?php

namespace Tests\Unit;

use App\Services\Spreadsheet;
use PHPUnit\Framework\TestCase;

class SpreadsheetTest extends TestCase
{
    public function test_xlsx_round_trip_and_csv_semicolon(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        Spreadsheet::writeXlsx($path, [['NRP', 'NAMA'], ['KT1', 'Ahmad & "Fauzi"']]);
        $this->assertSame([['NRP', 'NAMA'], ['KT1', 'Ahmad & "Fauzi"']], Spreadsheet::read($path, 'xlsx'));

        $csv = tempnam(sys_get_temp_dir(), 'c');
        file_put_contents($csv, "\xEF\xBB\xBFNRP;NAMA\nKT2;Budi\n");
        $this->assertSame([['NRP', 'NAMA'], ['KT2', 'Budi']], Spreadsheet::read($csv, 'csv'));
    }
}
