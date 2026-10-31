<?php
/**
 * Batch 2: promotions (flash sale, bundles, free gift, tips), referrals and loyalty, birthday codes,
 * price-drop alerts, outfits ("complete the look"), blog, staff accounts, rider details, returns,
 * extra background jobs (birthdays, monthly report, weekly backup) and error alerts.
 */

const STAFF_PAGES = ['/admin/orders', '/admin/login'];
const STAFF_API = ['/api/admin/orders', '/api/admin/logout', '/api/admin/export/orders'];

function migrate_v4(PDO $pdo, callable $add): void
{
    foreach ([['videoUrl', "TEXT NOT NULL DEFAULT ''"], ['source', "TEXT NOT NULL DEFAULT ''"]] as [$c, $d]) $add('Product', $c, $d);
    foreach ([
        ['tip', 'INTEGER NOT NULL DEFAULT 0'], ['bundleDiscount', 'INTEGER NOT NULL DEFAULT 0'], ['freeGift', "TEXT NOT NULL DEFAULT ''"],
        ['riderName', "TEXT NOT NULL DEFAULT ''"], ['riderPhone', "TEXT NOT NULL DEFAULT ''"], ['trackingUrl', "TEXT NOT NULL DEFAULT ''"],
        ['returnStatus', "TEXT NOT NULL DEFAULT ''"], ['returnReason', "TEXT NOT NULL DEFAULT ''"], ['leaderboard', 'INTEGER NOT NULL DEFAULT 0'],
    ] as [$c, $d]) $add('Order', $c, $d);
    foreach ([
        ['bundleMin', 'INTEGER NOT NULL DEFAULT 0'], ['bundlePct', 'INTEGER NOT NULL DEFAULT 0'],
        ['freeGiftOver', 'INTEGER NOT NULL DEFAULT 0'], ['freeGiftText', "TEXT NOT NULL DEFAULT ''"],
        ['flashCategory', "TEXT NOT NULL DEFAULT ''"], ['flashPct', 'INTEGER NOT NULL DEFAULT 0'], ['flashEndsAt', 'INTEGER'],
        ['loyaltyPoints', 'INTEGER NOT NULL DEFAULT 0'], ['loyaltyReward', 'INTEGER NOT NULL DEFAULT 0'],
        ['referralReward', 'INTEGER NOT NULL DEFAULT 0'], ['birthdayPct', 'INTEGER NOT NULL DEFAULT 0'],
    ] as [$c, $d]) $add('SiteSettings', $c, $d);
    $add('SellRequest', 'kind', "TEXT NOT NULL DEFAULT 'sell'");
    $add('Review', 'featured', 'INTEGER NOT NULL DEFAULT 0');
    $add('Subscriber', 'birthday', "TEXT NOT NULL DEFAULT ''");
    $add('Subscriber', 'birthdayYear', 'INTEGER NOT NULL DEFAULT 0');
    $add('DiscountCode', 'ownerEmail', 'TEXT');
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS "Outfit" (
  "id" TEXT PRIMARY KEY, "title" TEXT NOT NULL, "slug" TEXT NOT NULL UNIQUE, "description" TEXT NOT NULL DEFAULT '',
  "imageUrl" TEXT NOT NULL DEFAULT '', "productIds" TEXT NOT NULL DEFAULT '[]', "createdAt" INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS "Post" (
  "id" TEXT PRIMARY KEY, "title" TEXT NOT NULL, "slug" TEXT NOT NULL UNIQUE, "body" TEXT NOT NULL,
  "imageUrl" TEXT NOT NULL DEFAULT '', "published" INTEGER NOT NULL DEFAULT 1, "createdAt" INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS "PriceWatch" (
  "email" TEXT NOT NULL, "productId" TEXT NOT NULL, "price" INTEGER NOT NULL, "createdAt" INTEGER NOT NULL,
  PRIMARY KEY ("email", "productId")
);
CREATE TABLE IF NOT EXISTS "ProductView" (
  "productId" TEXT NOT NULL, "visitor" TEXT NOT NULL, "at" INTEGER NOT NULL, PRIMARY KEY ("productId", "visitor")
);
CREATE TABLE IF NOT EXISTS "Loyalty" ("email" TEXT PRIMARY KEY, "points" INTEGER NOT NULL DEFAULT 0, "lifetime" INTEGER NOT NULL DEFAULT 0);
CREATE TABLE IF NOT EXISTS "Staff" (
  "id" TEXT PRIMARY KEY, "name" TEXT NOT NULL, "passwordHash" TEXT NOT NULL, "createdAt" INTEGER NOT NULL
);
SQL);
}

// ---------- prices ----------

function flash_active(): bool
{
    $s = settings();
    return (int) $s['flashPct'] > 0 && !empty($s['flashEndsAt']) && (int) $s['flashEndsAt'] > now_ms();
}

/** Price a shopper pays right now (applies a running flash sale). Kobo, rounded to ₦100. */
function effective_price(array $p): int
{
    $sale = (int) $p['salePrice'];
    if (!flash_active()) return $sale;
    $s = settings();
    if ($s['flashCategory'] !== '' && $s['flashCategory'] !== $p['category']) return $sale;
    return max(10000, (int) round($sale * (100 - (int) $s['flashPct']) / 100 / 10000) * 10000);
}

function bundle_discount(int $itemCount, int $subtotal): int
{
    $s = settings();
    if ((int) $s['bundleMin'] < 2 || (int) $s['bundlePct'] < 1 || $itemCount < (int) $s['bundleMin']) return 0;
    return (int) round($subtotal * min(50, (int) $s['bundlePct']) / 100);
}

function free_gift_for(int $subtotal): string
{
    $s = settings();
    return (int) $s['freeGiftOver'] > 0 && trim($s['freeGiftText']) !== '' && $subtotal >= (int) $s['freeGiftOver'] * 100 ? trim($s['freeGiftText']) : '';
}

// ---------- after a payment lands: loyalty points + referral rewards ----------

function issue_code(string $prefix, int $nairaOff, int $days, ?string $productId = null): string
{
    $code = $prefix . strtoupper(bin2hex(random_bytes(3)));
    db()->prepare('INSERT INTO DiscountCode (code, kind, value, minOrder, maxUses, uses, expiresAt, active, createdAt, productId) VALUES (?,?,?,0,1,0,?,1,?,?)')
        ->execute([$code, 'fixed', $nairaOff, now_ms() + $days * 86400 * 1000, now_ms(), $productId]);
    return $code;
}

function after_order_paid(array $o): void
{
    $s = settings();
    $email = strtolower($o['customerEmail']);
    $pdo = db();

    // Loyalty: 1 point per ₦1,000 paid. Reaching the target emails a reward code.
    if ((int) $s['loyaltyPoints'] > 0 && (int) $s['loyaltyReward'] > 0) {
        $pts = intdiv((int) $o['totalAmount'], 100000);
        $pdo->prepare('INSERT INTO Loyalty (email, points, lifetime) VALUES (?, ?, ?) ON CONFLICT(email) DO UPDATE SET points = points + excluded.points, lifetime = lifetime + excluded.lifetime')->execute([$email, $pts, $pts]);
        $st = $pdo->prepare('SELECT points FROM Loyalty WHERE email = ?');
        $st->execute([$email]);
        $now = (int) $st->fetchColumn();
        if ($now >= (int) $s['loyaltyPoints']) {
            $pdo->prepare('UPDATE Loyalty SET points = points - ? WHERE email = ?')->execute([(int) $s['loyaltyPoints'], $email]);
            $code = issue_code('LOYAL', (int) $s['loyaltyReward'], 60);
            send_mail($email, 'You earned a reward! 🎁', '<p>Hi ' . e($o['customerName']) . ', thanks for shopping with us again. You\'ve earned <strong>' . naira((int) $s['loyaltyReward'] * 100) . ' off</strong> your next order.</p><p>Code: <strong style="font-size:18px">' . $code . '</strong> (valid 60 days)</p>');
        } else {
            send_mail($email, 'You earned ' . $pts . ' points', '<p>You now have <strong>' . $now . ' of ' . (int) $s['loyaltyPoints'] . '</strong> points. At ' . (int) $s['loyaltyPoints'] . ' we email you ' . naira((int) $s['loyaltyReward'] * 100) . ' off.</p>');
        }
    }

    if ((int) $s['referralReward'] > 0) {
        // Reward the friend who referred this buyer.
        if (!empty($o['discountCode'])) {
            $st = $pdo->prepare('SELECT ownerEmail FROM DiscountCode WHERE code = ?');
            $st->execute([$o['discountCode']]);
            $owner = $st->fetchColumn();
            if ($owner && $owner !== $email) {
                $code = issue_code('THANKS', (int) $s['referralReward'], 60);
                send_mail($owner, 'Your friend just ordered — here\'s ' . naira((int) $s['referralReward'] * 100), '<p>Someone used your referral code. Thank you! Here\'s <strong>' . naira((int) $s['referralReward'] * 100) . ' off</strong> your next order: <strong style="font-size:18px">' . $code . '</strong> (valid 60 days).</p>');
            }
        }
        // Give this buyer their own referral code (once).
        $st = $pdo->prepare('SELECT code FROM DiscountCode WHERE ownerEmail = ?');
        $st->execute([$email]);
        if (!$st->fetchColumn()) {
            $code = 'REF' . strtoupper(substr(preg_replace('/[^a-z]/i', '', $o['customerName']), 0, 4)) . strtoupper(bin2hex(random_bytes(2)));
            $pdo->prepare('INSERT INTO DiscountCode (code, kind, value, minOrder, maxUses, uses, expiresAt, active, createdAt, ownerEmail) VALUES (?,?,?,0,0,0,NULL,1,?,?)')
                ->execute([$code, 'fixed', (int) $s['referralReward'], now_ms(), $email]);
            send_mail($email, 'Give ' . naira((int) $s['referralReward'] * 100) . ', get ' . naira((int) $s['referralReward'] * 100),
                '<p>Share your code <strong style="font-size:18px">' . $code . '</strong> with friends. They get ' . naira((int) $s['referralReward'] * 100) . ' off, and you get ' . naira((int) $s['referralReward'] * 100) . ' off every time one of them orders.</p>');
        }
    }
}

// ---------- price-drop alerts ----------

function notify_price_drops(string $productId): void
{
    $p = fetch_products('id = ?', [$productId], '');
    if (!$p || product_availability($p[0]) !== 'available') return;
    $p = $p[0];
    $now = effective_price($p);
    $st = db()->prepare('SELECT email, price FROM PriceWatch WHERE productId = ? AND price > ?');
    $st->execute([$productId, $now]);
    foreach ($st->fetchAll() as $w) {
        send_mail($w['email'], 'Price drop: ' . $p['name'], '<p><strong>' . e($p['name']) . '</strong> dropped from ' . naira((int) $w['price']) . ' to <strong>' . naira($now) . '</strong>. It\'s one of one — grab it before someone else does.</p><p><a href="' . e(app_url() . '/product/' . rawurlencode($p['slug'])) . '">View it</a></p>');
        db()->prepare('DELETE FROM PriceWatch WHERE email = ? AND productId = ?')->execute([$w['email'], $productId]);
    }
}

// ---------- "people viewing" ----------

function record_view_and_count(string $productId): int
{
    $visitor = substr(hash('sha256', client_ip() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . date('Y-m-d')), 0, 24);
    $now = now_ms();
    db()->prepare('REPLACE INTO ProductView (productId, visitor, at) VALUES (?,?,?)')->execute([$productId, $visitor, $now]);
    if (random_int(1, 50) === 1) db()->prepare('DELETE FROM ProductView WHERE at < ?')->execute([$now - 86400 * 1000]);
    $st = db()->prepare('SELECT COUNT(*) FROM ProductView WHERE productId = ? AND at > ?');
    $st->execute([$productId, $now - 15 * 60 * 1000]);
    return (int) $st->fetchColumn();
}

// ---------- email with an attachment (weekly backup) ----------

function send_mail_attachment(string $to, string $subject, string $html, string $filePath, string $fileName): bool
{
    $boundary = 'b' . bin2hex(random_bytes(8));
    $s = settings();
    $headers = 'MIME-Version: 1.0' . "\r\n" . 'From: ' . mb_encode_mimeheader($s['storeName']) . ' <' . mail_from() . '>' . "\r\n"
        . 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    $body = "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n$html\r\n"
        . "--$boundary\r\nContent-Type: application/octet-stream; name=\"$fileName\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$fileName\"\r\n\r\n"
        . chunk_split(base64_encode((string) file_get_contents($filePath))) . "--$boundary--";
    return @mail($to, mb_encode_mimeheader($subject), $body, $headers);
}

/** Birthday codes, monthly report and weekly backup. Called from run_maintenance(). */
function run_maintenance_extra(): void
{
    $s = settings();
    $pdo = db();
    $owner = filter_var($s['contactEmail'], FILTER_VALIDATE_EMAIL) ? $s['contactEmail'] : '';

    // Birthday discount codes (once a year per subscriber).
    if ((int) $s['birthdayPct'] > 0) {
        $st = $pdo->prepare('SELECT DISTINCT email FROM Subscriber WHERE birthday = ? AND birthdayYear < ?');
        $st->execute([date('m-d'), (int) date('Y')]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $email) {
            $pdo->prepare('UPDATE Subscriber SET birthdayYear = ? WHERE email = ?')->execute([(int) date('Y'), $email]);
            $code = 'BDAY' . strtoupper(bin2hex(random_bytes(3)));
            $pdo->prepare('INSERT INTO DiscountCode (code, kind, value, minOrder, maxUses, uses, expiresAt, active, createdAt) VALUES (?,?,?,0,1,0,?,1,?)')
                ->execute([$code, 'percent', min(50, (int) $s['birthdayPct']), now_ms() + 7 * 86400 * 1000, now_ms()]);
            send_mail($email, 'Happy birthday from ' . $s['storeName'] . ' 🎂', '<p>Happy birthday! Treat yourself: <strong>' . (int) $s['birthdayPct'] . '% off</strong> with code <strong style="font-size:18px">' . $code . '</strong> — valid for 7 days.</p><p><a href="' . e(app_url() . '/shop') . '">Shop now</a></p>');
        }
    }

    // Monthly report on the 1st.
    $month = date('Y-m');
    if ((int) date('j') === 1 && meta_get('report_month') !== $month) {
        meta_set('report_month', $month);
        $from = strtotime('first day of last month 00:00') * 1000;
        $to = strtotime('first day of this month 00:00') * 1000;
        $q = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(totalAmount), 0), COALESCE(SUM(discountAmount + bundleDiscount), 0) FROM \"Order\" WHERE status = 'paid' AND createdAt BETWEEN ? AND ?");
        $q->execute([$from, $to]);
        [$n, $rev, $disc] = $q->fetch(PDO::FETCH_NUM);
        $c = $pdo->prepare("SELECT COALESCE(SUM((oi.price - p.cost) * oi.quantity), 0) FROM OrderItem oi JOIN \"Order\" o ON o.id = oi.orderId JOIN Product p ON p.id = oi.productId WHERE o.status = 'paid' AND o.createdAt BETWEEN ? AND ?");
        $c->execute([$from, $to]);
        $profit = (int) $c->fetchColumn() - (int) $disc;
        $top = $pdo->prepare("SELECT p.category, COUNT(*) n FROM OrderItem oi JOIN \"Order\" o ON o.id = oi.orderId JOIN Product p ON p.id = oi.productId WHERE o.status = 'paid' AND o.createdAt BETWEEN ? AND ? GROUP BY p.category ORDER BY n DESC LIMIT 3");
        $top->execute([$from, $to]);
        $cats = implode(', ', array_map(fn($r) => $r['category'] . ' (' . $r['n'] . ')', $top->fetchAll()));
        $listed = $pdo->prepare('SELECT COUNT(*) FROM Product WHERE createdAt BETWEEN ? AND ?');
        $listed->execute([$from, $to]);
        $subs = (int) $pdo->query('SELECT COUNT(DISTINCT email) FROM Subscriber')->fetchColumn();
        $label = date('F Y', intdiv($from, 1000));
        $text = "$label report\nPaid orders: $n\nRevenue: " . naira((int) $rev) . "\nProfit (where cost entered): " . naira($profit)
            . "\nTop categories: " . ($cats ?: '—') . "\nNew items listed: " . (int) $listed->fetchColumn() . "\nEmail subscribers: $subs";
        if ($owner) send_mail($owner, $label . ' report', '<p>' . nl2br(e($text)) . '</p>');
        telegram_alert('📈 ' . $text);
    }

    // Weekly database backup by email on Mondays (skipped if too big for email).
    $week = date('o-W');
    if ($owner && (int) date('N') === 1 && meta_get('backup_week') !== $week) {
        meta_set('backup_week', $week);
        $tmp = tempnam(sys_get_temp_dir(), 'tkbk');
        @unlink($tmp);
        try {
            $pdo->exec('VACUUM INTO ' . $pdo->quote($tmp));
            if (filesize($tmp) <= 7 * 1024 * 1024) {
                send_mail_attachment($owner, 'Weekly store backup ' . date('j M Y'), '<p>Your weekly database backup is attached (products, orders, settings). Keep it somewhere safe.</p>', $tmp, 'tk-store-' . date('Y-m-d') . '.db');
            } else {
                send_mail($owner, 'Weekly backup too large for email', '<p>Please download it from Admin → Security → Download database backup.</p>');
            }
        } finally {
            @unlink($tmp);
        }
    }
}

