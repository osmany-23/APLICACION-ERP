<?php

namespace App\Http\Controllers;

use App\Models\CashMovementReason;
use App\Traits\AuthorizesCashRegister;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashMovementReasonController extends Controller
{
    use AuthorizesCashRegister;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeCajaView($request);
        $companyId = (int) $request->user()->company_id;

        $reasons = CashMovementReason::query()
            ->forCompany($companyId)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CashMovementReason $reason) => $this->payload($reason));

        return response()->json(['data' => $reasons]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $companyId = (int) $request->user()->company_id;
        $validated = $this->validatedReason($request, $companyId);

        $reason = CashMovementReason::create([
            'company_id' => $companyId,
            'type' => $validated['type'],
            'name' => trim($validated['name']),
            'requires_note' => $this->boolValue($validated['requires_note'] ?? false),
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $validated['sort_order'] ?? 100,
        ]);

        return response()->json([
            'message' => 'Motivo creado correctamente.',
            'item' => $this->payload($reason),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $companyId = (int) $request->user()->company_id;
        $reason = $this->findReason($companyId, $id);

        abort_if($reason->is_system, 422, 'Los motivos del catalogo del sistema no se pueden editar.');

        $validated = $this->validatedReason($request, $companyId, $id);

        $reason->update([
            'type' => $validated['type'],
            'name' => trim($validated['name']),
            'requires_note' => $this->boolValue($validated['requires_note'] ?? false),
            'is_active' => array_key_exists('is_active', $validated) ? $this->boolValue($validated['is_active']) : $reason->is_active,
            'sort_order' => $validated['sort_order'] ?? $reason->sort_order,
        ]);

        return response()->json([
            'message' => 'Motivo actualizado correctamente.',
            'item' => $this->payload($reason->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $companyId = (int) $request->user()->company_id;
        $reason = $this->findReason($companyId, $id);

        abort_if($reason->is_system, 422, 'Los motivos del catalogo del sistema no se pueden eliminar.');

        if ($reason->movements()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el motivo porque tiene movimientos registrados. Desactívalo en su lugar.',
            ], 409);
        }

        try {
            $reason->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Motivo eliminado correctamente.', 'deleted' => true]);
    }

    private function payload(CashMovementReason $reason): array
    {
        return [
            'id' => $reason->id,
            'company_id' => $reason->company_id,
            'type' => $reason->type,
            'name' => $reason->name,
            'requires_note' => (bool) $reason->requires_note,
            'is_system' => (bool) $reason->is_system,
            'is_active' => (bool) $reason->is_active,
            'sort_order' => $reason->sort_order,
        ];
    }

    private function validatedReason(Request $request, int $companyId, ?int $reasonId = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['INGRESO', 'EGRESO'])],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('cash_movement_reasons', 'name')
                    ->where('company_id', $companyId)
                    ->where('type', $request->input('type'))
                    ->ignore($reasonId),
            ],
            'requires_note' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'name.required' => 'Ingresa el nombre del motivo.',
            'name.unique' => 'Ya existe un motivo con ese nombre para ese tipo.',
        ]);
    }

    private function findReason(int $companyId, int $id): CashMovementReason
    {
        $reason = CashMovementReason::query()->where('company_id', $companyId)->where('id', $id)->first();

        abort_unless($reason, 404, 'Motivo no encontrado.');

        return $reason;
    }

    private function boolValue(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }
}
