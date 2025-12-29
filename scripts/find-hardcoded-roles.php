<?php

/**
 * Script untuk menemukan semua hard-coded role names di codebase
 * 
 * Usage: php scripts/find-hardcoded-roles.php
 */

$baseDir = __DIR__ . '/../app';
$roleConstants = [
    'HR', 'Program Manager', 'Distribution Manager', 'GA',
    'Finance', 'General Affairs', 'Office Assistant',
    'VP President', 'President Director',
    'Producer', 'Creative', 'Production', 'Editor',
    'Social Media', 'Promotion', 'Graphic Design', 'Hopeline Care',
    'Music Arranger', 'Sound Engineer', 'Sound Engineer Recording', 'Sound Engineer Editing',
    'Quality Control', 'Art & Set Properti', 'Editor Promotion', 'Broadcasting',
    'Employee'
];

$variations = [
    'Manager', 'Manager Program', 'ManagerProgram', 'managerprogram', 'program manager',
    'production', 'produksi',
    'editor',
    'creative',
    'quality control', 'quality_control', 'QC',
    'sound engineer', 'sound_engineer',
    'music arranger', 'music_arranger', 'musik_arr',
    'broadcasting',
    'promotion', 'promosi',
    'graphic design', 'graphic_design',
];

$foundIssues = [];
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir),
    RecursiveIteratorIterator::SELF_FIRST
);

echo "🔍 Mencari hard-coded role names...\n\n";

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $relativePath = str_replace(__DIR__ . '/../', '', $file->getPathname());
        
        // Skip Role.php sendiri
        if (strpos($relativePath, 'Constants/Role.php') !== false) {
            continue;
        }
        
        $lines = explode("\n", $content);
        $lineNumber = 0;
        
        foreach ($lines as $line) {
            $lineNumber++;
            $lineTrimmed = trim($line);
            
            // Skip comments
            if (strpos($lineTrimmed, '//') === 0 || strpos($lineTrimmed, '*') === 0) {
                continue;
            }
            
            // Cek hard-coded role strings
            foreach ($roleConstants as $role) {
                // Cek string literal dengan role name
                if (preg_match("/['\"]" . preg_quote($role, '/') . "['\"]/", $line)) {
                    // Skip jika sudah menggunakan Role:: constant
                    if (strpos($line, 'Role::') !== false) {
                        continue;
                    }
                    
                    $foundIssues[] = [
                        'file' => $relativePath,
                        'line' => $lineNumber,
                        'content' => $lineTrimmed,
                        'role' => $role,
                        'type' => 'hard-coded'
                    ];
                }
            }
            
            // Cek variasi penulisan yang salah
            foreach ($variations as $variation) {
                if (preg_match("/['\"]" . preg_quote($variation, '/') . "['\"]/i", $line)) {
                    // Skip jika sudah menggunakan Role:: constant
                    if (strpos($line, 'Role::') !== false) {
                        continue;
                    }
                    
                    // Skip jika ini adalah comment atau documentation
                    if (strpos($lineTrimmed, '//') !== false || strpos($lineTrimmed, '*') !== false) {
                        continue;
                    }
                    
                    $foundIssues[] = [
                        'file' => $relativePath,
                        'line' => $lineNumber,
                        'content' => $lineTrimmed,
                        'role' => $variation,
                        'type' => 'variation'
                    ];
                }
            }
            
            // Cek in_array dengan role strings
            if (preg_match("/in_array.*role.*\[(.*?)\]/", $line, $matches)) {
                if (strpos($line, 'Role::') === false && strpos($line, 'getManagerRoles') === false) {
                    $foundIssues[] = [
                        'file' => $relativePath,
                        'line' => $lineNumber,
                        'content' => $lineTrimmed,
                        'role' => 'in_array with roles',
                        'type' => 'in_array'
                    ];
                }
            }
        }
    }
}

// Tampilkan hasil
if (empty($foundIssues)) {
    echo "✅ Tidak ada hard-coded role names ditemukan!\n";
} else {
    echo "⚠️  Ditemukan " . count($foundIssues) . " hard-coded role names:\n\n";
    
    $grouped = [];
    foreach ($foundIssues as $issue) {
        $grouped[$issue['file']][] = $issue;
    }
    
    foreach ($grouped as $file => $issues) {
        echo "📄 {$file}\n";
        foreach ($issues as $issue) {
            $typeIcon = $issue['type'] === 'variation' ? '❌' : '⚠️';
            echo "   {$typeIcon} Line {$issue['line']}: {$issue['role']}\n";
            echo "      {$issue['content']}\n";
        }
        echo "\n";
    }
    
    echo "\n💡 Rekomendasi:\n";
    echo "   1. Gunakan Role::CONSTANT untuk role names\n";
    echo "   2. Gunakan Role::helperMethods() untuk permission checking\n";
    echo "   3. Lihat ROLE_NAMES_REFERENCE.md untuk panduan lengkap\n";
}

echo "\n✅ Selesai!\n";

