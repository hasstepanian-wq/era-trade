<?php
/**
 * Общие утилиты OAuth-flow: state/nonce, HTTP, поиск/создание пользователя
 * по записи в social_accounts.
 */

class OAuthHelper
{
    public static function randomState(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Простой HTTP POST с Content-Type: application/x-www-form-urlencoded.
     * Возвращает [status, body]. Бросает RuntimeException при сетевой ошибке.
     */
    public static function httpPostForm(string $url, array $params, array $headers = []): array
    {
        $ch = curl_init($url);
        $hdr = array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $hdr,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new RuntimeException("HTTP POST $url failed: $err");
        }
        return [$code, (string)$body];
    }

    /** GET, ожидает JSON, возвращает декодированный массив. */
    public static function httpGetJson(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new RuntimeException("HTTP GET $url failed: $err");
        }
        if ($code >= 400) {
            throw new RuntimeException("HTTP GET $url returned $code: $body");
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            throw new RuntimeException("HTTP GET $url returned non-JSON: $body");
        }
        return $data;
    }

    /**
     * Найти пользователя по (provider, external_id) в social_accounts. Если нет —
     * создать нового users-юзера, привязать запись и вернуть.
     *
     * Возвращает массив с ключами: id, username, full_name, balance, user_type, social_id.
     */
    public static function findOrCreateUser(
        PDO $pdo,
        string $provider,
        string $externalId,
        ?string $email,
        ?string $displayName,
        ?string $avatarUrl,
        array $rawProfile
    ): array {
        $st = $pdo->prepare(
            'SELECT u.id, u.username, u.full_name, u.balance, u.user_type, sa.id AS social_id
             FROM social_accounts sa
             JOIN users u ON u.id = sa.user_id
             WHERE sa.provider = ? AND sa.external_id = ?
             LIMIT 1'
        );
        $st->execute([$provider, $externalId]);
        $row = $st->fetch();
        if ($row) {
            $upd = $pdo->prepare(
                'UPDATE social_accounts
                 SET email = ?, display_name = ?, avatar_url = ?, raw_profile = ?, last_login_at = NOW()
                 WHERE id = ?'
            );
            $upd->execute([
                $email,
                $displayName,
                $avatarUrl,
                json_encode($rawProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                (int)$row['social_id'],
            ]);
            return [
                'id'        => (int)$row['id'],
                'username'  => (string)$row['username'],
                'full_name' => (string)$row['full_name'],
                'balance'   => (float)$row['balance'],
                'user_type' => (string)$row['user_type'],
            ];
        }

        // Нет привязки. Если у нас уже есть пользователь с тем же email — линкуем к нему.
        if ($email) {
            $byEmail = $pdo->prepare('SELECT id, username, full_name, balance, user_type FROM users WHERE email = ? LIMIT 1');
            $byEmail->execute([$email]);
            $existing = $byEmail->fetch();
        } else {
            $existing = false;
        }

        if (!$existing) {
            $username = $provider . '_' . substr($externalId, 0, 16);
            $emailEff = $email ?: ($provider . '+' . $externalId . '@forsage.ct.ws');
            $passhash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $type     = 'respected';
            $ins = $pdo->prepare(
                'INSERT INTO users (username, full_name, email, password, user_type, balance)
                 VALUES (?, ?, ?, ?, ?, 0)'
            );
            $ins->execute([
                $username,
                $displayName ?: $username,
                $emailEff,
                $passhash,
                $type,
            ]);
            $userId   = (int)$pdo->lastInsertId();
            $existing = [
                'id'        => $userId,
                'username'  => $username,
                'full_name' => $displayName ?: $username,
                'balance'   => 0.0,
                'user_type' => $type,
            ];
        }

        $link = $pdo->prepare(
            'INSERT INTO social_accounts (user_id, provider, external_id, email, display_name, avatar_url, raw_profile, last_login_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $link->execute([
            (int)$existing['id'],
            $provider,
            $externalId,
            $email,
            $displayName,
            $avatarUrl,
            json_encode($rawProfile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return [
            'id'        => (int)$existing['id'],
            'username'  => (string)$existing['username'],
            'full_name' => (string)$existing['full_name'],
            'balance'   => (float)$existing['balance'],
            'user_type' => (string)$existing['user_type'],
        ];
    }

    /** Безопасный return_to — только относительный путь /xxx без //. */
    public static function safeReturnTo(?string $returnTo, string $default = '/profile.php'): string
    {
        if (!$returnTo || !preg_match('#^/[^/]#', $returnTo)) return $default;
        return $returnTo;
    }
}
