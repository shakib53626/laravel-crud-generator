<?php

namespace Shakib53626\CrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'make:crud
                            {name : Model name (e.g. Brand, ProductCategory)}
                            {--columns=  : Columns e.g. name:string,slug:string:unique,description:text,status:boolean}
                            {--table=    : Page group folder inside resources/js/Admin/Pages (e.g. Products, Settings)}
                            {--softdelete : Add soft delete support}
                            {--force     : Overwrite existing files}';

    protected $description = 'Generate full admin CRUD: Migration, Model, Repository, Request, Resource, Controller, Vue pages & Composable';

    // ── Naming variants ───────────────────────────────────────────────────────
    protected string $studly;
    protected string $snake;
    protected string $camel;
    protected string $plural;
    protected string $pluralSnake;
    protected string $pluralCamel;
    protected string $routeParam;
    protected string $tableName;
    protected string $pageGroup;
    protected string $routeGroup;

    protected array $columns    = [];
    protected bool  $softDelete = false;
    protected bool  $hasSlug    = false;
    protected bool  $hasImage   = false;
    protected bool  $hasStatus  = false;
    protected bool  $hasDesc    = false;

    // ─────────────────────────────────────────────────────────────────────────
    public function handle(): int
    {
        $name = $this->argument('name');

        $this->studly      = Str::studly($name);
        $this->snake       = Str::snake($this->studly);
        $this->camel       = Str::camel($this->studly);
        $this->plural      = Str::plural($this->studly);
        $this->pluralSnake = Str::snake($this->plural);
        $this->pluralCamel = Str::camel($this->plural);
        $this->routeParam  = $this->snake;
        $this->tableName   = $this->pluralSnake;
        $this->softDelete  = (bool) $this->option('softdelete');

        $tableOpt         = $this->option('table');
        $this->pageGroup  = $tableOpt ? Str::studly($tableOpt) : $this->plural;
        $this->routeGroup = Str::snake($this->pageGroup);

        $this->parseColumns();

        $this->info("🚀 Generating CRUD for: <comment>{$this->studly}</comment>");
        $this->newLine();

        $this->generateMigration();
        $this->generateModel();
        $this->generateRepository();
        $this->generateRequest();
        $this->generateResource();
        $this->generateController();
        $this->generateVueComposable();
        $this->generateVueFilterSheet();
        $this->generateVueIndex();
        $this->generateVueTrash();

        $this->newLine();
        $this->info('✅ CRUD generated successfully!');
        $this->newLine();
        $this->showRouteHint();

        return self::SUCCESS;
    }

    // ── Migration ─────────────────────────────────────────────────────────────
    protected function generateMigration(): void
    {
        $timestamp  = now()->format('Y_m_d_His');
        $fileName   = "{$timestamp}_create_{$this->tableName}_table.php";
        $targetPath = database_path("migrations/{$fileName}");

        $cols = '';
        foreach ($this->columns as $col) {
            $line = "\$table->{$col['type']}('{$col['name']}')";
            foreach ($col['options'] as $opt) {
                $line .= "->{$opt}()";
            }
            if ($col['type'] === 'boolean') {
                $line .= "->default(false)";
            }
            $cols .= $line . ";\n            ";
        }

        $softDel = $this->softDelete ? "\$table->softDeletes();\n            " : '';

        $stub = $this->getStub('migration');
        $stub = str_replace(
            ['{{ tableName }}', '{{ migrationColumns }}', '{{ softDeletesMigration }}'],
            [$this->tableName, $cols, $softDel],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Migration');
    }

    // ── Model ─────────────────────────────────────────────────────────────────
    protected function generateModel(): void
    {
        $targetPath = app_path("Models/{$this->studly}.php");

        $fillable = collect($this->columns)->pluck('name')
            ->map(fn($n) => "        '{$n}'")->implode(",\n");

        $casts = '';
        foreach ($this->columns as $col) {
            if ($col['type'] === 'boolean') {
                $casts .= "        '{$col['name']}' => 'boolean',\n";
            }
        }

        $docblock = " * @property int \$id\n";
        foreach ($this->columns as $col) {
            $phpType = match ($col['type']) {
                'boolean'                         => 'bool',
                'integer', 'bigInteger', 'unsignedBigInteger' => 'int',
                'decimal', 'float', 'double'      => 'float',
                default                           => 'string',
            };
            $nullable = in_array('nullable', $col['options']) ? '|null' : '';
            $docblock .= " * @property {$phpType}{$nullable} \${$col['name']}\n";
        }

        $softDeletesUse   = $this->softDelete ? "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n" : '';
        $softDeletesTrait = $this->softDelete ? "use SoftDeletes;\n\n    " : '';

        $stub = $this->getStub('model');
        $stub = str_replace(
            ['{{ softDeletesUse }}', '{{ softDeletesTrait }}', '{{ studly }}',
             '{{ tableName }}', '{{ fillableList }}', '{{ castsBlock }}', '{{ modelDocblock }}'],
            [$softDeletesUse, $softDeletesTrait, $this->studly,
             $this->tableName, $fillable, rtrim($casts), $docblock],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Model');
    }

    // ── Repository ────────────────────────────────────────────────────────────
    protected function generateRepository(): void
    {
        @mkdir(app_path('Repositories'), 0755, true);
        $targetPath = app_path("Repositories/{$this->studly}Repository.php");

        $slugCreate = $this->hasSlug
            ? "\$data['slug'] = \$this->uniqueSlug(\$data['slug'] ?? \$data['name']);"
            : '';

        $slugUpdate = $this->hasSlug
            ? "if (isset(\$data['slug'])) {\n            \$data['slug'] = \$this->uniqueSlug(\$data['slug'], \${$this->camel}->id);\n        }"
            : '';

        $softDeleteMethods = '';
        if ($this->softDelete) {
            $s = $this->studly;
            $c = $this->camel;
            $softDeleteMethods = <<<PHP


    public function restore(int \$id): {$s}
    {
        \${$c} = {$s}::onlyTrashed()->findOrFail(\$id);
        \${$c}->restore();

        return \${$c};
    }

    public function forceDelete(int \$id): void
    {
        {$s}::onlyTrashed()->findOrFail(\$id)->forceDelete();
    }

    public function trashed(): \\Illuminate\\Database\\Eloquent\\Collection
    {
        return {$s}::onlyTrashed()->orderBy('name')->get();
    }
PHP;
        }

        $uniqueSlugMethod = '';
        if ($this->hasSlug) {
            $s = $this->studly;
            $uniqueSlugMethod = <<<PHP

    private function uniqueSlug(string \$value, ?int \$exceptId = null): string
    {
        \$slug     = Str::slug(\$value);
        \$original = \$slug;
        \$count    = 1;

        \$query = {$s}::where('slug', \$slug);
        if (\$exceptId) \$query->where('id', '!=', \$exceptId);

        while (\$query->exists()) {
            \$slug  = "{\$original}-{\$count}";
            \$count++;
            \$query = {$s}::where('slug', \$slug);
            if (\$exceptId) \$query->where('id', '!=', \$exceptId);
        }

        return \$slug;
    }
PHP;
        }

        $stub = $this->getStub('repository');
        $stub = str_replace(
            ['{{ studly }}', '{{ camel }}', '{{ slugCreateBlock }}', '{{ slugUpdateBlock }}',
             '{{ softDeleteMethods }}', '{{ uniqueSlugMethod }}'],
            [$this->studly, $this->camel, $slugCreate, $slugUpdate,
             $softDeleteMethods, $uniqueSlugMethod],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Repository');
    }

    // ── Request ───────────────────────────────────────────────────────────────
    protected function generateRequest(): void
    {
        @mkdir(app_path('Http/Requests'), 0755, true);
        $targetPath = app_path("Http/Requests/{$this->studly}Request.php");

        $rules = '';
        foreach ($this->columns as $col) {
            $r     = $this->columnToRule($col);
            $rules .= "            '{$col['name']}' => {$r},\n";
        }

        $stub = $this->getStub('request');
        $stub = str_replace(
            ['{{ studly }}', '{{ camel }}', '{{ routeParam }}', '{{ validationRules }}'],
            [$this->studly, $this->camel, $this->routeParam, rtrim($rules)],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Request');
    }

    // ── Resource ──────────────────────────────────────────────────────────────
    protected function generateResource(): void
    {
        @mkdir(app_path('Http/Resources'), 0755, true);
        $targetPath = app_path("Http/Resources/{$this->studly}Resource.php");

        $fields = '';
        foreach ($this->columns as $col) {
            if ($col['name'] === 'image') {
                $fields .= "            '{$col['name']}' => \$this->fullPath(\$this->{$col['name']}),\n";
            } elseif ($col['type'] === 'boolean') {
                $fields .= "            '{$col['name']}' => (bool) \$this->{$col['name']},\n";
            } else {
                $fields .= "            '{$col['name']}' => \$this->{$col['name']},\n";
            }
        }

        $stub = $this->getStub('resource');
        $stub = str_replace(
            ['{{ studly }}', '{{ resourceFields }}'],
            [$this->studly, $fields],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Resource');
    }

    // ── Controller ────────────────────────────────────────────────────────────
    protected function generateController(): void
    {
        @mkdir(app_path('Http/Controllers/Admin'), 0755, true);
        $targetPath = app_path("Http/Controllers/Admin/{$this->studly}Controller.php");

        $stripStore  = $this->hasImage ? "\$data['image'] = \$this->stripToPath(\$data['image'] ?? null);" : '';
        $stripUpdate = $this->hasImage ? "\$data['image'] = \$this->stripToPath(\$data['image'] ?? null);" : '';

        $stripHelper = '';
        if ($this->hasImage) {
            $stripHelper = <<<'PHP'

    private function stripToPath(?string $url): ?string
    {
        if (!$url) return null;

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return $url;
        }

        $storageUrl = rtrim(\Illuminate\Support\Facades\Storage::disk('public')->url(''), '/');
        $path       = str_starts_with($url, $storageUrl)
            ? ltrim(substr($url, strlen($storageUrl)), '/')
            : parse_url($url, PHP_URL_PATH);

        return ltrim($path, '/');
    }
PHP;
        }

        $stub = $this->getStub('controller');
        $stub = str_replace(
            ['{{ studly }}', '{{ camel }}', '{{ snake }}', '{{ plural }}',
             '{{ pluralSnake }}', '{{ pluralCamel }}', '{{ pageGroup }}',
             '{{ routeGroup }}', '{{ routePlural }}',
             '{{ stripImageStore }}', '{{ stripImageUpdate }}', '{{ stripImageHelper }}'],
            [$this->studly, $this->camel, $this->snake, $this->plural,
             $this->pluralSnake, $this->pluralCamel, $this->pageGroup,
             $this->routeGroup, $this->pluralSnake,
             $stripStore, $stripUpdate, $stripHelper],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Controller');
    }

    // ── Vue Composable ────────────────────────────────────────────────────────
    protected function generateVueComposable(): void
    {
        $dir = resource_path('js/Admin/Composables');
        @mkdir($dir, 0755, true);
        $targetPath = "{$dir}/use{$this->plural}.js";

        $formFields = '';
        foreach ($this->columns as $col) {
            $default = match ($col['type']) {
                'boolean'        => 'false',
                'integer', 'bigInteger' => '0',
                default          => "''",
            };
            if (in_array($col['name'], ['status', 'is_active'])) $default = 'true';
            if ($col['name'] === 'image') $default = 'null';
            $formFields .= "    {$col['name']}: {$default},\n";
        }

        $fill = '';
        foreach ($this->columns as $col) {
            $suffix = match (true) {
                $col['name'] === 'image'        => " ?? null",
                $col['type'] === 'string'       => " ?? ''",
                default                         => '',
            };
            $fill .= "    form.{$col['name']}    = {$this->camel}.{$col['name']}{$suffix}\n";
        }

        $stub = $this->getStub('vue-composable');
        $stub = str_replace(
            ['{{ plural }}', '{{ camel }}', '{{ routeGroup }}', '{{ routePlural }}',
             '{{ formFields }}', '{{ formFillFromEdit }}'],
            [$this->plural, $this->camel, $this->routeGroup, $this->pluralSnake,
             rtrim($formFields), rtrim($fill)],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Vue Composable');
    }

    // ── Vue Filter Sheet ──────────────────────────────────────────────────────
    protected function generateVueFilterSheet(): void
    {
        $dir = resource_path("js/Admin/Components/{$this->pageGroup}/{$this->plural}");
        @mkdir($dir, 0755, true);
        $targetPath = "{$dir}/{$this->studly}FilterSheet.vue";

        $stub = $this->getStub('vue-filter-sheet');
        $stub = str_replace(['{{ plural }}', '{{ studly }}'], [$this->plural, $this->studly], $stub);

        $this->writeFile($targetPath, $stub, 'Vue FilterSheet');
    }

    // ── Vue Index ─────────────────────────────────────────────────────────────
    protected function generateVueIndex(): void
    {
        $dir = resource_path("js/Admin/Pages/{$this->pageGroup}/{$this->plural}");
        @mkdir($dir, 0755, true);
        $targetPath = "{$dir}/Index.vue";

        $slugColumn = $this->hasSlug
            ? '<p class="text-xs text-lightgrey font-mono">{{ row.slug }}</p>'
            : '';

        $descCol = $this->hasDesc
            ? implode("\n", [
                '<TableColumn label="Description" prop="description">',
                '            <template #default="{ value }">',
                '                <span class="text-sm text-semidark line-clamp-1">{{ value || \'—\' }}</span>',
                '            </template>',
                '        </TableColumn>',
            ])
            : '';

        $imageBlock = $this->hasImage ? $this->buildImageFormBlock() : '';

        $slugField = $this->hasSlug
            ? "<Input\n            v-model=\"form.slug\"\n            label=\"Slug\"\n            placeholder=\"auto-generated\"\n            hint=\"Used in URLs — lowercase, hyphens only\"\n        />"
            : '';

        $descField = $this->hasDesc
            ? "<Textarea\n            v-model=\"form.description\"\n            label=\"Description\"\n            placeholder=\"Short description...\"\n            :rows=\"3\"\n        />"
            : '';

        $mediaBlock = $this->hasImage
            ? "<MediaLibrary\n        v-model=\"showMediaLibrary\"\n        accept=\"image\"\n        title=\"Select {$this->studly} Image\"\n        @select=\"onImageSelect\"\n    />"
            : '';

        $stub = $this->getStub('vue-index');
        $stub = str_replace(
            ['{{ studly }}', '{{ snake }}', '{{ plural }}', '{{ pluralSnake }}',
             '{{ pluralCamel }}', '{{ pageGroup }}', '{{ routeGroup }}', '{{ routePlural }}',
             '{{ slugColumn }}', '{{ descriptionColumn }}',
             '{{ imageFormBlock }}', '{{ slugFormField }}', '{{ descriptionFormField }}',
             '{{ mediaLibraryBlock }}'],
            [$this->studly, $this->snake, $this->plural, $this->pluralSnake,
             $this->pluralCamel, $this->pageGroup, $this->routeGroup, $this->pluralSnake,
             $slugColumn, $descCol,
             $imageBlock, $slugField, $descField,
             $mediaBlock],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Vue Index');
    }

    // ── Vue Trash ─────────────────────────────────────────────────────────────
    protected function generateVueTrash(): void
    {
        $dir = resource_path("js/Admin/Pages/{$this->pageGroup}/{$this->plural}");
        @mkdir($dir, 0755, true);
        $targetPath = "{$dir}/Trash.vue";

        $slugColumn = $this->hasSlug
            ? '<p class="text-xs text-lightgrey font-mono">{{ row.slug }}</p>'
            : '';

        $descCol = $this->hasDesc
            ? implode("\n", [
                '<TableColumn label="Description" prop="description">',
                '            <template #default="{ value }">',
                '                <span class="text-sm text-semidark line-clamp-1">{{ value || \'—\' }}</span>',
                '            </template>',
                '        </TableColumn>',
            ])
            : '';

        $stub = $this->getStub('vue-trash');
        $stub = str_replace(
            ['{{ studly }}', '{{ snake }}', '{{ plural }}', '{{ pluralSnake }}',
             '{{ pluralCamel }}', '{{ camel }}', '{{ routeGroup }}', '{{ routePlural }}',
             '{{ slugColumn }}', '{{ descriptionColumn }}'],
            [$this->studly, $this->snake, $this->plural, $this->pluralSnake,
             $this->pluralCamel, $this->camel, $this->routeGroup, $this->pluralSnake,
             $slugColumn, $descCol],
            $stub
        );

        $this->writeFile($targetPath, $stub, 'Vue Trash');
    }

    // ── Shared helpers ────────────────────────────────────────────────────────
    protected function parseColumns(): void
    {
        $opt = $this->option('columns');
        if (empty($opt)) return;

        foreach (explode(',', $opt) as $raw) {
            $parts   = explode(':', trim($raw));
            $name    = $parts[0];
            $type    = $parts[1] ?? 'string';
            $options = array_slice($parts, 2);

            if ($name === 'slug')                              $this->hasSlug   = true;
            if ($name === 'image')                             $this->hasImage  = true;
            if (in_array($name, ['status', 'is_active']))      $this->hasStatus = true;
            if (in_array($name, ['description', 'body']))      $this->hasDesc   = true;

            $this->columns[] = ['name' => $name, 'type' => $type, 'options' => $options];
        }
    }

    protected function columnToRule(array $col): string
    {
        $rules = [];

        $rules[] = in_array('nullable', $col['options']) ? "'nullable'" : "'required'";

        $rules[] = match ($col['type']) {
            'boolean'                                  => "'boolean'",
            'integer', 'bigInteger', 'unsignedBigInteger' => "'integer'",
            'decimal', 'float', 'double'               => "'numeric'",
            'text', 'longText', 'mediumText'            => "'string'",
            default                                    => "'string', 'max:255'",
        };

        if (in_array('unique', $col['options'])) {
            $rules[] = "Rule::unique('{$this->tableName}', '{$col['name']}')->ignore(\${$this->camel}Id)";
        }

        return '[' . implode(', ', $rules) . ']';
    }

    protected function buildImageFormBlock(): string
    {
        return <<<VUE
<!-- Image -->
            <div class="flex flex-col gap-2">
                <label class="text-xs font-medium text-dark">Image</label>
                <div class="flex items-center gap-4">
                    <div
                        class="w-16 h-16 rounded-xl border-2 border-dashed border-stroke2 flex items-center justify-center overflow-hidden shrink-0"
                        :class="form.image ? '' : (form.name ? avatarColor(editTarget?.id ?? 0) : 'bg-page')"
                    >
                        <img v-if="form.image" :src="form.image" class="w-full h-full object-cover" alt="" />
                        <span v-else-if="form.name" class="text-lg font-bold">{{ initials(form.name) }}</span>
                        <FolderOpen v-else class="w-6 h-6 text-lightgrey" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="sm" @click="showMediaLibrary = true">
                                {{ form.image ? 'Change Image' : 'Upload Image' }}
                            </Button>
                            <button
                                v-if="form.image"
                                type="button"
                                class="w-7 h-7 flex items-center justify-center rounded-md text-semidark hover:bg-error/10 hover:text-error transition-colors cursor-pointer"
                                @click="form.image = null"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <p class="text-xs text-semidark">PNG, JPG, SVG up to 2MB</p>
                    </div>
                </div>
            </div>
VUE;
    }

    protected function getStub(string $name): string
    {
        $custom  = base_path("stubs/crud-generator/{$name}.stub");
        $default = __DIR__ . "/../stubs/{$name}.stub";

        if (file_exists($custom))  return file_get_contents($custom);
        if (file_exists($default)) return file_get_contents($default);

        $this->error("Stub not found: {$name}.stub");
        exit(1);
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
        $this->comment('── Add to your admin routes file inside the products/prefix group: ──');
        $this->newLine();

        $routeStub = $this->getStub('routes');
        $routeStub = str_replace(
            ['{{ studly }}', '{{ routePlural }}', '{{ routeParam }}'],
            [$this->studly, $this->pluralSnake, $this->routeParam],
            $routeStub
        );
        $this->line($routeStub);

        $this->comment('Also add the use statement at the top of the routes file:');
        $this->line("  use App\\Http\\Controllers\\Admin\\{$this->studly}Controller;");
        $this->newLine();
        $this->comment('Then run:');
        $this->line('  php artisan migrate');
        $this->newLine();
    }
}
