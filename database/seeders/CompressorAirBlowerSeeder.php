<?php

namespace Database\Seeders;

use App\Models\CompressorAirBlower;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CompressorAirBlowerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = Storage::path('235K001.csv');

        if (! file_exists($csvFile)) {
            $this->command->error('CSV file not found at: ' . $csvFile);

            return;
        }

        $file = fopen($csvFile, 'r');

        // Skip the header row
        fgetcsv($file);

        $records = [];
        $now = now();

        while (($row = fgetcsv($file)) !== false) {
            // Skip empty rows
            if (empty($row[1])) {
                continue;
            }

            $records[] = [
                'flow' => (float) $row[2],
                'temperature' => (float) $row[3],
                'pressure' => (float) $row[4],
                'vibration' => (float) $row[5],
                'status' => $row[6],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Insert in chunks of 100 for better performance
            if (count($records) >= 100) {
                CompressorAirBlower::insert($records);
                $records = [];
            }
        }

        // Insert remaining records
        if (! empty($records)) {
            CompressorAirBlower::insert($records);
        }

        fclose($file);

        $this->command->info('Successfully seeded ' . CompressorAirBlower::count() . ' compressor air blower records.');
    }
}
