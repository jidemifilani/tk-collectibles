<?php
/**
 * Store features added after the first release: extra columns and tables, delivery fees,
 * discount codes, email, admin activity log, 2-step login (TOTP), and scheduled drops.
 */

const SCHEMA_VERSION = 3;
const PRODUCT_TAGS = ['rare' => 'Rare find', 'vintage' => 'Vintage'];
const ERAS = ['70s', '80s', '90s', 'Y2K', '2010s', 'Modern'];

/** Adds new columns/tables once. PRAGMA user_version records what has already run. */
function migrate_schema(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version >= SCHEMA_VERSION) return;

    $add = function (string $table, string $col, string $def) use ($pdo) {
        $cols = array_column($pdo->query("PRAGMA table_info(\"$table\")")->fetchAll(), 'name');
        if (!in_array($col, $cols, true)) $pdo->exec("ALTER TABLE \"$table\" ADD COLUMN \"$col\" $def");
    };
    if ($version < 2) {
    foreach ([
        ['brand', "TEXT NOT NULL DEFAULT ''"], ['color', "TEXT NOT NULL DEFAULT ''"],
        ['measurements', "TEXT NOT NULL DEFAULT ''"], ['flaws', "TEXT NOT NULL DEFAULT ''"],
        ['publishAt', 'INTEGER'], ['announced', 'INTEGER NOT NULL DEFAULT 1'], ['soldAt', 'INTEGER'],
    ] as [$c, $d]) $add('Product', $c, $d);
    foreach ([
        ['deliveryFee', 'INTEGER NOT NULL DEFAULT 0'], ['discountAmount', 'INTEGER NOT NULL DEFAULT 0'],
        ['discountCode', 'TEXT'], ['paymentMethod', "TEXT NOT NULL DEFAULT 'paystack'"],
        ['fulfillment', "TEXT NOT NULL DEFAULT 'new'"],
    ] as [$c, $d]) $add('Order', $c, $d);
    foreach ([
        ['deliveryLagos', 'INTEGER NOT NULL DEFAULT 3000'], ['deliveryOther', 'INTEGER NOT NULL DEFAULT 6000'],
        ['freeDeliveryOver', 'INTEGER NOT NULL DEFAULT 0'], ['bankDetails', "TEXT NOT NULL DEFAULT ''"],
        ['payOnDelivery', 'INTEGER NOT NULL DEFAULT 0'],
    ] as [$c, $d]) $add('SiteSettings', $c, $d);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS "DiscountCode" (
  "code" TEXT PRIMARY KEY, "kind" TEXT NOT NULL, "value" INTEGER NOT NULL,
  "minOrder" INTEGER NOT NULL DEFAULT 0, "maxUses" INTEGER NOT NULL DEFAULT 0, "uses" INTEGER NOT NULL DEFAULT 0,
  "expiresAt" INTEGER, "active" INTEGER NOT NULL DEFAULT 1, "createdAt" INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS "AdminLog" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT, "action" TEXT NOT NULL, "detail" TEXT NOT NULL,
  "ip" TEXT NOT NULL, "createdAt" INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS "Subscriber" (
  "id" TEXT PRIMARY KEY, "email" TEXT NOT NULL, "category" TEXT NOT NULL DEFAULT '',
  "token" TEXT NOT NULL, "createdAt" INTEGER NOT NULL, UNIQUE("email", "category")
);
CREATE TABLE IF NOT EXISTS "Review" (
  "id" TEXT PRIMARY KEY, "name" TEXT NOT NULL, "rating" INTEGER NOT NULL, "body" TEXT NOT NULL,
  "photoUrl" TEXT NOT NULL DEFAULT '', "approved" INTEGER NOT NULL DEFAULT 0, "createdAt" INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS "Meta" ("key" TEXT PRIMARY KEY, "value" TEXT NOT NULL);
SQL);
    }

    if ($version < 3) {
        foreach ([
            ['fit', "TEXT NOT NULL DEFAULT ''"], ['fabric', "TEXT NOT NULL DEFAULT ''"], ['modelInfo', "TEXT NOT NULL DEFAULT ''"],
            ['era', "TEXT NOT NULL DEFAULT ''"], ['tags', "TEXT NOT NULL DEFAULT ''"], ['cost', 'INTEGER NOT NULL DEFAULT 0'],
        ] as [$c, $d]) $add('Product', $c, $d);
        foreach ([
            ['notes', "TEXT NOT NULL DEFAULT ''"], ['giftWrap', 'INTEGER NOT NULL DEFAULT 0'], ['giftWrapFee', 'INTEGER NOT NULL DEFAULT 0'],
            ['deliveryMethod', "TEXT NOT NULL DEFAULT 'delivery'"], ['reminderSent', 'INTEGER NOT NULL DEFAULT 0'],
        ] as [$c, $d]) $add('Order', $c, $d);
        foreach ([
            ['giftWrapPrice', 'INTEGER NOT NULL DEFAULT 1000'], ['pickupEnabled', 'INTEGER NOT NULL DEFAULT 0'],
            ['pickupAddress', "TEXT NOT NULL DEFAULT ''"], ['returnsPolicy', "TEXT NOT NULL DEFAULT ''"],
            ['termsText', "TEXT NOT NULL DEFAULT ''"], ['privacyText', "TEXT NOT NULL DEFAULT ''"],
            ['sizeGuide', "TEXT NOT NULL DEFAULT ''"], ['gaId', "TEXT NOT NULL DEFAULT ''"], ['metaPixelId', "TEXT NOT NULL DEFAULT ''"],
            ['offersEnabled', 'INTEGER NOT NULL DEFAULT 1'],
        ] as [$c, $d]) $add('SiteSettings', $c, $d);
        $add('DiscountCode', 'productId', 'TEXT');
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS "Offer" (
  "id" TEXT PRIMARY KEY, "productId" TEXT NOT NULL, "name" TEXT NOT NULL, "email" TEXT NOT NULL, "phone" TEXT NOT NULL,
  "amount" INTEGER NOT NULL, "status" TEXT NOT NULL DEFAULT 'new', "code" TEXT, "createdAt" INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS "SellRequest" (
  "id" TEXT PRIMARY KEY, "name" TEXT NOT NULL, "phone" TEXT NOT NULL, "email" TEXT NOT NULL DEFAULT '',
  "description" TEXT NOT NULL, "askingPrice" INTEGER NOT NULL DEFAULT 0, "photos" TEXT NOT NULL DEFAULT '[]',
  "status" TEXT NOT NULL DEFAULT 'new', "createdAt" INTEGER NOT NULL
);
SQL);
    }
    $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
}

