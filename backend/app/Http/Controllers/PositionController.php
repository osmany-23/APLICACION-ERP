<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    use StatusUpdateable;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;

        $positions = Position::query()
            ->where('company_id', $companyId)
            ->withCount('employees')
            ->with('department:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Position $position) => $this->payload($position));

        return response()->json(['data' => $positions]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $validated = $this->validatedPosition($request, $companyId);

        $position = Position::create([
            'company_id' => $companyId,
            'department_id' => $validated['department_id'] ?? null,
            'name' => trim($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
            'base_salary' => $validated['base_salary'] ?? null,
            'status' => $this->boolValue($validated['status'] ?? true),
        ]);

        return response()->json([
            'message' => 'Cargo creado correctamente.',
            'item' => $this->payload($position->fresh('department')),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $position = $this->findPosition($companyId, $id);
        $validated = $this->validatedPosition($request, $companyId, $id);

        $position->update([
            'department_id' => $validated['department_id'] ?? null,
            'name' => trim($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
            'base_salary' => $validated['base_salary'] ?? null,
            'status' => $this->boolValue($validated['status'] ?? true),
        ]);

        return response()->json([
            'message' => 'Cargo actualizado correctamente.',
            'item' => $this->payload($position->fresh('department')),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $position = $this->findPosition($companyId, $id);
        $validated = $this->validateStatusUpdate($request);

        return $this->changeStatus($position, $validated['status'] ? 1 : 0, 'status');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $position = $this->findPosition($companyId, $id);

        if ($position->employees()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el cargo porque tiene empleados asignados.',
            ], 409);
        }

        try {
            $position->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Cargo eliminado correctamente.', 'deleted' => true]);
    }

    private function payload(Position $position): array
    {
        return [
            'id' => $position->id,
            'name' => $position->name,
            'description' => $position->description,
            'department_id' => $position->department_id,
            'department_name' => $position->department?->name,
            'base_salary' => $position->base_salary !== null ? (float) $position->base_salary : null,
            'status' => (bool) $position->status,
            'employees_count' => (int) ($position->employees_count ?? 0),
        ];
    }

    private function validatedPosition(Request $request, int $companyId, ?int $positionId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('positions', 'name')->where('company_id', $companyId)->ignore($positionId),
            ],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('company_id', $companyId)],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in([1, 0, '1', '0', true, false])],
        ], [
            'name.required' => 'Ingresa el nombre del cargo.',
            'name.unique' => 'Ya existe un cargo con ese nombre.',
            'department_id.exists' => 'El departamento seleccionado no existe en esta empresa.',
        ]);
    }

    private function findPosition(int $companyId, int $id): Position
    {
        $position = Position::query()->where('company_id', $companyId)->where('id', $id)->first();

        abort_unless($position, 404, 'Cargo no encontrado.');

        return $position;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function boolValue(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }

    private function authorizeEmployees(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para empleados.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'empleados')
            ->whereIn('permissions.action_name', ['manage', 'administrar', 'view', 'ver'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar empleados.');
    }
}
