<?php

namespace App\Http\Controllers;

use App\Services\CreditNoteService;
use App\Traits\AuthorizesSales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    use AuthorizesSales;

    public function __construct(private CreditNoteService $creditNotes)
    {
    }

    public function store(Request $request, int $sale): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeSales($request);
        $companyId = (int) $user->company_id;

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ], [
            'reason.required' => 'Indica la razon de la nota de credito.',
            'items.required' => 'Selecciona al menos un producto a acreditar.',
            'items.min' => 'Selecciona al menos un producto a acreditar.',
        ]);

        $validated['sale_id'] = $sale;

        $creditNote = $this->creditNotes->createCreditNote($companyId, (int) $user->id, $validated);

        return response()->json([
            'message' => 'Nota de credito '.$creditNote->return_number.' generada correctamente.',
            'item' => [
                'id' => $creditNote->id,
                'number' => $creditNote->return_number,
                'total' => (float) $creditNote->total,
                'reason' => $creditNote->reason,
                'created_at' => $creditNote->created_at?->toIso8601String(),
            ],
        ], 201);
    }

}
