<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Program;
use App\Models\Episode;
use App\Models\EditorWork;
use App\Models\CreativeWork;
use Illuminate\Support\Facades\Hash;

class TaskReassignmentTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Start seeding for Task Reassignment Test...');

        // 1. Create/Get Test Users with CORRECT "String" Roles
        
        // Program Manager (Reassigner)
        $pm = User::updateOrCreate(
            ['email' => 'test_pm_reassign@test.com'],
            [
                'name' => 'Test Program Manager',
                'role' => 'Program Manager', // Correct string from DB
                'phone' => '081234567890',  // Added required phone field
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        );
        $this->command->info("PM User: {$pm->email} (ID: {$pm->id})");

        // Producer (Backup Reassigner)
        $producer = User::updateOrCreate(
            ['email' => 'test_producer_reassign@test.com'],
            [
                'name' => 'Test Producer',
                'role' => 'Producer', // Correct string
                'phone' => '081234567891', // Added required phone field
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        );
        $this->command->info("Producer User: {$producer->email} (ID: {$producer->id})");

        // Employee 1 (Original Editor)
        $editor = User::updateOrCreate(
            ['email' => 'test_editor_reassign@test.com'],
            [
                'name' => 'Test Employee Editor',
                'role' => 'Employee', // Correct string
                'phone' => '081234567892', // Added required phone field
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        );
        $this->command->info("Editor User: {$editor->email} (ID: {$editor->id})");

        // Employee 2 (New Target User)
        $newEditor = User::updateOrCreate(
            ['email' => 'test_new_target@test.com'],
            [
                'name' => 'Test New Target',
                'role' => 'Employee',
                'phone' => '081234567893', // Added required phone field
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        );
        $this->command->info("New Target User: {$newEditor->email} (ID: {$newEditor->id})");
        
        // 2. Create Test Program
        $program = Program::firstOrCreate(
            ['name' => 'Test Program Reassignment'],
            [
                'description' => 'Program for testing reassignment',
                'category' => 'Musik',
                'status' => 'draft',
                'manager_program_id' => $pm->id,
                'start_date' => now(),
                'air_time' => '10:00:00'
            ]
        );
        $this->command->info("Program: {$program->name} (ID: {$program->id})");

        // 3. Create Test Episode
        $episode = Episode::firstOrCreate(
            ['title' => 'Test Episode Reassignment', 'program_id' => $program->id],
            [
                'episode_number' => 1,
                // 'status' => 'production',
                'description' => 'Test episode'
            ]
        );

        // 4. Create Task (Editor Work) assigned to Editor (created_by)
        // Ensure we create a fresh one or update existing to specific state
        $task = EditorWork::updateOrCreate(
            [
                'episode_id' => $episode->id,
                'work_type' => 'main_episode'
            ],
            [
                'status' => 'pending',
                'created_by' => $editor->id, // Assign to editor
                'file_complete' => false,
                'was_reassigned' => false
            ]
        );
        
        $this->command->info("Task Created/Updated: EditorWork ID {$task->id} assigned to {$editor->name}");
        
        $this->command->info('------------------------------------------------');
        $this->command->info('CREDENTIALS FOR TESTING:');
        $this->command->info('PM Login: test_pm_reassign@test.com / password');
        $this->command->info('Target User ID for Reassignment: ' . $newEditor->id);
        $this->command->info('------------------------------------------------');
    }
}
