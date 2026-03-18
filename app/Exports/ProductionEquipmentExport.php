<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductionEquipmentExport implements FromCollection, WithHeadings, WithMapping
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

    public function headings(): array
    {
        return [
            'No',
            'Program',
            'Episode',
            'Daftar Alat',
            'Peminjam',
            'Crew Leader',
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

        return [
            $row->id,
            $programName,
            $episodeNumber,
            $equipmentList,
            $requesterName,
            $crewLeaderName,
            $row->requested_at ? $row->requested_at->format('d/m/Y H:i') : '—',
            $row->status ?? '—',
            $row->returned_at ? $row->returned_at->format('d/m/Y H:i') : '—',
            $returnedByName,
            $row->request_notes ?? '',
        ];
    }
}
