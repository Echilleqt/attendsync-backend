<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\NetworkRestrictionSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NetworkRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->create([
            'email' => 'admin@attendsync.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
    }

    protected function createEmployeeUser(): array
    {
        $user = User::factory()->create([
            'email' => 'employee@attendsync.test',
            'password' => Hash::make('password123'),
            'role' => 'employee',
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'employee_id' => 'EMP-' . $user->id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'position' => 'Teacher I',
            'department' => 'Elementary',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    public function test_admin_can_view_network_settings_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Network Restriction Settings');
        $response->assertSee('Bansud Central School Main Wi-Fi');
        $response->assertSee('Authorized Wi-Fi SSID');
    }

    public function test_employee_cannot_access_admin_network_settings(): void
    {
        [$employeeUser] = $this->createEmployeeUser();

        $response = $this->actingAs($employeeUser)->get(route('admin.settings.index'));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_update_network_settings(): void
    {
        $response = $this->post(route('admin.settings.network.update'), [
            'is_enabled' => '1',
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_employee_cannot_update_network_settings(): void
    {
        [$employeeUser] = $this->createEmployeeUser();

        $response = $this->actingAs($employeeUser)->post(route('admin.settings.network.update'), [
            'is_enabled' => '1',
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_network_restriction_settings(): void
    {
        $admin = $this->createAdmin();

        $payload = [
            'is_enabled' => '1',
            'authorized_ssid' => 'BansudCS-Faculty',
            'authorized_bssids' => "AA:BB:CC:DD:EE:01\nAA:BB:CC:DD:EE:02",
            'network_description' => 'Bansud Central School High-Speed Wi-Fi',
            'verification_method' => 'client_assisted',
        ];

        $response = $this->actingAs($admin)->post(route('admin.settings.network.update'), $payload);

        $response->assertRedirect(route('admin.settings.index'));
        $response->assertSessionHas('success');

        $setting = NetworkRestrictionSetting::current();
        $this->assertTrue($setting->is_enabled);
        $this->assertEquals('BansudCS-Faculty', $setting->authorized_ssid);
        $this->assertEquals('Bansud Central School High-Speed Wi-Fi', $setting->network_description);
        $this->assertEquals(['AA:BB:CC:DD:EE:01', 'AA:BB:CC:DD:EE:02'], $setting->authorized_bssid_list);
    }

    public function test_admin_can_disable_network_restriction(): void
    {
        $admin = $this->createAdmin();

        // Enable first
        $payload = [
            'is_enabled' => '1',
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ];
        $this->actingAs($admin)->post(route('admin.settings.network.update'), $payload);
        $this->assertTrue(NetworkRestrictionSetting::current()->is_enabled);

        // Now disable (checkbox unchecked means is_enabled not sent)
        $disablePayload = [
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ];
        $response = $this->actingAs($admin)->post(route('admin.settings.network.update'), $disablePayload);
        $response->assertRedirect(route('admin.settings.index'));

        $setting = NetworkRestrictionSetting::current();
        $this->assertFalse($setting->is_enabled);
    }

    public function test_admin_update_validation_requires_ssid_when_enabled(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.network.update'), [
            'is_enabled' => '1',
            'authorized_ssid' => '',
            'verification_method' => 'client_assisted',
        ]);

        $response->assertSessionHasErrors('authorized_ssid');
    }

    public function test_admin_update_validation_requires_trusted_ip_when_enabled_and_ip_method(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.network.update'), [
            'is_enabled' => '1',
            'verification_method' => 'trusted_ip_range',
            'trusted_ip_ranges' => '',
        ]);

        $response->assertSessionHasErrors('trusted_ip_ranges');
    }

    public function test_employee_can_fetch_network_policy_endpoint(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'network_description' => 'Bansud Central School Main Wi-Fi',
            'verification_method' => 'client_assisted',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/network-policy');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('policy.is_enabled', true)
            ->assertJsonPath('policy.authorized_ssid', 'BansudCS-Faculty')
            ->assertJsonPath('policy.network_description', 'Bansud Central School Main Wi-Fi')
            ->assertJsonPath('policy.verification_method', 'client_assisted');
    }

    public function test_when_restriction_is_disabled_employee_can_access_protected_routes(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/attendance/today');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_when_restriction_is_enabled_unauthorized_network_is_rejected_with_403(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        // Attempting with wrong SSID
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'wifi',
            'X-Network-SSID' => 'Home-WiFi-Guest',
        ])->getJson('/api/attendance/today');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'NETWORK_NOT_AUTHORIZED');
    }

    public function test_when_restriction_is_enabled_cellular_data_is_rejected(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'cellular',
        ])->getJson('/api/attendance/today');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'NETWORK_NOT_AUTHORIZED');
    }

    public function test_when_restriction_is_enabled_matching_ssid_allows_access(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'wifi',
            'X-Network-SSID' => 'BansudCS-Faculty',
        ])->getJson('/api/attendance/today');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_bssid_validation_blocks_unauthorized_access_point(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'authorized_bssids' => '00:11:22:33:44:55',
            'verification_method' => 'client_assisted',
        ]);

        // Wrong BSSID
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'wifi',
            'X-Network-SSID' => 'BansudCS-Faculty',
            'X-Network-BSSID' => '99:88:77:66:55:44',
        ])->getJson('/api/attendance/today');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'NETWORK_NOT_AUTHORIZED');

        // Correct BSSID
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'wifi',
            'X-Network-SSID' => 'BansudCS-Faculty',
            'X-Network-BSSID' => '00:11:22:33:44:55',
        ])->getJson('/api/attendance/today');

        $response->assertOk();
    }

    public function test_server_trusted_ip_range_verification(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'verification_method' => 'trusted_ip_range',
            'trusted_ip_ranges' => '192.168.1.0/24',
        ]);

        // Request from unauthorized IP
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.195',
        ])->getJson('/api/attendance/today');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'NETWORK_NOT_AUTHORIZED');

        // Request from authorized subnet
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.42',
        ])->getJson('/api/attendance/today');

        $response->assertOk();
    }

    public function test_network_verify_endpoint_returns_evaluation(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'wifi',
            'X-Network-SSID' => 'BansudCS-Faculty',
        ])->postJson('/api/network-verify');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('authorized', true);
    }

    public function test_school_vpn_or_gateway_verification(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'verification_method' => 'school_vpn_or_gateway',
            'trusted_ip_ranges' => "10.8.0.0/24\n120.28.10.5",
        ]);

        // Request from unauthorized IP
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.50',
        ])->getJson('/api/attendance/today');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'NETWORK_NOT_AUTHORIZED');

        // Request from authorized VPN subnet
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->withServerVariables([
            'REMOTE_ADDR' => '10.8.0.15',
        ])->getJson('/api/attendance/today');

        $response->assertOk();
    }

    public function test_school_vpn_or_gateway_fails_closed_when_no_ranges_configured(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'verification_method' => 'school_vpn_or_gateway',
            'trusted_ip_ranges' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/attendance/today');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'NETWORK_VERIFICATION_UNAVAILABLE');
    }

    public function test_logout_endpoint_remains_accessible_when_network_restricted(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        // Calling logout from cellular network
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'cellular',
        ])->postJson('/api/logout');

        $response->assertOk();
    }

    public function test_profile_me_endpoint_is_blocked_on_unauthorized_network(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'wifi',
            'X-Network-SSID' => 'Mall-Free-WiFi',
        ])->getJson('/api/me');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'NETWORK_NOT_AUTHORIZED');
    }

    public function test_attempt_to_time_in_on_unauthorized_network_is_rejected_with_403(): void
    {
        [$employeeUser] = $this->createEmployeeUser();
        $token = auth('api')->login($employeeUser);

        NetworkRestrictionSetting::current()->update([
            'is_enabled' => true,
            'authorized_ssid' => 'BansudCS-Faculty',
            'verification_method' => 'client_assisted',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Network-Type' => 'cellular',
        ])->postJson('/api/attendance/time-in', [
            'verification_method' => 'fingerprint',
            'biometric_verification_token' => str_repeat('a', 64),
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'NETWORK_NOT_AUTHORIZED');
    }
}
