<?php

namespace ArnaldoTomo\LaravelAutoSchema;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class TypeGenerator
{
    private array $config;
    private SchemaBuilder $schemaBuilder;

    public function __construct(SchemaBuilder $schemaBuilder)
    {
        $this->config = config('autoscema');
        $this->schemaBuilder = $schemaBuilder;
    }

    /**
     * Generate TypeScript types for all models.
     */
    public function generateAll(array $models): void
    {
        $this->ensureOutputDirectory();
        
        // Generate individual model types
        foreach ($models as $modelData) {
            $this->generateModelType($modelData);
        }
        
        // Generate index file
        $this->generateIndexFile($models);
        
        // Generate API client if enabled
        if ($this->config['api']['generate_client']) {
            $this->generateApiClient($models);
        }
        
        // Generate validation schemas if enabled
        if ($this->config['validation']['enabled']) {
            $this->generateValidationSchemas($models);
        }
    }

    /**
     * Generate TypeScript type for a single model.
     */
    public function generateModelType(array $modelData): void
    {
        $content = $this->buildModelInterface($modelData);
        $filename = $this->getModelFilename($modelData['name']);
        $path = $this->getOutputPath($filename);
        
        File::put($path, $content);
    }

    /**
     * Build TypeScript interface for a model.
     */
    private function buildModelInterface(array $modelData): string
    {
        $interfaceName = $modelData['name'];
        $imports = $this->buildImports($modelData);
        $properties = $this->buildProperties($modelData);
        $relationships = $this->buildRelationships($modelData);
        $timestamp = $this->config['advanced']['add_timestamps'] ? $this->getTimestamp() : '';
        
        return trim("
{$imports}
{$timestamp}
/**
 * {$interfaceName} model interface
 * Generated from: {$modelData['class']}
 * Table: {$modelData['table']}
 */
export interface {$interfaceName} {
{$properties}
{$relationships}
}

{$this->buildTypeAlias($modelData)}
{$this->buildEnums($modelData)}
        ");
    }

    /**
     * Build imports for the interface.
     */
    private function buildImports(array $modelData): string
    {
        $imports = [];
        
        // Import related models
        foreach ($modelData['relationships'] as $relationship) {
            $relatedModel = class_basename($relationship['related']);
            if ($relatedModel !== $modelData['name']) {
                $imports[] = $relatedModel;
            }
        }
        
        if (empty($imports)) {
            return '';
        }
        
        $importList = implode(', ', array_unique($imports));
        return "import type { {$importList} } from './index';";
    }

    /**
     * Build properties for the interface.
     */
    private function buildProperties(array $modelData): string
    {
        $properties = [];
        
        foreach ($modelData['properties'] as $property) {
            $name = $property['name'];
            $type = $this->buildPropertyType($property, $modelData);
            $comment = $property['comment'] ? "  /** {$property['comment']} */" : '';
            
            if ($comment) {
                $properties[] = $comment;
            }
            
            $properties[] = "  {$name}: {$type};";
        }
        
        // Add appends (accessors)
        foreach ($modelData['appends'] as $append) {
            if (isset($modelData['accessors'][$append])) {
                $accessor = $modelData['accessors'][$append];
                $properties[] = "  {$append}: {$accessor['type']}; // Accessor";
            }
        }
        
        return implode("\n", $properties);
    }

    /**
     * Build property type with nullability.
     */
    private function buildPropertyType(array $property, array $modelData): string
    {
        $type = $property['type'];
        
        // Handle casts
        if (isset($modelData['casts'][$property['name']])) {
            $cast = $modelData['casts'][$property['name']];
            $type = $this->mapCastToTypeScript($cast);
        }
        
        // Handle nullability
        if ($property['nullable']) {
            if ($this->config['types']['nullable_union']) {
                $type .= ' | null';
            } else {
                $type .= '?';
            }
        }
        
        return $type;
    }

    /**
     * Build relationships for the interface.
     */
    private function buildRelationships(array $modelData): string
    {
        if (!$this->config['models']['include_relationships']) {
            return '';
        }
        
        $relationships = [];
        
        foreach ($modelData['relationships'] as $relationship) {
            $name = $relationship['name'];
            $relatedModel = class_basename($relationship['related']);
            $type = $this->buildRelationshipType($relationship, $relatedModel);
            
            $relationships[] = "  // Relationship: {$relationship['type']}";
            $relationships[] = "  {$name}?: {$type};";
        }
        
        return empty($relationships) ? '' : "\n  // Relationships\n" . implode("\n", $relationships);
    }

    /**
     * Build relationship type.
     */
    private function buildRelationshipType(array $relationship, string $relatedModel): string
    {
        $type = $relationship['type'];
        
        return match ($type) {
            'HasOne', 'BelongsTo', 'MorphTo', 'MorphOne' => $relatedModel,
            'HasMany', 'BelongsToMany', 'MorphToMany', 'MorphMany', 'HasManyThrough' => "{$relatedModel}[]",
            default => $relatedModel,
        };
    }

    /**
     * Build type alias.
     */
    private function buildTypeAlias(array $modelData): string
    {
        if (!$this->config['types']['generate_types']) {
            return '';
        }
        
        $name = $modelData['name'];
        return "\nexport type {$name}Type = {$name};";
    }

    /**
     * Build enums for the model.
     */
    private function buildEnums(array $modelData): string
    {
        if (!$this->config['types']['generate_enums']) {
            return '';
        }
        
        $enums = [];
        
        // Generate enums from casts
        foreach ($modelData['casts'] as $field => $cast) {
            if (str_contains($cast, 'enum:')) {
                $enumValues = explode(',', str_replace('enum:', '', $cast));
                $enumName = Str::studly($modelData['name'] . '_' . $field);
                
                $values = array_map(fn($value) => "  {$value} = '{$value}'", $enumValues);
                $enums[] = "\nexport enum {$enumName} {\n" . implode(",\n", $values) . "\n}";
            }
        }
        
        return implode("\n", $enums);
    }

    /**
     * Generate index file.
     */
    private function generateIndexFile(array $models): void
    {
        $exports = [];
        
        foreach ($models as $modelData) {
            $name = $modelData['name'];
            $filename = $this->getModelFilename($name, false);
            $exports[] = "export type { {$name} } from './{$filename}';";
            
            if ($this->config['types']['generate_types']) {
                $exports[] = "export type { {$name}Type } from './{$filename}';";
            }
        }
        
        $timestamp = $this->config['advanced']['add_timestamps'] ? $this->getTimestamp() : '';
        $content = "{$timestamp}\n" . implode("\n", $exports);
        
        File::put($this->getOutputPath('index.ts'), $content);
    }

    /**
     * Generate API client.
     */
    private function generateApiClient(array $models): void
    {
        $content = $this->schemaBuilder->buildApiClient($models);
        File::put($this->getOutputPath('api-client.ts'), $content);
    }

    /**
     * Generate validation schemas.
     */
    private function generateValidationSchemas(array $models): void
    {
        $content = $this->schemaBuilder->buildValidationSchemas($models);
        File::put($this->getOutputPath('validation-schemas.ts'), $content);
    }

    /**
     * Map Laravel cast to TypeScript type.
     */
    private function mapCastToTypeScript(string $cast): string
    {
        return match ($cast) {
            'int', 'integer' => 'number',
            'float', 'double', 'decimal' => 'number',
            'bool', 'boolean' => 'boolean',
            'string' => 'string',
            'array' => 'Array<any>',
            'object', 'json' => 'Record<string, any>',
            'collection' => 'Array<any>',
            'date', 'datetime', 'timestamp' => 'Date',
            default => str_contains($cast, 'enum:') ? 'string' : 'any',
        };
    }

    /**
     * Get model filename.
     */
    private function getModelFilename(string $modelName, bool $withExtension = true): string
    {
        $case = $this->config['output']['filename_case'];
        
        $filename = match ($case) {
            'pascal' => Str::studly($modelName),
            'camel' => Str::camel($modelName),
            'snake' => Str::snake($modelName),
            'kebab' => Str::kebab($modelName),
            default => $modelName,
        };
        
        return $withExtension ? $filename . '.ts' : $filename;
    }

    /**
     * Get output path.
     */
    private function getOutputPath(string $filename): string
    {
        return $this->config['output']['path'] . '/' . $filename;
    }

    /**
     * Ensure output directory exists.
     */
    private function ensureOutputDirectory(): void
    {
        $outputPath = $this->config['output']['path'];
        
        if (!File::isDirectory($outputPath)) {
            File::makeDirectory($outputPath, 0755, true);
        }
    }

    /**
     * Get timestamp comment.
     */
    private function getTimestamp(): string
    {
        return "// Generated at " . now()->toDateTimeString() . " by Laravel AutoSchema\n";
    }
}