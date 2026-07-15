<?php

namespace App\Http\Controllers;

use App\Traits\StatusUpdateable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    use StatusUpdateable;
    public function index(): JsonResponse
    {
        $items = DB::table('currencies')
            ->select(['id', 'code', 'name', 'symbol', 'decimal_places', 'is_active'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:3', Rule::unique('currencies', 'code')],
            'name' => ['required', 'string', 'max:60'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['required', 'boolean'],
        ]);

        $id = DB::table('currencies')->insertGetId([
            'code' => strtoupper($validated['code']),
            'name' => trim($validated['name']),
            'symbol' => trim((string) ($validated['symbol'] ?? '')) ?: null,
            'decimal_places' => (int) ($validated['decimal_places'] ?? 2),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Moneda guardada correctamente.', 'item' => DB::table('currencies')->where('id', $id)->first()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:3', Rule::unique('currencies', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:60'],
            'symbol' => ['required', 'string', 'max:10'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::table('currencies')->where('id', $id)->update([
            'code' => strtoupper($validated['code']),
            'name' => trim($validated['name']),
            'symbol' => trim((string) ($validated['symbol'] ?? '')) ?: null,
            'decimal_places' => (int) ($validated['decimal_places'] ?? 2),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Moneda guardada correctamente.', 'item' => DB::table('currencies')->where('id', $id)->first()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        DB::table('currencies')->where('id', $id)->delete();

        return response()->json(['message' => 'Moneda eliminada correctamente.', 'deleted' => true]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);
        $validated = $this->validateStatusUpdate($request);
        
        DB::table('currencies')
            ->where('id', $id)
            ->update(['is_active' => $validated['status']]);

        $currency = DB::table('currencies')->where('id', $id)->first();
        
        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente.',
            'data' => [
                'id' => (int) $currency->id,
                'is_active' => (bool) $currency->is_active,
                'status_label' => $currency->is_active ? 'Activo' : 'Inactivo',
            ],
        ], 200);
    }

    private function ensureItemExists(int $id): void
    {
        abort_unless(DB::table('currencies')->where('id', $id)->exists(), 404, 'Registro no encontrado.');
    }
}
