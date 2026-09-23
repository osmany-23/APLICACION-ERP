<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Services\CashRegisterService;
use App\Traits\AuthorizesCashRegister;
use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashRegisterController extends Controller
{
    use AuthorizesCashRegister;
    use StatusUpdateable;

    public function __construct(private CashRegisterService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeCajaView($request);
        $user = $this->authenticatedUser($request);
        $companyId = (int) $user->company_id;
        $canViewAll = $this->canViewAllCajas($request);

        $query = CashRegister::query()
            ->where('company_id', $companyId)
            ->with(['branch:id,name', 'currency:id,code,symbol', 'authorizedUsers:id,full_name'])
            ->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        $registers = $query->get()->map(fn (CashRegister $register) => $this->payload($register, (int) $user->id, $canViewAll));

        return response()->json(['data' => $registers]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaView($request);
        $user = $this->authenticatedUser($request);
        $register = $this->findRegister((int) $user->company_id, $id);

        return response()->json(['item' => $this->payload($register, (int) $user->id, $this->canViewAllCajas($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $user = $this->authenticatedUser($request);
        $companyId = (int) $user->company_id;
        $validated = $this->validatedRegister($request, $companyId);

        $register = CashRegister::create([
            'company_id' => $companyId,
            'branch_id' => $validated['branch_id'],
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
            'default_currency_id' => $validated['default_currency_id'] ?? 1,
            'status' => $validated['status'] ?? 'ACTIVA',
            'authorization_threshold' => $validated['authorization_threshold'] ?? null,
            'created_by' => $user->id,
        ]);

        if (! empty($validated['authorized_user_ids'])) {
            $this->service->syncAuthorizedUsers($register, $validated['authorized_user_ids']);
        }

        return response()->json([
            'message' => 'Caja creada correctamente.',
            'item' => $this->payload($register->fresh(['branch', 'currency', 'authorizedUsers']), (int) $user->id, true),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $user = $this->authenticatedUser($request);
        $companyId = (int) $user->company_id;
        $register = $this->findRegister($companyId, $id);
        $validated = $this->validatedRegister($request, $companyId, $id);

        $register->update([
            'branch_id' => $validated['branch_id'],
            'code' => strtoupper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
            'default_currency_id' => $validated['default_currency_id'] ?? $register->default_currency_id,
            'authorization_threshold' => array_key_exists('authorization_threshold', $validated) ? $validated['authorization_threshold'] : $register->authorization_threshold,
        ]);

        if (array_key_exists('authorized_user_ids', $validated)) {
            $this->service->syncAuthorizedUsers($register, $validated['authorized_user_ids'] ?? []);
        }

        return response()->json([
            'message' => 'Caja actualizada correctamente.',
            'item' => $this->payload($register->fresh(['branch', 'currency', 'authorizedUsers']), (int) $user->id, true),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $companyId = (int) $request->user()->company_id;
        $register = $this->findRegister($companyId, $id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['ACTIVA', 'INACTIVA', 'MANTENIMIENTO'])],
        ]);

        if ($validated['status'] !== 'ACTIVA' && $register->openSession()) {
            return response()->json([
                'message' => 'No se puede desactivar una caja con una apertura en curso. Ciérrala primero.',
            ], 409);
        }

        $register->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Estado de la caja actualizado.',
            'item' => $this->payload($register->fresh(['branch', 'currency', 'authorizedUsers']), (int) $request->user()->id, true),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $companyId = (int) $request->user()->company_id;
        $register = $this->findRegister($companyId, $id);

        if ($register->sessions()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la caja porque tiene aperturas registradas. Desactívala en su lugar.',
            ], 409);
        }

        try {
            $register->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Caja eliminada correctamente.', 'deleted' => true]);
    }

    private function payload(CashRegister $register, int $userId, bool $canViewAll): array
    {
        // N+1 deliberado: el numero de cajas por empresa es pequeño (unas
        // pocas por sucursal), no amerita la complejidad de precargar esto.
        $openSession = $register->openSession();

        return [
            'id' => $register->id,
            'code' => $register->code,
            'name' => $register->name,
            'description' => $register->description,
            'branch_id' => $register->branch_id,
            'branch_name' => $register->branch?->name,
            'default_currency_id' => $register->default_currency_id,
            'currency_code' => $register->currency?->code,
            'status' => $register->status,
            'authorization_threshold' => $register->authorization_threshold !== null ? (float) $register->authorization_threshold : null,
            'authorized_users' => $register->authorizedUsers->map(fn ($u) => ['id' => $u->id, 'full_name' => $u->full_name])->values(),
            'authorized_user_ids' => $register->authorizedUsers->pluck('id')->values(),
            'can_operate' => $this->service->userCanOperate($register, $userId, $canViewAll),
            'is_open' => (bool) $openSession,
            'open_session_id' => $openSession?->id,
        ];
    }

    private function validatedRegister(Request $request, int $companyId, ?int $registerId = null): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('cash_registers', 'code')->where('company_id', $companyId)->ignore($registerId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'default_currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'status' => ['sometimes', Rule::in(['ACTIVA', 'INACTIVA', 'MANTENIMIENTO'])],
            'authorization_threshold' => ['nullable', 'numeric', 'min:0'],
            'authorized_user_ids' => ['sometimes', 'array'],
            'authorized_user_ids.*' => ['integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
        ], [
            'branch_id.required' => 'Selecciona la sucursal de la caja.',
            'code.required' => 'Ingresa un codigo para la caja.',
            'code.unique' => 'Ya existe una caja con ese codigo en esta empresa.',
            'name.required' => 'Ingresa el nombre de la caja.',
        ]);
    }

    private function findRegister(int $companyId, int $id): CashRegister
    {
        $register = CashRegister::query()
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->with(['branch', 'currency', 'authorizedUsers'])
            ->first();

        abort_unless($register, 404, 'Caja no encontrada.');

        return $register;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
