<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Models\DistributorProfile;
use App\Models\ManufacturerDiscountSet;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerEmployeeRole;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CurrentRoleService;
use App\Services\Discount\DiscountEngine;
use App\Services\ManufacturerEmployeeService;
use App\Support\CompanyStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManufacturerDiscountSetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_open_discount_sets_and_create_program(): void
    {
        [$admin] = $this->makeManufacturerAdmin();

        $this->actingAsManufacturer($admin)
            ->get(route('manufacturer.discounts.index'))
            ->assertOk()
            ->assertSee('Наборы скидок')
            ->assertSee('Создать');

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.discounts.store'), $this->validPayload([
                'name' => 'Оптовая 10%',
                'status' => 'active',
                'discount_percent' => 10,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('manufacturer_discount_sets', [
            'name' => 'Оптовая 10%',
            'type' => ManufacturerDiscountSet::TYPE_PERCENT,
            'status' => ManufacturerDiscountSet::STATUS_ACTIVE,
        ]);
    }

    public function test_volume_tiers_and_preview_explain_threshold(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $profile = $admin->manufacturerProfile;
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'base_price' => 15000,
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.discounts.store'), $this->validPayload([
                'name' => 'Градация объёма',
                'type' => ManufacturerDiscountSet::TYPE_VOLUME_TIER,
                'status' => 'active',
                'tiers' => [
                    ['min_amount' => 10000, 'min_quantity' => '', 'percent' => 10, 'amount' => ''],
                ],
            ]))
            ->assertRedirect();

        $set = ManufacturerDiscountSet::query()->firstOrFail();
        $distributor = $this->makeDistributor();

        $this->actingAsManufacturer($admin)
            ->postJson(route('manufacturer.discounts.preview', $set), [
                'distributor_id' => $distributor->id,
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('applies', true)
            ->assertJsonFragment(['unit_price' => 13500]);
    }

    public function test_percent_discount_applies_in_purchase_cart(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $profile = $admin->manufacturerProfile;
        $product = Product::factory()->active()->create([
            'manufacturer_profile_id' => $profile->id,
            'base_price' => 1000,
            'min_order_quantity' => 1,
        ]);
        $distributor = $this->makeDistributor();
        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $profile->id,
            'distributor_profile_id' => $distributor->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        ManufacturerDiscountSet::query()->create([
            'manufacturer_profile_id' => $profile->id,
            'name' => 'Скидка 20%',
            'type' => ManufacturerDiscountSet::TYPE_PERCENT,
            'status' => ManufacturerDiscountSet::STATUS_ACTIVE,
            'discount_percent' => 20,
            'apply_to_all_products' => true,
            'apply_only_active_products' => true,
            'audience_type' => ManufacturerDiscountSet::AUDIENCE_ALL,
            'is_indefinite' => true,
        ]);

        $quoted = app(DiscountEngine::class)->quote(
            (int) $profile->id,
            $distributor,
            [[
                'product_id' => (int) $product->id,
                'category_id' => $product->category_id ? (int) $product->category_id : null,
                'quantity' => 2,
                'list_unit_price' => 1000,
                'status' => $product->status,
            ]],
        );

        $this->assertSame(800.0, $quoted['items'][0]['unit_price']);
        $this->assertSame(400.0, $quoted['items'][0]['discount_amount']);
        $this->assertSame('Скидка 20%', $quoted['items'][0]['discount_label']);
    }

    public function test_toggle_copy_and_unique_promo_code(): void
    {
        [$admin] = $this->makeManufacturerAdmin();

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.discounts.store'), $this->validPayload(['name' => 'Базовый']))
            ->assertRedirect();

        $set = ManufacturerDiscountSet::query()->firstOrFail();
        $this->assertSame(ManufacturerDiscountSet::STATUS_INACTIVE, $set->status);

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.discounts.toggle', $set))
            ->assertRedirect();
        $this->assertSame(ManufacturerDiscountSet::STATUS_ACTIVE, $set->fresh()->status);

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.discounts.copy', $set))
            ->assertRedirect();
        $this->assertDatabaseHas('manufacturer_discount_sets', ['name' => 'Базовый (копия)']);

        $this->actingAsManufacturer($admin)
            ->get(route('manufacturer.promo-codes.index'))
            ->assertOk()
            ->assertSee('Промокоды');

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.promo-codes.store'), [
                'code' => 'SPRING10',
                'name' => 'Весна',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'status' => 'active',
                'apply_to_all_products' => 1,
            ])
            ->assertRedirect();

        $this->actingAsManufacturer($admin)
            ->from(route('manufacturer.promo-codes.create'))
            ->post(route('manufacturer.promo-codes.store'), [
                'code' => 'spring10',
                'discount_type' => 'percent',
                'discount_value' => 5,
                'status' => 'inactive',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_preview_uses_unsaved_audience_from_form(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $profile = $admin->manufacturerProfile;
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'base_price' => 1000,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $distributor = $this->makeDistributorPartner($admin, 'preview-aud@example.com', 'Козлов');

        $set = ManufacturerDiscountSet::query()->create([
            'manufacturer_profile_id' => $profile->id,
            'name' => 'Эксклюзив',
            'type' => ManufacturerDiscountSet::TYPE_PERCENT,
            'status' => ManufacturerDiscountSet::STATUS_ACTIVE,
            'discount_percent' => 10,
            'apply_to_all_products' => true,
            'apply_only_active_products' => true,
            'audience_type' => ManufacturerDiscountSet::AUDIENCE_EXCLUSIVE,
            'is_indefinite' => true,
        ]);

        $this->actingAsManufacturer($admin)
            ->postJson(route('manufacturer.discounts.preview', $set), [
                'distributor_id' => $distributor->id,
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('applies', false)
            ->assertJsonFragment(['explanation' => 'Набор не применяется к выбранному дистрибьютору. Дистрибьютор не является эксклюзивным в этом регионе.']);

        $this->actingAsManufacturer($admin)
            ->postJson(route('manufacturer.discounts.preview', $set), [
                'distributor_id' => $distributor->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'audience_type' => ManufacturerDiscountSet::AUDIENCE_ALL,
                'type' => ManufacturerDiscountSet::TYPE_PERCENT,
                'discount_percent' => 10,
                'apply_to_all_products' => 1,
                'apply_only_active_products' => 1,
                'name' => 'Эксклюзив',
            ])
            ->assertOk()
            ->assertJsonPath('applies', true)
            ->assertJsonPath('unit_price', 900);
    }

    public function test_preview_apply_to_all_covers_product_even_with_stale_product_ids(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $profile = $admin->manufacturerProfile;
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'base_price' => 1000,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $other = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'base_price' => 2000,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $distributor = $this->makeDistributorPartner($admin, 'preview-all@example.com', 'ВсеТовары');

        $set = ManufacturerDiscountSet::query()->create([
            'manufacturer_profile_id' => $profile->id,
            'name' => 'На все',
            'type' => ManufacturerDiscountSet::TYPE_PERCENT,
            'status' => ManufacturerDiscountSet::STATUS_ACTIVE,
            'discount_percent' => 10,
            'apply_to_all_products' => false,
            'apply_only_active_products' => true,
            'audience_type' => ManufacturerDiscountSet::AUDIENCE_ALL,
            'product_ids' => [$other->id],
            'is_indefinite' => true,
        ]);

        $this->actingAsManufacturer($admin)
            ->postJson(route('manufacturer.discounts.preview', $set), [
                'distributor_id' => $distributor->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'audience_type' => ManufacturerDiscountSet::AUDIENCE_ALL,
                'type' => ManufacturerDiscountSet::TYPE_PERCENT,
                'discount_percent' => 10,
                'apply_to_all_products' => '1',
                'apply_only_active_products' => '1',
                'product_ids' => [$other->id],
                'name' => 'На все',
            ])
            ->assertOk()
            ->assertJsonPath('applies', true)
            ->assertJsonPath('unit_price', 900);
    }

    public function test_preview_cleared_regions_override_saved_region_ids(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $profile = $admin->manufacturerProfile;
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'base_price' => 1000,
            'status' => Product::STATUS_ACTIVE,
        ]);
        $distributor = $this->makeDistributorPartner($admin, 'preview-region@example.com', 'РегионТест');

        $set = ManufacturerDiscountSet::query()->create([
            'manufacturer_profile_id' => $profile->id,
            'name' => 'Только Алтай',
            'type' => ManufacturerDiscountSet::TYPE_PERCENT,
            'status' => ManufacturerDiscountSet::STATUS_ACTIVE,
            'discount_percent' => 10,
            'apply_to_all_products' => true,
            'apply_only_active_products' => true,
            'audience_type' => ManufacturerDiscountSet::AUDIENCE_ALL,
            'region_ids' => [999],
            'is_indefinite' => true,
        ]);

        $this->actingAsManufacturer($admin)
            ->postJson(route('manufacturer.discounts.preview', $set), [
                'distributor_id' => $distributor->id,
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('applies', false)
            ->assertJsonFragment(['explanation' => 'Набор не покрывает выбранный товар. Регион не входит в область действия скидки.']);

        $this->actingAsManufacturer($admin)
            ->postJson(route('manufacturer.discounts.preview', $set), [
                'distributor_id' => $distributor->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'audience_type' => ManufacturerDiscountSet::AUDIENCE_ALL,
                'type' => ManufacturerDiscountSet::TYPE_PERCENT,
                'discount_percent' => 10,
                'apply_to_all_products' => 1,
                'apply_only_active_products' => 1,
                'name' => 'Только Алтай',
                // region_ids отсутствуют — как при «Все регионы» на форме
            ])
            ->assertOk()
            ->assertJsonPath('applies', true)
            ->assertJsonPath('unit_price', 900);
    }

    public function test_individual_discount_requires_exactly_one_distributor(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $first = $this->makeDistributorPartner($admin, 'first-disc@example.com', 'Первый');
        $second = $this->makeDistributorPartner($admin, 'second-disc@example.com', 'Второй');

        $this->actingAsManufacturer($admin)
            ->from(route('manufacturer.discounts.create'))
            ->post(route('manufacturer.discounts.store'), $this->validPayload([
                'name' => 'Индивидуальная',
                'type' => ManufacturerDiscountSet::TYPE_INDIVIDUAL,
                'audience_type' => ManufacturerDiscountSet::AUDIENCE_ALL,
                'distributor_ids' => [],
            ]))
            ->assertRedirect(route('manufacturer.discounts.create'))
            ->assertSessionHasErrors('distributor_ids');

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.discounts.store'), $this->validPayload([
                'name' => 'Индивидуальная',
                'type' => ManufacturerDiscountSet::TYPE_INDIVIDUAL,
                'audience_type' => ManufacturerDiscountSet::AUDIENCE_SELECTED,
                'distributor_ids' => [$first->id, $second->id],
            ]))
            ->assertRedirect();

        $set = ManufacturerDiscountSet::query()->where('name', 'Индивидуальная')->firstOrFail();
        $this->assertSame(ManufacturerDiscountSet::AUDIENCE_SELECTED, $set->audience_type);
        $this->assertSame([(int) $first->id], $set->intList($set->distributor_ids));
    }

    public function test_volume_tiers_can_store_multiple_steps_and_history_tracks_percent(): void
    {
        [$admin] = $this->makeManufacturerAdmin();

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.discounts.store'), $this->validPayload([
                'name' => 'Многоступенчатая',
                'type' => ManufacturerDiscountSet::TYPE_VOLUME_TIER,
                'discount_percent' => null,
                'tiers' => [
                    ['min_amount' => 10000, 'min_quantity' => '', 'percent' => 3, 'amount' => ''],
                    ['min_amount' => 50000, 'min_quantity' => '', 'percent' => 5, 'amount' => ''],
                    ['min_amount' => 100000, 'min_quantity' => '', 'percent' => 10, 'amount' => ''],
                ],
            ]))
            ->assertRedirect();

        $set = ManufacturerDiscountSet::query()->where('name', 'Многоступенчатая')->firstOrFail();
        $this->assertCount(3, $set->normalizedTiers());

        $this->actingAsManufacturer($admin)
            ->put(route('manufacturer.discounts.update', $set), $this->validPayload([
                'name' => 'Многоступенчатая',
                'type' => ManufacturerDiscountSet::TYPE_PERCENT,
                'discount_percent' => 12,
                'apply_to_all_products' => 1,
            ]))
            ->assertRedirect();

        $set->refresh()->load('logs');
        $updateLog = $set->logs->firstWhere('action', 'updated');
        $this->assertNotNull($updateLog);
        $this->assertArrayHasKey('discount_percent', $updateLog->changes);
        $this->assertArrayHasKey('type', $updateLog->changes);
    }

    public function test_employee_without_discounts_cannot_open_section(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $employee = $this->makeActiveEmployee($admin, roleName: 'Оператор');

        $this->actingAsEmployee($employee)
            ->get(route('manufacturer.discounts.index'))
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_and_manager_cannot_delete(): void
    {
        [$admin] = $this->makeManufacturerAdmin();
        $viewer = $this->makeActiveEmployee($admin, 'viewer-disc@example.com', 'Secret123', 'Бухгалтер');
        $manager = $this->makeActiveEmployee($admin, 'manager-disc@example.com', 'Secret123', 'Менеджер продаж');

        $this->actingAsEmployee($viewer)
            ->get(route('manufacturer.discounts.index'))
            ->assertOk();

        $this->actingAsEmployee($viewer)
            ->get(route('manufacturer.discounts.create'))
            ->assertForbidden();

        $this->actingAsEmployee($manager)
            ->post(route('manufacturer.discounts.store'), $this->validPayload(['name' => 'Менеджерский']))
            ->assertRedirect();

        $set = ManufacturerDiscountSet::query()->where('name', 'Менеджерский')->firstOrFail();

        $this->actingAsEmployee($manager)
            ->delete(route('manufacturer.discounts.destroy', $set))
            ->assertForbidden();

        $this->actingAsManufacturer($admin)
            ->delete(route('manufacturer.discounts.destroy', $set))
            ->assertRedirect(route('manufacturer.discounts.index'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Тестовый набор',
            'type' => ManufacturerDiscountSet::TYPE_PERCENT,
            'status' => ManufacturerDiscountSet::STATUS_INACTIVE,
            'discount_percent' => 5,
            'apply_to_all_products' => 1,
            'apply_only_active_products' => 1,
            'audience_type' => ManufacturerDiscountSet::AUDIENCE_ALL,
            'is_indefinite' => 1,
            'require_all_conditions' => 1,
        ], $overrides);
    }

    /**
     * @return array{0: User}
     */
    private function makeManufacturerAdmin(): array
    {
        $role = Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
        $admin = User::factory()->create([
            'name' => 'Админ Завода',
            'email' => 'mfr-disc-admin@example.com',
        ]);
        $admin->roles()->attach($role->id, [
            'company_name' => 'Завод Скидок',
            'company_type' => 'manufacturer',
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $profile = $admin->getOrCreateManufacturerProfile();
        $profile->update(['full_name' => 'Завод Скидок', 'short_name' => 'ЗСк']);
        Warehouse::factory()->create([
            'manufacturer_profile_id' => $profile->id,
            'name' => 'Склад скидок',
        ]);
        app(ManufacturerEmployeeService::class)->ensureDefaultRoles($profile);

        return [$admin->fresh(['roles', 'manufacturerProfile'])];
    }

    private function makeDistributor(): DistributorProfile
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $user->roles()->attach($role->id);
        $profile = $user->getOrCreateDistributorProfile();
        $profile->update(['full_name' => 'Дистрибьютор Скидок', 'short_name' => 'ДС']);

        return $profile;
    }

    private function makeDistributorPartner(User $admin, string $email, string $name): DistributorProfile
    {
        $user = User::factory()->create(['email' => $email, 'name' => $name]);
        $role = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $user->roles()->attach($role->id);
        $profile = $user->getOrCreateDistributorProfile();
        $profile->update(['full_name' => $name, 'short_name' => $name]);

        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $admin->manufacturerProfile->id,
            'distributor_profile_id' => $profile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);

        return $profile;
    }

    private function makeActiveEmployee(
        User $admin,
        string $email = 'employee-disc@example.com',
        string $password = 'Secret123',
        string $roleName = 'Оператор',
    ): User {
        Mail::fake();
        $role = ManufacturerEmployeeRole::query()->where('name', $roleName)->firstOrFail();

        $this->actingAsManufacturer($admin)
            ->post(route('manufacturer.employees.store'), [
                'name' => 'Сотрудник Скидок',
                'email' => $email,
                'employee_role_id' => $role->id,
            ]);

        $plainToken = null;
        Mail::assertSent(EmployeeInvitationMail::class, function ($mail) use ($email, &$plainToken) {
            if (! $mail->hasTo($email)) {
                return false;
            }
            $plainToken = $mail->plainToken;

            return true;
        });

        auth()->logout();
        $this->post(route('employee-invitation.accept', $plainToken), [
            'password' => $password,
            'password_confirmation' => $password,
        ]);
        auth()->logout();

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function actingAsManufacturer(User $user): self
    {
        $role = $user->roles->firstWhere('slug', Role::SLUG_MANUFACTURER) ?? Role::query()->where('slug', Role::SLUG_MANUFACTURER)->firstOrFail();
        app(CurrentRoleService::class)->set($user, $role->id);

        return $this->actingAs($user)->withSession(['current_role_id' => $role->id]);
    }

    private function actingAsEmployee(User $user): self
    {
        $user->load('roles');
        $role = $user->roles->firstWhere('slug', Role::SLUG_COMPANY_EMPLOYEE);
        app(CurrentRoleService::class)->set($user, $role->id);

        return $this->actingAs($user)->withSession(['current_role_id' => $role->id]);
    }
}
