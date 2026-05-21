# RBAC: покрытие тестами

Документ фиксирует, что проверяется в 5-м спринте по правам доступа и аудиту админ-панели.

## Feature-тесты

- `tests/Feature/AdminAccessControlTest.php`
  - `manager` имеет доступ к `admin.companies.*`, но не имеет доступ к `admin.staff.*`.
  - `analyst` не имеет доступа к управляющим модулям (`companies`, `catalog`).
  - override в `user_permissions` применяется на следующий запрос без релогина.
  - middleware `admin.audit` пишет изменение в `admin_action_logs` с route/method/status.

## Базовые принципы

- Контроль доступа идет через `permission:*` middleware.
- Аудит изменений централизован через `admin.audit` middleware.
- Новые админ-маршруты, добавленные в `admin`-группу, автоматически попадают под аудит.
