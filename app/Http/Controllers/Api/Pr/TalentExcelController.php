<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrTalent;
use App\Models\PrProgram;
use App\Models\PrCreativeWork;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

class TalentExcelController extends Controller
{
    // ─────────────────────────── STYLE CONSTANTS ───────────────────────────

    // Brand green (Hope Channel primary)
    const COLOR_GREEN       = '00AA5B';
    const COLOR_GREEN_LIGHT = 'E6F7EF';
    const COLOR_GREEN_MID   = '80D4AD';

    // Red for Total column (matches spreadsheet)
    const COLOR_RED         = 'DC2626';
    const COLOR_RED_LIGHT   = 'FEF2F2';

    // Header row
    const COLOR_HEADER_BG   = '0F172A';   // dark slate
    const COLOR_HEADER_FG   = 'FFFFFF';

    // Alternating rows
    const COLOR_ROW_ODD     = 'FFFFFF';
    const COLOR_ROW_EVEN    = 'F8FAFC';

    // Sub-header (program name row)
    const COLOR_PROG_BG     = '1E40AF';   // blue-800
    const COLOR_PROG_FG     = 'FFFFFF';
    const COLOR_GUEST_BG    = 'DBEAFE';   // blue-100
    const COLOR_HOST_BG     = 'EDE9FE';   // purple-100

    // ───────────────────────────── EXPORT ──────────────────────────────────

    /**
     * Export Talent Database as styled XLSX
     * GET /program-regular/talent-database/export/xlsx
     */
    public function exportXlsx(Request $request)
    {
        // ─ 1. Fetch Data (reuse dashboard logic) ─
        $talents  = PrTalent::orderBy('name')->get();
        $programs = PrProgram::orderBy('name')->get(['id', 'name']);
        $creativeWorks = PrCreativeWork::whereNotNull('talent_data')
            ->with(['episode' => function ($q) { $q->select('id', 'program_id'); }])
            ->get(['id', 'pr_episode_id', 'talent_data']);

        $episodeCounts   = [];
        $allNamesFromCW  = [];

        foreach ($creativeWorks as $cw) {
            if (!$cw->episode) continue;
            $programId  = $cw->episode->program_id;
            $talentData = $cw->talent_data;
            if (!is_array($talentData)) continue;

            foreach (['host', 'guest'] as $role) {
                $names = $talentData[$role] ?? [];
                if (!is_array($names)) $names = $names ? [$names] : [];
                foreach ($names as $name) {
                    $name = trim($name);
                    if (!$name) continue;
                    $allNamesFromCW[$name] = true;
                    $episodeCounts[$name][$programId][$role] = ($episodeCounts[$name][$programId][$role] ?? 0) + 1;
                }
            }
        }

        // Build talent list
        $talentMap = [];
        foreach ($talents as $t) {
            $talentMap[$t->name] = [
                'name'             => $t->name,
                'title'            => $t->title,
                'birth_place_date' => $t->birth_place_date,
                'expertise'        => $t->expertise,
                'social_media'     => $t->social_media,
                'phone'            => $t->phone,
                'type'             => $t->type,
                'episode_counts'   => [],
                'total_episodes'   => 0,
            ];
        }
        foreach ($allNamesFromCW as $name => $_) {
            if (!isset($talentMap[$name])) {
                $talentMap[$name] = [
                    'name'             => $name,
                    'title'            => null,
                    'birth_place_date' => null,
                    'expertise'        => null,
                    'social_media'     => null,
                    'phone'            => null,
                    'type'             => 'other',
                    'episode_counts'   => [],
                    'total_episodes'   => 0,
                ];
            }
            $talentMap[$name]['episode_counts'] = $episodeCounts[$name] ?? [];
        }
        foreach ($talentMap as $name => &$t) {
            $total = 0;
            foreach ($t['episode_counts'] as $pc) {
                $total += ($pc['host'] ?? 0) + ($pc['guest'] ?? 0);
            }
            $t['total_episodes'] = $total;
        }
        unset($t);

        usort($talentMap, fn($a, $b) => strcmp($a['name'], $b['name']));
        $talentList = array_values($talentMap);
        $programList = $programs->toArray();
        $numProgs = count($programList);

        // ─ 2. Build Spreadsheet ─
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Talent Database');

        // Fixed columns: No, Talent Name, Total, Title/Degree, Date of Birth, Expertise, Social Media, Phone
        // Then for each program: Guest | Host
        $fixedCols = 8;
        $totalCols = $fixedCols + ($numProgs * 2);

        // ─ 3. Title Row (row 1) ─
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->setCellValue('A1', 'HOPE CHANNEL INDONESIA — TALENT DATABASE');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        // ─ 4. Subtitle / Date Row (row 2) ─
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->setCellValue('A2', 'Generated: ' . now()->format('d F Y, H:i') . ' WIB');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '64748B']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // ─ 5. Main Header Row (row 3) — fixed columns with rowspan via merge in row 4 too ─
        $fixedHeaders = [
            ['label' => 'No.',            'width' => 6],
            ['label' => 'Talent Name',    'width' => 28],
            ['label' => 'Total Episodes', 'width' => 14],
            ['label' => 'Title / Degree', 'width' => 18],
            ['label' => 'Date of Birth',  'width' => 18],
            ['label' => 'Expertise',      'width' => 32],
            ['label' => 'Social Media',   'width' => 20],
            ['label' => 'Phone',          'width' => 18],
        ];

