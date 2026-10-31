<?php
/**
 * Core setup shared by every request: config, database, and small helpers.
 * The database and config live OUTSIDE the web root (see data_dir()).
 */

declare(strict_types=1);

date_default_timezone_set('Africa/Lagos');

/** Local dev keeps data in php-app/data; on cPanel it lives in ~/tk-data. */
function data_dir(): string
{
    static $dir = null;
    if ($dir !== null) return $dir;
    $candidates = [
        dirname(__DIR__, 2) . '/data',      // php-app/data (local)
        dirname(__DIR__, 3) . '/tk-data',   // ~/tk-data (cPanel: ~/public_html/<site>/app)
    ];
    foreach ($candidates as $c) {
        if (is_file($c . '/config.php')) return $dir = $c;
    }
    http_response_code(500);
    exit('Store is not configured: config.php not found.');
}

function config(string $key, $default = '')
{
    static $cfg = null;
    if ($cfg === null) $cfg = require data_dir() . '/config.php';
    return $cfg[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . data_dir() . '/store.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    ensure_schema($pdo);
    migrate_schema($pdo);
    return $pdo;
}

/** Creates tables on a fresh database. Matches the original Prisma layout. */
function ensure_schema(PDO $pdo): void
{
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS "Product" (
  "id" TEXT PRIMARY KEY, "name" TEXT NOT NULL, "slug" TEXT NOT NULL UNIQUE,
  "description" TEXT NOT NULL, "originalPrice" INTEGER NOT NULL, "salePrice" INTEGER NOT NULL,
  "category" TEXT NOT NULL, "size" TEXT NOT NULL, "condition" TEXT NOT NULL,
  "imageUrl" TEXT NOT NULL, "images" TEXT NOT NULL DEFAULT '[]',
  "featured" BOOLEAN NOT NULL DEFAULT 0, "inStock" BOOLEAN NOT NULL DEFAULT 1,
  "reservedUntil" DATETIME, "createdAt" DATETIME NOT NULL
);
CREATE TABLE IF NOT EXISTS "Order" (
  "id" TEXT PRIMARY KEY, "reference" TEXT NOT NULL UNIQUE, "idempotencyKey" TEXT UNIQUE,
  "authorizationUrl" TEXT, "customerName" TEXT NOT NULL, "customerEmail" TEXT NOT NULL,
  "customerPhone" TEXT NOT NULL, "shippingAddress" TEXT NOT NULL, "city" TEXT NOT NULL,
  "state" TEXT NOT NULL, "totalAmount" INTEGER NOT NULL, "status" TEXT NOT NULL DEFAULT 'pending',
  "paystackRef" TEXT, "createdAt" DATETIME NOT NULL, "updatedAt" DATETIME NOT NULL
);
CREATE TABLE IF NOT EXISTS "OrderItem" (
  "id" TEXT PRIMARY KEY, "orderId" TEXT NOT NULL, "productId" TEXT NOT NULL,
  "quantity" INTEGER NOT NULL, "price" INTEGER NOT NULL, "size" TEXT NOT NULL,
  FOREIGN KEY ("orderId") REFERENCES "Order"("id") ON DELETE CASCADE,
  FOREIGN KEY ("productId") REFERENCES "Product"("id")
);
CREATE TABLE IF NOT EXISTS "SiteSettings" (
  "id" TEXT PRIMARY KEY DEFAULT 'default', "storeName" TEXT NOT NULL, "logoUrl" TEXT NOT NULL DEFAULT '',
  "tagline" TEXT NOT NULL, "heroTitle" TEXT NOT NULL, "heroSubtitle" TEXT NOT NULL,
  "heroImageUrl" TEXT NOT NULL, "aboutTitle" TEXT NOT NULL, "aboutText" TEXT NOT NULL,
  "contactEmail" TEXT NOT NULL, "contactAddress" TEXT NOT NULL, "customerCarePhone" TEXT NOT NULL,
  "customerCareHours" TEXT NOT NULL, "instagramUrl" TEXT NOT NULL DEFAULT '', "tiktokUrl" TEXT NOT NULL DEFAULT '',
  "whatsappNumber" TEXT NOT NULL DEFAULT '', "colorCream" TEXT NOT NULL, "colorSand" TEXT NOT NULL,
  "colorTerracotta" TEXT NOT NULL, "colorRust" TEXT NOT NULL, "colorOlive" TEXT NOT NULL,
  "colorCharcoal" TEXT NOT NULL, "colorWarmgray" TEXT NOT NULL, "updatedAt" DATETIME NOT NULL
);
CREATE TABLE IF NOT EXISTS "PushSubscription" (
  "id" TEXT PRIMARY KEY, "endpoint" TEXT NOT NULL UNIQUE, "p256dh" TEXT NOT NULL,
  "auth" TEXT NOT NULL, "createdAt" DATETIME NOT NULL
);
CREATE TABLE IF NOT EXISTS "RateLimit" (
  "key" TEXT PRIMARY KEY, "count" INTEGER NOT NULL, "resetAt" INTEGER NOT NULL
);
SQL);
}

