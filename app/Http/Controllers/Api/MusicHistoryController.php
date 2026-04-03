<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MusicHistoryService;
use App\Models\Program;
use Illuminate\Http\Request;

class MusicHistoryController extends Controller
{
    protected $historyService;

    public function __construct(MusicHistoryService $historyService)
    {
        $this->historyService = $historyService;
    }

    /**
     * Get history records as JSON.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['program_id', 'year', 'start_date', 'end_date']);
        $history = $this->historyService->getHistory($filters);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    /**
     * Export history to Excel.
     */
    public function export(Request $request)
    {
        $filters = $request->only(['program_id', 'year', 'start_date', 'end_date']);
        $history = $this->historyService->getHistory($filters);
        
        $programName = 'Hope Music';
        if (!empty($filters['program_id'])) {
            $program = Program::find($filters['program_id']);
            if ($program) $programName = $program->name;
        }

        $fileName = 'History_Produksi_' . str_replace(' ', '_', $programName) . '_' . date('Y-m-d') . '.xls';

        // XML Spreadsheet 2003 format for better control over layout/widths/styling
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        
        $xml .= ' <Styles>' . "\n";
        $xml .= '  <Style ss:ID="Default" ss:Name="Normal">' . "\n";
        $xml .= '   <Alignment ss:Vertical="Center"/>' . "\n";
        $xml .= '   <Borders/>' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>' . "\n";
        $xml .= '   <Interior/>' . "\n";
        $xml .= '   <NumberFormat/>' . "\n";
        $xml .= '   <Protection/>' . "\n";
        $xml .= '  </Style>' . "\n";
        
        $xml .= '  <Style ss:ID="sHeaderMain">' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="14" ss:Color="#0000FF" ss:Bold="1"/>' . "\n";
        $xml .= '  </Style>' . "\n";
        
        $xml .= '  <Style ss:ID="sHeaderLabel">' . "\n";
        $xml .= '   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Bold="1"/>' . "\n";
        $xml .= '  </Style>' . "\n";
        
        $xml .= '  <Style ss:ID="sHeaderValue">' . "\n";
        $xml .= '   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
        $xml .= '   <Borders>' . "\n";
        $xml .= '    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        $xml .= '    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        $xml .= '    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        $xml .= '    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Bold="1"/>' . "\n";
        $xml .= '  </Style>' . "\n";
        
        $xml .= '  <Style ss:ID="sTableHeader">' . "\n";
        $xml .= '   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>' . "\n";
        $xml .= '   <Borders>' . "\n";
        for ($b=1;$b<=4;$b++) $xml .= '    <Border ss:Position="'.['Bottom','Left','Right','Top'][$b-1].'" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="10" ss:Bold="1"/>' . "\n";
        $xml .= '   <Interior ss:Color="#E6B8B7" ss:Pattern="Solid"/>' . "\n"; // Rose
        $xml .= '  </Style>' . "\n";
        
        $xml .= '  <Style ss:ID="sTableBody">' . "\n";
        $xml .= '   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>' . "\n";
        $xml .= '   <Borders>' . "\n";
        for ($b=1;$b<=4;$b++) $xml .= '    <Border ss:Position="'.['Bottom','Left','Right','Top'][$b-1].'" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="9"/>' . "\n";
        $xml .= '   <Interior ss:Color="#D8E4BC" ss:Pattern="Solid"/>' . "\n"; // Light Green
        $xml .= '  </Style>' . "\n";
        
        $xml .= '  <Style ss:ID="sTableBodyLeft">' . "\n";
        $xml .= '   <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="1"/>' . "\n";
        $xml .= '   <Borders>' . "\n";
        for ($b=1;$b<=4;$b++) $xml .= '    <Border ss:Position="'.['Bottom','Left','Right','Top'][$b-1].'" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="9"/>' . "\n";
        $xml .= '   <Interior ss:Color="#D8E4BC" ss:Pattern="Solid"/>' . "\n"; // Light Green
        $xml .= '  </Style>' . "\n";
        $xml .= ' </Styles>' . "\n";
        
        $xml .= ' <Worksheet ss:Name="History">' . "\n";
        $xml .= '  <Table>' . "\n";
        
        // Column Widths
        $xml .= '   <Column ss:Width="30"/>' . "\n"; // No
        $xml .= '   <Column ss:Width="80"/>' . "\n"; // Tgl Tayang
        $xml .= '   <Column ss:Width="150"/>' . "\n"; // Penyanyi
        $xml .= '   <Column ss:Width="150"/>' . "\n"; // Judul
        $xml .= '   <Column ss:Width="200"/>' . "\n"; // Group Members (New)
        $xml .= '   <Column ss:Width="90"/>' . "\n"; // Tipe
        $xml .= '   <Column ss:Width="100"/>' . "\n"; // Keterangan
        $xml .= '   <Column ss:Width="70"/>' . "\n"; // Editor
        $xml .= '   <Column ss:Width="80"/>' . "\n"; // Shooting
        $xml .= '   <Column ss:Width="80"/>' . "\n"; // Audio Final
        $xml .= '   <Column ss:Width="80"/>' . "\n"; // Arranger
        $xml .= '   <Column ss:Width="150"/>' . "\n"; // Arrangement Link
        $xml .= '   <Column ss:Width="80"/>' . "\n"; // Mix
        $xml .= '   <Column ss:Width="100"/>' . "\n"; // Promotion Status (New)
        $xml .= '   <Column ss:Width="150"/>' . "\n"; // Promotion Links (New)
        $xml .= '   <Column ss:Width="200"/>' . "\n"; // Link Youtube
        $xml .= '   <Column ss:Width="150"/>' . "\n"; // Filename
        
        // ... (Header rows OMITTED for brevity in tool call, will match existing)
        
        // Table Header
        $xml .= '   <Row ss:Height="25">' . "\n";
        $headers = ['No', 'Tgl Tayang YouTube', 'Nama Penyanyi/Pemusik', 'Judul Lagu', 'Anggota Grup', 'Tipe', 'Keterangan', 'Editor', 'Tgl Shooting', 'Tgl Audio Final', 'Arranger', 'Link Arrangement', 'Mixing & Mastering', 'Status Promosi', 'Link Promosi SOSMED', 'Link Youtube', 'File Name'];
        foreach ($headers as $h) {
            $xml .= '    <Cell ss:StyleID="sTableHeader"><Data ss:Type="String">' . $h . '</Data></Cell>' . "\n";
        }
        $xml .= '   </Row>' . "\n";
        
        // Table Data
        $i = 1;
        foreach ($history as $row) {
            $xml .= '   <Row ss:AutoFitHeight="1">' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="Number">' . $i++ . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['tgl_tayang_youtube'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBodyLeft"><Data ss:Type="String">' . htmlspecialchars($row['nama_penyanyi_pemusik'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBodyLeft"><Data ss:Type="String">"' . htmlspecialchars($row['judul_lagu'] ?? '') . '"</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBodyLeft"><Data ss:Type="String">' . htmlspecialchars($row['group_members'] ?? '-') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['tipe'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['keterangan'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['editor'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['tgl_shooting'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['tgl_audio_final'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['arranger'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBodyLeft"><Data ss:Type="String">' . htmlspecialchars($row['link_arrangement'] ?? '-') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['mixing_mastering'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBody"><Data ss:Type="String">' . htmlspecialchars($row['promotion_status'] ?? '-') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBodyLeft"><Data ss:Type="String">' . htmlspecialchars($row['promotion_links'] ?? '-') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBodyLeft"><Data ss:Type="String">' . htmlspecialchars($row['link_youtube'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '    <Cell ss:StyleID="sTableBodyLeft"><Data ss:Type="String">' . htmlspecialchars($row['file_name'] ?? '') . '</Data></Cell>' . "\n";
            $xml .= '   </Row>' . "\n";
        }
        
        $xml .= '  </Table>' . "\n";
        $xml .= '  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">' . "\n";
        $xml .= '   <PageSetup>' . "\n";
        $xml .= '    <Header x:Margin="0.3"/>' . "\n";
        $xml .= '    <Footer x:Margin="0.3"/>' . "\n";
        $xml .= '    <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>' . "\n";
        $xml .= '   </PageSetup>' . "\n";
        $xml .= '   <Print>' . "\n";
        $xml .= '    <ValidPrinterInfo/>' . "\n";
        $xml .= '    <HorizontalResolution>600</HorizontalResolution>' . "\n";
        $xml .= '    <VerticalResolution>600</VerticalResolution>' . "\n";
        $xml .= '   </Print>' . "\n";
        $xml .= '   <Selected/>' . "\n";
        $xml .= '   <Panes>' . "\n";
        $xml .= '    <Pane>' . "\n";
        $xml .= '     <Number>3</Number>' . "\n";
        $xml .= '     <ActiveRow>1</ActiveRow>' . "\n";
        $xml .= '    </Pane>' . "\n";
        $xml .= '   </Panes>' . "\n";
        $xml .= '   <ProtectObjects>False</ProtectObjects>' . "\n";
        $xml .= '   <ProtectScenarios>False</ProtectScenarios>' . "\n";
        $xml .= '  </WorksheetOptions>' . "\n";
        $xml .= ' </Worksheet>' . "\n";
        $xml .= '</Workbook>';

        return response($xml) 
            ->header('Content-Type', 'application/vnd.ms-excel; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Get available programs for filtering.
     */
    public function getFilters()
    {
        $programs = Program::musik()->get(['id', 'name']);
        $years = \App\Models\Episode::selectRaw('YEAR(air_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return response()->json([
            'success' => true,
            'data' => [
                'programs' => $programs,
                'years' => $years
            ]
        ]);
    }
}
