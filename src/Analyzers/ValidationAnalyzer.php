<?php

namespace ArnaldoTomo\LaravelAutoSchema;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ValidationAnalyzer
{
    /**
     * Find and analyze Form Request classes.
     */
    public function analyzeFormRequests(): array
    {
        $requests = $this->discoverFormRequests();
        $analyzed = [];
        
        foreach ($requests as $requestClass) {
            try {
                $analyzed[] = $this->analyzeFormRequest($requestClass);
            } catch (\Exception $e) {
                // Skip invalid form requests
                continue;
            }
        }
        
        return $analyzed;
    }

    /**
     * Discover Form Request classes.
     */
    private function discoverFormRequests(): array
    {
        $requests = [];
        $directories = [
            app_path('Http/Requests'),
            app_path('Requests'),
        ];
        
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            
            $finder = new Finder();
            $finder->files()->name('*.php')->in($directory);
            
            foreach ($finder as $file) {
                $class = $this->getClassFromFile($file->getRealPath());
                
                if ($class && 
                    class_exists($class) && 
                    is_subclass_of($class, FormRequest::class)) {
                    $requests[] = $class;
                }
            }
        }
        
        return $requests;
    }

    /**
     * Analyze a specific Form Request class.
     */
    private function analyzeFormRequest(string $requestClass): array
    {
        $reflection = new ReflectionClass($requestClass);
        $instance = app($requestClass);
        
        return [
            'class' => $requestClass,
            'name' => class_basename($requestClass),
            'rules' => $this->extractRules($instance),
            'messages' => $this->extractMessages($instance),
            'attributes' => $this->extractAttributes($instance),
            'model' => $this->inferModel($requestClass),
            'purpose' => $this->inferPurpose($requestClass),
        ];
    }

    /**
     * Extract validation rules.
     */
    private function extractRules(FormRequest $request): array
    {
        try {
            $rules = $request->rules();
            return $this->normalizeRules($rules);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract validation messages.
     */
    private function extractMessages(FormRequest $request): array
    {
        try {
            return $request->messages();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract custom attributes.
     */
    private function extractAttributes(FormRequest $request): array
    {
        try {
            return $request->attributes();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Infer related model from request name.
     */
    private function inferModel(string $requestClass): ?string
    {
        $className = class_basename($requestClass);
        
        // Remove common suffixes
        $suffixes = ['Request', 'FormRequest', 'Validation'];
        foreach ($suffixes as $suffix) {
            if (Str::endsWith($className, $suffix)) {
                $className = Str::beforeLast($className, $suffix);
                break;
            }
        }
        
        // Remove common prefixes
        $prefixes = ['Create', 'Update', 'Store', 'Edit', 'Delete'];
        foreach ($prefixes as $prefix) {
            if (Str::startsWith($className, $prefix)) {
                $className = Str::after($className, $prefix);
                break;
            }
        }
        
        // Try to find the model
        $possibleModels = [
            "App\\Models\\{$className}",
            "App\\{$className}",
        ];
        
        foreach ($possibleModels as $model) {
            if (class_exists($model)) {
                return $model;
            }
        }
        
        return null;
    }

    /**
     * Infer purpose from request name.
     */
    private function inferPurpose(string $requestClass): string
    {
        $className = class_basename($requestClass);
        
        if (Str::contains($className, 'Create') || Str::contains($className, 'Store')) {
            return 'create';
        } elseif (Str::contains($className, 'Update') || Str::contains($className, 'Edit')) {
            return 'update';
        } elseif (Str::contains($className, 'Delete')) {
            return 'delete';
        }
        
        return 'general';
    }

    /**
     * Normalize validation rules.
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];
        
        foreach ($rules as $field => $rule) {
            $normalized[$field] = $this->parseRule($rule);
        }
        
        return $normalized;
    }

    /**
     * Parse a validation rule.
     */
    private function parseRule($rule): array
    {
        if (is_string($rule)) {
            return $this->parseStringRule($rule);
        } elseif (is_array($rule)) {
            return $this->parseArrayRule($rule);
        }
        
        return ['type' => 'mixed', 'rules' => []];
    }

    /**
     * Parse string validation rule.
     */
    private function parseStringRule(string $rule): array
    {
        $rules = array_map('trim', explode('|', $rule));
        $parsed = [
            'type' => 'string',
            'nullable' => false,
            'required' => false,
            'rules' => [],
        ];
        
        foreach ($rules as $singleRule) {
            $this->applySingleRule($parsed, $singleRule);
        }
        
        return $parsed;
    }

    /**
     * Parse array validation rule.
     */
    private function parseArrayRule(array $rules): array
    {
        $parsed = [
            'type' => 'string',
            'nullable' => false,
            'required' => false,
            'rules' => [],
        ];
        
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $this->applySingleRule($parsed, $rule);
            } elseif (is_object($rule)) {
                $parsed['rules'][] = get_class($rule);
            }
        }
        
        return $parsed;
    }

    /**
     * Apply a single rule to parsed data.
     */
    private function applySingleRule(array &$parsed, string $rule): void
    {
        $ruleName = Str::before($rule, ':');
        $parameters = Str::after($rule, ':');
        
        match ($ruleName) {
            'required' => $parsed['required'] = true,
            'nullable' => $parsed['nullable'] = true,
            'string' => $parsed['type'] = 'string',
            'integer', 'numeric' => $parsed['type'] = 'number',
            'boolean' => $parsed['type'] = 'boolean',
            'array' => $parsed['type'] = 'array',
            'json' => $parsed['type'] = 'object',
            'date', 'date_format' => $parsed['type'] = 'date',
            'email' => $parsed['type'] = 'email',
            'url' => $parsed['type'] = 'url',
            'file', 'image' => $parsed['type'] = 'file',
            'exists' => $parsed['type'] = 'reference',
            'unique' => $parsed['rules'][] = 'unique',
            'confirmed' => $parsed['rules'][] = 'confirmed',
            'min' => $parsed['rules'][] = "min:{$parameters}",
            'max' => $parsed['rules'][] = "max:{$parameters}",
            'size' => $parsed['rules'][] = "size:{$parameters}",
            'between' => $parsed['rules'][] = "between:{$parameters}",
            'in' => $parsed['rules'][] = "in:{$parameters}",
            'not_in' => $parsed['rules'][] = "not_in:{$parameters}",
            'regex' => $parsed['rules'][] = "regex:{$parameters}",
            default => $parsed['rules'][] = $rule,
        };
    }

    /**
     * Map validation type to TypeScript type.
     */
    public function mapValidationTypeToTypeScript(array $validation): string
    {
        $type = match ($validation['type']) {
            'string', 'email', 'url' => 'string',
            'number', 'integer', 'numeric' => 'number',
            'boolean' => 'boolean',
            'array' => 'Array<any>',
            'object', 'json' => 'Record<string, any>',
            'date' => 'Date',
            'file' => 'File',
            'reference' => 'number | string',
            default => 'any',
        };
        
        if ($validation['nullable']) {
            $type .= ' | null';
        }
        
        return $type;
    }

    /**
     * Convert validation rules to Zod schema.
     */
    public function convertToZodSchema(array $validation): string
    {
        $zodType = match ($validation['type']) {
            'string', 'email', 'url' => 'z.string()',
            'number', 'integer', 'numeric' => 'z.number()',
            'boolean' => 'z.boolean()',
            'array' => 'z.array(z.any())',
            'object', 'json' => 'z.record(z.any())',
            'date' => 'z.date()',
            'file' => 'z.any()', // File type
            'reference' => 'z.union([z.number(), z.string()])',
            default => 'z.any()',
        };
        
        // Apply additional validations
        foreach ($validation['rules'] as $rule) {
            if (Str::startsWith($rule, 'min:')) {
                $value = Str::after($rule, 'min:');
                $zodType .= ".min({$value})";
            } elseif (Str::startsWith($rule, 'max:')) {
                $value = Str::after($rule, 'max:');
                $zodType .= ".max({$value})";
            } elseif (Str::startsWith($rule, 'email')) {
                $zodType .= ".email()";
            } elseif (Str::startsWith($rule, 'url')) {
                $zodType .= ".url()";
            }
        }
        
        // Handle nullable and optional
        if ($validation['nullable']) {
            $zodType .= '.nullable()';
        }
        
        if (!$validation['required']) {
            $zodType .= '.optional()';
        }
        
        return $zodType;
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
}