// ---------- small helpers ----------

/** Milliseconds since epoch — the date format the existing data uses. */
function now_ms(): int
{
    return (int) floor(microtime(true) * 1000);
}

function new_id(): string
{
    return 'c' . base_convert((string) now_ms(), 10, 36) . bin2hex(random_bytes(8));
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Amounts are stored in kobo (NGN × 100). */
function naira(int $kobo): string
{
    return '₦' . number_format($kobo / 100, 0, '.', ',');
}

function discount_percent(int $original, int $sale): int
{
    return $original <= $sale ? 0 : (int) round(($original - $sale) / $original * 100);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^\w\s-]/u', '', $text);
    $text = preg_replace('/[\s_-]+/', '-', $text);
    return trim($text, '-');
}

function app_url(): string
{
    return rtrim((string) config('APP_URL', ''), '/');
}

function json_out($data, int $status = 200, array $headers = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    foreach ($headers as $k => $v) header("$k: $v");
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_body(): ?array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : null;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function log_error(string $message, $context = null): void
{
    error_log('[tk-store] ' . $message . ($context !== null ? ' ' . json_encode($context) : ''));
    if (function_exists('alert_owner_of_error') && preg_match('/^(Paystack|API error|Page error)/', $message)) {
        alert_owner_of_error($message . ($context !== null ? ': ' . (is_string($context) ? $context : json_encode($context)) : ''));
    }
}

/** Fixed-window rate limit stored in the database (works across PHP workers). */
function rate_limit(string $key, int $limit, int $windowSeconds): array
{
    $pdo = db();
    $now = time();
    $row = $pdo->prepare('SELECT count, resetAt FROM RateLimit WHERE key = ?');
    $row->execute([$key]);
    $b = $row->fetch();
    if (!$b || $b['resetAt'] < $now) {
        $pdo->prepare('REPLACE INTO RateLimit (key, count, resetAt) VALUES (?, 1, ?)')
            ->execute([$key, $now + $windowSeconds]);
        return [true, 0];
    }
    if ($b['count'] >= $limit) return [false, $b['resetAt'] - $now];
    $pdo->prepare('UPDATE RateLimit SET count = count + 1 WHERE key = ?')->execute([$key]);
    return [true, 0];
}

// ---------- products ----------

const CATEGORIES = [
    'all' => 'All', 'tops' => 'Tops', 'bottoms' => 'Bottoms', 'dresses' => 'Dresses',
    'outerwear' => 'Outerwear', 'accessories' => 'Accessories',
];
const CONDITIONS = ['New with tags', 'Like New', 'Excellent', 'Good', 'Fair'];
const MAX_ITEM_PRICE_NAIRA = 5000000;
const RESERVATION_MINUTES = 15;
const NEW_ARRIVAL_DAYS = 7;

/** SQL fragment for products a shopper can buy right now. */
function available_sql(string $alias = ''): string
{
    $p = $alias ? "$alias." : '';
    return "{$p}inStock = 1 AND ({$p}reservedUntil IS NULL OR {$p}reservedUntil < " . now_ms() . ') AND ' . published_sql($alias);
}

/** Products whose scheduled drop time has passed (or that have none). */
function published_sql(string $alias = ''): string
{
    $p = $alias ? "$alias." : '';
    return "({$p}publishAt IS NULL OR {$p}publishAt <= " . now_ms() . ')';
}

function product_availability(array $p): string
{
    if (!$p['inStock']) return 'sold';
    if (!empty($p['publishAt']) && (int) $p['publishAt'] > now_ms()) return 'scheduled';
    if ($p['reservedUntil'] && (int) $p['reservedUntil'] > now_ms()) return 'reserved';
    return 'available';
}

function product_images(array $p): array
{
    $extra = json_decode($p['images'] ?? '[]', true);
    $extra = is_array($extra) ? array_filter($extra, fn($x) => is_string($x) && trim($x) !== '') : [];
    return array_values(array_unique(array_filter(array_merge([$p['imageUrl']], $extra))));
}

function is_new_arrival(array $p): bool
{
    return now_ms() - (int) $p['createdAt'] < NEW_ARRIVAL_DAYS * 86400 * 1000;
}

/** Casts SQLite values to the JSON shapes the browser code expects. */
function product_json(array $p): array
{
    $p['originalPrice'] = (int) $p['originalPrice'];
    $p['salePrice'] = (int) $p['salePrice'];
    $p['featured'] = (bool) $p['featured'];
    $p['inStock'] = (bool) $p['inStock'];
    $p['images'] = json_decode($p['images'] ?: '[]', true) ?: [];
    $p['createdAt'] = (int) $p['createdAt'];
    $p['reservedUntil'] = $p['reservedUntil'] === null ? null : (int) $p['reservedUntil'];
    $p['publishAt'] = empty($p['publishAt']) ? null : (int) $p['publishAt'];
    unset($p['announced']);
    return $p;
}

function fetch_products(string $where = '1', array $params = [], string $tail = 'ORDER BY createdAt DESC'): array
{
    $st = db()->prepare("SELECT * FROM Product WHERE $where $tail");
    $st->execute($params);
    return $st->fetchAll();
}

function count_products(string $where = '1', array $params = []): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM Product WHERE $where");
    $st->execute($params);
    return (int) $st->fetchColumn();
}

