<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackendLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_log_in_to_the_backend(): void
    {
        $employee = User::factory()->create([
            'email' => 'employee@example.test',
            'password' => Hash::make('password123'),
            'role' => 'employee',
        ]);

        $this->post('/login', [
            'email' => $employee->email,
            'password' => 'password123',
        ])->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_admin_can_log_in_to_the_backend(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'web');
    }
}
