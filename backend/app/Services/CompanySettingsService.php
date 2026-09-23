<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CompanySettingsService
{
    public const CACHE_PREFIX = 'company_settings.';

    public function defaults(): array
    {
        return [
            'regional' => [
                'locale' => ['value' => 'es_NI', 'type' => 'string'],
                'date_format' => ['value' => 'dd/mm/yyyy', 'type' => 'string'],
                'time_format' => ['value' => '24h', 'type' => 'string'],
                'timezone' => ['value' => 'America/Managua', 'type' => 'string'],
            ],
            'monetary' => [
                'decimal_separator' => ['value' => '.', 'type' => 'string'],
                'thousands_separator' => ['value' => ',', 'type' => 'string'],
                'decimal_places' => ['value' => 2, 'type' => 'integer'],
                'symbol_position' => ['value' => 'before', 'type' => 'string'],
            ],
            'mail' => [
                'smtp_host' => ['value' => '', 'type' => 'string'],
                'smtp_port' => ['value' => 587, 'type' => 'integer'],
                'smtp_username' => ['value' => '', 'type' => 'string'],
                'smtp_password' => ['value' => '', 'type' => 'string', 'encrypted' => true],
                'smtp_encryption' => ['value' => 'tls', 'type' => 'string'],
                'from_address' => ['value' => '', 'type' => 'string'],
                'from_name' => ['value' => '', 'type' => 'string'],
            ],
            'security' => [
                'session_timeout_minutes' => ['value' => 720, 'type' => 'integer'],
                'max_login_attempts' => ['value' => 5, 'type' => 'integer'],
                'lockout_enabled' => ['value' => true, 'type' => 'boolean'],
                'lockout_minutes' => ['value' => 15, 'type' => 'integer'],
                'password_min_length' => ['value' => 8, 'type' => 'integer'],
                'password_complexity' => ['value' => 'medium', 'type' => 'string'],
                'two_factor_enabled' => ['value' => false, 'type' => 'boolean'],
            ],
            'inventory' => [
                'minimum_stock' => ['value' => 0, 'type' => 'decimal'],
                'allow_negative_stock' => ['value' => false, 'type' => 'boolean'],
                'track_lots' => ['value' => false, 'type' => 'boolean'],
                'track_serials' => ['value' => false, 'type' => 'boolean'],
                'alerts_enabled' => ['value' => true, 'type' => 'boolean'],
            ],
            'sales' => [
                'auto_numbering' => ['value' => true, 'type' => 'boolean'],
                'prefix' => ['value' => 'FAC', 'type' => 'string'],
                'suffix' => ['value' => '', 'type' => 'string'],
                'digits' => ['value' => 8, 'type' => 'integer'],
                'rounding' => ['value' => 2, 'type' => 'integer'],
                'default_tax_id' => ['value' => null, 'type' => 'integer'],
                // IVA general: si esta activado, el % indicado se aplica
                // automaticamente a todo producto TAXABLE al facturar (ver
                // GeneralSettingsController::syncGeneralIvaTax(), que
                // sincroniza esto con la fila "is_default" de la tabla
                // taxes — SalesService::resolveLineTax() ya usa esa fila
                // sin ningun cambio adicional). Por defecto activado al
                // 15%, igual al IVA15 que ErpBaseSeeder ya sembraba.
                'iva_enabled' => ['value' => true, 'type' => 'boolean'],
                'iva_rate' => ['value' => 15.00, 'type' => 'decimal'],
                // Descuento maximo general: tope de respaldo para
                // cualquier producto que NO tenga su propio limite
                // especifico (products.max_discount_type/max_discount_value)
                // — ver SalesService::resolveMaxLineDiscount(). Un vendedor
                // con su propio limite (users.max_discount_percentage) sigue
                // aplicando ademas de este, se usa el mas restrictivo de
                // los que apliquen. Desactivado por defecto (sin tope
                // general, solo quedan los limites por producto/vendedor si
                // se configuran).
                'max_discount_enabled' => ['value' => false, 'type' => 'boolean'],
                'max_discount_percentage' => ['value' => 100.00, 'type' => 'decimal'],
            ],
            'purchases' => [
                'prefix' => ['value' => 'COM', 'type' => 'string'],
                'default_tax_id' => ['value' => null, 'type' => 'integer'],
                'authorization_required' => ['value' => false, 'type' => 'boolean'],
            ],
            'printing' => [
                'default_format' => ['value' => 'A4', 'type' => 'string'],
                'default_printer' => ['value' => '', 'type' => 'string'],
            ],
            // Tamano en pixeles con el que se muestra el logo de la empresa
            // (login y ticket de venta — ver brandingPayload() abajo, que
            // es publico y de ahi lo leen ambos). Antes venia fijo por
            // CSS (64px); con un logo que trae mucho espacio en blanco
            // alrededor de la marca eso lo dejaba viendose chico, asi que
            // ahora es configurable.
            'branding' => [
                'logo_height' => ['value' => 64, 'type' => 'integer'],
            ],
            // Configuracion del ticket/recibo impreso al cobrar una venta
            // (POS y reimpresion desde Ventas — ver ReceiptTicket.tsx). El
            // logo y el nombre real de la empresa ya se configuran en la
            // pestana "Empresa"; "display_name" es solo un nombre alterno
            // opcional para el encabezado del ticket (vacio = usa el nombre
            // comercial de la empresa tal cual).
            'receipts' => [
                'show_logo' => ['value' => true, 'type' => 'boolean'],
                'display_name' => ['value' => '', 'type' => 'string'],
                'footer_note' => ['value' => 'PARA HACER EFECTIVA CUALQUIER DEVOLUCION O CAMBIO, EL PRODUCTO DEBE PRESENTARSE EN SU EMPAQUE ORIGINAL, COMPLETAMENTE SELLADO, SIN ABRIR Y EN PERFECTAS CONDICIONES', 'type' => 'string'],
                'claim_days' => ['value' => 5, 'type' => 'integer'],
            ],
            'backup' => [
                'schedule_enabled' => ['value' => false, 'type' => 'boolean'],
                'frequency' => ['value' => 'daily', 'type' => 'string'],
                'time' => ['value' => '02:00', 'type' => 'string'],
                'retention_days' => ['value' => 7, 'type' => 'integer'],
            ],
            'system' => [
                'audit_enabled' => ['value' => true, 'type' => 'boolean'],
                'notifications_enabled' => ['value' => true, 'type' => 'boolean'],
            ],
        ];
    }

    public function all(int $companyId): array
    {
        if (! Schema::hasTable('company_settings')) {
            return $this->defaultValues();
        }

        return Cache::rememberForever($this->cacheKey($companyId), function () use ($companyId) {
            $settings = $this->defaultValues();

            CompanySetting::query()
                ->where('company_id', $companyId)
                ->get()
                ->each(function (CompanySetting $setting) use (&$settings) {
                    $group = (string) $setting->group;
                    $key = (string) $setting->key;

                    if (! array_key_exists($group, $settings)) {
                        $settings[$group] = [];
                    }

                    $settings[$group][$key] = $setting->value;
                });

            return $settings;
        });
    }

    public function get(int $companyId, string $group, string $key, mixed $fallback = null): mixed
    {
        $settings = $this->all($companyId);

        return $settings[$group][$key] ?? $fallback;
    }

    public function setMany(int $companyId, array $groups): array
    {
        if (! Schema::hasTable('company_settings')) {
            return $this->defaultValues();
        }

        $defaults = $this->defaults();

        foreach ($groups as $group => $values) {
            if (! is_array($values) || ! array_key_exists($group, $defaults)) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (! array_key_exists($key, $defaults[$group])) {
                    continue;
                }

                $definition = $defaults[$group][$key];
                $type = (string) ($definition['type'] ?? 'string');
                $encrypted = (bool) ($definition['encrypted'] ?? false);

                if ($encrypted && ($value === null || $value === '' || $value === '__KEEP__')) {
                    continue;
                }

                $this->set($companyId, (string) $group, (string) $key, $value, $type, $encrypted);
            }
        }

        $this->clearCache($companyId);

        return $this->all($companyId);
    }

    public function seedDefaults(int $companyId): void
    {
        if (! Schema::hasTable('company_settings')) {
            return;
        }

        foreach ($this->defaults() as $group => $values) {
            foreach ($values as $key => $definition) {
                $exists = CompanySetting::query()
                    ->where('company_id', $companyId)
                    ->where('group', $group)
                    ->where('key', $key)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $this->set(
                    $companyId,
                    $group,
                    $key,
                    $definition['value'] ?? null,
                    $definition['type'] ?? 'string',
                    (bool) ($definition['encrypted'] ?? false),
                );
            }
        }

        $this->clearCache($companyId);
    }

    public function clearCache(int $companyId): void
    {
        Cache::forget($this->cacheKey($companyId));
    }

    public function cacheKey(int $companyId): string
    {
        return self::CACHE_PREFIX.$companyId;
    }

    private function set(int $companyId, string $group, string $key, mixed $value, string $type, bool $encrypted): void
    {
        $setting = CompanySetting::query()->firstOrNew([
            'company_id' => $companyId,
            'group' => $group,
            'key' => $key,
        ]);

        $setting->type = $type;
        $setting->is_encrypted = $encrypted;
        $setting->value = $this->normalizeValue($value, $type);
        $setting->save();
    }

    private function defaultValues(): array
    {
        $values = [];

        foreach ($this->defaults() as $group => $settings) {
            foreach ($settings as $key => $definition) {
                $values[$group][$key] = $definition['value'] ?? null;
            }
        }

        return $values;
    }

    private function normalizeValue(mixed $value, string $type): mixed
    {
        if ($value === '') {
            return null;
        }

        return match ($type) {
            'integer' => $value === null ? null : (int) $value,
            'decimal' => $value === null ? null : (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'json' => is_array($value) ? $value : [],
            default => $value === null ? null : trim((string) $value),
        };
    }
}