function meta_get(string $key): ?string
{
    $st = db()->prepare('SELECT value FROM Meta WHERE key = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function meta_set(string $key, ?string $value): void
{
    if ($value === null) db()->prepare('DELETE FROM Meta WHERE key = ?')->execute([$key]);
    else db()->prepare('REPLACE INTO Meta (key, value) VALUES (?, ?)')->execute([$key, $value]);
}

// ---------- delivery + discounts ----------

/** Delivery fee in kobo for a state and item subtotal. */
function delivery_fee(string $state, int $subtotal): int
{
    $s = settings();
    $free = (int) $s['freeDeliveryOver'] * 100;
    if ($free > 0 && $subtotal >= $free) return 0;
    return (int) (strcasecmp($state, 'Lagos') === 0 ? $s['deliveryLagos'] : $s['deliveryOther']) * 100;
}

/** Returns [discountKobo, normalisedCode] or throws with a shopper-friendly message. */
function apply_discount(string $code, int $subtotal, array $productIds = []): array
{
    $code = strtoupper(trim($code));
    if ($code === '') return [0, null];
    $st = db()->prepare('SELECT * FROM DiscountCode WHERE code = ?');
    $st->execute([$code]);
    $d = $st->fetch();
    if (!$d || !$d['active']) throw new InvalidArgumentException('That discount code is not valid');
    if ($d['expiresAt'] && (int) $d['expiresAt'] < now_ms()) throw new InvalidArgumentException('That discount code has expired');
    if ($d['maxUses'] > 0 && $d['uses'] >= $d['maxUses']) throw new InvalidArgumentException('That discount code has been fully used');
    if (!empty($d['productId']) && !in_array($d['productId'], $productIds, true)) throw new InvalidArgumentException('This code only works with the item your offer was for');
    if ($subtotal < (int) $d['minOrder'] * 100) throw new InvalidArgumentException('This code needs a minimum order of ' . naira((int) $d['minOrder'] * 100));
    $amount = $d['kind'] === 'percent'
        ? (int) round($subtotal * min(100, (int) $d['value']) / 100)
        : (int) $d['value'] * 100;
    return [min($amount, $subtotal), $code];
}

function absolute_url(string $url): string
{
    return str_starts_with($url, '/') ? app_url() . $url : $url;
}

// ---------- email ----------

function mail_from(): string
{
    $host = parse_url(app_url() ?: 'https://localhost', PHP_URL_HOST) ?: 'localhost';
    return (string) config('MAIL_FROM', 'no-reply@' . $host);
}

/** Sends a simple branded HTML email. Returns false (and logs) on failure instead of throwing. */
function send_mail(string $to, string $subject, string $bodyHtml): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $s = settings();
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#2C2825">'
        . '<h2 style="color:' . e($s['colorTerracotta']) . '">' . e($s['storeName']) . '</h2>' . $bodyHtml
        . '<p style="margin-top:32px;font-size:12px;color:#6B6560">' . e($s['storeName']) . ' · ' . e($s['customerCarePhone']) . ' · <a href="' . e(app_url()) . '">' . e(app_url()) . '</a></p></div>';
    $headers = [
        'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($s['storeName']) . ' <' . mail_from() . '>',
    ];
    if (filter_var($s['contactEmail'], FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $s['contactEmail'];
    $ok = @mail($to, mb_encode_mimeheader($subject), $html, implode("\r\n", $headers));
    if (!$ok) log_error('Email failed', ['to' => $to, 'subject' => $subject]);
    return $ok;
}

function order_summary_html(array $o): string
{
    $rows = '';
    foreach ($o['items'] as $i) {
        $rows .= '<tr><td style="padding:4px 0">' . e($i['product']['name']) . ' × ' . (int) $i['quantity'] . '</td><td style="text-align:right">' . naira($i['price'] * $i['quantity']) . '</td></tr>';
    }
    if ((int) $o['deliveryFee'] > 0) $rows .= '<tr><td>Delivery</td><td style="text-align:right">' . naira((int) $o['deliveryFee']) . '</td></tr>';
    if ((int) $o['discountAmount'] > 0) $rows .= '<tr><td>Discount (' . e($o['discountCode']) . ')</td><td style="text-align:right">−' . naira((int) $o['discountAmount']) . '</td></tr>';
    $rows .= '<tr><td style="font-weight:bold;padding-top:8px">Total</td><td style="text-align:right;font-weight:bold">' . naira((int) $o['totalAmount']) . '</td></tr>';
    return '<p>Order reference: <strong>' . e($o['reference']) . '</strong></p><table style="width:100%;border-collapse:collapse">' . $rows . '</table>'
        . '<p>Track your order any time: <a href="' . e(app_url() . '/track?ref=' . urlencode($o['reference'])) . '">' . e(app_url()) . '/track</a></p>';
}

const PAYMENT_LABELS = ['paystack' => 'Paystack (card, bank, USSD)', 'transfer' => 'Bank transfer', 'pod' => 'Pay on delivery'];
const FULFILLMENT_LABELS = ['new' => 'Order received', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];

/** Emails the shopper and the store owner about a new or updated order. */
function notify_order(array $o, string $event): void
{
    $s = settings();
    $name = e($o['customerName']);
    $method = $o['paymentMethod'] ?? 'paystack';
    if ($event === 'placed') {
        telegram_alert(order_alert_text($o));
        $intro = match (true) {
            $o['status'] === 'paid' => "<p>Hi $name, thank you! Your payment was successful and we're packing your thrift finds.</p>",
            $method === 'transfer' => "<p>Hi $name, thanks for your order! Please transfer <strong>" . naira((int) $o['totalAmount']) . '</strong> to:</p><p style="white-space:pre-line;background:#F6EDE8;padding:12px">' . e($s['bankDetails']) . '</p><p>Use your order reference as the narration. Your items are held for you for 48 hours.</p>',
            default => "<p>Hi $name, thanks for your order! You'll pay <strong>" . naira((int) $o['totalAmount']) . '</strong> when it is delivered.</p>',
        };
        send_mail($o['customerEmail'], 'Your ' . $s['storeName'] . ' order ' . $o['reference'], $intro . order_summary_html($o));
        if (filter_var($s['contactEmail'], FILTER_VALIDATE_EMAIL)) {
            send_mail($s['contactEmail'], 'New order ' . $o['reference'] . ' — ' . naira((int) $o['totalAmount']),
                '<p>New order from ' . $name . ' (' . e($o['customerPhone']) . ', ' . e($o['customerEmail']) . ').</p><p>Payment: ' . e(PAYMENT_LABELS[$method] ?? $method) . ' · Status: ' . e($o['status']) . '</p><p>'
                . e($o['shippingAddress']) . ', ' . e($o['city']) . ', ' . e($o['state']) . '</p>' . order_summary_html($o));
        }
    } else {
        $label = FULFILLMENT_LABELS[$o['fulfillment']] ?? $o['status'];
        send_mail($o['customerEmail'], 'Order ' . $o['reference'] . ': ' . $label,
            "<p>Hi $name, your order is now: <strong>" . e($label) . '</strong>.</p>' . order_summary_html($o));
    }
}

// ---------- subscribers (email alerts for new drops) ----------

function email_subscribers_about(array $product): void
{
    $st = db()->prepare("SELECT email, token, category FROM Subscriber WHERE category = '' OR category = ?");
    $st->execute([$product['category']]);
    $sent = [];
    foreach ($st->fetchAll() as $sub) {
        if (isset($sent[$sub['email']])) continue;
        $sent[$sub['email']] = true;
        $url = app_url() . '/product/' . rawurlencode($product['slug']);
        send_mail($sub['email'], 'New arrival: ' . $product['name'],
            '<p><img src="' . e(absolute_url($product['imageUrl'])) . '" alt="" style="max-width:100%;border-radius:12px"></p>'
            . '<p><strong>' . e($product['name']) . '</strong> — ' . naira((int) $product['salePrice']) . ' · Size ' . e($product['size']) . '</p>'
            . '<p><a href="' . e($url) . '" style="background:#7A1F2B;color:#fff;padding:10px 18px;border-radius:999px;text-decoration:none">Shop it before it\'s gone</a></p>'
            . '<p style="font-size:12px"><a href="' . e(app_url() . '/unsubscribe?t=' . $sub['token']) . '">Unsubscribe</a></p>');
    }
}

/** Phone push + email for a product that just went live. */
function announce_product(array $product): void
{
    require_once __DIR__ . '/push.php';
    try {
        notify_all_subscribers([
            'title' => 'New arrival ✨',
            'body' => $product['name'] . ' — ' . naira((int) $product['salePrice']) . ' · Size ' . $product['size'],
            'url' => '/product/' . $product['slug'],
            'image' => absolute_url($product['imageUrl']),
        ]);
    } catch (Throwable $ex) {
        log_error('New-arrival push failed', $ex->getMessage());
    }
    try {
        email_subscribers_about($product);
    } catch (Throwable $ex) {
        log_error('New-arrival email failed', $ex->getMessage());
    }
}

/**
 * Scheduled drops: when a product's go-live time passes, the next page view announces it.
 * Claiming the row first (announced = 1) stops two visitors sending the alert twice.
 */
function announce_due_drops(): void
{
    $due = fetch_products('announced = 0 AND inStock = 1 AND publishAt IS NOT NULL AND publishAt <= ?', [now_ms()], 'LIMIT 20');
    foreach ($due as $p) {
        $claim = db()->prepare('UPDATE Product SET announced = 1 WHERE id = ? AND announced = 0');
        $claim->execute([$p['id']]);
        if ($claim->rowCount()) announce_product($p);
    }
}

function has_due_drops(): bool
{
    $st = db()->prepare('SELECT 1 FROM Product WHERE announced = 0 AND publishAt IS NOT NULL AND publishAt <= ? LIMIT 1');
    $st->execute([now_ms()]);
    return (bool) $st->fetchColumn();
}

/** Earliest scheduled drop still in the future, or null. */
function next_drop_at(): ?int
{
    $st = db()->prepare('SELECT MIN(publishAt) FROM Product WHERE inStock = 1 AND publishAt > ?');
    $st->execute([now_ms()]);
    $v = $st->fetchColumn();
    return $v ? (int) $v : null;
}

// ---------- admin activity log ----------

function admin_log(string $action, string $detail = ''): void
{
    try {
        db()->prepare('INSERT INTO AdminLog (action, detail, ip, createdAt) VALUES (?,?,?,?)')
            ->execute([$action, mb_substr($detail, 0, 500), client_ip(), now_ms()]);
    } catch (Throwable $ex) {
        log_error('Admin log failed', $ex->getMessage());
    }
}

// ---------- 2-step login (TOTP, works with Google Authenticator / Authy) ----------

function base32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32))) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_code(string $secret, int $step): string
{
    $hash = hash_hmac('sha1', pack('N*', 0, $step), base32_decode($secret), true);
    $o = ord($hash[19]) & 0xf;
    $n = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Accepts the current code or one 30-second step either side (phone clock drift). */
function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $step = intdiv(time(), 30);
    foreach ([-1, 0, 1] as $d) if (hash_equals(totp_code($secret, $step + $d), $code)) return true;
    return false;
}

