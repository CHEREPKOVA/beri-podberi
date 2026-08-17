<x-mail::message>
# Статус заказа изменён

{{ $message }}

**Заказ:** {{ $order->order_number }}  
**Статус:** {{ $statusLabel }}

@if($order->tracking_number)
**ТТН / трек-номер:** {{ $order->tracking_number }}
@endif

@if($order->rejection_reason)
**Комментарий:** {{ $order->rejection_reason }}
@endif

С уважением,<br>
{{ config('app.name') }}
</x-mail::message>
