<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    */
    'output' => [
        'path' => resource_path('js/types'),
        'filename_case' => 'pascal', // pascal, camel, snake, kebab
        'export_format' => 'named', // named, default, namespace
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    */
    'models' => [
        'directories' => [
            app_path('Models'),
              app_path(),  // Adicionar esta linha
        ],
        'base_model' => 'Illuminate\\Database\\Eloquent\\Model',
        'exclude' => [
            // Models to exclude from generation
        ],
        'include_relationships' => true,
        'include_accessors' => true,
        'include_mutators' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Generation
    |--------------------------------------------------------------------------
    */
    'types' => [
        'generate_interfaces' => true,
        'generate_types' => true,
        'generate_enums' => true,
        'nullable_union' => true, // string | null vs string?
        'readonly_properties' => false,
        'strict_types' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'enabled' => true,
        'schema_format' => 'zod', // zod, yup, joi
        'include_form_requests' => true,
        'include_model_rules' => true,
        'custom_rules_path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'generate_client' => true,
        'base_url' => env('APP_URL', 'http://localhost'),
        'include_resources' => true,
        'include_collections' => true,
        'authentication' => 'sanctum', // sanctum, passport, none
    ],

    /*
    |--------------------------------------------------------------------------
    | Framework Integration
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'inertia' => true,
        'livewire' => false,
        'sanctum' => true,
        'passport' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Options
    |--------------------------------------------------------------------------
    */
    'advanced' => [
        'cache_enabled' => true,
        'cache_ttl' => 3600,
        'backup_existing' => true,
        'prettify_output' => true,
        'add_timestamps' => true,
        'include_database_comments' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Configuration
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'interface' => 'interface',
        'type' => 'type',
        'enum' => 'enum',
        'validation' => 'validation',
        'api_client' => 'api-client',
    ],
];