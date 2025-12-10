<?php

namespace App\Console\Commands;

use App\Models\CompressorAirBlower;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorMLProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compressor:monitor {--live : Show live log updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor ML analysis process and show statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('live')) {
            return $this->showLiveLogs();
        }

        return $this->showStatistics();
    }

    /**
     * Show real-time statistics
     */
    protected function showStatistics(): int
    {
        $this->info('=== ML Analysis Statistics ===');
        $this->newLine();

        $total = CompressorAirBlower::count();
        $statusCounts = CompressorAirBlower::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->table(
            ['Status', 'Count', 'Percentage'],
            collect($statusCounts)->map(function ($count, $status) use ($total) {
                return [
                    ucfirst($status),
                    $count,
                    round(($count / $total) * 100, 2).'%',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info("Total Records: {$total}");

        // Show recent records
        $this->newLine();
        $this->info('=== Latest 5 Records ===');
        $recent = CompressorAirBlower::latest()->limit(5)->get();

        $this->table(
            ['ID', 'Status', 'Vibration', 'Created'],
            $recent->map(fn ($r) => [
                $r->id,
                $r->status,
                $r->vibration,
                $r->created_at->diffForHumans(),
            ])->toArray()
        );

        // Show pending queue count
        $pendingJobs = DB::table('jobs')->count();
        if ($pendingJobs > 0) {
            $this->warn("⚠ {$pendingJobs} job(s) in queue waiting to be processed");
            $this->info('Run: php artisan queue:work');
        } else {
            $this->info('✓ Queue is empty');
        }

        return self::SUCCESS;
    }

    /**
     * Show live log monitoring
     */
    protected function showLiveLogs(): int
    {
        $this->info('=== Monitoring ML Process (Press Ctrl+C to stop) ===');
        $this->newLine();

        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile)) {
            $this->error("Log file not found: {$logFile}");

            return self::FAILURE;
        }

        $this->info("Tailing: {$logFile}");
        $this->newLine();

        // Use tail -f to follow the log
        $handle = popen("tail -f {$logFile} | grep --line-buffered -E '\[Compressor Data\]|\[ML Analysis\]|\[Status Update\]'", 'r');

        if ($handle) {
            while (! feof($handle)) {
                $line = fgets($handle);
                if ($line) {
                    // Color code the output
                    if (str_contains($line, '[Compressor Data]')) {
                        $this->line("<fg=cyan>{$line}</>");
                    } elseif (str_contains($line, '[ML Analysis]')) {
                        $this->line("<fg=yellow>{$line}</>");
                    } elseif (str_contains($line, '[Status Update]')) {
                        $this->line("<fg=green>{$line}</>");
                    }
                }
            }
            pclose($handle);
        }

        return self::SUCCESS;
    }
}
