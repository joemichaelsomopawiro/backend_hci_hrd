<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PersonalProfileController extends Controller
{
    /**
     * GET /api/personal/profile
     * Get personal employee profile data
     */
    public function show(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|integer|exists:employees,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $employee = Employee::with([
                'documents',
                'employmentHistories',
                'promotionHistories', 
                'trainings',
                'benefits',
                'user',
                'leaveQuotas' => function($query) {
                    $query->where('year', date('Y'));
                },
                'leaveRequests' => function($query) {
                    $query->orderBy('created_at', 'desc')->limit(5);
                }
            ])->find($request->employee_id);

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Calculate additional data
            $profileData = [
                'basic_info' => [
                    'id' => $employee->id,
                    'nama_lengkap' => $employee->nama_lengkap,
                    'nik' => $employee->nik,
                    'nip' => $employee->nip,
                    'tanggal_lahir' => $employee->tanggal_lahir,
                    'jenis_kelamin' => $employee->jenis_kelamin,
                    'alamat' => $employee->alamat,
                    'status_pernikahan' => $employee->status_pernikahan,
                    'jabatan_saat_ini' => $employee->jabatan_saat_ini,
                    'tanggal_mulai_kerja' => $employee->tanggal_mulai_kerja,
                    'tingkat_pendidikan' => $employee->tingkat_pendidikan,
                    'gaji_pokok' => $employee->gaji_pokok,
                    'tunjangan' => $employee->tunjangan,
                    'bonus' => $employee->bonus,
                    'nomor_bpjs_kesehatan' => $employee->nomor_bpjs_kesehatan,
                    'nomor_bpjs_ketenagakerjaan' => $employee->nomor_bpjs_ketenagakerjaan,
                    'npwp' => $employee->npwp,
                    'nomor_kontrak' => $employee->nomor_kontrak,
                    'tanggal_kontrak_berakhir' => $employee->tanggal_kontrak_berakhir,
                    'created_from' => $employee->created_from,
                    'created_at' => $employee->created_at,
                    'updated_at' => $employee->updated_at
                ],
                'user_info' => $employee->user ? [
                    'id' => $employee->user->id,
                    'name' => $employee->user->name,
                    'email' => $employee->user->email,
                    'phone' => $employee->user->phone,
                    'role' => $employee->user->role,
                    'profile_picture' => $employee->user->profile_picture,
                    'phone_verified_at' => $employee->user->phone_verified_at,
                    'created_at' => $employee->user->created_at
                ] : null,
                'documents' => $employee->documents->map(function($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'file_path' => $doc->file_path,
                        'created_at' => $doc->created_at
                    ];
                }),
                'employment_histories' => $employee->employmentHistories->map(function($history) {
                    return [
                        'id' => $history->id,
                        'company_name' => $history->company_name,
                        'position' => $history->position,
                        'start_date' => $history->start_date,
                        'end_date' => $history->end_date,
                        'created_at' => $history->created_at
                    ];
                }),
                'promotion_histories' => $employee->promotionHistories->map(function($promotion) {
                    return [
                        'id' => $promotion->id,
                        'position' => $promotion->position,
                        'promotion_date' => $promotion->promotion_date,
                        'created_at' => $promotion->created_at
                    ];
                }),
                'trainings' => $employee->trainings->map(function($training) {
                    return [
                        'id' => $training->id,
                        'training_name' => $training->training_name,
                        'institution' => $training->institution,
                        'completion_date' => $training->completion_date,
                        'certificate_number' => $training->certificate_number,
                        'created_at' => $training->created_at
                    ];
                }),
                'benefits' => $employee->benefits->map(function($benefit) {
                    return [
                        'id' => $benefit->id,
                        'benefit_type' => $benefit->benefit_type,
                        'amount' => $benefit->amount,
                        'start_date' => $benefit->start_date,
                        'created_at' => $benefit->created_at
                    ];
                }),
                'current_leave_quota' => $employee->leaveQuotas->first() ? [
                    'id' => $employee->leaveQuotas->first()->id,
                    'year' => $employee->leaveQuotas->first()->year,
                    'annual_leave_quota' => $employee->leaveQuotas->first()->annual_leave_quota,
                    'annual_leave_used' => $employee->leaveQuotas->first()->annual_leave_used,
                    'annual_leave_remaining' => $employee->leaveQuotas->first()->annual_leave_quota - $employee->leaveQuotas->first()->annual_leave_used,
                    'sick_leave_quota' => $employee->leaveQuotas->first()->sick_leave_quota,
                    'sick_leave_used' => $employee->leaveQuotas->first()->sick_leave_used,
                    'sick_leave_remaining' => $employee->leaveQuotas->first()->sick_leave_quota - $employee->leaveQuotas->first()->sick_leave_used,
                    'emergency_leave_quota' => $employee->leaveQuotas->first()->emergency_leave_quota,
                    'emergency_leave_used' => $employee->leaveQuotas->first()->emergency_leave_used,
                    'emergency_leave_remaining' => $employee->leaveQuotas->first()->emergency_leave_quota - $employee->leaveQuotas->first()->emergency_leave_used,
                    'maternity_leave_quota' => $employee->leaveQuotas->first()->maternity_leave_quota,
                    'maternity_leave_used' => $employee->leaveQuotas->first()->maternity_leave_used,
                    'maternity_leave_remaining' => $employee->leaveQuotas->first()->maternity_leave_quota - $employee->leaveQuotas->first()->maternity_leave_used,
                    'paternity_leave_quota' => $employee->leaveQuotas->first()->paternity_leave_quota,
                    'paternity_leave_used' => $employee->leaveQuotas->first()->paternity_leave_used,
                    'paternity_leave_remaining' => $employee->leaveQuotas->first()->paternity_leave_quota - $employee->leaveQuotas->first()->paternity_leave_used,
                    'marriage_leave_quota' => $employee->leaveQuotas->first()->marriage_leave_quota,
                    'marriage_leave_used' => $employee->leaveQuotas->first()->marriage_leave_used,
                    'marriage_leave_remaining' => $employee->leaveQuotas->first()->marriage_leave_quota - $employee->leaveQuotas->first()->marriage_leave_used,
                    'bereavement_leave_quota' => $employee->leaveQuotas->first()->bereavement_leave_quota,
                    'bereavement_leave_used' => $employee->leaveQuotas->first()->bereavement_leave_used,
                    'bereavement_leave_remaining' => $employee->leaveQuotas->first()->bereavement_leave_quota - $employee->leaveQuotas->first()->bereavement_leave_used
                ] : null,
                'recent_leave_requests' => $employee->leaveRequests->map(function($request) {
                    return [
                        'id' => $request->id,
                        'leave_type' => $request->leave_type,
                        'start_date' => $request->start_date,
                        'end_date' => $request->end_date,
                        'reason' => $request->reason,
                        'overall_status' => $request->overall_status,
                        'created_at' => $request->created_at
                    ];
                }),
                'statistics' => [
                    'total_documents' => $employee->documents->count(),
                    'total_employment_histories' => $employee->employmentHistories->count(),
                    'total_promotions' => $employee->promotionHistories->count(),
                    'total_trainings' => $employee->trainings->count(),
                    'total_benefits' => $employee->benefits->count(),
                    'total_leave_requests' => $employee->leaveRequests->count(),
                    'years_of_service' => $employee->tanggal_mulai_kerja ? 
                        \Carbon\Carbon::parse($employee->tanggal_mulai_kerja)->diffInYears(now()) : 0
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Profile data retrieved successfully',
                'data' => $profileData
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting personal profile', [
                'employee_id' => $request->employee_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data profil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/personal/profile
     * Update personal profile data (basic info only)
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|integer|exists:employees,id',
                'alamat' => 'nullable|string|max:500',
                'nomor_bpjs_kesehatan' => 'nullable|string|max:20',
                'nomor_bpjs_ketenagakerjaan' => 'nullable|string|max:20',
                'npwp' => 'nullable|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $employee = Employee::find($request->employee_id);
            
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Only allow updating certain fields for security
            $updateableFields = [
                'alamat',
                'nomor_bpjs_kesehatan', 
                'nomor_bpjs_ketenagakerjaan',
                'npwp'
            ];

            $updateData = [];
            foreach ($updateableFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }

            if (!empty($updateData)) {
                $employee->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $employee->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating personal profile', [
                'employee_id' => $request->employee_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat update profil: ' . $e->getMessage()
            ], 500);
        }
    }
} 