<?php

namespace App\Jobs;

use App\Models\CompressorAirBlower;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerMLAnalysis implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $recordId,
        public string $mlServiceUrl,
        public string $mlToken
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $record = CompressorAirBlower::find($this->recordId);

        if (! $record) {
            Log::warning("Record {$this->recordId} not found for ML analysis");

            return;
        }

        Log::info("[ML Analysis] Starting analysis for record {$this->recordId}", [
            'flow' => $record->flow,
            'temperature' => $record->temperature,
            'pressure' => $record->pressure,
            'vibration' => $record->vibration,
            'current_status' => $record->status,
        ]);

        try {
            $startTime = microtime(true);

            $response = Http::timeout(30)
                ->withQueryParameters([
                    'record_id' => $this->recordId,
                    'token' => $this->mlToken,
                ])
                ->post($this->mlServiceUrl);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $response->throw();

            $responseData = $response->json();

            Log::info("[ML Analysis] Completed for record {$this->recordId}", [
                'duration_ms' => $duration,
                'http_status' => $response->status(),
                'response' => $responseData,
            ]);

            // Refresh record to see updated status
            $record->refresh();

            Log::info("[ML Analysis] Final status for record {$this->recordId}: {$record->status}");
        } catch (\Exception $e) {
            Log::error("[ML Analysis] Failed for record {$this->recordId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update the record status to 'error' if the ML service couldn't be contacted
            CompressorAirBlower::where('id', $this->recordId)->update(['status' => 'analysis_error']);
        }
    }
}
