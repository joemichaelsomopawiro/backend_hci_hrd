<?php
/**
 * Perbaikan untuk AttendanceExcelUploadService
 * 
 * Copy paste kode ini ke method processExcelUpload dan previewExcelData
 */

// === PERBAIKAN UNTUK METHOD processExcelUpload ===

// Ganti bagian ini di method processExcelUpload():
/*
// Baca file Excel dengan error handling yang lebih detail
try {
    // Cek ekstensi file untuk menentukan reader yang tepat
    $fileExtension = strtolower($file->getClientOriginalExtension());
    
    if ($fileExtension === 'xls') {
        // Untuk file .xls, gunakan Xls reader secara eksplisit
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getPathname());
    } else {
        // Untuk file .xlsx, gunakan auto-detect
        $spreadsheet = IOFactory::load($file->getPathname());
    }
    
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    
} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    return [
        'success' => false,
        'message' => 'File Excel tidak dapat dibaca. Pastikan file tidak rusak dan format benar.',
        'debug_info' => [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_extension' => $file->getClientOriginalExtension(),
            'error' => $e->getMessage(),
            'php_extensions' => [
                'zip' => extension_loaded('zip'),
                'xml' => extension_loaded('xml'),
                'gd' => extension_loaded('gd')
            ]
        ]
    ];
} catch (\Exception $e) {
    return [
        'success' => false,
        'message' => 'Terjadi kesalahan saat membaca file Excel: ' . $e->getMessage(),
        'debug_info' => [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_extension' => $file->getClientOriginalExtension(),
            'php_extensions' => [
                'zip' => extension_loaded('zip'),
                'xml' => extension_loaded('xml'),
                'gd' => extension_loaded('gd')
            ]
        ]
    ];
}
*/

// === PERBAIKAN UNTUK METHOD previewExcelData ===

// Ganti bagian ini di method previewExcelData():
/*
// Baca file Excel dengan error handling yang lebih detail
try {
    // Cek ekstensi file untuk menentukan reader yang tepat
    $fileExtension = strtolower($file->getClientOriginalExtension());
    
    if ($fileExtension === 'xls') {
        // Untuk file .xls, gunakan Xls reader secara eksplisit
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getPathname());
    } else {
        // Untuk file .xlsx, gunakan auto-detect
        $spreadsheet = IOFactory::load($file->getPathname());
    }
    
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
    
} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    return [
        'success' => false,
        'message' => 'File Excel tidak dapat dibaca. Pastikan file tidak rusak dan format benar.',
        'debug_info' => [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_extension' => $file->getClientOriginalExtension(),
            'error' => $e->getMessage(),
            'php_extensions' => [
                'zip' => extension_loaded('zip'),
                'xml' => extension_loaded('xml'),
                'gd' => extension_loaded('gd')
            ]
        ]
    ];
} catch (\Exception $e) {
    return [
        'success' => false,
        'message' => 'Terjadi kesalahan saat membaca file Excel: ' . $e->getMessage(),
        'debug_info' => [
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_extension' => $file->getClientOriginalExtension(),
            'php_extensions' => [
                'zip' => extension_loaded('zip'),
                'xml' => extension_loaded('xml'),
                'gd' => extension_loaded('gd')
            ]
        ]
    ];
}
*/

// === LANGKAH-LANGKAH PERBAIKAN ===

echo "=== LANGKAH PERBAIKAN ===\n\n";

echo "1. Buka file: app/Services/AttendanceExcelUploadService.php\n\n";

echo "2. Cari method processExcelUpload() dan ganti bagian:\n";
echo "   \$spreadsheet = IOFactory::load(\$file->getPathname());\n";
echo "   Menjadi kode yang ada di atas\n\n";

echo "3. Cari method previewExcelData() dan ganti bagian yang sama\n\n";

echo "4. Pastikan PHP extensions sudah terinstall:\n";
echo "   - zip: " . (extension_loaded('zip') ? '✅' : '❌') . "\n";
echo "   - xml: " . (extension_loaded('xml') ? '✅' : '❌') . "\n";
echo "   - gd: " . (extension_loaded('gd') ? '✅' : '❌') . "\n\n";

echo "5. Test upload file Excel .xls\n\n";

echo "=== SELESAI ===\n"; 