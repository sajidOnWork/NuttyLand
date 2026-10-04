<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsNuttyLand;
use Tests\TestCase;

/** TC-04: each role can access only its authorised functions. */
class RoleAccessTest extends TestCase
{
    use BuildsNuttyLand, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildNuttyLand();
    }

    public function test_tc04_guest_is_sent_to_login(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
        $this->get('/staff')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect();
        $this->get('/')->assertOk();
    }

    public function test_tc04_customer_cannot_use_staff_or_admin(): void
    {
        $this->actingAs($this->user('customer'));
        $this->get('/account')->assertOk();
        $this->get('/staff')->assertForbidden();
        $this->postJson(route('staff.api.sales'), ['sales' => []])->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/admin-reports/export/products')->assertForbidden();
    }

    public function test_tc04_staff_use_staff_screens_but_not_admin(): void
    {
        $this->actingAs($this->user('staff'));
        foreach (['/staff', '/staff/sell', '/staff/orders', '/staff/stock', '/staff/sales'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin')->assertForbidden();
    }

    public function test_tc04_marketing_can_view_reports_but_not_manage_users_or_stock(): void
    {
        $this->actingAs($this->user('marketing'));
        $this->get('/admin')->assertOk();
        $this->get('/admin/reports')->assertOk();
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/inventories')->assertForbidden();
        $this->get('/admin/audit-logs')->assertForbidden();
        $this->get('/staff')->assertForbidden();
    }

    public function test_tc04_owner_can_access_everything(): void
    {
        $this->actingAs($this->user('owner'));
        foreach (['/admin', '/admin/products', '/admin/orders', '/admin/inventories', '/admin/stock-movements', '/admin/locations', '/admin/customers', '/admin/users', '/admin/audit-logs', '/admin/reports', '/staff', '/staff/sell'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin-reports/export/products')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_public_registration_always_creates_a_customer(): void
    {
        $this->post('/register', [
            'first_name' => 'New', 'last_name' => 'Person', 'email' => 'new@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'owner',
        ])->assertRedirect();

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertNotNull($user->customer);
    }

    public function test_deactivated_staff_cannot_log_in(): void
    {
        $staff = $this->user('staff');
        $staff->update(['is_active' => false]);

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
