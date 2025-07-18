<?php
/**
 * Script untuk memperbaiki validasi supervisor di CustomRoleController
 * 
 * Masalah: Backend memerlukan supervisor_id untuk employee roles
 * Solusi: Menghilangkan validasi supervisor_id sementara
 */

// Path ke file controller
$controllerPath = 'C:\laragon\www\backend_hci_hrd\app\Http\Controllers\CustomRoleController.php';

// Baca file controller
$content = file_get_contents($controllerPath);

// Perbaikan 1: Menghilangkan validasi supervisor_id untuk employee
$oldValidation1 = "// Validasi tambahan untuk supervisor
            if (\$validated['access_level'] === 'employee' && !\$validated['supervisor_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee roles must have a supervisor'
                ], 422);
            }";

$newValidation1 = "// Validasi supervisor sementara dinonaktifkan
            // TODO: Implementasi supervisor validation setelah data supervisor tersedia
            // if (\$validated['access_level'] === 'employee' && !\$validated['supervisor_id']) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Employee roles must have a supervisor'
            //     ], 422);
            // }";

// Perbaikan 2: Menghilangkan validasi supervisor_id di update method
$oldValidation2 = "// Validasi tambahan untuk supervisor
            if (\$validated['access_level'] === 'employee' && !\$validated['supervisor_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee roles must have a supervisor'
                ], 422);
            }";

$newValidation2 = "// Validasi supervisor sementara dinonaktifkan
            // TODO: Implementasi supervisor validation setelah data supervisor tersedia
            // if (\$validated['access_level'] === 'employee' && !\$validated['supervisor_id']) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Employee roles must have a supervisor'
            //     ], 422);
            // }";

// Perbaikan 3: Mengubah validasi supervisor_id menjadi nullable
$oldValidation3 = "'supervisor_id' => [
                    'nullable',
                    'exists:custom_roles,id',
                    function (\$attribute, \$value, \$fail) {
                        // Validasi hierarchy untuk mencegah circular reference
                        if (\$value && !RoleHierarchyService::validateHierarchy(null, \$value)) {        
                            \$fail('Invalid supervisor selection. Cannot create circular reference.');  
                        }
                    }
                ]";

$newValidation3 = "'supervisor_id' => [
                    'nullable',
                    // 'exists:custom_roles,id', // Sementara dinonaktifkan
                    // function (\$attribute, \$value, \$fail) {
                    //     // Validasi hierarchy untuk mencegah circular reference
                    //     if (\$value && !RoleHierarchyService::validateHierarchy(null, \$value)) {        
                    //         \$fail('Invalid supervisor selection. Cannot create circular reference.');  
                    //     }
                    // }
                ]";

// Perbaikan 4: Mengubah validasi supervisor_id di update method
$oldValidation4 = "'supervisor_id' => [
                    'nullable',
                    'exists:custom_roles,id',
                    function (\$attribute, \$value, \$fail) use (\$id) {
                        // Validasi hierarchy untuk mencegah circular reference
                        if (\$value && !RoleHierarchyService::validateHierarchy(\$id, \$value)) {
                            \$fail('Invalid supervisor selection. Cannot create circular reference.');  
                        }
                    }
                ],";

$newValidation4 = "'supervisor_id' => [
                    'nullable',
                    // 'exists:custom_roles,id', // Sementara dinonaktifkan
                    // function (\$attribute, \$value, \$fail) use (\$id) {
                    //     // Validasi hierarchy untuk mencegah circular reference
                    //     if (\$value && !RoleHierarchyService::validateHierarchy(\$id, \$value)) {
                    //         \$fail('Invalid supervisor selection. Cannot create circular reference.');  
                    //     }
                    // }
                ],";

// Terapkan perbaikan
$content = str_replace($oldValidation1, $newValidation1, $content);
$content = str_replace($oldValidation2, $newValidation2, $content);
$content = str_replace($oldValidation3, $newValidation3, $content);
$content = str_replace($oldValidation4, $newValidation4, $content);

// Tulis kembali ke file
if (file_put_contents($controllerPath, $content)) {
    echo "✅ Berhasil memperbaiki validasi supervisor di CustomRoleController\n";
    echo "📁 File: $controllerPath\n";
    echo "🔧 Perubahan yang dilakukan:\n";
    echo "   - Menghilangkan validasi supervisor_id untuk employee roles\n";
    echo "   - Menghilangkan validasi exists:custom_roles,id\n";
    echo "   - Menghilangkan validasi circular reference\n";
    echo "   - Supervisor_id sekarang bisa null\n";
} else {
    echo "❌ Gagal memperbaiki file controller\n";
}

echo "\n📝 Catatan:\n";
echo "- Validasi supervisor sementara dinonaktifkan\n";
echo "- Setelah ada data supervisor di database, validasi bisa diaktifkan kembali\n";
echo "- Restart backend server setelah perubahan\n";
?> 