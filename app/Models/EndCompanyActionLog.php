<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndCompanyActionLog extends Model
{
    public $timestamps = false;

    public const ACTION_ORDER_CREATED = 'order.created';

    public const ACTION_ORDER_CHANGES_APPROVED = 'order.changes_approved';

    public const ACTION_ORDER_CHANGES_REJECTED = 'order.changes_rejected';

    public const ACTION_PROFILE_UPDATED = 'profile.updated';

    public const ACTION_EMPLOYEE_INVITED = 'employee.invited';

    public const ACTION_EMPLOYEE_UPDATED = 'employee.updated';

    public const ACTION_EMPLOYEE_BLOCKED = 'employee.blocked';

    public const ACTION_EMPLOYEE_UNBLOCKED = 'employee.unblocked';

    public const ACTION_EMPLOYEE_DELETED = 'employee.deleted';

    public const ACTION_EMPLOYEE_ROLE_MANAGED = 'employee.role_managed';

    protected $fillable = [
        'end_company_profile_id',
        'user_id',
        'user_name',
        'action',
        'description',
        'target_type',
        'target_id',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EndCompanyProfile::class, 'end_company_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_ORDER_CREATED => 'Создание заказа',
            self::ACTION_ORDER_CHANGES_APPROVED => 'Утверждение изменений заказа',
            self::ACTION_ORDER_CHANGES_REJECTED => 'Отклонение изменений заказа',
            self::ACTION_PROFILE_UPDATED => 'Редактирование профиля',
            self::ACTION_EMPLOYEE_INVITED => 'Приглашение сотрудника',
            self::ACTION_EMPLOYEE_UPDATED => 'Изменение сотрудника',
            self::ACTION_EMPLOYEE_BLOCKED => 'Блокировка учётной записи',
            self::ACTION_EMPLOYEE_UNBLOCKED => 'Разблокировка учётной записи',
            self::ACTION_EMPLOYEE_DELETED => 'Удаление учётной записи',
            self::ACTION_EMPLOYEE_ROLE_MANAGED => 'Управление ролями сотрудников',
            default => $this->action,
        };
    }
}
