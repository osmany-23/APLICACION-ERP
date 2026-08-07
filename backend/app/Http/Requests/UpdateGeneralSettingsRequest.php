<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;

        return [
            'company' => ['sometimes', 'array'],
            'company.name' => ['required_with:company', 'string', 'max:150'],
            'company.legal_name' => ['nullable', 'string', 'max:200'],
            'company.short_name' => ['nullable', 'string', 'max:80'],
            'company.slogan' => ['nullable', 'string', 'max:160'],
            'company.description' => ['nullable', 'string'],
            'company.tax_id' => ['nullable', 'string', 'max:50'],
            'company.nrc' => ['nullable', 'string', 'max:50'],
            'company.commercial_registry' => ['nullable', 'string', 'max:120'],
            'company.business_activity' => ['nullable', 'string', 'max:180'],
            'company.tax_regime' => ['nullable', 'string', 'max:50'],
            'company.taxpayer_type' => ['nullable', 'string', 'max:80'],
            'company.phone' => ['nullable', 'string', 'max:30'],
            'company.mobile' => ['nullable', 'string', 'max:30'],
            'company.whatsapp' => ['nullable', 'string', 'max:30'],
            'company.email' => ['nullable', 'email', 'max:120'],
            'company.website' => ['nullable', 'url', 'max:200'],
            'company.fiscal_address' => ['nullable', 'string'],
            'company.commercial_address' => ['nullable', 'string'],
            'company.country' => ['nullable', 'string', 'max:80'],
            'company.department' => ['nullable', 'string', 'max:80'],
            'company.city' => ['nullable', 'string', 'max:80'],
            'company.full_address' => ['nullable', 'string'],
            'company.postal_code' => ['nullable', 'string', 'max:20'],
            'company.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'company.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'company.logo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:4096'],
            'company.logo_dark_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:4096'],
            'company.favicon_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg,ico', 'max:1024'],

            'branches' => ['sometimes', 'array'],
            'branches.headquarters_id' => [
                'required_with:branches',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],

            'finance' => ['sometimes', 'array'],
            'finance.currency_id' => ['required_with:finance', 'integer', Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'finance.decimal_separator' => ['nullable', 'string', 'max:4'],
            'finance.thousands_separator' => ['nullable', 'string', 'max:4', 'different:finance.decimal_separator'],
            'finance.decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'finance.symbol_position' => ['nullable', Rule::in(['before', 'after'])],

            'regional' => ['sometimes', 'array'],
            'regional.timezone' => ['nullable', 'timezone'],
            'regional.locale' => ['nullable', Rule::in(['es_NI', 'es_SV', 'es_HN', 'es_GT', 'es_CR', 'en_US'])],
            'regional.date_format' => ['nullable', Rule::in(['dd/mm/yyyy', 'yyyy-mm-dd', 'mm/dd/yyyy'])],
            'regional.time_format' => ['nullable', Rule::in(['12h', '24h'])],

            'mail' => ['sometimes', 'array'],
            'mail.smtp_host' => ['nullable', 'string', 'max:150'],
            'mail.smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail.smtp_username' => ['nullable', 'string', 'max:150'],
            'mail.smtp_password' => ['nullable', 'string', 'max:255'],
            'mail.smtp_encryption' => ['nullable', Rule::in(['none', 'tls', 'ssl'])],
            'mail.from_address' => ['nullable', 'email', 'max:150'],
            'mail.from_name' => ['nullable', 'string', 'max:150'],

            'security' => ['sometimes', 'array'],
            'security.session_timeout_minutes' => ['nullable', 'integer', 'min:5', 'max:43200'],
            'security.max_login_attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
            'security.lockout_enabled' => ['nullable', 'boolean'],
            'security.lockout_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'security.password_min_length' => ['nullable', 'integer', 'min:8', 'max:64'],
            'security.password_complexity' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'security.two_factor_enabled' => ['nullable', 'boolean'],

            'inventory' => ['sometimes', 'array'],
            'inventory.minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'inventory.allow_negative_stock' => ['nullable', 'boolean'],
            'inventory.track_lots' => ['nullable', 'boolean'],
            'inventory.track_serials' => ['nullable', 'boolean'],
            'inventory.alerts_enabled' => ['nullable', 'boolean'],

            'sales' => ['sometimes', 'array'],
            'sales.auto_numbering' => ['nullable', 'boolean'],
            'sales.prefix' => ['nullable', 'string', 'max:20'],
            'sales.suffix' => ['nullable', 'string', 'max:20'],
            'sales.digits' => ['nullable', 'integer', 'min:1', 'max:20'],
            'sales.rounding' => ['nullable', 'integer', 'min:0', 'max:6'],
            'sales.default_tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')],

            'purchases' => ['sometimes', 'array'],
            'purchases.prefix' => ['nullable', 'string', 'max:20'],
            'purchases.default_tax_id' => ['nullable', 'integer', Rule::exists('taxes', 'id')],
            'purchases.authorization_required' => ['nullable', 'boolean'],

            'printing' => ['sometimes', 'array'],
            'printing.default_format' => ['nullable', Rule::in(['ticket', 'letter', 'a4', 'A4', 'Carta', 'Ticket'])],
            'printing.default_printer' => ['nullable', 'string', 'max:150'],

            'backup' => ['sometimes', 'array'],
            'backup.schedule_enabled' => ['nullable', 'boolean'],
            'backup.frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'backup.time' => ['nullable', 'date_format:H:i'],
            'backup.retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            'system' => ['sometimes', 'array'],
            'system.audit_enabled' => ['nullable', 'boolean'],
            'system.notifications_enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'company.name.required_with' => 'Ingresa el nombre comercial.',
            'company.email.email' => 'Ingresa un correo valido.',
            'company.website.url' => 'Ingresa un sitio web valido.',
            'company.logo_file.mimes' => 'El logo principal debe ser JPG, PNG, WEBP, GIF o SVG.',
            'company.logo_dark_file.mimes' => 'El logo para login debe ser JPG, PNG, WEBP, GIF o SVG.',
            'company.favicon_file.mimes' => 'El favicon debe ser JPG, PNG, WEBP, GIF, SVG o ICO.',
            'branches.headquarters_id.required_with' => 'Selecciona la casa matriz.',
            'branches.headquarters_id.exists' => 'La sucursal seleccionada no existe.',
            'finance.currency_id.required_with' => 'Selecciona la moneda principal.',
            'finance.currency_id.exists' => 'La moneda seleccionada no existe o esta inactiva.',
            'finance.thousands_separator.different' => 'El separador de miles debe ser distinto al separador decimal.',
            'regional.timezone.timezone' => 'Selecciona una zona horaria valida.',
            'mail.from_address.email' => 'Ingresa un correo remitente valido.',
            'security.password_min_length.min' => 'La longitud minima de contrasena no puede ser menor a 8.',
            'inventory.minimum_stock.min' => 'El stock minimo no puede ser negativo.',
            'backup.time.date_format' => 'La hora del respaldo debe tener formato HH:mm.',
        ];
    }
}
