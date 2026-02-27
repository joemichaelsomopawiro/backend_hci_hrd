<?php

namespace App\Constants;

/**
 * Role Constants
 * 
 * Class ini berisi semua role names yang valid di sistem Hope Channel Indonesia.
 * GUNAKAN CONSTANTS INI untuk menghindari typo dan memastikan konsistensi.
 * 
 * JANGAN hard-code role names di controller/service!
 * 
 * Catatan: 
 * - Role ini adalah role standar yang selalu ada di sistem
 * - Untuk custom roles yang ditambahkan melalui sistem, gunakan DatabaseEnumService::getAllAvailableRoles()
 * - Method normalize() akan handle typo dan variasi penulisan (misalnya 'prmotion' -> 'Promotion')
 * 
 * Contoh penggunaan:
 * - Role::PRODUCER (bukan 'Producer' atau 'prodcer')
 * - Role::PROGRAM_MANAGER (bukan 'Manager Program' atau 'Program Manager')
 * - Role::isManager($user->role)
 * - Role::normalize('prmotion') // Returns 'Promotion' (handle typo)
 */
class Role
{
    // ============================================
    // HR & MANAGEMENT ROLES
    // ============================================
    public const HR = 'HR';
    public const GENERAL_AFFAIRS = 'General Affairs';
    public const FINANCE = 'Finance';
    public const OFFICE_ASSISTANT = 'Office Assistant';
    public const PROGRAM_MANAGER = 'Program Manager';
    public const DISTRIBUTION_MANAGER = 'Distribution Manager';
    public const VP_PRESIDENT = 'VP President';
    public const PRESIDENT_DIRECTOR = 'President Director';

    // ============================================
    // PRODUCTION ROLES
    // ============================================
    public const PRODUCER = 'Producer';
    public const CREATIVE = 'Creative';
    public const PRODUCTION = 'Production';
    public const EDITOR = 'Editor';

    // ============================================
    // DISTRIBUTION & MARKETING ROLES
    // ============================================
    public const SOCIAL_MEDIA = 'Social Media';
    public const PROMOTION = 'Promotion';
    public const GRAPHIC_DESIGN = 'Graphic Design';
    public const HOPELINE_CARE = 'Hopeline Care';
    public const BROADCASTING = 'Broadcasting';

    // ============================================
    // MUSIC PROGRAM ROLES
    // ============================================
    public const MUSIC_ARRANGER = 'Music Arranger';
    public const SOUND_ENGINEER = 'Sound Engineer';
    public const QUALITY_CONTROL = 'Quality Control';
    public const ART_SET_PROPERTI = 'Art & Set Properti';
    public const EDITOR_PROMOTION = 'Editor Promotion';

    // ============================================
    // DEFAULT ROLE
    // ============================================
    public const EMPLOYEE = 'Employee';

    // ============================================
    // ROLE GROUPS (untuk permission checking)
    // ============================================

    /**
     * Semua manager roles yang bisa approve program
     */
    public static function getManagerRoles(): array
    {
        return [
            self::PROGRAM_MANAGER,
            self::DISTRIBUTION_MANAGER,
            // Note: 'Manager' tidak ada di standard roles, gunakan PROGRAM_MANAGER
        ];
    }

    /**
     * Semua roles yang bisa approve rundown
     */
    public static function getRundownApproverRoles(): array
    {
        return [
            self::PRODUCER,
            self::PROGRAM_MANAGER,
            self::DISTRIBUTION_MANAGER,
        ];
    }

    /**
     * Semua roles yang bisa approve schedule
     */
    public static function getScheduleApproverRoles(): array
    {
        return [
            self::PROGRAM_MANAGER,
            self::DISTRIBUTION_MANAGER,
        ];
    }

    /**
     * Semua production team roles
     */
    public static function getProductionTeamRoles(): array
    {
        return [
            self::CREATIVE,
            self::MUSIC_ARRANGER,
            self::SOUND_ENGINEER,
            self::PRODUCTION,
            self::EDITOR,
            self::ART_SET_PROPERTI,
        ];
    }

    /**
     * Semua distribution team roles
     */
    public static function getDistributionTeamRoles(): array
    {
        return [
            self::BROADCASTING,
            self::PROMOTION,
            self::GRAPHIC_DESIGN,
            self::SOCIAL_MEDIA,
            self::EDITOR_PROMOTION,
        ];
    }

