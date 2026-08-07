<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class JournalEntry extends Model
{
    protected $table = 'journal_entries';

    protected $fillable = [
        'company_id',
        'branch_id',
        'entry_number',
        'entry_date',
        'description',
        'fiscal_period_id',
        'reference_table',
        'reference_id',
        'status',
        'total_debit',
        'total_credit',
        'currency_id',
        'exchange_rate',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'entry_date' => 'date',
        'fiscal_period_id' => 'integer',
        'reference_id' => 'integer',
        'total_debit' => 'decimal:4',
        'total_credit' => 'decimal:4',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'created_by' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Genera el correlativo del asiento (ej. AS-000123) reutilizando la
     * tabla number_sequences ya existente en el esquema, con bloqueo
     * pesimista para evitar numeros duplicados bajo concurrencia.
     */
    public static function generateEntryNumber(int $companyId): string
    {
        return DB::transaction(function () use ($companyId) {
            $sequence = DB::table('number_sequences')
                ->where('company_id', $companyId)
                ->where('code', 'ASIENTO')
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('number_sequences')->insert([
                    'company_id' => $companyId,
                    'code' => 'ASIENTO',
                    'description' => 'Correlativo de asientos contables',
                    'prefix' => 'AS',
                    'current_value' => 1,
                    'pad_length' => 6,
                    'reset_on' => 'NEVER',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $sequence = DB::table('number_sequences')
                    ->where('company_id', $companyId)
                    ->where('code', 'ASIENTO')
                    ->lockForUpdate()
                    ->first();
            }

            $nextValue = (int) $sequence->current_value;
            $padLength = (int) ($sequence->pad_length ?? 6);
            $prefix = (string) ($sequence->prefix ?: 'AS');

            DB::table('number_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'current_value' => $nextValue + 1,
                    'updated_at' => now(),
                ]);

            return sprintf('%s-%s', $prefix, str_pad((string) $nextValue, $padLength, '0', STR_PAD_LEFT));
        });
    }

    /**
     * Agrega una linea de debe/haber y mantiene los totales de la cabecera
     * sincronizados. No valida el cuadre; eso lo hace post().
     */
    public function addLine(
        int $accountingAccountId,
        float $debit,
        float $credit,
        ?string $description = null,
        ?int $costCenterId = null,
    ): JournalEntryLine {
        $line = $this->lines()->create([
            'accounting_account_id' => $accountingAccountId,
            'cost_center_id' => $costCenterId,
            'debit' => round($debit, 4),
            'credit' => round($credit, 4),
            'description' => $description,
            'line_order' => $this->lines()->count() + 1,
        ]);

        $this->total_debit = round((float) $this->total_debit + $debit, 4);
        $this->total_credit = round((float) $this->total_credit + $credit, 4);
        $this->save();

        return $line;
    }

    /**
     * Valida que el asiento cuadre (debe === haber) y lo marca POSTED.
     * Lanza excepcion si no cuadra, para que el llamador haga rollback.
     */
    public function post(): void
    {
        if (abs((float) $this->total_debit - (float) $this->total_credit) > 0.01) {
            throw new \RuntimeException(sprintf(
                'El asiento %s no cuadra: debe %s, haber %s.',
                $this->entry_number,
                number_format((float) $this->total_debit, 4),
                number_format((float) $this->total_credit, 4),
            ));
        }

        $this->status = 'POSTED';
        $this->posted_at = now();
        $this->save();
    }

    public function void(): void
    {
        $this->status = 'VOID';
        $this->save();
    }
}
