# era-trade signer

Тонкий микросервис для **ГОСТ Р 34.10-2012 (256)** подписи запросов к ЕСИА.

Зачем нужен. ЕСИА требует, чтобы каждый запрос на `/aas/oauth2/v3/te`
(обмен `code` → `access_token`) был подписан клиентским сертификатом ГОСТ
в формате PKCS#7-detached. Реализовать это на чистом PHP / шаред-хостинге
невозможно (нет CryptoPro CSP, нет нативных GOST-расширений).
Этот сервис вытаскивает подпись наружу: основной сайт `era-trade` на
PHP-хостинге дёргает `POST /sign`, signer возвращает готовый
`client_secret` (base64url-PKCS#7).

Под капотом — `github.com/Theo730/pkcs7` (CMS-каркас) + `gogost`
(чистая Go реализация ГОСТ Р 34.10/34.11-2012). Никакого CryptoPro, никаких
нативных бинарников.

---

## Endpoints

```
POST /sign     X-API-Key: <ключ>
               Content-Type: application/json
               { "payload": "<строка для подписи>" }
               → 200 { "signature": "<base64url-PKCS#7>", "alg": "GOST3410-2012-256" }

GET  /health   → 200 { "status": "ok", "alg": "...", "time": "..." }
```

`payload` — это уже скомпонованная строка, которую ЕСИА ожидает в
`client_secret`: `scope + timestamp + clientId + state` (без разделителей,
конкатенация UTF-8 байт). Конкатенацию делает PHP-сторона
(`esia/EsiaClient.php` → `RemoteGostSigner`), signer её не трогает —
только хеширует Streebog-256 и подписывает.

---

## Локальный запуск

```bash
# понадобится Go 1.21+
cp /path/to/cert.pem ./cert.pem
cp /path/to/key.pem  ./key.pem
SIGNER_API_KEY=secret \
SIGNER_CERT_PATH=$PWD/cert.pem \
SIGNER_KEY_PATH=$PWD/key.pem \
PORT=8080 \
go run .

# в другом терминале
curl -sS -H 'X-API-Key: secret' -H 'Content-Type: application/json' \
  -d '{"payload":"openid fullname2024-01-01T00:00:00 +0300FORSAGE-state-abc"}' \
  http://localhost:8080/sign
```

---

## Тесты

```bash
go test ./...
```

В тестах используется готовая пара `cert.pem`/`key.pem` из
`Theo730/pkcs7` (тестовый материал), чтобы проверить, что хешер +
подписант + PKCS#7-обёртка работают связно. Они не проверяют корректность
подписи на стороне ЕСИА — для этого нужен реальный тестовый сертификат
и тестовый контур `esia-portal1.test.gosuslugi.ru`.

---

## Деплой

### Render.com (Free)

1. Подключите репозиторий `hasstepanian-wq/era-trade` к Render.
2. New → Blueprint → выберите `signer/render.yaml`.
3. В Dashboard → Secret Files добавьте:
   - `/etc/secrets/cert.pem` — содержимое тестового сертификата ESIA
   - `/etc/secrets/key.pem` — приватный ключ к нему
4. Environment → SIGNER_API_KEY → сгенерируйте длинный случайный.
5. Получите URL вида `https://era-esia-signer.onrender.com`.

⚠ Free-инстанс засыпает после 15 мин неактивности (первый запрос ~30 сек
на разогрев). Для прода — Starter $7/мес или VPS.

### Fly.io (Free)

```bash
fly launch --name era-esia-signer --region fra --copy-config
fly secrets set SIGNER_API_KEY=<длинный случайный>
fly secrets set --stage SIGNER_CERT_PEM="$(cat cert.pem)"
fly secrets set --stage SIGNER_KEY_PEM="$(cat key.pem)"
fly deploy
```

### Beget VPS Start (~200 ₽/мес, в РФ — самое стабильное)

```bash
ssh root@<vps>
apt-get update && apt-get install -y docker.io
mkdir -p /opt/signer/secrets
scp cert.pem key.pem root@<vps>:/opt/signer/secrets/
docker build -t era-signer https://github.com/hasstepanian-wq/era-trade.git#devin/esia-integration:signer
docker run -d --name era-signer --restart always \
  -p 127.0.0.1:8080:8080 \
  -v /opt/signer/secrets:/secrets:ro \
  -e SIGNER_API_KEY=<длинный случайный> \
  era-signer
# nginx-сайдкар на 443 с Let's Encrypt → https://signer.<ваш-домен>.ru
```

---

## Подключение к era-trade

В `.env` или серверной панели InfinityFree (через PHP `putenv`)
проставьте:

```
ESIA_SIGNER_REMOTE_URL=https://era-esia-signer.onrender.com/sign
ESIA_SIGNER_REMOTE_KEY=<тот же SIGNER_API_KEY>
ESIA_SIGNER_BACKEND=gost_remote
```

`esia/RemoteGostSigner.php` сразу заработает: сделает POST с
`X-API-Key`, получит `signature` и подставит его в запрос на ЕСИА.

---

## Безопасность

- Приватный ключ ESIA **никогда не должен покидать** signer-VPS.
  В PHP/InfinityFree он не хранится.
- `SIGNER_API_KEY` — общий секрет PHP↔signer. Меняйте, если
  утёк (можно держать пару активных ключей с rolling-rotation).
- Все запросы только по HTTPS, nginx/Render автоматически выдают TLS.
- IP-allowlist (только IP InfinityFree исходящего трафика) — желательно,
  но InfinityFree может менять IP без предупреждения, так что ставка
  на `X-API-Key` + длинный секрет.
