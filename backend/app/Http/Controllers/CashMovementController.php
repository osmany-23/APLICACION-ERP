<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Services\CashRegisterService;
use App\Traits\AuthorizesCashRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashMovementController extends Controller
{
    use AuthorizesCashRegister;

    public function __construct(private CashRegisterService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeCajaView($request);
        $user = $this->authenticatedUser($request);

        $validated = $request->validate([
            'cash_session_id' => ['required', 'integer'],
        ]);

        $session = $this->findSession($request, (int) $validated['cash_session_id']);

        $movements = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->with(['reason:id,name', 'user:id,full_name', 'authorizedBy:id,full_name', 'cancelledBy:id,full_name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CashMovement $movement) => $this->payload($movement));

        return response()->json(['data' => $movements]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $validated = $request->validate([
            'cash_session_id' => ['required', 'integer'],
            'type' => ['required', 'in:INGRESO,EGRESO'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason_id' => ['nullable', 'integer'],
            'reason_text' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'observation' => ['nullable', 'string'],
        ], [
            'amount.required' => 'Ingresa el monto del movimiento.',
            'amount.min' => 'El monto debe ser mayor que cero.',
        ]);

        $this->authorizeCajaAction($request, $validated['type'] === 'INGRESO' ? 'agregar_efectivo' : 'retirar_efectivo');

        $session = $this->findSession($request, (int) $validated['cash_session_id']);
        abort_unless((int) $session->opened_by === (int) $user->id || $this->canViewAllCajas($request), 403, 'Solo el responsable de esta caja puede registrar movimientos.');

        $movement = $this->service->addMovement($session, (int) $user->id, $validated['type'], $validated);

        return response()->json([
            'message' => $movement->status === 'PENDIENTE_AUTORIZACION'
                ? 'Movimiento registrado, queda pendiente de autorizacion por superar el limite de la caja.'
                : 'Movimiento registrado correctamente.',
            'item' => $this->payload($movement),
        ], 201);
    }

    public function authorize(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaAction($request, 'autorizar_movimiento');
        $user = $this->authenticatedUser($request);
        $movement = $this->findMovement($request, $id);

        $movement = $this->service->authorizeMovement($movement, (int) $user->id);

        return response()->json(['message' => 'Movimiento autorizado.', 'item' => $this->payload($movement)]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaAction($request, 'autorizar_movimiento');
        $user = $this->authenticatedUser($request);
        $movement = $this->findMovement($request, $id);

        $validated = $request->validate(['reason' => ['nullable', 'string']]);

        $movement = $this->service->rejectMovement($movement, (int) $user->id, $validated['reason'] ?? null);

        return response()->json(['message' => 'Movimiento rechazado.', 'item' => $this->payload($movement)]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaAction($request, 'anular_movimiento');
        $user = $this->authenticatedUser($request);
        $movement = $this->findMovement($request, $id);

        $validated = $request->validate([
            'reason' => ['required', 'string'],
        ], [
            'reason.required' => 'Indica el motivo de la anulacion.',
        ]);

        $movement = $this->service->cancelMovement($movement, (int) $user->id, $validated['reason']);

        return response()->json(['message' => 'Movimiento anulado.', 'item' => $this->payload($movement)]);
    }

    private function payload(CashMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'cash_session_id' => $movement->cash_session_id,
            'type' => $movement->type,
            'amount' => (float) $movement->amount,
            'reason_id' => $movement->reason_id,
            'reason_name' => $movement->reason?->name,
            'reason_text' => $movement->reason_text,
            'reference' => $movement->reference,
            'observation' => $movement->observation,
            'status' => $movement->status,
            'requires_authorization' => (bool) $movement->requires_authorization,
            'user' => $movement->user ? ['id' => $movement->user->id, 'full_name' => $movement->user->full_name] : null,
            'authorized_by' => $movement->authorizedBy ? ['id' => $movement->authorizedBy->id, 'full_name' => $movement->authorizedBy->full_name] : null,
            'authorized_at' => $movement->authorized_at?->toIso8601String(),
            'cancelled_by' => $movement->cancelledBy ? ['id' => $movement->cancelledBy->id, 'full_name' => $movement->cancelledBy->full_name] : null,
            'cancelled_at' => $movement->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $movement->cancellation_reason,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }

    private function findSession(Request $request, int $id): CashSession
    {
        $user = $this->authenticatedUser($request);
        $session = CashSession::query()->where('company_id', $user->company_id)->where('id', $id)->first();

        abort_unless($session, 404, 'Apertura no encontrada.');

        if (! $this->canViewAllCajas($request) && (int) $session->opened_by !== (int) $user->id) {
            abort(403, 'No tienes permiso para operar la caja de otro usuario.');
        }

        return $session;
    }

    private function findMovement(Request $request, int $id): CashMovement
    {
        $user = $this->authenticatedUser($request);
        $movement = CashMovement::query()->where('company_id', $user->company_id)->where('id', $id)->first();

        abort_unless($movement, 404, 'Movimiento no encontrado.');

        return $movement;
    }
}
