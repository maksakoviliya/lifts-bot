# lifts-bot

Telegram-бот со статусами подъёмников и камерами Шерегеша (данные парсятся с egegesh.ru), погодой
(WorldWeatherOnline) и ежедневным постом в канал. Laravel 12, Filament 4 (админка на `/admin`).

## Локальный запуск (Docker / Sail, MySQL)

Нужны PHP 8.2+ и composer на хосте (только чтобы поставить `vendor/`), Docker, ngrok для бота.

```bash
composer install
cp .env.example .env          # значения DB_* и APP_PORT=8080 уже под Sail
php artisan key:generate
./vendor/bin/sail up -d       # app + mysql + redis; первая сборка образа идёт несколько минут
./vendor/bin/sail artisan migrate --seed   # админ: admin@admin.admin / password
```

Админка: http://localhost:8080/admin

Если порт занят, поменяйте `APP_PORT`, `FORWARD_DB_PORT` или `VITE_PORT` в `.env`.

### Данные

```bash
./vendor/bin/sail artisan app:parse-lifts-command   # подъёмники
./vendor/bin/sail artisan app:parse-web-cams        # список камер
./vendor/bin/sail artisan app:parse-web-cam         # скриншоты камер
./vendor/bin/sail artisan app:fetch-weather         # нужен WORLDWEATHERONONLINE_API_KEY
./vendor/bin/sail artisan schedule:work             # планировщик, как на проде (routes/console.php)
```

### Бот

Используйте **отдельного тестового бота** от @BotFather. С боевым токеном `setWebhook` перехватит вебхук у прода.

1. `.env`: `TELEGRAM_BOT_TOKEN=<тестовый>`, `TELEGRAM_WEBHOOK_SECRET=<любая строка>`,
   `TELEGRAM_REQUIRED_CHANNEL`/`TELEGRAM_CHANNEL_URL` указывают на свой тестовый канал (бот должен быть в нём
   админом, иначе проверка подписки всегда пропускает).
2. `ngrok http 8080`, затем `APP_URL=https://<id>.ngrok-free.app` в `.env`.
3. Зарегистрировать вебхук и меню:
   ```bash
   ./vendor/bin/sail artisan app:set-bot-webhook-command
   ./vendor/bin/sail artisan app:set-bot-commands-command
   ```
4. `telegram:send-lifts-to-channel` постит в `TELEGRAM_REQUIRED_CHANNEL`.

### Тесты

```bash
./vendor/bin/sail artisan test
```

## Прод: секрет вебхука

`/webhook` проверяет заголовок `X-Telegram-Bot-Api-Secret-Token`, если задан `TELEGRAM_WEBHOOK_SECRET`.
Чтобы включить проверку: задать переменную на проде и перерегистрировать вебхук
(`php artisan app:set-bot-webhook-command`). Пока переменная пустая, вебхук работает как раньше.
