<?php

namespace App\Services;

use App\Models\DistributorProfile;
use App\Services\Concerns\BuildsProfileCompletionSummary;

class DistributorProfileCompletionService
{
    use BuildsProfileCompletionSummary;

    public function summary(DistributorProfile $profile): array
    {
        $profile->loadMissing(['regions', 'productCategories', 'contacts', 'warehouses', 'deliveryMethods']);

        $partnerCatalogReady = $profile->regions->isNotEmpty() && $profile->productCategories->isNotEmpty();

        $steps = [
            $this->step(
                key: 'company',
                title: 'Данные компании',
                description: 'Заполните юридический адрес и описание компании',
                completed: filled($profile->legal_address) && filled($profile->short_name),
                url: route('distributor.profile', ['tab' => 'company']),
                required: true,
            ),
            $this->step(
                key: 'contacts',
                title: 'Контактные данные',
                description: 'Добавьте контактное лицо с телефоном или e-mail',
                completed: $profile->contacts->contains(
                    fn ($contact) => filled($contact->email) || filled($contact->phone)
                ),
                url: route('distributor.profile', ['tab' => 'contacts']),
                required: true,
            ),
            $this->step(
                key: 'regions',
                title: 'Регионы присутствия',
                description: 'Укажите регионы, в которых работает компания',
                completed: $profile->regions->isNotEmpty(),
                url: route('distributor.profile', ['tab' => 'regions']),
                required: true,
            ),
            $this->step(
                key: 'product_categories',
                title: 'Типы продукции',
                description: 'Выберите категории товаров для каталога партнёров',
                completed: $profile->productCategories->isNotEmpty(),
                url: route('distributor.profile', ['tab' => 'product_categories']),
                required: true,
            ),
            $this->step(
                key: 'warehouses',
                title: 'Склады',
                description: 'Добавьте хотя бы один склад для работы с остатками',
                completed: $profile->warehouses->where('is_active', true)->isNotEmpty(),
                url: route('distributor.warehouses.index'),
                required: false,
            ),
            $this->step(
                key: 'delivery',
                title: 'Доставка и логистика',
                description: 'Настройте способы доставки и транспортные компании',
                completed: $profile->deliveryMethods->isNotEmpty(),
                url: route('distributor.profile', ['tab' => 'delivery']),
                required: false,
            ),
        ];

        return $this->buildSummary(
            steps: $steps,
            introComplete: 'Обязательные шаги выполнены. Завершите дополнительные пункты для полноценной работы на платформе.',
            introIncomplete: 'Заполните профиль, чтобы производители видели вашу компанию в каталоге партнёров.',
            notice: $partnerCatalogReady
                ? ['type' => 'info', 'message' => 'Профиль доступен в каталоге партнёров производителей.']
                : ['type' => 'warning', 'message' => 'Профиль пока не отображается в каталоге партнёров производителей. Укажите регионы и типы продукции.'],
        );
    }
}