/** Emails/Telegrams the owner about checkout or payment errors, at most once an hour. */
function alert_owner_of_error(string $message): void
{
    static $busy = false;
    if ($busy) return;
    $busy = true;
    try {
        $flag = data_dir() . '/last-error-alert';
        if (is_file($flag) && time() - (int) @file_get_contents($flag) < 3600) return;
        @file_put_contents($flag, (string) time());
        $text = '⚠️ Store error: ' . mb_substr($message, 0, 500) . "\nCheck that checkout still works.";
        telegram_alert($text);
        $s = settings();
        if (filter_var($s['contactEmail'], FILTER_VALIDATE_EMAIL)) @mail($s['contactEmail'], 'Store error alert', $text, 'From: ' . mail_from());
    } catch (Throwable $ex) {
        // Never let alerting break the page.
    } finally {
        $busy = false;
    }
}

// ---------- staff accounts ----------

function admin_role(): string
{
    start_session();
    return (string) ($_SESSION['role'] ?? 'owner');
}

function staff_may_open(string $path): bool
{
    if (admin_role() !== 'staff') return true;
    if (in_array($path, STAFF_PAGES, true) || preg_match('#^/admin/orders/[^/]+/slip$#', $path)) return true;
    foreach (STAFF_API as $prefix) if (str_starts_with($path, $prefix)) return true;
    return false;
}

