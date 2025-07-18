<?php

namespace ArnaldoTomo\LaravelAutoSchema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Relations\Relation;

class ModelAnalyzer
{
    /**
     * Analyze a model and extract its structure.
     */
    public function analyze(string $modelClass): array
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("Class {$modelClass} is not a valid Eloquent model");
        }

        $model = app($modelClass);
        $reflection = new ReflectionClass($model);
        $table = $model->getTable();

        return [
            'class' => $modelClass,
            'name' => class_basename($modelClass),
            'table' => $table,
            'properties' => $this->getModelProperties($model),
            'relationships' => $this->getRelationships($model, $reflection),
            'casts' => $this->getCasts($model),
            'fillable' => $this->getFillable($model),
            'guarded' => $this->getGuarded($model),
            'hidden' => $this->getHidden($model),
            'appends' => $this->getAppends($model),
            'dates' => $this->getDates($model),
            'accessors' => $this->getAccessors($reflection),
            'mutators' => $this->getMutators($reflection),
            'validation_rules' => $this->getValidationRules($modelClass),
        ];
    }

    /**
     * Get model properties from database schema.
     */
    private function getModelProperties(Model $model): array
    {
        $table = $model->getTable();
        $columns = Schema::getColumnListing($table);
        $properties = [];

        foreach ($columns as $column) {
            $columnType = Schema::getColumnType($table, $column);
            $properties[$column] = [
                'name' => $column,
                'type' => $this->mapDatabaseTypeToTypeScript($columnType),
                'nullable' => $this->isColumnNullable($table, $column),
                'default' => $this->getColumnDefault($table, $column),
                'comment' => $this->getColumnComment($table, $column),
            ];
        }

        return $properties;
    }

    /**
     * Get model relationships.
     */
    private function getRelationships(Model $model, ReflectionClass $reflection): array
    {
        $relationships = [];
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            if ($this->isRelationshipMethod($method, $model)) {
                $relationshipName = $method->getName();
                $relationship = $model->$relationshipName();
                
                $relationships[$relationshipName] = [
                    'name' => $relationshipName,
                    'type' => $this->getRelationshipType($relationship),
                    'related' => get_class($relationship->getRelated()),
                    'foreign_key' => $this->getForeignKey($relationship),
                    'local_key' => $this->getLocalKey($relationship),
                ];
            }
        }

        return $relationships;
    }

    /**
     * Check if a method is a relationship method.
     */
    private function isRelationshipMethod(ReflectionMethod $method, Model $model): bool
    {
        if ($method->getNumberOfParameters() > 0) {
            return false;
        }

        try {
            $return = $method->invoke($model);
            return $return instanceof Relation;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get relationship type name.
     */
    private function getRelationshipType(Relation $relationship): string
    {
        $class = get_class($relationship);
        return Str::afterLast($class, '\\');
    }

    /**
     * Get foreign key from relationship.
     */
    private function getForeignKey(Relation $relationship): ?string
    {
        $methods = ['getForeignKeyName', 'getForeignKey', 'getQualifiedForeignKeyName'];
        
        foreach ($methods as $method) {
            if (method_exists($relationship, $method)) {
                return $relationship->$method();
            }
        }

        return null;
    }

    /**
     * Get local key from relationship.
     */
    private function getLocalKey(Relation $relationship): ?string
    {
        $methods = ['getLocalKeyName', 'getLocalKey', 'getQualifiedParentKeyName'];
        
        foreach ($methods as $method) {
            if (method_exists($relationship, $method)) {
                return $relationship->$method();
            }
        }

        return null;
    }

    /**
     * Get model casts.
     */
    private function getCasts(Model $model): array
    {
        return $model->getCasts();
    }

    /**
     * Get fillable attributes.
     */
    private function getFillable(Model $model): array
    {
        return $model->getFillable();
    }

    /**
     * Get guarded attributes.
     */
    private function getGuarded(Model $model): array
    {
        return $model->getGuarded();
    }

    /**
     * Get hidden attributes.
     */
    private function getHidden(Model $model): array
    {
        return $model->getHidden();
    }

    /**
     * Get appends attributes.
     */
    private function getAppends(Model $model): array
    {
        return $model->getAppends();
    }

    /**
     * Get date attributes.
     */
    private function getDates(Model $model): array
    {
        return $model->getDates();
    }

    /**
     * Get model accessors.
     */
    private function getAccessors(ReflectionClass $reflection): array
    {
        $accessors = [];
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (Str::startsWith($method->getName(), 'get') && 
                Str::endsWith($method->getName(), 'Attribute')) {
                $attribute = Str::snake(Str::between($method->getName(), 'get', 'Attribute'));
                $accessors[$attribute] = [
                    'name' => $attribute,
                    'method' => $method->getName(),
                    'type' => $this->getMethodReturnType($method),
                ];
            }
        }

        return $accessors;
    }

    /**
     * Get model mutators.
     */
    private function getMutators(ReflectionClass $reflection): array
    {
        $mutators = [];
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (Str::startsWith($method->getName(), 'set') && 
                Str::endsWith($method->getName(), 'Attribute')) {
                $attribute = Str::snake(Str::between($method->getName(), 'set', 'Attribute'));
                $mutators[$attribute] = [
                    'name' => $attribute,
                    'method' => $method->getName(),
                ];
            }
        }

        return $mutators;
    }

    /**
     * Get validation rules for model.
     */
    private function getValidationRules(string $modelClass): array
    {
        // This would integrate with FormRequest classes
        // For now, return empty array
        return [];
    }

    /**
     * Map database type to TypeScript type.
     */
    private function mapDatabaseTypeToTypeScript(string $databaseType): string
    {
        return match ($databaseType) {
            'integer', 'bigint', 'smallint', 'tinyint', 'mediumint' => 'number',
            'decimal', 'float', 'double', 'real' => 'number',
            'boolean', 'bool' => 'boolean',
            'string', 'char', 'varchar', 'text', 'longtext', 'mediumtext', 'tinytext' => 'string',
            'date', 'datetime', 'timestamp', 'time' => 'Date',
            'json', 'jsonb' => 'Record<string, any>',
            'array' => 'Array<any>',
            'object' => 'Record<string, any>',
            default => 'any',
        };
    }

    /**
     * Check if column is nullable.
     */
    private function isColumnNullable(string $table, string $column): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();
            
            if (method_exists($schemaBuilder, 'getColumnDetails')) {
                $details = $schemaBuilder->getColumnDetails($table, $column);
                return $details['nullable'] ?? false;
            }
            
            // Fallback for older Laravel versions
            return true; // Conservative approach
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Get column default value.
     */
    private function getColumnDefault(string $table, string $column): mixed
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();
            
            if (method_exists($schemaBuilder, 'getColumnDetails')) {
                $details = $schemaBuilder->getColumnDetails($table, $column);
                return $details['default'] ?? null;
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get column comment.
     */
    private function getColumnComment(string $table, string $column): ?string
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();
            
            if (method_exists($schemaBuilder, 'getColumnDetails')) {
                $details = $schemaBuilder->getColumnDetails($table, $column);
                return $details['comment'] ?? null;
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get method return type.
     */
    private function getMethodReturnType(ReflectionMethod $method): string
    {
        $returnType = $method->getReturnType();
        
        if ($returnType) {
            return $this->mapPhpTypeToTypeScript($returnType->getName());
        }
        
        return 'any';
    }

    /**
     * Map PHP type to TypeScript type.
     */
    private function mapPhpTypeToTypeScript(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer', 'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'string' => 'string',
            'array' => 'Array<any>',
            'object' => 'Record<string, any>',
            'mixed' => 'any',
            default => 'any',
        };
    }
}