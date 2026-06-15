<?php

namespace App\Services;

use App\Models\ManufacturerProfile;
use App\Services\Concerns\BuildsProfileCompletionSummary;

class ManufacturerProfileCompletionService
{
    use BuildsProfileCompletionSummary;

    public function summary(ManufacturerProfile $profile): array
    {
        $profile->loadMissing(['regions', 'contacts', 'warehouses', 'deliveryMethods']);
        $profile->loadCount('products');

        $hasProducts = ($profile->products_count ?? 0) > 0;
        $hasRegions = $profile->regions->isNotEmpty();
        $catalogReady = $hasProducts && $hasRegions;

        $steps = [
            $this->step(
                key: 'company',
                title: 'Данные компании',
                description: 'Заполните юридический адрес и описание компании',
                completed: filled($profile->legal_address) && filled($profile->short_name),
                url: route('manufacturer.profile', ['tab' => 'company']),
                required: true,
            ),
            $this->step(
                key: 'contacts',
                title: 'Контактные данные',
                description: 'Добавьте контактное лицо с телефоном или e-mail',
                completed: $profile->contacts->contains(
                    fn ($contact) => filled($contact->email) || filled($contact->phone)
                ),
                url: route('manufacturer.profile', ['tab' => 'contacts']),
                required: true,
            ),
            $this->step(
                key: 'regions',
                title: 'Регионы присутствия',
                description: 'Укажите регионы, в которых представлена компания',
                completed: $hasRegions,
                url: route('manufacturer.profile', ['tab' => 'regions']),
                required: true,
            ),
            $this->step(
                key: 'products',
                title: 'Номенклатура',
                description: 'Добавьте хотя бы один товар в каталог',
                completed: $hasProducts,
                url: route('manufacturer.products.index'),
                required: true,
            ),
            $this->step(
                key: 'warehouses',
                title: 'Склады',
                description: 'Добавьте склад для учёта остатков',
                completed: $profile->warehouses->where('is_active', true)->isNotEmpty(),
                url: route('manufacturer.warehouses.index'),
                required: false,
            ),
            $this->step(
                key: 'delivery',
                title: 'Доставка и логистика',
                description: 'Настройте способы доставки и транспортные компании',
                completed: $profile->deliveryMethods->isNotEmpty(),
                url: route('manufacturer.profile', ['tab' => 'delivery']),
                required: false,
            ),
        ];

        return $this->buildSummary(
            steps: $steps,
            introComplete: 'Обязательные шаги выполнены. Завершите дополнительные пункты для полноценной работы на платформе.',
            introIncomplete: 'Заполните профиль и добавьте товары, чтобы дистрибьюторы могли работать с вашим ассортиментом.',
            notice: $catalogReady
                ? ['type' => 'info', 'message' => 'Профиль и номенклатура готовы к работе с дистрибьюторами.']
                : ['type' => 'warning', 'message' => 'Добавьте товары и укажите регионы присутствия, чтобы дистрибьюторы видели ваш ассортимент.'],
        );
    }
}