/** Checks a password against staff accounts. Returns the staff name or null. */
function staff_login(string $password): ?string
{
    foreach (db()->query('SELECT name, passwordHash FROM Staff')->fetchAll() as $st) {
        if (password_verify($password, $st['passwordHash'])) return $st['name'];
    }
    return null;
}

// ---------- extra API endpoints ----------

function api_price_watch(): void
{
    [$ok] = rate_limit('watch:' . client_ip(), 20, 3600);
    if (!$ok) json_out(['error' => 'Too many requests'], 429);
    $d = json_body() ?? [];
    $email = strtolower(trim((string) ($d['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email');
    $p = fetch_products('id = ?', [(string) ($d['productId'] ?? '')], '');
    if (!$p || product_availability($p[0]) === 'scheduled') throw new InvalidArgumentException('Item not found');
    db()->prepare('REPLACE INTO PriceWatch (email, productId, price, createdAt) VALUES (?,?,?,?)')->execute([$email, $p[0]['id'], effective_price($p[0]), now_ms()]);
    json_out(['ok' => true, 'message' => "Done — we'll email you if the price drops."]);
}

function api_admin_save_post(?string $id = null): void
{
    require_admin(true);
    $d = json_body() ?? [];
    $title = str_field($d, 'title', 200);
    $body = str_field($d, 'body', 30000, true, 'Post');
    $image = trim((string) ($d['imageUrl'] ?? ''));
    if ($image !== '' && !preg_match('#^(https://|/uploads/|/tk-)#', $image)) throw new InvalidArgumentException('Image must be uploaded or an https link');
    $slug = slugify((string) ($d['slug'] ?? '') ?: $title) ?: 'post';
    $st = db()->prepare('SELECT id FROM Post WHERE slug = ?' . ($id ? ' AND id != ?' : ''));
    $st->execute($id ? [$slug, $id] : [$slug]);
    if ($st->fetch()) $slug .= '-' . base_convert((string) now_ms(), 10, 36);
    $pub = !empty($d['published']) ? 1 : 0;
    if ($id) {
        db()->prepare('UPDATE Post SET title = ?, slug = ?, body = ?, imageUrl = ?, published = ? WHERE id = ?')->execute([$title, $slug, $body, $image, $pub, $id]);
    } else {
        $id = new_id();
        db()->prepare('INSERT INTO Post (id, title, slug, body, imageUrl, published, createdAt) VALUES (?,?,?,?,?,?,?)')->execute([$id, $title, $slug, $body, $image, $pub, now_ms()]);
    }
    admin_log('post_save', $title);
    json_out(['ok' => true, 'id' => $id, 'slug' => $slug]);
}

function api_admin_update_post(string $id): void { api_admin_save_post($id); }

function api_admin_delete_post(string $id): void
{
    require_admin(true);
    db()->prepare('DELETE FROM Post WHERE id = ?')->execute([$id]);
    admin_log('post_delete', $id);
    json_out(['ok' => true]);
}

function api_admin_save_outfit(): void
{
    require_admin(true);
    $d = json_body() ?? [];
    $title = str_field($d, 'title', 150);
    $ids = array_values(array_unique(array_filter(array_map('strval', (array) ($d['productIds'] ?? [])))));
    if (count($ids) < 2 || count($ids) > 8) throw new InvalidArgumentException('Pick between 2 and 8 pieces');
    $found = fetch_products('id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ')', $ids, '');
    if (count($found) !== count($ids)) throw new InvalidArgumentException('Some pieces no longer exist');
    $image = trim((string) ($d['imageUrl'] ?? ''));
    $slug = slugify($title) ?: 'look';
    $st = db()->prepare('SELECT 1 FROM Outfit WHERE slug = ?');
    $st->execute([$slug]);
    if ($st->fetch()) $slug .= '-' . base_convert((string) now_ms(), 10, 36);
    db()->prepare('INSERT INTO Outfit (id, title, slug, description, imageUrl, productIds, createdAt) VALUES (?,?,?,?,?,?,?)')
        ->execute([new_id(), $title, $slug, str_field($d, 'description', 1000, false), $image, json_encode($ids), now_ms()]);
    admin_log('outfit_save', $title);
    json_out(['ok' => true]);
}

function api_admin_delete_outfit(string $id): void
{
    require_admin(true);
    db()->prepare('DELETE FROM Outfit WHERE id = ?')->execute([$id]);
    admin_log('outfit_delete', $id);
    json_out(['ok' => true]);
}

function api_admin_add_staff(): void
{
    require_admin(true);
    if (admin_role() !== 'owner') json_out(['error' => 'Only the owner can manage staff'], 403);
    $d = json_body() ?? [];
    $name = str_field($d, 'name', 60);
    $pw = (string) ($d['password'] ?? '');
    if (strlen($pw) < 10) throw new InvalidArgumentException('Staff password must be at least 10 characters');
    if (hash_equals((string) config('ADMIN_PASSWORD'), $pw)) throw new InvalidArgumentException('Use a different password from the owner');
    db()->prepare('INSERT INTO Staff (id, name, passwordHash, createdAt) VALUES (?,?,?,?)')->execute([new_id(), $name, password_hash($pw, PASSWORD_DEFAULT), now_ms()]);
    admin_log('staff_add', $name);
    json_out(['ok' => true]);
}

function api_admin_delete_staff(string $id): void
{
    require_admin(true);
    if (admin_role() !== 'owner') json_out(['error' => 'Only the owner can manage staff'], 403);
    db()->prepare('DELETE FROM Staff WHERE id = ?')->execute([$id]);
    admin_log('staff_delete', $id);
    json_out(['ok' => true]);
}

function api_admin_feature_review(string $id): void
{
    require_admin(true);
    db()->exec('UPDATE Review SET featured = 0');
    db()->prepare('UPDATE Review SET featured = 1, approved = 1 WHERE id = ?')->execute([$id]);
    admin_log('review_feature', $id);
    json_out(['ok' => true]);
}

/** Short product clips (MP4/WebM, up to 6MB). The bytes are checked, not the file name. */
function api_admin_upload_video(): void
{
    require_admin(true);
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_out(['error' => 'Video must be 6MB or smaller'], 400);
    if ($f['size'] > 6 * 1024 * 1024) json_out(['error' => 'Video must be 6MB or smaller'], 400);
    $head = (string) file_get_contents($f['tmp_name'], false, null, 0, 16);
    $ext = substr($head, 4, 4) === 'ftyp' ? 'mp4' : (str_starts_with($head, "\x1A\x45\xDF\xA3") ? 'webm' : null);
    if (!$ext) json_out(['error' => 'Only MP4 or WebM videos are allowed'], 400);
    $dir = dirname(__DIR__) . '/uploads/video';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = time() . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    move_uploaded_file($f['tmp_name'], "$dir/$name");
    json_out(['url' => '/uploads/video/' . $name]);
}
