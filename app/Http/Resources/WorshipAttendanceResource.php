<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorshipAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pegawai_id' => $this->employee_id,
            'nama_lengkap' => $this->employee->nama_lengkap ?? 'Unknown',
            'jabatan' => $this->employee->jabatan_saat_ini ?? '-',
            'status' => $this->mapStatusToFrontend($this->status),
            'status_label' => $this->getStatusLabel($this->status),
            'tanggal' => $this->date->format('Y-m-d'),
            'attendance_time' => $this->join_time ? $this->join_time->format('H:i') : '-',
            'attendance_method' => $this->attendance_method ?? 'online',
            'attendance_source' => $this->attendance_source ?? 'zoom',
            'testing_mode' => $this->testing_mode ?? false,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Map status dari database ke frontend
     */
    private function mapStatusToFrontend(string $status): string
    {
        $statusMap = [
            'Hadir' => 'present',
            'Terlambat' => 'late',
            'Absen' => 'absent'
        ];

        return $statusMap[$status] ?? 'absent';
    }

    /**
     * Get label untuk status
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'Hadir' => 'Hadir',
            'Terlambat' => 'Terlambat',
            'Absen' => 'Tidak Hadir'
        ];

        return $labels[$status] ?? $status;
    }
}
