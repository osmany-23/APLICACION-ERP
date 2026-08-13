<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;

        $search = trim((string) $request->query('search', ''));

        $query = Employee::query()
            ->where('company_id', $companyId)
            ->with(['department:id,name', 'position:id,name', 'user:id,username,status'])
            ->orderBy('first_name');

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $employees = $query->get()->map(fn (Employee $employee) => $this->payload($employee));

        return response()->json(['data' => $employees]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;

        return response()->json(['item' => $this->payload($this->findEmployee($companyId, $id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->authenticatedUser($request);
        $this->authorizeEmployees($request);

        $companyId = (int) $actor->company_id;
        $validated = $this->validatedEmployee($request, $companyId);

        $employee = DB::transaction(function () use ($validated, $companyId) {
            $data = $this->employeeData($validated, $companyId);
            $data['code'] = $this->generateEmployeeCode($companyId);

            return Employee::create($data);
        });

        return response()->json([
            'message' => 'Empleado creado correctamente.',
            'item' => $this->payload($employee->fresh(['department', 'position', 'user'])),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $employee = $this->findEmployee($companyId, $id);
        $validated = $this->validatedEmployee($request, $companyId, $id);

        $warning = null;
        $becomingTerminated = $validated['status'] === 'TERMINATED' && $employee->status !== 'TERMINATED';
        // Se lee ANTES del update: si el request no trae user_id, employeeData()
        // no lo toca (ver mas abajo) y el vinculo actual sigue igual, pero de
        // todas formas conviene decidir a quien desactivar con el valor de
        // antes de tocar el registro, no depender de que sobreviva el update.
        $linkedUserIdBeforeUpdate = $employee->user_id;

        DB::transaction(function () use ($request, $validated, $companyId, $employee, $becomingTerminated, $linkedUserIdBeforeUpdate, &$warning) {
            $data = $this->employeeData($validated, $companyId);

            // user_id solo se toca si el request lo trajo explicitamente.
            // Sin esto, editar cualquier otro campo del empleado (ej. el
            // telefono) sin volver a mandar user_id lo desvincularia del
            // usuario por accidente, justo antes de decidir si hay que
            // desactivarlo por la terminacion.
            if (! $request->has('user_id')) {
                unset($data['user_id']);
            }

            $employee->update($data);

            // Baja automatica de acceso: si el empleado pasa a TERMINATED y
            // tiene un usuario del sistema vinculado, se desactiva ese
            // usuario (buena practica de seguridad: nadie que ya no trabaja
            // aqui deberia conservar acceso). Si ese usuario es el ultimo
            // que puede administrar usuarios, no lo desactiva solo -- avisa
            // para que un admin reasigne el acceso primero, en vez de dejar
            // la empresa sin nadie que pueda arreglarlo despues.
            if ($becomingTerminated && $linkedUserIdBeforeUpdate) {
                $linkedUser = User::find($linkedUserIdBeforeUpdate);

                if ($linkedUser && (int) $linkedUser->status === 1) {
                    if ($this->managesUsers($linkedUser->role_id) && ! $this->hasOtherActiveManager($companyId, $linkedUser->id)) {
                        $warning = "El empleado quedo como Terminado, pero su usuario \"{$linkedUser->username}\" sigue activo porque es el unico que puede administrar usuarios. Reasigna ese rol antes de desactivarlo manualmente.";
                    } else {
                        $linkedUser->update(['status' => 0]);
                    }
                }
            }
        });

        $employee = $employee->fresh(['department', 'position', 'user']);

        return response()->json([
            'message' => 'Empleado actualizado correctamente.'.($warning ? " {$warning}" : ''),
            'item' => $this->payload($employee),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeEmployees($request);
        $companyId = (int) $request->user()->company_id;
        $employee = $this->findEmployee($companyId, $id);

        if ($this->hasReferences($id)) {
            return response()->json([
                'message' => 'No se puede eliminar el empleado porque esta asignado como vendedor de clientes o encargado de un departamento.',
            ], 409);
        }

        try {
            $employee->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Empleado eliminado correctamente.', 'deleted' => true]);
    }

    private function payload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'code' => $employee->code,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'full_name' => $employee->full_name,
            'phone' => $employee->phone,
            'email' => $employee->email,
            'branch_id' => $employee->branch_id,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department?->name,
            'position_id' => $employee->position_id,
            'position_name' => $employee->position?->name,
            'salary' => $employee->salary !== null ? (float) $employee->salary : null,
            'hire_date' => $employee->hire_date?->toDateString(),
            'termination_date' => $employee->termination_date?->toDateString(),
            'status' => $employee->status,
            'status_label' => match ($employee->status) {
                'ACTIVE' => 'Activo',
                'INACTIVE' => 'Inactivo',
                'TERMINATED' => 'Terminado',
                default => $employee->status,
            },
            'user_id' => $employee->user_id,
            'user' => $employee->user ? [
                'id' => $employee->user->id,
                'username' => $employee->user->username,
                'status' => (int) $employee->user->status,
            ] : null,
        ];
    }

    private function validatedEmployee(Request $request, int $companyId, ?int $employeeId = null): array
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]{1,4}([\-\s][0-9]{1,4})*$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('company_id', $companyId)],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('company_id', $companyId)],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE', 'TERMINATED'])],
            'user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
                Rule::unique('employees', 'user_id')->ignore($employeeId),
            ],
        ], [
            'first_name.required' => 'Ingresa el nombre.',
            'last_name.required' => 'Ingresa el apellido.',
            'phone.regex' => 'El telefono solo puede tener numeros, espacios y guiones.',
            'department_id.exists' => 'El departamento seleccionado no existe en esta empresa.',
            'position_id.exists' => 'El cargo seleccionado no existe en esta empresa.',
            'termination_date.after_or_equal' => 'La fecha de baja no puede ser anterior a la fecha de contratacion.',
            'user_id.exists' => 'El usuario seleccionado no existe en esta empresa.',
            'user_id.unique' => 'Ese usuario ya esta vinculado a otro empleado.',
        ]);

        if ($validated['status'] === 'TERMINATED' && empty($validated['termination_date'])) {
            $validated['termination_date'] = now()->toDateString();
        }
        if ($validated['status'] !== 'TERMINATED') {
            $validated['termination_date'] = null;
        }

        return $validated;
    }

    private function employeeData(array $validated, int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'phone' => $this->nullableText($validated['phone'] ?? null),
            'email' => $this->nullableText($validated['email'] ?? null),
            'branch_id' => $validated['branch_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'position_id' => $validated['position_id'] ?? null,
            'salary' => $validated['salary'] ?? null,
            'hire_date' => $validated['hire_date'] ?? null,
            'termination_date' => $validated['termination_date'] ?? null,
            'status' => $validated['status'],
            'user_id' => $validated['user_id'] ?? null,
        ];
    }

    private function generateEmployeeCode(int $companyId): string
    {
        $codes = Employee::query()
            ->where('company_id', $companyId)
            ->where('code', 'like', 'EMP-%')
            ->pluck('code');

        $next = 1;

        foreach ($codes as $code) {
            if (preg_match('/^EMP-(\d+)$/', (string) $code, $matches)) {
                $next = max($next, (int) $matches[1] + 1);
            }
        }

        return sprintf('EMP-%06d', $next);
    }

    private function hasReferences(int $employeeId): bool
    {
        if (DB::table('customers')->where('salesperson_id', $employeeId)->exists()) {
            return true;
        }

        if (DB::table('departments')->where('manager_employee_id', $employeeId)->exists()) {
            return true;
        }

        return false;
    }

    private function managesUsers(?int $roleId): bool
    {
        if (! $roleId) {
            return false;
        }

        return DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.module_name', 'usuarios')
            ->whereIn('permissions.action_name', ['manage', 'administrar'])
            ->exists();
    }

    private function hasOtherActiveManager(int $companyId, int $excludingUserId): bool
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('id', '!=', $excludingUserId)
            ->where('status', 1)
            ->get(['id', 'role_id'])
            ->contains(fn (User $candidate) => $this->managesUsers($candidate->role_id));
    }

    private function findEmployee(int $companyId, int $id): Employee
    {
        $employee = Employee::query()
            ->with(['department:id,name', 'position:id,name', 'user:id,username,status'])
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        abort_unless($employee, 404, 'Empleado no encontrado.');

        return $employee;
    }

    private function authenticatedUser(Request $request): object
    {
        $user = $request->user();

        abort_unless($user && $user->id && $user->company_id, 403, 'No hay usuario autenticado o empresa asociada.');

        return $user;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
