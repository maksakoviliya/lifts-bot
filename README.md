# lifts-bot

Telegram-бот со статусами подъёмников и камерами Шерегеша (данные парсятся с egegesh.ru), погодой
(WorldWeatherOnline) и ежедневным постом в канал. Laravel 12, Filament 4 (админка на `/admin`).

## Локальный запуск (Docker / Sail, MySQL)

Нужны PHP 8.2+ и composer на хосте (только чтобы поставить `vendor/`) и Docker.

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

Бот получает апдейты через **long polling**: `telegram:poll` (`getUpdates`) запускается планировщиком каждую
минуту и работает ~55 секунд. Входящие соединения от Telegram не нужны, туннель тоже.

Используйте **отдельного тестового бота** от @BotFather. Боевой токен локально не подходит: два поллера на один
токен будут отбирать апдейты друг у друга.

1. `.env`: `TELEGRAM_BOT_TOKEN=<тестовый>`, `TELEGRAM_REQUIRED_CHANNEL`/`TELEGRAM_CHANNEL_URL` указывают на свой
   тестовый канал (бот должен быть в нём админом, иначе проверка подписки всегда пропускает).
2. Меню команд: `./vendor/bin/sail artisan app:set-bot-commands-command`
3. Запустить `./vendor/bin/sail artisan schedule:work` (или разово `./vendor/bin/sail artisan telegram:poll`).
4. `telegram:send-lifts-to-channel` постит в `TELEGRAM_REQUIRED_CHANNEL`.

Если у бота был включён вебхук, `getUpdates` отвечает 409, и поллер сам удаляет вебхук. Роут `/webhook` и
`app:set-bot-webhook-command` остались для окружений, куда Telegram может достучаться, но при включённом
планировщике поллер снимет вебхук в течение минуты.

### Тесты

```bash
./vendor/bin/sail artisan test
```

## Прод (REG.RU, без Docker)

Сервер в Москве: входящие соединения от Telegram до него не доходят (вебхук ловит `Connection timed out`),
поэтому прод работает на polling. Нужен только cron, раз в минуту, с PHP 8.4:

```
* * * * * cd /var/www/u2186645/data/www/lifts-bot && /opt/php/8.4/bin/php artisan schedule:run >> /dev/null 2>&1
```

После выкладки первый запуск `telegram:poll` сам удалит вебхук и обработает накопившиеся апдейты. Если старые
апдейты отвечать не нужно, до выкладки сбросьте их:

```bash
/opt/php/8.4/bin/php artisan tinker --execute='Telegram::deleteWebhook(["drop_pending_updates" => true]);'
```

Логи: `storage/logs/laravel-*.log` (бот, Telegram), `storage/logs/egegesh-*.log` (запросы к egegesh.ru).

`/webhook` проверяет заголовок `X-Telegram-Bot-Api-Secret-Token`, если задан `TELEGRAM_WEBHOOK_SECRET`. В режиме
polling это не используется.
