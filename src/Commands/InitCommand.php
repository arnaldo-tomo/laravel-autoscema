<?php

namespace ArnaldoTomo\LaravelAutoSchema\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InitCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schema:init 
                            {--force : Force overwrite existing configuration}';

    /**
     * The console command description.
     */
    protected $description = 'Initialize Laravel AutoSchema configuration and setup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Laravel AutoSchema - Initialization');
        $this->newLine();

        $this->publishConfiguration();
        $this->createOutputDirectory();
        $this->createGitignore();
        $this->showNextSteps();

        return self::SUCCESS;
    }

    /**
     * Publish configuration file.
     */
    private function publishConfiguration(): void
    {
        $configPath = config_path('autoscema.php');
        $force = $this->option('force');

        if (File::exists($configPath) && !$force) {
            if ($this->confirm('Configuration file already exists. Do you want to overwrite it?')) {
                $force = true;
            }
        }

        if (!File::exists($configPath) || $force) {
            $this->call('vendor:publish', [
                '--provider' => 'ArnaldoTomo\\LaravelAutoSchema\\AutoSchemaServiceProvider',
                '--tag' => 'autoscema-config',
                '--force' => $force
            ]);
            
            $this->info("✅ Configuration file published to config/autoscema.php");
        } else {
            $this->info("⏭️  Configuration file already exists, skipping");
        }
    }

    /**
     * Create output directory.
     */
    private function createOutputDirectory(): void
    {
        $outputPath = config('autoscema.output.path', resource_path('js/types'));
        
        if (!File::isDirectory($outputPath)) {
            File::makeDirectory($outputPath, 0755, true);
            $this->info("✅ Created output directory: {$outputPath}");
        } else {
            $this->info("⏭️  Output directory already exists: {$outputPath}");
        }
    }

    /**
     * Create .gitignore for generated files.
     */
    private function createGitignore(): void
    {
        $outputPath = config('autoscema.output.path', resource_path('js/types'));
        $gitignorePath = $outputPath . '/.gitignore';
        
        if (!File::exists($gitignorePath)) {
            $gitignoreContent = $this->getGitignoreContent();
            File::put($gitignorePath, $gitignoreContent);
            $this->info("✅ Created .gitignore for generated files");
        } else {
            $this->info("⏭️  .gitignore already exists");
        }
    }

    /**
     * Get .gitignore content.
     */
    private function getGitignoreContent(): string
    {
        return "# Laravel AutoSchema Generated Files
# These files are automatically generated, do not edit manually

# Generated TypeScript types
*.ts

# Keep the directory
!.gitignore

# Exception: You may want to commit these files if you prefer
# In that case, comment out the *.ts line above and uncomment below:
# !index.ts
# !api-client.ts
# !validation-schemas.ts
";
    }

    /**
     * Show next steps.
     */
    private function showNextSteps(): void
    {
        $this->newLine();
        $this->info("🎉 Laravel AutoSchema initialized successfully!");
        $this->newLine();
        
        $this->info("📋 Next steps:");
        $this->line("   1. Review configuration in config/autoscema.php");
        $this->line("   2. Run 'php artisan schema:generate' to generate types");
        $this->line("   3. Use 'php artisan schema:watch' for automatic regeneration");
        $this->newLine();
        
        $this->info("💡 Quick commands:");
        $this->line("   • php artisan schema:generate --dry-run  (preview generation)");
        $this->line("   • php artisan schema:generate --model=User  (specific model)");
        $this->line("   • php artisan schema:watch --quiet  (silent watching)");
        $this->newLine();
        
        $outputPath = config('autoscema.output.path', resource_path('js/types'));
        $this->info("📁 Types will be generated in: {$outputPath}");
        $this->info("🔗 Import in your frontend: import { User } from './types'");
    }
}