<?php
/** Front controller: every request that isn't a real file comes through here. */

declare(strict_types=1);

const ASSET_VERSION = '4';

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/icons.php';
require __DIR__ . '/app/layout.php';

$path = '/' . trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// PHP's built-in dev server: let it serve real static files directly.
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.googletagmanager.com https://connect.facebook.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://www.facebook.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");

// Hidden admin address: with ADMIN_PATH set, /admin is a 404 and the secret path serves the panel.
$adminBase = admin_base();
if ($adminBase !== '/admin') {
    if ($path === '/admin' || str_starts_with($path, '/admin/')) $path = '/__hidden__';
    elseif ($path === $adminBase || str_starts_with($path, $adminBase . '/')) $path = '/admin' . substr($path, strlen($adminBase));
}

try {
    if (str_starts_with($path, '/api/')) {
        require __DIR__ . '/app/api.php';
        handle_api($path, $method);
        exit;
    }

    if ($path === '/manifest.webmanifest') {
        $s = settings();
        header('Content-Type: application/manifest+json');
        echo json_encode([
            'name' => $s['storeName'],
            'short_name' => $s['storeName'],
            'description' => $s['tagline'],
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => $s['colorCream'],
            'theme_color' => $s['colorTerracotta'],
            'icons' => [['src' => '/tk-icon.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($path === '/admin' || str_starts_with($path, '/admin/')) {
        header('Cache-Control: no-store');
        require __DIR__ . '/app/admin.php';
        require __DIR__ . '/app/admin_more.php';
        require __DIR__ . '/app/promo_pages.php';
        if ($adminBase !== '/admin') ob_start(fn($html) => str_replace(['"/admin', "'/admin"], ['"' . $adminBase, "'" . $adminBase], $html));
        match (true) {
            $path === '/admin/login' => admin_login_page(),
            $path === '/admin' => admin_dashboard(),
            $path === '/admin/products' => admin_products_page(),
            $path === '/admin/products/new' => admin_product_form(),
            $path === '/admin/products/bulk' => admin_bulk_page(),
            $path === '/admin/discounts' => admin_discounts_page(),
            $path === '/admin/reviews' => admin_reviews_page(),
            $path === '/admin/activity' => admin_activity_page(),
            $path === '/admin/security' => admin_security_page(),
            $path === '/admin/offers' => admin_offers_page(),
            $path === '/admin/looks' => admin_looks_page(),
            $path === '/admin/blog' => admin_blog_page(),
            $path === '/admin/blog/new' => admin_post_editor(null),
            (bool) preg_match('#^/admin/blog/([^/]+)$#', $path, $m) => admin_post_editor($m[1]),
            $path === '/admin/sell' => admin_sell_page(),
            (bool) preg_match('#^/admin/orders/([^/]+)/slip$#', $path, $m) => admin_packing_slip($m[1]),
            (bool) preg_match('#^/admin/products/([^/]+)$#', $path, $m) => admin_edit_product($m[1]),
            $path === '/admin/orders' => admin_orders_page(),
            $path === '/admin/settings' => admin_settings_page(),
            default => not_found_page(),
        };
        exit;
    }

    require __DIR__ . '/app/pages.php';
    require __DIR__ . '/app/promo_pages.php';
    match (true) {
        $path === '/' => page_home(),
        $path === '/shop' => page_shop(),
        (bool) preg_match('#^/product/([^/]+)$#', $path, $m) => page_product($m[1]),
        $path === '/cart' => page_cart(),
        $path === '/checkout' => page_checkout(),
        $path === '/contact' => page_contact(),
        $path === '/order/success' => page_order_success(),
        $path === '/track' => page_track(),
        $path === '/wishlist' => page_wishlist(),
        $path === '/unsubscribe' => page_unsubscribe(),
        $path === '/sitemap.xml' => sitemap_xml(),
        $path === '/robots.txt' => robots_txt(),
        $path === '/feed/products.csv' => product_feed_csv(),
        $path === '/returns' => page_policy('returnsPolicy', 'Returns & refunds'),
        $path === '/terms' => page_policy('termsText', 'Terms of sale'),
        $path === '/privacy' => page_policy('privacyText', 'Privacy policy'),
        $path === '/size-guide' => page_policy('sizeGuide', 'Size guide'),
        $path === '/sell' => page_sell(),
        $path === '/looks' => page_looks(),
        (bool) preg_match('#^/looks/([^/]+)$#', $path, $m) => page_look($m[1]),
        $path === '/blog' => page_blog(),
        (bool) preg_match('#^/blog/([^/]+)$#', $path, $m) => page_post($m[1]),
        $path === '/quiz' => page_quiz(),
        $path === '/top-buyers' => page_top_buyers(),
        $path === '/wholesale' => page_request_form('wholesale'),
        $path === '/request' => page_request_form('request'),
        default => not_found_page(),
    };

    // Scheduled drops: the first page view after go-live sends the alerts, after the page is delivered.
    if ($method === 'GET') {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        ignore_user_abort(true);
        set_time_limit(120);
        if (has_due_drops()) announce_due_drops();
        run_maintenance();
    }
} catch (Throwable $ex) {
    log_error('Page error ' . $path, $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><div style="font-family:sans-serif;max-width:32rem;margin:6rem auto;text-align:center">'
        . '<h1>Something went wrong</h1><p>Please refresh the page or try again in a moment.</p><p><a href="/">Back to the store</a></p></div>';
}
