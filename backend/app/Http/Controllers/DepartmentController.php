<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    use StatusUpdateable;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;

        $departments = Department::query()
            ->where('company_id', $companyId)
            ->withCount('employees')
            ->with('managerEmployee:id,first_name,last_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => $this->payload($department));

        return response()->json(['data' => $departments]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $validated = $this->validatedDepartment($request, $companyId);

        $department = Department::create([
            'company_id' => $companyId,
            'name' => trim($validated['name']),
            'manager_employee_id' => $validated['manager_employee_id'] ?? null,
            'status' => $this->boolValue($validated['status'] ?? true),
        ]);

        return response()->json([
            'message' => 'Departamento creado correctamente.',
            'item' => $this->payload($department->fresh('managerEmployee')),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $department = $this->findDepartment($companyId, $id);
        $validated = $this->validatedDepartment($request, $companyId, $id);

        $department->update([
            'name' => trim($validated['name']),
            'manager_employee_id' => $validated['manager_employee_id'] ?? null,
            'status' => $this->boolValue($validated['status'] ?? true),
        ]);

        return response()->json([
            'message' => 'Departamento actualizado correctamente.',
            'item' => $this->payload($department->fresh('managerEmployee')),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $department = $this->findDepartment($companyId, $id);
        $validated = $this->validateStatusUpdate($request);

        return $this->changeStatus($department, $validated['status'] ? 1 : 0, 'status');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $department = $this->findDepartment($companyId, $id);

        if ($department->employees()->exists() || $department->positions()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el departamento porque tiene empleados o cargos asociados.',
            ], 409);
        }

        try {
            $department->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Departamento eliminado correctamente.', 'deleted' => true]);
    }

    private function payload(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'manager_employee_id' => $department->manager_employee_id,
            'manager_name' => $department->managerEmployee?->full_name,
            'status' => (bool) $department->status,
            'employees_count' => (int) ($department->employees_count ?? 0),
        ];
    }

    private function validatedDepartment(Request $request, int $companyId, ?int $departmentId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('departments', 'name')->where('company_id', $companyId)->ignore($departmentId),
            ],
            'manager_employee_id' => [
                'nullable', 'integer',
                Rule::exists('employees', 'id')->where('company_id', $companyId),
            ],
            'status' => ['sometimes', Rule::in([1, 0, '1', '0', true, false])],
        ], [
            'name.required' => 'Ingresa el nombre del departamento.',
            'name.unique' => 'Ya existe un departamento con ese nombre.',
            'manager_employee_id.exists' => 'El empleado seleccionado como encargado no existe en esta empresa.',
        ]);
    }

    private function findDepartment(int $companyId, int $id): Department
    {
        $department = Department::query()->where('company_id', $companyId)->where('id', $id)->first();

        abort_unless($department, 404, 'Departamento no encontrado.');

        return $department;
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
