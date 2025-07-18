<?php

namespace ArnaldoTomo\LaravelAutoSchema\Tests\Commands;

use ArnaldoTomo\LaravelAutoSchema\Tests\TestCase;
use Illuminate\Support\Facades\File;

class GenerateTypesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test models directory
        $this->createTestModelsDirectory();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $this->cleanupTestFiles();
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_generate_types_for_basic_model()
    {
        $this->createTestModel('User', [
            'fillable' => ['name', 'email'],
            'casts' => ['email_verified_at' => 'datetime'],
        ]);

        $this->artisan('schema:generate')
            ->expectsOutput('🚀 Laravel AutoSchema - TypeScript Generator')
            ->expectsOutput('✅ TypeScript types generated successfully!')
            ->assertExitCode(0);

        $this->assertTypeFileExists('User.ts');
        $this->assertTypeFileContains('User.ts', 'export interface User');
        $this->assertTypeFileContains('User.ts', 'name: string;');
        $this->assertTypeFileContains('User.ts', 'email: string;');
        $this->assertTypeFileContains('User.ts', 'email_verified_at: Date | null;');
    }

    /** @test */
    public function it_can_generate_types_for_model_with_relationships()
    {
        $this->createTestModel('User', [
            'fillable' => ['name', 'email'],
            'relationships' => [
                'posts' => 'hasMany:Post',
            ],
        ]);

        $this->createTestModel('Post', [
            'fillable' => ['title', 'content'],
            'relationships' => [
                'user' => 'belongsTo:User',
            ],
        ]);

        $this->artisan('schema:generate')
            ->assertExitCode(0);

        $this->assertTypeFileExists('User.ts');
        $this->assertTypeFileExists('Post.ts');
        $this->assertTypeFileContains('User.ts', 'posts?: Post[];');
        $this->assertTypeFileContains('Post.ts', 'user?: User;');
    }

    /** @test */
    public function it_can_generate_types_for_specific_model()
    {
        $this->createTestModel('User', ['fillable' => ['name']]);
        $this->createTestModel('Post', ['fillable' => ['title']]);

        $this->artisan('schema:generate --model=User')
            ->assertExitCode(0);

        $this->assertTypeFileExists('User.ts');
        $this->assertTypeFileDoesNotExist('Post.ts');
    }

    /** @test */
    public function it_can_run_dry_run()
    {
        $this->createTestModel('User', ['fillable' => ['name']]);

        $this->artisan('schema:generate --dry-run')
            ->expectsOutput('🔍 Dry run mode - analyzing models:')
            ->expectsOutput('✅ Dry run completed. Use --force to generate files.')
            ->assertExitCode(0);

        $this->assertTypeFileDoesNotExist('User.ts');
    }

    /** @test */
    public function it_shows_error_for_invalid_model()
    {
        $this->artisan('schema:generate --model=NonExistentModel')
            ->expectsOutput('⚠️  Model \'NonExistentModel\' not found.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_generates_index_file()
    {
        $this->createTestModel('User', ['fillable' => ['name']]);
        $this->createTestModel('Post', ['fillable' => ['title']]);

        $this->artisan('schema:generate')
            ->assertExitCode(0);

        $this->assertTypeFileExists('index.ts');
        $this->assertTypeFileContains('index.ts', 'export type { User } from \'./User\';');
        $this->assertTypeFileContains('index.ts', 'export type { Post } from \'./Post\';');
    }

    /** @test */
    public function it_generates_validation_schemas_when_enabled()
    {
        config(['autoscema.validation.enabled' => true]);

        $this->createTestModel('User', ['fillable' => ['name', 'email']]);

        $this->artisan('schema:generate')
            ->assertExitCode(0);

        $this->assertTypeFileExists('validation-schemas.ts');
        $this->assertTypeFileContains('validation-schemas.ts', 'export const userSchema');
    }

    /** @test */
    public function it_generates_api_client_when_enabled()
    {
        config(['autoscema.api.generate_client' => true]);

        $this->createTestModel('User', ['fillable' => ['name', 'email']]);

        $this->artisan('schema:generate')
            ->assertExitCode(0);

        $this->assertTypeFileExists('api-client.ts');
        $this->assertTypeFileContains('api-client.ts', 'export const userApi');
    }

    private function createTestModelsDirectory(): void
    {
        $modelsPath = $this->app->basePath('app/Models');
        if (!File::isDirectory($modelsPath)) {
            File::makeDirectory($modelsPath, 0755, true);
        }
    }

    private function createTestModel(string $name, array $config = []): void
    {
        $fillable = $config['fillable'] ?? [];
        $casts = $config['casts'] ?? [];
        $relationships = $config['relationships'] ?? [];

        $fillableStr = "'" . implode("', '", $fillable) . "'";
        $castsStr = '';
        if (!empty($casts)) {
            $castsArray = [];
            foreach ($casts as $field => $cast) {
                $castsArray[] = "'$field' => '$cast'";
            }
            $castsStr = "protected \$casts = [" . implode(', ', $castsArray) . "];";
        }

        $relationshipsStr = '';
        foreach ($relationships as $relationName => $relationConfig) {
            [$type, $model] = explode(':', $relationConfig);
            $relationshipsStr .= "
    public function $relationName()
    {
        return \$this->$type($model::class);
    }";
        }

        $modelContent = "<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class $name extends Model
{
    protected \$fillable = [$fillableStr];
    
    $castsStr
    $relationshipsStr
}";

        File::put($this->app->basePath("app/Models/$name.php"), $modelContent);
    }

    private function cleanupTestFiles(): void
    {
        $paths = [
            $this->app->basePath('app/Models'),
            config('autoscema.output.path'),
        ];

        foreach ($paths as $path) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        }
    }

    private function assertTypeFileExists(string $filename): void
    {
        $path = config('autoscema.output.path') . '/' . $filename;
        $this->assertTrue(File::exists($path), "Type file $filename does not exist");
    }

    private function assertTypeFileDoesNotExist(string $filename): void
    {
        $path = config('autoscema.output.path') . '/' . $filename;
        $this->assertFalse(File::exists($path), "Type file $filename should not exist");
    }

    private function assertTypeFileContains(string $filename, string $content): void
    {
        $path = config('autoscema.output.path') . '/' . $filename;
        $this->assertTrue(File::exists($path), "Type file $filename does not exist");
        
        $fileContent = File::get($path);
        $this->assertStringContainsString($content, $fileContent, "Type file $filename does not contain expected content");
    }
}