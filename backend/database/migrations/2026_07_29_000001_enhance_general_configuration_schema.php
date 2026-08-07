<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (! Schema::hasColumn('companies', 'short_name')) {
                    $table->string('short_name', 80)->nullable();
                }
                if (! Schema::hasColumn('companies', 'slogan')) {
                    $table->string('slogan', 160)->nullable();
                }
                if (! Schema::hasColumn('companies', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('companies', 'logo_dark')) {
                    $table->string('logo_dark', 255)->nullable();
                }
                if (! Schema::hasColumn('companies', 'favicon')) {
                    $table->string('favicon', 255)->nullable();
                }
                if (! Schema::hasColumn('companies', 'nrc')) {
                    $table->string('nrc', 50)->nullable();
                }
                if (! Schema::hasColumn('companies', 'commercial_registry')) {
                    $table->string('commercial_registry', 120)->nullable();
                }
                if (! Schema::hasColumn('companies', 'business_activity')) {
                    $table->string('business_activity', 180)->nullable();
                }
                if (! Schema::hasColumn('companies', 'taxpayer_type')) {
                    $table->string('taxpayer_type', 80)->nullable();
                }
                if (! Schema::hasColumn('companies', 'mobile')) {
                    $table->string('mobile', 30)->nullable();
                }
                if (! Schema::hasColumn('companies', 'whatsapp')) {
                    $table->string('whatsapp', 30)->nullable();
                }
                if (! Schema::hasColumn('companies', 'department')) {
                    $table->string('department', 80)->nullable();
                }
                if (! Schema::hasColumn('companies', 'city')) {
                    $table->string('city', 80)->nullable();
                }
                if (! Schema::hasColumn('companies', 'postal_code')) {
                    $table->string('postal_code', 20)->nullable();
                }
                if (! Schema::hasColumn('companies', 'full_address')) {
                    $table->text('full_address')->nullable();
                }
                if (! Schema::hasColumn('companies', 'latitude')) {
                    $table->decimal('latitude', 10, 8)->nullable();
                }
                if (! Schema::hasColumn('companies', 'longitude')) {
                    $table->decimal('longitude', 11, 8)->nullable();
                }
                if (! Schema::hasColumn('companies', 'locale')) {
                    $table->string('locale', 12)->default('es_NI');
                }
                if (! Schema::hasColumn('companies', 'date_format')) {
                    $table->string('date_format', 20)->default('dd/mm/yyyy');
                }
                if (! Schema::hasColumn('companies', 'time_format')) {
                    $table->string('time_format', 10)->default('24h');
                }
            });
        }

        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                if (! Schema::hasColumn('branches', 'mobile')) {
                    $table->string('mobile', 30)->nullable();
                }
                if (! Schema::hasColumn('branches', 'whatsapp')) {
                    $table->string('whatsapp', 30)->nullable();
                }
                if (! Schema::hasColumn('branches', 'country')) {
                    $table->string('country', 80)->nullable();
                }
                if (! Schema::hasColumn('branches', 'department')) {
                    $table->string('department', 80)->nullable();
                }
                if (! Schema::hasColumn('branches', 'city')) {
                    $table->string('city', 80)->nullable();
                }
                if (! Schema::hasColumn('branches', 'postal_code')) {
                    $table->string('postal_code', 20)->nullable();
                }
                if (! Schema::hasColumn('branches', 'full_address')) {
                    $table->text('full_address')->nullable();
                }
                if (! Schema::hasColumn('branches', 'headquarters_unique_key')) {
                    $table->string('headquarters_unique_key', 20)->nullable();
                }
            });

            $this->normalizeHeadquarters();
            $this->createIndexIfMissing('branches', 'branches_company_headquarters_unique', ['company_id', 'headquarters_unique_key'], true);
        }

        if (Schema::hasTable('currencies')) {
            Schema::table('currencies', function (Blueprint $table) {
                if (! Schema::hasColumn('currencies', 'decimal_separator')) {
                    $table->string('decimal_separator', 4)->default('.');
                }
                if (! Schema::hasColumn('currencies', 'thousands_separator')) {
                    $table->string('thousands_separator', 4)->default(',');
                }
                if (! Schema::hasColumn('currencies', 'symbol_position')) {
                    $table->enum('symbol_position', ['before', 'after'])->default('before');
                }
            });
        }

        if (Schema::hasTable('exchange_rates')) {
            Schema::table('exchange_rates', function (Blueprint $table) {
                if (! Schema::hasColumn('exchange_rates', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                }
                if (! Schema::hasColumn('exchange_rates', 'observation')) {
                    $table->text('observation')->nullable();
                }
            });

            $this->createIndexIfMissing('exchange_rates', 'exchange_rates_company_pair_date_index', ['company_id', 'from_currency_id', 'to_currency_id', 'date']);
        }

        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->char('iso2', 2)->unique();
                $table->char('iso3', 3)->nullable()->unique();
                $table->string('name', 100);
                $table->string('phone_code', 10)->nullable();
                $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('timezones')) {
            Schema::create('timezones', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('offset', 10)->nullable();
                $table->char('country_iso2', 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_settings')) {
            Schema::create('company_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('group', 60);
                $table->string('key', 100);
                $table->text('value')->nullable();
                $table->enum('type', ['string', 'integer', 'decimal', 'boolean', 'json'])->default('string');
                $table->boolean('is_encrypted')->default(false);
                $table->timestamps();

                $table->unique(['company_id', 'group', 'key']);
                $table->index(['company_id', 'group']);
            });
        }

        if (! Schema::hasTable('configuration_backups')) {
            Schema::create('configuration_backups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('file_name', 255);
                $table->string('file_path', 255);
                $table->unsignedBigInteger('file_size')->default(0);
                $table->enum('status', ['CREATED', 'RESTORED', 'FAILED'])->default('CREATED');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('restored_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_backups');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('countries');

        if (Schema::hasTable('exchange_rates')) {
            $this->dropIndexIfExists('exchange_rates', 'exchange_rates_company_pair_date_index');

            Schema::table('exchange_rates', function (Blueprint $table) {
                if (Schema::hasColumn('exchange_rates', 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }
                if (Schema::hasColumn('exchange_rates', 'observation')) {
                    $table->dropColumn('observation');
                }
            });
        }

        if (Schema::hasTable('currencies')) {
            Schema::table('currencies', function (Blueprint $table) {
                foreach (['symbol_position', 'thousands_separator', 'decimal_separator'] as $column) {
                    if (Schema::hasColumn('currencies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('branches')) {
            $this->dropIndexIfExists('branches', 'branches_company_headquarters_unique', true);

            Schema::table('branches', function (Blueprint $table) {
                foreach ([
                    'mobile',
                    'whatsapp',
                    'country',
                    'department',
                    'city',
                    'postal_code',
                    'full_address',
                    'headquarters_unique_key',
                ] as $column) {
                    if (Schema::hasColumn('branches', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                foreach ([
                    'short_name',
                    'slogan',
                    'description',
                    'logo_dark',
                    'favicon',
                    'nrc',
                    'commercial_registry',
                    'business_activity',
                    'taxpayer_type',
                    'mobile',
                    'whatsapp',
                    'department',
                    'city',
                    'postal_code',
                    'full_address',
                    'latitude',
                    'longitude',
                    'locale',
                    'date_format',
                    'time_format',
                ] as $column) {
                    if (Schema::hasColumn('companies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function normalizeHeadquarters(): void
    {
        if (! Schema::hasColumn('branches', 'headquarters_unique_key')) {
            return;
        }

        DB::table('branches')->update([
            'headquarters_unique_key' => null,
        ]);

        $companyIds = DB::table('branches')->select('company_id')->distinct()->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $headquarters = DB::table('branches')
                ->where('company_id', $companyId)
                ->where('is_headquarters', true)
                ->orderBy('id')
                ->pluck('id');

            $headquartersId = $headquarters->first()
                ?: DB::table('branches')->where('company_id', $companyId)->orderBy('id')->value('id');

            if (! $headquartersId) {
                continue;
            }

            DB::table('branches')
                ->where('company_id', $companyId)
                ->where('id', '<>', $headquartersId)
                ->update([
                    'is_headquarters' => false,
                    'headquarters_unique_key' => null,
                    'updated_at' => now(),
                ]);

            DB::table('branches')
                ->where('id', $headquartersId)
                ->update([
                    'is_headquarters' => true,
                    'headquarters_unique_key' => 'HEADQUARTERS',
                    'updated_at' => now(),
                ]);
        }
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns, bool $unique = false): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName, $unique) {
            $unique
                ? $blueprint->unique($columns, $indexName)
                : $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName, bool $unique = false): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $unique ? $blueprint->dropUnique($indexName) : $blueprint->dropIndex($indexName));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
    }
};
