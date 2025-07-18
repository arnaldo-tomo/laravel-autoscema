<?php

namespace ArnaldoTomo\LaravelAutoSchema\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class WatchTypesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schema:watch 
                            {--interval=2 : Check interval in seconds}
                            {--quiet : Suppress output except for changes}';

    /**
     * The console command description.
     */
    protected $description = 'Watch for model changes and regenerate types automatically';

    private array $fileHashes = [];
    private array $watchedDirectories = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('👁️  Laravel AutoSchema - File Watcher');
        $this->info('🔄 Watching for model changes...');
        $this->newLine();

        $this->setupWatchedDirectories();
        $this->initializeFileHashes();

        $interval = max(1, (int) $this->option('interval'));
        $quiet = $this->option('quiet');

        if (!$quiet) {
            $this->info("⏱️  Checking every {$interval} second(s)");
            $this->info("📁 Watching directories:");
            foreach ($this->watchedDirectories as $dir) {
                $this->line("   • {$dir}");
            }
            $this->newLine();
            $this->info("Press Ctrl+C to stop watching");
            $this->newLine();
        }

        $this->watchForChanges($interval, $quiet);

        return self::SUCCESS;
    }

    /**
     * Setup directories to watch.
     */
    private function setupWatchedDirectories(): void
    {
        $this->watchedDirectories = array_filter(
            config('autoscema.models.directories', [app_path('Models')]),
            fn($dir) => is_dir($dir)
        );

        // Also watch migration files
        $migrationsPath = database_path('migrations');
        if (is_dir($migrationsPath)) {
            $this->watchedDirectories[] = $migrationsPath;
        }
    }

    /**
     * Initialize file hashes for change detection.
     */
    private function initializeFileHashes(): void
    {
        $this->fileHashes = [];
        
        foreach ($this->watchedDirectories as $directory) {
            $finder = new Finder();
            $finder->files()->name('*.php')->in($directory);

            foreach ($finder as $file) {
                $this->fileHashes[$file->getRealPath()] = md5_file($file->getRealPath());
            }
        }
    }

    /**
     * Watch for file changes.
     */
    private function watchForChanges(int $interval, bool $quiet): void
    {
        $lastCheck = time();
        
        while (true) {
            sleep($interval);
            
            $changes = $this->detectChanges();
            
            if (!empty($changes)) {
                $this->handleChanges($changes, $quiet);
            } elseif (!$quiet && time() - $lastCheck > 30) {
                $this->info("⏰ Still watching... (" . date('H:i:s') . ")");
                $lastCheck = time();
            }
        }
    }

    /**
     * Detect file changes.
     */
    private function detectChanges(): array
    {
        $changes = [
            'modified' => [],
            'added' => [],
            'deleted' => []
        ];
        
        $currentFiles = [];
        
        foreach ($this->watchedDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            
            $finder = new Finder();
            $finder->files()->name('*.php')->in($directory);

            foreach ($finder as $file) {
                $filePath = $file->getRealPath();
                $currentHash = md5_file($filePath);
                $currentFiles[$filePath] = $currentHash;
                
                if (!isset($this->fileHashes[$filePath])) {
                    // New file
                    $changes['added'][] = $filePath;
                } elseif ($this->fileHashes[$filePath] !== $currentHash) {
                    // Modified file
                    $changes['modified'][] = $filePath;
                }
            }
        }
        
        // Check for deleted files
        foreach ($this->fileHashes as $filePath => $hash) {
            if (!isset($currentFiles[$filePath])) {
                $changes['deleted'][] = $filePath;
            }
        }
        
        // Update file hashes
        $this->fileHashes = $currentFiles;
        
        return array_filter($changes, fn($changeType) => !empty($changeType));
    }

    /**
     * Handle detected changes.
     */
    private function handleChanges(array $changes, bool $quiet): void
    {
        if (!$quiet) {
            $this->newLine();
            $this->info("🔄 Changes detected at " . date('H:i:s'));
            
            foreach ($changes as $type => $files) {
                if (empty($files)) {
                    continue;
                }
                
                $emoji = match ($type) {
                    'added' => '➕',
                    'modified' => '📝',
                    'deleted' => '🗑️',
                    default => '📄'
                };
                
                $this->info("{$emoji} " . ucfirst($type) . " files:");
                foreach ($files as $file) {
                    $relativePath = str_replace(base_path() . '/', '', $file);
                    $this->line("   • {$relativePath}");
                }
            }
            $this->newLine();
        }
        
        // Regenerate types
        $this->regenerateTypes($quiet);
    }

    /**
     * Regenerate types after changes.
     */
    private function regenerateTypes(bool $quiet): void
    {
        try {
            if (!$quiet) {
                $this->info("🔄 Regenerating types...");
            }
            
            $result = $this->call('schema:generate', [
                '--force' => true,
                '--quiet' => $quiet
            ]);
            
            if ($result === self::SUCCESS) {
                $this->info("✅ Types regenerated successfully!");
            } else {
                $this->error("❌ Error regenerating types");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
        
        if (!$quiet) {
            $this->newLine();
            $this->info("👁️  Continuing to watch for changes...");
        }
    }
}