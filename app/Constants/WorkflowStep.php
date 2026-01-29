<?php

namespace App\Constants;

class WorkflowStep
{
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';

    // Workflow step definitions
    const STEPS = [
        1 => [
            'name' => 'Membuat Program Reguler',
            'role' => 'Manager Program',
            'description' => 'Manager Program membuat dan mengkonfigurasi program reguler'
        ],
        2 => [
            'name' => 'Menerima Program',
            'role' => 'Producer',
            'description' => 'Producer menerima dan acknowledge program yang dibuat'
        ],
        3 => [
            'name' => 'Menulis Script & Mengatur Syuting',
            'role' => 'Kreatif',
            'description' => 'Tim Kreatif menulis script dan mengatur jadwal syuting'
        ],
        4 => [
            'name' => 'Accept Script & Budget',
            'role' => 'Producer,Manager Program',
            'description' => 'Producer dan Manager Program approve script dan budget'
        ],
        5 => [
            'name' => 'Pinjam Alat dan Syuting',
            'role' => 'Produksi,Promosi',
            'description' => 'Tim Produksi dan Promosi meminjam alat dan melakukan syuting'
        ],
        6 => [
            'name' => 'Edit Konten',
            'role' => 'Editor,Editor Promosi,Design Grafis',
            'description' => 'Tim Editor melakukan editing konten video'
        ],
        7 => [
            'name' => 'Quality Check',
            'role' => 'Manager Distribusi',
            'description' => 'Manager Distribusi melakukan quality check awal'
        ],
        8 => [
            'name' => 'Quality Check Final',
            'role' => 'QC',
            'description' => 'Quality Control melakukan final check semua aspek'
        ],
        9 => [
            'name' => 'Upload',
            'role' => 'Broadcasting',
            'description' => 'Tim Broadcasting upload konten ke platform'
        ],
        10 => [
            'name' => 'Share Konten',
            'role' => 'Promosi',
            'description' => 'Tim Promosi share dan promosikan konten'
        ]
    ];

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
