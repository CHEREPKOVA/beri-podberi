<x-mail::message>
# Изменение доступа

Здравствуйте{{ $employee->name ? ', '.$employee->name : '' }}!

@if($action === 'blocked')
Администратор компании **{{ $companyDisplayName }}** деактивировал ваш доступ в личный кабинет. Авторизация временно невозможна.
@else
Администратор компании **{{ $companyDisplayName }}** восстановил ваш доступ в личный кабинет. Вы снова можете войти в систему.
@endif

Спасибо,<br>
{{ config('app.name') }}
</x-mail::message>
