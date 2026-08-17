<x-mail::message>
# Компания активирована

Ваша компания **{{ $companyName }}** успешно активирована. Вы можете войти в личный кабинет.

<x-mail::button :url="$loginUrl">
Войти в личный кабинет
</x-mail::button>

Спасибо,<br>
{{ config('app.name') }}
</x-mail::message>
