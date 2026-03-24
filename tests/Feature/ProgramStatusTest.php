<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PrProgram;
use App\Constants\Role;
use Illuminate\Support\Facades\Auth;

class ProgramStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Find or create a manager user
        $this->user = User::where('role', 'program_manager')->first();
        if (!$this->user) {
            $this->user = User::factory()->create(['role' => 'program_manager']);
        }
    }

    public function test_program_manager_can_deactivate_program()
    {
        // 1. Create a program
        $program = PrProgram::create([
            'name' => 'Deactivate Test Program ' . rand(1, 1000),
            'manager_program_id' => $this->user->id,
            'status' => 'draft',
            'start_date' => now(),
            'air_time' => '20:00',
            'program_year' => date('Y')
        ]);

        // 2. Deactivate via API
        $response = $this->actingAs($this->user)
            ->postJson("/api/program-regular/manager-program/programs/{$program->id}/deactivate");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // 3. Verify status in DB
        $program->refresh();
        $this->assertEquals('cancelled', $program->status);

        // 4. Cleanup
        $program->forceDelete();
    }

    public function test_program_history_includes_deactivated_and_archived()
    {
        // 1. Create two programs
        $p1 = PrProgram::create([
            'name' => 'History Inactive ' . rand(1, 1000),
            'manager_program_id' => $this->user->id,
            'status' => 'cancelled', // Deactivated
            'start_date' => now(),
            'air_time' => '20:00',
            'program_year' => date('Y')
        ]);

        $p2 = PrProgram::create([
            'name' => 'History Archived ' . rand(1, 1000),
            'manager_program_id' => $this->user->id,
            'status' => 'draft',
            'start_date' => now(),
            'air_time' => '20:00',
            'program_year' => date('Y')
        ]);
        $p2->delete(); // Soft delete / Archived

        // 2. Check History API
        $response = $this->actingAs($this->user)
            ->getJson("/api/program-regular/manager-program/history");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        
        // Find them in history
        $inactive = collect($data)->firstWhere('id', $p1->id);
        $archived = collect($data)->firstWhere('id', $p2->id);

        $this->assertNotNull($inactive, 'Deactivated program should be in history');
        $this->assertFalse($inactive['is_deleted'], 'Deactivated program should have is_deleted=false');
        
        $this->assertNotNull($archived, 'Archived program should be in history');
        $this->assertTrue($archived['is_deleted'], 'Archived program should have is_deleted=true');

        // 3. Cleanup
        $p1->forceDelete();
        $p2->forceDelete();
    }

    public function test_program_manager_can_reactivate_both_types()
    {
        // 1. Cancelled Program
        $p1 = PrProgram::create([
            'name' => 'Reactivate Inactive ' . rand(1, 1000),
            'manager_program_id' => $this->user->id,
            'status' => 'cancelled',
            'start_date' => now(),
            'air_time' => '20:00',
            'program_year' => date('Y')
        ]);

        // 2. Archived Program
        $p2 = PrProgram::create([
            'name' => 'Reactivate Archived ' . rand(1, 1000),
            'manager_program_id' => $this->user->id,
            'status' => 'draft',
            'start_date' => now(),
            'air_time' => '20:00',
            'program_year' => date('Y')
        ]);
        $p2->delete();

        // 3. Reactivate Inactive
        $this->actingAs($this->user)
            ->postJson("/api/program-regular/manager-program/programs/{$p1->id}/reactivate")
            ->assertStatus(200);
        
        $p1->refresh();
        $this->assertEquals('draft', $p1->status);

        // 4. Reactivate Archived
        $this->actingAs($this->user)
            ->postJson("/api/program-regular/manager-program/programs/{$p2->id}/reactivate")
            ->assertStatus(200);
        
        $this->assertNull(PrProgram::onlyTrashed()->find($p2->id), 'Program should no longer be trashed');
        $this->assertNotNull(PrProgram::find($p2->id), 'Program should be restored');

        // 5. Cleanup
        $p1->forceDelete();
        $p2->forceDelete();
    }
}
