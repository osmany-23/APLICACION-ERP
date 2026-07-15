<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentTermRequest;
use App\Http\Resources\PaymentTermResource;
use App\Models\PaymentTerm;
use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentTermController extends Controller
{
    use StatusUpdateable;
    public function index(): JsonResponse
    {
        $items = PaymentTerm::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => PaymentTermResource::collection($items)->resolve(),
        ]);
    }

    public function store(PaymentTermRequest $request): JsonResponse
    {
        $term = DB::transaction(function () use ($request): PaymentTerm {
            $term = PaymentTerm::create($this->payload($request->validated()));
            $this->syncDefault($term);

            return $term->fresh();
        });

        return response()->json([
            'message' => 'Termino de pago guardado correctamente.',
            'item' => new PaymentTermResource($term),
        ], 201);
    }

    public function update(PaymentTermRequest $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        $term = DB::transaction(function () use ($request, $id): PaymentTerm {
            $term = PaymentTerm::findOrFail($id);
            $term->update($this->payload($request->validated(), $term));
            $this->syncDefault($term);

            return $term->fresh();
        });

        return response()->json([
            'message' => 'Termino de pago guardado correctamente.',
            'item' => new PaymentTermResource($term),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        if ($this->isReferenced($id)) {
            return response()->json([
                'message' => 'No se puede eliminar porque esta en uso en clientes, proveedores o transacciones.',
            ], 409);
        }

        try {
            PaymentTerm::findOrFail($id)->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Termino de pago eliminado correctamente.', 'deleted' => true]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);
        $validated = $this->validateStatusUpdate($request);
        
        $paymentTerm = PaymentTerm::findOrFail($id);
        return $this->changeStatus($paymentTerm, $validated['status'], 'is_active');
    }

    private function payload(array $validated, ?PaymentTerm $current = null): array
    {
        $type = (string) ($validated['type'] ?? $current?->type ?? 'CREDIT');
        $isCash = $type === 'CASH';
        $isCredit = in_array($type, ['CREDIT', 'INSTALLMENTS'], true);
        $isAdvance = $type === 'ADVANCE';
        $isInstallments = $type === 'INSTALLMENTS';
        $discountPercent = (float) ($validated['discount_percent'] ?? $current?->discount_percent ?? 0);

        return [
            'company_id' => array_key_exists('company_id', $validated) ? $validated['company_id'] : $current?->company_id,
            'code' => $this->nullableText($validated['code'] ?? $current?->code),
            'name' => trim((string) ($validated['name'] ?? $current?->name)),
            'description' => $this->nullableText($validated['description'] ?? $current?->description),
            'type' => $type,
            'cash' => $isCash || ($type === 'OTHER' && (bool) ($validated['cash'] ?? false)),
            'credit' => $isCredit || ($type === 'OTHER' && (bool) ($validated['credit'] ?? false)),
            'advance' => $isAdvance || ($type === 'OTHER' && (bool) ($validated['advance'] ?? false)),
            'days' => $isCash || $isAdvance ? 0 : (int) ($validated['days'] ?? $current?->days ?? 0),
            'discount_percent' => $discountPercent,
            'discount_days' => $discountPercent > 0 && array_key_exists('discount_days', $validated)
                ? $validated['discount_days']
                : ($discountPercent > 0 ? $current?->discount_days : null),
            'late_fee_percent' => $isCredit ? ($validated['late_fee_percent'] ?? $current?->late_fee_percent) : null,
            'down_payment_percent' => $isAdvance ? ($validated['down_payment_percent'] ?? $current?->down_payment_percent) : null,
            'allow_partial_payments' => $isInstallments || (bool) ($validated['allow_partial_payments'] ?? false),
            'installments' => $isInstallments ? (int) ($validated['installments'] ?? $current?->installments ?? 2) : null,
            'is_default' => (bool) ($validated['is_default'] ?? $current?->is_default ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? $current?->is_active ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? $current?->sort_order ?? 100),
        ];
    }

    private function syncDefault(PaymentTerm $term): void
    {
        if (! $term->is_default) {
            return;
        }

        PaymentTerm::query()
            ->whereKeyNot($term->id)
            ->when(
                $term->company_id === null,
                fn ($query) => $query->whereNull('company_id'),
                fn ($query) => $query->where('company_id', $term->company_id)
            )
            ->update(['is_default' => false]);
    }

    private function isReferenced(int $id): bool
    {
        foreach (['customers', 'suppliers', 'sales', 'purchases', 'quotations', 'payments'] as $table) {
            if ($this->hasReference($table, 'payment_term_id', $id)) {
                return true;
            }
        }

        return false;
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

    private function ensureItemExists(int $id): void
    {
        abort_unless(PaymentTerm::where('id', $id)->exists(), 404, 'Registro no encontrado.');
    }
}
