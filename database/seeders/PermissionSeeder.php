<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(config('permissions.catalog', []));

        $permissions->each(function (array $item): void {
            Permission::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'group_key' => $item['group_key'] ?? null,
                    'sort_order' => $item['sort_order'] ?? 0,
                ]
            );
        });

        $allPermissionIds = Permission::query()->pluck('id')->all();
        $roleDefaults = config('permissions.role_defaults', []);

        foreach ($roleDefaults as $roleSlug => $permissionSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            if (! $role) {
                continue;
            }

            if (in_array('*', $permissionSlugs, true)) {
                $role->permissions()->sync($allPermissionIds);
                continue;
            }

            $permissionIds = Permission::query()
                ->whereIn('slug', $permissionSlugs)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
