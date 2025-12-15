<?php

namespace App\Http\Controllers\API\ML;

use App\Http\Controllers\Controller;
use App\Jobs\TriggerMLAnalysis;
use App\Models\CompressorAirBlower;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompressorDataController extends Controller
{
    /**
     * Configuration for the External ML Service
     */
    private $mlServiceUrl;

    private $mlToken;

    public function __construct()
    {
        // Fetches ML_SERVICE_URL (e.g., http://127.0.0.1:8002/process_data)
        $this->mlServiceUrl = env('ML_SERVICE_URL', 'http://127.0.0.1:8002/process_data');

        // Fetches the shared secret token
        $this->mlToken = env('ML_SERVICE_TOKEN', 'YOUR_SECURE_ML_TOKEN');
    }

    // --- 1. EXTERNAL API: Process New Sensor Data (Ingestion & Trigger) ---
    /**
     * Receives new sensor data, inserts a 'pending' record, and triggers the ML service for analysis.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processNewData(Request $request)
    {
        // 1. Validate the incoming sensor data. Laravel automatically handles the 422 response.
        $validated = $request->validate([
            'flow' => 'required|numeric',
            'temperature' => 'required|numeric',
            'pressure' => 'required|numeric',
            'vibration' => 'required|numeric',
        ]);

        // 2. Insert the record with a temporary 'pending' status
        $newRecord = CompressorAirBlower::create(array_merge($validated, ['status' => 'pending']));

        Log::info('[Compressor Data] New record created', [
            'id' => $newRecord->id,
            'flow' => $validated['flow'],
            'temperature' => $validated['temperature'],
            'pressure' => $validated['pressure'],
            'vibration' => $validated['vibration'],
            'status' => 'pending',
        ]);

        // 3. ML analysis will be triggered by scheduled command
        // TriggerMLAnalysis::dispatch($newRecord->id, $this->mlServiceUrl, $this->mlToken);

        Log::info('[Compressor Data] Record saved. ML analysis will be processed by scheduler', ['record_id' => $newRecord->id]);

        return response()->json([
            'message' => 'Record created. ML analysis queued.',
            'id' => $newRecord->id,
            'initial_status' => 'pending',
        ], 202); // 202 Accepted: Processing is asynchronous/external
    }

    // --- 2. INTERNAL API: Fetch Target Record + Historical Data ---
    /**
     * Used by the Python ML service to get all data needed for analysis.
     * This route is protected by the 'check.ml.token' middleware.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchForAnalysis(Request $request)
    {
        // The Python ML service passes the ID of the record it needs to analyze
        $target_id = $request->get('id');
        $limit = $request->get('limit', 100);

        // Fetch the target record
        $targetRecord = CompressorAirBlower::find($target_id);

        if (! $targetRecord) {
            return response()->json(['message' => 'Target record not found.'], 404);
        }

        // Fetch the N previous records (excluding the target record itself)
        $historicalData = CompressorAirBlower::where('id', '<', $target_id)
            ->latest()
            ->limit($limit)
            ->get([
                'flow',
                'temperature',
                'pressure',
                'vibration',
            ]);

        // Return all necessary data to the ML service
        return response()->json([
            // Target data for Z-score calculation
            'target_record' => $targetRecord->only(['flow', 'temperature', 'pressure', 'vibration']),
            // Historical data for baseline establishment
            'historical_data' => $historicalData->toArray(),
        ]);
    }

    // --- 3. INTERNAL API: Receive Status Update from ML Service ---
    /**
     * Receives the status update from the Python ML service and persists it.
     * This route is protected by the 'check.ml.token' middleware.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request)
    {
        // 1. Validate the update payload from the ML service
        $validated = $request->validate([
            'id' => 'required|integer|exists:compressor_air_blowers,id',
            'status' => 'required|in:normal,warning,critical',
            // 'vibration_z' is optional data we expect from the ML script
            'vibration_z' => 'nullable|numeric',
        ]);

        $record = CompressorAirBlower::find($validated['id']);

        Log::info('[Status Update] Received from ML service', [
            'record_id' => $validated['id'],
            'old_status' => $record->status,
            'new_status' => $validated['status'],
            'vibration_z' => $validated['vibration_z'] ?? null,
        ]);

        // 2. Update the status
        $record->status = $validated['status'];
        $record->save();

        Log::info("[Status Update] Record {$validated['id']} status updated to: {$validated['status']}");

        // 3. Perform the post-processing update (Critical -> Warning progression)
        if ($record->status === 'critical') {
            $this->updatePreviousStatus($record);
        }

        return response()->json(['message' => 'Status update received and processed successfully.']);
    }

    /**
     * Helper function to update the status of previous records if the new one is critical.
     */
    protected function updatePreviousStatus(CompressorAirBlower $newRecord): void
    {
        if ($newRecord->status === 'critical') {

            $updatedCount = CompressorAirBlower::where('id', '<', $newRecord->id)
                ->where('status', 'normal') // Only target records currently marked as 'normal'
                ->latest()
                ->limit(5) // Only update the last 5 relevant records
                ->update(['status' => 'warning']); // Change status to 'warning'

            Log::info("Critical event detected (ID: {$newRecord->id}). Updated {$updatedCount} previous records to 'warning'.");
        }
    }
}