        // Fixed columns: merge rows 3+4
        for ($i = 0; $i < $fixedCols; $i++) {
            $col    = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $colEnd = $col;
            $sheet->mergeCells("{$col}3:{$colEnd}4");
            $sheet->setCellValue("{$col}3", $fixedHeaders[$i]['label']);
            $sheet->getColumnDimension($col)->setWidth($fixedHeaders[$i]['width']);
        }

        // Program columns (colspan=2, then sub-headers Guest/Host in row 4)
        for ($p = 0; $p < $numProgs; $p++) {
            $colG = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + $p * 2 + 1);
            $colH = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + $p * 2 + 2);
            $sheet->mergeCells("{$colG}3:{$colH}3");
            $sheet->setCellValue("{$colG}3", strtoupper($programList[$p]['name']));
            // Sub headers
            $sheet->setCellValue("{$colG}4", 'Guest');
            $sheet->setCellValue("{$colH}4", 'Host');

            $sheet->getColumnDimension($colG)->setWidth(8);
            $sheet->getColumnDimension($colH)->setWidth(8);
        }

        // Style all of row 3 (program header)
        $sheet->getStyle("A3:{$lastColLetter}3")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COLOR_HEADER_FG]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        // Style row 4 sub-headers
        for ($i = 0; $i < $fixedCols; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getStyle("{$col}4")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COLOR_HEADER_FG]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GREEN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
            ]);
        }

        for ($p = 0; $p < $numProgs; $p++) {
            $colG = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + $p * 2 + 1);
            $colH = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + $p * 2 + 2);
            $sheet->getStyle("{$colG}4")->applyFromArray([
                'font'  => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1D4ED8']],
                'fill'  => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GUEST_BG]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
            $sheet->getStyle("{$colH}4")->applyFromArray([
                'font'  => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '7C3AED']],
                'fill'  => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HOST_BG]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
        }

        $sheet->getRowDimension(3)->setRowHeight(22);
        $sheet->getRowDimension(4)->setRowHeight(18);

        // ─ 6. Data Rows ─
        foreach ($talentList as $idx => $talent) {
            $row   = $idx + 5; // data starts at row 5
            $isEven = ($idx % 2 === 1);
            $rowBg  = $isEven ? self::COLOR_ROW_EVEN : self::COLOR_ROW_ODD;

            // Fixed columns
            $sheet->setCellValue("A{$row}", $idx + 1);
            $sheet->setCellValue("B{$row}", $talent['name']);
            $sheet->setCellValue("C{$row}", $talent['total_episodes']);
            $sheet->setCellValue("D{$row}", $talent['title'] ?? '');
            $sheet->setCellValue("E{$row}", $talent['birth_place_date'] ?? '');
            $sheet->setCellValue("F{$row}", $talent['expertise'] ?? '');
            $sheet->setCellValue("G{$row}", $talent['social_media'] ?? '');
            $sheet->setCellValueExplicit("H{$row}", $talent['phone'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Program episode counts
            for ($p = 0; $p < $numProgs; $p++) {
                $progId = $programList[$p]['id'];
                $colG   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + $p * 2 + 1);
                $colH   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + $p * 2 + 2);
                $g = $talent['episode_counts'][$progId]['guest'] ?? 0;
                $h = $talent['episode_counts'][$progId]['host']  ?? 0;
                if ($g > 0) $sheet->setCellValue("{$colG}{$row}", $g);
                if ($h > 0) $sheet->setCellValue("{$colH}{$row}", $h);
            }

            // Row base style
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
                'font'      => ['size' => 10, 'color' => ['rgb' => '1E293B']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']]],
            ]);

            // No. column — center, light
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font'      => ['size' => 9, 'color' => ['rgb' => '94A3B8']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Name column — bold
            $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(10);

            // Total column — styled pill
            $totalVal = $talent['total_episodes'];
            if ($totalVal > 0) {
                $sheet->getStyle("C{$row}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::COLOR_GREEN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            } else {
                $sheet->getStyle("C{$row}")->applyFromArray([
                    'font'      => ['size' => 10, 'color' => ['rgb' => 'CBD5E1']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            // Type badge color on name cell
            $type = strtolower($talent['type'] ?? 'other');
            if ($type === 'host') {
                $sheet->getStyle("B{$row}")->getFont()->getColor()->setRGB('15803D');
            } elseif ($type === 'guest') {
                $sheet->getStyle("B{$row}")->getFont()->getColor()->setRGB('1D4ED8');
            }

            // Program count cells
            for ($p = 0; $p < $numProgs; $p++) {
                $progId = $programList[$p]['id'];
                $colG = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + $p * 2 + 1);
                $colH = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fixedCols + $p * 2 + 2);
                $g = $talent['episode_counts'][$progId]['guest'] ?? 0;
                $h = $talent['episode_counts'][$progId]['host']  ?? 0;
                if ($g > 0) {
                    $sheet->getStyle("{$colG}{$row}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1D4ED8']],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
                if ($h > 0) {
                    $sheet->getStyle("{$colH}{$row}")->applyFromArray([
                        'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '7C3AED']],
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F3FF']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
            }

            $sheet->getRowDimension($row)->setRowHeight(20);
        }

        // ─ 7. Freeze panes & filters ─
        $sheet->freezePane('C5');
        $sheet->setAutoFilter("A4:{$lastColLetter}4");

        // ─ 8. Outer border ─
        $lastRow = count($talentList) + 4;
        $sheet->getStyle("A3:{$lastColLetter}{$lastRow}")->applyFromArray([
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_GREEN]],
            ],
        ]);

        // ─ 9. Sheet settings ─
        $sheet->getSheetView()->setZoomScale(85);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageSetup()->setFitToWidth(1);

        // ─ 10. Second sheet: Import Template ─
        $tplSheet = $spreadsheet->createSheet();
        $tplSheet->setTitle('Import Template');
        $this->buildImportTemplate($tplSheet);

        $spreadsheet->setActiveSheetIndex(0);

        // ─ 11. Stream to browser ─
        $filename = 'talent_database_' . now()->format('Ymd_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Build a clearly labelled import template on a separate sheet
     */
    private function buildImportTemplate(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $headers = ['Name', 'Title/Degree', 'Date of Birth', 'Expertise', 'Social Media', 'Phone', 'Type (host/guest/other)'];
        $widths  = [30, 20, 22, 40, 22, 18, 24];

        // Title
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'TALENT IMPORT TEMPLATE — Fill in this sheet and import via the website');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Instructions
        $instructions = [
            '• Column "Name" is required. Leave other columns blank if unknown.',
            '• "Type" must be exactly: host, guest, or other (lowercase)',
            '• "Date of Birth" format: City, DD Bulan YYYY  (e.g., Jakarta, 17 Agustus 1985)',
            '• "Social Media" — enter Instagram handle with @ (e.g., @namaakun)',
            '• If importing from an existing export, you can edit any column value before re-importing.',
        ];
        foreach ($instructions as $i => $instr) {
            $r = $i + 2;
            $sheet->mergeCells("A{$r}:G{$r}");
            $sheet->setCellValue("A{$r}", $instr);
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '475569']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(16);
        }

        // Header row (row 7)
        $headerRow = 7;
        foreach ($headers as $c => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $h);
            $sheet->getColumnDimension($col)->setWidth($widths[$c]);
        }
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '00884A']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // Two example rows
        $examples = [
            ['Budi Santoso', 'S.Kom, M.T.', 'Jakarta, 17 Agustus 1985', 'Digital Marketing Specialist', '@budisantoso', '+6281112223344', 'guest'],
            ['Ayu Lestari',  'M.A.',         'Bandung, 10 Mei 1992',      'Psikolog Keluarga',             '@ayulestari_psy', '+6281234567890', 'host'],
        ];
        foreach ($examples as $ei => $ex) {
            $r = $headerRow + 1 + $ei;
            foreach ($ex as $ci => $val) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
                if ($ci === 5) {
                    $sheet->setCellValueExplicit("{$col}{$r}", $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue("{$col}{$r}", $val);
                }
            }
            $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
                'font'      => ['size' => 10, 'color' => ['rgb' => '64748B'], 'italic' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D1FAE5']]],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(18);
        }

        // Empty rows for user input (rows 10–60)
        for ($r = $headerRow + 3; $r <= 60; $r++) {
            $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']]],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ($r % 2 === 0 ? 'FFFFFF' : 'F8FAFC')]],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(18);
        }

        $sheet->freezePane('A8');
        $sheet->setAutoFilter("A{$headerRow}:G{$headerRow}");
    }
}
