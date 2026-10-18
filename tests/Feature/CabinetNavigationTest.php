<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\CurrentRoleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CabinetNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_role_switch_stays_in_the_same_section(): void
    {
        $user = User::factory()->withRoles(['manufacturer', 'distributor'])->create();
        $distributorRole = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $manufacturerRole = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();

        $this->actingAs($user)
            ->withSession([CurrentRoleService::SESSION_KEY => $manufacturerRole->id])
            ->withHeader('X-Cabinet-From', route('manufacturer.orders.index', absolute: false))
            ->postJson(route('role.switch'), ['role_id' => $distributorRole->id])
            ->assertOk()
            ->assertJsonPath('url', route('distributor.orders.index'));
    }

    public function test_role_switch_from_dashboard_stays_on_dashboard(): void
    {
        $user = User::factory()->withRoles(['manufacturer', 'end_company'])->create();
        $endCompanyRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();

        $this->actingAs($user)
            ->withHeader('X-Cabinet-From', route('dashboard', absolute: false))
            ->postJson(route('role.switch'), ['role_id' => $endCompanyRole->id])
            ->assertOk()
            ->assertJsonPath('url', route('dashboard'));
    }

    public function test_role_switch_without_equivalent_section_opens_dashboard(): void
    {
        $user = User::factory()->withRoles(['manufacturer', 'end_company'])->create();
        $endCompanyRole = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();

        $this->actingAs($user)
            ->withHeader('X-Cabinet-From', route('manufacturer.products.index', absolute: false))
            ->postJson(route('role.switch'), ['role_id' => $endCompanyRole->id])
            ->assertOk()
            ->assertJsonPath('url', route('dashboard'));
    }

    public function test_cabinet_shell_is_marked_for_partial_navigation(): void
    {
        $user = User::factory()->withRoles(['manufacturer', 'distributor'])->create();
        $manufacturerRole = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();

        $this->actingAs($user)
            ->withSession([CurrentRoleService::SESSION_KEY => $manufacturerRole->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-cabinet-sidebar', false)
            ->assertSee('data-cabinet-main', false)
            ->assertSee('data-role-switch', false);
    }
}
