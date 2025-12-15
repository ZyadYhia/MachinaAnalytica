<?php

namespace App\Mcp\Tools;

use App\Models\CompressorAirBlower;
use Illuminate\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CompressorAirBlowerReadings extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Retrieve readings from the compressor air blower sensors.
        Returns flow, temperature, pressure, vibration, and status data.
        Supports filtering by status, date range, and limiting results.

        IMPORTANT DATE HANDLING:
        - For "last 24 hours" or "past day": Use from_date with current date minus 1 day
        - For "today": Use from_date with today's date at 00:00:00
        - Always use current year (2025) unless explicitly specified otherwise
        - Date format: YYYY-MM-DD HH:MM:SS (e.g., 2025-12-06 00:00:00)
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        try {
            $query = CompressorAirBlower::query();

            // Filter by status if provided
            if ($request->get('status')) {
                $query->where('status', $request->get('status'));
            }

            // Temperature filters
            if ($request->get('temperature_gt')) {
                $query->where('temperature', '>', $request->get('temperature_gt'));
            }
            if ($request->get('temperature_lt')) {
                $query->where('temperature', '<', $request->get('temperature_lt'));
            }
            if ($request->get('temperature') && !$request->get('temperature_gt') && !$request->get('temperature_lt')) {
                $query->where('temperature', '=', $request->get('temperature'));
            }

            // Vibration filters
            if ($request->get('vibration_gt')) {
                $query->where('vibration', '>', $request->get('vibration_gt'));
            }
            if ($request->get('vibration_lt')) {
                $query->where('vibration', '<', $request->get('vibration_lt'));
            }
            if ($request->get('vibration') && !$request->get('vibration_gt') && !$request->get('vibration_lt')) {
                $query->where('vibration', '=', $request->get('vibration'));
            }

            // Flow filters
            if ($request->get('flow_gt')) {
                $query->where('flow', '>', $request->get('flow_gt'));
            }
            if ($request->get('flow_lt')) {
                $query->where('flow', '<', $request->get('flow_lt'));
            }
            if ($request->get('flow') && !$request->get('flow_gt') && !$request->get('flow_lt')) {
                $query->where('flow', '=', $request->get('flow'));
            }

            // Pressure filters
            if ($request->get('pressure_gt')) {
                $query->where('pressure', '>', $request->get('pressure_gt'));
            }
            if ($request->get('pressure_lt')) {
                $query->where('pressure', '<', $request->get('pressure_lt'));
            }
            if ($request->get('pressure') && !$request->get('pressure_gt') && !$request->get('pressure_lt')) {
                $query->where('pressure', '=', $request->get('pressure'));
            }

            // Filter by date range
            if ($request->get('from_date')) {
                $query->where('created_at', '>=', $request->get('from_date'));
            }

            if ($request->get('to_date')) {
                $query->where('created_at', '<=', $request->get('to_date'));
            }

            // Apply ordering
            $orderBy = $request->get('order_by', 'created_at');
            $orderDirection = $request->get('order_direction', 'desc');
            $query->orderBy($orderBy, $orderDirection);

            // Apply limit - cap at 50 for performance
            $limit = min($request->get('limit', 20), 50);

            // Use select to only fetch needed columns
            $readings = $query->select([
                'id',
                'flow',
                'temperature',
                'pressure',
                'vibration',
                'status',
                'created_at',
            ])->limit($limit)->get();

            if ($readings->isEmpty()) {
                return Response::text(json_encode([
                    'success' => true,
                    'count' => 0,
                    'message' => 'No readings found matching the criteria.',
                    'readings' => [],
                    'statistics' => null,
                ], JSON_PRETTY_PRINT));
            }

            // Calculate statistics efficiently
            $stats = [
                'count' => $readings->count(),
                'avg_flow' => round($readings->avg('flow'), 2),
                'avg_temperature' => round($readings->avg('temperature'), 2),
                'avg_pressure' => round($readings->avg('pressure'), 2),
                'avg_vibration' => round($readings->avg('vibration'), 2),
                'max_temperature' => round($readings->max('temperature'), 2),
                'max_pressure' => round($readings->max('pressure'), 2),
                'max_vibration' => round($readings->max('vibration'), 2),
                'min_temperature' => round($readings->min('temperature'), 2),
                'min_pressure' => round($readings->min('pressure'), 2),
                'min_vibration' => round($readings->min('vibration'), 2),
            ];

            // Format readings data
            $readingsData = $readings->map(function ($reading) {
                return [
                    'id' => $reading->id,
                    'flow' => $reading->flow,
                    'temperature' => $reading->temperature,
                    'pressure' => $reading->pressure,
                    'vibration' => $reading->vibration,
                    'status' => $reading->status,
                    'created_at' => $reading->created_at->format('Y-m-d H:i:s'),
                ];
            })->toArray();

            // Build JSON response
            $response = [
                'success' => true,
                'count' => $stats['count'],
                'readings' => $readingsData,
                'statistics' => $stats,
                'filters_applied' => [
                    'status' => $request->get('status'),
                    'temperature_gt' => $request->get('temperature_gt'),
                    'temperature_lt' => $request->get('temperature_lt'),
                    'vibration_gt' => $request->get('vibration_gt'),
                    'vibration_lt' => $request->get('vibration_lt'),
                    'flow_gt' => $request->get('flow_gt'),
                    'flow_lt' => $request->get('flow_lt'),
                    'pressure_gt' => $request->get('pressure_gt'),
                    'pressure_lt' => $request->get('pressure_lt'),
                    'from_date' => $request->get('from_date'),
                    'to_date' => $request->get('to_date'),
                ],
            ];

            return Response::text(json_encode($response, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            return Response::text(json_encode([
                'success' => false,
                'error' => true,
                'message' => "Error retrieving readings: {$e->getMessage()}",
                'count' => 0,
                'readings' => [],
            ], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->description('Filter by status (e.g., normal, warning, critical)'),
            'temperature_gt' => $schema->string()
                ->description('Filter temperature greater than this value'),
            'temperature_lt' => $schema->string()
                ->description('Filter temperature less than this value'),
            'temperature' => $schema->string()
                ->description('Filter temperature equal to this value'),
            'vibration_gt' => $schema->string()
                ->description('Filter vibration greater than this value'),
            'vibration_lt' => $schema->string()
                ->description('Filter vibration less than this value'),
            'vibration' => $schema->string()
                ->description('Filter vibration equal to this value'),
            'flow_gt' => $schema->string()
                ->description('Filter flow greater than this value'),
            'flow_lt' => $schema->string()
                ->description('Filter flow less than this value'),
            'flow' => $schema->string()
                ->description('Filter flow equal to this value'),
            'pressure_gt' => $schema->string()
                ->description('Filter pressure greater than this value'),
            'pressure_lt' => $schema->string()
                ->description('Filter pressure less than this value'),
            'pressure' => $schema->string()
                ->description('Filter pressure equal to this value'),
            'from_date' => $schema->string()
                ->description('Filter readings from this date. Format: YYYY-MM-DD HH:MM:SS. Example: 2025-12-05 07:30:00 for last 24 hours from now.'),
            'to_date' => $schema->string()
                ->description('Filter readings to this date. Format: YYYY-MM-DD HH:MM:SS. Example: 2025-12-06 07:30:00 for now. Leave empty to get all records from from_date until now.'),
            'order_by' => $schema->string()
                ->enum(['created_at', 'flow', 'temperature', 'pressure', 'vibration'])
                ->description('Field to order by (default: created_at)'),
            'order_direction' => $schema->string()
                ->enum(['asc', 'desc'])
                ->description('Order direction (default: desc)'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(50)
                ->description('Maximum number of readings to return (default: 20, max: 50)'),
        ];
    }
}
