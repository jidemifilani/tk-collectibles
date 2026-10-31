<?php
/** JSON API used by assets/app.js and assets/admin.js. Same contract as the original Next.js routes. */

require_once __DIR__ . '/push.php';
require_once __DIR__ . '/ai.php';

// ---------- validation ----------

function str_field(array $d, string $key, int $max, bool $required = true, string $label = ''): string
{
    $v = trim((string) ($d[$key] ?? ''));
    if ($required && $v === '') throw new InvalidArgumentException(($label ?: ucfirst($key)) . ' is required');
    if (mb_strlen($v) > $max) throw new InvalidArgumentException(($label ?: ucfirst($key)) . " must be $max characters or fewer");
    return $v;
}

function validate_product(?array $d): array
{
    if (!$d) throw new InvalidArgumentException('Invalid request');
    $price = function ($v, string $label): float {
        if (!is_numeric($v) || $v <= 0) throw new InvalidArgumentException("$label must be greater than zero");
        if ($v > MAX_ITEM_PRICE_NAIRA) throw new InvalidArgumentException("$label must be " . number_format(MAX_ITEM_PRICE_NAIRA) . ' or less');
        return (float) $v;
    };
    $category = (string) ($d['category'] ?? '');
    if ($category === 'all' || !isset(CATEGORIES[$category])) throw new InvalidArgumentException('Invalid category');
    $condition = (string) ($d['condition'] ?? '');
    if (!in_array($condition, CONDITIONS, true)) throw new InvalidArgumentException('Invalid condition');
    $images = $d['images'] ?? [];
    if (!is_array($images)) $images = [];
    $images = array_values(array_filter(array_map(fn($x) => trim((string) $x), $images)));
    if (count($images) > 6) throw new InvalidArgumentException('Up to 6 extra photos');

    return [
        'name' => str_field($d, 'name', 200),
        'slug' => str_field($d, 'slug', 200, false),
        'description' => str_field($d, 'description', 4000, false),
        'originalPrice' => (int) round($price($d['originalPriceNaira'] ?? null, 'Price') * 100),
        'salePrice' => (int) round($price($d['salePriceNaira'] ?? null, 'Price') * 100),
        'category' => $category,
        'size' => str_field($d, 'size', 40),
        'condition' => $condition,
        'imageUrl' => str_field($d, 'imageUrl', 2000, true, 'Image'),
        'images' => json_encode($images, JSON_UNESCAPED_SLASHES),
        'featured' => !empty($d['featured']) ? 1 : 0,
        'inStock' => array_key_exists('inStock', $d) ? (!empty($d['inStock']) ? 1 : 0) : 1,
        'brand' => str_field($d, 'brand', 80, false),
        'color' => str_field($d, 'color', 40, false),
        'measurements' => str_field($d, 'measurements', 500, false),
        'flaws' => str_field($d, 'flaws', 1000, false),
        'publishAt' => parse_publish_at($d['publishAt'] ?? null),
        'fit' => str_field($d, 'fit', 200, false),
        'fabric' => str_field($d, 'fabric', 200, false),
        'modelInfo' => str_field($d, 'modelInfo', 200, false),
        'era' => str_field($d, 'era', 40, false),
        'tags' => implode(',', array_values(array_intersect(array_map('trim', explode(',', strtolower((string) ($d['tags'] ?? '')))), array_keys(PRODUCT_TAGS)))),
        'cost' => max(0, min(MAX_ITEM_PRICE_NAIRA, (int) ($d['costNaira'] ?? 0))) * 100,
        'videoUrl' => (function ($v) { $v = trim((string) $v); if ($v !== '' && !preg_match('#^(/uploads/video/[\w.-]+|https://\S+\.(mp4|webm))$#i', $v)) throw new InvalidArgumentException('Video must be an uploaded clip or an https link to an .mp4 file'); return $v; })($d['videoUrl'] ?? ''),
        'source' => str_field($d, 'source', 80, false, 'Source'),
    ];
}

/** "2026-11-07T19:00" from a datetime-local input (Lagos time) -> ms, or null for "now". */
function parse_publish_at($v): ?int
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $ts = strtotime($v);
    if ($ts === false) throw new InvalidArgumentException('Drop time is not a valid date');
    return $ts * 1000 > now_ms() ? $ts * 1000 : null;
}

function unique_slug(string $base, ?string $exceptId = null): string
{
    $slug = slugify($base) ?: 'item';
    $st = db()->prepare('SELECT id FROM Product WHERE slug = ?' . ($exceptId ? ' AND id != ?' : ''));
    $st->execute($exceptId ? [$slug, $exceptId] : [$slug]);
    return $st->fetch() ? $slug . '-' . base_convert((string) now_ms(), 10, 36) : $slug;
}

// ---------- router ----------

function handle_api(string $path, string $method): void
{
    try {
        $routes = [
            ['GET', '#^/api/settings$#', 'api_settings'],
            ['GET', '#^/api/products$#', 'api_products'],
            ['GET', '#^/api/products/([^/]+)$#', 'api_product_by_slug'],
            ['POST', '#^/api/cart/check$#', 'api_cart_check'],
            ['POST', '#^/api/chat$#', 'api_chat'],
            ['POST', '#^/api/paystack/initialize$#', 'api_paystack_initialize'],
            ['GET', '#^/api/paystack/verify$#', 'api_paystack_verify'],
            ['POST', '#^/api/paystack/webhook$#', 'api_paystack_webhook'],
            ['POST', '#^/api/push/subscribe$#', 'api_push_subscribe'],
            ['DELETE', '#^/api/push/subscribe$#', 'api_push_unsubscribe'],
            ['POST', '#^/api/admin/login$#', 'api_admin_login'],
            ['POST', '#^/api/admin/logout$#', 'api_admin_logout'],
            ['GET', '#^/api/admin/products$#', 'api_admin_products'],
            ['POST', '#^/api/admin/products$#', 'api_admin_create_product'],
            ['GET', '#^/api/admin/products/([^/]+)$#', 'api_admin_get_product'],
            ['PUT', '#^/api/admin/products/([^/]+)$#', 'api_admin_update_product'],
            ['DELETE', '#^/api/admin/products/([^/]+)$#', 'api_admin_delete_product'],
            ['GET', '#^/api/admin/orders$#', 'api_admin_orders'],
            ['GET', '#^/api/admin/settings$#', 'api_settings_admin'],
            ['PUT', '#^/api/admin/settings$#', 'api_admin_update_settings'],
            ['POST', '#^/api/admin/upload$#', 'api_admin_upload'],
            ['GET', '#^/api/health$#', 'api_health'],
            ['POST', '#^/api/checkout/quote$#', 'api_checkout_quote'],
            ['POST', '#^/api/order/place$#', 'api_order_place'],
            ['GET', '#^/api/track$#', 'api_track'],
            ['POST', '#^/api/subscribe$#', 'api_subscribe'],
            ['POST', '#^/api/reviews$#', 'api_review_submit'],
            ['POST', '#^/api/admin/bulk-products$#', 'api_admin_bulk_create'],
            ['PUT', '#^/api/admin/orders/([^/]+)$#', 'api_admin_update_order'],
            ['GET', '#^/api/admin/export/orders$#', 'api_admin_export_orders'],
            ['GET', '#^/api/admin/backup$#', 'api_admin_backup'],
            ['POST', '#^/api/admin/discounts$#', 'api_admin_save_discount'],
            ['DELETE', '#^/api/admin/discounts/([^/]+)$#', 'api_admin_delete_discount'],
            ['PUT', '#^/api/admin/reviews/([^/]+)$#', 'api_admin_update_review'],
            ['DELETE', '#^/api/admin/reviews/([^/]+)$#', 'api_admin_delete_review'],
            ['POST', '#^/api/admin/2fa/start$#', 'api_admin_2fa_start'],
            ['POST', '#^/api/offers$#', 'api_offer_submit'],
            ['POST', '#^/api/price-watch$#', 'api_price_watch'],
            ['POST', '#^/api/admin/posts$#', 'api_admin_save_post'],
            ['PUT', '#^/api/admin/posts/([^/]+)$#', 'api_admin_update_post'],
            ['DELETE', '#^/api/admin/posts/([^/]+)$#', 'api_admin_delete_post'],
            ['POST', '#^/api/admin/outfits$#', 'api_admin_save_outfit'],
            ['DELETE', '#^/api/admin/outfits/([^/]+)$#', 'api_admin_delete_outfit'],
            ['POST', '#^/api/admin/staff$#', 'api_admin_add_staff'],
            ['DELETE', '#^/api/admin/staff/([^/]+)$#', 'api_admin_delete_staff'],
            ['POST', '#^/api/admin/reviews/([^/]+)/feature$#', 'api_admin_feature_review'],
            ['POST', '#^/api/admin/upload-video$#', 'api_admin_upload_video'],
            ['POST', '#^/api/sell$#', 'api_sell_submit'],
            ['PUT', '#^/api/admin/offers/([^/]+)$#', 'api_admin_offer_decide'],
            ['PUT', '#^/api/admin/sell/([^/]+)$#', 'api_admin_sell_status'],
            ['POST', '#^/api/admin/price-drop$#', 'api_admin_price_drop'],
            ['POST', '#^/api/admin/2fa/enable$#', 'api_admin_2fa_enable'],
            ['POST', '#^/api/admin/2fa/disable$#', 'api_admin_2fa_disable'],
        ];
        foreach ($routes as [$m, $re, $fn]) {
            if ($m === $method && preg_match($re, $path, $mm)) {
                $fn(...array_map('urldecode', array_slice($mm, 1)));
                return;
            }
        }
        json_out(['error' => 'Not found'], 404);
    } catch (InvalidArgumentException $ex) {
        json_out(['error' => $ex->getMessage()], 400);
    } catch (Throwable $ex) {
        log_error('API error ' . $path, $ex->getMessage());
        json_out(['error' => 'Something went wrong. Please try again.'], 500);
    }
}

