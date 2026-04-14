<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryItemExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $collection;

    public function __construct($collection)
    {
        $this->collection = $collection;
    }

    public function collection()
    {
        return $this->collection;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->collection->count() + 1;
        $range = 'A1:K' . $lastRow;

        // Header style
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE0E0E0'],
            ],
        ]);

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

    public function headings(): array
    {
        return [
            'No',
            'ID Alat',
            'Nama Alat',
            'Deskripsi',
            'Kondisi',
            'Lokasi',
            'Posisi',
            'Kategori',
            'Total Qty',
            'Tersedia',
            'Status',
        ];
    }

    /**
     * @param \App\Models\InventoryItem $row
     * @return array
     */
    public function map($row): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $row->equipment_id ?? '—',
            $row->name,
            $row->description ?? '—',
            ucwords($row->condition ?? '—'),
            $row->location ?? '—',
            $row->position ?? '—',
            $row->category ?? '—',
            $row->total_quantity,
            $row->available_quantity,
            ucfirst($row->status ?? 'active'),
        ];
    }
}
