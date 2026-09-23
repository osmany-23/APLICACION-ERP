<?php

namespace App\Http\Controllers;

use App\Services\DebitNoteService;
use App\Traits\AuthorizesSales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebitNoteController extends Controller
{
    use AuthorizesSales;

    public function __construct(private DebitNoteService $debitNotes)
    {
    }

    public function store(Request $request, int $sale): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeSales($request);
        $companyId = (int) $user->company_id;

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'override_credit_limit' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0'],
        ], [
            'reason.required' => 'Indica la razon de la nota de debito.',
            'items.required' => 'Agrega al menos un cargo.',
            'items.min' => 'Agrega al menos un cargo.',
        ]);

        $validated['sale_id'] = $sale;

        $debitNote = $this->debitNotes->createDebitNote($companyId, (int) $user->id, $validated);

        return response()->json([
            'message' => 'Nota de debito '.$debitNote->debit_number.' generada correctamente.',
            'item' => [
                'id' => $debitNote->id,
                'number' => $debitNote->debit_number,
                'total' => (float) $debitNote->total,
                'reason' => $debitNote->reason,
                'created_at' => $debitNote->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
