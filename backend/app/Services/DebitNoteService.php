<?php

namespace App\Services;

use App\Models\AccountsReceivable;
use App\Models\Customer;
use App\Models\DocumentType;
use App\Models\Sale;
use App\Models\SalesDebitNote;
use App\Models\SalesDebitNoteItem;
use Illuminate\Support\Facades\DB;

/**
 * Nota de Debito: agrega un cargo adicional a una factura ya emitida
 * (COMPLETED), incrementando siempre lo que el cliente debe. Sin impacto de
 * inventario (no hay devolucion fisica de producto). Mismo candado de
 * limite de credito que SalesService::finalizeSale() usa para ventas a
 * credito nuevas.
 */
class DebitNoteService
{
    public function __construct(private JournalPostingService $journalPosting)
    {
    }

    /**
     * $payload: [
     *   'sale_id' => int, 'reason' => string, 'override_credit_limit' => bool,
     *   'items' => [['product_id' => ?int, 'description' => ?string, 'quantity' => float, 'unit_price' => float, 'tax' => ?float], ...],
     * ]
     */
    public function createDebitNote(int $companyId, int $userId, array $payload): SalesDebitNote
    {
        return DB::transaction(function () use ($companyId, $userId, $payload) {
            $sale = Sale::query()
                ->where('company_id', $companyId)
                ->where('id', (int) ($payload['sale_id'] ?? 0))
                ->first();

            abort_unless($sale, 422, 'La factura indicada no existe.');
            abort_unless($sale->status === 'COMPLETED', 422, 'Solo se pueden generar notas de debito sobre facturas emitidas.');

            $reason = trim((string) ($payload['reason'] ?? ''));
            abort_if($reason === '', 422, 'Debes indicar la razon de la nota de debito.');

            $itemsInput = $payload['items'] ?? [];
            abort_if(empty($itemsInput), 422, 'La nota de debito debe tener al menos una linea.');

            $lines = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($itemsInput as $itemInput) {
                $quantity = (float) ($itemInput['quantity'] ?? 1);
                $unitPrice = (float) ($itemInput['unit_price'] ?? 0);
                $tax = round((float) ($itemInput['tax'] ?? 0), 4);
                $productId = ! empty($itemInput['product_id']) ? (int) $itemInput['product_id'] : null;
                $description = trim((string) ($itemInput['description'] ?? ''));

                abort_if($quantity <= 0, 422, 'La cantidad debe ser mayor que cero.');
                abort_if($unitPrice <= 0, 422, 'El precio unitario debe ser mayor que cero.');
                abort_if(! $productId && $description === '', 422, 'Cada linea necesita un producto o una descripcion.');

                $lineSubtotal = round($quantity * $unitPrice, 4);

                $lines[] = [
                    'product_id' => $productId,
                    'description' => $description !== '' ? $description : null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax' => $tax,
                    'subtotal' => $lineSubtotal,
                    'total' => round($lineSubtotal + $tax, 4),
                ];

                $subtotal += $lineSubtotal;
                $taxTotal += $tax;
            }

            $subtotal = round($subtotal, 4);
            $taxTotal = round($taxTotal, 4);
            $total = round($subtotal + $taxTotal, 4);

            $customer = Customer::findOrFail($sale->customer_id);
            $creditLimit = (float) $customer->credit_limit;
            $projectedBalance = round((float) $customer->current_balance + $total, 4);
            $overrideCreditLimit = (bool) ($payload['override_credit_limit'] ?? false);

            if (! $overrideCreditLimit && ($creditLimit <= 0 || $projectedBalance > $creditLimit)) {
                abort(422, 'El cliente excede su limite de credito disponible.');
            }

            $documentType = DocumentType::where('code', 'ND')->first();
            abort_unless($documentType, 500, 'No esta configurado el tipo de documento de Nota de Debito.');
            $debitNumber = $documentType->generateDocumentNumber($documentType);

            $note = SalesDebitNote::create([
                'company_id' => $companyId,
                'branch_id' => $sale->branch_id,
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'debit_number' => $debitNumber,
                'debit_date' => now()->toDateString(),
                'status' => 'PROCESSED',
                'subtotal' => $subtotal,
                'tax' => $taxTotal,
                'total' => $total,
                'reason' => $reason,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                SalesDebitNoteItem::create(array_merge($line, ['sales_debit_note_id' => $note->id]));
            }

            $journalEntry = $this->journalPosting->postDebitNote($note, $sale, $userId);
            $note->journal_entry_id = $journalEntry->id;
            $note->save();

            if ($sale->accounts_receivable_id) {
                AccountsReceivable::where('id', $sale->accounts_receivable_id)->increment('total_amount', $total);
                AccountsReceivable::where('id', $sale->accounts_receivable_id)->increment('balance', $total);
            }

            Customer::query()->where('id', $sale->customer_id)->increment('current_balance', $total);

            return $note->fresh(['items']);
        });
    }
}
