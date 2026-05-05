# Вход через Яндекс ID и VK ID

Социальные OAuth-провайдеры — быстрая альтернатива ЕСИА: бесплатно, без
сертификатов, без бюрократии. Подключаются за 1 день.

В отличие от ЕСИА не дают «настоящий» УНЭП (СНИЛС/паспорт), но дают
верифицированный e-mail и ФИО, чего достаточно для большинства B2C/SMB
сценариев на ЭТП.

## Архитектура

- `oauth/OAuthConfig.php` — конфиг провайдеров (env vars).
- `oauth/OAuthSchema.php` — миграция таблицы `social_accounts`.
- `oauth/OAuthHelper.php` — HTTP, state/nonce, find-or-create user.
- `oauth/YandexClient.php` / `oauth/VkClient.php` — клиенты OAuth 2.0.
- `yandex_login.php` / `yandex_callback.php` — flow Яндекса.
- `vk_login.php` / `vk_callback.php` — flow VK.
- `auth_modal.php` — кнопки появляются только если provider настроен.

Если переменные окружения не заданы — кнопки не показываются, callback
отдают 503 с понятной ошибкой. Существующая авторизация по логину/паролю
и через Telegram не ломается.

## Регистрация Яндекс OAuth

1. https://oauth.yandex.ru/client/new
2. Платформа — «Веб-сервисы».
3. Callback URI: `https://forsage.ct.ws/yandex_callback.php`.
4. Доступы (scope) — отметьте: `login:info`, `login:email`, `login:birthday`,
   `login:avatar`.
5. Сохраните → запишите `Client ID` и `Client Secret`.

## Регистрация VK ID приложения

1. https://id.vk.com/business → «Создать приложение» (или
   `https://vk.com/apps?act=manage`, если там удобнее).
2. Тип — «Веб-сайт».
3. Адрес сайта: `https://forsage.ct.ws`.
4. Базовый домен: `forsage.ct.ws`.
5. Доверенный redirect URI: `https://forsage.ct.ws/vk_callback.php`.
6. В настройках приложения откройте страницу с ключами и запишите:
   `app_id` (он же `client_id`), `secure_key` (он же `client_secret`).

## Переменные окружения

В корне сайта (`.htaccess` на InfinityFree или панель хостинга → «Переменные
окружения») задайте:

```
# Yandex
YANDEX_CLIENT_ID=...
YANDEX_CLIENT_SECRET=...
YANDEX_REDIRECT_URI=https://forsage.ct.ws/yandex_callback.php

# VK
VK_CLIENT_ID=...
VK_CLIENT_SECRET=...
VK_REDIRECT_URI=https://forsage.ct.ws/vk_callback.php
VK_API_VERSION=5.199
```

Пример блока в `.htaccess` (Apache на InfinityFree):

```
SetEnv YANDEX_CLIENT_ID xxxxxxx
SetEnv YANDEX_CLIENT_SECRET yyyyyyy
SetEnv YANDEX_REDIRECT_URI https://forsage.ct.ws/yandex_callback.php
SetEnv VK_CLIENT_ID xxxxxx
SetEnv VK_CLIENT_SECRET yyyyyy
SetEnv VK_REDIRECT_URI https://forsage.ct.ws/vk_callback.php
SetEnv VK_API_VERSION 5.199
```

## Что происходит при первом входе

1. Пользователь жмёт «Войти через Яндекс/VK» → редирект на oauth.яндекс/vk.
2. Авторизуется → callback с `code`.
3. Сервер обменивает `code` → `access_token`, тянет профиль (id, ФИО, email,
   аватар).
4. По `(provider, external_id)` ищем запись в `social_accounts`. Если есть —
   логиним. Если нет, но в `users` уже есть юзер с тем же email — линкуем к
   нему (одна учётка с несколькими провайдерами).
5. Иначе создаём нового `users`-юзера с `user_type='respected'`, `balance=0`,
   `username=yandex_…` / `vk_…`. Связь сохраняется в `social_accounts`.
6. Редирект на `return_to` (или `/profile.php`).

## Что НЕ делает

- Не выдаёт ЭП (УНЭП/УКЭП) — для подписи документов нужна ЕСИА (см.
  `README_ESIA.md`) или внешний сервис.
- Не верифицирует СНИЛС/ИНН — для участия в торгах с такими требованиями
  площадка должна потребовать дополнительную идентификацию через ЕСИА.
- Не подменяет регистрацию по форме (логин/пароль) — существует параллельно.

## Будущее: Сбер ID

Сбер ID отдаёт верифицированные ФИО/паспорт/телефон/СНИЛС (банк делает
KYC через Госуслуги при подключении). Подключение требует:

- Договор с СберБизнес («Сбер ID для бизнеса»).
- API-ключ из кабинета СберБизнес.
- Реализация почти идентичная Яндексу — добавим `oauth/SberClient.php`.

Подключим в следующей итерации, когда у вас будет учётка СберБизнес.
