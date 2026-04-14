<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductionEquipmentExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths
{
    protected $collection;
    protected $userNames = [];

    public function __construct($collection)
    {
        $this->collection = $collection;
        $this->loadUserNames();
    }

    public function columnWidths(): array
    {
        return [
            'D' => 45, // Daftar Alat
            'G' => 45, // Anggota Tim
            'L' => 30, // Catatan
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->collection->count() + 1;
        $range = 'A1:L' . $lastRow;

        // Header style
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE0E0E0'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Wrap text for columns D and G
        $sheet->getStyle('D')->getAlignment()->setWrapText(true);
        $sheet->getStyle('G')->getAlignment()->setWrapText(true);
        $sheet->getStyle('L')->getAlignment()->setWrapText(true);

        // Center alignment for some columns
        $sheet->getStyle('A')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Border for all cells
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ]);

        return [];
    }

    protected function loadUserNames()
    {
        // Extract all unique crew member IDs from the collection
        $userIds = $this->collection->pluck('crew_member_ids')
            ->flatten()
            ->unique()
            ->filter()
            ->toArray();

        if (!empty($userIds)) {
            $this->userNames = \App\Models\User::whereIn('id', $userIds)
                ->pluck('name', 'id')
                ->toArray();
        }
    }

    public function collection()
    {
        return $this->collection;
    }

    public function headings(): array
    {
        return [
            'No',
            'Program',
            'Episode',
            'Daftar Alat',
            'Peminjam (Staff)',
            'Coordinator (Crew)',
            'Anggota Tim',
            'Tanggal Pinjam',
            'Status',
            'Tanggal Kembali',
            'Dikembalikan Oleh',
            'Catatan',
        ];
    }

    /**
     * @param \App\Models\ProductionEquipment $row
     * @return array
     */
    public function map($row): array
    {
        $programName = $row->program?->name ?? $row->episode?->program?->name ?? '—';
        $episodeNumber = $row->episode?->episode_number ?? '—';
        $equipmentList = is_array($row->equipment_list)
            ? implode(', ', $row->equipment_list)
            : (string) $row->equipment_list;
        
        $requesterName = $row->requester?->name ?? '—';
        $crewLeaderName = $row->crewLeader?->name ?? '—';
        $returnedByName = $row->returnedByUser?->name ?? '—';

        // Resolve Crew Member names
        $memberNames = '—';
        if (is_array($row->crew_member_ids) && !empty($row->crew_member_ids)) {
            $names = [];
            foreach ($row->crew_member_ids as $id) {
                if (isset($this->userNames[$id])) {
                    $names[] = $this->userNames[$id];
                }
            }
            if (!empty($names)) {
                $memberNames = implode(', ', $names);
            }
        }

        return [
            $row->id,
            $programName,
            $episodeNumber,
            $equipmentList,
            $requesterName,
            $crewLeaderName,
            $memberNames,
            $row->requested_at ? $row->requested_at->format('d/m/Y H:i') : '—',
            ucwords($row->status ?? '—'),
            $row->returned_at ? $row->returned_at->format('d/m/Y H:i') : '—',
            $returnedByName,
            $row->request_notes ?? '',
        ];
    }
}
