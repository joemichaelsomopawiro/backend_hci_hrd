<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\QualityControlWork;

class CleanMockQCData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qc:clean-mock-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus atau bersihkan data QC yang memakai nilai mock (mis. timestamp) sebagai file_path/file_link';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Mencari dan membersihkan data QC mock (file_path/file_link yang bukan link/file valid)...');

        $totalUpdated = 0;

        QualityControlWork::chunk(100, function ($works) use (&$totalUpdated) {
            /** @var \App\Models\QualityControlWork $work */
            foreach ($works as $work) {
                $changed = false;

                $fields = [
                    'files_to_check',
                    'editor_promosi_file_locations',
                    'design_grafis_file_locations',
                ];

                foreach ($fields as $field) {
                    $data = $work->{$field};
                    if (!is_array($data) || empty($data)) {
                        continue;
                    }

                    $originalCount = count($data);

                    // Filter hanya item yang punya file_path/file_link "masuk akal"
                    $data = array_values(array_filter($data, function ($item) {
                        $path = $item['file_path'] ?? '';
                        $link = $item['file_link'] ?? '';

                        // Kalau punya file_link yang mulai dengan http/https → anggap valid
                        if (is_string($link) && preg_match('#^https?://#i', $link)) {
                            return true;
                        }

                        // Kalau punya file_path yang kelihatan seperti path file (ada "/" dan ekstensi) → valid
                        if (is_string($path) && str_contains($path, '/') && preg_match('/\.\w+$/', $path)) {
                            return true;
                        }

                        // Selain itu: buang (kemungkinan besar timestamp / mock)
                        return false;
                    }));

                    if (count($data) !== $originalCount) {
                        $work->{$field} = $data;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $work->save();
                    $totalUpdated++;
                }
            }
        });

        $this->info("✅ Selesai. QC works yang dibersihkan: {$totalUpdated} record.");

        return Command::SUCCESS;
    }
}

