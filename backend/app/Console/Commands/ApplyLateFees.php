<?php

namespace App\Console\Commands;

use App\Models\AccountsReceivable;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApplyLateFees extends Command
{
    protected $signature = 'sales:apply-late-fees {--dry-run : Solo muestra que se aplicaria, sin guardar cambios}';

    protected $description = 'Aplica mora automatica a las cuentas por cobrar vencidas, segun el porcentaje y periodo (dias/semanas/meses) configurados por cliente.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();

        // PENDING/PARTIAL/OVERDUE: cualquier documento que todavia debe
        // dinero. PAID/VOID quedan fuera por el whereIn.
        $receivables = AccountsReceivable::query()
            ->whereIn('status', ['PENDING', 'PARTIAL', 'OVERDUE'])
            ->where('due_date', '<', $today->toDateString())
            ->where('balance', '>', 0)
            ->with('customer')
            ->get();

        $applied = 0;
        $skipped = 0;

        foreach ($receivables as $ar) {
            $customer = $ar->customer;

            // Solo se cobra mora si el cliente esta configurado para eso.
            // Nada de porcentaje fijo del 2%: cada empresa define el suyo
            // (o simplemente no activa la mora).
            if (! $customer || ! $customer->applies_late_fee
                || ! $customer->late_fee_percentage
                || ! $customer->late_fee_period_unit) {
                $skipped++;
                continue;
            }

            // "Conversion de mora": desde cuando se cuenta el atraso (la
            // ultima vez que se corrio el cargo, o el vencimiento si nunca
            // se ha cobrado), traducido a la unidad que eligio el cliente.
            $since = $ar->late_fee_last_run_at ?? $ar->due_date;
            $periods = $this->elapsedPeriods($since, $today, $customer->late_fee_period_unit);

            if ($periods < 1) {
                continue;
            }

            // La mora se calcula sobre el saldo principal (sin la mora ya
            // acumulada), para que sea interes simple por periodo y no se
            // dispare de forma compuesta.
            $principalBalance = round((float) $ar->balance - (float) $ar->late_fee_accrued, 4);
            if ($principalBalance <= 0) {
                continue;
            }

            $fee = round($principalBalance * ((float) $customer->late_fee_percentage / 100) * $periods, 4);
            if ($fee <= 0) {
                continue;
            }

            $this->line(sprintf(
                '  %s (cliente #%d %s): +%.4f (%d periodo(s) de %s al %.2f%%)',
                $ar->document_number ?? "AR#{$ar->id}",
                $customer->id,
                $customer->full_name,
                $fee,
                $periods,
                $customer->late_fee_period_unit,
                $customer->late_fee_percentage,
            ));

            if ($dryRun) {
                $applied++;
                continue;
            }

            DB::transaction(function () use ($ar, $customer, $fee, $today) {
                // total_amount tiene que subir junto con balance porque
                // AccountsReceivable::applyPayment() recalcula
                // balance = total_amount - paid_amount en cada abono; si
                // solo tocara balance, el proximo pago borraria la mora.
                $ar->total_amount = round((float) $ar->total_amount + $fee, 4);
                $ar->balance = round((float) $ar->total_amount - (float) $ar->paid_amount, 4);
                $ar->late_fee_accrued = round((float) $ar->late_fee_accrued + $fee, 4);
                $ar->late_fee_last_run_at = $today->toDateString();
                $ar->status = 'OVERDUE';
                $ar->save();

                Customer::query()->where('id', $customer->id)->increment('current_balance', $fee);
            });

            $applied++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Mora aplicada a {$applied} documento(s). {$skipped} omitido(s) (cliente sin mora configurada).");

        return self::SUCCESS;
    }

    /**
     * Traduce el tiempo transcurrido entre $since y $today al numero de
     * periodos vencidos completos, segun la unidad que la empresa configuro
     * para ese cliente (dias, semanas o meses) — la "conversion de mora".
     */
    private function elapsedPeriods(Carbon $since, Carbon $today, string $unit): int
    {
        return match ($unit) {
            'DAYS' => (int) $since->diffInDays($today),
            'WEEKS' => intdiv((int) $since->diffInDays($today), 7),
            'MONTHS' => (int) $since->diffInMonths($today),
            default => 0,
        };
    }
}
