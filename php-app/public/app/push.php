<?php
/**
 * Web Push sender (RFC 8291 aes128gcm encryption + RFC 8292 VAPID), using only OpenSSL.
 * Uses the same VAPID keys as the original app, so existing subscriptions keep working.
 */

function b64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_decode(string $s): string
{
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/** Wraps a raw 65-byte P-256 public point as a PEM public key. */
function p256_public_pem(string $point): string
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Builds a PEM private key from the raw 32-byte VAPID private key and its public point. */
function p256_private_pem(string $d, string $point): string
{
    $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $point;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

/** OpenSSL returns DER-encoded ECDSA signatures; JWT ES256 needs raw r||s. */
function der_to_raw_signature(string $der): string
{
    $offset = 3 + (ord($der[1]) & 0x80 ? 1 : 0);
    $rLen = ord($der[$offset]);
    $r = substr($der, $offset + 1, $rLen);
    $offset += 1 + $rLen + 1;
    $sLen = ord($der[$offset]);
    $s = substr($der, $offset + 1, $sLen);
    $fix = fn($x) => str_pad(ltrim($x, "\0"), 32, "\0", STR_PAD_LEFT);
    return $fix($r) . $fix($s);
}

function vapid_authorization(string $endpoint): string
{
    $pub = b64url_decode((string) config('VAPID_PUBLIC_KEY'));
    $priv = b64url_decode((string) config('VAPID_PRIVATE_KEY'));
    $parts = parse_url($endpoint);
    $aud = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $header = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = b64url_encode(json_encode([
        'aud' => $aud,
        'exp' => time() + 12 * 3600,
        'sub' => config('VAPID_SUBJECT') ?: 'mailto:hello@example.com',
    ], JSON_UNESCAPED_SLASHES));
    $input = "$header.$claims";
    if (!openssl_sign($input, $sig, p256_private_pem($priv, $pub), OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('VAPID signing failed');
    }
    return 'vapid t=' . $input . '.' . b64url_encode(der_to_raw_signature($sig)) . ', k=' . b64url_encode($pub);
}

function hkdf_expand_one(string $prk, string $info, int $len): string
{
    return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $len);
}

/** Encrypts a payload for one subscription (RFC 8291). Returns the request body. */
function webpush_encrypt(string $payload, string $p256dh, string $authSecret): string
{
    $uaPublic = b64url_decode($p256dh);
    $auth = b64url_decode($authSecret);

    $local = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $details = openssl_pkey_get_details($local);
    $asPublic = "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $shared = openssl_pkey_derive(openssl_pkey_get_public(p256_public_pem($uaPublic)), $local, 32);
    if ($shared === false) throw new RuntimeException('ECDH failed');

    $prkKey = hash_hmac('sha256', $shared, $auth, true);
    $ikm = hkdf_expand_one($prkKey, "WebPush: info\0" . $uaPublic . $asPublic, 32);
    $salt = random_bytes(16);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = hkdf_expand_one($prk, "Content-Encoding: aes128gcm\0", 16);
    $nonce = hkdf_expand_one($prk, "Content-Encoding: nonce\0", 12);

    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(strlen($asPublic)) . $asPublic . $cipher . $tag;
}

/** Sends to every subscriber in parallel; deletes subscriptions the browser has dropped. */
function notify_all_subscribers(array $payload): void
{
    if (!config('VAPID_PUBLIC_KEY') || !config('VAPID_PRIVATE_KEY')) return;
    $subs = db()->query('SELECT * FROM PushSubscription')->fetchAll();
    if (!$subs) return;

    $message = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $multi = curl_multi_init();
    $handles = [];
    foreach ($subs as $s) {
        try {
            $body = webpush_encrypt($message, $s['p256dh'], $s['auth']);
            $ch = curl_init($s['endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/octet-stream',
                    'Content-Encoding: aes128gcm',
                    'TTL: 2419200',
                    'Urgency: normal',
                    'Authorization: ' . vapid_authorization($s['endpoint']),
                ],
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[] = [$ch, $s['id']];
        } catch (Throwable $ex) {
            log_error('Push encrypt failed', $ex->getMessage());
        }
    }
    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) curl_multi_select($multi, 1.0);
    } while ($running && $status === CURLM_OK);

    $expired = [];
    foreach ($handles as [$ch, $id]) {
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code === 404 || $code === 410) $expired[] = $id;
        elseif ($code >= 400 || $code === 0) log_error('Push send failed', ['status' => $code, 'body' => substr((string) curl_multi_getcontent($ch), 0, 200)]);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);
    if ($expired) {
        db()->prepare('DELETE FROM PushSubscription WHERE id IN (' . rtrim(str_repeat('?,', count($expired)), ',') . ')')->execute($expired);
    }
}
