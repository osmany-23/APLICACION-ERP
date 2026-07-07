<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $password = Hash::make('password');

        foreach ($this->users() as $user) {
            DB::table('users')->updateOrInsert(
                ['company_id' => 1, 'username' => $user['username']],
                [
                    'branch_id' => 1,
                    'uuid' => $user['uuid'],
                    'password_hash' => $password,
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'phone' => null,
                    'role_id' => $user['role_id'],
                    'status' => 1,
                    'last_login' => null,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function users(): array
    {
        return [
            [
                'uuid' => '33333333-3333-3333-3333-333333333333',
                'username' => 'admin',
                'full_name' => 'Administrador del Sistema',
                'email' => 'admin@sigma.com',
                'role_id' => 1,
            ],
            [
                'uuid' => '44444444-4444-4444-4444-444444444444',
                'username' => 'supervisor',
                'full_name' => 'Supervisor General',
                'email' => 'supervisor@sigma.com',
                'role_id' => 2,
            ],
            [
                'uuid' => '55555555-5555-5555-5555-555555555555',
                'username' => 'vendedor',
                'full_name' => 'Usuario Vendedor',
                'email' => 'vendedor@sigma.com',
                'role_id' => 3,
            ],
            [
                'uuid' => '66666666-6666-6666-6666-666666666666',
                'username' => 'bodega',
                'full_name' => 'Usuario Bodega',
                'email' => 'bodega@sigma.com',
                'role_id' => 4,
            ],
            [
                'uuid' => '77777777-7777-7777-7777-777777777777',
                'username' => 'contabilidad',
                'full_name' => 'Usuario Contabilidad',
                'email' => 'contabilidad@sigma.com',
                'role_id' => 5,
            ],
        ];
    }
}
