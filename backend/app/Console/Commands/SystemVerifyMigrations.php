<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SystemVerifyMigrations extends Command
{
    protected $signature = 'system:verify-migrations
        {--no-generate : Solo reporta diferencias sin crear nuevas migraciones}';

    protected $description = 'Audita migraciones, seeders, indices y llaves foraneas del ERP.';

    private const INTERNAL_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
    ];

    public function handle(): int
    {
        $this->info('Auditoria automatica de migraciones y seeders');

        try {
            $schemaTables = $this->currentTables();
            $migrationTables = $this->scanMigrationTables();
            $seederUsage = $this->scanSeeders();
        } catch (Throwable $exception) {
            $this->error('No se pudo iniciar la auditoria: '.$exception->getMessage());

            return self::FAILURE;
        }

        $missingTables = [];
        $missingColumns = [];
        $typeWarnings = [];

        foreach ($seederUsage as $tableName => $usage) {
            if (! in_array($tableName, $schemaTables, true)) {
                $missingTables[] = $tableName;
                continue;
            }

            $columns = $this->columnsFor($tableName);

            foreach ($usage['columns'] as $columnName) {
                if (! array_key_exists($columnName, $columns)) {
                    $missingColumns[$tableName][] = $columnName;
                    continue;
                }

                if (! $this->columnLooksCompatible($columnName, $columns[$columnName])) {
                    $typeWarnings[] = $tableName.'.'.$columnName.' parece no coincidir con el tipo esperado.';
                }
            }
        }

        $unseededTables = collect($migrationTables['created'])
            ->reject(fn (string $table) => in_array($table, self::INTERNAL_TABLES, true))
            ->reject(fn (string $table) => array_key_exists($table, $seederUsage))
            ->values();

        $relationWarnings = $this->relationWarnings($schemaTables);
        $indexWarnings = $this->indexWarnings($schemaTables);

        $this->report('Tablas en migraciones', count($migrationTables['created']));
        $this->report('Tablas usadas por seeders', count($seederUsage));
        $this->report('Tablas actuales en la base', count($schemaTables));

        $this->printList('Tablas usadas por seeders que no existen', $missingTables, 'error');
        $this->printMissingColumns($missingColumns);
        $this->printList('Posibles tipos incompatibles', $typeWarnings, 'warn');
        $this->printList('Tablas migradas sin datos en seeders', $unseededTables->all(), 'warn', 25);
        $this->printList('Columnas *_id sin foreign key detectada', $relationWarnings, 'warn', 25);
        $this->printList('Foreign keys sin indice visible', $indexWarnings, 'warn', 25);

        if ($missingColumns !== [] && ! $this->option('no-generate')) {
            $path = $this->generateMigration($missingColumns, $schemaTables);
            $this->warn('Se genero una migracion correctiva: '.Str::after($path, base_path().DIRECTORY_SEPARATOR));
            $this->line('Ejecuta php artisan migrate para aplicar los campos faltantes.');
        }

        if ($missingTables !== []) {
            return self::FAILURE;
        }

        if ($missingColumns !== [] && $this->option('no-generate')) {
            return self::FAILURE;
        }

        $this->info('Auditoria finalizada.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function currentTables(): array
    {
        return collect(Schema::getTables())
            ->map(fn (array $table) => $table['name'] ?? $table['table'] ?? $table['TABLE_NAME'] ?? null)
            ->filter()
            ->map(fn (string $table) => Str::afterLast($table, '.'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array{created: list<string>, touched: list<string>}
     */
    private function scanMigrationTables(): array
    {
        $created = collect();
        $touched = collect();

        foreach (File::files(database_path('migrations')) as $file) {
            $content = File::get($file->getPathname());

            preg_match_all('/Schema::create\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $content, $createMatches);
            preg_match_all('/Schema::table\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $content, $tableMatches);

            $created = $created->merge($createMatches[1] ?? []);
            $touched = $touched->merge($tableMatches[1] ?? []);
        }

        return [
            'created' => $created->unique()->sort()->values()->all(),
            'touched' => $touched->merge($created)->unique()->sort()->values()->all(),
        ];
    }

    /**
     * @return array<string, array{columns: list<string>, seeders: list<string>}>
     */
    private function scanSeeders(): array
    {
        $usage = [];

        foreach (File::files(database_path('seeders')) as $file) {
            $content = File::get($file->getPathname());
            preg_match_all(
                '/DB::table\(\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\)/',
                $content,
                $matches,
                PREG_OFFSET_CAPTURE
            );

            $count = count($matches[0] ?? []);

            for ($i = 0; $i < $count; $i++) {
                $tableName = $matches[1][$i][0];
                $start = $matches[0][$i][1];
                $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($content);
                $block = substr($content, $start, $end - $start);

                $usage[$tableName]['seeders'][$file->getFilename()] = true;

                foreach ($this->extractColumnsFromSeederBlock($block) as $columnName) {
                    $usage[$tableName]['columns'][$columnName] = true;
                }
            }
        }

        return collect($usage)
            ->map(fn (array $item) => [
                'columns' => collect(array_keys($item['columns'] ?? []))->sort()->values()->all(),
                'seeders' => collect(array_keys($item['seeders'] ?? []))->sort()->values()->all(),
            ])
            ->sortKeys()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function extractColumnsFromSeederBlock(string $block): array
    {
        $columns = collect();

        preg_match_all('/[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]\s*=>/', $block, $arrayKeyMatches);
        $columns = $columns->merge($arrayKeyMatches[1] ?? []);

        preg_match_all(
            '/->(?:where|orWhere|whereNull|whereNotNull|whereIn|whereNotIn|value|pluck)\(\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]/',
            $block,
            $methodMatches
        );
        $columns = $columns->merge($methodMatches[1] ?? []);

        preg_match_all('/->select\(\s*\[([^\]]+)\]/s', $block, $selectMatches);
        foreach ($selectMatches[1] ?? [] as $selectBlock) {
            preg_match_all('/[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]/', $selectBlock, $selectedColumns);
            $columns = $columns->merge($selectedColumns[1] ?? []);
        }

        return $columns
            ->reject(fn (string $column) => in_array($column, ['class'], true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function columnsFor(string $tableName): array
    {
        return collect(Schema::getColumns($tableName))
            ->keyBy(fn (array $column) => $column['name'] ?? '')
            ->filter(fn (array $column, string $name) => $name !== '')
            ->all();
    }

    private function columnLooksCompatible(string $columnName, array $metadata): bool
    {
        $type = Str::lower((string) ($metadata['type_name'] ?? $metadata['type'] ?? ''));

        if ($type === '') {
            return true;
        }

        return match ($this->expectedFamily($columnName)) {
            'boolean' => Str::contains($type, ['bool', 'tinyint']),
            'integer' => Str::contains($type, ['int']),
            'decimal' => Str::contains($type, ['decimal', 'double', 'float', 'numeric']),
            'date' => Str::contains($type, ['date', 'time']),
            'text' => Str::contains($type, ['text', 'char', 'varchar', 'string']),
            default => true,
        };
    }

    private function expectedFamily(string $columnName): ?string
    {
        if (Str::startsWith($columnName, ['is_', 'has_', 'requires_', 'allow_'])
            || in_array($columnName, ['cash', 'card', 'bank', 'check', 'digital_wallet', 'credit', 'other'], true)) {
            return 'boolean';
        }

        if (Str::endsWith($columnName, '_id')
            || in_array($columnName, [
                'days',
                'credit_days',
                'decimal_places',
                'discount_days',
                'installments',
                'month',
                'pad_length',
                'period_number',
                'rating',
                'sort_order',
                'step_order',
                'useful_life_years',
                'warranty_days',
                'year',
            ], true)
            || Str::endsWith($columnName, ['_count', '_days', '_number', '_order'])) {
            return 'integer';
        }

        if (Str::contains($columnName, ['amount', 'balance', 'cost', 'limit', 'percent', 'price', 'rate', 'salary', 'stock', 'total'])) {
            return 'decimal';
        }

        if (Str::endsWith($columnName, '_at') || Str::contains($columnName, ['date'])) {
            return 'date';
        }

        if (Str::contains($columnName, ['address', 'description', 'notes'])) {
            return 'text';
        }

        return null;
    }

    /**
     * @param  list<string>  $schemaTables
     * @return list<string>
     */
    private function relationWarnings(array $schemaTables): array
    {
        $warnings = [];

        foreach ($schemaTables as $tableName) {
            if (in_array($tableName, self::INTERNAL_TABLES, true)) {
                continue;
            }

            $foreignColumns = $this->foreignKeyColumns($tableName);

            foreach (array_keys($this->columnsFor($tableName)) as $columnName) {
                if (! $this->shouldHaveForeignKey($columnName)) {
                    continue;
                }

                if (! in_array($columnName, $foreignColumns, true)) {
                    $warnings[] = $tableName.'.'.$columnName;
                }
            }
        }

        sort($warnings);

        return $warnings;
    }

    /**
     * @param  list<string>  $schemaTables
     * @return list<string>
     */
    private function indexWarnings(array $schemaTables): array
    {
        $warnings = [];

        foreach ($schemaTables as $tableName) {
            if (in_array($tableName, self::INTERNAL_TABLES, true)) {
                continue;
            }

            $indexes = $this->indexesFor($tableName);

            foreach ($this->foreignKeyColumns($tableName) as $columnName) {
                $hasIndex = collect($indexes)->contains(function (array $index) use ($columnName) {
                    $columns = $index['columns'] ?? [];

                    return is_array($columns) && in_array($columnName, $columns, true);
                });

                if (! $hasIndex) {
                    $warnings[] = $tableName.'.'.$columnName;
                }
            }
        }

        sort($warnings);

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function foreignKeyColumns(string $tableName): array
    {
        try {
            return collect(Schema::getForeignKeys($tableName))
                ->flatMap(fn (array $foreignKey) => $foreignKey['columns'] ?? [])
                ->unique()
                ->sort()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function indexesFor(string $tableName): array
    {
        try {
            return Schema::getIndexes($tableName);
        } catch (Throwable) {
            return [];
        }
    }

    private function shouldHaveForeignKey(string $columnName): bool
    {
        if (in_array($columnName, ['id', 'record_id', 'reference_id', 'payable_id', 'tax_id'], true)) {
            return false;
        }

        return Str::endsWith($columnName, '_id')
            || in_array($columnName, [
                'approved_by',
                'assigned_to',
                'created_by',
                'performed_by',
                'requested_by',
                'salesperson_id',
                'updated_by',
                'uploaded_by',
            ], true);
    }

    /**
     * @param  array<string, list<string>>  $missingColumns
     * @param  list<string>  $schemaTables
     */
    private function generateMigration(array $missingColumns, array $schemaTables): string
    {
        $timestamp = now()->format('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_system_verify_missing_seeder_columns.php");
        $counter = 1;

        while (File::exists($path)) {
            $path = database_path("migrations/{$timestamp}_system_verify_missing_seeder_columns_{$counter}.php");
            $counter++;
        }

        File::put($path, $this->buildMigration($missingColumns, $schemaTables));

        return $path;
    }

    /**
     * @param  array<string, list<string>>  $missingColumns
     * @param  list<string>  $schemaTables
     */
    private function buildMigration(array $missingColumns, array $schemaTables): string
    {
        $up = [];
        $down = [];

        foreach ($missingColumns as $tableName => $columns) {
            $up[] = "        if (Schema::hasTable('{$tableName}')) {";
            $up[] = "            Schema::table('{$tableName}', function (Blueprint \$table) {";

            $down[] = "        if (Schema::hasTable('{$tableName}')) {";
            $down[] = "            Schema::table('{$tableName}', function (Blueprint \$table) {";

            foreach ($columns as $columnName) {
                $definition = $this->columnDefinition($tableName, $columnName, $schemaTables);

                $up[] = "                if (! Schema::hasColumn('{$tableName}', '{$columnName}')) {";
                $up[] = '                    '.$definition['up'];
                $up[] = '                }';

                $down[] = "                if (Schema::hasColumn('{$tableName}', '{$columnName}')) {";
                $down[] = '                    '.$definition['down'];
                $down[] = '                }';
            }

            $up[] = '            });';
            $up[] = '        }';
            $up[] = '';

            $down[] = '            });';
            $down[] = '        }';
            $down[] = '';
        }

        return "<?php\n\n"
            ."use Illuminate\\Database\\Migrations\\Migration;\n"
            ."use Illuminate\\Database\\Schema\\Blueprint;\n"
            ."use Illuminate\\Support\\Facades\\Schema;\n\n"
            ."return new class extends Migration\n"
            ."{\n"
            ."    public function up(): void\n"
            ."    {\n"
            .implode("\n", $up)
            ."    }\n\n"
            ."    public function down(): void\n"
            ."    {\n"
            .implode("\n", $down)
            ."    }\n"
            ."};\n";
    }

    /**
     * @param  list<string>  $schemaTables
     * @return array{up: string, down: string}
     */
    private function columnDefinition(string $tableName, string $columnName, array $schemaTables): array
    {
        $foreignTable = $this->foreignTableFor($tableName, $columnName, $schemaTables);

        if ($foreignTable !== null) {
            return [
                'up' => "\$table->foreignId('{$columnName}')->nullable()->constrained('{$foreignTable}')->nullOnDelete();",
                'down' => "\$table->dropConstrainedForeignId('{$columnName}');",
            ];
        }

        $up = match (true) {
            in_array($columnName, ['created_at', 'updated_at', 'deleted_at'], true) => "\$table->timestamp('{$columnName}')->nullable();",
            $columnName === 'uuid' => "\$table->uuid('uuid')->nullable();",
            $columnName === 'code' => "\$table->string('code', 50)->nullable();",
            $columnName === 'name' => "\$table->string('name', 150)->nullable();",
            Str::endsWith($columnName, '_email') || $columnName === 'email' => "\$table->string('{$columnName}', 120)->nullable();",
            Str::contains($columnName, ['address', 'description', 'notes']) => "\$table->text('{$columnName}')->nullable();",
            $this->expectedFamily($columnName) === 'boolean' => "\$table->boolean('{$columnName}')->default(false);",
            $columnName === 'sort_order' => "\$table->unsignedSmallInteger('sort_order')->default(100);",
            $this->expectedFamily($columnName) === 'integer' => "\$table->integer('{$columnName}')->nullable();",
            $this->expectedFamily($columnName) === 'decimal' => "\$table->decimal('{$columnName}', 18, 4)->default(0);",
            $this->expectedFamily($columnName) === 'date' => "\$table->dateTime('{$columnName}')->nullable();",
            default => "\$table->string('{$columnName}')->nullable();",
        };

        return [
            'up' => $up,
            'down' => "\$table->dropColumn('{$columnName}');",
        ];
    }

    /**
     * @param  list<string>  $schemaTables
     */
    private function foreignTableFor(string $tableName, string $columnName, array $schemaTables): ?string
    {
        $map = [
            'approved_by' => 'users',
            'assigned_to' => 'employees',
            'branch_id' => 'branches',
            'company_id' => 'companies',
            'created_by' => 'users',
            'currency_id' => 'currencies',
            'customer_id' => 'customers',
            'department_id' => 'departments',
            'payment_method_id' => 'payment_methods',
            'payment_term_id' => 'payment_terms',
            'performed_by' => 'users',
            'requested_by' => 'users',
            'role_id' => 'roles',
            'salesperson_id' => 'employees',
            'supplier_id' => 'suppliers',
            'updated_by' => 'users',
            'uploaded_by' => 'users',
            'user_id' => 'users',
            'warehouse_id' => 'warehouses',
        ];

        if ($columnName === 'parent_id') {
            return $tableName;
        }

        if (! array_key_exists($columnName, $map) && ! Str::endsWith($columnName, '_id')) {
            return null;
        }

        $foreignTable = $map[$columnName] ?? Str::plural(Str::beforeLast($columnName, '_id'));

        return in_array($foreignTable, $schemaTables, true) ? $foreignTable : null;
    }

    private function report(string $label, int $value): void
    {
        $this->line(sprintf(' - %s: %d', $label, $value));
    }

    /**
     * @param  list<string>  $items
     */
    private function printList(string $title, array $items, string $level = 'line', int $limit = 15): void
    {
        if ($items === []) {
            $this->line(" - {$title}: OK");
            return;
        }

        $method = $level === 'error' ? 'error' : ($level === 'warn' ? 'warn' : 'line');
        $this->{$method}(" - {$title}: ".count($items));

        foreach (array_slice($items, 0, $limit) as $item) {
            $this->line('   * '.$item);
        }

        if (count($items) > $limit) {
            $this->line('   * ... '.(count($items) - $limit).' mas');
        }
    }

    /**
     * @param  array<string, list<string>>  $missingColumns
     */
    private function printMissingColumns(array $missingColumns): void
    {
        if ($missingColumns === []) {
            $this->line(' - Campos usados por seeders y ausentes en migraciones: OK');
            return;
        }

        $total = collect($missingColumns)->sum(fn (array $columns) => count($columns));
        $this->warn(" - Campos usados por seeders y ausentes en migraciones: {$total}");

        foreach ($missingColumns as $tableName => $columns) {
            $this->line('   * '.$tableName.': '.implode(', ', $columns));
        }
    }
}
