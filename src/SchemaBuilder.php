<?php

namespace ArnaldoTomo\LaravelAutoSchema;

use Illuminate\Support\Str;

class SchemaBuilder
{
    private array $config;

    public function __construct()
    {
        $this->config = config('autoscema');
    }

    /**
     * Build API client TypeScript file.
     */
    public function buildApiClient(array $models): string
    {
        $baseUrl = $this->config['api']['base_url'];
        $authentication = $this->config['api']['authentication'];
        
        $imports = $this->buildApiImports($models);
        $client = $this->buildApiClientClass($authentication, $baseUrl);
        $methods = $this->buildApiMethods($models);
        $timestamp = $this->config['advanced']['add_timestamps'] ? $this->getTimestamp() : '';
        
        return trim("
{$timestamp}
{$imports}

{$client}

{$methods}

// Export singleton instance
export const api = new ApiClient();
        ");
    }

    /**
     * Build API imports.
     */
    private function buildApiImports(array $models): string
    {
        $modelNames = array_map(fn($model) => $model['name'], $models);
        $imports = implode(', ', $modelNames);
        
        return "import type { {$imports} } from './index';";
    }

    /**
     * Build API client class.
     */
    private function buildApiClientClass(string $authentication, string $baseUrl): string
    {
        $authHeaders = $this->buildAuthHeaders($authentication);
        
        return "
class ApiClient {
    private baseUrl: string;
    private headers: Record<string, string>;

    constructor(baseUrl: string = '{$baseUrl}') {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            {$authHeaders}
        };
    }

    /**
     * Set authentication token.
     */
    setToken(token: string): void {
        this.headers['Authorization'] = `Bearer \${token}`;
    }

    /**
     * Remove authentication token.
     */
    removeToken(): void {
        delete this.headers['Authorization'];
    }

    /**
     * Make HTTP request.
     */
    private async request<T>(
        method: string,
        endpoint: string,
        data?: any,
        options?: RequestInit
    ): Promise<T> {
        const url = `\${this.baseUrl}\${endpoint}`;
        const config: RequestInit = {
            method,
            headers: { ...this.headers, ...options?.headers },
            ...options,
        };

        if (data && ['POST', 'PUT', 'PATCH'].includes(method)) {
            config.body = JSON.stringify(data);
        }

        const response = await fetch(url, config);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: \${response.status}`);
        }

        return response.json();
    }

    /**
     * GET request.
     */
    async get<T>(endpoint: string, options?: RequestInit): Promise<T> {
        return this.request<T>('GET', endpoint, null, options);
    }

    /**
     * POST request.
     */
    async post<T>(endpoint: string, data?: any, options?: RequestInit): Promise<T> {
        return this.request<T>('POST', endpoint, data, options);
    }

    /**
     * PUT request.
     */
    async put<T>(endpoint: string, data?: any, options?: RequestInit): Promise<T> {
        return this.request<T>('PUT', endpoint, data, options);
    }

    /**
     * PATCH request.
     */
    async patch<T>(endpoint: string, data?: any, options?: RequestInit): Promise<T> {
        return this.request<T>('PATCH', endpoint, data, options);
    }

    /**
     * DELETE request.
     */
    async delete<T>(endpoint: string, options?: RequestInit): Promise<T> {
        return this.request<T>('DELETE', endpoint, null, options);
    }
}";
    }

    /**
     * Build authentication headers.
     */
    private function buildAuthHeaders(string $authentication): string
    {
        return match ($authentication) {
            'sanctum' => "'X-Requested-With': 'XMLHttpRequest',",
            'passport' => '',
            default => '',
        };
    }

    /**
     * Build API methods for models.
     */
    private function buildApiMethods(array $models): string
    {
        $methods = [];
        
        foreach ($models as $modelData) {
            $modelName = $modelData['name'];
            $resourceName = Str::kebab(Str::plural($modelName));
            
            $methods[] = $this->buildModelApiMethods($modelName, $resourceName);
        }
        
        return implode("\n\n", $methods);
    }

    /**
     * Build API methods for a specific model.
     */
    private function buildModelApiMethods(string $modelName, string $resourceName): string
    {
        $lowerModelName = Str::camel($modelName);
        $createType = "Create{$modelName}Request";
        $updateType = "Update{$modelName}Request";
        
        return "
/**
 * {$modelName} API methods
 */
export const {$lowerModelName}Api = {
    /**
     * Get all {$resourceName}
     */
    getAll: (params?: Record<string, any>): Promise<{$modelName}[]> => {
        const query = params ? '?' + new URLSearchParams(params).toString() : '';
        return api.get<{$modelName}[]>(`/{$resourceName}\${query}`);
    },

    /**
     * Get {$lowerModelName} by ID
     */
    getById: (id: number | string): Promise<{$modelName}> => {
        return api.get<{$modelName}>(`/{$resourceName}/\${id}`);
    },

    /**
     * Create new {$lowerModelName}
     */
    create: (data: Partial<{$modelName}>): Promise<{$modelName}> => {
        return api.post<{$modelName}>(`/{$resourceName}`, data);
    },

    /**
     * Update {$lowerModelName}
     */
    update: (id: number | string, data: Partial<{$modelName}>): Promise<{$modelName}> => {
        return api.put<{$modelName}>(`/{$resourceName}/\${id}`, data);
    },

    /**
     * Delete {$lowerModelName}
     */
    delete: (id: number | string): Promise<void> => {
        return api.delete<void>(`/{$resourceName}/\${id}`);
    },

    /**
     * Restore {$lowerModelName} (if soft deletes enabled)
     */
    restore: (id: number | string): Promise<{$modelName}> => {
        return api.post<{$modelName}>(`/{$resourceName}/\${id}/restore`);
    },
};";
    }

    /**
     * Build validation schemas.
     */
    public function buildValidationSchemas(array $models): string
    {
        $format = $this->config['validation']['schema_format'];
        $imports = $this->buildValidationImports($format);
        $schemas = $this->buildValidationSchemasForModels($models, $format);
        $timestamp = $this->config['advanced']['add_timestamps'] ? $this->getTimestamp() : '';
        
        return trim("
{$timestamp}
{$imports}

{$schemas}
        ");
    }

    /**
     * Build validation imports.
     */
    private function buildValidationImports(string $format): string
    {
        return match ($format) {
            'zod' => "import { z } from 'zod';",
            'yup' => "import * as yup from 'yup';",
            'joi' => "import Joi from 'joi';",
            default => "import { z } from 'zod';",
        };
    }

    /**
     * Build validation schemas for all models.
     */
    private function buildValidationSchemasForModels(array $models, string $format): string
    {
        $schemas = [];
        
        foreach ($models as $modelData) {
            $schemas[] = $this->buildModelValidationSchema($modelData, $format);
        }
        
        return implode("\n\n", $schemas);
    }

    /**
     * Build validation schema for a specific model.
     */
    private function buildModelValidationSchema(array $modelData, string $format): string
    {
        $modelName = $modelData['name'];
        $schemaName = Str::camel($modelName) . 'Schema';
        
        return match ($format) {
            'zod' => $this->buildZodSchema($modelData, $schemaName),
            'yup' => $this->buildYupSchema($modelData, $schemaName),
            'joi' => $this->buildJoiSchema($modelData, $schemaName),
            default => $this->buildZodSchema($modelData, $schemaName),
        };
    }

    /**
     * Build Zod validation schema.
     */
    private function buildZodSchema(array $modelData, string $schemaName): string
    {
        $modelName = $modelData['name'];
        $properties = [];
        
        foreach ($modelData['properties'] as $property) {
            if (in_array($property['name'], ['id', 'created_at', 'updated_at'])) {
                continue; // Skip auto-generated fields
            }
            
            $zodType = $this->mapTypeToZod($property['type']);
            $validation = $this->buildZodValidation($property, $modelData);
            
            $properties[] = "  {$property['name']}: {$zodType}{$validation}";
        }
        
        $propertiesStr = implode(",\n", $properties);
        
        return "
/**
 * {$modelName} validation schema
 */
export const {$schemaName} = z.object({
{$propertiesStr}
});

export type {$modelName}Input = z.infer<typeof {$schemaName}>;";
    }

    /**
     * Map TypeScript type to Zod type.
     */
    private function mapTypeToZod(string $type): string
    {
        return match ($type) {
            'string' => 'z.string()',
            'number' => 'z.number()',
            'boolean' => 'z.boolean()',
            'Date' => 'z.date()',
            'Array<any>' => 'z.array(z.any())',
            'Record<string, any>' => 'z.record(z.any())',
            default => 'z.any()',
        };
    }

    /**
     * Build Zod validation rules.
     */
    private function buildZodValidation(array $property, array $modelData): string
    {
        $validations = [];
        
        // Handle nullable
        if ($property['nullable']) {
            $validations[] = '.nullable()';
        }
        
        // Handle string length (if we can determine from database)
        if ($property['type'] === 'string') {
            $validations[] = '.trim()';
        }
        
        // Handle fillable vs required
        if (!in_array($property['name'], $modelData['fillable']) && !$property['nullable']) {
            $validations[] = '.optional()';
        }
        
        return implode('', $validations);
    }

    /**
     * Build Yup validation schema.
     */
    private function buildYupSchema(array $modelData, string $schemaName): string
    {
        $modelName = $modelData['name'];
        $properties = [];
        
        foreach ($modelData['properties'] as $property) {
            if (in_array($property['name'], ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            
            $yupType = $this->mapTypeToYup($property['type']);
            $validation = $this->buildYupValidation($property, $modelData);
            
            $properties[] = "  {$property['name']}: {$yupType}{$validation}";
        }
        
        $propertiesStr = implode(",\n", $properties);
        
        return "
/**
 * {$modelName} validation schema
 */
export const {$schemaName} = yup.object({
{$propertiesStr}
});

export type {$modelName}Input = yup.InferType<typeof {$schemaName}>;";
    }

    /**
     * Map TypeScript type to Yup type.
     */
    private function mapTypeToYup(string $type): string
    {
        return match ($type) {
            'string' => 'yup.string()',
            'number' => 'yup.number()',
            'boolean' => 'yup.boolean()',
            'Date' => 'yup.date()',
            'Array<any>' => 'yup.array()',
            'Record<string, any>' => 'yup.object()',
            default => 'yup.mixed()',
        };
    }

    /**
     * Build Yup validation rules.
     */
    private function buildYupValidation(array $property, array $modelData): string
    {
        $validations = [];
        
        if (!$property['nullable']) {
            $validations[] = '.required()';
        } else {
            $validations[] = '.nullable()';
        }
        
        return implode('', $validations);
    }

    /**
     * Build Joi validation schema.
     */
    private function buildJoiSchema(array $modelData, string $schemaName): string
    {
        $modelName = $modelData['name'];
        $properties = [];
        
        foreach ($modelData['properties'] as $property) {
            if (in_array($property['name'], ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            
            $joiType = $this->mapTypeToJoi($property['type']);
            $validation = $this->buildJoiValidation($property, $modelData);
            
            $properties[] = "  {$property['name']}: {$joiType}{$validation}";
        }
        
        $propertiesStr = implode(",\n", $properties);
        
        return "
/**
 * {$modelName} validation schema
 */
export const {$schemaName} = Joi.object({
{$propertiesStr}
});";
    }

    /**
     * Map TypeScript type to Joi type.
     */
    private function mapTypeToJoi(string $type): string
    {
        return match ($type) {
            'string' => 'Joi.string()',
            'number' => 'Joi.number()',
            'boolean' => 'Joi.boolean()',
            'Date' => 'Joi.date()',
            'Array<any>' => 'Joi.array()',
            'Record<string, any>' => 'Joi.object()',
            default => 'Joi.any()',
        };
    }

    /**
     * Build Joi validation rules.
     */
    private function buildJoiValidation(array $property, array $modelData): string
    {
        $validations = [];
        
        if (!$property['nullable']) {
            $validations[] = '.required()';
        } else {
            $validations[] = '.optional()';
        }
        
        return implode('', $validations);
    }

    /**
     * Get timestamp comment.
     */
    private function getTimestamp(): string
    {
        return "// Generated at " . now()->toDateTimeString() . " by Laravel AutoSchema";
    }
}