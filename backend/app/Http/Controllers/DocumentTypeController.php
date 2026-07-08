<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentTypeRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DocumentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $items = DB::table('document_types')
            ->select(['id', 'code', 'name', 'prefix', 'next_number', 'is_active'])
            ->orderBy('name')
            ->get()
            ->map(fn ($type) => [
                'id' => (int) $type->id,
                'code' => (string) ($type->code ?? ''),
                'name' => (string) $type->name,
                'prefix' => $type->prefix !== null ? (string) $type->prefix : null,
                'next_number' => (int) ($type->next_number ?? 1),
                'is_active' => (bool) ($type->is_active ?? true),
            ]);

        return response()->json(['data' => $items]);
    }

    public function store(DocumentTypeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $id = DB::table('document_types')->insertGetId([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'prefix' => isset($validated['prefix']) ? trim($validated['prefix']) : null,
            'next_number' => (int) ($validated['next_number'] ?? 1),
            'is_active' => (bool) ($validated['is_active']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tipo de documento guardado correctamente.',
            'item' => DB::table('document_types')->where('id', $id)->first(),
        ], 201);
    }

    public function update(DocumentTypeRequest $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        $validated = $request->validated();

        DB::table('document_types')->where('id', $id)->update([
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'prefix' => isset($validated['prefix']) ? trim($validated['prefix']) : null,
            'next_number' => (int) ($validated['next_number'] ?? 1),
            'is_active' => (bool) ($validated['is_active']),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Tipo de documento guardado correctamente.',
            'item' => DB::table('document_types')->where('id', $id)->first(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        try {
            DB::table('document_types')->where('id', $id)->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Tipo de documento eliminado correctamente.', 'deleted' => true]);
    }

    private function ensureItemExists(int $id): void
    {
        abort_unless(DB::table('document_types')->where('id', $id)->exists(), 404, 'Registro no encontrado.');
    }
}
