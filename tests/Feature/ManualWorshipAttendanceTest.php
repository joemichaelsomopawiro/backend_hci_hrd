<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\MorningReflectionAttendance;
use Carbon\Carbon;

class ManualWorshipAttendanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $gaUser;
    protected $employees;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat user GA
        $this->gaUser = User::factory()->create([
            'role' => 'General Affairs'
        ]);

        // Buat beberapa employee
        $this->employees = Employee::factory()->count(3)->create();
    }

    /** @test */
    public function ga_can_store_manual_attendance_for_tuesday()
    {
        // Set tanggal ke hari Selasa
        $tuesday = Carbon::now()->next(Carbon::TUESDAY)->format('Y-m-d');

        $attendanceData = [
            [
                'pegawai_id' => $this->employees[0]->id,
                'status' => 'present'
            ],
            [
                'pegawai_id' => $this->employees[1]->id,
                'status' => 'late'
            ],
            [
                'pegawai_id' => $this->employees[2]->id,
                'status' => 'absent'
            ]
        ];

        $response = $this->actingAs($this->gaUser)
            ->postJson('/api/ga-dashboard/manual-worship-attendance', [
                'tanggal' => $tuesday,
                'attendance_data' => $attendanceData
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data absensi manual berhasil disimpan'
            ]);

        // Cek data tersimpan di database
        $this->assertDatabaseHas('morning_reflection_attendance', [
            'employee_id' => $this->employees[0]->id,
            'date' => $tuesday,
            'status' => 'Hadir',
            'attendance_method' => 'manual',
            'attendance_source' => 'manual_input'
        ]);
    }

    /** @test */
    public function ga_can_store_manual_attendance_for_thursday()
    {
        // Set tanggal ke hari Kamis
        $thursday = Carbon::now()->next(Carbon::THURSDAY)->format('Y-m-d');

        $attendanceData = [
            [
                'pegawai_id' => $this->employees[0]->id,
                'status' => 'present'
            ]
        ];

        $response = $this->actingAs($this->gaUser)
            ->postJson('/api/ga-dashboard/manual-worship-attendance', [
                'tanggal' => $thursday,
                'attendance_data' => $attendanceData
            ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function cannot_store_manual_attendance_for_monday()
    {
        // Set tanggal ke hari Senin
        $monday = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

        $attendanceData = [
            [
                'pegawai_id' => $this->employees[0]->id,
                'status' => 'present'
            ]
        ];

        $response = $this->actingAs($this->gaUser)
            ->postJson('/api/ga-dashboard/manual-worship-attendance', [
                'tanggal' => $monday,
                'attendance_data' => $attendanceData
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tanggal']);
    }

    /** @test */
    public function non_ga_user_cannot_access_manual_attendance()
    {
        $regularUser = User::factory()->create([
            'role' => 'Staff'
        ]);

        $tuesday = Carbon::now()->next(Carbon::TUESDAY)->format('Y-m-d');

        $response = $this->actingAs($regularUser)
            ->postJson('/api/ga-dashboard/manual-worship-attendance', [
                'tanggal' => $tuesday,
                'attendance_data' => []
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function can_get_employees_for_manual_input()
    {
        $response = $this->actingAs($this->gaUser)
            ->getJson('/api/ga-dashboard/employees-for-manual-input');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function can_update_existing_data()
    {
        // Buat data existing tanpa attendance_method dan attendance_source
        MorningReflectionAttendance::create([
            'employee_id' => $this->employees[0]->id,
            'date' => Carbon::today(),
            'status' => 'Hadir',
            'join_time' => now(),
            'testing_mode' => false
        ]);

        $response = $this->actingAs($this->gaUser)
            ->postJson('/api/ga-dashboard/update-existing-worship-data');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Data existing berhasil diupdate'
            ]);

        // Cek data terupdate
        $this->assertDatabaseHas('morning_reflection_attendance', [
            'employee_id' => $this->employees[0]->id,
            'attendance_method' => 'online',
            'attendance_source' => 'zoom'
        ]);
    }

    /** @test */
    public function validation_requires_valid_employee_id()
    {
        $tuesday = Carbon::now()->next(Carbon::TUESDAY)->format('Y-m-d');

        $attendanceData = [
            [
                'pegawai_id' => 99999, // ID tidak ada
                'status' => 'present'
            ]
        ];

        $response = $this->actingAs($this->gaUser)
            ->postJson('/api/ga-dashboard/manual-worship-attendance', [
                'tanggal' => $tuesday,
                'attendance_data' => $attendanceData
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendance_data.0.pegawai_id']);
    }

    /** @test */
    public function validation_requires_valid_status()
    {
        $tuesday = Carbon::now()->next(Carbon::TUESDAY)->format('Y-m-d');

        $attendanceData = [
            [
                'pegawai_id' => $this->employees[0]->id,
                'status' => 'invalid_status'
            ]
        ];

        $response = $this->actingAs($this->gaUser)
            ->postJson('/api/ga-dashboard/manual-worship-attendance', [
                'tanggal' => $tuesday,
                'attendance_data' => $attendanceData
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendance_data.0.status']);
    }
}
