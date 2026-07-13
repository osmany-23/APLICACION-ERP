<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentTypeRequest;
use App\Models\DocumentType;
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

        $item = DB::transaction(function () use ($validated): DocumentType {
            $prefix = isset($validated['prefix']) ? strtoupper(trim($validated['prefix'])) : null;
            $code = $prefix ?: 'DOC';

            $documentType = DocumentType::create([
                'code' => $code,
                'name' => trim($validated['name']),
                'prefix' => $prefix,
                'next_number' => 1,
                'is_active' => (bool) $validated['is_active'],
            ]);

            return $documentType;
        });

        return response()->json([
            'message' => 'Tipo de documento guardado correctamente.',
            'item' => $this->serializeItem($item),
        ], 201);
    }

    public function update(DocumentTypeRequest $request, int $id): JsonResponse
    {
        $this->ensureItemExists($id);

        $validated = $request->validated();

        $item = DB::transaction(function () use ($validated, $id): DocumentType {
            $documentType = DocumentType::findOrFail($id);
            $prefix = isset($validated['prefix']) ? strtoupper(trim($validated['prefix'])) : null;
            $code = $prefix ?: 'DOC';

            $documentType->fill([
                'code' => $code,
                'name' => trim($validated['name']),
                'prefix' => $prefix,
                'is_active' => (bool) $validated['is_active'],
            ]);
            $documentType->save();

            return $documentType;
        });

        return response()->json([
            'message' => 'Tipo de documento guardado correctamente.',
            'item' => $this->serializeItem($item),
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

    private function serializeItem(DocumentType $documentType): array
    {
        return [
            'id' => (int) $documentType->id,
            'code' => (string) ($documentType->code ?? ''),
            'name' => (string) $documentType->name,
            'prefix' => $documentType->prefix !== null ? (string) $documentType->prefix : null,
            'next_number' => (int) ($documentType->next_number ?? 1),
            'is_active' => (bool) ($documentType->is_active ?? true),
        ];
    }
}
