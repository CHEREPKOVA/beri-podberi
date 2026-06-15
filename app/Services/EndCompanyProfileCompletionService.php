<?php

namespace App\Services;

use App\Models\EndCompanyProfile;
use App\Services\Concerns\BuildsProfileCompletionSummary;

class EndCompanyProfileCompletionService
{
    use BuildsProfileCompletionSummary;

    public function summary(EndCompanyProfile $profile): array
    {
        $profile->loadMissing(['contacts', 'deliveryAddresses', 'documents']);

        $hasDeliveryAddress = $profile->deliveryAddresses->contains(
            fn ($address) => filled($address->address) && filled($address->region_id)
        );
        $hasContacts = $profile->contacts->contains(
            fn ($contact) => filled($contact->email) || filled($contact->phone)
        );
        $orderReady = $hasDeliveryAddress && $hasContacts;

        $steps = [
            $this->step(
                key: 'general',
                title: 'Общая информация',
                description: 'Укажите сокращённое название и тип деятельности',
                completed: filled($profile->short_name) && filled($profile->activity_type),
                url: route('end_company.profile', ['tab' => 'general']),
                required: true,
            ),
            $this->step(
                key: 'legal',
                title: 'Юридические реквизиты',
                description: 'Заполните ИНН и юридический адрес',
                completed: filled($profile->inn) && filled($profile->legal_address),
                url: route('end_company.profile', ['tab' => 'legal']),
                required: true,
            ),
            $this->step(
                key: 'contacts',
                title: 'Контакты',
                description: 'Добавьте контактное лицо с телефоном или e-mail',
                completed: $hasContacts,
                url: route('end_company.profile', ['tab' => 'contacts']),
                required: true,
            ),
            $this->step(
                key: 'delivery',
                title: 'Адреса доставки',
                description: 'Добавьте адрес с указанием региона для заказов',
                completed: $hasDeliveryAddress,
                url: route('end_company.profile', ['tab' => 'delivery']),
                required: true,
            ),
            $this->step(
                key: 'documents',
                title: 'Документы',
                description: 'Загрузите учредительные или лицензионные документы',
                completed: $profile->documents->isNotEmpty(),
                url: route('end_company.profile', ['tab' => 'documents']),
                required: false,
            ),
            $this->step(
                key: 'integration',
                title: 'Интеграции',
                description: 'Настройте EDI или webhook для обмена данными',
                completed: $profile->integration_edi_enabled || filled($profile->integration_webhook_url),
                url: route('end_company.profile', ['tab' => 'integration']),
                required: false,
            ),
        ];

        return $this->buildSummary(
            steps: $steps,
            introComplete: 'Обязательные шаги выполнены. Завершите дополнительные пункты для полноценной работы на платформе.',
            introIncomplete: 'Заполните профиль организации, чтобы пользоваться каталогом и оформлять заказы.',
            notice: $orderReady
                ? ['type' => 'info', 'message' => 'Профиль готов к работе с каталогом и оформлению заказов.']
                : ['type' => 'warning', 'message' => 'Добавьте контакты и адрес доставки с регионом для оформления заказов.'],
        );
    }
}
