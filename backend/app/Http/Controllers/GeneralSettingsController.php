<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGeneralSettingsRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ConfigurationBackup;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Timezone;
use App\Services\CompanySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class GeneralSettingsController extends Controller
{
    public function __construct(private readonly CompanySettingsService $settings)
    {
    }

    public function publicBranding(): JsonResponse
    {
        $company = Company::query()
            ->active()
            ->orderBy('id')
            ->first();

        if (! $company) {
            return response()->json([
                'data' => [
                    'company_name' => config('app.name', 'ERP'),
                    'welcome_text' => 'Bienvenido',
                    'logo_url' => null,
                    'logo_dark_url' => null,
                    'favicon_url' => null,
                    'notifications_enabled' => true,
                ],
            ]);
        }

        $this->applyTimezone((string) $company->timezone);

        return response()->json([
            'data' => $this->brandingPayload($company),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['ver', 'index', 'view', 'manage', 'administrar']);

        $companyId = (int) $request->user()->company_id;
        $this->settings->seedDefaults($companyId);

        return response()->json([
            'data' => $this->payload($companyId),
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['editar', 'update', 'store', 'manage', 'administrar']);

        $companyId = (int) $request->user()->company_id;
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $companyId) {
            if (isset($validated['company'])) {
                $this->updateCompany($request, $companyId, $validated['company']);
            }

            if (isset($validated['branches'])) {
                $this->setHeadquarters($companyId, (int) $validated['branches']['headquarters_id']);
            }

            if (isset($validated['finance'])) {
                $this->updateFinance($companyId, $validated['finance']);
            }

            $settingGroups = $this->settingGroupsFrom($companyId, $validated);

            if ($settingGroups !== []) {
                $this->settings->setMany($companyId, $settingGroups);
            }

            $this->writeAudit($request, 'UPDATE', $companyId, null, $validated);
        });

        $this->settings->clearCache($companyId);
        Cache::forget('general_settings.public_branding');

        $timezone = (string) DB::table('companies')->where('id', $companyId)->value('timezone');
        $this->applyTimezone($timezone);

        return response()->json([
            'message' => 'Configuracion general guardada correctamente.',
            'data' => $this->payload($companyId),
        ]);
    }

    public function uploadLogos(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['editar', 'update', 'store', 'manage', 'administrar']);

        $validated = $request->validate([
            'logo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:4096'],
            'logo_dark_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:4096'],
            'favicon_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg,ico', 'max:1024'],
        ], [
            'logo_file.mimes' => 'El logo del menu debe ser JPG, PNG, WEBP, GIF o SVG.',
            'logo_dark_file.mimes' => 'El logo para login debe ser JPG, PNG, WEBP, GIF o SVG.',
            'favicon_file.mimes' => 'El favicon debe ser JPG, PNG, WEBP, GIF, SVG o ICO.',
        ]);

        abort_if(
            ! $request->hasFile('logo_file')
            && ! $request->hasFile('logo_dark_file')
            && ! $request->hasFile('favicon_file'),
            422,
            'Selecciona al menos una imagen para actualizar.',
        );

        $companyId = (int) $request->user()->company_id;

        DB::transaction(function () use ($request, $companyId) {
            $this->updateCompany($request, $companyId, []);
            $this->writeAudit($request, 'UPDATE', $companyId, null, ['logos_updated' => true]);
        });

        $this->settings->clearCache($companyId);
        Cache::forget('general_settings.public_branding');

        return response()->json([
            'message' => 'Logotipos actualizados correctamente.',
            'data' => $this->payload($companyId),
        ]);
    }

    public function updateCurrency(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['editar', 'update', 'store', 'manage', 'administrar']);

        $validated = $request->validate([
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'decimal_separator' => ['nullable', 'string', 'max:4'],
            'thousands_separator' => ['nullable', 'string', 'max:4', 'different:decimal_separator'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'symbol_position' => ['nullable', Rule::in(['before', 'after'])],
            'dollar_exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'exchange_rate_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string', 'max:500'],
        ], [
            'currency_id.required' => 'Selecciona la moneda principal.',
            'currency_id.exists' => 'La moneda seleccionada no existe o esta inactiva.',
            'thousands_separator.different' => 'El separador de miles debe ser distinto al separador decimal.',
            'dollar_exchange_rate.gt' => 'El tipo de cambio debe ser mayor que cero.',
        ]);

        $companyId = (int) $request->user()->company_id;

        DB::transaction(function () use ($request, $validated, $companyId) {
            $finance = ['currency_id' => $validated['currency_id']];

            foreach (['decimal_separator', 'thousands_separator', 'decimal_places', 'symbol_position'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $finance[$field] = $validated[$field];
                }
            }

            $this->updateFinance($companyId, $finance);

            if (isset($validated['dollar_exchange_rate'])) {
                $this->storeDollarExchangeRate(
                    $request,
                    $companyId,
                    (int) $validated['currency_id'],
                    (float) $validated['dollar_exchange_rate'],
                    $validated['exchange_rate_date'] ?? now()->toDateString(),
                    $validated['observation'] ?? null,
                );
            }

            $this->writeAudit($request, 'UPDATE', $companyId, null, ['currency_id' => (int) $validated['currency_id']]);
        });

        $this->settings->clearCache($companyId);

        return response()->json([
            'message' => 'Moneda principal actualizada correctamente.',
            'data' => $this->payload($companyId),
        ]);
    }

    public function updateHeadquarters(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['editar', 'update', 'store', 'manage', 'administrar']);

        $companyId = (int) $request->user()->company_id;
        $validated = $request->validate([
            'headquarters_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
        ], [
            'headquarters_id.required' => 'Selecciona la casa matriz.',
            'headquarters_id.exists' => 'La sucursal seleccionada no existe.',
        ]);

        DB::transaction(function () use ($request, $validated, $companyId) {
            $this->setHeadquarters($companyId, (int) $validated['headquarters_id']);
            $this->writeAudit($request, 'UPDATE', $companyId, null, ['headquarters_id' => (int) $validated['headquarters_id']]);
        });

        $this->settings->clearCache($companyId);

        return response()->json([
            'message' => 'Casa matriz actualizada correctamente.',
            'data' => $this->payload($companyId),
        ]);
    }

    public function storeExchangeRate(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['editar', 'update', 'store', 'manage', 'administrar']);

        $validated = $request->validate([
            'from_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'to_currency_id' => ['required', 'integer', 'different:from_currency_id', Rule::exists('currencies', 'id')],
            'rate' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'observation' => ['nullable', 'string', 'max:500'],
        ], [
            'to_currency_id.different' => 'La moneda origen y destino deben ser distintas.',
            'rate.gt' => 'El tipo de cambio debe ser mayor que cero.',
        ]);

        $rate = ExchangeRate::create([
            'company_id' => (int) $request->user()->company_id,
            'from_currency_id' => (int) $validated['from_currency_id'],
            'to_currency_id' => (int) $validated['to_currency_id'],
            'rate' => (float) $validated['rate'],
            'date' => Carbon::parse($validated['date'])->toDateString(),
            'created_by' => $request->user()->id,
            'observation' => $this->nullableText($validated['observation'] ?? null),
        ]);

        $this->writeAudit($request, 'INSERT', (int) $rate->id, null, $rate->toArray());

        return response()->json([
            'message' => 'Tipo de cambio guardado correctamente.',
            'item' => $this->exchangeRatePayload($rate->fresh()),
        ], 201);
    }

    public function latestExchangeRate(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['ver', 'index', 'view', 'manage', 'administrar']);

        $validated = $request->validate([
            'from_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'to_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
        ]);

        if ((int) $validated['from_currency_id'] === (int) $validated['to_currency_id']) {
            return response()->json([
                'data' => [
                    'rate' => 1,
                    'date' => now()->toDateString(),
                    'same_currency' => true,
                ],
            ]);
        }

        $companyId = (int) $request->user()->company_id;
        $rate = ExchangeRate::query()
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->latestForPair((int) $validated['from_currency_id'], (int) $validated['to_currency_id'])
            ->first();

        return response()->json([
            'data' => $rate ? $this->exchangeRatePayload($rate) : null,
        ]);
    }

    public function testMail(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['editar', 'update', 'manage', 'administrar']);

        $companyId = (int) $request->user()->company_id;
        $mail = $this->settings->all($companyId)['mail'] ?? [];

        $host = trim((string) ($mail['smtp_host'] ?? ''));
        $fromAddress = trim((string) ($mail['from_address'] ?? ''));

        abort_if($host === '' || $fromAddress === '', 422, 'Configura el host SMTP y el correo remitente antes de probar.');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) ($mail['smtp_port'] ?? 587),
            'mail.mailers.smtp.username' => $mail['smtp_username'] ?: null,
            'mail.mailers.smtp.password' => $mail['smtp_password'] ?: null,
            'mail.mailers.smtp.scheme' => ($mail['smtp_encryption'] ?? 'tls') === 'none' ? null : $mail['smtp_encryption'],
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $mail['from_name'] ?: $fromAddress,
        ]);

        try {
            Mail::raw('Prueba de conexion SMTP del ERP.', function ($message) use ($fromAddress) {
                $message->to($fromAddress)->subject('Prueba SMTP ERP');
            });
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'No se pudo completar la prueba SMTP: '.$exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Conexion SMTP probada correctamente.',
        ]);
    }

    public function createBackup(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['administrar', 'manage']);

        $companyId = (int) $request->user()->company_id;
        $payload = [
            'created_at' => now()->toIso8601String(),
            'company' => Company::query()->whereKey($companyId)->first()?->toArray(),
            'branches' => Branch::query()->where('company_id', $companyId)->orderBy('id')->get()->toArray(),
            'settings' => $this->settings->all($companyId),
            'currencies' => Currency::query()->orderBy('code')->get()->toArray(),
            'exchange_rates' => ExchangeRate::query()
                ->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');
                })
                ->orderBy('date')
                ->get()
                ->toArray(),
        ];

        $fileName = 'general-settings-'.$companyId.'-'.now()->format('Ymd-His').'.json';
        $filePath = 'configuration-backups/'.$fileName;
        Storage::disk('local')->put($filePath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $backup = ConfigurationBackup::create([
            'company_id' => $companyId,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => Storage::disk('local')->size($filePath),
            'status' => 'CREATED',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Respaldo de configuracion creado correctamente.',
            'item' => $this->backupPayload($backup),
        ], 201);
    }

    public function restoreBackup(Request $request): JsonResponse
    {
        $this->authorizeGeneralSettings($request, ['administrar', 'manage']);

        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'mimes:json,txt', 'max:10240'],
        ]);

        $companyId = (int) $request->user()->company_id;
        $content = file_get_contents($validated['backup_file']->getRealPath());
        $payload = json_decode((string) $content, true);

        abort_unless(is_array($payload), 422, 'El archivo de respaldo no es valido.');

        DB::transaction(function () use ($request, $payload, $companyId) {
            if (isset($payload['company']) && is_array($payload['company'])) {
                $companyData = $this->onlyCompanyFields($payload['company']);
                unset($companyData['id'], $companyData['uuid'], $companyData['currency_id']);

                if ($companyData !== []) {
                    Company::query()->whereKey($companyId)->update($companyData);
                }
            }

            if (isset($payload['settings']) && is_array($payload['settings'])) {
                $this->settings->setMany($companyId, $payload['settings']);
            }

            $this->writeAudit($request, 'RESTORE', $companyId, null, ['backup_restored' => true]);
        });

        $this->settings->clearCache($companyId);

        return response()->json([
            'message' => 'Respaldo restaurado correctamente.',
            'data' => $this->payload($companyId),
        ]);
    }

    private function payload(int $companyId): array
    {
        $company = Company::query()->with('currency')->findOrFail($companyId);
        $settings = $this->settings->all($companyId);

        $settings['regional'] = array_merge($settings['regional'] ?? [], [
            'timezone' => $company->timezone ?: ($settings['regional']['timezone'] ?? 'America/Managua'),
            'locale' => $company->locale ?: ($settings['regional']['locale'] ?? 'es_NI'),
            'date_format' => $company->date_format ?: ($settings['regional']['date_format'] ?? 'dd/mm/yyyy'),
            'time_format' => $company->time_format ?: ($settings['regional']['time_format'] ?? '24h'),
        ]);

        $settings['monetary'] = array_merge($settings['monetary'] ?? [], [
            'symbol' => $company->currency?->symbol,
            'decimal_places' => $company->currency?->decimal_places ?? ($settings['monetary']['decimal_places'] ?? 2),
            'decimal_separator' => $company->currency?->decimal_separator ?? ($settings['monetary']['decimal_separator'] ?? '.'),
            'thousands_separator' => $company->currency?->thousands_separator ?? ($settings['monetary']['thousands_separator'] ?? ','),
            'symbol_position' => $company->currency?->symbol_position ?? ($settings['monetary']['symbol_position'] ?? 'before'),
        ]);

        $responseSettings = $this->settingsForResponse($settings);

        return [
            'company' => $this->companyPayload($company),
            'branding' => $this->brandingPayload($company),
            'branches' => Branch::query()
                ->where('company_id', $companyId)
                ->orderByDesc('is_headquarters')
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch) => $this->branchPayload($branch))
                ->values(),
            'currencies' => Currency::query()
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn (Currency $currency) => $this->currencyPayload($currency))
                ->values(),
            'taxes' => Schema::hasTable('taxes')
                ? DB::table('taxes')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'rate'])
                : [],
            'exchange_rates' => ExchangeRate::query()
                ->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');
                })
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (ExchangeRate $rate) => $this->exchangeRatePayload($rate))
                ->values(),
            'settings' => $responseSettings,
            'options' => [
                'countries' => Schema::hasTable('countries')
                    ? Country::query()->active()->orderBy('name')->pluck('name')->values()
                    : ['Nicaragua', 'El Salvador', 'Honduras', 'Guatemala', 'Costa Rica', 'Estados Unidos'],
                'timezones' => Schema::hasTable('timezones')
                    ? Timezone::query()->active()->orderBy('name')->pluck('name')->values()
                    : ['America/Managua', 'America/El_Salvador', 'America/Tegucigalpa', 'America/Guatemala', 'America/Costa_Rica', 'America/Mexico_City', 'America/New_York'],
                'locales' => ['es_NI', 'es_SV', 'es_HN', 'es_GT', 'es_CR', 'en_US'],
                'date_formats' => ['dd/mm/yyyy', 'yyyy-mm-dd', 'mm/dd/yyyy'],
                'time_formats' => ['24h', '12h'],
                'print_formats' => ['ticket', 'letter', 'a4'],
                'two_factor_supported' => Schema::hasTable('two_factor_codes'),
            ],
            'backups' => ConfigurationBackup::query()
                ->where('company_id', $companyId)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (ConfigurationBackup $backup) => $this->backupPayload($backup))
                ->values(),
        ];
    }

    private function updateCompany(Request $request, int $companyId, array $company): void
    {
        $data = $this->onlyCompanyFields($company);

        foreach ([
            'name',
            'legal_name',
            'short_name',
            'slogan',
            'description',
            'tax_id',
            'nrc',
            'commercial_registry',
            'business_activity',
            'tax_regime',
            'taxpayer_type',
            'phone',
            'mobile',
            'whatsapp',
            'email',
            'website',
            'fiscal_address',
            'commercial_address',
            'country',
            'department',
            'city',
            'full_address',
            'postal_code',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->nullableText($data[$field]);
            }
        }

        foreach (['latitude', 'longitude'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] === null || $data[$field] === '' ? null : (float) $data[$field];
            }
        }

        // Cada logo se guarda de forma independiente: subir el logo del menu
        // (columna `logo`) nunca debe pisar el logo de login (`logo_dark`), y
        // viceversa. Si el usuario no ha definido un logo de login propio, la
        // UI/branding pública ya resuelve el fallback en tiempo de lectura
        // (ver brandingPayload()), no hace falta duplicar el archivo aquí.
        foreach ([
            'logo_file' => 'logo',
            'logo_dark_file' => 'logo_dark',
            'favicon_file' => 'favicon',
        ] as $fileField => $column) {
            $file = $this->requestFile($request, $fileField);

            if ($file) {
                $data[$column] = $this->storeCompanyImage($file, $companyId, $column);
            }
        }

        if ($data !== []) {
            $data['updated_at'] = now();
            Company::query()->whereKey($companyId)->update($data);
        }
    }

    private function settingsForResponse(array $settings): array
    {
        $mailPassword = (string) ($settings['mail']['smtp_password'] ?? '');

        if (isset($settings['mail']) && is_array($settings['mail'])) {
            $settings['mail']['smtp_password'] = '';
            $settings['mail']['smtp_password_configured'] = $mailPassword !== '';
        }

        return $settings;
    }

    private function setHeadquarters(int $companyId, int $branchId): void
    {
        $branchExists = Branch::query()
            ->where('company_id', $companyId)
            ->whereKey($branchId)
            ->exists();

        abort_unless($branchExists, 422, 'La sucursal seleccionada no existe.');

        Branch::query()
            ->where('company_id', $companyId)
            ->update([
                'is_headquarters' => false,
                'headquarters_unique_key' => null,
                'updated_at' => now(),
            ]);

        Branch::query()
            ->where('company_id', $companyId)
            ->whereKey($branchId)
            ->update([
                'is_headquarters' => true,
                'headquarters_unique_key' => 'HEADQUARTERS',
                'updated_at' => now(),
            ]);
    }

    private function updateFinance(int $companyId, array $finance): void
    {
        $currency = Currency::query()->findOrFail((int) $finance['currency_id']);

        $currencyData = [];

        foreach (['decimal_separator', 'thousands_separator', 'symbol_position'] as $field) {
            if (array_key_exists($field, $finance)) {
                $currencyData[$field] = $finance[$field];
            }
        }

        if (array_key_exists('decimal_places', $finance)) {
            $currencyData['decimal_places'] = (int) $finance['decimal_places'];
        }

        if ($currencyData !== []) {
            $currency->update($currencyData);
        }

        Currency::query()->whereKeyNot($currency->id)->update(['is_base' => false]);
        $currency->forceFill(['is_base' => true])->save();

        Company::query()->whereKey($companyId)->update([
            'currency_id' => $currency->id,
            'updated_at' => now(),
        ]);
    }

    private function storeDollarExchangeRate(
        Request $request,
        int $companyId,
        int $currencyId,
        float $rate,
        string $date,
        ?string $observation,
    ): ?ExchangeRate {
        $currency = Currency::query()->findOrFail($currencyId);

        if (strtoupper((string) $currency->code) === 'USD') {
            return null;
        }

        $usd = Currency::query()
            ->where('code', 'USD')
            ->where('is_active', true)
            ->first();

        abort_unless($usd, 422, 'No existe una moneda USD activa para registrar el tipo de cambio.');

        return ExchangeRate::create([
            'company_id' => $companyId,
            'from_currency_id' => (int) $usd->id,
            'to_currency_id' => $currency->id,
            'rate' => $rate,
            'date' => Carbon::parse($date)->toDateString(),
            'created_by' => $request->user()->id,
            'observation' => $this->nullableText($observation),
        ]);
    }

    private function settingGroupsFrom(int $companyId, array $validated): array
    {
        $groups = [];

        foreach (['regional', 'mail', 'security', 'inventory', 'sales', 'purchases', 'printing', 'backup', 'system'] as $group) {
            if (isset($validated[$group]) && is_array($validated[$group])) {
                $groups[$group] = $validated[$group];
            }
        }

        if (isset($validated['finance'])) {
            $monetary = [];

            foreach (['decimal_separator', 'thousands_separator', 'decimal_places', 'symbol_position'] as $field) {
                if (array_key_exists($field, $validated['finance'])) {
                    $monetary[$field] = $validated['finance'][$field];
                }
            }

            if ($monetary !== []) {
                $groups['monetary'] = $monetary;
            }
        }

        if (isset($validated['regional'])) {
            $regional = $validated['regional'];
            $companyData = [];

            foreach (['timezone', 'locale', 'date_format', 'time_format'] as $field) {
                if (array_key_exists($field, $regional)) {
                    $companyData[$field] = $regional[$field];
                }
            }

            if ($companyData !== []) {
                Company::query()
                    ->whereKey($companyId)
                    ->update([...$companyData, 'updated_at' => now()]);
            }
        }

        return array_filter($groups, fn ($value) => is_array($value));
    }

    private function onlyCompanyFields(array $data): array
    {
        return collect($data)
            ->only([
                'name',
                'legal_name',
                'short_name',
                'slogan',
                'description',
                'tax_id',
                'nrc',
                'commercial_registry',
                'business_activity',
                'tax_regime',
                'taxpayer_type',
                'phone',
                'mobile',
                'whatsapp',
                'email',
                'website',
                'fiscal_address',
                'commercial_address',
                'country',
                'department',
                'city',
                'full_address',
                'postal_code',
                'latitude',
                'longitude',
                'timezone',
                'locale',
                'date_format',
                'time_format',
            ])
            ->all();
    }

    private function companyPayload(Company $company): array
    {
        return [
            'id' => (int) $company->id,
            'uuid' => $company->uuid,
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'short_name' => $company->short_name,
            'slogan' => $company->slogan,
            'description' => $company->description,
            'tax_id' => $company->tax_id,
            'nrc' => $company->nrc,
            'commercial_registry' => $company->commercial_registry,
            'business_activity' => $company->business_activity,
            'tax_regime' => $company->tax_regime,
            'taxpayer_type' => $company->taxpayer_type,
            'phone' => $company->phone,
            'mobile' => $company->mobile,
            'whatsapp' => $company->whatsapp,
            'email' => $company->email,
            'website' => $company->website,
            'fiscal_address' => $company->fiscal_address,
            'commercial_address' => $company->commercial_address,
            'country' => $company->country,
            'department' => $company->department,
            'city' => $company->city,
            'full_address' => $company->full_address,
            'postal_code' => $company->postal_code,
            'latitude' => $company->latitude,
            'longitude' => $company->longitude,
            'logo' => $company->logo,
            'logo_url' => $this->storageUrl($company->logo),
            'logo_dark' => $company->logo_dark,
            'logo_dark_url' => $this->storageUrl($company->logo_dark),
            'favicon' => $company->favicon,
            'favicon_url' => $this->storageUrl($company->favicon),
            'currency_id' => $company->currency_id ? (int) $company->currency_id : null,
            'currency' => $company->currency ? $this->currencyPayload($company->currency) : null,
            'timezone' => $company->timezone,
            'locale' => $company->locale,
            'date_format' => $company->date_format,
            'time_format' => $company->time_format,
            'status' => (int) $company->status,
        ];
    }

    private function brandingPayload(Company $company): array
    {
        $name = trim((string) ($company->short_name ?: $company->name ?: config('app.name', 'ERP')));

        return [
            'company_name' => $name,
            'commercial_name' => $company->name,
            'slogan' => $company->slogan,
            'welcome_text' => 'Bienvenido a '.$name,
            'logo_url' => $this->storageUrl($company->logo),
            'logo_dark_url' => $this->storageUrl($company->logo_dark ?: $company->logo),
            'favicon_url' => $this->storageUrl($company->favicon),
            'notifications_enabled' => (bool) $this->settings->get((int) $company->id, 'system', 'notifications_enabled', true),
        ];
    }

    private function branchPayload(Branch $branch): array
    {
        return [
            'id' => (int) $branch->id,
            'uuid' => $branch->uuid,
            'code' => $branch->code,
            'name' => $branch->name,
            'phone' => $branch->phone,
            'mobile' => $branch->mobile,
            'whatsapp' => $branch->whatsapp,
            'email' => $branch->email,
            'address' => $branch->address,
            'country' => $branch->country,
            'department' => $branch->department,
            'city' => $branch->city,
            'postal_code' => $branch->postal_code,
            'full_address' => $branch->full_address,
            'contact_person' => $branch->contact_person,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'is_headquarters' => (bool) $branch->is_headquarters,
            'status' => (int) $branch->status,
        ];
    }

    private function currencyPayload(Currency $currency): array
    {
        return [
            'id' => (int) $currency->id,
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => (int) $currency->decimal_places,
            'decimal_separator' => $currency->decimal_separator ?? '.',
            'thousands_separator' => $currency->thousands_separator ?? ',',
            'symbol_position' => $currency->symbol_position ?? 'before',
            'is_base' => (bool) $currency->is_base,
            'is_active' => (bool) $currency->is_active,
        ];
    }

    private function exchangeRatePayload(ExchangeRate $rate): array
    {
        $rate->loadMissing(['fromCurrency', 'toCurrency', 'user']);

        return [
            'id' => (int) $rate->id,
            'from_currency_id' => (int) $rate->from_currency_id,
            'from_currency' => $rate->fromCurrency?->code,
            'to_currency_id' => (int) $rate->to_currency_id,
            'to_currency' => $rate->toCurrency?->code,
            'rate' => (float) $rate->rate,
            'date' => $rate->date?->toDateString(),
            'created_by' => $rate->created_by ? (int) $rate->created_by : null,
            'created_by_name' => $rate->user?->full_name,
            'observation' => $rate->observation,
        ];
    }

    private function backupPayload(ConfigurationBackup $backup): array
    {
        return [
            'id' => (int) $backup->id,
            'file_name' => $backup->file_name,
            'file_path' => $backup->file_path,
            'file_size' => (int) $backup->file_size,
            'status' => $backup->status,
            'notes' => $backup->notes,
            'created_by' => $backup->created_by ? (int) $backup->created_by : null,
            'created_at' => $backup->created_at?->toIso8601String(),
            'restored_at' => $backup->restored_at?->toIso8601String(),
        ];
    }

    private function storeCompanyImage($file, int $companyId, string $name): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $fileName = $name.'-'.now()->format('YmdHis').'.'.$extension;

        return $file->storeAs('companies/'.$companyId, $fileName, 'public');
    }

    private function requestFile(Request $request, string $field)
    {
        return $request->file("company.$field") ?: $request->file($field);
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function applyTimezone(?string $timezone): void
    {
        if (! $timezone) {
            return;
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }

    private function authorizeGeneralSettings(Request $request, array $actions): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id && $user->role_id, 403, 'No tienes un rol con permisos para configuracion.');

        $role = DB::table('roles')->where('id', $user->role_id)->first();

        if ($role && strcasecmp((string) $role->name, 'Administrador') === 0) {
            return;
        }

        $allowed = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->whereIn('permissions.module_name', ['general_settings', 'settings'])
            ->whereIn('permissions.action_name', $actions)
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar la configuracion general.');
    }

    private function writeAudit(Request $request, string $action, int $recordId, ?array $oldValues, ?array $newValues): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $companyId = (int) ($request->user()?->company_id ?? 0);

        if ($companyId > 0 && ! (bool) $this->settings->get($companyId, 'system', 'audit_enabled', true)) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->id,
            'table_name' => 'general_settings',
            'action_type' => $action,
            'record_id' => $recordId,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'created_at' => now(),
        ]);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
