<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierOutletAccessTest extends TestCase
{
    use RefreshDatabase;

    private Role $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->owner = Role::create(['name' => 'Owner', 'code' => 'owner']);
    }

    public function test_user_without_user_outlet_rows_uses_only_home_outlet_and_does_not_loop(): void
    {
        $home = $this->outlet('Home');
        $other = $this->outlet('Other');
        $user = $this->user($home);

        $this->actingAs($user)->withSession(['auth_portal' => 'cashier'])
            ->get(route('cashier.index'))
            ->assertOk()
            ->assertSee('Home');

        $this->assertSame($home->id, (int) session('cashier_outlet_id'));

        // Home outlet fallback must not grant any other outlet.
        $this->post(route('cashier.select-outlet.store'), ['outlet_id' => $other->id])->assertSessionHasErrors();
        $this->assertSame($home->id, (int) session('cashier_outlet_id'));
    }

    public function test_user_without_any_usable_outlet_gets_clear_message_instead_of_redirect_loop(): void
    {
        $inactive = $this->outlet('Closed', false);
        $this->outlet('Open');
        $user = $this->user($inactive);

        $response = $this->actingAs($user)->withSession(['auth_portal' => 'cashier'])->get(route('cashier.index'));

        $response->assertStatus(403)->assertSee('Akun ini belum memiliki akses outlet Cashier.');
    }

    public function test_single_outlet_user_enters_that_outlet(): void
    {
        $a = $this->outlet('Alpha');
        $b = $this->outlet('Beta');
        $user = $this->user($a);
        $user->outlets()->sync([$b->id]);

        $this->actingAs($user)->withSession(['auth_portal' => 'cashier'])
            ->get(route('cashier.index'))
            ->assertOk()->assertSee('Beta');

        $this->assertSame($b->id, (int) session('cashier_outlet_id'));
    }

    public function test_multi_outlet_user_is_sent_to_selector_and_can_switch(): void
    {
        $a = $this->outlet('Alpha');
        $b = $this->outlet('Beta');
        $c = $this->outlet('Gamma');
        $user = $this->user($a);
        $user->outlets()->sync([$a->id, $b->id]);

        $this->actingAs($user)->withSession(['auth_portal' => 'cashier'])
            ->get(route('cashier.index'))
            ->assertRedirect(route('cashier.select-outlet'));

        $this->get(route('cashier.select-outlet'))->assertOk();

        $this->post(route('cashier.select-outlet.store'), ['outlet_id' => $b->id])->assertRedirect(route('cashier.index'));
        $this->get(route('cashier.index'))->assertOk()->assertSee('Beta');

        $this->post(route('cashier.select-outlet.store'), ['outlet_id' => $a->id]);
        $this->get(route('cashier.index'))->assertOk()->assertSee('Alpha');

        // Outlet outside the user's access is rejected.
        $this->post(route('cashier.select-outlet.store'), ['outlet_id' => $c->id])->assertSessionHasErrors();
        $this->assertSame($a->id, (int) session('cashier_outlet_id'));
    }

    public function test_stale_or_inactive_session_outlet_is_cleared_without_loop(): void
    {
        $a = $this->outlet('Alpha');
        $b = $this->outlet('Beta');
        $closed = $this->outlet('Closed', false);
        $user = $this->user($a);
        $user->outlets()->sync([$a->id, $b->id, $closed->id]);

        // Multi-outlet user with an inactive outlet in session -> selector, stale value dropped.
        $this->actingAs($user)->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $closed->id, 'cashier_cart' => ['x' => 1]])
            ->get(route('cashier.index'))
            ->assertRedirect(route('cashier.select-outlet'));
        $this->assertNull(session('cashier_outlet_id'));
        $this->assertNull(session('cashier_cart'));

        // Nonexistent id behaves the same.
        $this->withSession(['cashier_outlet_id' => 999999])->get(route('cashier.index'))->assertRedirect(route('cashier.select-outlet'));

        // Single-outlet user with a stale id recovers into their only outlet.
        $solo = $this->user($a, 'solo');
        $solo->outlets()->sync([$a->id]);
        $this->actingAs($solo)->withSession(['auth_portal' => 'cashier', 'cashier_outlet_id' => $closed->id])
            ->get(route('cashier.index'))->assertRedirect(route('cashier.select-outlet'));
        $this->assertNull(session('cashier_outlet_id'));
        $this->get(route('cashier.select-outlet'))->assertRedirect(route('cashier.index'));
        $this->get(route('cashier.index'))->assertOk();
        $this->assertSame($a->id, (int) session('cashier_outlet_id'));
    }

    private function outlet(string $name, bool $active = true): Outlet
    {
        return Outlet::create(['name' => $name, 'code' => strtoupper($name), 'is_active' => $active]);
    }

    private function user(Outlet $home, string $username = 'owner'): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'role_id' => $this->owner->id,
            'outlet_id' => $home->id,
            'is_active' => true,
        ]);
    }
}