/** Admin URL prefix. Set ADMIN_PATH in config.php (e.g. '/tk-manage') to hide /admin. */
function admin_base(): string
{
    $p = '/' . trim((string) config('ADMIN_PATH', '/admin'), '/');
    return $p === '/' ? '/admin' : $p;
}

// ---------- Telegram alerts to the owner (optional: TELEGRAM_BOT_TOKEN + TELEGRAM_CHAT_ID in config.php) ----------

function telegram_alert(string $text): void
{
    $token = (string) config('TELEGRAM_BOT_TOKEN', '');
    $chat = (string) config('TELEGRAM_CHAT_ID', '');
    if ($token === '' || $chat === '' || !function_exists('curl_init')) return;
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['chat_id' => $chat, 'text' => mb_substr($text, 0, 3500), 'disable_web_page_preview' => 'true']]);
    if (curl_exec($ch) === false) log_error('Telegram alert failed', curl_error($ch));
    curl_close($ch);
}

function order_alert_text(array $o): string
{
    $lines = array_map(fn($i) => '• ' . $i['product']['name'] . ' × ' . $i['quantity'], $o['items']);
    return '🛍 New order ' . $o['reference'] . "\n" . $o['customerName'] . ' · ' . $o['customerPhone'] . "\n"
        . implode("\n", $lines) . "\nTotal: " . naira((int) $o['totalAmount']) . ' · ' . (PAYMENT_LABELS[$o['paymentMethod']] ?? $o['paymentMethod'])
        . ' · ' . ($o['deliveryMethod'] === 'pickup' ? 'Pickup' : $o['city'] . ', ' . $o['state'])
        . ($o['notes'] !== '' ? "\nNote: " . $o['notes'] : '') . ($o['giftWrap'] ? "\n🎁 Gift wrap" : '');
}

