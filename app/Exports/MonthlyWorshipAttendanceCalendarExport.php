<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Maatwebsite\Excel\Events\AfterSheet;

class MonthlyWorshipAttendanceCalendarExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, WithTitle, WithColumnWidths
{
    protected $data;
    protected $workDays; // array of 'Y-m-d'
    protected $month;
    protected $year;

    // tambahan konfigurasi
    protected $mode; // 'monthly' | 'weekly'
    protected $periodLabel; // string untuk ditampilkan di header
    protected $globalSummary; // ['employees' => int, 'work_days' => int, 'H' => int, 'T' => int, 'C' => int, 'I' => int, 'A' => int]

    public function __construct($data, $workDays, $month, $year, $mode = 'monthly', $periodLabel = '', $globalSummary = [])
    {
        $this->data = $data;
        $this->workDays = $workDays;
        $this->month = $month;
        $this->year = $year;
        $this->mode = $mode;
        $this->periodLabel = $periodLabel;
        $this->globalSummary = array_merge([
            'employees' => count($data),
            'work_days' => count($workDays),
            'H' => 0, 'T' => 0, 'C' => 0, 'I' => 0, 'A' => 0,
        ], $globalSummary);
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        return collect($this->data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $headers = ['No', 'Nama Karyawan', 'Jabatan'];
        // Generate headers untuk setiap hari kerja (format: "d\n{HariSingkat}")
        foreach ($this->workDays as $date) {
            $carbonDate = Carbon::parse($date);
            $headers[] = $carbonDate->format('j') . "\n" . $this->getDayShortName($carbonDate->dayOfWeek);
        }
        // Ringkasan per-karyawan di kanan
        $headers[] = 'H';
        $headers[] = 'T';
        $headers[] = 'C';
        $headers[] = 'I';
        $headers[] = 'A';
        return $headers;
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        $mappedRow = [
            $row['no'] ?? '',
            $row['employee_name'] ?? '',
            $row['position'] ?? '',
        ];

        $countH = 0; $countT = 0; $countC = 0; $countI = 0; $countA = 0;

        foreach ($this->workDays as $date) {
            $status = $row['attendance'][$date]['status'] ?? 'absent';
            // Normalisasi
            $status = strtolower($status);
            if ($status === 'hadir' || $status === 'present_ontime' || $status === 'present') { $status = 'present'; }
            if ($status === 'terlambat' || $status === 'present_late' ) { $status = 'late'; }
            if ($status === 'cuti' || $status === 'on_leave' || $status === 'sick_leave' || $status === 'leave') { $status = 'leave'; }
            if ($status === 'izin' || $status === 'permit') { $status = 'izin'; }
            if ($status === 'absen' || $status === 'no_data') { $status = 'absent'; }

            switch ($status) {
                case 'present':
                    $mappedRow[] = 'H';
                    $countH++;
                    break;
                case 'late':
                    $mappedRow[] = 'T';
                    $countT++;
                    break;
                case 'leave':
                    $mappedRow[] = 'C';
                    $countC++;
                    break;
                case 'izin':
                    $mappedRow[] = 'I';
                    $countI++;
                    break;
                default:
                    $mappedRow[] = 'A';
                    $countA++;
                    break;
            }
        }

        // Tambahkan ringkasan per-karyawan
        $mappedRow[] = $countH;
        $mappedRow[] = $countT;
        $mappedRow[] = $countC;
        $mappedRow[] = $countI;
        $mappedRow[] = $countA;

        return $mappedRow;
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Style dasar untuk header (baris heading setelah disisipkan header tambahan akan berada di row 4)
        return [
            4 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Sisipkan 3 baris di atas untuk judul dan ringkasan
                $sheet->insertNewRowBefore(1, 3);

                $lastColumnIndex = 3 + count($this->workDays) + 5; // No, Nama, Jabatan + hari + 5 kol ringkasan
                $lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);

                // Baris 1: Judul
                $title = $this->mode === 'weekly' ? 'Tabel Absensi Ibadah Mingguan' : 'Tabel Absensi Ibadah Bulanan';
                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->setCellValue('A1', $title);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Baris 2: Periode
                $period = $this->periodLabel ?: ($this->mode === 'weekly' ? '' : ('Bulan: ' . Carbon::create($this->year, $this->month, 1)->isoFormat('MMMM Y')));
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->setCellValue('A2', $this->mode === 'weekly' ? ("Periode: " . $period) : ("Bulan: " . $period));
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Baris 3: Ringkasan global
                $summaryText = sprintf(
                    '%d Karyawan, %d Hari Kerja  |  Hadir: %d  Terlambat: %d  Cuti: %d  Izin: %d  Absen: %d',
                    $this->globalSummary['employees'] ?? 0,
                    $this->globalSummary['work_days'] ?? 0,
                    $this->globalSummary['H'] ?? 0,
                    $this->globalSummary['T'] ?? 0,
                    $this->globalSummary['C'] ?? 0,
                    $this->globalSummary['I'] ?? 0,
                    $this->globalSummary['A'] ?? 0,
                );
                $sheet->mergeCells("A3:{$lastColumn}3");
                $sheet->setCellValue('A3', $summaryText);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Freeze panes: bekukan 4 baris (3 header + 1 heading) dan 3 kolom (A-C)
                $sheet->freezePane('D5');

                // Lebar kolom + wrap header hari
                $sheet->getStyle("A4:{$lastColumn}4")->getAlignment()->setWrapText(true);

                // Border seluruh tabel (mulai heading row 4 sampai data terakhir)
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A4:{$lastColumn}{$highestRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // Alignment: Nama kiri, yang lain tengah
                $sheet->getStyle('B5:B' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("A4:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C4:{$lastColumn}{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Pewarnaan sel untuk kolom hari berdasarkan nilai H/T/C/I/A
                $startDataRow = 5; // data mulai baris 5
                $dayStartColIndex = 4; // kolom D
                $summaryStartColIndex = $dayStartColIndex + count($this->workDays);
                for ($row = $startDataRow; $row <= $highestRow; $row++) {
                    // Kolom hari
                    for ($colIndex = $dayStartColIndex; $colIndex < $summaryStartColIndex; $colIndex++) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $cell = $colLetter . $row;
                        $value = (string) $sheet->getCell($cell)->getValue();
                        $color = null;
                        switch (strtoupper(trim($value))) {
                            case 'H': $color = '10b981'; break; // Hijau
                            case 'T': $color = 'f59e0b'; break; // Kuning
                            case 'C': $color = '3498db'; break; // Biru
                            case 'I': $color = 'ff8c00'; break; // Oranye
                            case 'A': $color = 'ef4444'; break; // Merah
                            default: $color = null; break;
                        }
                        if ($color) {
                            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
                        }
                    }
                }

                // Tambah baris TOTAL di bawah untuk kolom H/T/C/I/A
                $totalRow = $highestRow + 1;
                $sheet->setCellValue('A' . $totalRow, '');
                $sheet->setCellValue('B' . $totalRow, 'TOTAL');
                $sheet->setCellValue('C' . $totalRow, '');
                // Hitung total dengan SUM untuk tiap kolom ringkasan
                $summaryCols = ['H', 'T', 'C', 'I', 'A'];
                for ($i = 0; $i < count($summaryCols); $i++) {
                    $colIndex = $summaryStartColIndex + $i;
                    $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($colLetter . $totalRow, sprintf('=SUM(%1$s5:%1$s%2$d)', $colLetter, $highestRow));
                    $sheet->getStyle($colLetter . $totalRow)->getFont()->setBold(true);
                }
                $sheet->getStyle('B' . $totalRow)->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            }
        ];
    }

    public function title(): string
    {
        if ($this->mode === 'weekly') {
            return 'Absensi Mingguan ' . ($this->periodLabel ?: Carbon::create($this->year, $this->month, 1)->isoFormat('DD MMM YYYY'));
        }
        return 'Absensi Bulanan ' . Carbon::create($this->year, $this->month, 1)->isoFormat('MMMM YYYY');
    }

    public function columnWidths(): array
    {
        // No:6, Nama:26, Jabatan:18, Hari:6, Ringkasan:6
        $widths = [
            'A' => 6,
            'B' => 26,
            'C' => 18,
        ];
        // Hari kerja dimulai dari D
        $startIndex = 4;
        for ($i = 0; $i < count($this->workDays); $i++) {
            $letter = Coordinate::stringFromColumnIndex($startIndex + $i);
            $widths[$letter] = 6;
        }
        // Tambah ringkasan H/T/C/I/A
        $summaryStart = $startIndex + count($this->workDays);
        for ($j = 0; $j < 5; $j++) {
            $letter = Coordinate::stringFromColumnIndex($summaryStart + $j);
            $widths[$letter] = 6;
        }
        return $widths;
    }

    /**
     * Get day short name in Indonesian
     */
    private function getDayShortName($dayOfWeek)
    {
        $days = [
            0 => 'Min',
            1 => 'Sen',
            2 => 'Sel',
            3 => 'Rab',
            4 => 'Kam',
            5 => 'Jum',
            6 => 'Sab'
        ];
        return $days[$dayOfWeek] ?? 'N/A';
    }
}