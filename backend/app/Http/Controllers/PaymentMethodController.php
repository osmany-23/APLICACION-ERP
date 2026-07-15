<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Models\PaymentMethod;
use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentMethodController extends Controller
{
    use StatusUpdateable;
    public function index(): JsonResponse
    {
        $items = PaymentMethod::query()
            ->select(['id','company_id','code','name','description','type','is_active','is_default','sort_order'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(PaymentMethodRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $method = PaymentMethod::create([
            'company_id' => $validated['company_id'] ?? null,
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'] ?? null,
            'cash' => (bool) ($validated['cash'] ?? false),
            'card' => (bool) ($validated['card'] ?? false),
            'bank' => (bool) ($validated['bank'] ?? false),
            'check' => (bool) ($validated['check'] ?? false),
            'digital_wallet' => (bool) ($validated['digital_wallet'] ?? false),
            'credit' => (bool) ($validated['credit'] ?? false),
            'other' => (bool) ($validated['other'] ?? false),
            'requires_reference' => (bool) ($validated['requires_reference'] ?? false),
            'requires_bank' => (bool) ($validated['requires_bank'] ?? false),
            'requires_authorization' => (bool) ($validated['requires_authorization'] ?? false),
            'allow_change' => (bool) ($validated['allow_change'] ?? false),
            'allow_partial_payment' => (bool) ($validated['allow_partial_payment'] ?? false),
            'is_online' => (bool) ($validated['is_online'] ?? false),
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => $validated['sort_order'] ?? 100,
        ]);

        return response()->json(['message' => 'Método de pago guardado correctamente.', 'item' => new \App\Http\Resources\PaymentMethodResource($method)], 201);
    }

    public function update(PaymentMethodRequest $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        $validated = $request->validated();
        $method = PaymentMethod::findOrFail($id);
        $method->update([
            'company_id' => $validated['company_id'] ?? $method->company_id,
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? $method->description,
            'type' => $validated['type'] ?? $method->type,
            'cash' => (bool) ($validated['cash'] ?? $method->cash),
            'card' => (bool) ($validated['card'] ?? $method->card),
            'bank' => (bool) ($validated['bank'] ?? $method->bank),
            'check' => (bool) ($validated['check'] ?? $method->check),
            'digital_wallet' => (bool) ($validated['digital_wallet'] ?? $method->digital_wallet),
            'credit' => (bool) ($validated['credit'] ?? $method->credit),
            'other' => (bool) ($validated['other'] ?? $method->other),
            'requires_reference' => (bool) ($validated['requires_reference'] ?? $method->requires_reference),
            'requires_bank' => (bool) ($validated['requires_bank'] ?? $method->requires_bank),
            'requires_authorization' => (bool) ($validated['requires_authorization'] ?? $method->requires_authorization),
            'allow_change' => (bool) ($validated['allow_change'] ?? $method->allow_change),
            'allow_partial_payment' => (bool) ($validated['allow_partial_payment'] ?? $method->allow_partial_payment),
            'is_online' => (bool) ($validated['is_online'] ?? $method->is_online),
            'is_default' => (bool) ($validated['is_default'] ?? $method->is_default),
            'is_active' => (bool) ($validated['is_active'] ?? $method->is_active),
            'sort_order' => $validated['sort_order'] ?? $method->sort_order,
        ]);

        return response()->json(['message' => 'Método de pago guardado correctamente.', 'item' => new \App\Http\Resources\PaymentMethodResource($method->fresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        $method = PaymentMethod::findOrFail($id);

        // Prevent deletion if used in payments/sales/purchases
        $usedInPayments = DB::table('payments')->where('payment_method_id', $id)->exists() || DB::table('payments')->where('payment_method', $method->code)->exists();
        $usedInSales = DB::table('sales')->where('payment_method_id', $id)->exists();
        $usedInPurchases = DB::table('purchases')->where('payment_method_id', $id)->exists();

        if ($usedInPayments || $usedInSales || $usedInPurchases) {
            return response()->json(['message' => 'No se puede eliminar porque está en uso en transacciones.'], 409);
        }

        try {
            $method->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Método de pago eliminado correctamente.', 'deleted' => true]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);
        $validated = $this->validateStatusUpdate($request);
        
        $paymentMethod = PaymentMethod::findOrFail($id);
        return $this->changeStatus($paymentMethod, $validated['status'], 'is_active');
    }

    private function ensureItemExists(int $id): void
    {
        abort_unless(PaymentMethod::where('id', $id)->exists(), 404, 'Registro no encontrado.');
    }
}
