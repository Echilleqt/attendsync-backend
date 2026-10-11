<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ADMIN ACCOUNT
        User::create([
            'name' => 'ATTENDSYNC Administrator',
            'email' => 'attendsync.bcs@gmail.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        // EMPLOYEE ACCOUNT
        $employeeUser = User::create([
            'name' => 'Bansud Central School Employee',
            'email' => 'employee@attendsync.test',
            'password' => 'password123',
            'role' => 'employee',
        ]);

        // EMPLOYEE PROFILE
        Employee::create([
            'user_id' => $employeeUser->id,
            'employee_id' => 'EMP-0001',
            'first_name' => 'Bansud',
            'middle_name' => null,
            'last_name' => 'Employee',
            'position' => 'Teacher',
            'department' => 'Teaching',
            'date_hired' => '2026-06-01',
            'status' => 'active',
        ]);
    }
}