<?php

namespace Database\Seeders;

use App\Models\CompressorAirBlower;
use Illuminate\Database\Seeder;

class CompressorAirBlowerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Generating 5000 compressor air blower records...');

        $totalRecords = 5000;
        $now = now();
        $oneMonthAgo = $now->copy()->subMonth();

        // Calculate time increment in seconds to distribute records evenly
        $timeRangeSeconds = $oneMonthAgo->diffInSeconds($now);
        $timeIncrementSeconds = $timeRangeSeconds / ($totalRecords - 1);

        // Generate records in chunks for better performance
        $chunkSize = 500;
        $chunks = ceil($totalRecords / $chunkSize);

        for ($chunk = 0; $chunk < $chunks; $chunk++) {
            $recordsInChunk = min($chunkSize, $totalRecords - ($chunk * $chunkSize));

            $records = CompressorAirBlower::factory()
                ->count($recordsInChunk)
                ->make()
                ->map(function ($record, $index) use ($oneMonthAgo, $timeIncrementSeconds, $chunk, $chunkSize) {
                    $globalIndex = ($chunk * $chunkSize) + $index;
                    $recordTimestamp = $oneMonthAgo->copy()->addSeconds($globalIndex * $timeIncrementSeconds);

                    return [
                        'flow' => $record->flow,
                        'temperature' => $record->temperature,
                        'pressure' => $record->pressure,
                        'vibration' => $record->vibration,
                        'status' => $record->status,
                        'created_at' => $recordTimestamp,
                        'updated_at' => $recordTimestamp,
                    ];
                })
                ->toArray();

            CompressorAirBlower::insert($records);
            $this->command->info('Inserted ' . (($chunk + 1) * $chunkSize) . ' / ' . $totalRecords . ' records...');
        }

        $this->command->info('Successfully seeded ' . CompressorAirBlower::count() . ' compressor air blower records.');
    }
}
