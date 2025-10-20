<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AnalyzeSsoLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sso:analyze-logs
                            {--from= : Start date (Y-m-d format)}
                            {--to= : End date (Y-m-d format)}
                            {--category= : Log category to analyze (auth_flow, token_management, security_events, performance_metrics)}
                            {--format=table : Output format (table, json, csv)}
                            {--output= : Output file path}
                            {--detailed : Show detailed analysis}';

    /**
     * The console command description.
     */
    protected $description = 'Analyze SSO logs with detailed insights for development and diagnosis';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Analyzing SSO Logs...');
        $this->newLine();

        $from = $this->option('from') ? Carbon::parse($this->option('from')) : Carbon::now()->subDays(7);
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : Carbon::now();
        $category = $this->option('category');
        $format = $this->option('format');
        $detailed = $this->option('detailed');

        $this->info("📅 Date Range: {$from->format('Y-m-d')} to {$to->format('Y-m-d')}");
        if ($category) {
            $this->info("🏷️  Category: {$category}");
        }
        $this->newLine();

        $logData = $this->collectLogData($from, $to, $category);

        if (empty($logData)) {
            $this->warn('No SSO log entries found for the specified criteria.');
            return Command::SUCCESS;
        }

        $analysis = $this->performAnalysis($logData, $detailed);

        $this->displayAnalysis($analysis, $format);

        if ($this->option('output')) {
            $this->exportAnalysis($analysis, $this->option('output'), $format);
        }

        return Command::SUCCESS;
    }

    /**
     * Collect log data from Laravel log files
     */
    private function collectLogData(Carbon $from, Carbon $to, ?string $category): array
    {
        $logData = [];
        $logPath = storage_path('logs');

        // Get log files within date range
        $logFiles = collect(File::files($logPath))
            ->filter(function ($file) {
                return Str::endsWith($file->getFilename(), '.log');
            })
            ->filter(function ($file) use ($from, $to) {
                $filename = $file->getFilename();

                // Extract date from filename (laravel-YYYY-MM-DD.log)
                if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $filename, $matches)) {
                    $fileDate = Carbon::parse($matches[1]);
                    return $fileDate->between($from, $to);
                }

                // For laravel.log (current file), check modification time
                if ($filename === 'laravel.log') {
                    $modTime = Carbon::createFromTimestamp($file->getMTime());
                    return $modTime->between($from, $to);
                }

                return false;
            });

        $this->info("📁 Analyzing {$logFiles->count()} log file(s)...");

        foreach ($logFiles as $file) {
            $this->line("   Processing: {$file->getFilename()}");
            $content = File::get($file->getPathname());
            $entries = $this->parseLogEntries($content, $from, $to, $category);
            $logData = array_merge($logData, $entries);
        }

        return $logData;
    }

    /**
     * Parse log entries from log content
     */
    private function parseLogEntries(string $content, Carbon $from, Carbon $to, ?string $category): array
    {
        $entries = [];
        $lines = explode("\n", $content);
        $currentEntry = null;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Check if line starts with timestamp (new log entry)
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                // Save previous entry
                if ($currentEntry && $this->isValidSsoEntry($currentEntry, $from, $to, $category)) {
                    $entries[] = $currentEntry;
                }

                // Start new entry
                $timestamp = Carbon::parse($matches[1]);
                $currentEntry = [
                    'timestamp' => $timestamp,
                    'raw_line' => $line,
                    'full_content' => $line,
                ];
            } elseif ($currentEntry) {
                // Continuation of previous entry
                $currentEntry['full_content'] .= "\n" . $line;
            }
        }

        // Don't forget the last entry
        if ($currentEntry && $this->isValidSsoEntry($currentEntry, $from, $to, $category)) {
            $entries[] = $currentEntry;
        }

        return $entries;
    }

    /**
     * Check if log entry is a valid SSO entry
     */
    private function isValidSsoEntry(array $entry, Carbon $from, Carbon $to, ?string $category): bool
    {
        // Check timestamp range
        if (!$entry['timestamp']->between($from, $to)) {
            return false;
        }

        // Check if it's an SSO log entry
        if (!Str::contains($entry['full_content'], '[SSO:')) {
            return false;
        }

        // Check category filter
        if ($category && !Str::contains(strtoupper($entry['full_content']), strtoupper($category))) {
            return false;
        }

        return true;
    }

    /**
     * Perform analysis on collected log data
     */
    private function performAnalysis(array $logData, bool $detailed): array
    {
        $analysis = [
            'summary' => [
                'total_entries' => count($logData),
                'date_range' => [
                    'from' => min(array_column($logData, 'timestamp'))->format('Y-m-d H:i:s'),
                    'to' => max(array_column($logData, 'timestamp'))->format('Y-m-d H:i:s'),
                ],
            ],
            'categories' => [],
            'events' => [],
            'performance' => [],
            'security' => [],
            'errors' => [],
        ];

        foreach ($logData as $entry) {
            $this->analyzeEntry($entry, $analysis, $detailed);
        }

        // Calculate additional metrics
        $this->calculateMetrics($analysis);

        return $analysis;
    }

    /**
     * Analyze individual log entry
     */
    private function analyzeEntry(array $entry, array &$analysis, bool $detailed): void
    {
        $content = $entry['full_content'];

        // Extract category
        if (preg_match('/\[SSO:([^\]]+)\]/', $content, $matches)) {
            $category = strtolower($matches[1]);
            $analysis['categories'][$category] = ($analysis['categories'][$category] ?? 0) + 1;
        }

        // Extract event type
        if (preg_match('/\[SSO:[^\]]+\]\s+([^\s]+)/', $content, $matches)) {
            $event = $matches[1];
            $analysis['events'][$event] = ($analysis['events'][$event] ?? 0) + 1;
        }

        // Performance analysis
        if (Str::contains($content, 'duration_ms')) {
            if (preg_match('/"duration_ms":(\d+(?:\.\d+)?)/', $content, $matches)) {
                $duration = floatval($matches[1]);
                $analysis['performance']['durations'][] = $duration;

                if ($duration > 2000) {
                    $analysis['performance']['slow_operations'][] = [
                        'timestamp' => $entry['timestamp']->format('Y-m-d H:i:s'),
                        'duration' => $duration,
                        'content' => $content,
                    ];
                }
            }
        }

        // Security analysis
        if (Str::contains($content, 'SECURITY') || Str::contains($content, '.ERROR')) {
            $analysis['security']['events'][] = [
                'timestamp' => $entry['timestamp']->format('Y-m-d H:i:s'),
                'content' => $content,
            ];
        }

        // Error analysis
        if (Str::contains($content, '.ERROR') || Str::contains($content, 'exception')) {
            $analysis['errors']['entries'][] = [
                'timestamp' => $entry['timestamp']->format('Y-m-d H:i:s'),
                'content' => $content,
            ];
        }
    }

    /**
     * Calculate additional metrics
     */
    private function calculateMetrics(array &$analysis): void
    {
        // Performance metrics
        if (!empty($analysis['performance']['durations'])) {
            $durations = $analysis['performance']['durations'];
            $analysis['performance']['metrics'] = [
                'avg_duration' => round(array_sum($durations) / count($durations), 2),
                'min_duration' => min($durations),
                'max_duration' => max($durations),
                'median_duration' => $this->calculateMedian($durations),
                'slow_operations_count' => count($analysis['performance']['slow_operations'] ?? []),
            ];
        }

        // Category percentages
        $total = $analysis['summary']['total_entries'];
        if ($total > 0) {
            foreach ($analysis['categories'] as $category => $count) {
                $analysis['categories'][$category] = [
                    'count' => $count,
                    'percentage' => round(($count / $total) * 100, 2),
                ];
            }
        }

        // Error rate
        $errorCount = count($analysis['errors']['entries'] ?? []);
        $analysis['errors']['error_rate'] = $total > 0 ? round(($errorCount / $total) * 100, 2) : 0;

        // Security events count
        $analysis['security']['total_events'] = count($analysis['security']['events'] ?? []);
    }

    /**
     * Calculate median value
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        }

        return $values[intval($count / 2)];
    }

    /**
     * Display analysis results
     */
    private function displayAnalysis(array $analysis, string $format): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($analysis, JSON_PRETTY_PRINT));
                break;

            case 'csv':
                $this->displayCsvAnalysis($analysis);
                break;

            default:
                $this->displayTableAnalysis($analysis);
        }
    }

    /**
     * Display analysis in table format
     */
    private function displayTableAnalysis(array $analysis): void
    {
        // Summary
        $this->info('📊 SUMMARY');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Entries', $analysis['summary']['total_entries']],
                ['Date Range', $analysis['summary']['date_range']['from'] . ' to ' . $analysis['summary']['date_range']['to']],
                ['Error Rate', ($analysis['errors']['error_rate'] ?? 0) . '%'],
            ]
        );

        // Categories
        if (!empty($analysis['categories'])) {
            $this->newLine();
            $this->info('📂 LOG CATEGORIES');
            $categoryData = [];
            foreach ($analysis['categories'] as $category => $data) {
                $categoryData[] = [
                    ucfirst(str_replace('_', ' ', $category)),
                    is_array($data) ? $data['count'] : $data,
                    is_array($data) ? $data['percentage'] . '%' : 'N/A',
                ];
            }
            $this->table(['Category', 'Count', 'Percentage'], $categoryData);
        }

        // Performance
        if (!empty($analysis['performance']['metrics'])) {
            $this->newLine();
            $this->info('⚡ PERFORMANCE METRICS');
            $metrics = $analysis['performance']['metrics'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Average Duration', $metrics['avg_duration'] . ' ms'],
                    ['Min Duration', $metrics['min_duration'] . ' ms'],
                    ['Max Duration', $metrics['max_duration'] . ' ms'],
                    ['Median Duration', $metrics['median_duration'] . ' ms'],
                    ['Slow Operations', $metrics['slow_operations_count']],
                ]
            );
        }

        // Security
        if ($analysis['security']['total_events'] > 0) {
            $this->newLine();
            $this->warn('🔐 SECURITY EVENTS: ' . $analysis['security']['total_events']);
            if (!empty($analysis['security']['events'])) {
                $securityData = array_slice($analysis['security']['events'], 0, 5); // Show first 5
                $this->table(
                    ['Timestamp', 'Event'],
                    array_map(function ($event) {
                        return [$event['timestamp'], Str::limit($event['content'], 100)];
                    }, $securityData)
                );

                if (count($analysis['security']['events']) > 5) {
                    $this->line('... and ' . (count($analysis['security']['events']) - 5) . ' more security events');
                }
            }
        }

        // Errors
        if (!empty($analysis['errors']['entries'])) {
            $this->newLine();
            $this->error('❌ RECENT ERRORS');
            $errorData = array_slice($analysis['errors']['entries'], -5); // Show last 5
            $this->table(
                ['Timestamp', 'Error'],
                array_map(function ($error) {
                    return [$error['timestamp'], Str::limit($error['content'], 100)];
                }, $errorData)
            );
        }
    }

    /**
     * Display analysis in CSV format
     */
    private function displayCsvAnalysis(array $analysis): void
    {
        $this->line('timestamp,category,event,type');
        foreach ($analysis['categories'] as $category => $data) {
            $count = is_array($data) ? $data['count'] : $data;
            $this->line("summary,{$category},category_count,{$count}");
        }
    }

    /**
     * Export analysis to file
     */
    private function exportAnalysis(array $analysis, string $outputPath, string $format): void
    {
        $content = '';

        switch ($format) {
            case 'json':
                $content = json_encode($analysis, JSON_PRETTY_PRINT);
                break;

            case 'csv':
                // Generate CSV content
                $content = "timestamp,category,metric,value\n";
                foreach ($analysis['categories'] as $category => $data) {
                    $count = is_array($data) ? $data['count'] : $data;
                    $content .= "summary,{$category},count,{$count}\n";
                }
                break;

            default:
                $content = "SSO Log Analysis Report\n";
                $content .= "Generated: " . Carbon::now()->format('Y-m-d H:i:s') . "\n\n";
                $content .= "Total Entries: " . $analysis['summary']['total_entries'] . "\n";
                $content .= "Error Rate: " . ($analysis['errors']['error_rate'] ?? 0) . "%\n\n";
        }

        File::put($outputPath, $content);
        $this->info("📁 Analysis exported to: {$outputPath}");
    }
}
