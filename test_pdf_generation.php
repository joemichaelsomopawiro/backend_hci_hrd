<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$app = new Application(realpath(__DIR__));

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
);

echo "Testing PDF Generation...\n";

try {
    // Test 1: Check if DomPDF class exists
    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
        echo "✅ DomPDF class found\n";
    } else {
        echo "❌ DomPDF class NOT found\n";
        echo "Please run: composer require barryvdh/laravel-dompdf\n";
        exit(1);
    }

    // Test 2: Check if template exists
    $templatePath = resource_path('views/pdfs/leave_letter_simple.blade.php');
    if (file_exists($templatePath)) {
        echo "✅ PDF template found\n";
    } else {
        echo "❌ PDF template NOT found at: $templatePath\n";
        exit(1);
    }

    // Test 3: Check storage directories
    $storagePath = storage_path('app/public');
    if (is_dir($storagePath) && is_writable($storagePath)) {
        echo "✅ Storage directory is writable\n";
    } else {
        echo "❌ Storage directory not writable: $storagePath\n";
    }

    // Test 4: Check public downloads directory
    $downloadsPath = public_path('downloads');
    if (is_dir($downloadsPath) && is_writable($downloadsPath)) {
        echo "✅ Downloads directory is writable\n";
    } else {
        echo "❌ Downloads directory not writable: $downloadsPath\n";
    }

    // Test 5: Try to create a simple PDF
    echo "Testing PDF creation...\n";
    
    $testData = [
        'employee_name' => 'Test Employee',
        'employee_position' => 'Test Position',
        'date_range_text' => '1 Januari 2025 - 5 Januari 2025',
        'total_days' => 5,
        'leave_type' => 'annual',
        'letter_date' => '1 Januari 2025',
        'city' => 'Jakarta',
        'year' => 2025,
        'employee_signature_data_uri' => null,
        'approver_signature_data_uri' => null,
        'leave_location' => 'Jakarta',
        'contact_phone' => '08123456789',
        'emergency_contact' => '08123456789',
        'approver_name' => 'Test Manager',
        'approver_position' => 'Manager',
        'company_logo_data_uri' => null,
        'company_name' => 'HOPE CHANNEL INDONESIA',
        'company_address_lines' => [
            '2nd Floor Gedung Pertemuan Advent',
            'Jl. M. T. Haryono Kav. 4-5 Block A - Jakarta 12810',
            '🕿 62.21.8379 7879 / 8379 7883 ● 🖂: info@hopetv.or.id  ● www.hopechannel.id',
        ],
    ];

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.leave_letter_simple', $testData)->setPaper('A4');
    $pdfContent = $pdf->output();
    
    if ($pdfContent && strlen($pdfContent) > 1000) {
        echo "✅ PDF generated successfully (" . strlen($pdfContent) . " bytes)\n";
        
        // Save test PDF
        $testPdfPath = storage_path('app/public/test_leave_letter.pdf');
        file_put_contents($testPdfPath, $pdfContent);
        echo "✅ Test PDF saved to: $testPdfPath\n";
    } else {
        echo "❌ PDF generation failed\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nPDF Generation Test Complete!\n";





