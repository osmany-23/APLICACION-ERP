<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentTermRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentTermController extends Controller
{
    public function index(): JsonResponse
    {
        $items = DB::table('payment_terms')
            ->select(['id', 'name', 'days', 'discount_percent', 'discount_days', 'is_active'])
            ->orderBy('name')
            ->get()
            ->map(fn ($term) => [
                'id' => (int) $term->id,
                'name' => (string) $term->name,
                'days' => (int) ($term->days ?? 0),
                'discount_percent' => (float) ($term->discount_percent ?? 0),
                'discount_days' => $term->discount_days !== null ? (int) $term->discount_days : null,
                'is_active' => (bool) ($term->is_active ?? true),
            ]);

        return response()->json(['data' => $items]);
    }

    public function store(PaymentTermRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $id = DB::table('payment_terms')->insertGetId([
            'name' => trim($validated['name']),
            'days' => (int) ($validated['days'] ?? 0),
            'discount_percent' => (float) ($validated['discount_percent'] ?? 0),
            'discount_days' => isset($validated['discount_days']) ? (int) $validated['discount_days'] : null,
            'is_active' => (bool) ($validated['is_active']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Término de pago guardado correctamente.',
            'item' => DB::table('payment_terms')->where('id', $id)->first(),
        ], 201);
    }

    public function update(PaymentTermRequest $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        $validated = $request->validated();

        DB::table('payment_terms')->where('id', $id)->update([
            'name' => trim($validated['name']),
            'days' => (int) ($validated['days'] ?? 0),
            'discount_percent' => (float) ($validated['discount_percent'] ?? 0),
            'discount_days' => isset($validated['discount_days']) ? (int) $validated['discount_days'] : null,
            'is_active' => (bool) ($validated['is_active']),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Término de pago guardado correctamente.',
            'item' => DB::table('payment_terms')->where('id', $id)->first(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        try {
            DB::table('payment_terms')->where('id', $id)->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Término de pago eliminado correctamente.', 'deleted' => true]);
    }

    private function ensureItemExists(int $id): void
    {
        abort_unless(DB::table('payment_terms')->where('id', $id)->exists(), 404, 'Registro no encontrado.');
    }
}
