<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use App\Services\CashRegisterService;
use App\Traits\AuthorizesCashRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TerminalController extends Controller
{
    use AuthorizesCashRegister;

    public function __construct(private CashRegisterService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeCajaView($request);
        $companyId = (int) $request->user()->company_id;

        $terminals = Terminal::query()
            ->where('company_id', $companyId)
            ->with(['branch:id,name', 'cashRegister:id,name,code', 'currentUser:id,full_name'])
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (Terminal $terminal) => $this->payload($terminal));

        return response()->json(['data' => $terminals]);
    }

    /**
     * Auto-registro/actualizacion de una terminal (patron "self check-in"):
     * cualquier usuario autenticado puede llamarlo, no requiere permiso
     * especial de administracion de caja — es solo metadata de auditoria
     * (IP, SO, navegador, ultima conexion, usuario actual).
     */
    public function checkin(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $validated = $request->validate([
            'terminal_code' => ['required', 'string', 'max:40'],
            'terminal_name' => ['nullable', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $terminal = $this->service->checkinTerminal(
            (int) $user->company_id,
            $user->branch_id ? (int) $user->branch_id : null,
            (int) $user->id,
            array_merge($validated, [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ])
        );

        return response()->json(['item' => $this->payload($terminal->fresh(['branch', 'cashRegister', 'currentUser']))]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $companyId = (int) $request->user()->company_id;
        $terminal = $this->findTerminal($companyId, $id);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'cash_register_id' => ['nullable', 'integer', Rule::exists('cash_registers', 'id')->where('company_id', $companyId)],
            'status' => ['sometimes', Rule::in(['ACTIVA', 'INACTIVA'])],
        ]);

        $terminal->update([
            'name' => array_key_exists('name', $validated) ? $this->nullableText($validated['name']) : $terminal->name,
            'cash_register_id' => $validated['cash_register_id'] ?? null,
            'status' => $validated['status'] ?? $terminal->status,
        ]);

        return response()->json([
            'message' => 'Terminal actualizada correctamente.',
            'item' => $this->payload($terminal->fresh(['branch', 'cashRegister', 'currentUser'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeCajaManage($request);
        $companyId = (int) $request->user()->company_id;
        $terminal = $this->findTerminal($companyId, $id);

        if ($terminal->cashSessions()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la terminal porque tiene aperturas de caja registradas.',
            ], 409);
        }

        $terminal->delete();

        return response()->json(['message' => 'Terminal eliminada correctamente.', 'deleted' => true]);
    }

    private function payload(Terminal $terminal): array
    {
        return [
            'id' => $terminal->id,
            'code' => $terminal->code,
            'name' => $terminal->name,
            'device_name' => $terminal->device_name,
            'branch_id' => $terminal->branch_id,
            'branch_name' => $terminal->branch?->name,
            'cash_register_id' => $terminal->cash_register_id,
            'cash_register_name' => $terminal->cashRegister?->name,
            'ip_address' => $terminal->ip_address,
            'os_info' => $terminal->os_info,
            'browser_info' => $terminal->browser_info,
            'status' => $terminal->status,
            'last_seen_at' => $terminal->last_seen_at?->toIso8601String(),
            'current_user' => $terminal->currentUser ? ['id' => $terminal->currentUser->id, 'full_name' => $terminal->currentUser->full_name] : null,
        ];
    }

    private function findTerminal(int $companyId, int $id): Terminal
    {
        $terminal = Terminal::query()->where('company_id', $companyId)->where('id', $id)->first();

        abort_unless($terminal, 404, 'Terminal no encontrada.');

        return $terminal;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
