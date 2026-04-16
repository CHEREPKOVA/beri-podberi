# Автодеплой через GitHub Actions (Laravel)

## Что уже добавлено

- Workflow: `.github/workflows/deploy.yml`
- Триггеры:
  - push в `main`/`master`
  - ручной запуск через `workflow_dispatch`

## 1) Подготовка сервера

1. На сервере должен быть готовый проект (клонированный репозиторий), например:
   - `/var/www/beri-podberi`
2. Должны быть установлены:
   - PHP (с нужными расширениями)
   - Composer
   - Git
3. Пользователь, под которым идет деплой (например `deploy`), должен иметь права на папку проекта.
4. Файл `.env` должен уже существовать на сервере и не перетираться деплоем.
5. Убедитесь, что команда работает вручную:
   - `cd /var/www/beri-podberi && php artisan --version`

## 2) Создание SSH-ключа для GitHub Actions

На локальной машине:

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_deploy
```

Добавьте публичный ключ на сервер в `~/.ssh/authorized_keys` пользователя деплоя:

```bash
cat ~/.ssh/github_actions_deploy.pub
```

Скопируйте и вставьте вывод в:

```bash
~/.ssh/authorized_keys
```

Проверьте вход:

```bash
ssh -i ~/.ssh/github_actions_deploy deploy@YOUR_SERVER_IP
```

## 3) Секреты в GitHub

Откройте: `GitHub repo -> Settings -> Secrets and variables -> Actions -> New repository secret`

Создайте секреты:

- `SSH_HOST` — IP или домен сервера (например `1.2.3.4`)
- `SSH_PORT` — порт SSH (обычно `22`)
- `SSH_USER` — пользователь для деплоя (например `deploy`)
- `SSH_PRIVATE_KEY` — содержимое файла `~/.ssh/github_actions_deploy` (приватный ключ целиком)
- `PROJECT_PATH` — абсолютный путь к проекту на сервере (например `/var/www/beri-podberi`)

## 4) Первый запуск

1. Закоммитьте и запушьте workflow в GitHub.
2. Зайдите в `Actions`.
3. Запустите `Deploy Laravel` вручную (Run workflow) или просто отправьте commit в `main`/`master`.
4. Проверьте логи job и убедитесь, что все шаги прошли успешно.

## 5) Что делает деплой

На сервере выполняется:

1. Переход в `PROJECT_PATH`
2. `git fetch --all`
3. `git reset --hard origin/<ветка>`
4. `composer install --no-dev --optimize-autoloader`
5. `php artisan migrate --force`
6. Очистка и пересборка кешей Laravel
7. `php artisan queue:restart` (если очереди не используются, шаг не ломает деплой)

## 6) Важные замечания

- Workflow использует `git reset --hard origin/<ветка>`, поэтому локальные незакоммиченные изменения на сервере будут удалены.
- Для production лучше деплоить только из защищенной ветки (`main`) и включить branch protection.
- Если используете Supervisor/systemd для воркеров, убедитесь, что они корректно перезапускаются после `queue:restart`.
- Если миграции потенциально долгие, планируйте окно релиза.
