<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Agency;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the demo users for local development only.
     */
    public function run(): void
    {
        $roadAgency = Agency::where('name', 'Road Maintenance Department')->first();

        User::updateOrCreate(
            ['email' => 'admin@baladna.test'],
            [
                'name' => 'Baladna Admin',
                'password' => Hash::make('password'),
                'role' => Role::Admin,
                'is_active' => true,
            ]
        );

        $roadEmployee = User::updateOrCreate(
            ['email' => 'employee@baladna.test'],
            [
                'name' => 'Road Employee',
                'password' => Hash::make('password'),
                'role' => Role::Employee,
                'agency_id' => $roadAgency?->id,
                'is_active' => true,
            ]
        );

        // Reports filed under "Damaged road" go straight to this employee.
        if ($roadAgency) {
            Category::where('agency_id', $roadAgency->id)
                ->update(['responsible_employee_id' => $roadEmployee->id]);
        }

        User::updateOrCreate(
            ['email' => 'citizen@baladna.test'],
            [
                'name' => 'Baladna Citizen',
                'password' => Hash::make('password'),
                'role' => Role::Citizen,
                'is_active' => true,
            ]
        );
    }
}
