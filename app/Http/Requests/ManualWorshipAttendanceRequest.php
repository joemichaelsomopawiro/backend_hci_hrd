<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class ManualWorshipAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization akan dilakukan di controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tanggal' => 'required|date|after_or_equal:2020-01-01',
            'attendance_data' => 'required|array|min:1',
            'attendance_data.*.employee_id' => 'required|integer|exists:employees,id',
            'attendance_data.*.status' => 'required|in:present,late,absent,izin'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal harus diisi',
            'tanggal.date' => 'Format tanggal tidak valid',
            'tanggal.after_or_equal' => 'Tanggal harus setelah atau sama dengan 2020-01-01',
            'attendance_data.required' => 'Data absensi harus diisi',
            'attendance_data.array' => 'Data absensi harus berupa array',
            'attendance_data.min' => 'Minimal harus ada 1 data absensi',
            'attendance_data.*.employee_id.required' => 'ID pegawai harus diisi',
            'attendance_data.*.employee_id.integer' => 'ID pegawai harus berupa angka',
            'attendance_data.*.employee_id.exists' => 'Pegawai tidak ditemukan',
            'attendance_data.*.status.required' => 'Status absensi harus diisi',
            'attendance_data.*.status.in' => 'Status harus present, late, absent, atau izin'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        // Validasi hari sudah dihapus - input manual sekarang bisa untuk semua hari
        // $validator->after(function ($validator) {
        //     $this->validateWorshipDay($validator);
        // });
    }

    /**
     * Validasi bahwa tanggal adalah hari Selasa atau Kamis
     * NOTE: Validasi ini sudah dihapus untuk memungkinkan input manual di semua hari
     */
    private function validateWorshipDay($validator)
    {
        // Validasi hari sudah dihapus - input manual sekarang bisa untuk semua hari
        // $date = $this->input('tanggal');
        
        // if ($date) {
        //     try {
        //         $carbonDate = Carbon::parse($date);
        //         $dayOfWeek = $carbonDate->dayOfWeek; // 2 = Selasa, 4 = Kamis

        //         if (!in_array($dayOfWeek, [2, 4])) {
        //             $validator->errors()->add('tanggal', 'Input manual hanya diperbolehkan untuk hari Selasa dan Kamis');
        //         }
        //     } catch (\Exception $e) {
        //             $validator->errors()->add('tanggal', 'Format tanggal tidak valid');
        //         }
        //     }
        // }
    }
}
