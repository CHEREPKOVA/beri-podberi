<x-mail::message>
# Приглашение в личный кабинет

Здравствуйте{{ $invitation->name ? ', '.$invitation->name : '' }}!

Вас пригласили в личный кабинет компании **{{ $companyDisplayName }}** на платформе Бери-Подбери.

Перейдите по ссылке, чтобы задать пароль и активировать доступ:

<x-mail::button :url="$registerUrl">
Задать пароль и войти
</x-mail::button>

Ссылка действительна до **{{ $expiresAt->timezone(config('app.timezone'))->format('d.m.Y H:i') }}**.

Если вы не ожидали это письмо, просто проигнорируйте его.

Спасибо,<br>
{{ config('app.name') }}
</x-mail::message>
