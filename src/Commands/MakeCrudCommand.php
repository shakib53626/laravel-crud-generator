<?php

namespace Shakib53626\CrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud
                            {name : Model name (e.g. Post, ProductCategory)}
                            {--columns= : Columns for the table, e.g. name:string,slug:string:unique,description:text}
                            {--api : Generate API controller instead of web resource controller}
                            {--softdelete : Add soft delete support}
                            {--guarded : Use $guarded instead of $fillable in model}
                            {--files= : Specific files to generate, e.g. model,controller,repository}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate CRUD scaffold: Migration, Model, Controller, Requests, and API Resource';

    protected string $modelName;
    protected string $studly;
    protected string $snake;
    protected string $camel;
    protected string $plural;
    protected string $pluralSnake;
    protected string $tableName;
    protected array $columns = [];
    protected bool $softDelete = false;
    protected bool $useGuarded = false;
    protected array $filesToGenerate = [];

    public function handle(): int
    {
        $this->modelName = $this->argument('name');
        $this->studly    = Str::studly($this->modelName);
        $this->snake     = Str::snake($this->studly);
        $this->camel     = Str::camel($this->studly);
        $this->plural    = Str::plural($this->studly);
        $this->pluralSnake = Str::snake($this->plural);
        $this->tableName = $this->pluralSnake;
        $this->softDelete = $this->option('softdelete');
        $this->useGuarded = $this->option('guarded');
        
        $this->parseColumns();
        $this->parseFilesToGenerate();

        $this->info("🚀 Generating CRUD for: <comment>{$this->studly}</comment>");
        $this->newLine();

        if ($this->shouldGenerate('migration')) {
            $this->generateMigration();
        }
        if ($this->shouldGenerate('model')) {
            $this->generateModel();
        }
        if ($this->shouldGenerate('repository')) {
            $this->generateRepository();
        }
        if ($this->shouldGenerate('request')) {
            $this->generateRequest();
        }
        if ($this->shouldGenerate('controller')) {
            $this->generateController();
        }
        if ($this->shouldGenerate('resource')) {
            $this->generateResource();
        }

        $this->newLine();
        $this->info('✅ CRUD generated successfully!');
        $this->newLine();
        $this->showRouteHint();

        return self::SUCCESS;
    }

    // ─── Migration ────────────────────────────────────────────────
    protected function generateMigration(): void
    {
        $timestamp  = now()->format('Y_m_d_His');
        $fileName   = "{$timestamp}_create_{$this->tableName}_table.php";
        $targetPath = database_path("migrations/{$fileName}");

        $stub = $this->getStub('migration');
        $stub = $this->replacePlaceholders($stub);

        $this->writeFile($targetPath, $stub, 'Migration');
    }

    // ─── Model ────────────────────────────────────────────────────
    protected function generateModel(): void
    {
        $targetPath = app_path("Models/{$this->studly}.php");

        $stub = $this->getStub('model');
        $stub = $this->replacePlaceholders($stub);

        $this->writeFile($targetPath, $stub, 'Model');
    }

    // ─── Repository ───────────────────────────────────────────────
    protected function generateRepository(): void
    {
        $dir = app_path('Repositories');
        @mkdir($dir, 0755, true);
        $targetPath = "{$dir}/{$this->studly}Repository.php";

        $stub = $this->getStub('repository');
        $stub = $this->replacePlaceholders($stub);

        $this->writeFile($targetPath, $stub, 'Repository');
    }

    // ─── Request ──────────────────────────────────────────────────
    protected function generateRequest(): void
    {
        $dir = app_path('Http/Requests');
        @mkdir($dir, 0755, true);
        $targetPath = "{$dir}/{$this->studly}Request.php";

        $stub = $this->getStub('request');
        $stub = $this->replacePlaceholders($stub);

        $this->writeFile($targetPath, $stub, 'Request');
    }

    // ─── Controller ───────────────────────────────────────────────
    protected function generateController(): void
    {
        $isApi      = $this->option('api');
        $stubName   = $isApi ? 'controller-api' : 'controller';
        $dir        = $isApi ? app_path('Http/Controllers/Api') : app_path('Http/Controllers');
        $targetPath = "{$dir}/{$this->studly}Controller.php";

        if ($isApi) {
            @mkdir($dir, 0755, true);
        }

        $stub = $this->getStub($stubName);
        $stub = $this->replacePlaceholders($stub);

        $this->writeFile($targetPath, $stub, 'Controller');
    }

    // ─── API Resource ─────────────────────────────────────────────
    protected function generateResource(): void
    {
        $dir        = app_path('Http/Resources');
        @mkdir($dir, 0755, true);
        $targetPath = "{$dir}/{$this->studly}Resource.php";

        $stub = $this->getStub('resource');
        $stub = $this->replacePlaceholders($stub);

        $this->writeFile($targetPath, $stub, 'Resource');
    }

    // ─── Helpers ──────────────────────────────────────────────────
    protected function getStub(string $stubName): string
    {
        // Check if user published & customized the stubs
        $customPath = base_path("stubs/crud-generator/{$stubName}.stub");
        if (file_exists($customPath)) {
            return file_get_contents($customPath);
        }

        $defaultPath = __DIR__ . "/../stubs/{$stubName}.stub";
        if (!file_exists($defaultPath)) {
            $this->error("Stub not found: {$stubName}.stub");
            exit(1);
        }

        return file_get_contents($defaultPath);
    }

    protected function parseColumns(): void
    {
        $columnsOption = $this->option('columns');
        
        if (empty($columnsOption)) {
            return;
        }
        
        $columns = explode(',', $columnsOption);
        
        foreach ($columns as $column) {
            $parts = explode(':', trim($column));
            $name = $parts[0];
            $type = $parts[1] ?? 'string';
            $options = array_slice($parts, 2); // get everything after type
            
            $this->columns[] = [
                'name' => $name,
                'type' => $type,
                'options' => $options,
            ];
        }
    }

    protected function parseFilesToGenerate(): void
    {
        $filesOption = $this->option('files');
        
        if (!empty($filesOption)) {
            $this->filesToGenerate = array_map('trim', explode(',', $filesOption));
            
            // If model is requested, also add migration
            if (in_array('model', $this->filesToGenerate) && !in_array('migration', $this->filesToGenerate)) {
                $this->filesToGenerate[] = 'migration';
            }
            
            return;
        }
        
        // Default: generate all files
        $this->filesToGenerate = ['migration', 'model', 'repository', 'request', 'controller', 'resource'];
    }

    protected function shouldGenerate(string $file): bool
    {
        return in_array($file, $this->filesToGenerate);
    }

    protected function replacePlaceholders(string $stub): string
    {
        $migrationColumns = '';
        $fillable = '';
        $validationRules = '';
        $softDeletesMigration = '';
        $softDeletesUse = '';
        $softDeletesTrait = '';
        $guardedOrFillable = '';
        
        foreach ($this->columns as $column) {
            // Build migration column line
            $migrationLine = "\$table->{$column['type']}('{$column['name']}')";
            
            // Add options (like unique)
            if (!empty($column['options'])) {
                foreach ($column['options'] as $option) {
                    $migrationLine .= "->{$option}()";
                }
            }
            
            $migrationLine .= ";\n            ";
            $migrationColumns .= $migrationLine;
            
            // Fillable
            if ($fillable !== '') {
                $fillable .= ', ';
            }
            $fillable .= "'{$column['name']}'";
            
            // Validation rules
            if ($validationRules !== '') {
                $validationRules .= ",\n            ";
            }
            $validationRules .= "'{$column['name']}' => 'required'";
        }
        
        if ($this->softDelete) {
            $softDeletesMigration = "\$table->softDeletes();\n            ";
            $softDeletesUse = "use Illuminate\Database\Eloquent\SoftDeletes;\n";
            $softDeletesTrait = "use SoftDeletes;\n    ";
        }
        
        if ($this->useGuarded) {
            $guardedOrFillable = <<<'PHP'
    protected $guarded = [];
PHP;
        } else {
            $guardedOrFillable = <<<PHP
    protected \$fillable = [
        {$fillable}
    ];
PHP;
        }
        
        return str_replace(
            [
                '{{ studly }}',
                '{{ snake }}',
                '{{ camel }}',
                '{{ plural }}',
                '{{ pluralSnake }}',
                '{{ tableName }}',
                '{{ migrationColumns }}',
                '{{ validationRules }}',
                '{{ softDeletesMigration }}',
                '{{ softDeletesUse }}',
                '{{ softDeletesTrait }}',
                '{{ guardedOrFillable }}',
            ],
            [
                $this->studly,
                $this->snake,
                $this->camel,
                $this->plural,
                $this->pluralSnake,
                $this->tableName,
                $migrationColumns,
                $validationRules,
                $softDeletesMigration,
                $softDeletesUse,
                $softDeletesTrait,
                $guardedOrFillable,
            ],
            $stub
        );
    }

    protected function writeFile(string $path, string $content, string $label): void
    {
        if (file_exists($path) && !$this->option('force')) {
            $this->warn("  ⚠  {$label} already exists — skipped: <comment>" . basename($path) . '</comment>');
            return;
        }

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
        $this->line("  ✔  {$label} created: <info>" . basename($path) . '</info>');
    }

    protected function showRouteHint(): void
    {
        $isApi = $this->option('api');
        $this->comment('Add this to your routes file:');

        if ($isApi) {
            $this->line("  Route::apiResource('{$this->pluralSnake}', \\App\\Http\\Controllers\\Api\\{$this->studly}Controller::class);");
        } else {
            $this->line("  Route::resource('{$this->pluralSnake}', \\App\\Http\\Controllers\\{$this->studly}Controller::class);");
        }

        $this->newLine();
        $this->comment('Then run:');
        $this->line('  php artisan migrate');
    }
}
