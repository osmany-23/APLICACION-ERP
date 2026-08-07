<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class PaymentMethodController extends Controller
{
    use StatusUpdateable;

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PaymentMethod::class);

        $items = PaymentMethod::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => PaymentMethodResource::collection($items)->resolve(),
        ]);
    }

    public function store(PaymentMethodRequest $request): JsonResponse
    {
        Gate::authorize('create', PaymentMethod::class);

        $method = DB::transaction(function () use ($request): PaymentMethod {
            $method = PaymentMethod::create($this->payload($request->validated()));
            $this->syncDefault($method);

            return $method->fresh();
        });

        return response()->json([
            'message' => 'Metodo de pago guardado correctamente.',
            'item' => new PaymentMethodResource($method),
        ], 201);
    }

    public function update(PaymentMethodRequest $request, int $id): JsonResponse
    {
        $method = PaymentMethod::findOrFail($id);
        Gate::authorize('update', $method);

        $method = DB::transaction(function () use ($request, $method): PaymentMethod {
            $method->update($this->payload($request->validated(), $method));
            $this->syncDefault($method);

            return $method->fresh();
        });

        return response()->json([
            'message' => 'Metodo de pago guardado correctamente.',
            'item' => new PaymentMethodResource($method),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $method = PaymentMethod::findOrFail($id);
        Gate::authorize('delete', $method);

        if ($this->isReferenced($method)) {
            return response()->json(['message' => 'No se puede eliminar porque esta en uso en transacciones.'], 409);
        }

        try {
            $method->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Metodo de pago eliminado correctamente.', 'deleted' => true]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        Gate::authorize('update', $paymentMethod);

        $validated = $this->validateStatusUpdate($request);

        return $this->changeStatus($paymentMethod, $validated['status'], 'is_active');
    }

    private function payload(array $validated, ?PaymentMethod $current = null): array
    {
        $type = (string) ($validated['type'] ?? $current?->type ?? 'OTHER');
        $flags = $this->flagsForType($type, $validated, $current);

        return [
            'company_id' => array_key_exists('company_id', $validated) ? $validated['company_id'] : $current?->company_id,
            'code' => strtoupper(trim((string) ($validated['code'] ?? $current?->code))),
            'name' => trim((string) ($validated['name'] ?? $current?->name)),
            'description' => $this->nullableText($validated['description'] ?? $current?->description),
            'type' => $type,
            'cash' => $flags['cash'],
            'card' => $flags['card'],
            'bank' => $flags['bank'],
            'check' => $flags['check'],
            'digital_wallet' => $flags['digital_wallet'],
            'credit' => $flags['credit'],
            'other' => $flags['other'],
            'requires_reference' => in_array($type, ['BANK_TRANSFER', 'DEPOSIT', 'CHECK'], true)
                || (bool) ($validated['requires_reference'] ?? $current?->requires_reference ?? false),
            'requires_bank' => in_array($type, ['BANK_TRANSFER', 'DEPOSIT', 'CHECK'], true)
                || (bool) ($validated['requires_bank'] ?? $current?->requires_bank ?? false),
            'requires_authorization' => (bool) ($validated['requires_authorization'] ?? $current?->requires_authorization ?? false),
            'allow_change' => $type === 'CASH' || (bool) ($validated['allow_change'] ?? $current?->allow_change ?? false),
            'allow_partial_payment' => (bool) ($validated['allow_partial_payment'] ?? $current?->allow_partial_payment ?? true),
            'is_online' => $type === 'DIGITAL_WALLET' || (bool) ($validated['is_online'] ?? $current?->is_online ?? false),
            'is_default' => (bool) ($validated['is_default'] ?? $current?->is_default ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? $current?->is_active ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? $current?->sort_order ?? 100),
        ];
    }

    /**
     * @return array{cash: bool, card: bool, bank: bool, check: bool, digital_wallet: bool, credit: bool, other: bool}
     */
    private function flagsForType(string $type, array $validated, ?PaymentMethod $current): array
    {
        $base = [
            'cash' => $type === 'CASH',
            'card' => $type === 'CARD',
            'bank' => in_array($type, ['BANK_TRANSFER', 'DEPOSIT'], true),
            'check' => $type === 'CHECK',
            'digital_wallet' => $type === 'DIGITAL_WALLET',
            'credit' => $type === 'CREDIT_INTERNAL',
            'other' => $type === 'OTHER',
        ];

        if ($type !== 'OTHER') {
            return $base;
        }

        foreach (array_keys($base) as $flag) {
            $base[$flag] = (bool) ($validated[$flag] ?? $current?->{$flag} ?? ($flag === 'other'));
        }

        return $base;
    }

    private function syncDefault(PaymentMethod $method): void
    {
        if (! $method->is_default) {
            return;
        }

        PaymentMethod::query()
            ->whereKeyNot($method->id)
            ->when(
                $method->company_id === null,
                fn ($query) => $query->whereNull('company_id'),
                fn ($query) => $query->where('company_id', $method->company_id)
            )
            ->update(['is_default' => false]);
    }

    private function isReferenced(PaymentMethod $method): bool
    {
        foreach (['sales', 'purchases', 'quotations', 'customers', 'suppliers', 'payments'] as $table) {
            if ($this->hasReference($table, 'payment_method_id', $method->id)) {
                return true;
            }
        }

        return Schema::hasTable('payments')
            && Schema::hasColumn('payments', 'payment_method')
            && DB::table('payments')->where('payment_method', $method->code)->exists();
    }

    private function hasReference(string $table, string $column, int $id): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, $column)
            && DB::table($table)->where($column, $id)->exists();
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
