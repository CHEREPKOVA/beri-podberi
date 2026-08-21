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

    protected $description = 'Удаляет все товары и компании (производители, дистрибьюторы, конечные компании) вместе с заказами, корзинами и пользователями этих компаний. Справочники и администраторы платформы сохраняются.';

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

            $deletedUsers = $this->deleteCompanyUsers();
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
        $this->line("  пользователей компаний: {$deletedUsers}");
        $this->line("  заказов: {$before['orders']} → {$after['orders']}");

        return self::SUCCESS;
    }

    /**
     * @return array{products: int, distributor_products: int, manufacturers: int, distributors: int, end_companies: int, orders: int}
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
        ];
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function deleteCompanyUsers(): int
    {
        $adminPanelRoles = config('roles.admin_panel_roles', [
            Role::SLUG_ADMIN,
            Role::SLUG_MANAGER,
            Role::SLUG_ANALYST,
        ]);

        $keepUserIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.slug', $adminPanelRoles)
            ->pluck('role_user.user_id')
            ->unique()
            ->all();

        $corporateRoleIds = Role::query()
            ->whereIn('slug', [
                Role::SLUG_MANUFACTURER,
                Role::SLUG_DISTRIBUTOR,
                Role::SLUG_END_COMPANY,
                Role::SLUG_COMPANY_EMPLOYEE,
            ])
            ->pluck('id');

        DB::table('role_user')->whereIn('role_id', $corporateRoleIds)->delete();

        if ($keepUserIds === []) {
            $this->warn('Администраторы платформы не найдены — пользователи не удаляются.');

            return 0;
        }

        $userIds = User::query()->whereNotIn('id', $keepUserIds)->pluck('id');
        if ($userIds->isEmpty()) {
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

        return User::query()->whereIn('id', $userIds)->delete();
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
