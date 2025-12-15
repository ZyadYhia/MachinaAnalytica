<?php

namespace App\Console\Commands;

use App\Jobs\TriggerMLAnalysis;
use App\Models\CompressorAirBlower;
use Illuminate\Console\Command;

class ProcessPendingRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compressor:process-pending {--limit=100 : Maximum number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process latest records that are not analyzed (pending or error status)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mlServiceUrl = env('ML_SERVICE_URL', 'http://127.0.0.1:8002/process_data');
        $mlToken = env('ML_SERVICE_TOKEN', 'YOUR_SECURE_ML_TOKEN');
        $limit = (int) $this->option('limit');

        // Get records that don't have normal, warning, or critical status
        $records = CompressorAirBlower::whereNotIn('status', ['normal', 'warning', 'critical'])
            ->latest()
            ->limit($limit)
            ->get();

        $count = $records->count();

        if ($count === 0) {
            $this->info('✓ No pending records to process.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} pending record(s) to analyze.");

        foreach ($records as $record) {
            TriggerMLAnalysis::dispatch($record->id, $mlServiceUrl, $mlToken);
        }

        $this->info("✓ Queued {$count} record(s) for ML analysis.");

        return self::SUCCESS;
    }
}
