<?php

namespace ArnaldoTomo\LaravelAutoSchema\Commands;

use ArnaldoTomo\LaravelAutoSchema\ModelAnalyzer;
use ArnaldoTomo\LaravelAutoSchema\TypeGenerator;
use ArnaldoTomo\LaravelAutoSchema\SchemaBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class GenerateTypesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schema:generate 
                            {--model=* : Specific models to generate types for}
                            {--force : Force overwrite existing files}
                            {--watch : Watch for changes and regenerate automatically}
                            {--dry-run : Show what would be generated without writing files}';

    /**
     * The console command description.
     */
    protected $description = 'Generate TypeScript types from Laravel models';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Laravel AutoSchema - TypeScript Generator');
        $this->newLine();

        try {
            // Create analyzers manually to avoid dependency injection issues
            $analyzer = new ModelAnalyzer();
            $schemaBuilder = new SchemaBuilder();
            $generator = new TypeGenerator($schemaBuilder);

            $models = $this->discoverModels();
            
            if (empty($models)) {
                $this->error('❌ No models found to generate types for.');
                return self::FAILURE;
            }

            $this->info("📋 Found " . count($models) . " model(s) to analyze:");
            foreach ($models as $model) {
                $this->line("   • " . class_basename($model));
            }
            $this->newLine();

            if ($this->option('dry-run')) {
                return $this->dryRun($models, $analyzer);
            }

            return $this->generateTypes($models, $analyzer, $generator);

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
    }

    /**
     * Discover models to analyze.
     */
    private function discoverModels(): array
    {
        $specificModels = $this->option('model');
        
        if (!empty($specificModels)) {
            return $this->resolveSpecificModels($specificModels);
        }

        return $this->discoverAllModels();
    }

    /**
     * Resolve specific models from command options.
     */
    private function resolveSpecificModels(array $modelNames): array
    {
        $models = [];
        
        foreach ($modelNames as $modelName) {
            $modelClass = $this->resolveModelClass($modelName);
            
            if ($modelClass) {
                $models[] = $modelClass;
            } else {
                $this->warn("⚠️  Model '{$modelName}' not found.");
            }
        }
        
        return $models;
    }

    /**
     * Resolve model class from name.
     */
    private function resolveModelClass(string $modelName): ?string
    {
        // Try different namespace combinations
        $possibleClasses = [
            "App\\Models\\{$modelName}",
            "App\\{$modelName}",
            $modelName,
        ];
        
        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }
        
        return null;
    }

/**
 * Discover all models in the application.
 */
private function discoverAllModels(): array
{
    $models = [];
    
    // Get configuration with fallbacks
    $config = config('autoscema', []);
    $directories = $config['models']['directories'] ?? [app_path('Models')];
    $baseModel = $config['models']['base_model'] ?? 'Illuminate\\Database\\Eloquent\\Model';
    $exclude = $config['models']['exclude'] ?? [];

    $this->info("🔍 Searching for models in directories:");
    foreach ($directories as $directory) {
        $this->line("   • {$directory}");
    }
    $this->newLine();

    foreach ($directories as $directory) {
        if (!is_dir($directory)) {
            $this->warn("⚠️  Directory does not exist: {$directory}");
            continue;
        }

        $finder = new Finder();
        $finder->files()->name('*.php')->in($directory);

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $class = $this->getClassFromFile($filePath);
            
            if (!$class) {
                continue;
            }

            // Debug information
            $this->info("📄 Found file: " . $file->getRelativePathname());
            $this->line("   Class: {$class}");
            
            // Check if class exists
            if (!class_exists($class)) {
                $this->warn("   ⚠️  Class does not exist: {$class}");
                continue;
            }
            
            // Check if it's a Model
            if (!is_subclass_of($class, $baseModel)) {
                $this->warn("   ⚠️  Not a Model: {$class}");
                continue;
            }
            
            // Check if it's excluded
            if (in_array($class, $exclude) || in_array(class_basename($class), $exclude)) {
                $this->info("   ⏭️  Excluded: {$class}");
                continue;
            }
            
            $models[] = $class;
            $this->info("   ✅ Added: {$class}");
        }
    }

    return $models;
}

/**
 * Get class name from file - Improved version.
 */
