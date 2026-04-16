<?php

namespace App\Constants;

class WorkflowStep
{
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';

    // Workflow step definitions with deadline_days_before (days before air_date)
    // Based on official KPI spreadsheet:
    // Producer: 10, Kreatif: 10, Setting/Produksi: 8, Editor: 7,
    // Editor Promosi: 6, Design Grafis: 5, Manager Distribusi QC: 6,
    // QC: 6, Broadcasting: 4, Promosi: 0 (hari tayang)
    const STEPS = [
        1 => [
            'name' => 'Membuat Program Reguler',
            'role' => 'Manager Program',
            'description' => 'Manager Program membuat dan mengkonfigurasi program reguler',
            'deadline_days_before' => 10,
        ],
        2 => [
            'name' => 'Menerima Program',
            'role' => 'Producer',
            'description' => 'Producer menerima dan acknowledge program yang dibuat',
            'deadline_days_before' => 10,
        ],
        3 => [
            'name' => 'Menulis Script & Mengatur Syuting',
            'role' => 'Kreatif',
            'description' => 'Tim Kreatif menulis script dan mengatur jadwal syuting',
            'deadline_days_before' => 10,
        ],
        4 => [
            'name' => 'Accept Script & Budget',
            'role' => 'Producer,Manager Program',
            'description' => 'Producer dan Manager Program approve script dan budget',
            'deadline_days_before' => 10,
        ],
        5 => [
            'name' => 'Pinjam Alat dan Syuting',
            'role' => 'Produksi,Promosi',
            'description' => 'Tim Produksi dan Promosi meminjam alat dan melakukan syuting',
            'deadline_days_before' => 8,
        ],
        6 => [
            'name' => 'Edit Konten',
            'role' => 'Editor,Editor Promosi,Design Grafis',
            'description' => 'Tim Editor melakukan editing konten video',
            'deadline_days_before' => 7, // Editor=7, Editor Promosi=6, Design Grafis=5 (handled separately in work models)
        ],
        7 => [
            'name' => 'Quality Check',
            'role' => 'Manager Distribusi',
            'description' => 'Manager Distribusi melakukan quality check awal',
            'deadline_days_before' => 6,
        ],
        8 => [
            'name' => 'Quality Check Final',
            'role' => 'QC',
            'description' => 'Quality Control melakukan final check semua aspek',
            'deadline_days_before' => 6,
        ],
        9 => [
            'name' => 'Upload',
            'role' => 'Broadcasting',
            'description' => 'Tim Broadcasting upload konten ke platform',
            'deadline_days_before' => 4,
        ],
        10 => [
            'name' => 'Share Konten',
            'role' => 'Promosi',
            'description' => 'Tim Promosi share dan promosikan konten',
            'deadline_days_before' => 0, // Hari tayang
        ]
    ];

    /**
     * Role-specific deadline days for individual work records.
     * Used for generating deadlines on per-role work tables.
     */
    const ROLE_DEADLINE_DAYS = [
        // Program Regular roles
        'producer' => 10,
        'kreatif' => 10,
        'produksi' => 8,
        'art_set_design' => 8, // Sesuai jadwal syuting (same as produksi)
        'editor' => 7,
        'editor_promosi' => 6,
        'design_grafis' => 5,
        'manager_distribusi' => 6,
        'quality_control' => 6,
        'broadcasting' => 4,
        'promotion' => 0,
        // Default music values (will be handled by MUSIC_ROLE_DEADLINE_DAYS where possible)
        'musik_arr' => 8,
        'sound_eng' => 8,
    ];

    /**
     * Role-specific deadline days for Music Programs.
     */
    const MUSIC_ROLE_DEADLINE_DAYS = [
        'producer' => 10,
        'kreatif' => 10,
        'producer_creative' => 10,
        'musik_arr' => 11,
        'musik_arr_song' => 15,
        'producer_acc_song' => 15,
        'musik_arr_lagu' => 11,
        'producer_acc_lagu' => 11,
        'sound_eng' => 8,
        'tim_setting_coord' => 8,
        'tim_syuting_coord' => 8,
        'tim_vocal_coord' => 10,
        'art_set_design' => 0,
        'art_set_design_return' => 0,
        'editor' => 7,
        'quality_control' => 6,
        'editor_promosi' => 6,
        'design_grafis' => 5,
        'broadcasting' => 4,
        'promotion' => 0,
        'manager_distribusi' => 6,
        'general_affairs' => 8,
        'promosi_syuting' => 8,
        'program_manager' => 10,
    ];

    /**
     * Get deadline days before air_date for a specific role
     */
    public static function getDeadlineDaysForRole(string $role, string $category = 'regular'): int
    {
        if (strtolower($category) === 'musik') {
            return self::MUSIC_ROLE_DEADLINE_DAYS[$role] ?? (self::ROLE_DEADLINE_DAYS[$role] ?? 7);
        }
        
        return self::ROLE_DEADLINE_DAYS[$role] ?? 7; // Default 7 if role not found
    }


    /**
     * Get step information by step number
     */
    public static function getStepInfo(int $stepNumber): ?array
    {
        return self::STEPS[$stepNumber] ?? null;
    }

    /**
     * Get all workflow steps
     */
    public static function getAllSteps(): array
    {
        return self::STEPS;
    }

    /**
     * Check if step number is valid
     */
    public static function isValidStep(int $stepNumber): bool
    {
        return isset(self::STEPS[$stepNumber]);
    }

    /**
     * Get roles for a specific step
     */
    public static function getRolesForStep(int $stepNumber): array
    {
        $stepInfo = self::getStepInfo($stepNumber);
        if (!$stepInfo) {
            return [];
        }

        return array_map('trim', explode(',', $stepInfo['role']));
    }

    /**
     * Check if user role can access a step
     */
    public static function canRoleAccessStep(string $userRole, int $stepNumber): bool
    {
        $normalizedUserRole = Role::normalize($userRole);
        $stepRoles = self::getRolesForStep($stepNumber);

        // Normalize step roles
        $normalizedStepRoles = array_map([Role::class, 'normalize'], $stepRoles);

        return in_array($normalizedUserRole, $normalizedStepRoles);
    }

    /**
     * Get total number of steps
     */
    public static function getTotalSteps(): int
    {
        return count(self::STEPS);
    }

    /**
     * Get color code for status (for frontend visualization)
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_IN_PROGRESS => 'orange',
            self::STATUS_PENDING => 'gray',
            default => 'gray'
        };
    }

}
