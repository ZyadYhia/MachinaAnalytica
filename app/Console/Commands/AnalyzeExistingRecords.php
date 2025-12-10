<?php

namespace App\Console\Commands;

use App\Jobs\TriggerMLAnalysis;
use App\Models\CompressorAirBlower;
use Illuminate\Console\Command;

class AnalyzeExistingRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compressor:analyze-all {--status= : Filter by status (pending, analysis_error, or leave empty for all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue ML analysis for all existing compressor records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mlServiceUrl = env('ML_SERVICE_URL', 'http://127.0.0.1:8002/process_data');
        $mlToken = env('ML_SERVICE_TOKEN', 'YOUR_SECURE_ML_TOKEN');

        $query = CompressorAirBlower::query();

        // Filter by status if provided
        if ($status = $this->option('status')) {
            $query->where('status', $status);
            $this->info("Filtering records with status: {$status}");
        }

        $records = $query->get();
        $count = $records->count();

        if ($count === 0) {
            $this->warn('No records found to analyze.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} records to analyze.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($records as $record) {
            // Dispatch job for each record
            TriggerMLAnalysis::dispatch($record->id, $mlServiceUrl, $mlToken);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Queued {$count} records for ML analysis.");
        $this->info("Run 'php artisan queue:work' to process them.");

        return self::SUCCESS;
    }
}
