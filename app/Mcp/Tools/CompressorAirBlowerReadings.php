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
                'created_at'
            ])->limit($limit)->get();

            if ($readings->isEmpty()) {
                return Response::text('No readings found matching the criteria.');
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
            ];

            // Build compact response
            $output = "Compressor Air Blower Readings\n";
            $output .= str_repeat('=', 50) . "\n\n";

            $output .= "Statistics:\n";
            $output .= "  Total Readings: {$stats['count']}\n";
            $output .= "  Average Flow: {$stats['avg_flow']}\n";
            $output .= "  Average Temperature: {$stats['avg_temperature']}°C\n";
            $output .= "  Average Pressure: {$stats['avg_pressure']} PSI\n";
            $output .= "  Average Vibration: {$stats['avg_vibration']}\n";
            $output .= "  Max Temperature: {$stats['max_temperature']}°C\n";
            $output .= "  Max Pressure: {$stats['max_pressure']} PSI\n";
            $output .= "  Max Vibration: {$stats['max_vibration']}\n\n";

            $output .= "Recent Readings:\n";
            $output .= str_repeat('-', 50) . "\n";

            foreach ($readings as $reading) {
                $output .= "ID: {$reading->id} | Time: {$reading->created_at->format('Y-m-d H:i:s')}\n";
                $output .= "  Flow: {$reading->flow} | Temp: {$reading->temperature}°C\n";
                $output .= "  Pressure: {$reading->pressure} PSI | Vibration: {$reading->vibration}\n";
                $output .= "  Status: {$reading->status}\n";
                $output .= str_repeat('-', 50) . "\n";
            }

            return Response::text($output);
        } catch (\Exception $e) {
            return Response::text("Error retrieving readings: {$e->getMessage()}");
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
            'from_date' => $schema->string()
                ->description('Filter readings from this date (YYYY-MM-DD HH:MM:SS)'),
            'to_date' => $schema->string()
                ->description('Filter readings to this date (YYYY-MM-DD HH:MM:SS)'),
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