private function getClassFromFile(string $filePath): ?string
{
    $namespace = null;
    $className = null;
    
    try {
        $contents = File::get($filePath);
        $tokens = token_get_all($contents);
        
        $namespaceTokens = [];
        $classFound = false;
        
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            
            // Handle namespace
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespaceTokens = [];
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    $nextToken = $tokens[$j];
                    
                    if (is_array($nextToken) && ($nextToken[0] === T_STRING || $nextToken[0] === T_NS_SEPARATOR)) {
                        $namespaceTokens[] = $nextToken[1];
                    } elseif ($nextToken === '{' || $nextToken === ';') {
                        break;
                    }
                }
                $namespace = implode('', $namespaceTokens);
            }
            
            // Handle class
            if (is_array($token) && $token[0] === T_CLASS) {
                // Look for the class name
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    $nextToken = $tokens[$j];
                    
                    if (is_array($nextToken) && $nextToken[0] === T_STRING) {
                        $className = $nextToken[1];
                        $classFound = true;
                        break;
                    }
                }
                
                if ($classFound) {
                    break;
                }
            }
        }
        
        if ($className) {
            return $namespace ? $namespace . '\\' . $className : $className;
        }
        
    } catch (\Exception $e) {
        // Ignore files that can't be parsed
    }
    
    return null;
}

    /**
     * Get class name from file.
     */
    private function getClassFromFile(string $filePath): ?string
    {
        $namespace = null;
        $className = null;
        
        $contents = File::get($filePath);
        $tokens = token_get_all($contents);
        
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NS_SEPARATOR) {
                        $namespace .= $tokens[$j][1];
                    } else if ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
            }
            
            if ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $className = $tokens[$j][1];
                        break;
                    }
                }
                break;
            }
        }
        
        return $className ? ($namespace ? $namespace . '\\' . $className : $className) : null;
    }

    /**
     * Perform dry run.
     */
    private function dryRun(array $models, ModelAnalyzer $analyzer): int
    {
        $this->info("🔍 Dry run mode - analyzing models:");
        $this->newLine();

        $outputPath = config('autoscema.output.path', resource_path('js/types'));
        $this->info("📁 Output directory: {$outputPath}");
        $this->newLine();

        foreach ($models as $modelClass) {
            $this->info("🔍 Analyzing: " . class_basename($modelClass));
            
            try {
                $modelData = $analyzer->analyze($modelClass);
                
                $this->line("   Table: {$modelData['table']}");
                $this->line("   Properties: " . count($modelData['properties']));
                $this->line("   Relationships: " . count($modelData['relationships']));
                
                if (!empty($modelData['relationships'])) {
                    foreach ($modelData['relationships'] as $relationship) {
                        $this->line("     • {$relationship['name']} ({$relationship['type']})");
                    }
                }
                
                $this->newLine();
                
            } catch (\Exception $e) {
                $this->error("   ❌ Error analyzing {$modelClass}: " . $e->getMessage());
            }
        }

        $this->info("✅ Dry run completed. Use --force to generate files.");
        return self::SUCCESS;
    }

    /**
     * Generate types for models.
     */
    private function generateTypes(array $models, ModelAnalyzer $analyzer, TypeGenerator $generator): int
    {
        $this->info("🔄 Analyzing models and generating types...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar(count($models));
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $modelData = [];
        $errors = [];

        foreach ($models as $modelClass) {
            try {
                $progressBar->setMessage("Analyzing " . class_basename($modelClass));
                $analyzed = $analyzer->analyze($modelClass);
                $modelData[] = $analyzed;
                
                $progressBar->advance();
                
            } catch (\Exception $e) {
                $errors[] = "Error analyzing {$modelClass}: " . $e->getMessage();
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        if (!empty($errors)) {
            $this->error("❌ Errors occurred during analysis:");
            foreach ($errors as $error) {
                $this->error("   • {$error}");
            }
            $this->newLine();
        }

        if (empty($modelData)) {
            $this->error("❌ No models could be analyzed successfully.");
            return self::FAILURE;
        }

        $this->info("📝 Generating TypeScript files...");
        
        try {
            $generator->generateAll($modelData);
            
            $outputPath = config('autoscema.output.path', resource_path('js/types'));
            $this->info("✅ TypeScript types generated successfully!");
            $this->info("📁 Files created in: {$outputPath}");
            $this->newLine();
            
            $this->displayGeneratedFiles($outputPath);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Error generating TypeScript files: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Display generated files.
     */
    private function displayGeneratedFiles(string $outputPath): void
    {
        if (!is_dir($outputPath)) {
            return;
        }

        $files = File::files($outputPath);
        
        if (empty($files)) {
            return;
        }

        $this->info("📄 Generated files:");
        foreach ($files as $file) {
            $relativePath = str_replace(base_path() . '/', '', $file->getRealPath());
            $size = $this->formatFileSize($file->getSize());
            $this->line("   • {$relativePath} ({$size})");
        }
        
        $this->newLine();
        $this->info("🎉 Ready to use in your frontend application!");
        $this->info("💡 Import types: import { User, Post } from './types'");
    }

    /**
     * Format file size.
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}