<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Печать заказа {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; color: #111; margin: 0; padding: 24px; font-size: 13px; line-height: 1.45; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        h2 { font-size: 15px; margin: 20px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .meta p { margin: 2px 0; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; font-weight: 600; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-top: 12px; text-align: right; font-size: 15px; font-weight: 700; }
        .actions { margin-bottom: 16px; }
        .actions button { border: 1px solid #c3242a; background: #c3242a; color: #fff; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; }
        @media print { .actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Печать</button></div>
    <h1>Закупка {{ $order->order_number }}</h1>
    <div class="meta">
        <p><span class="muted">Дата:</span> {{ optional($order->ordered_at)->format('d.m.Y H:i') ?: '—' }}</p>
        <p><span class="muted">Статус:</span> {{ $order->statusLabel() }}</p>
        <p><span class="muted">Источник:</span> {{ $order->sourceLabel() }}</p>
        <p><span class="muted">Производитель:</span> {{ $order->manufacturerProfile?->displayName() ?? '—' }}</p>
        <p><span class="muted">Покупатель:</span> {{ $order->distributorProfile?->displayName() ?? '—' }}</p>
        @if($order->distributorWarehouse)
            <p><span class="muted">Склад поставки:</span> {{ $order->distributorWarehouse->name }}</p>
        @endif
        @if($order->responsibleContact)
            <p><span class="muted">Ответственный:</span> {{ $order->responsibleContact->full_name }}</p>
        @endif
    </div>
    <h2>Состав заказа</h2>
    <table>
        <thead>
            <tr>
                <th>Товар</th>
                <th>Артикул</th>
                <th class="num">Цена</th>
                <th class="num">Кол-во</th>
                <th class="num">Сумма</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->sku ?: '—' }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2, ',', ' ') }} ₽</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format((float) $item->line_total, 2, ',', ' ') }} ₽</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="totals">Итого: {{ number_format((float) $order->total_amount, 2, ',', ' ') }} ₽</p>
</body>
</html>
