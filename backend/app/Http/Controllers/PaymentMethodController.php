<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentMethodController extends Controller
{
    public function index(): JsonResponse
    {
        $items = DB::table('payment_methods')
            ->select(['id', 'code', 'name', 'description', 'requires_reference', 'requires_bank', 'is_active'])
            ->orderBy('name')
            ->get()
            ->map(fn ($method) => [
                'id' => (int) $method->id,
                'code' => (string) ($method->code ?? ''),
                'name' => (string) $method->name,
                'description' => $method->description !== null ? (string) $method->description : null,
                'requires_reference' => (bool) ($method->requires_reference ?? false),
                'requires_bank' => (bool) ($method->requires_bank ?? false),
                'is_active' => (bool) ($method->is_active ?? true),
            ]);

        return response()->json(['data' => $items]);
    }

    public function store(PaymentMethodRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $id = DB::table('payment_methods')->insertGetId([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'requires_reference' => (bool) ($validated['requires_reference'] ?? false),
            'requires_bank' => (bool) ($validated['requires_bank'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Método de pago guardado correctamente.',
            'item' => DB::table('payment_methods')->where('id', $id)->first(),
        ], 201);
    }

    public function update(PaymentMethodRequest $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        $validated = $request->validated();

        DB::table('payment_methods')->where('id', $id)->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => isset($validated['description']) ? trim($validated['description']) : null,
            'requires_reference' => (bool) ($validated['requires_reference'] ?? false),
            'requires_bank' => (bool) ($validated['requires_bank'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Método de pago guardado correctamente.',
            'item' => DB::table('payment_methods')->where('id', $id)->first(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        try {
            DB::table('payment_methods')->where('id', $id)->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json([
            'message' => 'Método de pago eliminado correctamente.',
            'deleted' => true,
        ]);
    }

    private function ensureItemExists(int $id): void
    {
        abort_unless(DB::table('payment_methods')->where('id', $id)->exists(), 404, 'Registro no encontrado.');
    }
}
