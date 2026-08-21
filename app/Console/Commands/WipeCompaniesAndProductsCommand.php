<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class WipeCompaniesAndProductsCommand extends Command
{
    protected $signature = 'data:wipe-companies {--force : Выполнить без подтверждения}';

    protected $description = 'Удаляет все товары и компании (производители, дистрибьюторы, конечные компании) вместе с заказами, корзинами и пользователями этих компаний. Справочники и сотрудники платформы без привязки к компании сохраняются.';

    private const PROTECTED_ADMIN_ID = 1;

    /**
     * Таблицы с данными товаров, компаний, заказов и приглашений.
     * Справочники (категории, атрибуты, регионы, статусы, роли) не трогаем.
     *
     * @var list<string>
     */
    private const TABLES = [
        'cart_items',
        'carts',
        'distributor_purchase_cart_items',
        'distributor_purchase_carts',
        'platform_order_claims',
        'platform_order_documents',
        'platform_order_status_logs',
        'platform_order_items',
        'platform_orders',
        'distributor_product_regional_prices',
        'distributor_product_documents',
        'distributor_product_change_logs',
        'distributor_product_price_histories',
        'distributor_product_stocks',
        'distributor_products',
        'product_analogs',
        'product_category_product',
        'product_attribute_values',
        'product_regional_prices',
        'product_documents',
        'product_images',
        'product_stocks',
        'product_region',
        'products',
        'manufacturer_distributor_partnership_logs',
        'manufacturer_distributor_exclusive_regions',
        'manufacturer_distributor_partnerships',
        'distributor_product_category',
        'manufacturer_contacts',
        'manufacturer_region',
        'manufacturer_documents',
        'manufacturer_delivery_settings',
        'manufacturer_transport_company',
        'warehouses',
        'manufacturer_profiles',
        'distributor_contacts',
        'distributor_region',
        'distributor_documents',
        'distributor_delivery_settings',
        'distributor_transport_company',
        'distributor_warehouses',
        'distributor_profiles',
        'end_company_profile_changes',
        'end_company_documents',
        'end_company_delivery_addresses',
        'end_company_contacts',
        'end_company_profiles',
        'company_invitations',
        'user_invitations',
    ];

    /** @var list<string> */
    private const PUBLIC_DIRECTORIES = [
        'products',
        'manufacturer',
        'distributor',
        'distributor-products',
        'end_company',
        'orders',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Это безвозвратно удалит все товары, компании, заказы и пользователей компаний. Продолжить?', false)) {
            $this->warn('Отменено.');

            return self::SUCCESS;
        }

        $before = $this->counts();
        $companyUserIds = $this->collectCompanyUserIds();
        $keepUserIds = $this->platformStaffUserIdsToKeep($companyUserIds);

        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->delete();

                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                }
            }

            $deletedUsers = $this->deleteCompanyUsers($companyUserIds, $keepUserIds);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->wipeUploadedFiles();

        $after = $this->counts();

        $this->newLine();
        $this->info('Готово. Удалено:');
        $this->line("  товаров производителей: {$before['products']} → {$after['products']}");
        $this->line("  товаров дистрибьюторов: {$before['distributor_products']} → {$after['distributor_products']}");
        $this->line("  производителей: {$before['manufacturers']} → {$after['manufacturers']}");
        $this->line("  дистрибьюторов: {$before['distributors']} → {$after['distributors']}");
        $this->line("  конечных компаний: {$before['end_companies']} → {$after['end_companies']}");
        $this->line("  пользователей компаний: {$before['company_users']} → {$after['company_users']}");
        $this->line("  заказов: {$before['orders']} → {$after['orders']}");
        $this->line("  учётных записей пользователей: удалено {$deletedUsers}, осталось {$after['users']}");

        return self::SUCCESS;
    }

    /**
     * @return array{products: int, distributor_products: int, manufacturers: int, distributors: int, end_companies: int, orders: int, company_users: int, users: int}
     */
    private function counts(): array
    {
        return [
            'products' => $this->tableCount('products'),
            'distributor_products' => $this->tableCount('distributor_products'),
            'manufacturers' => $this->tableCount('manufacturer_profiles'),
            'distributors' => $this->tableCount('distributor_profiles'),
            'end_companies' => $this->tableCount('end_company_profiles'),
            'orders' => $this->tableCount('platform_orders'),
            'company_users' => count($this->collectCompanyUserIds()),
            'users' => $this->tableCount('users'),
        ];
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    /**
     * Пользователи компаний: профили, корпоративные роли, приглашения.
     *
     * @return list<int>
     */
    private function collectCompanyUserIds(): array
    {
        $ids = collect();

        foreach (['manufacturer_profiles', 'distributor_profiles', 'end_company_profiles'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                $ids = $ids->merge(DB::table($table)->whereNotNull('user_id')->pluck('user_id'));
            }
        }

        if (Schema::hasTable('role_user')) {
            $corporateSlugs = $this->corporateRoleSlugs();

            $ids = $ids->merge(
                DB::table('role_user')
                    ->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->where(function ($query) use ($corporateSlugs): void {
                        $query->whereIn('roles.slug', $corporateSlugs)
                            ->orWhere(function ($companyQuery): void {
                                $companyQuery->whereNotNull('role_user.company_name')
                                    ->where('role_user.company_name', '!=', '');
                            })
                            ->orWhere(function ($typeQuery): void {
                                $typeQuery->whereNotNull('role_user.company_type')
                                    ->where('role_user.company_type', '!=', '');
                            });
                    })
                    ->pluck('role_user.user_id')
            );
        }

        if (Schema::hasTable('company_invitations')) {
            $ids = $ids->merge(DB::table('company_invitations')->whereNotNull('user_id')->pluck('user_id'));
        }

        return $ids->map(fn ($id): int => (int) $id)->unique()->filter()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function corporateRoleSlugs(): array
    {
        return array_values(array_unique(array_merge(
            Role::corporateSlugsWithEmployees(),
            [
                Role::SLUG_MANUFACTURER,
                Role::SLUG_DISTRIBUTOR,
                Role::SLUG_END_COMPANY,
                Role::SLUG_COMPANY_EMPLOYEE,
            ],
        )));
    }

    /**
     * Сотрудники платформы без привязки к компании. Главный админ всегда сохраняется.
     *
     * @param  list<int>  $companyUserIds
     * @return list<int>
     */
    private function platformStaffUserIdsToKeep(array $companyUserIds): array
    {
        $staffIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.slug', config('roles.admin_panel_roles', [
                Role::SLUG_ADMIN,
                Role::SLUG_MANAGER,
                Role::SLUG_ANALYST,
            ]))
            ->pluck('role_user.user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $keepIds = array_values(array_diff($staffIds, $companyUserIds));
        $keepIds[] = self::PROTECTED_ADMIN_ID;

        return array_values(array_unique($keepIds));
    }

    /**
     * @param  list<int>  $companyUserIds
     * @param  list<int>  $keepUserIds
     */
    private function deleteCompanyUsers(array $companyUserIds, array $keepUserIds): int
    {
        $corporateRoleIds = Role::query()
            ->whereIn('slug', $this->corporateRoleSlugs())
            ->pluck('id');

        DB::table('role_user')
            ->where(function ($query) use ($corporateRoleIds): void {
                $query->whereIn('role_id', $corporateRoleIds)
                    ->orWhere(function ($companyQuery): void {
                        $companyQuery->whereNotNull('company_name')->where('company_name', '!=', '');
                    })
                    ->orWhere(function ($typeQuery): void {
                        $typeQuery->whereNotNull('company_type')->where('company_type', '!=', '');
                    });
            })
            ->delete();

        $userIds = User::query()
            ->whereNotIn('id', $keepUserIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $userIds = array_values(array_unique(array_merge($userIds, array_diff($companyUserIds, $keepUserIds))));
        if ($userIds === []) {
            return 0;
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        }

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $userIds)
                ->delete();
        }

        if (Schema::hasTable('catalog_search_logs')) {
            DB::table('catalog_search_logs')->whereIn('user_id', $userIds)->delete();
        }

        if (Schema::hasTable('user_permissions')) {
            DB::table('user_permissions')->whereIn('user_id', $userIds)->delete();
        }

        if (Schema::hasTable('user_password_histories')) {
            DB::table('user_password_histories')->whereIn('user_id', $userIds)->delete();
        }

        if (Schema::hasTable('password_reset_tokens') && Schema::hasColumn('users', 'email')) {
            $emails = DB::table('users')->whereIn('id', $userIds)->pluck('email')->filter()->all();
            if ($emails !== []) {
                DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
            }
        }

        DB::table('role_user')->whereIn('user_id', $userIds)->delete();

        return DB::table('users')->whereIn('id', $userIds)->delete();
    }

    private function wipeUploadedFiles(): void
    {
        foreach (self::PUBLIC_DIRECTORIES as $directory) {
            if (Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->deleteDirectory($directory);
            }
        }

        if (Storage::disk('local')->exists('imports/distributor')) {
            Storage::disk('local')->deleteDirectory('imports/distributor');
        }
    }
}