    /**
     * Semua HR roles
     */
    public static function getHrRoles(): array
    {
        return [
            self::HR,
            self::GENERAL_AFFAIRS,
            self::FINANCE,
            self::OFFICE_ASSISTANT,
        ];
    }

    /**
     * Semua executive roles
     */
    public static function getExecutiveRoles(): array
    {
        return [
            self::VP_PRESIDENT,
            self::PRESIDENT_DIRECTOR,
        ];
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Cek apakah role adalah manager
     */
    public static function isManager(string $role): bool
    {
        return in_array($role, self::getManagerRoles());
    }

    /**
     * Cek apakah role adalah producer
     */
    public static function isProducer(string $role): bool
    {
        return $role === self::PRODUCER;
    }

    /**
     * Cek apakah role adalah HR
     */
    public static function isHr(string $role): bool
    {
        return in_array($role, self::getHrRoles());
    }

    /**
     * Cek apakah role adalah production team
     */
    public static function isProductionTeam(string $role): bool
    {
        return in_array($role, self::getProductionTeamRoles());
    }

    /**
     * Cek apakah role adalah distribution team
     */
    public static function isDistributionTeam(string $role): bool
    {
        return in_array($role, self::getDistributionTeamRoles());
    }

    /**
     * Cek apakah role bisa approve program
     */
    public static function canApproveProgram(string $role): bool
    {
        return in_array($role, self::getManagerRoles());
    }

    /**
     * Cek apakah role bisa approve rundown
     */
    public static function canApproveRundown(string $role): bool
    {
        return in_array($role, self::getRundownApproverRoles());
    }

    /**
     * Cek apakah role bisa approve schedule
     */
    public static function canApproveSchedule(string $role): bool
    {
        return in_array($role, self::getScheduleApproverRoles());
    }

    /**
     * Normalize role name (handle variasi penulisan)
     * 
     * Contoh:
     * - 'Manager Program' -> 'Program Manager'
     * - 'managerprogram' -> 'Program Manager'
     * - 'program manager' -> 'Program Manager'
     */
    public static function normalize(string $role): string
    {
        $role = trim($role);

        // Mapping untuk variasi penulisan
        $variations = [
            // Manager Program variations
            'manager program' => self::PROGRAM_MANAGER,
            'managerprogram' => self::PROGRAM_MANAGER,
            'programmanager' => self::PROGRAM_MANAGER,
            'Manager Program' => self::PROGRAM_MANAGER,
            'ManagerProgram' => self::PROGRAM_MANAGER,
            'ProgramManager' => self::PROGRAM_MANAGER,

            // Production variations
            'production' => self::PRODUCTION,
            'produksi' => self::PRODUCTION,

            // Editor variations
            'editor' => self::EDITOR,

            // Creative variations
            'creative' => self::CREATIVE,

            // Quality Control variations
            'quality control' => self::QUALITY_CONTROL,
            'quality_control' => self::QUALITY_CONTROL,
            'QC' => self::QUALITY_CONTROL,

            // Sound Engineer variations
            'sound engineer' => self::SOUND_ENGINEER,
            'sound_engineer' => self::SOUND_ENGINEER,

            // Music Arranger variations
            'music arranger' => self::MUSIC_ARRANGER,
            'music_arranger' => self::MUSIC_ARRANGER,
            'musik_arr' => self::MUSIC_ARRANGER,

            // Broadcasting variations
            'broadcasting' => self::BROADCASTING,

            // Promotion variations (handle typo)
            'promotion' => self::PROMOTION,
            'promosi' => self::PROMOTION,
            'prmotion' => self::PROMOTION, // Handle typo: prmotion -> Promotion

            // Producer variations (handle typo)
            'producer' => self::PRODUCER,
            'prodcer' => self::PRODUCER, // Handle typo: prodcer -> Producer

            // VP President variations (handle typo)
            'vp president' => self::VP_PRESIDENT,
            'vice president' => self::VP_PRESIDENT,
            'vice presdent' => self::VP_PRESIDENT, // Handle typo: vice presdent -> VP President
            'vice_president' => self::VP_PRESIDENT,

            // Graphic Design variations
            'graphic design' => self::GRAPHIC_DESIGN,
            'graphic_design' => self::GRAPHIC_DESIGN,

            // Hopeline Care variations
            'hopeline care' => self::HOPELINE_CARE,
            'hope line care' => self::HOPELINE_CARE,
            'hope_line_care' => self::HOPELINE_CARE,

            // Art & Set Properti variations
            'art & set properti' => self::ART_SET_PROPERTI,
            'art set properti' => self::ART_SET_PROPERTI,
            'art_set_properti' => self::ART_SET_PROPERTI,

            // Editor Promotion variations
            'editor promotion' => self::EDITOR_PROMOTION,
            'editor_promotion' => self::EDITOR_PROMOTION,

            // Distribution Manager variations
            'distribution manager' => self::DISTRIBUTION_MANAGER,
            'distribution_manager' => self::DISTRIBUTION_MANAGER,
            'manager distribusi' => self::DISTRIBUTION_MANAGER,
            'manager_distribusi' => self::DISTRIBUTION_MANAGER,

            // Program Manager variations
            'program manager' => self::PROGRAM_MANAGER,
            'program_manager' => self::PROGRAM_MANAGER,
        ];

        $lowerRole = strtolower($role);

        // Cek apakah ada di mapping
        if (isset($variations[$lowerRole])) {
            return $variations[$lowerRole];
        }

        // Jika tidak ada di mapping, return as-is (mungkin custom role)
        return $role;
    }

    /**
     * Cek apakah role valid (ada di standard roles atau custom roles)
     */
    public static function isValid(string $role): bool
    {
        $standardRoles = self::getAllStandardRoles();
        $normalizedRole = self::normalize($role);

        return in_array($normalizedRole, $standardRoles);
    }

    /**
     * Get all standard roles (sesuai dengan role yang ada di Hope Channel Indonesia)
     * 
     * Catatan: Role ini adalah role standar yang selalu ada.
     * Untuk custom roles yang ditambahkan melalui sistem, gunakan DatabaseEnumService::getAllAvailableRoles()
     */
    public static function getAllStandardRoles(): array
    {
        return [
                // HR & Management (sesuai urutan di daftar)
            self::HOPELINE_CARE,        // 1. Hope Line Care
            self::PRODUCTION,           // 2. Production
            self::GRAPHIC_DESIGN,       // 3. Graphic Design
            self::EDITOR,               // 4. Editor
            self::HR,                   // 5. HR
            self::GENERAL_AFFAIRS,      // 6. General Affairs
            self::PROMOTION,            // 7. Promotion
            self::SOUND_ENGINEER,       // 8. Sound Engineer
            self::ART_SET_PROPERTI,    // 9. Art & Set Properti
            self::PROGRAM_MANAGER,      // 10. Program Manager
            self::OFFICE_ASSISTANT,     // 11. Office Assistant
            self::CREATIVE,             // 12. Creative
            self::QUALITY_CONTROL,      // 13. Quality Control
            self::PRESIDENT_DIRECTOR,   // 14. President Director
            self::EDITOR_PROMOTION,     // 15. Editor Promotion
            self::DISTRIBUTION_MANAGER, // 16. Distribution Manager
            self::MUSIC_ARRANGER,       // 17. Music Arranger
            self::BROADCASTING,         // 18. Broadcasting
            self::PRODUCER,             // 19. Producer
            self::VP_PRESIDENT,         // 20. VP President

                // Additional roles (tetap ada di sistem)
            self::FINANCE,              // Finance
            self::SOCIAL_MEDIA,          // Social Media

                // Default (untuk fallback)
            self::EMPLOYEE,
        ];
    }

    /**
     * Compare two roles (handle variasi penulisan)
     */
    public static function equals(string $role1, string $role2): bool
    {
        return self::normalize($role1) === self::normalize($role2);
    }

    /**
     * Cek apakah role ada di array (handle variasi penulisan)
     */
    public static function inArray(string $role, array $roles): bool
    {
        $normalizedRole = self::normalize($role);
        $normalizedRoles = array_map([self::class, 'normalize'], $roles);

        return in_array($normalizedRole, $normalizedRoles);
    }
}

