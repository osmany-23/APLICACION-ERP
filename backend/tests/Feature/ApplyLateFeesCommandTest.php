<?php

namespace Tests\Feature;

use App\Models\AccountsReceivable;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplyLateFeesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(array $overrides = []): Customer
    {
        $currencyId = DB::table('currencies')->insertGetId([
            'code' => 'NIO', 'name' => 'Cordoba', 'symbol' => 'C$', 'decimal_places' => 2,
            'is_base' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $companyId = DB::table('companies')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Empresa Test', 'currency_id' => $currencyId,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $customer = Customer::create(array_merge([
            'company_id' => $companyId,
            'full_name' => 'Cliente Moroso',
            'currency_id' => $currencyId,
            'status' => 1,
        ], $overrides));

        // current_balance no es mass-assignable a proposito (se mantiene
        // solo desde SalesService/AccountsReceivable::applyPayment), asi
        // que aqui se fuerza para simular una venta ya posteada.
        $customer->forceFill(['current_balance' => 1000])->save();

        return $customer;
    }

    private function makeReceivable(Customer $customer, array $overrides = []): AccountsReceivable
    {
        return AccountsReceivable::create(array_merge([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'doc_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(30)->toDateString(),
            'currency_id' => $customer->currency_id,
            'exchange_rate' => 1,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'balance' => 1000,
            'status' => 'PENDING',
        ], $overrides));
    }

    public function test_applies_late_fee_to_overdue_receivable_with_configured_customer(): void
    {
        $customer = $this->makeCustomer([
            'applies_late_fee' => true,
            'late_fee_percentage' => 2,
            'late_fee_period_unit' => 'MONTHS',
        ]);
        // 2 meses calendario exactos vencidos, para que diffInMonths() de
        // Carbon sea determinista sin importar el dia del mes en que corra
        // el test (30 dias "a secas" no siempre son un mes calendario).
        $ar = $this->makeReceivable($customer, [
            'doc_date' => now()->subMonths(2)->subDays(10)->toDateString(),
            'due_date' => now()->subMonths(2)->toDateString(),
        ]);

        $this->artisan('sales:apply-late-fees')->assertExitCode(0);

        $ar->refresh();
        $customer->refresh();

        // 2 meses completos vencidos -> 2% de 1000 por mes = 40
        $this->assertEquals(40.0, (float) $ar->late_fee_accrued);
        $this->assertEquals(1040.0, (float) $ar->balance);
        $this->assertEquals(1040.0, (float) $ar->total_amount);
        $this->assertEquals('OVERDUE', $ar->status);
        $this->assertEquals(1040.0, (float) $customer->current_balance);
        $this->assertNotNull($ar->late_fee_last_run_at);
    }

    public function test_skips_customers_without_late_fee_configured(): void
    {
        $customer = $this->makeCustomer(); // applies_late_fee = false por defecto
        $ar = $this->makeReceivable($customer);

        $this->artisan('sales:apply-late-fees')->assertExitCode(0);

        $ar->refresh();
        $this->assertEquals(0.0, (float) $ar->late_fee_accrued);
        $this->assertEquals(1000.0, (float) $ar->balance);
        $this->assertEquals('PENDING', $ar->status);
    }

    public function test_does_not_double_charge_within_the_same_period(): void
    {
        $customer = $this->makeCustomer([
            'applies_late_fee' => true,
            'late_fee_percentage' => 2,
            'late_fee_period_unit' => 'MONTHS',
        ]);
        $ar = $this->makeReceivable($customer);

        $this->artisan('sales:apply-late-fees')->assertExitCode(0);
        $ar->refresh();
        $balanceAfterFirstRun = (float) $ar->balance;

        // Correrlo de nuevo el mismo dia no deberia cobrar mora otra vez:
        // late_fee_last_run_at ya quedo en hoy, cero periodos nuevos.
        $this->artisan('sales:apply-late-fees')->assertExitCode(0);
        $ar->refresh();

        $this->assertEquals($balanceAfterFirstRun, (float) $ar->balance);
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $customer = $this->makeCustomer([
            'applies_late_fee' => true,
            'late_fee_percentage' => 2,
            'late_fee_period_unit' => 'DAYS',
        ]);
        $ar = $this->makeReceivable($customer);

        $this->artisan('sales:apply-late-fees --dry-run')->assertExitCode(0);

        $ar->refresh();
        $this->assertEquals(1000.0, (float) $ar->balance);
        $this->assertNull($ar->late_fee_last_run_at);
    }
}
