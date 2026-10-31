<?php
/** Admin panel pages. Forms submit through assets/admin.js to the /api/admin/* endpoints. */

const ADMIN_INPUT = 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm';

function admin_guard_page(): void
{
    if (!is_admin()) {
        header('Location: ' . admin_base() . '/login?from=' . urlencode($_SERVER['REQUEST_URI'] ?? admin_base()));
        exit;
    }
}

function render_admin(string $content, string $title, bool $withNav = true): void
{
    $s = settings();
    $csrf = $withNav ? csrf_token() : '';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $links = [
        '/admin' => ['dashboard', 'Dashboard'],
        '/admin/products' => ['shirt', 'Products'],
        '/admin/orders' => ['bag', 'Orders'],
        '/admin/discounts' => ['tag', 'Discount codes'],
        '/admin/reviews' => ['star', 'Reviews'],
        '/admin/settings' => ['palette', 'Theme & content'],
        '/admin/security' => ['key', 'Security'],
        '/admin/activity' => ['list', 'Activity log'],
    ];
    $nav = '';
    foreach ($links as $href => [$ic, $label]) {
        $active = $href === '/admin' ? in_array($path, ['/admin', admin_base()], true) : (str_starts_with($path, $href) || str_starts_with($path, admin_base() . substr($href, 6)));
        $nav .= '<a href="' . $href . '" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium '
            . ($active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100') . '">' . icon($ic) . $label . '</a>';
    }
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="<?= e($csrf) ?>">
<meta name="admin-base" content="<?= e(admin_base()) ?>">
<title><?= e($title) ?> | <?= e($s['storeName']) ?> admin</title>
<link rel="icon" href="/tk-icon.png">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=<?= ASSET_VERSION ?>">
</head>
<body class="min-h-screen font-sans antialiased" style="<?= e(theme_style($s)) ?>">
<?php if ($withNav): ?>
<div class="flex min-h-screen flex-col bg-gray-50 text-gray-900 md:flex-row">
  <aside class="border-b border-gray-200 bg-white md:w-60 md:shrink-0 md:border-b-0 md:border-r">
    <div class="p-5">
      <p class="font-semibold"><?= e($s['storeName']) ?></p>
      <p class="text-xs text-gray-500">Manage your store</p>
    </div>
    <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:flex-col md:overflow-visible"><?= $nav ?>
      <a href="/" target="_blank" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100"><?= icon('external') ?>View store</a>
      <button type="button" id="admin-logout" class="flex items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-gray-600 hover:bg-gray-100"><?= icon('logout') ?>Log out</button>
    </nav>
  </aside>
  <div class="flex-1 overflow-auto p-6 sm:p-8"><?= $content ?></div>
</div>
<?php else: ?>
<?= $content ?>
<?php endif; ?>
<script src="/assets/admin.js?v=<?= ASSET_VERSION ?>"></script>
</body>
</html>
<?php
}

function admin_pager(string $base, int $page, int $totalPages): string
{
    if ($totalPages <= 1) return '';
    $cls = fn(bool $off) => 'rounded-lg border border-gray-300 px-3 py-1.5 text-sm ' . ($off ? 'pointer-events-none opacity-40' : 'hover:bg-gray-50');
    return '<div class="mt-6 flex items-center justify-center gap-3">'
        . '<a href="' . $base . '?page=' . max(1, $page - 1) . '" class="' . $cls($page === 1) . '">Previous</a>'
        . '<span class="text-sm text-gray-500">Page ' . $page . ' of ' . $totalPages . '</span>'
        . '<a href="' . $base . '?page=' . min($totalPages, $page + 1) . '" class="' . $cls($page === $totalPages) . '">Next</a></div>';
}

function admin_login_page(): void
{
    if (is_admin()) { header('Location: ' . admin_base()); exit; }
    $content = '<div class="flex min-h-screen items-center justify-center bg-gray-100 px-4"><div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-lg">'
        . '<h1 class="text-xl font-semibold text-gray-900">Admin login</h1><p class="mt-1 text-sm text-gray-500">Enter your admin password to continue.</p>'
        . '<form id="login-form" class="mt-6 space-y-4"><div><label for="password" class="block text-sm font-medium text-gray-700">Password</label>'
        . '<input id="password" name="password" type="password" required autofocus class="' . ADMIN_INPUT . '"></div>'
        . '<div id="code-row" class="hidden"><label for="code" class="block text-sm font-medium text-gray-700">Authenticator code</label>'
        . '<input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456" class="' . ADMIN_INPUT . '"></div>'
        . '<p id="login-error" class="hidden text-sm text-red-600"></p>'
        . '<button type="submit" class="' . btn_class('primary', true) . '">Sign in</button></form></div></div>';
    render_admin($content, 'Login', false);
}

function admin_dashboard(): void
{
    admin_guard_page();
    $s = settings();
    $pdo = db();
    $cards = [
        ['Products', (int) $pdo->query('SELECT COUNT(*) FROM Product')->fetchColumn(), '/admin/products', 'shirt'],
        ['Orders', (int) $pdo->query('SELECT COUNT(*) FROM "Order"')->fetchColumn(), '/admin/orders', 'bag'],
        ['Paid orders', (int) $pdo->query('SELECT COUNT(*) FROM "Order" WHERE status = \'paid\'')->fetchColumn(), '/admin/orders', 'bag'],
        ['Notification subscribers', (int) $pdo->query('SELECT COUNT(*) FROM PushSubscription')->fetchColumn(), '/admin', 'bell'],
    ];
    $cardHtml = '';
    foreach ($cards as [$label, $value, $href, $ic]) {
        $cardHtml .= '<a href="' . $href . '" class="rounded-xl border border-gray-200 bg-white p-5 transition-shadow hover:shadow-md">'
            . icon($ic, 'h-5 w-5 text-gray-400') . '<p class="mt-3 text-2xl font-semibold">' . $value . '</p><p class="text-sm text-gray-500">' . $label . '</p></a>';
    }
    $recent = $pdo->query('SELECT * FROM "Order" ORDER BY createdAt DESC LIMIT 5')->fetchAll();
    $recentHtml = $recent ? '<ul class="mt-4 divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white">' . implode('', array_map(fn($o) =>
        '<li class="flex justify-between gap-4 px-4 py-3 text-sm"><span>' . e($o['customerName']) . ' · ' . e($o['reference']) . '</span>'
        . '<span class="capitalize text-gray-500">' . e($o['status']) . ' · ' . naira((int) $o['totalAmount']) . '</span></li>', $recent)) . '</ul>'
        : '<p class="mt-4 text-sm text-gray-500">No orders yet.</p>';

    // Sales: paid orders only. Week starts Monday, Lagos time.
    $revenue = function (int $sinceMs) use ($pdo): array {
        $st = $pdo->prepare('SELECT COALESCE(SUM(totalAmount), 0), COUNT(*) FROM "Order" WHERE status = \'paid\' AND createdAt >= ?');
        $st->execute([$sinceMs]);
        return $st->fetch(PDO::FETCH_NUM);
    };
    [$weekRev, $weekCount] = $revenue(strtotime('monday this week') * 1000);
    [$monthRev, $monthCount] = $revenue(strtotime('first day of this month 00:00') * 1000);
    [$allRev] = $revenue(0);
    $stat = fn(string $label, string $value, string $sub) => '<div class="rounded-xl border border-gray-200 bg-white p-5"><p class="text-sm text-gray-500">' . e($label) . '</p><p class="mt-1 text-2xl font-semibold">' . $value . '</p><p class="text-xs text-gray-400">' . e($sub) . '</p></div>';
    $salesHtml = '<div class="mt-8 grid gap-4 sm:grid-cols-3">'
        . $stat('Revenue this week', naira((int) $weekRev), $weekCount . ' paid orders')
        . $stat('Revenue this month', naira((int) $monthRev), $monthCount . ' paid orders')
        . $stat('All-time revenue', naira((int) $allRev), 'Paid orders only') . '</div>';

    $best = $pdo->query('SELECT p.category, COUNT(*) n, SUM(oi.price * oi.quantity) amt FROM OrderItem oi JOIN "Order" o ON o.id = oi.orderId JOIN Product p ON p.id = oi.productId WHERE o.status = \'paid\' GROUP BY p.category ORDER BY amt DESC LIMIT 5')->fetchAll();
    $bestHtml = $best ? '<ul class="mt-3 space-y-2 text-sm">' . implode('', array_map(fn($b) => '<li class="flex justify-between"><span class="capitalize">' . e($b['category']) . ' · ' . (int) $b['n'] . ' sold</span><span class="font-medium">' . naira((int) $b['amt']) . '</span></li>', $best)) . '</ul>'
        : '<p class="mt-3 text-sm text-gray-500">No sales yet.</p>';
    $stale = fetch_products('inStock = 1 AND createdAt < ? AND ' . published_sql(), [now_ms() - 30 * 86400 * 1000], 'ORDER BY createdAt ASC LIMIT 6');
    $staleHtml = $stale ? '<ul class="mt-3 space-y-2 text-sm">' . implode('', array_map(fn($p) => '<li class="flex justify-between gap-3"><a href="/admin/products/' . e($p['id']) . '" class="text-blue-600 hover:underline">' . e($p['name']) . '</a><span class="shrink-0 text-gray-500">' . (int) floor((now_ms() - (int) $p['createdAt']) / 86400000) . ' days · ' . naira((int) $p['salePrice']) . '</span></li>', $stale)) . '</ul><p class="mt-3 text-xs text-gray-400">Tip: drop the price or re-feature these to move them.</p>'
        : '<p class="mt-3 text-sm text-gray-500">Nothing has been sitting longer than 30 days. 🎉</p>';
    $insights = '<div class="mt-8 grid gap-4 lg:grid-cols-2">'
        . '<div class="rounded-xl border border-gray-200 bg-white p-5"><h2 class="font-semibold">Best-selling categories</h2>' . $bestHtml . '</div>'
        . '<div class="rounded-xl border border-gray-200 bg-white p-5"><h2 class="font-semibold">Unsold for 30+ days</h2>' . $staleHtml . '</div></div>';

    $content = '<h1 class="text-2xl font-semibold">Dashboard</h1><p class="mt-1 text-gray-500">Welcome back — manage ' . e($s['storeName']) . '</p>'
        . $salesHtml
        . '<div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">' . $cardHtml . '</div>'
        . $insights
        . '<div class="mt-8 flex flex-wrap gap-3">'
        . '<a href="/admin/products/new" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">+ Add product</a>'
        . '<a href="/admin/products/bulk" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Bulk upload</a>'
        . '<a href="/api/admin/backup" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">' . icon('download') . 'Download backup</a>'
        . '<a href="/admin/settings" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">' . icon('palette') . 'Edit theme &amp; content</a>'
        . '<a href="' . e(tel_link($s['customerCarePhone'])) . '" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">' . icon('phone') . e($s['customerCarePhone']) . '</a></div>'
        . '<div class="mt-10"><h2 class="font-semibold">Recent orders</h2>' . $recentHtml . '</div>';
    render_admin($content, 'Dashboard');
}

function admin_products_page(): void
{
    admin_guard_page();
    $pageSize = 20;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $total = count_products();
    $products = fetch_products('1', [], "ORDER BY createdAt DESC LIMIT $pageSize OFFSET " . (($page - 1) * $pageSize));
    $rows = '';
    foreach ($products as $p) {
        $status = match (product_availability($p)) {
            'sold' => '<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Sold</span>',
            'scheduled' => '<span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800">Drops ' . e(date('j M, g:ia', intdiv((int) $p['publishAt'], 1000))) . '</span>',
            'reserved' => '<span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">Reserved</span>',
            default => '<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">In stock</span>',
        };
        $rows .= '<tr><td class="px-4 py-3"><div class="flex items-center gap-3"><img src="' . e($p['imageUrl']) . '" alt="" class="h-12 w-10 rounded bg-gray-100 object-cover">'
            . '<div><p class="font-medium">' . e($p['name']) . '</p><p class="text-xs text-gray-400">' . e($p['category']) . ' · ' . e($p['size']) . '</p></div></div></td>'
            . '<td class="px-4 py-3 text-gray-500 line-through">' . naira((int) $p['originalPrice']) . '</td>'
            . '<td class="px-4 py-3 font-medium">' . naira((int) $p['salePrice']) . '</td>'
            . '<td class="px-4 py-3">' . $status . '</td>'
            . '<td class="px-4 py-3"><div class="flex gap-3"><a href="/admin/products/' . e($p['id']) . '" class="text-sm text-blue-600 hover:underline">Edit</a>'
            . '<button type="button" data-delete-product="' . e($p['id']) . '" data-name="' . e($p['name']) . '" class="text-sm text-red-600 hover:underline">Delete</button></div></td></tr>';
    }
    $content = '<div class="flex items-center justify-between gap-4"><div><h1 class="text-2xl font-semibold">Products</h1><p class="text-gray-500">' . $total . ' items in catalog</p></div>'
        . '<div class="flex gap-2"><a href="/admin/products/bulk" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Bulk upload</a><a href="/admin/products/new" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">+ Add product</a></div></div>'
        . '<div class="mt-8 overflow-x-auto rounded-xl border border-gray-200 bg-white"><table class="w-full min-w-[640px] text-left text-sm">'
        . '<thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">Actual</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>'
        . '<tbody class="divide-y divide-gray-100">' . $rows . '</tbody></table></div>'
        . admin_pager('/admin/products', $page, max(1, (int) ceil($total / $pageSize)));
    render_admin($content, 'Products');
}

function image_field(string $name, string $label, string $value, string $variant = 'product', bool $required = true): string
{
    $hint = [
        'product' => 'Upload a photo (JPG, PNG, WebP, GIF — max 5MB) or paste an image URL.',
        'logo' => 'Upload your store logo — it will auto-resize to fit the header (max 320×96px). PNG with transparent background works best.',
        'hero' => 'Wide banner image for the top of the homepage.',
    ][$variant];
    $preview = $variant === 'logo' ? 'max-h-16 max-w-[240px] object-contain' : 'h-40 w-32 rounded-lg object-cover';
    return '<div class="image-field sm:col-span-2" data-variant="' . e($variant) . '">'
        . '<label class="block text-sm font-medium text-gray-700">' . e($label) . '</label>'
        . '<p class="mt-1 text-xs text-gray-500">' . e($hint) . '</p>'
        . '<div class="mt-2 flex flex-wrap items-start gap-4">'
        . '<img data-preview src="' . e($value) . '" alt="" class="' . $preview . ' bg-gray-100' . ($value ? '' : ' hidden') . '">'
        . '<div class="flex flex-col gap-2"><label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">'
        . icon('upload') . '<span data-upload-label>Upload image</span><input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" data-file></label>'
        . ($variant !== 'logo' ? '' : '<button type="button" data-clear class="text-left text-xs text-red-600 hover:underline">Remove logo</button>')
        . '</div></div>'
        . '<input name="' . e($name) . '" value="' . e($value) . '" data-url ' . ($required ? 'required ' : '') . 'placeholder="https://…" class="' . ADMIN_INPUT . ($variant === 'logo' ? ' hidden' : '') . '">'
        . '<p data-error class="mt-1 hidden text-xs text-red-600"></p></div>';
}

function admin_product_form(?array $p = null): void
{
    admin_guard_page();
    $catOpts = '';
    foreach (CATEGORIES as $id => $label) {
        if ($id === 'all') continue;
        $catOpts .= '<option value="' . $id . '"' . (($p['category'] ?? 'tops') === $id ? ' selected' : '') . '>' . $label . '</option>';
    }
    $condOpts = '';
    foreach (CONDITIONS as $c) $condOpts .= '<option' . (($p['condition'] ?? 'Good') === $c ? ' selected' : '') . '>' . e($c) . '</option>';
    $gallery = $p ? (json_decode($p['images'] ?: '[]', true) ?: []) : [];
    $galleryItems = '';
    foreach ($gallery as $g) {
        $galleryItems .= '<div class="relative" data-gallery-item="' . e($g) . '"><img src="' . e($g) . '" alt="" class="h-24 w-20 rounded-lg bg-gray-100 object-cover">'
            . '<button type="button" data-remove class="absolute -right-2 -top-2 rounded-full bg-white p-1 shadow ring-1 ring-gray-200" aria-label="Remove photo">' . icon('x', 'h-3 w-3') . '</button></div>';
    }
    $num = fn($k) => $p ? e((string) ((int) $p[$k] / 100)) : '';
    $checked = fn(bool $v) => $v ? ' checked' : '';

    $content = '<a href="/admin/products" class="text-sm text-gray-500 hover:text-gray-800">← Back to products</a>'
        . '<h1 class="mt-2 text-2xl font-semibold">' . ($p ? 'Edit product' : 'Add product') . '</h1>'
        . ($p ? '' : '<p class="mt-1 text-sm text-gray-500">In-stock items you add here send a "New arrival" notification to subscribed shoppers.</p>')
        . '<form id="product-form" data-id="' . e($p['id'] ?? '') . '" class="mt-8 max-w-2xl space-y-5"><div class="grid gap-5 sm:grid-cols-2">'
        . '<div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Name</label><input name="name" required maxlength="200" value="' . e($p['name'] ?? '') . '" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="block text-sm font-medium text-gray-700">Slug (optional)</label><input name="slug" value="' . e($p['slug'] ?? '') . '" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="block text-sm font-medium text-gray-700">Category</label><select name="category" class="' . ADMIN_INPUT . '">' . $catOpts . '</select></div>'
        . '<div><label class="block text-sm font-medium text-gray-700">Actual price (₦)</label><input name="originalPriceNaira" type="number" min="1" max="' . MAX_ITEM_PRICE_NAIRA . '" required value="' . $num('originalPrice') . '" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="block text-sm font-medium text-gray-700">Discount price (₦)</label><input name="salePriceNaira" type="number" min="1" max="' . MAX_ITEM_PRICE_NAIRA . '" required value="' . $num('salePrice') . '" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="block text-sm font-medium text-gray-700">Size</label><input name="size" required maxlength="40" value="' . e($p['size'] ?? 'M') . '" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="block text-sm font-medium text-gray-700">Condition</label><select name="condition" class="' . ADMIN_INPUT . '">' . $condOpts . '</select></div>'
        . '<div><label class="block text-sm font-medium text-gray-700">Brand (optional)</label><input name="brand" maxlength="80" value="' . e($p['brand'] ?? '') . '" placeholder="Zara, Nike, vintage…" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="block text-sm font-medium text-gray-700">Colour (optional)</label><input name="color" maxlength="40" value="' . e($p['color'] ?? '') . '" placeholder="Black" class="' . ADMIN_INPUT . '"></div>'
        . '<div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Measurements (optional)</label><textarea name="measurements" rows="2" maxlength="500" placeholder="Chest 52cm · Length 70cm · Waist 38cm" class="' . ADMIN_INPUT . '">' . e($p['measurements'] ?? '') . '</textarea></div>'
        . '<div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Flaws / wear notes (optional)</label><textarea name="flaws" rows="2" maxlength="1000" placeholder="Small mark on left sleeve (see photo 3)" class="' . ADMIN_INPUT . '">' . e($p['flaws'] ?? '') . '</textarea><p class="mt-1 text-xs text-gray-500">Add close-up photos of any flaws in Extra photos below — honest listings get fewer returns.</p></div>'
        . image_field('imageUrl', 'Cover photo', $p['imageUrl'] ?? '')
        . '<div class="sm:col-span-2" id="gallery-field"><label class="block text-sm font-medium text-gray-700">Extra photos (up to 6)</label>'
        . '<div class="mt-2 flex flex-wrap gap-3" data-gallery>' . $galleryItems . '</div>'
        . '<label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">' . icon('upload') . '<span data-upload-label>Add photos</span>'
        . '<input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" data-gallery-file></label><p data-error class="mt-1 hidden text-xs text-red-600"></p></div>'
        . '<div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Description</label><textarea name="description" rows="4" maxlength="4000" class="' . ADMIN_INPUT . '">' . e($p['description'] ?? '') . '</textarea></div>'
        . '<div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">Scheduled drop (optional)</label><input name="publishAt" type="datetime-local" value="' . e(!empty($p['publishAt']) && (int) $p['publishAt'] > now_ms() ? date('Y-m-d\TH:i', intdiv((int) $p['publishAt'], 1000)) : '') . '" class="' . ADMIN_INPUT . ' sm:max-w-xs"><p class="mt-1 text-xs text-gray-500">Leave empty to go live now. Set a time (Lagos) to hide the item until then — the homepage shows a countdown and alerts go out when it drops.</p></div>'
        . '<label class="flex items-center gap-2 text-sm"><input name="featured" type="checkbox"' . $checked((bool) ($p['featured'] ?? false)) . '> Featured drop</label>'
        . '<label class="flex items-center gap-2 text-sm"><input name="inStock" type="checkbox"' . $checked((bool) ($p['inStock'] ?? true)) . '> In stock</label>'
        . '</div><p id="form-error" class="hidden text-sm text-red-600"></p>'
        . '<div class="flex gap-3"><button type="submit" class="' . btn_class() . '">' . ($p ? 'Update product' : 'Add product') . '</button>'
        . '<a href="/admin/products" class="' . btn_class('outline') . '">Cancel</a></div></form>';
    render_admin($content, $p ? 'Edit product' : 'Add product');
}

function admin_edit_product(string $id): void
{
    admin_guard_page();
    $st = db()->prepare('SELECT * FROM Product WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) { http_response_code(404); render_admin('<p>Product not found. <a href="/admin/products" class="text-blue-600">Back</a></p>', 'Not found'); return; }
    admin_product_form($p);
}

function admin_orders_page(): void
{
    admin_guard_page();
    require_once __DIR__ . '/api.php';
    $pageSize = 20;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $total = (int) db()->query('SELECT COUNT(*) FROM "Order"')->fetchColumn();
    $ids = db()->query('SELECT id FROM "Order" ORDER BY createdAt DESC LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize))->fetchAll(PDO::FETCH_COLUMN);
    $cards = '';
    foreach ($ids as $id) {
        $o = order_with_items('id = ?', [$id]);
        $badge = ['paid' => 'bg-green-100 text-green-800', 'pending' => 'bg-yellow-100 text-yellow-800', 'awaiting' => 'bg-blue-100 text-blue-800'][$o['status']] ?? 'bg-red-100 text-red-800';
        $statusLabel = ['awaiting' => 'awaiting payment'][$o['status']] ?? $o['status'];
        $items = implode('', array_map(fn($i) => '<li>' . e($i['product']['name']) . ' × ' . $i['quantity'] . ' — ' . naira($i['price'] * $i['quantity']) . '</li>', $o['items']));
        $extra = '';
        if ($o['deliveryFee']) $extra .= '<li class="text-gray-500">Delivery — ' . naira($o['deliveryFee']) . '</li>';
        if ($o['discountAmount']) $extra .= '<li class="text-gray-500">Discount ' . e($o['discountCode']) . ' — −' . naira($o['discountAmount']) . '</li>';

        $actions = '';
        $btn = fn(string $action, string $label, string $cls = 'border-gray-300 hover:bg-gray-50', string $value = '') => '<button type="button" data-order-action="' . $action . '" data-value="' . e($value) . '" data-order="' . e($o['id']) . '" class="rounded-lg border px-3 py-1.5 text-xs font-medium ' . $cls . '">' . e($label) . '</button>';
        if (in_array($o['status'], ['awaiting', 'pending'], true)) $actions .= $btn('paid', 'Mark paid', 'border-green-600 text-green-700 hover:bg-green-50');
        if (in_array($o['status'], ['paid', 'awaiting'], true)) {
            $opts = '';
            foreach (FULFILLMENT_LABELS as $k => $label) $opts .= '<option value="' . $k . '"' . ($o['fulfillment'] === $k ? ' selected' : '') . '>' . e($label) . '</option>';
            $actions .= '<label class="flex items-center gap-2 text-xs text-gray-600">Delivery<select data-fulfillment="' . e($o['id']) . '" class="rounded-lg border border-gray-300 px-2 py-1 text-xs">' . $opts . '</select></label>';
        }
        if (!in_array($o['status'], ['cancelled', 'failed'], true)) $actions .= $btn('cancel', $o['status'] === 'paid' ? 'Cancel & restock' : 'Cancel', 'border-red-300 text-red-700 hover:bg-red-50');
        $wa = '<a href="' . e(whatsapp_link($o['customerPhone'])) . '" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-50">WhatsApp customer</a>';

        $cards .= '<div class="rounded-xl border border-gray-200 bg-white p-5" data-order-card="' . e($o['id']) . '"><div class="flex flex-wrap items-start justify-between gap-2">'
            . '<div><p class="font-semibold">' . e($o['customerName']) . '</p><p class="text-sm text-gray-500">' . e($o['reference']) . ' · ' . date('j M Y, g:ia', (int) ($o['createdAt'] / 1000)) . '</p>'
            . '<p class="text-xs text-gray-500">' . e(PAYMENT_LABELS[$o['paymentMethod']] ?? $o['paymentMethod']) . ' · ' . e(FULFILLMENT_LABELS[$o['fulfillment']] ?? $o['fulfillment']) . '</p></div>'
            . '<span class="rounded-full px-2.5 py-1 text-xs font-medium capitalize ' . $badge . '">' . e($statusLabel) . '</span></div>'
            . '<p class="mt-2 text-sm text-gray-600">' . e($o['customerPhone']) . ' · ' . e($o['customerEmail']) . '<br>' . e($o['shippingAddress']) . ', ' . e($o['city']) . ', ' . e($o['state']) . '</p>'
            . '<ul class="mt-3 space-y-1 text-sm text-gray-600">' . $items . $extra . '</ul><p class="mt-3 font-semibold">' . naira($o['totalAmount']) . '</p>'
            . '<div class="mt-4 flex flex-wrap items-center gap-2">' . $actions . $wa . '</div><p data-order-error class="mt-2 hidden text-xs text-red-600"></p></div>';
    }
    $content = '<div class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-2xl font-semibold">Orders</h1><p class="text-gray-500">' . $total . ' total orders</p></div>'
        . '<a href="/api/admin/export/orders" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">' . icon('download') . 'Export to Excel (CSV)</a></div>'
        . '<div class="mt-8 space-y-4">' . ($cards ?: '<p class="text-gray-500">No orders yet.</p>') . '</div>'
        . admin_pager('/admin/orders', $page, max(1, (int) ceil($total / $pageSize)));
    render_admin($content, 'Orders');
}

function admin_settings_page(): void
{
    admin_guard_page();
    $s = settings();
    $f = fn(string $name, string $label, string $type = 'text', bool $req = false) =>
        '<div><label class="text-sm font-medium">' . e($label) . '</label><input name="' . $name . '" type="' . $type . '" value="' . e($s[$name]) . '"' . ($req ? ' required' : '') . ' class="' . ADMIN_INPUT . '"></div>';
    $ta = fn(string $name, string $label, int $rows) =>
        '<div><label class="text-sm font-medium">' . e($label) . '</label><textarea name="' . $name . '" rows="' . $rows . '" class="' . ADMIN_INPUT . '">' . e($s[$name]) . '</textarea></div>';
    $colors = ['colorCream' => 'Background', 'colorSand' => 'Accent light', 'colorTerracotta' => 'Primary', 'colorRust' => 'Primary hover', 'colorOlive' => 'Secondary', 'colorCharcoal' => 'Text dark', 'colorWarmgray' => 'Text muted'];
    $colorHtml = '';
    foreach ($colors as $k => $label) {
        $colorHtml .= '<div class="flex items-center gap-3"><input type="color" name="' . $k . '" value="' . e(strtolower($s[$k])) . '" class="h-10 w-14 cursor-pointer rounded border border-gray-300">'
            . '<div><p class="text-sm font-medium">' . $label . '</p><p class="text-xs text-gray-400" data-hex="' . $k . '">' . e($s[$k]) . '</p></div></div>';
    }

    $content = '<h1 class="text-2xl font-semibold">Theme &amp; content</h1><p class="mt-1 text-gray-500">Changes appear on the storefront as soon as you save.</p>'
        . '<form id="settings-form" class="mt-8 max-w-3xl space-y-10">'
        . '<section class="space-y-4"><h2 class="text-lg font-semibold text-gray-900">Store identity</h2><div class="grid gap-4 sm:grid-cols-2">'
        . $f('storeName', 'Store name', 'text', true) . $f('tagline', 'Tagline') . '</div>'
        . image_field('logoUrl', 'Store logo', $s['logoUrl'], 'logo', false) . '</section>'
        . '<section class="space-y-4"><h2 class="text-lg font-semibold text-gray-900">Homepage content</h2>'
        . $f('heroTitle', 'Hero title') . $ta('heroSubtitle', 'Hero subtitle', 2)
        . image_field('heroImageUrl', 'Hero banner image', $s['heroImageUrl'], 'hero')
        . $f('aboutTitle', 'About title') . $ta('aboutText', 'About text', 4) . '</section>'
        . '<section class="space-y-4"><h2 class="text-lg font-semibold text-gray-900">Contact &amp; customer care</h2><div class="grid gap-4 sm:grid-cols-2">'
        . $f('customerCarePhone', 'Customer care phone', 'text', true) . $f('customerCareHours', 'Hours') . $f('contactEmail', 'Email', 'email')
        . $f('contactAddress', 'Address') . $f('instagramUrl', 'Instagram URL') . $f('tiktokUrl', 'TikTok URL') . $f('whatsappNumber', 'WhatsApp number') . '</div></section>'
        . '<section class="space-y-4"><h2 class="text-lg font-semibold text-gray-900">Delivery &amp; payment</h2><div class="grid gap-4 sm:grid-cols-3">'
        . '<div><label class="text-sm font-medium">Lagos delivery (₦)</label><input name="deliveryLagos" type="number" min="0" value="' . (int) $s['deliveryLagos'] . '" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="text-sm font-medium">Other states (₦)</label><input name="deliveryOther" type="number" min="0" value="' . (int) $s['deliveryOther'] . '" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="text-sm font-medium">Free delivery over (₦, 0 = never)</label><input name="freeDeliveryOver" type="number" min="0" value="' . (int) $s['freeDeliveryOver'] . '" class="' . ADMIN_INPUT . '"></div></div>'
        . '<div><label class="text-sm font-medium">Bank transfer details</label><textarea name="bankDetails" rows="3" maxlength="600" placeholder="Bank: GTBank&#10;Account name: T.K Collectibles&#10;Account number: 0123456789" class="' . ADMIN_INPUT . '">' . e($s['bankDetails']) . '</textarea><p class="mt-1 text-xs text-gray-500">Leave empty to hide the bank transfer option at checkout.</p></div>'
        . '<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="payOnDelivery" value="1"' . ($s['payOnDelivery'] ? ' checked' : '') . '> Offer pay on delivery</label></section>'
        . '<section class="space-y-4"><h2 class="text-lg font-semibold text-gray-900">Color palette</h2><p class="text-sm text-gray-500">These colors apply across the entire storefront.</p>'
        . '<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">' . $colorHtml . '</div>'
        . '<div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-sm font-medium text-gray-900">Readability check</p>'
        . '<p class="mt-1 text-xs text-gray-500">WCAG contrast ratio for common text/background pairings in this palette.</p><ul id="contrast-list" class="mt-3 space-y-2"></ul></div></section>'
        . '<p id="settings-message" class="hidden text-sm"></p>'
        . '<button type="submit" class="' . btn_class() . '">Save all settings</button></form>';
    render_admin($content, 'Settings');
}
