<?php

namespace Tests\Feature;

use App\Mail\EmployeeInvitationMail;
use App\Models\DistributorDiscountSet;
use App\Models\DistributorEmployeeRole;
use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\DistributorWarehouse;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\CurrentRoleService;
use App\Services\DistributorEmployeeService;
use App\Support\CompanyStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DistributorDiscountSetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_open_discount_sets_and_create_program(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $offer = $this->makeOffer($admin);

        $this->actingAsDistributor($admin)
            ->get(route('distributor.discounts.index'))
            ->assertOk()
            ->assertSee('Наборы скидок')
            ->assertSee('Создать набор скидок');

        $this->actingAsDistributor($admin)
            ->post(route('distributor.discounts.store'), $this->validPayload([
                'name' => 'Скидка 10% на кабели',
                'type' => DistributorDiscountSet::TYPE_PRODUCTS,
                'product_ids' => [$offer->id],
                'activate' => '1',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('distributor_discount_sets', [
            'name' => 'Скидка 10% на кабели',
            'type' => DistributorDiscountSet::TYPE_PRODUCTS,
            'status' => DistributorDiscountSet::STATUS_ACTIVE,
            'source' => DistributorDiscountSet::SOURCE_MANUAL,
        ]);
    }

    public function test_employee_without_discount_rights_cannot_open_section(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $employee = $this->makeActiveEmployee($admin);

        $this->actingAsEmployee($employee)
            ->get(route('distributor.discounts.index'))
            ->assertForbidden();
    }

    public function test_employee_with_discount_rights_can_open_section(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $employee = $this->makeDiscountEmployee($admin);

        $this->actingAsEmployee($employee)
            ->get(route('distributor.discounts.index'))
            ->assertOk()
            ->assertSee('Наборы скидок');
    }

    public function test_percent_discount_applies_in_buyer_cart(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $offer = $this->makeOffer($admin, ['retail_price' => 1000, 'brand' => 'Varta']);
        $buyer = $this->makeBuyer($admin);

        DistributorDiscountSet::query()->create([
            'distributor_profile_id' => $admin->distributorProfile->id,
            'name' => 'Скидка 20%',
            'type' => DistributorDiscountSet::TYPE_PRODUCTS,
            'value_kind' => DistributorDiscountSet::VALUE_PERCENT,
            'status' => DistributorDiscountSet::STATUS_ACTIVE,
            'discount_percent' => 20,
            'product_ids' => [$offer->id],
            'audience_type' => DistributorDiscountSet::AUDIENCE_ALL,
            'is_indefinite' => true,
            'source' => DistributorDiscountSet::SOURCE_MANUAL,
        ]);

        app(CurrentRoleService::class)->set($buyer, $buyer->roles->first()->id);
        $cart = app(CartService::class);
        $cart->add($buyer, $offer->id, 2);
        $view = $cart->view($buyer);
        $item = $view['groups']->first()['items'][0];

        $this->assertSame(800.0, $item['unit_price']);
        $this->assertSame(400.0, $item['discount_amount']);
        $this->assertSame('Скидка 20%', $item['discount_label']);
        $this->assertSame(1600.0, $view['totals']['total_amount']);
    }

    public function test_personal_discount_does_not_apply_to_other_company(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $offer = $this->makeOffer($admin, ['retail_price' => 1000]);
        $buyer = $this->makeBuyer($admin);
        $other = $this->makeBuyer($admin, 'other-buyer@example.com');

        DistributorDiscountSet::query()->create([
            'distributor_profile_id' => $admin->distributorProfile->id,
            'name' => 'Персональная 50%',
            'type' => DistributorDiscountSet::TYPE_PERSONAL,
            'value_kind' => DistributorDiscountSet::VALUE_PERCENT,
            'status' => DistributorDiscountSet::STATUS_ACTIVE,
            'discount_percent' => 50,
            'apply_to_all_products' => true,
            'audience_type' => DistributorDiscountSet::AUDIENCE_SELECTED,
            'company_ids' => [$buyer->getOrCreateEndCompanyProfile()->id],
            'is_indefinite' => true,
            'source' => DistributorDiscountSet::SOURCE_MANUAL,
        ]);

        $cart = app(CartService::class);

        app(CurrentRoleService::class)->set($buyer, $buyer->roles->first()->id);
        $cart->add($buyer, $offer->id, 1);
        $this->assertSame(500.0, $cart->view($buyer)['groups']->first()['items'][0]['unit_price']);

        app(CurrentRoleService::class)->set($other, $other->roles->first()->id);
        $cart->add($other, $offer->id, 1);
        $this->assertSame(1000.0, $cart->view($other)['groups']->first()['items'][0]['unit_price']);
    }

    public function test_fixed_discount_does_not_make_price_negative(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $offer = $this->makeOffer($admin, ['retail_price' => 200]);

        DistributorDiscountSet::query()->create([
            'distributor_profile_id' => $admin->distributorProfile->id,
            'name' => 'Минус 300',
            'type' => DistributorDiscountSet::TYPE_PRODUCTS,
            'value_kind' => DistributorDiscountSet::VALUE_FIXED,
            'status' => DistributorDiscountSet::STATUS_ACTIVE,
            'discount_amount' => 300,
            'product_ids' => [$offer->id],
            'audience_type' => DistributorDiscountSet::AUDIENCE_ALL,
            'is_indefinite' => true,
            'source' => DistributorDiscountSet::SOURCE_MANUAL,
        ]);

        $buyer = $this->makeBuyer($admin);
        app(CurrentRoleService::class)->set($buyer, $buyer->roles->first()->id);
        $cart = app(CartService::class);
        $cart->add($buyer, $offer->id, 1);
        $item = $cart->view($buyer)['groups']->first()['items'][0];

        $this->assertSame(0.0, $item['unit_price']);
        $this->assertSame(200.0, $item['discount_amount']);
    }

    public function test_toggle_and_copy_discount_set(): void
    {
        [$admin] = $this->makeDistributorAdmin();
        $offer = $this->makeOffer($admin);

        $this->actingAsDistributor($admin)
            ->post(route('distributor.discounts.store'), $this->validPayload([
                'name' => 'Базовый',
                'product_ids' => [$offer->id],
            ]))
            ->assertRedirect();

        $set = DistributorDiscountSet::query()->firstOrFail();
        $this->assertSame(DistributorDiscountSet::STATUS_INACTIVE, $set->status);

        $this->actingAsDistributor($admin)
            ->post(route('distributor.discounts.toggle', $set))
            ->assertRedirect();
        $this->assertSame(DistributorDiscountSet::STATUS_ACTIVE, $set->fresh()->status);

        $this->actingAsDistributor($admin)
            ->post(route('distributor.discounts.copy', $set))
            ->assertRedirect();
        $this->assertSame(2, DistributorDiscountSet::query()->count());
        $this->assertSame(DistributorDiscountSet::STATUS_INACTIVE, DistributorDiscountSet::query()->orderByDesc('id')->first()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Тестовый набор',
            'type' => DistributorDiscountSet::TYPE_PRODUCTS,
            'value_kind' => DistributorDiscountSet::VALUE_PERCENT,
            'status' => DistributorDiscountSet::STATUS_INACTIVE,
            'discount_percent' => 10,
            'is_indefinite' => 1,
        ], $overrides);
    }

    /**
     * @return array{0: User}
     */
    private function makeDistributorAdmin(): array
    {
        $role = Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
        $admin = User::factory()->create([
            'name' => 'Админ Дистрибьютора',
            'email' => 'dst-disc-admin@example.com',
        ]);
        $admin->roles()->attach($role->id, [
            'company_name' => 'Дистр Скидки',
            'company_type' => 'distributor',
            'company_status' => CompanyStatus::ACTIVE,
        ]);
        $profile = $admin->getOrCreateDistributorProfile();
        $profile->update(['full_name' => 'Дистр Скидки', 'short_name' => 'ДСк']);
        $region = Region::factory()->create();
        DistributorWarehouse::query()->create([
            'distributor_profile_id' => $profile->id,
            'name' => 'Склад скидок',
            'address' => 'Москва',
            'region_id' => $region->id,
            'type' => DistributorWarehouse::TYPE_MAIN,
            'is_active' => true,
        ]);
        app(DistributorEmployeeService::class)->ensureDefaultRoles($profile);

        return [$admin->fresh(['roles', 'distributorProfile'])];
    }

    /**
     * @param  array<string, mixed>  $offerOverrides
     */
    private function makeOffer(User $admin, array $offerOverrides = []): DistributorProduct
    {
        $profile = $admin->distributorProfile;
        $warehouse = $profile->warehouses()->first();
        $regionId = $warehouse?->region_id;
        if ($regionId) {
            $profile->regions()->sync([$regionId => ['is_primary' => true]]);
        }
        $manufacturer = ManufacturerProfile::factory()->create();
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'category_id' => $category->id,
            'show_in_catalog' => true,
            'status' => Product::STATUS_ACTIVE,
            'name' => 'Кабель скидочный',
        ]);
        ManufacturerDistributorPartnership::query()->create([
            'manufacturer_profile_id' => $manufacturer->id,
            'distributor_profile_id' => $profile->id,
            'status' => ManufacturerDistributorPartnership::STATUS_ACTIVE,
            'added_at' => now(),
        ]);
        $warehouse = $profile->warehouses()->first();
        $offer = DistributorProduct::query()->create(array_merge([
            'distributor_profile_id' => $profile->id,
            'source_product_id' => $product->id,
            'manufacturer_profile_id' => $manufacturer->id,
            'product_category_id' => $category->id,
            'name' => $product->name,
            'internal_sku' => 'D-DISC-1',
            'manufacturer_sku' => 'MFR-DISC',
            'brand' => 'Varta',
            'retail_price' => 2500,
            'status' => DistributorProduct::STATUS_ACTIVE,
            'min_order_quantity' => 1,
        ], $offerOverrides));
        if ($warehouse) {
            DistributorProductStock::query()->create([
                'distributor_product_id' => $offer->id,
                'distributor_warehouse_id' => $warehouse->id,
                'quantity' => 20,
                'reserved' => 0,
                'stock_updated_at' => now(),
            ]);
        }

        return $offer;
    }

    private function makeBuyer(User $admin, string $email = 'buyer-disc@example.com'): User
    {
        $role = Role::query()->where('slug', Role::SLUG_END_COMPANY)->firstOrFail();
        $region = $admin->distributorProfile->warehouses()->first()?->region
            ?? Region::query()->first()
            ?? Region::factory()->create(['name' => 'Москва']);
        $user = User::factory()->create(['email' => $email]);
        $user->roles()->attach($role->id, ['company_region' => $region->name]);
        $user->getOrCreateEndCompanyProfile()->update([
            'full_name' => 'ООО Покупатель',
            'short_name' => 'Покупатель',
            'inn' => '7701234567',
        ]);

        return $user->fresh(['roles']);
    }

    private function makeActiveEmployee(
        User $admin,
        string $email = 'dst-disc-employee@example.com',
        string $password = 'Secret123',
        string $roleName = 'Менеджер по продажам',
    ): User {
        Mail::fake();
        $role = DistributorEmployeeRole::query()->where('name', $roleName)->firstOrFail();

        $this->actingAsDistributor($admin)
            ->post(route('distributor.employees.store'), [
                'name' => 'Сотрудник',
                'email' => $email,
                'employee_role_id' => $role->id,
                'all_warehouses' => '1',
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

    private function makeDiscountEmployee(User $admin): User
    {
        Mail::fake();
        $this->actingAsDistributor($admin)
            ->post(route('distributor.employees.roles.store'), [
                'name' => 'Менеджер скидок',
                'permissions' => [
                    'orders' => 'none',
                    'purchases' => 'none',
                    'products' => 'none',
                    'discounts' => '1',
                ],
            ]);
        $role = DistributorEmployeeRole::query()->where('name', 'Менеджер скидок')->firstOrFail();

        $this->actingAsDistributor($admin)
            ->post(route('distributor.employees.store'), [
                'name' => 'Скидочный менеджер',
                'email' => 'dst-disc-manager@example.com',
                'employee_role_id' => $role->id,
                'all_warehouses' => '1',
            ]);

        $plainToken = null;
        Mail::assertSent(EmployeeInvitationMail::class, function ($mail) use (&$plainToken) {
            if (! $mail->hasTo('dst-disc-manager@example.com')) {
                return false;
            }
            $plainToken = $mail->plainToken;

            return true;
        });

        auth()->logout();
        $this->post(route('employee-invitation.accept', $plainToken), [
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ]);
        auth()->logout();

        return User::query()->where('email', 'dst-disc-manager@example.com')->firstOrFail();
    }

    private function actingAsDistributor(User $user): self
    {
        $role = $user->roles->firstWhere('slug', Role::SLUG_DISTRIBUTOR) ?? Role::query()->where('slug', Role::SLUG_DISTRIBUTOR)->firstOrFail();
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
