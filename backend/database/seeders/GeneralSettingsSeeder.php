<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Timezone;
use App\Services\CompanySettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeneralSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $this->seedCurrencies($now);
        $this->seedCountries($now);
        $this->seedTimezones($now);
        $this->seedCompanyDefaults();
    }

    private function seedCurrencies(mixed $now): void
    {
        if (! Schema::hasTable('currencies')) {
            return;
        }

        $rows = [
            ['code' => 'NIO', 'name' => 'Cordoba', 'symbol' => 'C$', 'is_base' => true],
            ['code' => 'USD', 'name' => 'Dolar Estadounidense', 'symbol' => '$', 'is_base' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR', 'is_base' => false],
        ];

        foreach ($rows as $row) {
            Currency::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'symbol' => $row['symbol'],
                    'decimal_places' => 2,
                    'decimal_separator' => '.',
                    'thousands_separator' => ',',
                    'symbol_position' => 'before',
                    'is_base' => $row['is_base'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Lista mundial de paises (ISO 3166-1 alpha-2/alpha-3 + prefijo
     * telefonico E.164) para el selector de prefijo del modulo de Clientes
     * (campo "extranjero/nacional" y telefono con prefijo internacional).
     * Se reutiliza la tabla countries ya existente en vez de agregar una
     * libreria nueva: es la misma data, ya modelada.
     */
    private function seedCountries(mixed $now): void
    {
        if (! Schema::hasTable('countries')) {
            return;
        }

        $currencyIds = Currency::query()->pluck('id', 'code');
        $rows = [
            // Centroamerica y Caribe
            ['iso2' => 'NI', 'iso3' => 'NIC', 'name' => 'Nicaragua', 'phone_code' => '+505', 'currency' => 'NIO'],
            ['iso2' => 'SV', 'iso3' => 'SLV', 'name' => 'El Salvador', 'phone_code' => '+503', 'currency' => 'USD'],
            ['iso2' => 'HN', 'iso3' => 'HND', 'name' => 'Honduras', 'phone_code' => '+504', 'currency' => null],
            ['iso2' => 'GT', 'iso3' => 'GTM', 'name' => 'Guatemala', 'phone_code' => '+502', 'currency' => null],
            ['iso2' => 'CR', 'iso3' => 'CRI', 'name' => 'Costa Rica', 'phone_code' => '+506', 'currency' => null],
            ['iso2' => 'PA', 'iso3' => 'PAN', 'name' => 'Panama', 'phone_code' => '+507', 'currency' => 'USD'],
            ['iso2' => 'BZ', 'iso3' => 'BLZ', 'name' => 'Belice', 'phone_code' => '+501', 'currency' => null],
            ['iso2' => 'CU', 'iso3' => 'CUB', 'name' => 'Cuba', 'phone_code' => '+53', 'currency' => null],
            ['iso2' => 'DO', 'iso3' => 'DOM', 'name' => 'Republica Dominicana', 'phone_code' => '+1', 'currency' => null],
            ['iso2' => 'HT', 'iso3' => 'HTI', 'name' => 'Haiti', 'phone_code' => '+509', 'currency' => null],
            ['iso2' => 'JM', 'iso3' => 'JAM', 'name' => 'Jamaica', 'phone_code' => '+1', 'currency' => null],
            ['iso2' => 'PR', 'iso3' => 'PRI', 'name' => 'Puerto Rico', 'phone_code' => '+1', 'currency' => 'USD'],
            ['iso2' => 'TT', 'iso3' => 'TTO', 'name' => 'Trinidad y Tobago', 'phone_code' => '+1', 'currency' => null],
            ['iso2' => 'BS', 'iso3' => 'BHS', 'name' => 'Bahamas', 'phone_code' => '+1', 'currency' => null],
            ['iso2' => 'BB', 'iso3' => 'BRB', 'name' => 'Barbados', 'phone_code' => '+1', 'currency' => null],

            // Norteamerica
            ['iso2' => 'US', 'iso3' => 'USA', 'name' => 'Estados Unidos', 'phone_code' => '+1', 'currency' => 'USD'],
            ['iso2' => 'CA', 'iso3' => 'CAN', 'name' => 'Canada', 'phone_code' => '+1', 'currency' => null],
            ['iso2' => 'MX', 'iso3' => 'MEX', 'name' => 'Mexico', 'phone_code' => '+52', 'currency' => null],

            // Sudamerica
            ['iso2' => 'CO', 'iso3' => 'COL', 'name' => 'Colombia', 'phone_code' => '+57', 'currency' => null],
            ['iso2' => 'VE', 'iso3' => 'VEN', 'name' => 'Venezuela', 'phone_code' => '+58', 'currency' => null],
            ['iso2' => 'EC', 'iso3' => 'ECU', 'name' => 'Ecuador', 'phone_code' => '+593', 'currency' => 'USD'],
            ['iso2' => 'PE', 'iso3' => 'PER', 'name' => 'Peru', 'phone_code' => '+51', 'currency' => null],
            ['iso2' => 'BO', 'iso3' => 'BOL', 'name' => 'Bolivia', 'phone_code' => '+591', 'currency' => null],
            ['iso2' => 'CL', 'iso3' => 'CHL', 'name' => 'Chile', 'phone_code' => '+56', 'currency' => null],
            ['iso2' => 'AR', 'iso3' => 'ARG', 'name' => 'Argentina', 'phone_code' => '+54', 'currency' => null],
            ['iso2' => 'UY', 'iso3' => 'URY', 'name' => 'Uruguay', 'phone_code' => '+598', 'currency' => null],
            ['iso2' => 'PY', 'iso3' => 'PRY', 'name' => 'Paraguay', 'phone_code' => '+595', 'currency' => null],
            ['iso2' => 'BR', 'iso3' => 'BRA', 'name' => 'Brasil', 'phone_code' => '+55', 'currency' => null],
            ['iso2' => 'GY', 'iso3' => 'GUY', 'name' => 'Guyana', 'phone_code' => '+592', 'currency' => null],
            ['iso2' => 'SR', 'iso3' => 'SUR', 'name' => 'Surinam', 'phone_code' => '+597', 'currency' => null],

            // Europa
            ['iso2' => 'ES', 'iso3' => 'ESP', 'name' => 'España', 'phone_code' => '+34', 'currency' => 'EUR'],
            ['iso2' => 'PT', 'iso3' => 'PRT', 'name' => 'Portugal', 'phone_code' => '+351', 'currency' => 'EUR'],
            ['iso2' => 'FR', 'iso3' => 'FRA', 'name' => 'Francia', 'phone_code' => '+33', 'currency' => 'EUR'],
            ['iso2' => 'DE', 'iso3' => 'DEU', 'name' => 'Alemania', 'phone_code' => '+49', 'currency' => 'EUR'],
            ['iso2' => 'IT', 'iso3' => 'ITA', 'name' => 'Italia', 'phone_code' => '+39', 'currency' => 'EUR'],
            ['iso2' => 'GB', 'iso3' => 'GBR', 'name' => 'Reino Unido', 'phone_code' => '+44', 'currency' => null],
            ['iso2' => 'IE', 'iso3' => 'IRL', 'name' => 'Irlanda', 'phone_code' => '+353', 'currency' => 'EUR'],
            ['iso2' => 'NL', 'iso3' => 'NLD', 'name' => 'Paises Bajos', 'phone_code' => '+31', 'currency' => 'EUR'],
            ['iso2' => 'BE', 'iso3' => 'BEL', 'name' => 'Belgica', 'phone_code' => '+32', 'currency' => 'EUR'],
            ['iso2' => 'CH', 'iso3' => 'CHE', 'name' => 'Suiza', 'phone_code' => '+41', 'currency' => null],
            ['iso2' => 'AT', 'iso3' => 'AUT', 'name' => 'Austria', 'phone_code' => '+43', 'currency' => 'EUR'],
            ['iso2' => 'SE', 'iso3' => 'SWE', 'name' => 'Suecia', 'phone_code' => '+46', 'currency' => null],
            ['iso2' => 'NO', 'iso3' => 'NOR', 'name' => 'Noruega', 'phone_code' => '+47', 'currency' => null],
            ['iso2' => 'DK', 'iso3' => 'DNK', 'name' => 'Dinamarca', 'phone_code' => '+45', 'currency' => null],
            ['iso2' => 'FI', 'iso3' => 'FIN', 'name' => 'Finlandia', 'phone_code' => '+358', 'currency' => 'EUR'],
            ['iso2' => 'PL', 'iso3' => 'POL', 'name' => 'Polonia', 'phone_code' => '+48', 'currency' => null],
            ['iso2' => 'GR', 'iso3' => 'GRC', 'name' => 'Grecia', 'phone_code' => '+30', 'currency' => 'EUR'],
            ['iso2' => 'RO', 'iso3' => 'ROU', 'name' => 'Rumania', 'phone_code' => '+40', 'currency' => null],
            ['iso2' => 'CZ', 'iso3' => 'CZE', 'name' => 'Republica Checa', 'phone_code' => '+420', 'currency' => null],
            ['iso2' => 'HU', 'iso3' => 'HUN', 'name' => 'Hungria', 'phone_code' => '+36', 'currency' => null],
            ['iso2' => 'RU', 'iso3' => 'RUS', 'name' => 'Rusia', 'phone_code' => '+7', 'currency' => null],
            ['iso2' => 'UA', 'iso3' => 'UKR', 'name' => 'Ucrania', 'phone_code' => '+380', 'currency' => null],

            // Asia
            ['iso2' => 'CN', 'iso3' => 'CHN', 'name' => 'China', 'phone_code' => '+86', 'currency' => null],
            ['iso2' => 'JP', 'iso3' => 'JPN', 'name' => 'Japon', 'phone_code' => '+81', 'currency' => null],
            ['iso2' => 'KR', 'iso3' => 'KOR', 'name' => 'Corea del Sur', 'phone_code' => '+82', 'currency' => null],
            ['iso2' => 'IN', 'iso3' => 'IND', 'name' => 'India', 'phone_code' => '+91', 'currency' => null],
            ['iso2' => 'ID', 'iso3' => 'IDN', 'name' => 'Indonesia', 'phone_code' => '+62', 'currency' => null],
            ['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Filipinas', 'phone_code' => '+63', 'currency' => null],
            ['iso2' => 'VN', 'iso3' => 'VNM', 'name' => 'Vietnam', 'phone_code' => '+84', 'currency' => null],
            ['iso2' => 'TH', 'iso3' => 'THA', 'name' => 'Tailandia', 'phone_code' => '+66', 'currency' => null],
            ['iso2' => 'MY', 'iso3' => 'MYS', 'name' => 'Malasia', 'phone_code' => '+60', 'currency' => null],
            ['iso2' => 'SG', 'iso3' => 'SGP', 'name' => 'Singapur', 'phone_code' => '+65', 'currency' => null],
            ['iso2' => 'TW', 'iso3' => 'TWN', 'name' => 'Taiwan', 'phone_code' => '+886', 'currency' => null],
            ['iso2' => 'HK', 'iso3' => 'HKG', 'name' => 'Hong Kong', 'phone_code' => '+852', 'currency' => null],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'name' => 'Pakistan', 'phone_code' => '+92', 'currency' => null],
            ['iso2' => 'BD', 'iso3' => 'BGD', 'name' => 'Bangladesh', 'phone_code' => '+880', 'currency' => null],
            ['iso2' => 'IL', 'iso3' => 'ISR', 'name' => 'Israel', 'phone_code' => '+972', 'currency' => null],
            ['iso2' => 'SA', 'iso3' => 'SAU', 'name' => 'Arabia Saudita', 'phone_code' => '+966', 'currency' => null],
            ['iso2' => 'AE', 'iso3' => 'ARE', 'name' => 'Emiratos Arabes Unidos', 'phone_code' => '+971', 'currency' => null],
            ['iso2' => 'TR', 'iso3' => 'TUR', 'name' => 'Turquia', 'phone_code' => '+90', 'currency' => null],
            ['iso2' => 'QA', 'iso3' => 'QAT', 'name' => 'Qatar', 'phone_code' => '+974', 'currency' => null],
            ['iso2' => 'KW', 'iso3' => 'KWT', 'name' => 'Kuwait', 'phone_code' => '+965', 'currency' => null],
            ['iso2' => 'JO', 'iso3' => 'JOR', 'name' => 'Jordania', 'phone_code' => '+962', 'currency' => null],
            ['iso2' => 'LB', 'iso3' => 'LBN', 'name' => 'Libano', 'phone_code' => '+961', 'currency' => null],
            ['iso2' => 'IR', 'iso3' => 'IRN', 'name' => 'Iran', 'phone_code' => '+98', 'currency' => null],
            ['iso2' => 'IQ', 'iso3' => 'IRQ', 'name' => 'Irak', 'phone_code' => '+964', 'currency' => null],

            // Africa
            ['iso2' => 'ZA', 'iso3' => 'ZAF', 'name' => 'Sudafrica', 'phone_code' => '+27', 'currency' => null],
            ['iso2' => 'EG', 'iso3' => 'EGY', 'name' => 'Egipto', 'phone_code' => '+20', 'currency' => null],
            ['iso2' => 'NG', 'iso3' => 'NGA', 'name' => 'Nigeria', 'phone_code' => '+234', 'currency' => null],
            ['iso2' => 'MA', 'iso3' => 'MAR', 'name' => 'Marruecos', 'phone_code' => '+212', 'currency' => null],
            ['iso2' => 'KE', 'iso3' => 'KEN', 'name' => 'Kenia', 'phone_code' => '+254', 'currency' => null],
            ['iso2' => 'GH', 'iso3' => 'GHA', 'name' => 'Ghana', 'phone_code' => '+233', 'currency' => null],
            ['iso2' => 'DZ', 'iso3' => 'DZA', 'name' => 'Argelia', 'phone_code' => '+213', 'currency' => null],
            ['iso2' => 'TN', 'iso3' => 'TUN', 'name' => 'Tunez', 'phone_code' => '+216', 'currency' => null],
            ['iso2' => 'ET', 'iso3' => 'ETH', 'name' => 'Etiopia', 'phone_code' => '+251', 'currency' => null],
            ['iso2' => 'SN', 'iso3' => 'SEN', 'name' => 'Senegal', 'phone_code' => '+221', 'currency' => null],

            // Oceania
            ['iso2' => 'AU', 'iso3' => 'AUS', 'name' => 'Australia', 'phone_code' => '+61', 'currency' => null],
            ['iso2' => 'NZ', 'iso3' => 'NZL', 'name' => 'Nueva Zelanda', 'phone_code' => '+64', 'currency' => null],
        ];

        foreach ($rows as $row) {
            Country::query()->updateOrCreate(
                ['iso2' => $row['iso2']],
                [
                    'iso3' => $row['iso3'],
                    'name' => $row['name'],
                    'phone_code' => $row['phone_code'],
                    'currency_id' => $row['currency'] ? ($currencyIds[$row['currency']] ?? null) : null,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedTimezones(mixed $now): void
    {
        if (! Schema::hasTable('timezones')) {
            return;
        }

        $rows = [
            ['name' => 'America/Managua', 'offset' => '-06:00', 'country_iso2' => 'NI'],
            ['name' => 'America/El_Salvador', 'offset' => '-06:00', 'country_iso2' => 'SV'],
            ['name' => 'America/Tegucigalpa', 'offset' => '-06:00', 'country_iso2' => 'HN'],
            ['name' => 'America/Guatemala', 'offset' => '-06:00', 'country_iso2' => 'GT'],
            ['name' => 'America/Costa_Rica', 'offset' => '-06:00', 'country_iso2' => 'CR'],
            ['name' => 'America/Mexico_City', 'offset' => '-06:00', 'country_iso2' => 'MX'],
            ['name' => 'America/New_York', 'offset' => '-05:00', 'country_iso2' => 'US'],
        ];

        foreach ($rows as $row) {
            Timezone::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'offset' => $row['offset'],
                    'country_iso2' => $row['country_iso2'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedCompanyDefaults(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('company_settings')) {
            return;
        }

        $service = app(CompanySettingsService::class);

        DB::table('companies')->orderBy('id')->pluck('id')->each(function ($companyId) use ($service) {
            $service->seedDefaults((int) $companyId);
        });
    }
}
