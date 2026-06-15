<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanyType extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    public static function activeSlugs(): array
    {
        if (! Schema::hasTable('company_types')) {
            return config('roles.corporate_roles_with_employees', []);
        }

        $slugs = static::query()->active()->ordered()->pluck('slug')->all();

        return $slugs !== [] ? $slugs : config('roles.corporate_roles_with_employees', []);
    }

    public function syncLinkedRole(): void
    {
        Role::query()
            ->where('slug', $this->slug)
            ->update([
                'name' => $this->name,
                'description' => $this->description,
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active,
            ]);
    }

    public function isInUse(): bool
    {
        return DB::table('role_user')->where('company_type', $this->slug)->exists()
            || Role::query()->where('slug', $this->slug)->whereHas('users')->exists();
    }
}
