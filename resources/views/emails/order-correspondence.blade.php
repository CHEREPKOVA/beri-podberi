<x-mail::message>
# Переписка по заказу

{{ $text }}

**Заказ:** {{ $order->order_number }}

Откройте карточку заказа в личном кабинете, чтобы ответить.

С уважением,<br>
{{ config('app.name') }}
</x-mail::message>