// ---------- public ----------

function api_settings(): void
{
    json_out(settings());
}

function api_products(): void
{
    if (isset($_GET['ids'])) {
        $ids = array_slice(array_values(array_filter(explode(',', (string) $_GET['ids']))), 0, 50);
        if (!$ids) json_out(['products' => []]);
        $rows = fetch_products('id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ') AND ' . published_sql(), $ids, '');
        json_out(['products' => array_map('public_product_json', $rows)]);
    }
    $pageSize = min(100, max(1, (int) ($_GET['pageSize'] ?? 24)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $where = available_sql();
    $params = [];
    $cat = $_GET['category'] ?? '';
    if ($cat && $cat !== 'all') { $where .= ' AND category = ?'; $params[] = $cat; }
    if (($_GET['featured'] ?? '') === 'true') $where .= ' AND featured = 1';
    $products = fetch_products($where, $params, "ORDER BY createdAt DESC LIMIT $pageSize OFFSET " . (($page - 1) * $pageSize));
    json_out(['products' => array_map('public_product_json', $products), 'total' => count_products($where, $params), 'page' => $page, 'pageSize' => $pageSize]);
}

function api_product_by_slug(string $slug): void
{
    $st = db()->prepare('SELECT * FROM Product WHERE slug = ? AND ' . published_sql());
    $st->execute([$slug]);
    $p = $st->fetch();
    $p ? json_out(public_product_json($p)) : json_out(['error' => 'Product not found'], 404);
}

/** Shopper-safe product data: hides what you paid and where it came from. */
function public_product_json(array $p): array
{
    $j = product_json($p);
    unset($j['cost'], $j['source']);
    $j['availability'] = product_availability($p);
    $j['price'] = effective_price($p);
    return $j;
}

function api_cart_check(): void
{
    $body = json_body();
    $ids = array_slice(array_filter((array) ($body['productIds'] ?? []), 'is_string'), 0, 50);
    if (!$ids) json_out(['items' => []]);
    $st = db()->prepare('SELECT * FROM Product WHERE id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ')');
    $st->execute($ids);
    $found = [];
    foreach ($st->fetchAll() as $p) $found[$p['id']] = $p;
    $items = array_map(fn($id) => [
        'id' => $id,
        'available' => isset($found[$id]) && product_availability($found[$id]) === 'available',
        'salePrice' => isset($found[$id]) ? effective_price($found[$id]) : null,
    ], $ids);
    json_out(['items' => $items]);
}

function api_chat(): void
{
    [$ok, $retry] = rate_limit('chat:' . client_ip(), 20, 60);
    if (!$ok) json_out(['error' => "You're sending messages a bit fast — please wait a moment."], 429, ['Retry-After' => (string) $retry]);

    $incoming = (array) (json_body()['messages'] ?? []);
    $messages = [];
    foreach ($incoming as $m) {
        if (is_array($m) && in_array($m['role'] ?? '', ['user', 'assistant'], true) && is_string($m['content'] ?? null)) {
            $messages[] = ['role' => $m['role'], 'content' => mb_substr($m['content'], 0, 1000)];
        }
    }
    $messages = array_slice($messages, -16);
    $lastUser = null;
    foreach (array_reverse($messages) as $m) if ($m['role'] === 'user') { $lastUser = $m; break; }
    if (!$lastUser) json_out(['error' => 'No question provided'], 400);

    $s = settings();
    $products = fetch_products('1', [], 'ORDER BY createdAt DESC LIMIT 60');
    $reply = null;
    try {
        $reply = ai_reply(ai_system_prompt($s, $products), $messages);
    } catch (Throwable $ex) {
        log_error('AI provider error, using fallback', $ex->getMessage());
    }
    json_out(['reply' => $reply ?: ai_fallback_reply($lastUser['content'], $s, $products)]);
}

// ---------- Paystack ----------

function paystack_request(string $method, string $path, ?array $payload = null): array
{
    $key = config('PAYSTACK_SECRET_KEY');
    if (!$key) throw new RuntimeException('Payments are not configured yet');
    $ch = curl_init('https://api.paystack.co' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    if ($status >= 400 || !is_array($data) || empty($data['status'])) {
        throw new RuntimeException($data['message'] ?? 'Payment provider error');
    }
    return $data;
}

function release_reservations(array $productIds): void
{
    if (!$productIds) return;
    db()->prepare('UPDATE Product SET reservedUntil = NULL WHERE id IN (' . rtrim(str_repeat('?,', count($productIds)), ',') . ')')
        ->execute(array_values($productIds));
}

function order_item_ids(string $orderId): array
{
    $st = db()->prepare('SELECT productId FROM OrderItem WHERE orderId = ?');
    $st->execute([$orderId]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/** Validates the checkout form and cart. Shared by Paystack and the offline payment methods. */
function read_checkout(?array $d): array
{
    if (!$d) throw new InvalidArgumentException('Invalid request');
    $email = strtolower(trim((string) ($d['customerEmail'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email');
    $phone = preg_replace('/[\s\-()]/', '', (string) ($d['customerPhone'] ?? ''));
    if (!preg_match('/^(\+?234|0)[789][01]\d{8}$/', $phone)) throw new InvalidArgumentException('Enter a valid Nigerian phone number');
    $items = [];
    foreach ((array) ($d['items'] ?? []) as $i) {
        $qty = (int) ($i['quantity'] ?? 0);
        if (!is_string($i['productId'] ?? null) || $qty < 1 || $qty > 20) throw new InvalidArgumentException('Invalid cart item');
        $items[] = ['productId' => $i['productId'], 'quantity' => $qty, 'size' => mb_substr(trim((string) ($i['size'] ?? '')), 0, 40) ?: '-'];
    }
    if (!$items) throw new InvalidArgumentException('Your cart is empty');
    $method = (string) ($d['paymentMethod'] ?? 'paystack');
    $s = settings();
    if (!isset(PAYMENT_LABELS[$method])) throw new InvalidArgumentException('Choose a payment method');
    $pickup = ($d['deliveryMethod'] ?? '') === 'pickup';
    if ($pickup && !$s['pickupEnabled']) throw new InvalidArgumentException('Pickup is not available');
    if ($pickup) {
        // Pickup orders don't need a delivery address.
        foreach (['shippingAddress' => 'Pickup: ' . ($s['pickupAddress'] ?: $s['contactAddress']), 'city' => 'Pickup', 'state' => 'Pickup'] as $k => $v) {
            if (trim((string) ($d[$k] ?? '')) === '') $d[$k] = $v;
        }
    }
    if ($method === 'transfer' && trim($s['bankDetails']) === '') throw new InvalidArgumentException('Bank transfer is not available');
    if ($method === 'pod' && !$s['payOnDelivery']) throw new InvalidArgumentException('Pay on delivery is not available');
    return [
        'name' => str_field($d, 'customerName', 200, true, 'Name'),
        'email' => $email, 'phone' => $phone,
        'address' => str_field($d, 'shippingAddress', 500, true, 'Address'),
        'city' => str_field($d, 'city', 100, true, 'City'),
        'state' => str_field($d, 'state', 100, true, 'State'),
        'code' => mb_substr(trim((string) ($d['discountCode'] ?? '')), 0, 40),
        'method' => $method, 'items' => $items,
        'notes' => str_field($d, 'notes', 500, false, 'Order note'),
        'giftWrap' => !empty($d['giftWrap']),
        'tip' => max(0, min(100000, (int) ($d['tip'] ?? 0))) * 100,
        'leaderboard' => !empty($d['leaderboard']),
        'pickup' => $pickup,
    ];
}

/** Price breakdown for the checkout summary (reserves nothing). */
function api_checkout_quote(): void
{
    $d = json_body() ?? [];
    $subtotal = 0;
    $count = 0;
    $st = db()->prepare('SELECT * FROM Product WHERE id = ?');
    foreach (array_slice((array) ($d['items'] ?? []), 0, 50) as $i) {
        $st->execute([(string) ($i['productId'] ?? '')]);
        $row = $st->fetch();
        if (!$row) continue;
        $qty = max(1, min(20, (int) ($i['quantity'] ?? 1)));
        $subtotal += effective_price($row) * $qty;
        $count += $qty;
    }
    $state = (string) ($d['state'] ?? '');
    $s = settings();
    $pickup = ($d['deliveryMethod'] ?? '') === 'pickup' && $s['pickupEnabled'];
    $delivery = $pickup ? 0 : ($state === '' ? null : delivery_fee($state, $subtotal));
    $wrap = !empty($d['giftWrap']) ? (int) $s['giftWrapPrice'] * 100 : 0;
    $ids = array_map(fn($i) => (string) ($i['productId'] ?? ''), (array) ($d['items'] ?? []));
    $discount = 0;
    $code = null;
    $codeError = null;
    try {
        [$discount, $code] = apply_discount((string) ($d['discountCode'] ?? ''), $subtotal, $ids);
    } catch (InvalidArgumentException $ex) {
        $codeError = $ex->getMessage();
    }
    json_out(['subtotal' => $subtotal, 'delivery' => $delivery, 'discount' => $discount, 'code' => $code,
        'codeError' => $codeError, 'giftWrap' => $wrap, 'bundle' => $bundle = bundle_discount($count, $subtotal),
        'tip' => $tip = max(0, min(100000, (int) ($d['tip'] ?? 0))) * 100, 'freeGift' => free_gift_for($subtotal),
        'freeGiftOver' => (int) settings()['freeGiftOver'] * 100, 'freeGiftText' => settings()['freeGiftText'],
        'total' => $subtotal + (int) $delivery + $wrap + $tip - $discount - $bundle]);
}

/**
 * Reserves every one-of-one item atomically and writes the order.
 * Items are held 15 minutes for Paystack, 48 hours for bank transfer, 7 days for pay on delivery.
 */
function create_order(array $c, ?string $idem): array
{
    $pdo = db();
    $now = now_ms();
    $holdMinutes = ['paystack' => RESERVATION_MINUTES, 'transfer' => 48 * 60, 'pod' => 7 * 24 * 60][$c['method']];
    $reservedUntil = $now + $holdMinutes * 60 * 1000;
    $orderId = new_id();
    $reference = 'TK_' . $now . '_' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 7));

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $subtotal = 0;
        $lines = [];
        foreach ($c['items'] as $item) {
            $st = $pdo->prepare('SELECT * FROM Product WHERE id = ?');
            $st->execute([$item['productId']]);
            $p = $st->fetch();
            if (!$p || product_availability($p) === 'scheduled') throw new InvalidArgumentException('One or more items are unavailable');
            $up = $pdo->prepare('UPDATE Product SET reservedUntil = ? WHERE id = ? AND inStock = 1 AND (reservedUntil IS NULL OR reservedUntil < ?)');
            $up->execute([$reservedUntil, $p['id'], $now]);
            if ($up->rowCount() === 0) throw new InvalidArgumentException('"' . $p['name'] . '" just sold or is being purchased by someone else');
            $price = effective_price($p);
            $subtotal += $price * $item['quantity'];
            $itemCount = ($itemCount ?? 0) + $item['quantity'];
            $lines[] = [new_id(), $orderId, $p['id'], $item['quantity'], $price, $item['size']];
        }
        [$discount, $code] = apply_discount($c['code'], $subtotal, array_column($c['items'], 'productId'));
        $delivery = $c['pickup'] ? 0 : delivery_fee($c['state'], $subtotal);
        $wrap = $c['giftWrap'] ? (int) settings()['giftWrapPrice'] * 100 : 0;
        if ($code) {
            $own = $pdo->prepare('SELECT ownerEmail FROM DiscountCode WHERE code = ?');
            $own->execute([$code]);
            if (strtolower((string) $own->fetchColumn()) === strtolower($c['email'])) throw new InvalidArgumentException('You can\'t use your own referral code — share it with friends!');
        }
        $bundle = bundle_discount($itemCount, $subtotal);
        $gift = free_gift_for($subtotal);
        $total = $subtotal - $discount - $bundle + $delivery + $wrap + $c['tip'];
        if ($total < 10000) throw new InvalidArgumentException('Minimum order amount is ₦100');
        $status = $c['method'] === 'paystack' ? 'pending' : 'awaiting';

        $pdo->prepare('INSERT INTO "Order" (id, reference, idempotencyKey, customerName, customerEmail, customerPhone, shippingAddress, city, state, totalAmount, status, createdAt, updatedAt, deliveryFee, discountAmount, discountCode, paymentMethod, notes, giftWrap, giftWrapFee, deliveryMethod, tip, bundleDiscount, freeGift, leaderboard) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$orderId, $reference, $idem, $c['name'], $c['email'], $c['phone'], $c['address'], $c['city'], $c['state'], $total, $status, $now, $now, $delivery, $discount, $code, $c['method'], $c['notes'], $c['giftWrap'] ? 1 : 0, $wrap, $c['pickup'] ? 'pickup' : 'delivery', $c['tip'], $bundle, $gift, $c['leaderboard'] ? 1 : 0]);
        $ins = $pdo->prepare('INSERT INTO OrderItem (id, orderId, productId, quantity, price, size) VALUES (?,?,?,?,?,?)');
        foreach ($lines as $l) $ins->execute($l);
        if ($code) {
            $pdo->prepare('UPDATE DiscountCode SET uses = uses + 1 WHERE code = ?')->execute([$code]);
            $pdo->prepare("UPDATE Offer SET status = 'used' WHERE code = ?")->execute([$code]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
    return order_with_items('id = ?', [$orderId]);
}

/** Bank transfer and pay-on-delivery orders: no Paystack redirect, just a confirmation page. */
function api_order_place(): void
{
    [$ok] = rate_limit('order:' . client_ip(), 10, 3600);
    if (!$ok) json_out(['error' => 'Too many orders from this connection. Please call us.'], 429);
    $c = read_checkout(json_body());
    if ($c['method'] === 'paystack') throw new InvalidArgumentException('Use Paystack checkout for card payments');
    $order = create_order($c, null);
    respond_then_continue(['reference' => $order['reference'], 'redirect' => '/order/success?reference=' . urlencode($order['reference'])]);
    notify_order($order, 'placed');
    exit;
}

function api_paystack_initialize(): void
{
    $c = read_checkout(json_body());
    $c['method'] = 'paystack';
    $pdo = db();
    $idem = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')) ?: null;
    if ($idem) {
        $st = $pdo->prepare('SELECT * FROM "Order" WHERE idempotencyKey = ?');
        $st->execute([$idem]);
        if ($existing = $st->fetch()) {
            if ($existing['status'] === 'paid') json_out(['error' => 'This order has already been paid for.'], 409);
            if ($existing['authorizationUrl']) json_out(['authorization_url' => $existing['authorizationUrl'], 'reference' => $existing['reference']]);
            // Earlier attempt never reached Paystack — clear it so this retry starts clean.
            release_reservations(order_item_ids($existing['id']));
            if ($existing['discountCode']) $pdo->prepare('UPDATE DiscountCode SET uses = MAX(0, uses - 1) WHERE code = ?')->execute([$existing['discountCode']]);
            $pdo->prepare('DELETE FROM "Order" WHERE id = ?')->execute([$existing['id']]);
        }
    }
    $order = create_order($c, $idem);

    try {
        $res = paystack_request('POST', '/transaction/initialize', [
            'email' => $order['customerEmail'],
            'amount' => $order['totalAmount'],
            'reference' => $order['reference'],
            'currency' => 'NGN',
            'callback_url' => app_url() . '/order/success?reference=' . urlencode($order['reference']),
            'metadata' => ['order_id' => $order['id'], 'customer_name' => $order['customerName']],
        ]);
        $pdo->prepare('UPDATE "Order" SET paystackRef = ?, authorizationUrl = ?, updatedAt = ? WHERE id = ?')
            ->execute([$res['data']['reference'], $res['data']['authorization_url'], now_ms(), $order['id']]);
        json_out(['authorization_url' => $res['data']['authorization_url'], 'reference' => $order['reference']]);
    } catch (RuntimeException $ex) {
        release_reservations(array_column($order['items'], 'productId'));
        $pdo->prepare('UPDATE "Order" SET status = \'failed\', updatedAt = ? WHERE id = ?')->execute([now_ms(), $order['id']]);
        if ($order['discountCode']) $pdo->prepare('UPDATE DiscountCode SET uses = MAX(0, uses - 1) WHERE code = ?')->execute([$order['discountCode']]);
        log_error('Paystack initialize error', $ex->getMessage());
        json_out(['error' => $ex->getMessage()], 400);
    }
}

function order_with_items(string $where, array $params): ?array
{
    $st = db()->prepare("SELECT * FROM \"Order\" WHERE $where");
    $st->execute($params);
    $o = $st->fetch();
    if (!$o) return null;
    $it = db()->prepare('SELECT oi.*, p.name AS productName, p.slug AS productSlug FROM OrderItem oi LEFT JOIN Product p ON p.id = oi.productId WHERE oi.orderId = ?');
    $it->execute([$o['id']]);
    $o['totalAmount'] = (int) $o['totalAmount'];
    $o['deliveryFee'] = (int) ($o['deliveryFee'] ?? 0);
    $o['discountAmount'] = (int) ($o['discountAmount'] ?? 0);
    $o['giftWrap'] = (bool) ($o['giftWrap'] ?? 0);
    $o['giftWrapFee'] = (int) ($o['giftWrapFee'] ?? 0);
    $o['notes'] = (string) ($o['notes'] ?? '');
    $o['deliveryMethod'] = (string) ($o['deliveryMethod'] ?? 'delivery');
    $o['tip'] = (int) ($o['tip'] ?? 0);
    $o['bundleDiscount'] = (int) ($o['bundleDiscount'] ?? 0);
    $o['items'] = array_map(fn($i) => [
        'id' => $i['id'], 'productId' => $i['productId'], 'quantity' => (int) $i['quantity'],
        'price' => (int) $i['price'], 'size' => $i['size'],
        'product' => ['name' => $i['productName'] ?? 'Item', 'slug' => $i['productSlug'] ?? ''],
    ], $it->fetchAll());
    return $o;
}

/** Marks an order paid and takes its items off sale. Shared by verify + webhook. */
function mark_order_paid(array $order, string $paystackRef): void
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    $pdo->prepare('UPDATE "Order" SET status = \'paid\', paystackRef = ?, updatedAt = ? WHERE id = ?')->execute([$paystackRef, now_ms(), $order['id']]);
    $ids = array_column($order['items'], 'productId');
    if ($ids) {
        $pdo->prepare('UPDATE Product SET inStock = 0, reservedUntil = NULL, soldAt = ? WHERE id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ')')->execute([now_ms(), ...$ids]);
    }
    $pdo->exec('COMMIT');
    if (($order['paymentMethod'] ?? 'paystack') === 'paystack') {
        $order['status'] = 'paid';
        try { notify_order($order, 'placed'); } catch (Throwable $ex) { log_error('Order email failed', $ex->getMessage()); }
    }
    try { after_order_paid($order); } catch (Throwable $ex) { log_error('Loyalty/referral failed', $ex->getMessage()); }
}

function api_paystack_verify(): void
{
    $reference = (string) ($_GET['reference'] ?? '');
    if ($reference === '') json_out(['error' => 'Reference is required'], 400);
    $order = order_with_items('reference = ?', [$reference]);
    if (!$order) json_out(['error' => 'Order not found'], 404);
    if ($order['status'] === 'paid') json_out(['order' => $order, 'alreadyPaid' => true]);
    if ($order['paymentMethod'] !== 'paystack') {
        json_out(['order' => $order, 'bankDetails' => $order['paymentMethod'] === 'transfer' ? settings()['bankDetails'] : null]);
    }

    try {
        $v = paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));
    } catch (RuntimeException $ex) {
        json_out(['error' => $ex->getMessage()], 500);
    }
    if (($v['data']['status'] ?? '') !== 'success' || (int) ($v['data']['amount'] ?? 0) !== $order['totalAmount']) {
        db()->prepare('UPDATE "Order" SET status = \'failed\', updatedAt = ? WHERE id = ?')->execute([now_ms(), $order['id']]);
        release_reservations(array_column($order['items'], 'productId'));
        json_out(['error' => 'Payment was not successful'], 400);
    }
    mark_order_paid($order, (string) $v['data']['reference']);
    $order['status'] = 'paid';
    json_out(['order' => $order]);
}

/** Source of truth for payment status — still runs if the shopper closes the tab after paying. */
function api_paystack_webhook(): void
{
    $secret = config('PAYSTACK_SECRET_KEY');
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    $raw = file_get_contents('php://input') ?: '';
    if (!$secret || !$signature) json_out(['error' => 'Unauthorized'], 401);
    if (!hash_equals(hash_hmac('sha512', $raw, $secret), $signature)) json_out(['error' => 'Invalid signature'], 401);

    $event = json_decode($raw, true);
    if (!is_array($event)) json_out(['error' => 'Invalid payload'], 400);

    if (($event['event'] ?? '') === 'charge.success' && !empty($event['data']['reference'])) {
        try {
            $order = order_with_items('reference = ?', [$event['data']['reference']]);
            if ($order && $order['status'] !== 'paid' && (int) ($event['data']['amount'] ?? 0) === $order['totalAmount']) {
                mark_order_paid($order, (string) $event['data']['reference']);
            }
        } catch (Throwable $ex) {
            // Still ack — Paystack retries on non-2xx, and a retry won't fix a bug here.
            log_error('Paystack webhook error', $ex->getMessage());
        }
    }
    json_out(['received' => true]);
}

// ---------- push subscriptions ----------

function api_push_subscribe(): void
{
    $d = json_body();
    $endpoint = (string) ($d['endpoint'] ?? '');
    $p256dh = (string) ($d['keys']['p256dh'] ?? '');
    $auth = (string) ($d['keys']['auth'] ?? '');
    if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with($endpoint, 'https://') || strlen($endpoint) > 1000
        || $p256dh === '' || strlen($p256dh) > 200 || $auth === '' || strlen($auth) > 100) {
        json_out(['error' => 'Invalid subscription'], 400);
    }
    [$ok] = rate_limit('push:' . client_ip(), 20, 3600);
    if (!$ok) json_out(['error' => 'Too many requests'], 429);
    db()->prepare('INSERT INTO PushSubscription (id, endpoint, p256dh, auth, createdAt) VALUES (?,?,?,?,?)
        ON CONFLICT(endpoint) DO UPDATE SET p256dh = excluded.p256dh, auth = excluded.auth')
        ->execute([new_id(), $endpoint, $p256dh, $auth, now_ms()]);
    json_out(['ok' => true]);
}

function api_push_unsubscribe(): void
{
    $endpoint = (string) (json_body()['endpoint'] ?? '');
    if ($endpoint === '') json_out(['error' => 'Missing endpoint'], 400);
    db()->prepare('DELETE FROM PushSubscription WHERE endpoint = ?')->execute([$endpoint]);
    json_out(['ok' => true]);
}

// ---------- admin ----------

function api_admin_login(): void
{
    // Lockout: 5 wrong attempts from one connection blocks logins for 30 minutes.
    $key = 'loginfail:' . client_ip();
    $st = db()->prepare('SELECT count, resetAt FROM RateLimit WHERE key = ?');
    $st->execute([$key]);
    $b = $st->fetch();
    if ($b && $b['resetAt'] > time() && $b['count'] >= 5) {
        json_out(['error' => 'Too many wrong attempts. Login is locked for ' . (int) ceil(($b['resetAt'] - time()) / 60) . ' more minutes.'], 429, ['Retry-After' => (string) ($b['resetAt'] - time())]);
    }
    $d = json_body() ?? [];
    $password = (string) ($d['password'] ?? '');
    $expected = (string) config('ADMIN_PASSWORD');
    $secret = meta_get('totp_secret');
    $fail = function (string $msg) use ($key) {
        rate_limit($key, 5, 30 * 60);
        admin_log('login_failed', $msg);
        json_out(['error' => $msg], 401);
    };
    $staffName = null;
    if ($password === '' || $expected === '' || !hash_equals($expected, $password)) {
        $staffName = $password !== '' ? staff_login($password) : null;
        if ($staffName === null) $fail('Invalid password');
        $secret = null; // 2-step login protects the owner account
    }
    if ($secret) {
        if (trim((string) ($d['code'] ?? '')) === '') json_out(['error' => 'Enter the 6-digit code from your authenticator app', 'needCode' => true], 401);
        if (!totp_verify($secret, (string) $d['code'])) $fail('Wrong authenticator code');
    }
    db()->prepare('DELETE FROM RateLimit WHERE key = ?')->execute([$key]);
    admin_log('login', $staffName ? 'Staff: ' . $staffName : 'Owner signed in');
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['expires'] = time() + 60 * 60 * 24 * 7;
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    $_SESSION['role'] = $staffName ? 'staff' : 'owner';
    $_SESSION['staffName'] = $staffName;
    json_out(['success' => true, 'role' => $_SESSION['role']]);
}

function api_admin_logout(): void
{
    require_admin(true);
    $_SESSION = [];
    session_destroy();
    json_out(['success' => true]);
}

function api_admin_products(): void
{
    require_admin();
    json_out(array_map('product_json', fetch_products()));
}

function api_admin_get_product(string $id): void
{
    require_admin();
    $st = db()->prepare('SELECT * FROM Product WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    $p ? json_out(product_json($p)) : json_out(['error' => 'Not found'], 404);
}

function api_admin_create_product(): void
{
    require_admin(true);
    $d = validate_product(json_body());
    $d['slug'] = unique_slug($d['slug'] ?: $d['name']);
    $d['id'] = new_id();
    $d['createdAt'] = $d['publishAt'] ?? now_ms();
    $d['announced'] = $d['publishAt'] ? 0 : 1;
    $cols = array_keys($d);
    db()->prepare('INSERT INTO Product ("' . implode('","', $cols) . '") VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')')
        ->execute(array_values($d));
    $st = db()->prepare('SELECT * FROM Product WHERE id = ?');
    $st->execute([$d['id']]);
    $product = $st->fetch();

    admin_log('product_create', $product['name']);
    if ($product['inStock'] && !$product['publishAt']) {
        // Reply to the admin first, then send phone + email alerts in the background.
        respond_then_continue(product_json($product), 201);
        announce_product($product);
        exit;
    }
    json_out(product_json($product), 201);
}

function api_admin_update_product(string $id): void
{
    require_admin(true);
    $st = db()->prepare('SELECT * FROM Product WHERE id = ?');
    $st->execute([$id]);
    $before = $st->fetch();
    if (!$before) json_out(['error' => 'Not found'], 404);
    $d = validate_product(json_body());
    $d['slug'] = unique_slug($d['slug'] ?: $d['name'], $id);
    if ($d['publishAt'] && $d['publishAt'] !== (int) $before['publishAt']) { $d['announced'] = 0; $d['createdAt'] = $d['publishAt']; }
    if ($before['inStock'] && !$d['inStock']) $d['soldAt'] = now_ms();
    if (!$before['inStock'] && $d['inStock']) $d['soldAt'] = null;
    admin_log('product_update', $d['name']);
    $sets = implode(', ', array_map(fn($c) => "\"$c\" = ?", array_keys($d)));
    db()->prepare("UPDATE Product SET $sets WHERE id = ?")->execute([...array_values($d), $id]);
    $st = db()->prepare('SELECT * FROM Product WHERE id = ?');
    $st->execute([$id]);
    $after = $st->fetch();
    if ($after['salePrice'] < $before['salePrice']) {
        respond_then_continue(product_json($after));
        notify_price_drops($id);
        exit;
    }
    json_out(product_json($after));
}

function api_admin_delete_product(string $id): void
{
    require_admin(true);
    $st = db()->prepare('SELECT COUNT(*) FROM OrderItem WHERE productId = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        json_out(['error' => 'This item is part of an order, so it can’t be deleted. Mark it as sold out instead.'], 409);
    }
    $del = db()->prepare('DELETE FROM Product WHERE id = ?');
    $del->execute([$id]);
    if ($del->rowCount()) admin_log('product_delete', $id);
    $del->rowCount() ? json_out(['success' => true]) : json_out(['error' => 'Not found'], 404);
}

function api_admin_orders(): void
{
    require_admin();
    $pageSize = min(100, max(1, (int) ($_GET['pageSize'] ?? 50)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $ids = db()->query('SELECT id FROM "Order" ORDER BY createdAt DESC LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize))->fetchAll(PDO::FETCH_COLUMN);
    $orders = array_map(fn($id) => order_with_items('id = ?', [$id]), $ids);
    json_out(['orders' => $orders, 'total' => (int) db()->query('SELECT COUNT(*) FROM "Order"')->fetchColumn(), 'page' => $page, 'pageSize' => $pageSize]);
}

function api_settings_admin(): void
{
    require_admin();
    json_out(settings());
}

function api_admin_update_settings(): void
{
    require_admin(true);
    $d = json_body() ?? [];
    $limits = [
        'storeName' => [200, true], 'logoUrl' => [2000, false], 'tagline' => [300, false], 'heroTitle' => [300, false],
        'heroSubtitle' => [600, false], 'heroImageUrl' => [2000, false], 'aboutTitle' => [300, false], 'aboutText' => [4000, false],
        'contactEmail' => [200, false], 'contactAddress' => [300, false], 'customerCarePhone' => [60, true],
        'customerCareHours' => [100, false], 'instagramUrl' => [500, false], 'tiktokUrl' => [500, false], 'whatsappNumber' => [60, false],
        'bankDetails' => [600, false], 'pickupAddress' => [300, false], 'returnsPolicy' => [6000, false],
        'termsText' => [6000, false], 'privacyText' => [6000, false], 'sizeGuide' => [6000, false],
        'gaId' => [40, false], 'metaPixelId' => [40, false],
    ];
    $data = [];
    foreach ($limits as $k => [$max, $req]) $data[$k] = str_field($d, $k, $max, $req);
    foreach (['instagramUrl', 'tiktokUrl'] as $k) {
        if ($data[$k] !== '' && !preg_match('#^https://#i', $data[$k])) throw new InvalidArgumentException('Social links must start with https://');
    }
    foreach (['deliveryLagos', 'deliveryOther', 'freeDeliveryOver'] as $k) {
        $v = $d[$k] ?? 0;
        if (!is_numeric($v) || $v < 0 || $v > 1000000) throw new InvalidArgumentException('Delivery fees must be between 0 and 1,000,000');
        $data[$k] = (int) $v;
    }
    $data['payOnDelivery'] = !empty($d['payOnDelivery']) && $d['payOnDelivery'] !== 'false' ? 1 : 0;
    $data['pickupEnabled'] = !empty($d['pickupEnabled']) && $d['pickupEnabled'] !== 'false' ? 1 : 0;
    $data['offersEnabled'] = !empty($d['offersEnabled']) && $d['offersEnabled'] !== 'false' ? 1 : 0;
    $data['giftWrapPrice'] = max(0, min(100000, (int) ($d['giftWrapPrice'] ?? 0)));
    foreach (['bundleMin' => 20, 'bundlePct' => 50, 'flashPct' => 80, 'birthdayPct' => 50] as $k => $max) $data[$k] = max(0, min($max, (int) ($d[$k] ?? 0)));
    foreach (['freeGiftOver', 'loyaltyPoints', 'loyaltyReward', 'referralReward'] as $k) $data[$k] = max(0, min(1000000, (int) ($d[$k] ?? 0)));
    $data['freeGiftText'] = str_field($d, 'freeGiftText', 120, false);
    $fc = (string) ($d['flashCategory'] ?? '');
    $data['flashCategory'] = isset(CATEGORIES[$fc]) && $fc !== 'all' ? $fc : '';
    $fe = trim((string) ($d['flashEndsAt'] ?? ''));
    $data['flashEndsAt'] = $fe === '' ? null : ((strtotime($fe) ?: 0) * 1000 ?: null);
    if ($data['gaId'] !== '' && !preg_match('/^G-[A-Z0-9]{4,20}$/', $data['gaId'])) throw new InvalidArgumentException('Google Analytics ID looks like G-XXXXXXX');
    if ($data['metaPixelId'] !== '' && !preg_match('/^\d{6,20}$/', $data['metaPixelId'])) throw new InvalidArgumentException('Meta Pixel ID is a number');
    foreach (['colorCream', 'colorSand', 'colorTerracotta', 'colorRust', 'colorOlive', 'colorCharcoal', 'colorWarmgray'] as $k) {
        $v = trim((string) ($d[$k] ?? ''));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) throw new InvalidArgumentException('Colors must be hex values like #7A1F2B');
        $data[$k] = $v;
    }
    $data['updatedAt'] = now_ms();
    $sets = implode(', ', array_map(fn($c) => "\"$c\" = ?", array_keys($data)));
    settings(); // ensures the row exists
    db()->prepare("UPDATE SiteSettings SET $sets WHERE id = 'default'")->execute(array_values($data));
    admin_log('settings_update', 'Theme, content or delivery settings');
    json_out(db()->query("SELECT * FROM SiteSettings WHERE id = 'default'")->fetch());
}

function api_admin_upload(): void
{
    require_admin(true);
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $tooBig = $file && in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        json_out(['error' => $tooBig ? 'Image must be 5MB or smaller' : 'No image file provided'], 400);
    }
    if ($file['size'] > 5 * 1024 * 1024) json_out(['error' => 'Image must be 5MB or smaller'], 400);

    // Check the real bytes, not the browser-supplied type, before writing anything to /uploads.
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($allowed[$info[2]])) json_out(['error' => 'Only JPG, PNG, WebP, or GIF images are allowed'], 400);

    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $isLogo = ($_POST['type'] ?? '') === 'logo';
    $base = substr(preg_replace('/[^a-zA-Z0-9-_]/', '-', pathinfo($file['name'], PATHINFO_FILENAME)), 0, 40) ?: 'image';
    [$maxW, $maxH] = $isLogo ? [320, 96] : [1600, 1600];
    // Product photos are saved as WebP (much smaller, faster pages) when the server supports it.
    $ext = $isLogo ? 'png' : (function_exists('imagewebp') && $info[2] !== IMAGETYPE_GIF ? 'webp' : $allowed[$info[2]]);
    $name = ($isLogo ? 'logo-' : '') . time() . '-' . bin2hex(random_bytes(3)) . '-' . $base . '.' . $ext;

    if ($info[2] === IMAGETYPE_GIF && !$isLogo) {
        // Keep animated GIFs as-is; re-encoding would drop frames.
        move_uploaded_file($file['tmp_name'], "$dir/$name");
    } else {
        save_resized_image($file['tmp_name'], $info, "$dir/$name", $maxW, $maxH, $ext);
    }
    json_out(['url' => '/uploads/' . $name]);
}

function save_resized_image(string $src, array $info, string $dest, int $maxW, int $maxH, string $ext): void
{
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($src),
        IMAGETYPE_PNG => imagecreatefrompng($src),
        IMAGETYPE_WEBP => imagecreatefromwebp($src),
        IMAGETYPE_GIF => imagecreatefromgif($src),
    };
    if (!$img) throw new InvalidArgumentException('File is not a valid image');

    // Apply phone-camera rotation before resizing.
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) $img = imagerotate($img, $rot, 0);
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $maxW / $w, $maxH / $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    match ($ext) {
        'png' => imagepng($out, $dest, 9),
        'webp' => imagewebp($out, $dest, 85),
        default => imagejpeg($out, $dest, 85),
    };
}


/** Sends the JSON response now and keeps the script running for background work. */
function respond_then_continue($data, int $status = 200): void
{
    ignore_user_abort(true);
    set_time_limit(120);
    $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Length: ' . strlen($body));
    header('Connection: close');
    echo $body;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) ob_end_flush();
        flush();
    }
}

// ---------- extra public endpoints ----------

/** For uptime monitors (UptimeRobot etc.): checks the database answers. */
function api_health(): void
{
    db()->query('SELECT 1')->fetchColumn();
    json_out(['ok' => true, 'time' => date('c')], 200, ['Cache-Control' => 'no-store']);
}

/** Order tracking: shopper gives the reference plus the email or phone used at checkout. */
function api_track(): void
{
    [$ok] = rate_limit('track:' . client_ip(), 30, 3600);
    if (!$ok) json_out(['error' => 'Too many lookups. Please try again later.'], 429);
    $ref = trim((string) ($_GET['ref'] ?? ''));
    $contact = strtolower(preg_replace('/[\s\-()]/', '', (string) ($_GET['contact'] ?? '')));
    if ($ref === '' || $contact === '') json_out(['error' => 'Enter your order reference and email or phone'], 400);
    $o = order_with_items('reference = ?', [$ref]);
    $norm = fn($p) => preg_replace('/^(\+?234|0)/', '', $p);
    $match = $o && (hash_equals(strtolower($o['customerEmail']), $contact)
        || ($norm($contact) !== '' && hash_equals($norm($o['customerPhone']), $norm($contact))));
    if (!$match) json_out(['error' => 'We could not find an order with those details'], 404);
    json_out(['order' => [
        'reference' => $o['reference'], 'status' => $o['status'], 'fulfillment' => $o['fulfillment'],
        'paymentMethod' => $o['paymentMethod'], 'totalAmount' => $o['totalAmount'], 'createdAt' => (int) $o['createdAt'],
        'updatedAt' => (int) $o['updatedAt'], 'city' => $o['city'], 'state' => $o['state'],
        'riderName' => $o['riderName'] ?? '', 'riderPhone' => $o['riderPhone'] ?? '', 'trackingUrl' => $o['trackingUrl'] ?? '', 'deliveryMethod' => $o['deliveryMethod'],
        'items' => array_map(fn($i) => ['name' => $i['product']['name'], 'quantity' => $i['quantity'], 'price' => $i['price']], $o['items']),
    ]]);
}

/** Email alerts: all new drops (category '') or a single category ("notify me when similar arrive"). */
function api_subscribe(): void
{
    [$ok] = rate_limit('sub:' . client_ip(), 10, 3600);
    if (!$ok) json_out(['error' => 'Too many requests'], 429);
    $d = json_body() ?? [];
    $email = strtolower(trim((string) ($d['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) json_out(['error' => 'Enter a valid email'], 400);
    $cat = (string) ($d['category'] ?? '');
    if ($cat !== '' && (!isset(CATEGORIES[$cat]) || $cat === 'all')) $cat = '';
    db()->prepare('INSERT OR IGNORE INTO Subscriber (id, email, category, token, createdAt) VALUES (?,?,?,?,?)')
        ->execute([new_id(), $email, $cat, bin2hex(random_bytes(16)), now_ms()]);
    $bday = (string) ($d['birthday'] ?? '');
    if (preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $bday, $bm) && checkdate((int) $bm[1], (int) $bm[2], 2000)) {
        db()->prepare('UPDATE Subscriber SET birthday = ? WHERE email = ?')->execute([$bm[1] . '-' . $bm[2], $email]);
    }
    json_out(['ok' => true]);
}

function api_review_submit(): void
{
    [$ok] = rate_limit('review:' . client_ip(), 3, 3600);
    if (!$ok) json_out(['error' => 'Too many reviews from this connection'], 429);
    $d = json_body() ?? [];
    $rating = (int) ($d['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) throw new InvalidArgumentException('Choose a rating from 1 to 5');
    db()->prepare('INSERT INTO Review (id, name, rating, body, approved, createdAt) VALUES (?,?,?,?,0,?)')
        ->execute([new_id(), str_field($d, 'name', 80), $rating, str_field($d, 'body', 1000, true, 'Review'), now_ms()]);
    json_out(['ok' => true, 'message' => 'Thank you! Your review will appear once approved.']);
}

// ---------- extra admin endpoints ----------

function api_admin_bulk_create(): void
{
    require_admin(true);
    $items = (array) (json_body()['items'] ?? []);
    if (!$items || count($items) > 30) throw new InvalidArgumentException('Add between 1 and 30 items');
    $valid = [];
    foreach ($items as $n => $raw) {
        try {
            $valid[] = validate_product(is_array($raw) ? $raw : null);
        } catch (InvalidArgumentException $ex) {
            throw new InvalidArgumentException('Row ' . ($n + 1) . ': ' . $ex->getMessage());
        }
    }
    $created = [];
    foreach ($valid as $d) {
        $d['slug'] = unique_slug($d['slug'] ?: $d['name']);
        $d['id'] = new_id();
        $d['createdAt'] = $d['publishAt'] ?? now_ms();
        $d['announced'] = $d['publishAt'] ? 0 : 1;
        $cols = array_keys($d);
        db()->prepare('INSERT INTO Product ("' . implode('","', $cols) . '") VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')')
            ->execute(array_values($d));
        $created[] = $d;
    }
    admin_log('product_bulk_create', count($created) . ' items');
    respond_then_continue(['created' => count($created)], 201);
    // One combined alert instead of one per item.
    $live = array_values(array_filter($created, fn($p) => $p['inStock'] && !$p['publishAt']));
    if ($live) {
        require_once __DIR__ . '/push.php';
        try {
            notify_all_subscribers([
                'title' => 'New drop ✨',
                'body' => count($live) . ' fresh ' . (count($live) === 1 ? 'piece' : 'pieces') . ' just landed — ' . $live[0]['name'] . (count($live) > 1 ? ' and more' : ''),
                'url' => '/shop', 'image' => absolute_url($live[0]['imageUrl']),
            ]);
        } catch (Throwable $ex) {
            log_error('Bulk push failed', $ex->getMessage());
        }
        foreach ($live as $p) {
            try { email_subscribers_about($p); } catch (Throwable $ex) { log_error('Bulk email failed', $ex->getMessage()); }
        }
    }
    exit;
}

/** Admin order actions: mark paid, cancel (puts items back on sale), or move delivery status along. */
function api_admin_update_order(string $id): void
{
    require_admin(true);
    $o = order_with_items('id = ?', [$id]);
    if (!$o) json_out(['error' => 'Not found'], 404);
    $d = json_body() ?? [];
    $action = (string) ($d['action'] ?? '');
    $pdo = db();
    if ($action === 'paid') {
        if ($o['status'] === 'paid') throw new InvalidArgumentException('Already paid');
        mark_order_paid($o, $o['paystackRef'] ?: 'manual');
    } elseif ($action === 'cancel') {
        if ($o['status'] === 'paid' && empty($d['restock'])) throw new InvalidArgumentException('This order is paid. Refund the customer first, then cancel with restock.');
        $pdo->prepare('UPDATE "Order" SET status = \'cancelled\', updatedAt = ? WHERE id = ?')->execute([now_ms(), $id]);
        $ids = array_column($o['items'], 'productId');
        if ($ids) $pdo->prepare('UPDATE Product SET inStock = 1, reservedUntil = NULL, soldAt = NULL WHERE id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ')')->execute($ids);
    } elseif ($action === 'rider') {
        $url = trim((string) ($d['trackingUrl'] ?? ''));
        if ($url !== '' && !preg_match('#^https://#i', $url)) throw new InvalidArgumentException('Tracking link must start with https://');
        $pdo->prepare('UPDATE "Order" SET riderName = ?, riderPhone = ?, trackingUrl = ?, updatedAt = ? WHERE id = ?')
            ->execute([str_field($d, 'riderName', 80, false), str_field($d, 'riderPhone', 30, false), $url, now_ms(), $id]);
    } elseif ($action === 'return') {
        $pdo->prepare('UPDATE "Order" SET returnStatus = \'returned\', returnReason = ?, updatedAt = ? WHERE id = ?')->execute([str_field($d, 'reason', 300, true, 'Reason'), now_ms(), $id]);
        if (!empty($d['restock'])) {
            $ids = array_column($o['items'], 'productId');
            if ($ids) $pdo->prepare('UPDATE Product SET inStock = 1, reservedUntil = NULL, soldAt = NULL WHERE id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ')')->execute($ids);
        }
    } elseif ($action === 'fulfillment' && isset(FULFILLMENT_LABELS[$d['value'] ?? ''])) {
        $pdo->prepare('UPDATE "Order" SET fulfillment = ?, updatedAt = ? WHERE id = ?')->execute([$d['value'], now_ms(), $id]);
    } else {
        throw new InvalidArgumentException('Unknown action');
    }
    admin_log('order_' . $action, $o['reference'] . (isset($d['value']) ? ' → ' . $d['value'] : ''));
    $updated = order_with_items('id = ?', [$id]);
    respond_then_continue(['order' => $updated]);
    if ($action === 'fulfillment' && in_array($d['value'], ['shipped', 'delivered'], true)) notify_order($updated, 'status');
    exit;
}

function api_admin_export_orders(): void
{
    require_admin();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads ₦ and accents correctly
    fputcsv($out, ['Reference', 'Date', 'Customer', 'Email', 'Phone', 'Address', 'City', 'State', 'Items', 'Delivery (NGN)', 'Discount (NGN)', 'Discount code', 'Total (NGN)', 'Payment', 'Status', 'Delivery status']);
    $ids = db()->query('SELECT id FROM "Order" ORDER BY createdAt DESC')->fetchAll(PDO::FETCH_COLUMN);
    // Cells starting with = + - @ would run as formulas in Excel; prefix them with a quote.
    $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    foreach ($ids as $id) {
        $o = order_with_items('id = ?', [$id]);
        $items = implode('; ', array_map(fn($i) => $i['product']['name'] . ' x' . $i['quantity'], $o['items']));
        fputcsv($out, array_map($safe, [
            $o['reference'], date('Y-m-d H:i', (int) ($o['createdAt'] / 1000)), $o['customerName'], $o['customerEmail'], $o['customerPhone'],
            $o['shippingAddress'], $o['city'], $o['state'], $items, $o['deliveryFee'] / 100, $o['discountAmount'] / 100, (string) $o['discountCode'],
            $o['totalAmount'] / 100, PAYMENT_LABELS[$o['paymentMethod']] ?? $o['paymentMethod'], $o['status'], FULFILLMENT_LABELS[$o['fulfillment']] ?? $o['fulfillment'],
        ]));
    }
    fclose($out);
    admin_log('export_orders', count($ids) . ' orders');
    exit;
}

/** Downloads a consistent copy of the whole store database (products, orders, settings). */
function api_admin_backup(): void
{
    require_admin();
    $tmp = tempnam(sys_get_temp_dir(), 'tkbak');
    @unlink($tmp);
    try {
        db()->exec('VACUUM INTO ' . db()->quote($tmp));
    } catch (Throwable $ex) {
        copy(data_dir() . '/store.db', $tmp);
    }
    admin_log('backup_download', 'Database backup');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="tk-store-backup-' . date('Y-m-d') . '.db"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

function api_admin_save_discount(): void
{
    require_admin(true);
    $d = json_body() ?? [];
    $code = strtoupper(trim((string) ($d['code'] ?? '')));
    if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $code)) throw new InvalidArgumentException('Code must be 3–30 letters or numbers');
    $kind = ($d['kind'] ?? '') === 'fixed' ? 'fixed' : 'percent';
    $value = (int) ($d['value'] ?? 0);
    if ($value < 1 || ($kind === 'percent' && $value > 90) || $value > 1000000) throw new InvalidArgumentException($kind === 'percent' ? 'Percent must be 1–90' : 'Amount must be 1–1,000,000');
    $expires = trim((string) ($d['expiresAt'] ?? ''));
    $expiresMs = $expires === '' ? null : (strtotime($expires . ' 23:59:59') ?: null) * 1000;
    db()->prepare('INSERT INTO DiscountCode (code, kind, value, minOrder, maxUses, uses, expiresAt, active, createdAt) VALUES (?,?,?,?,?,0,?,1,?)
        ON CONFLICT(code) DO UPDATE SET kind = excluded.kind, value = excluded.value, minOrder = excluded.minOrder, maxUses = excluded.maxUses, expiresAt = excluded.expiresAt, active = 1')
        ->execute([$code, $kind, $value, max(0, (int) ($d['minOrder'] ?? 0)), max(0, (int) ($d['maxUses'] ?? 0)), $expiresMs, now_ms()]);
    admin_log('discount_save', $code);
    json_out(['ok' => true]);
}

function api_admin_delete_discount(string $code): void
{
    require_admin(true);
    db()->prepare('DELETE FROM DiscountCode WHERE code = ?')->execute([$code]);
    admin_log('discount_delete', $code);
    json_out(['ok' => true]);
}

function api_admin_update_review(string $id): void
{
    require_admin(true);
    $d = json_body() ?? [];
    $photo = trim((string) ($d['photoUrl'] ?? ''));
    if ($photo !== '' && !preg_match('#^(https://|/uploads/)#', $photo)) throw new InvalidArgumentException('Photo must be an uploaded image or https link');
    db()->prepare('UPDATE Review SET approved = ?, photoUrl = ? WHERE id = ?')->execute([!empty($d['approved']) ? 1 : 0, $photo, $id]);
    admin_log('review_update', $id);
    json_out(['ok' => true]);
}

function api_admin_delete_review(string $id): void
{
    require_admin(true);
    db()->prepare('DELETE FROM Review WHERE id = ?')->execute([$id]);
    admin_log('review_delete', $id);
    json_out(['ok' => true]);
}

function api_admin_2fa_start(): void
{
    require_admin(true);
    $secret = base32_encode(random_bytes(20));
    $_SESSION['totp_pending'] = $secret;
    $label = rawurlencode(settings()['storeName'] . ' admin');
    json_out(['secret' => $secret, 'uri' => "otpauth://totp/$label?secret=$secret&issuer=" . rawurlencode(settings()['storeName'])]);
}

function api_admin_2fa_enable(): void
{
    require_admin(true);
    $secret = $_SESSION['totp_pending'] ?? '';
    if (!$secret) throw new InvalidArgumentException('Start setup first');
    if (!totp_verify($secret, (string) (json_body()['code'] ?? ''))) throw new InvalidArgumentException('That code is not right. Check your phone clock and try again.');
    meta_set('totp_secret', $secret);
    unset($_SESSION['totp_pending']);
    admin_log('2fa_enable', '2-step login turned on');
    json_out(['ok' => true]);
}

function api_admin_2fa_disable(): void
{
    require_admin(true);
    $secret = meta_get('totp_secret');
    if ($secret && !totp_verify($secret, (string) (json_body()['code'] ?? ''))) throw new InvalidArgumentException('Enter a current code from your authenticator app to turn this off');
    meta_set('totp_secret', null);
    admin_log('2fa_disable', '2-step login turned off');
    json_out(['ok' => true]);
}

// ---------- offers ("make an offer") ----------

function api_offer_submit(): void
{
    [$ok] = rate_limit('offer:' . client_ip(), 5, 3600);
    if (!$ok) json_out(['error' => 'Too many offers. Please try again later.'], 429);
    if (!settings()['offersEnabled']) json_out(['error' => 'Offers are turned off'], 400);
    $d = json_body() ?? [];
    $st = db()->prepare('SELECT * FROM Product WHERE id = ?');
    $st->execute([(string) ($d['productId'] ?? '')]);
    $p = $st->fetch();
    if (!$p || product_availability($p) !== 'available') throw new InvalidArgumentException('This piece is no longer available');
    $email = strtolower(trim((string) ($d['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email');
    $amount = (int) round((float) ($d['amount'] ?? 0)) * 100;
    if ($amount <= 0 || $amount >= (int) $p['salePrice']) throw new InvalidArgumentException('Your offer must be below ' . naira((int) $p['salePrice']));
    if ($amount < (int) $p['salePrice'] * 0.5) throw new InvalidArgumentException('Offers below half the price are not accepted');
    $o = [new_id(), $p['id'], str_field($d, 'name', 80), $email, str_field($d, 'phone', 30), $amount, now_ms()];
    db()->prepare('INSERT INTO Offer (id, productId, name, email, phone, amount, createdAt) VALUES (?,?,?,?,?,?,?)')->execute($o);
    respond_then_continue(['ok' => true, 'message' => "Offer sent! We'll email you within 24 hours."]);
    telegram_alert('💬 Offer: ' . naira($amount) . ' for ' . $p['name'] . ' (listed ' . naira((int) $p['salePrice']) . ') from ' . $o[2] . ' · ' . $o[4]);
    $s = settings();
    if (filter_var($s['contactEmail'], FILTER_VALIDATE_EMAIL)) {
        send_mail($s['contactEmail'], 'New offer: ' . naira($amount) . ' for ' . $p['name'], '<p>' . e($o[2]) . ' (' . e($email) . ', ' . e($o[4]) . ') offered <strong>' . naira($amount) . '</strong> for ' . e($p['name']) . ' (listed at ' . naira((int) $p['salePrice']) . ').</p><p><a href="' . e(app_url() . admin_base() . '/offers') . '">Accept or decline</a></p>');
    }
    exit;
}

/** Accepting creates a single-use code, valid 48 hours, that only works for that piece. */
function api_admin_offer_decide(string $id): void
{
    require_admin(true);
    $st = db()->prepare('SELECT o.*, p.name AS productName, p.slug, p.salePrice FROM Offer o JOIN Product p ON p.id = o.productId WHERE o.id = ?');
    $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) json_out(['error' => 'Not found'], 404);
    if ($o['status'] !== 'new') throw new InvalidArgumentException('This offer was already answered');
    $accept = (json_body()['action'] ?? '') === 'accept';
    $url = app_url() . '/product/' . rawurlencode($o['slug']);
    if ($accept) {
        $code = 'OFFER' . strtoupper(bin2hex(random_bytes(3)));
        $off = max(1, intdiv((int) $o['salePrice'] - (int) $o['amount'], 100));
        db()->prepare('INSERT INTO DiscountCode (code, kind, value, minOrder, maxUses, uses, expiresAt, active, createdAt, productId) VALUES (?,?,?,0,1,0,?,1,?,?)')
            ->execute([$code, 'fixed', $off, now_ms() + 48 * 3600 * 1000, now_ms(), $o['productId']]);
        db()->prepare("UPDATE Offer SET status = 'accepted', code = ? WHERE id = ?")->execute([$code, $id]);
        admin_log('offer_accept', $o['productName'] . ' at ' . naira((int) $o['amount']));
        respond_then_continue(['ok' => true, 'code' => $code]);
        send_mail($o['email'], 'Your offer was accepted! 🎉', '<p>Hi ' . e($o['name']) . ', good news — we accepted your offer of <strong>' . naira((int) $o['amount']) . '</strong> for ' . e($o['productName']) . '.</p>'
            . '<p>Use code <strong style="font-size:18px">' . $code . '</strong> at checkout within 48 hours. The piece is first come, first served until you pay.</p><p><a href="' . e($url) . '">Buy it now</a></p>');
    } else {
        db()->prepare("UPDATE Offer SET status = 'declined' WHERE id = ?")->execute([$id]);
        admin_log('offer_decline', $o['productName']);
        respond_then_continue(['ok' => true]);
        send_mail($o['email'], 'About your offer', '<p>Hi ' . e($o['name']) . ', thanks for your offer on ' . e($o['productName']) . '. We can\'t accept it this time, but it\'s still available at ' . naira((int) $o['salePrice']) . '.</p><p><a href="' . e($url) . '">View it</a></p>');
    }
    exit;
}

// ---------- "Sell to TK" requests ----------

function api_sell_submit(): void
{
    [$ok] = rate_limit('sell:' . client_ip(), 3, 3600);
    if (!$ok) json_out(['error' => 'Too many requests. Please try again later.'], 429);
    $d = $_POST;
    $name = str_field($d, 'name', 80);
    $phone = str_field($d, 'phone', 30);
    $email = strtolower(trim((string) ($d['email'] ?? '')));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email');
    $desc = str_field($d, 'description', 2000, true, 'Description');
    $photos = [];
    $files = $_FILES['photos'] ?? null;
    if ($files && is_array($files['name'])) {
        $dir = dirname(__DIR__) . '/uploads/sell';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        for ($i = 0; $i < min(4, count($files['name'])); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK || $files['size'][$i] > 5 * 1024 * 1024) continue;
            $info = @getimagesize($files['tmp_name'][$i]);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) continue;
            // Always re-encode: strips anything hidden in the file.
            $name2 = time() . '-' . bin2hex(random_bytes(6)) . '.jpg';
            save_resized_image($files['tmp_name'][$i], $info, "$dir/$name2", 1200, 1200, 'jpg');
            $photos[] = '/uploads/sell/' . $name2;
        }
    }
    $kind = in_array($d['kind'] ?? '', ['wholesale', 'request'], true) ? $d['kind'] : 'sell';
    db()->prepare('INSERT INTO SellRequest (id, name, phone, email, description, askingPrice, photos, createdAt, kind) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([new_id(), $name, $phone, $email, $desc, max(0, (int) ($d['askingPrice'] ?? 0)) * 100, json_encode($photos), now_ms(), $kind]);
    respond_then_continue(['ok' => true, 'message' => "Thanks! We'll review your pieces and reply on WhatsApp within 2 days."]);
    telegram_alert(['sell' => '👗 Sell request', 'wholesale' => '📦 Wholesale enquiry', 'request' => '🔎 Find-me-a-piece request'][$kind] . ' from ' . $name . ' · ' . $phone . "\n" . mb_substr($desc, 0, 300));
    exit;
}

function api_admin_sell_status(string $id): void
{
    require_admin(true);
    $status = (string) (json_body()['status'] ?? '');
    if (!in_array($status, ['new', 'contacted', 'bought', 'declined'], true)) throw new InvalidArgumentException('Unknown status');
    db()->prepare('UPDATE SellRequest SET status = ? WHERE id = ?')->execute([$status, $id]);
    admin_log('sell_status', $id . ' → ' . $status);
    json_out(['ok' => true]);
}

// ---------- bulk price drop ----------

function api_admin_price_drop(): void
{
    require_admin(true);
    $d = json_body() ?? [];
    $pct = (int) ($d['percent'] ?? 0);
    if ($pct < 1 || $pct > 80) throw new InvalidArgumentException('Percent must be between 1 and 80');
    $days = max(0, (int) ($d['olderThanDays'] ?? 0));
    $where = 'inStock = 1 AND ' . published_sql() . ' AND createdAt < ?';
    $params = [now_ms() - $days * 86400 * 1000];
    $cat = (string) ($d['category'] ?? '');
    if ($cat !== '' && isset(CATEGORIES[$cat]) && $cat !== 'all') { $where .= ' AND category = ?'; $params[] = $cat; }
    // Round to the nearest ₦100; never below ₦100.
    $st = db()->prepare("UPDATE Product SET salePrice = MAX(10000, CAST(ROUND(salePrice * (100 - ?) / 100.0 / 10000.0) AS INTEGER) * 10000) WHERE $where");
    $st->execute([$pct, ...$params]);
    admin_log('price_drop', "-$pct% on " . $st->rowCount() . ' items');
    $n = $st->rowCount();
    respond_then_continue(['ok' => true, 'updated' => $n]);
    $w = db()->query('SELECT DISTINCT productId FROM PriceWatch')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($w as $pid) notify_price_drops($pid);
    exit;
}
