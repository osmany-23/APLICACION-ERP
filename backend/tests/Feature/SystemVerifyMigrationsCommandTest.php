<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemVerifyMigrationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_verify_migrations_command_runs_without_missing_columns(): void
    {
        $this->artisan('system:verify-migrations', ['--no-generate' => true])
            ->assertExitCode(0);
    }
}
