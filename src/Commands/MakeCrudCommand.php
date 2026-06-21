<?php

namespace Shakib53626\CrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud
                            {name : Model name (e.g. Post, ProductCategory)}
                            {--api : Generate API controller instead of web resource controller}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate CRUD scaffold: Migration, Model, Controller, Requests, and API Resource';

    protected string $modelName;
    protected string $studly;
    protected string $snake;
    protected string $camel;
    protected string $plural;
    protected string $pluralSnake;
    protected string $tableName;

    public function handle(): int
    {
        $this->modelName = $this->argument('name');
        $this->studly    = Str::studly($this->modelName);
        $this->snake     = Str::snake($this->studly);
        $this->camel     = Str::camel($this->studly);
        $this->plural    = Str::plural($this->studly);
        $this->pluralSnake = Str::snake($this->plural);
        $this->tableName = $this->pluralSnake;

        $this->info("🚀 Generating CRUD for: <comment>{$this->studly}</comment>");
        $this->newLine();

        $this->generateMigration();
        $this->generateModel();
        $this->generateController();
        $this->generateRequests();
        $this->generateResource();

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

    // ─── Requests ─────────────────────────────────────────────────
    protected function generateRequests(): void
    {
        $dir = app_path("Http/Requests/{$this->studly}");
        @mkdir($dir, 0755, true);

        foreach (['store', 'update'] as $type) {
            $stub       = $this->getStub("request-{$type}");
            $stub       = $this->replacePlaceholders($stub);
            $targetPath = "{$dir}/" . ucfirst($type) . "{$this->studly}Request.php";
            $this->writeFile($targetPath, $stub, ucfirst($type) . 'Request');
        }
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

    protected function replacePlaceholders(string $stub): string
    {
        return str_replace(
            [
                '{{ studly }}',
                '{{ snake }}',
                '{{ camel }}',
                '{{ plural }}',
                '{{ pluralSnake }}',
                '{{ tableName }}',
            ],
            [
                $this->studly,
                $this->snake,
                $this->camel,
                $this->plural,
                $this->pluralSnake,
                $this->tableName,
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
