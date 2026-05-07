# Интеграция с ЕСИА (Госуслуги): авторизация и подпись документов

## Что уже сделано в коде

```
esia/
├── EsiaConfig.php           # читает env, флаг включения
├── EsiaSignerInterface.php  # интерфейс подписанта (ГОСТ Р 34.10-2012)
├── MockSigner.php           # HMAC-SHA256, для разработки UI
├── RemoteGostSigner.php     # делегирует подпись внешнему микросервису
├── EsiaClient.php           # OAuth/OIDC v3: /ac, /te, /rs/prns/{oid}
└── EsiaSchema.php           # миграция users + esia_signatures

esia_login.php           # старт OAuth flow → редирект на esia.gosuslugi.ru
esia_callback.php        # приём code, обмен на токен, линковка/создание users
esia_sign.php            # старт подписи документа
esia_sign_callback.php   # приём подписи → запись в esia_signatures

auth_modal.php           # синяя кнопка «Войти через Госуслуги» (только если ESIA включена)
```

## Кнопка в шапке появляется автоматически

Кнопка «Войти через Госуслуги» (синий `#0d4cd3`, иконка ЕСИА) показывается в модалке
авторизации только когда заданы переменные окружения `ESIA_CLIENT_ID` и
`ESIA_REDIRECT_URI`. Без них существующий сайт не меняется ни на пиксель.

## Что нужно сделать ВАМ для запуска

### Шаг 1. Регистрация ИС в техпортале ЕСИА (10–15 минут)

1. Войдите в [esia.gosuslugi.ru](https://esia.gosuslugi.ru/) под учётной записью
   ЮЛ ООО «Форсаж».
2. Перейдите в [Технологический портал ЕСИА](https://esia.gosuslugi.ru/console/tech/).
3. Создайте «Информационную систему» с параметрами:
   - **Мнемоника**: например `FORSAGE` (станет `ESIA_CLIENT_ID`).
   - **Адрес для возврата**: `https://forsage.ct.ws/esia_callback.php`
   - **Адрес для возврата подписи**: `https://forsage.ct.ws/esia_sign_callback.php`
   - **Запрашиваемые scope**:
     `openid fullname birthdate gender snils inn email mobile contacts`
4. Заявка обрабатывается оператором ЕСИА: тест-контур ~1 рабочий день, продуктив
   `esia.gosuslugi.ru` 5 раб. дней — 2 мес. (зависит от Минцифры).

### Шаг 2. Получение сертификата ГОСТ Р 34.10-2012-256

Для подписи запросов ЕСИА нужен **квалифицированный сертификат на КриптоПро**
(не openssl). Варианты:

- **Тестовый контур**: можно использовать тестовые сертификаты Минцифры,
  раздаются бесплатно через техпортал тест-контура (после регистрации ИС).
- **Продуктив**: квалифицированный сертификат КЭП ЮЛ от любого аккредитованного
  УЦ (~3 000–6 000 ₽/год).

### Шаг 3. Деплой подписанта (signer-микросервис)

InfinityFree не разрешает нативные крипто-расширения, поэтому подпись
запросов вынесена в отдельный сервис в каталоге [`signer/`](signer/README.md).

Это Go-сервис (~250 строк), который **не требует CryptoPro CSP** — использует
открытую Go-реализацию ГОСТ Р 34.10/34.11-2012 (`Theo730/pkcs7` + `gogost`).

Варианты деплоя:

- **Render.com Free** (0 ₽/мес, спит после 15 мин) — для теста.
- **Fly.io Free** (0 ₽/мес, не спит, 3 машины) — для теста и лёгкого прода.
- **Beget VPS Start** (~200 ₽/мес, в РФ) — для прода.

Подробная инструкция и `Dockerfile`/`render.yaml`/`fly.toml` лежат в
[`signer/README.md`](signer/README.md). Endpoints:

```
POST /sign     X-API-Key: <ключ>
               { "payload": "<строка>" }
               → { "signature": "<base64url-PKCS7>", "alg": "GOST3410-2012-256" }
GET  /health
```

На этот сервис нужно положить:
- `cert.pem` — сертификат тестового или продуктивного контура ЕСИА,
- `key.pem` — приватный ключ к нему,
- `SIGNER_API_KEY` — общий секрет с PHP-сайтом.

### Шаг 4. Прописать переменные окружения

В `.htaccess` или через панель хостинга:

```apache
SetEnv ESIA_CLIENT_ID         FORSAGE
SetEnv ESIA_REDIRECT_URI      https://forsage.ct.ws/esia_callback.php
SetEnv ESIA_SIGN_CALLBACK_URI https://forsage.ct.ws/esia_sign_callback.php

# Тестовый контур:
SetEnv ESIA_AUTH_URL  https://esia-portal1.test.gosuslugi.ru/aas/oauth2/v3/ac
SetEnv ESIA_TOKEN_URL https://esia-portal1.test.gosuslugi.ru/aas/oauth2/v3/te
SetEnv ESIA_RS_URL    https://esia-portal1.test.gosuslugi.ru/rs

# Подписант:
SetEnv ESIA_SIGNER_DRIVER     gost_remote
SetEnv ESIA_SIGNER_REMOTE_URL https://signer.forsage.ct.ws/sign
SetEnv ESIA_SIGNER_REMOTE_KEY <случайный токен>
```

Для прода — заменить `esia-portal1.test.gosuslugi.ru` на `esia.gosuslugi.ru`.

### Шаг 5. Проверка цепочки

1. Открыть `https://forsage.ct.ws/esia_login.php` → должен прилететь редирект
   на ЕСИА.
2. Залогиниться, разрешить доступ к данным.
3. ЕСИА вернёт на `esia_callback.php?code=…&state=…`.
4. В таблице `users` появится строка с `esia_oid`, `esia_snils`, `esia_inn`.

## Текущий статус

- [x] OAuth/OIDC скаффолдинг
- [x] DB-миграция (запускается из `EsiaSchema.php`)
- [x] Кнопка «Войти через Госуслуги» в модалке авторизации
- [x] Заглушка-подписант (mock) для разработки UI
- [ ] Регистрация ИС в техпортале ЕСИА — на стороне заказчика
- [ ] Сертификат ГОСТ — на стороне заказчика
- [ ] Развёртывание signer-микросервиса (после получения сертификата)
- [ ] Подпись ставок/офферов через ЕСИА на live-формах
      (`send_proposal_offer.php`, `send_quotation_offer.php`,
      `send_closed_bid.php`, `apply_closed.php`)

## Юридическая разница УНЭП vs УКЭП

ЕСИА выдаёт **УНЭП** (усиленная неквалифицированная подпись) для большинства
действий. Это достаточно для b2c и большинства b2b-сделок до 1 млн ₽.

Для крупных b2b-сделок (>1 млн ₽, недвижимость, спецтехника) может потребоваться
**УКЭП** — тогда у пользователя должен быть привязан ЭП-сертификат на токене,
что выходит за пределы базовой ЕСИА. На таких формах оставляем
fallback-кнопку «Загрузить подпись файлом» (PKCS#7-detached).