// ---------- background jobs, run from page views (no cron needed) ----------

/** Runs at most every 10 minutes: expires unpaid transfers, sends cart reminders, sends the daily summary. */
function run_maintenance(): void
{
    $last = (int) (meta_get('maint_at') ?? 0);
    if (time() - $last < 600) return;
    meta_set('maint_at', (string) time());
    require_once __DIR__ . '/api.php';
    $pdo = db();
    $now = now_ms();

    // 1. Bank transfers unpaid after 48 hours: cancel and put the pieces back on sale.
    $st = $pdo->prepare("SELECT id FROM \"Order\" WHERE status = 'awaiting' AND paymentMethod = 'transfer' AND createdAt < ?");
    $st->execute([$now - 48 * 3600 * 1000]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $o = order_with_items('id = ?', [$id]);
        $pdo->prepare("UPDATE \"Order\" SET status = 'cancelled', updatedAt = ? WHERE id = ? AND status = 'awaiting'")->execute([$now, $id]);
        $ids = array_column($o['items'], 'productId');
        if ($ids) $pdo->prepare('UPDATE Product SET reservedUntil = NULL WHERE inStock = 1 AND id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ')')->execute($ids);
        admin_log('order_auto_cancel', $o['reference'] . ' (no transfer after 48h)');
        send_mail($o['customerEmail'], 'Your order ' . $o['reference'] . ' was cancelled',
            '<p>Hi ' . e($o['customerName']) . ', we didn\'t receive your bank transfer within 48 hours, so your order has been cancelled and the pieces released. If you already paid, reply to this email with your receipt.</p>');
    }

    // 2. Abandoned checkout: Paystack started but never paid, 1–24 hours ago. One reminder per order.
    $st = $pdo->prepare("SELECT id FROM \"Order\" WHERE status IN ('pending', 'failed') AND paymentMethod = 'paystack' AND reminderSent = 0 AND createdAt BETWEEN ? AND ?");
    $st->execute([$now - 24 * 3600 * 1000, $now - 3600 * 1000]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $o = order_with_items('id = ?', [$id]);
        $pdo->prepare('UPDATE "Order" SET reminderSent = 1 WHERE id = ?')->execute([$id]);
        // Skip shoppers who came back and paid on a newer order.
        $paid = $pdo->prepare("SELECT 1 FROM \"Order\" WHERE customerEmail = ? AND status = 'paid' AND createdAt > ?");
        $paid->execute([$o['customerEmail'], (int) $o['createdAt']]);
        if ($paid->fetchColumn()) continue;
        $links = '';
        foreach ($o['items'] as $i) {
            $p = fetch_products('id = ?', [$i['productId']], '');
            if ($p && product_availability($p[0]) === 'available') {
                $links .= '<li><a href="' . e(app_url() . '/product/' . rawurlencode($p[0]['slug'])) . '">' . e($p[0]['name']) . '</a> — ' . naira((int) $p[0]['salePrice']) . '</li>';
            }
        }
        if ($links === '') continue;
        send_mail($o['customerEmail'], 'Still thinking about it?',
            '<p>Hi ' . e($o['customerName']) . ', you didn\'t finish checking out. These one-of-one pieces are still available — but not for long:</p><ul>' . $links . '</ul>');
    }

    // 3. Daily summary to the owner, once a day after 8am Lagos time.
    $today = date('Y-m-d');
    $s = settings();
    if ((int) date('G') >= 8 && meta_get('summary_day') !== $today) {
        meta_set('summary_day', $today);
        $since = strtotime('yesterday') * 1000;
        $until = strtotime('today') * 1000;
        $q = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(totalAmount), 0) FROM \"Order\" WHERE status = 'paid' AND createdAt BETWEEN ? AND ?");
        $q->execute([$since, $until]);
        [$n, $rev] = $q->fetch(PDO::FETCH_NUM);
        $awaiting = (int) $pdo->query("SELECT COUNT(*) FROM \"Order\" WHERE status = 'awaiting'")->fetchColumn();
        $toShip = (int) $pdo->query("SELECT COUNT(*) FROM \"Order\" WHERE status = 'paid' AND fulfillment IN ('new', 'packed')")->fetchColumn();
        $offers = (int) $pdo->query("SELECT COUNT(*) FROM Offer WHERE status = 'new'")->fetchColumn();
        $sell = (int) $pdo->query("SELECT COUNT(*) FROM SellRequest WHERE status = 'new'")->fetchColumn();
        $text = 'Yesterday: ' . (int) $n . ' paid orders, ' . naira((int) $rev) . ".\nTo ship: $toShip · Awaiting payment: $awaiting · New offers: $offers · Sell requests: $sell";
        if (filter_var($s['contactEmail'], FILTER_VALIDATE_EMAIL)) send_mail($s['contactEmail'], 'Daily summary — ' . date('j M'), '<p>' . nl2br(e($text)) . '</p><p><a href="' . e(app_url() . admin_base()) . '">Open admin</a></p>');
        telegram_alert("📊 " . $text);
    }
}

