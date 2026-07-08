<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GlobalCatalogController extends Controller
{
    private const CATALOGS = ['currencies', 'payment-terms', 'payment-methods'];

    public function index(Request $request, string $catalog): JsonResponse
    {
        $this->ensureCatalog($catalog);

        return response()->json([
            'data' => match ($catalog) {
                'currencies' => $this->currencies(),
                'payment-terms' => $this->paymentTerms(),
                'payment-methods' => $this->paymentMethods(),
            },
        ]);
    }

    public function store(Request $request, string $catalog): JsonResponse
    {
        $this->ensureCatalog($catalog);

        $id = match ($catalog) {
            'currencies' => $this->storeCurrency($request),
            'payment-terms' => $this->storePaymentTerm($request),
            'payment-methods' => $this->storePaymentMethod($request),
        };

        return response()->json([
            'message' => $this->savedMessage($catalog),
            'item' => $this->findItem($catalog, $id),
        ], 201);
    }

    public function update(Request $request, string $catalog, int $id): JsonResponse
    {
        $this->ensureCatalog($catalog);
        $this->ensureItemExists($catalog, $id);

        match ($catalog) {
            'currencies' => $this->updateCurrency($request, $id),
            'payment-terms' => $this->updatePaymentTerm($request, $id),
            'payment-methods' => $this->updatePaymentMethod($request, $id),
        };

        return response()->json([
            'message' => $this->savedMessage($catalog),
            'item' => $this->findItem($catalog, $id),
        ]);
    }

    public function destroy(Request $request, string $catalog, int $id): JsonResponse
    {
        $this->ensureCatalog($catalog);
        $this->ensureItemExists($catalog, $id);

        try {
            match ($catalog) {
                'currencies' => DB::table('currencies')->where('id', $id)->delete(),
                'payment-terms' => DB::table('payment_terms')->where('id', $id)->delete(),
                'payment-methods' => DB::table('payment_methods')->where('id', $id)->delete(),
            };
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json([
            'message' => $this->deletedMessage($catalog),
            'deleted' => true,
        ]);
    }

    private function currencies(): array
    {
        return DB::table('currencies')
            ->select(['id', 'code', 'name', 'symbol', 'decimal_places', 'is_active'])
            ->orderBy('name')
            ->get()
            ->map(fn ($currency) => [
                'id' => (int) $currency->id,
                'code' => (string) ($currency->code ?? ''),
                'name' => (string) $currency->name,
                'symbol' => (string) ($currency->symbol ?? ''),
                'decimal_places' => (int) ($currency->decimal_places ?? 2),
                'is_active' => (bool) ($currency->is_active ?? true),
            ])
            ->values()
            ->all();
    }

    private function paymentTerms(): array
    {
        return DB::table('payment_terms')
            ->select(['id', 'name', 'days', 'discount_percent', 'discount_days', 'is_active'])
            ->orderBy('name')
            ->get()
            ->map(fn ($term) => [
                'id' => (int) $term->id,
                'name' => (string) $term->name,
                'days' => (int) ($term->days ?? 0),
                'discount_percent' => (float) ($term->discount_percent ?? 0),
                'discount_days' => (int) ($term->discount_days ?? 0),
                'is_active' => (bool) ($term->is_active ?? true),
            ])
            ->values()
            ->all();
    }

    private function paymentMethods(): array
    {
        return DB::table('payment_methods')
            ->select(['id', 'code', 'name', 'description', 'is_active'])
            ->orderBy('name')
            ->get()
            ->map(fn ($method) => [
                'id' => (int) $method->id,
                'code' => (string) ($method->code ?? ''),
                'name' => (string) $method->name,
                'description' => (string) ($method->description ?? ''),
                'is_active' => (bool) ($method->is_active ?? true),
            ])
            ->values()
            ->all();
    }

    private function storeCurrency(Request $request): int
    {
        $validated = $this->currencyValidation($request);

        return DB::table('currencies')->insertGetId([
            'code' => strtoupper($validated['code']),
            'name' => $this->cleanName($validated['name']),
            'symbol' => $this->nullableText($validated['symbol'] ?? null),
            'decimal_places' => (int) ($validated['decimal_places'] ?? 2),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function storePaymentTerm(Request $request): int
    {
        $validated = $this->paymentTermValidation($request);

        return DB::table('payment_terms')->insertGetId([
            'name' => $this->cleanName($validated['name']),
            'days' => (int) ($validated['days'] ?? 0),
            'discount_percent' => (float) ($validated['discount_percent'] ?? 0),
            'discount_days' => $validated['discount_days'] !== null ? (int) $validated['discount_days'] : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function storePaymentMethod(Request $request): int
    {
        $validated = $this->paymentMethodValidation($request);

        return DB::table('payment_methods')->insertGetId([
            'code' => strtoupper($validated['code']),
            'name' => $this->cleanName($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateCurrency(Request $request, int $id): void
    {
        $validated = $this->currencyValidation($request);

        DB::table('currencies')->where('id', $id)->update([
            'code' => strtoupper($validated['code']),
            'name' => $this->cleanName($validated['name']),
            'symbol' => $this->nullableText($validated['symbol'] ?? null),
            'decimal_places' => (int) ($validated['decimal_places'] ?? 2),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_at' => now(),
        ]);
    }

    private function updatePaymentTerm(Request $request, int $id): void
    {
        $validated = $this->paymentTermValidation($request);

        DB::table('payment_terms')->where('id', $id)->update([
            'name' => $this->cleanName($validated['name']),
            'days' => (int) ($validated['days'] ?? 0),
            'discount_percent' => (float) ($validated['discount_percent'] ?? 0),
            'discount_days' => $validated['discount_days'] !== null ? (int) $validated['discount_days'] : null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_at' => now(),
        ]);
    }

    private function updatePaymentMethod(Request $request, int $id): void
    {
        $validated = $this->paymentMethodValidation($request);

        DB::table('payment_methods')->where('id', $id)->update([
            'code' => strtoupper($validated['code']),
            'name' => $this->cleanName($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_at' => now(),
        ]);
    }

    private function currencyValidation(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:3', Rule::unique('currencies', 'code')->ignore($request->route('id'))],
            'name' => ['required', 'string', 'max:60'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['required', 'boolean'],
        ], $this->messages());
    }

    private function paymentTermValidation(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('payment_terms', 'name')->ignore($request->route('id'))],
            'days' => ['nullable', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ], $this->messages());
    }

    private function paymentMethodValidation(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('payment_methods', 'code')->ignore($request->route('id'))],
            'name' => ['required', 'string', 'max:80', Rule::unique('payment_methods', 'name')->ignore($request->route('id'))],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ], $this->messages());
    }

    private function ensureCatalog(string $catalog): void
    {
        abort_unless(in_array($catalog, self::CATALOGS, true), 404, 'Catálogo no encontrado.');
    }

    private function ensureItemExists(string $catalog, int $id): void
    {
        $table = match ($catalog) {
            'currencies' => 'currencies',
            'payment-terms' => 'payment_terms',
            'payment-methods' => 'payment_methods',
        };

        abort_unless(DB::table($table)->where('id', $id)->exists(), 404, 'Registro no encontrado.');
    }

    private function findItem(string $catalog, int $id): array
    {
        $record = match ($catalog) {
            'currencies' => DB::table('currencies')->where('id', $id)->first(),
            'payment-terms' => DB::table('payment_terms')->where('id', $id)->first(),
            'payment-methods' => DB::table('payment_methods')->where('id', $id)->first(),
        };

        return $record ? (array) $record : [];
    }

    private function savedMessage(string $catalog): string
    {
        return match ($catalog) {
            'currencies' => 'Moneda guardada correctamente.',
            'payment-terms' => 'Término de pago guardado correctamente.',
            'payment-methods' => 'Método de pago guardado correctamente.',
        };
    }

    private function deletedMessage(string $catalog): string
    {
        return match ($catalog) {
            'currencies' => 'Moneda eliminada correctamente.',
            'payment-terms' => 'Término de pago eliminado correctamente.',
            'payment-methods' => 'Método de pago eliminado correctamente.',
        };
    }

    private function cleanName(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function nullableText(?string $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function messages(): array
    {
        return [
            'code.required' => 'El código es obligatorio.',
            'code.required' => 'El código es obligatorio.',
            'code.unique' => 'El código ya existe.',
            'name.required' => 'El nombre es obligatorio.',
            'name.unique' => 'El nombre ya existe.',
            'symbol.required' => 'El símbolo es obligatorio.',
            'decimal_places.integer' => 'Los decimales deben ser un número entero.',
            'decimal_places.min' => 'Los decimales no pueden ser negativos.',
            'decimal_places.max' => 'Los decimales no pueden superar 6.',
            'days.integer' => 'Los días deben ser un número entero.',
            'days.min' => 'Los días no pueden ser negativos.',
            'discount_percent.numeric' => 'El descuento debe ser numérico.',
            'discount_percent.min' => 'El descuento no puede ser menor a 0.',
            'discount_percent.max' => 'El descuento no puede ser mayor a 100.',
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}