// ---------- settings ----------

const DEFAULT_SETTINGS = [
    'id' => 'default',
    'storeName' => 'T.K Collectibles',
    'logoUrl' => '/tk-logo.png',
    'tagline' => 'Spend less, wear luxury',
    'heroTitle' => 'Unique thrift pieces, curated for you',
    'heroSubtitle' => 'One-of-one secondhand fashion — the same items from Instagram and TikTok, now shoppable with secure Paystack checkout.',
    'heroImageUrl' => 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=1600&q=80',
    'aboutTitle' => 'From DMs to your doorstep',
    'aboutText' => 'We started on social media — sharing daily thrift finds with our community. Each item is photographed honestly: real condition, real measurements.',
    'contactEmail' => 'hello@curatedthrift.com',
    'contactAddress' => 'Lagos, Nigeria',
    'customerCarePhone' => '+234 903 413 1872',
    'customerCareHours' => 'Mon–Sat, 9am–6pm WAT',
    'instagramUrl' => 'https://instagram.com/yourstore',
    'tiktokUrl' => 'https://tiktok.com/@yourstore',
    'whatsappNumber' => '2349034131872',
    'colorCream' => '#F6EDE8',
    'colorSand' => '#EBDDD5',
    'colorTerracotta' => '#7A1F2B',
    'colorRust' => '#5E1620',
    'colorOlive' => '#5C6B4A',
    'colorCharcoal' => '#2C2825',
    'colorWarmgray' => '#6B6560',
];

function settings(): array
{
    static $s = null;
    if ($s !== null) return $s;
    $s = db()->query("SELECT * FROM SiteSettings WHERE id = 'default'")->fetch();
    if (!$s) {
        $s = DEFAULT_SETTINGS + ['updatedAt' => now_ms()];
        $cols = array_keys($s);
        $sql = 'INSERT INTO SiteSettings ("' . implode('","', $cols) . '") VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
        db()->prepare($sql)->execute(array_values($s));
    }
    return $s;
}

function theme_style(array $s): string
{
    $map = [
        'cream' => 'colorCream', 'sand' => 'colorSand', 'terracotta' => 'colorTerracotta',
        'rust' => 'colorRust', 'olive' => 'colorOlive', 'charcoal' => 'colorCharcoal', 'warmgray' => 'colorWarmgray',
    ];
    $out = [];
    foreach ($map as $var => $key) {
        $hex = preg_match('/^#[0-9a-fA-F]{3,8}$/', $s[$key] ?? '') ? $s[$key] : DEFAULT_SETTINGS[$key];
        // Space-separated RGB channels let Tailwind opacity modifiers (bg-sand/40) work.
        $out[] = "--color-$var: " . hex_to_rgb_channels($hex);
    }
    return implode('; ', $out);
}

function hex_to_rgb_channels(string $hex): string
{
    $h = ltrim($hex, '#');
    if (strlen($h) === 3) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    $h = substr($h, 0, 6);
    return hexdec(substr($h, 0, 2)) . ' ' . hexdec(substr($h, 2, 2)) . ' ' . hexdec(substr($h, 4, 2));
}

function tel_link(string $phone): string
{
    return 'tel:' . preg_replace('/\s/', '', $phone);
}

function whatsapp_link(string $number): string
{
    return 'https://wa.me/' . preg_replace('/\D/', '', $number);
}

// ---------- admin auth (PHP session + CSRF token) ----------

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('tk_admin');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 7));
    session_start();
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function is_admin(): bool
{
    start_session();
    return !empty($_SESSION['admin']) && ($_SESSION['expires'] ?? 0) > time();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

/** Every /api/admin/* call goes through here. Mutating calls also need the CSRF header. */
function require_admin(bool $mutating = false): void
{
    if (!is_admin()) json_out(['error' => 'Unauthorized'], 401);
    if (!staff_may_open((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH))) json_out(['error' => 'Staff accounts can only manage orders'], 403);
    if ($mutating) {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$header || !hash_equals(csrf_token(), $header)) {
            json_out(['error' => 'Invalid or missing CSRF token'], 403);
        }
    }
}

require_once __DIR__ . '/features.php';
