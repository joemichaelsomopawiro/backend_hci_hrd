<?php

namespace App\Http\Controllers\Api\Pr;

use App\Http\Controllers\Controller;
use App\Models\PrProgram;
use App\Models\PrEpisode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Constants\Role;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ProgramRegularExcelController extends Controller
{
    const COLOR_GREEN       = '00AA5B';
    const COLOR_HEADER_BG   = '0F172A';
    const COLOR_HEADER_FG   = 'FFFFFF';
    const COLOR_ROW_ODD     = 'FFFFFF';
    const COLOR_ROW_EVEN    = 'F8FAFC';
    const COLOR_LATE        = 'FEE2E2';
    const COLOR_LATE_TXT    = 'B91C1C';
    const COLOR_OK_TXT      = '047857';

    /**
     * Export Program Regular Database as styled XLSX
     * GET /program-regular/database/export/xlsx
     */
    public function exportXlsx(Request $request)
    {
        $user = Auth::user();

        // ─ 1. Fetch episodes with all relations ─
        $episodes = PrEpisode::with([
            'program',
            'creativeWork.createdBy',
            'productionWork',
            'broadcastingWork.createdBy',
            'designGrafisWork.assignedUser',
            'editorPromosiWork.assignedUser',
            'editorWork.assignedUser',
            'promotionWork.createdBy',
            'qualityControlWork.createdBy',
            'qualityControlWork.reviewedBy',
            'managerDistribusiQcWork.createdBy',
            'workflowProgress.assignedUser',
            'crews.user',
        ])
        ->whereHas('program', fn($q) => $q->where('status', '!=', 'cancelled'))
        ->orderBy('air_date', 'desc')
        ->get();

        // ─ 2. Build rows ─
        $rows = [];
        foreach ($episodes as $ep) {
            $program = $ep->program;

            // Hosts / Guests
            $talentData = $ep->creativeWork?->talent_data;
            $hosts  = is_array($talentData) ? ($talentData['host']  ?? $talentData['hosts']  ?? []) : [];
            $guests = is_array($talentData) ? ($talentData['guest'] ?? $talentData['guests'] ?? []) : [];

            // Editor
            $editorName = $ep->editorWork?->assignedUser?->name;
            if (!$editorName) {
                $step6 = $ep->workflowProgress->where('workflow_step', 6)->first();
                $editorName = $step6?->assignedUser?->name;
            }

            // QC
            $qcName = $ep->qualityControlWork?->reviewedBy?->name
                   ?? $ep->qualityControlWork?->createdBy?->name
                   ?? $ep->managerDistribusiQcWork?->createdBy?->name;

            // Budget
            $budgetTotal = 0;
            $creative = $ep->creativeWork;
            if ($creative) {
                $budgetTotal = $creative->budget_data['total'] ?? $creative->total_budget ?? 0;
            }

            // Graphic design
            $gd = $ep->designGrafisWork;
            $gdLinks = [];
            if ($gd) {
                foreach (['episode_poster_link' => 'POSTER', 'youtube_thumbnail_link' => 'YT THUMBNAIL', 'bts_thumbnail_link' => 'BTS THUMBNAIL'] as $col => $label) {
                    if (!empty($gd->$col)) $gdLinks[] = $label . ': ' . $gd->$col;
                }
            }

            // Promotion editor links
            $pe = $ep->editorPromosiWork;
            $peLinks = [];
            if ($pe) {
                foreach (['bts_video_link' => 'BTS', 'tv_ad_link' => 'TV AD', 'ig_highlight_link' => 'IG', 'tv_highlight_link' => 'TV HIGHLIGHT'] as $col => $label) {
                    if (!empty($pe->$col)) $peLinks[] = $label . ': ' . $pe->$col;
                }
            }

            // Producer
            $producerName = $program?->producer?->name
                ?? ($ep->crews->first(fn($c) => strtolower($c->role ?? '') === 'producer')?->user?->name)
                ?? '-';

            // Shooting date
            $shootDate = $ep->creativeWork?->shooting_schedule ?? $ep->production_date ?? null;

            // Air date late check
            $airDate = $ep->air_date;

            $rows[] = [
                'id'           => $ep->id,
                'program'      => $program?->name ?? 'N/A',
                'title'        => $ep->title ?? '-',
                'air_date'     => $airDate,
                'shoot_date'   => $shootDate,
                'producer'     => $producerName,
                'creative_by'  => $creative?->createdBy?->name ?? '-',
                'budget'       => $budgetTotal > 0 ? 'Rp ' . number_format($budgetTotal, 0, ',', '.') : '-',
                'hosts'        => implode(', ', $hosts),
                'guests'       => implode(', ', $guests),
                'editor'       => $editorName ?? '-',
                'graphic'      => implode("\n", $gdLinks) ?: '-',
                'promo_editor' => implode("\n", $peLinks) ?: '-',
                'qc'           => $qcName ?? '-',
                'youtube'      => $ep->broadcastingWork?->youtube_url ?? '-',
                'status'       => $ep->status ?? '-',
            ];
        }

        // ─ 3. Build Spreadsheet ─
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Program Regular Database');

        $columns = [
            ['header' => 'Episode ID',      'key' => 'id',           'width' => 12],
            ['header' => 'Program',          'key' => 'program',      'width' => 22],
            ['header' => 'Episode Title',    'key' => 'title',        'width' => 30],
            ['header' => 'Air Date',         'key' => 'air_date',     'width' => 14],
            ['header' => 'Shooting Date',    'key' => 'shoot_date',   'width' => 20],
            ['header' => 'Producer',         'key' => 'producer',     'width' => 22],
            ['header' => 'Creative By',      'key' => 'creative_by',  'width' => 22],
            ['header' => 'Budget Episode',   'key' => 'budget',       'width' => 18],
            ['header' => 'Host(s)',          'key' => 'hosts',        'width' => 24],
            ['header' => 'Guest(s)',         'key' => 'guests',       'width' => 24],
            ['header' => 'Editor',           'key' => 'editor',       'width' => 22],
            ['header' => 'Graphic Design',   'key' => 'graphic',      'width' => 32],
            ['header' => 'Promo Editor Links','key' => 'promo_editor', 'width' => 32],
            ['header' => 'QC',               'key' => 'qc',           'width' => 22],
            ['header' => 'YouTube URL',      'key' => 'youtube',      'width' => 36],
            ['header' => 'Status',           'key' => 'status',       'width' => 16],
        ];

        $numCols = count($columns);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);

        // ─ Title Row ─
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'HOPE CHANNEL INDONESIA — PROGRAM REGULAR DATABASE');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        // ─ Export date ─
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', 'Generated: ' . now()->format('d F Y, H:i') . ' WIB  |  Total: ' . count($rows) . ' episodes');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '64748B']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // ─ Header Row ─
        foreach ($columns as $ci => $col) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $sheet->setCellValue("{$colLetter}3", $col['header']);
            $sheet->getColumnDimension($colLetter)->setWidth($col['width']);
        }
        $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '00884A']]],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(22);

        // ─ Data Rows ─
        foreach ($rows as $ri => $row) {
            $r     = $ri + 4;
            $isEven = $ri % 2 === 1;
            $rowBg = $isEven ? self::COLOR_ROW_EVEN : self::COLOR_ROW_ODD;

            foreach ($columns as $ci => $col) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
                $sheet->setCellValue("{$colLetter}{$r}", $row[$col['key']] ?? '');
            }

            // Base row style
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                'font'      => ['size' => 10, 'color' => ['rgb' => '1E293B']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']]],
            ]);

            // ID mono style
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font'      => ['size' => 9, 'color' => ['rgb' => '64748B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Program bold
            $sheet->getStyle("B{$r}")->getFont()->setBold(true);

            // Status styling
            $status = strtolower($row['status'] ?? '');
            $statusCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);
            if ($status === 'completed') {
                $sheet->getStyle("{$statusCol}{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => self::COLOR_OK_TXT]],
                ]);
            } elseif (in_array($status, ['late', 'rejected'])) {
                $sheet->getStyle("{$statusCol}{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => self::COLOR_LATE_TXT]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_LATE]],
                ]);
            }

            // Wrap text for long columns
            foreach ([4, 10, 11, 12, 14] as $wci) {
                $wCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($wci);
                $sheet->getStyle("{$wCol}{$r}")->getAlignment()->setWrapText(true);
            }

            $sheet->getRowDimension($r)->setRowHeight(20);
        }

        // ─ Freeze & Filter ─
        $sheet->freezePane('D4');
        $sheet->setAutoFilter("A3:{$lastCol}3");

        // ─ Border around data ─
        $lastRow = count($rows) + 3;
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::COLOR_GREEN]],
            ],
        ]);

        // ─ Page setup ─
        $sheet->getSheetView()->setZoomScale(85);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

        // ─ Import template on sheet 2 ─
        $tplSheet = $spreadsheet->createSheet();
        $tplSheet->setTitle('Import Template');
        $this->buildImportTemplate($tplSheet);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'program_regular_database_' . now()->format('Ymd_His') . '.xlsx';

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
     * Import template sheet for Program Regular Database
     */
    private function buildImportTemplate(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
    {
        $headers = [
            'Program Name', 'Episode Title', 'Air Date (YYYY-MM-DD)',
            'Shooting Date (YYYY-MM-DD or text)', 'Producer', 'Creative By',
            'Budget (number only)', 'Host(s) (comma-separated)', 'Guest(s) (comma-separated)',
            'Editor', 'YouTube URL', 'Status'
        ];
        $widths = [22, 30, 22, 28, 20, 20, 18, 28, 28, 20, 36, 16];

        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'PROGRAM REGULAR IMPORT TEMPLATE — Fill from row 7 onwards');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $instructions = [
            '• "Program Name" must match an existing program in the system exactly.',
            '• Dates: use YYYY-MM-DD format (e.g., 2024-03-15)',
            '• "Host(s)" and "Guest(s)": separate multiple names with a comma (e.g., Budi, Ayu)',
            '• "Budget": number only, no Rp or commas (e.g., 5000000)',
            '• "Status": use one of: draft, in_progress, completed, rejected',
        ];
        foreach ($instructions as $i => $instr) {
            $r = $i + 2;
            $sheet->mergeCells("A{$r}:L{$r}");
            $sheet->setCellValue("A{$r}", $instr);
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '475569']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(16);
        }

        $headerRow = 7;
        foreach ($headers as $c => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $h);
            $sheet->getColumnDimension($col)->setWidth($widths[$c]);
        }
        $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GREEN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '00884A']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // Example row
        $example = ['Talk Hope', 'Ep. 100 - Keluarga Bahagia', '2024-04-01', '2024-03-28', 'Rinto Siagian', 'Sarah Lestari', '5000000', 'Budi Santoso', 'Ayu Lestari, Maria Situmorang', 'Dani Editor', 'https://youtu.be/xxx', 'completed'];
        foreach ($example as $ci => $val) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $sheet->setCellValue("{$col}8", $val);
        }
        $sheet->getStyle('A8:L8')->applyFromArray([
            'font' => ['size' => 10, 'italic' => true, 'color' => ['rgb' => '64748B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'D1FAE5']]],
        ]);

        for ($r = 9; $r <= 100; $r++) {
            $sheet->getStyle("A{$r}:L{$r}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E2E8F0']]],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ($r % 2 === 0 ? 'FFFFFF' : 'F8FAFC')]],
            ]);
            $sheet->getRowDimension($r)->setRowHeight(18);
        }

        $sheet->freezePane('A8');
        $sheet->setAutoFilter("A{$headerRow}:L{$headerRow}");
    }
}