// ---------- default policy texts (shown until the owner edits them in Settings) ----------

const DEFAULT_POLICIES = [
    'returnsPolicy' => "Every piece is one-of-one and pre-loved, photographed honestly with any flaws shown.\n\n• If an item arrives significantly different from its description, message us within 48 hours of delivery with photos. We'll offer an exchange, store credit or refund.\n• Items must be unworn, unwashed and returned in the same condition.\n• Change-of-mind returns aren't accepted on sale items.\n• Refunds are made to your original payment method within 7 working days of us receiving the item.\n• Return delivery costs are covered by us only when the item was not as described.",
    'termsText' => "By ordering from us you agree to these terms.\n\n• Prices are in Nigerian Naira and include VAT where applicable.\n• Items are reserved only once checkout starts, and released if payment isn't completed.\n• Bank transfer orders are held for 48 hours.\n• Delivery times are estimates; we'll keep you updated by email and WhatsApp.\n• We may cancel an order and refund you in full if an item is unavailable.",
    'privacyText' => "We collect only what we need to deliver your order: your name, email, phone and address.\n\n• Payments are processed by Paystack; we never see or store your card details.\n• We use your contact details to update you about your order and, if you sign up, about new drops.\n• We never sell your data. You can unsubscribe from emails at any time.\n• To have your data deleted, contact us.",
    'sizeGuide' => "Measurements are taken with the garment laid flat. Double the chest/waist figure for the full circumference.\n\nSize | Chest (cm) | Waist (cm) | Hips (cm)\nXS | 80–84 | 62–66 | 86–90\nS | 85–89 | 67–71 | 91–95\nM | 90–95 | 72–77 | 96–101\nL | 96–102 | 78–84 | 102–108\nXL | 103–110 | 85–92 | 109–116\n\nVintage sizes often run smaller than modern ones — check each item's measurements and fit notes.",
];

function policy_text(string $key): string
{
    $v = trim((string) (settings()[$key] ?? ''));
    return $v !== '' ? $v : DEFAULT_POLICIES[$key];
}
