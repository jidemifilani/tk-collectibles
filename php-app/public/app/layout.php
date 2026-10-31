<?php
/** Storefront page shell and shared UI pieces. */

function btn_class(string $variant = 'primary', bool $full = false, string $extra = ''): string
{
    $variants = [
        'primary' => 'bg-terracotta text-white hover:bg-rust focus-visible:ring-terracotta',
        'secondary' => 'bg-olive text-white hover:bg-olive/90 focus-visible:ring-olive',
        'outline' => 'border-2 border-charcoal text-charcoal hover:bg-charcoal hover:text-cream focus-visible:ring-charcoal',
        'ghost' => 'text-charcoal hover:bg-sand/60 focus-visible:ring-warmgray',
    ];
    return 'inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 '
        . $variants[$variant] . ($full ? ' w-full' : '') . ($extra ? " $extra" : '');
}

function price_html(int $original, int $sale, string $size = 'md', string $class = ''): string
{
    $priceClass = ['sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-2xl'][$size];
    $origClass = ['sm' => 'text-xs', 'md' => 'text-sm', 'lg' => 'text-base'][$size];
    $html = '<div class="flex flex-wrap items-baseline gap-2 ' . e($class) . '">'
        . '<span class="font-semibold text-terracotta ' . $priceClass . '">' . naira($sale) . '</span>';
    if ($sale < $original) {
        $html .= '<span class="text-warmgray line-through ' . $origClass . '" aria-label="Original price ' . e(naira($original)) . '">' . naira($original) . '</span>';
        $pct = discount_percent($original, $sale);
        if ($pct > 0) $html .= '<span class="rounded-full bg-olive/15 px-2 py-0.5 text-xs font-semibold text-olive">-' . $pct . '%</span>';
    }
    return $html . '</div>';
}

function product_card(array $p): string
{
    $sold = !$p['inStock'];
    $badges = '';
    if ($sold) {
        $badges .= '<span class="rounded-full bg-charcoal px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-cream">Sold</span>';
    } else {
        if (is_new_arrival($p)) $badges .= '<span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-terracotta shadow-sm">New</span>';
        if ($p['featured']) $badges .= '<span class="rounded-full bg-terracotta px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">Drop</span>';
        $badges .= '<span class="rounded-full bg-charcoal/80 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-cream">1 of 1</span>';
    }
    $meta = trim((string) ($p['brand'] ?? '')) !== '' ? $p['brand'] : $p['category'];
    return '<div class="relative">'
        . '<a href="/product/' . e($p['slug']) . '" class="group flex h-full flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-sand/60 transition-shadow hover:shadow-md' . ($sold ? ' opacity-75' : '') . '">'
        . '<div class="relative aspect-[3/4] overflow-hidden bg-sand">'
        . '<img src="' . e($p['imageUrl']) . '" alt="' . e($p['name']) . '" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105' . ($sold ? ' grayscale' : '') . '">'
        . '<div class="absolute left-3 top-3 flex flex-wrap gap-1.5 pr-10">' . $badges . '</div></div>'
        . '<div class="flex flex-1 flex-col p-4">'
        . '<p class="text-xs uppercase tracking-wide text-warmgray">' . e($meta) . '</p>'
        . '<h3 class="mt-1 font-display text-lg leading-tight text-charcoal">' . e($p['name']) . '</h3>'
        . '<div class="mt-auto flex items-end justify-between gap-2 pt-3">'
        . price_html((int) $p['originalPrice'], (int) $p['salePrice'], 'sm')
        . '<span class="shrink-0 text-xs text-warmgray">Size ' . e($p['size']) . '</span>'
        . '</div></div></a>'
        . wish_button($p['id'], 'absolute right-3 top-3') . '</div>';
}

/** Heart toggle. The list is kept in the shopper's browser (assets/app.js). */
function wish_button(string $id, string $class = ''): string
{
    return '<button type="button" data-wish="' . e($id) . '" aria-pressed="false" aria-label="Save to wishlist" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-charcoal shadow-sm transition-colors hover:text-terracotta ' . e($class) . '">'
        . icon('heart', 'h-4 w-4') . '</button>';
}

function email_signup(string $variant = 'dark', string $category = ''): string
{
    $dark = $variant === 'dark';
    $id = 'sub-' . $variant . $category;
    return '<form data-subscribe class="flex max-w-sm gap-2"' . ($category ? ' data-category="' . e($category) . '"' : '') . '>'
        . '<label class="sr-only" for="' . e($id) . '">Email</label>'
        . '<input id="' . e($id) . '" type="email" required placeholder="Your email" class="min-w-0 flex-1 rounded-full border px-4 py-2 text-sm focus:outline-none '
        . ($dark ? 'border-sand/30 bg-white/10 text-cream placeholder:text-sand/60 focus:border-cream' : 'border-sand bg-white text-charcoal focus:border-terracotta') . '">'
        . '<button type="submit" class="shrink-0 rounded-full bg-terracotta px-4 py-2 text-sm font-semibold text-cream hover:bg-rust">' . ($category ? 'Notify me' : 'Join') . '</button>'
        . '</form><p data-subscribe-msg class="mt-2 hidden text-xs ' . ($dark ? 'text-sand/80' : 'text-warmgray') . '"></p>';
}

function product_grid(array $products): string
{
    return '<div class="mt-10 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">'
        . implode('', array_map('product_card', $products)) . '</div>';
}

function notify_button(string $variant = 'light'): string
{
    // Filled in by assets/app.js once it knows the browser's notification support.
    return '<div class="notify-slot" data-variant="' . e($variant) . '"></div>';
}

function new_arrival_bar(): string
{
    $since = now_ms() - NEW_ARRIVAL_DAYS * 86400 * 1000;
    $where = 'inStock = 1 AND createdAt >= ? AND ' . published_sql();
    $count = count_products($where, [$since]);
    if (!$count) return '';
    $st = db()->prepare("SELECT MAX(createdAt) FROM Product WHERE $where");
    $st->execute([$since]);
    $dropId = (string) $st->fetchColumn();
    $noun = $count === 1 ? 'piece' : 'pieces';
    return '<div id="new-arrival-bar" class="hidden bg-terracotta text-cream" data-drop="' . e($dropId) . '">'
        . '<div class="mx-auto flex max-w-6xl items-center justify-center gap-3 px-4 py-2 text-sm sm:px-6">'
        . icon('sparkles', 'h-4 w-4 shrink-0')
        . '<p><span class="font-semibold">New arrivals!</span> ' . $count . ' fresh ' . $noun . ' just dropped. '
        . '<a href="/shop" data-dismiss-bar class="font-semibold underline underline-offset-2">Shop now</a></p>'
        . '<button type="button" data-dismiss-bar aria-label="Dismiss new arrivals alert" class="ml-auto rounded p-1 hover:bg-white/10">' . icon('x') . '</button>'
        . '</div></div>';
}

function logo_img(array $s, string $class): string
{
    return '<img src="' . e($s['logoUrl']) . '" alt="' . e($s['storeName']) . ' logo" class="' . e($class) . '">';
}

/** Outputs the full storefront page. $meta: title, description, image. */
function render_store(string $content, array $meta = []): void
{
    $s = settings();
    $title = isset($meta['title']) ? $meta['title'] . ' | ' . $s['storeName'] : $s['storeName'] . ' | Thrift Fashion';
    $desc = $meta['description'] ?? $s['heroSubtitle'];
    $image = absolute_url((string) ($meta['image'] ?? $s['heroImageUrl']));
    $canonical = app_url() . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $hasLogo = trim($s['logoUrl']) !== '';
    $nav = ['/shop' => 'Shop', '/shop?category=featured' => 'New Drops', '/track' => 'Track order', '/contact' => 'Contact'];

    $navLinks = '';
    $mobileLinks = '';
    foreach ($nav as $href => $label) {
        $navLinks .= '<a href="' . $href . '" class="text-sm font-medium text-warmgray transition-colors hover:text-charcoal">' . $label . '</a>';
        $mobileLinks .= '<a href="' . $href . '" class="py-2 text-sm font-medium">' . $label . '</a>';
    }

    $brand = $hasLogo
        ? logo_img($s, 'h-auto max-h-12 w-auto max-w-[180px] object-contain sm:max-h-14 sm:max-w-[220px]')
        : '<span class="font-display text-xl font-semibold tracking-tight text-charcoal sm:text-2xl">' . e($s['storeName']) . '</span>';

    $footerBrand = $hasLogo
        ? logo_img($s, 'h-auto max-h-20 w-auto max-w-[240px] object-contain brightness-0 invert')
        : '<p class="font-display text-xl font-semibold">' . e($s['storeName']) . '</p>';

    $iconCls = 'hidden rounded-full p-2 text-charcoal transition-colors hover:bg-sand/50 sm:block';
    $socialIcons = '';
    if ($s['instagramUrl']) $socialIcons .= '<a href="' . e($s['instagramUrl']) . '" target="_blank" rel="noopener noreferrer" class="' . $iconCls . '" aria-label="Instagram">' . icon('instagram', 'h-5 w-5') . '</a>';
    if ($s['tiktokUrl']) $socialIcons .= '<a href="' . e($s['tiktokUrl']) . '" target="_blank" rel="noopener noreferrer" class="' . $iconCls . '" aria-label="TikTok">' . icon('tiktok', 'h-5 w-5') . '</a>';

    $social = '<a href="' . e(tel_link($s['customerCarePhone'])) . '" class="inline-flex items-center gap-2 rounded-full border border-sand/30 px-4 py-2 text-sm transition-colors hover:border-terracotta hover:text-terracotta">' . icon('phone') . 'Call us</a>';
    if ($s['instagramUrl']) $social .= '<a href="' . e($s['instagramUrl']) . '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full border border-sand/30 px-4 py-2 text-sm transition-colors hover:border-terracotta hover:text-terracotta">' . icon('instagram') . 'Instagram</a>';
    if ($s['tiktokUrl']) $social .= '<a href="' . e($s['tiktokUrl']) . '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full border border-sand/30 px-4 py-2 text-sm transition-colors hover:border-terracotta hover:text-terracotta">' . icon('tiktok') . 'TikTok</a>';
    if ($s['whatsappNumber']) $social .= '<a href="' . e(whatsapp_link($s['whatsappNumber'])) . '" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full border border-sand/30 px-4 py-2 text-sm transition-colors hover:border-terracotta hover:text-terracotta">' . icon('message') . 'WhatsApp</a>';

    $year = date('Y');
    $storeJson = e(json_encode([
        'storeName' => $s['storeName'],
        'phone' => $s['customerCarePhone'],
        'vapidKey' => config('VAPID_PUBLIC_KEY'),
        'gaId' => $s['gaId'] ?? '',
        'pixelId' => $s['metaPixelId'] ?? '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $newBar = new_arrival_bar();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:site_name" content="<?= e($s['storeName']) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:type" content="<?= isset($meta['price']) ? 'product' : 'website' ?>">
<?php if (isset($meta['price'])): ?><meta property="product:price:amount" content="<?= e((string) ($meta['price'] / 100)) ?>"><meta property="product:price:currency" content="NGN"><?php endif; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if ($image): ?><meta property="og:image" content="<?= e($image) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<meta name="theme-color" content="<?= e($s['colorTerracotta']) ?>">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/tk-icon.png">
<link rel="apple-touch-icon" href="/tk-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=<?= ASSET_VERSION ?>">
</head>
<body class="min-h-screen font-sans antialiased" style="<?= e(theme_style($s)) ?>">
<div class="flex min-h-screen flex-col bg-cream text-charcoal" id="store" data-store="<?= $storeJson ?>">
<?= $newBar ?>
<header class="sticky top-0 z-50 border-b border-sand/80 bg-cream/95 backdrop-blur-md">
  <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 sm:py-4">
    <a href="/" class="flex min-w-0 shrink items-center gap-2" aria-label="<?= e($s['storeName']) ?>"><?= $brand ?></a>
    <nav class="hidden items-center gap-8 md:flex"><?= $navLinks ?></nav>
    <div class="flex shrink-0 items-center gap-3">
      <a href="/shop?q=" data-search-toggle class="rounded-full p-2 text-charcoal transition-colors hover:bg-sand/50" aria-label="Search"><?= icon('search', 'h-5 w-5') ?></a>
      <?= $socialIcons ?>
      <a href="/wishlist" class="relative rounded-full p-2 text-charcoal transition-colors hover:bg-sand/50" aria-label="Wishlist">
        <?= icon('heart', 'h-5 w-5') ?>
        <span data-wish-count class="absolute -right-0.5 -top-0.5 hidden h-5 w-5 items-center justify-center rounded-full bg-terracotta text-[10px] font-bold text-white"></span>
      </a>
      <a href="/cart" class="relative rounded-full p-2 text-charcoal transition-colors hover:bg-sand/50" aria-label="Shopping cart">
        <?= icon('bag', 'h-5 w-5') ?>
        <span data-cart-count class="absolute -right-0.5 -top-0.5 hidden h-5 w-5 items-center justify-center rounded-full bg-terracotta text-[10px] font-bold text-white"></span>
      </a>
      <button type="button" id="menu-toggle" class="rounded-full p-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-terracotta md:hidden" aria-label="Open menu" aria-expanded="false">
        <span data-icon-open><?= icon('menu', 'h-5 w-5') ?></span><span data-icon-close class="hidden"><?= icon('x', 'h-5 w-5') ?></span>
      </button>
    </div>
  </div>
  <form id="search-bar" action="/shop" method="get" class="hidden border-t border-sand px-4 py-3" role="search"><div class="mx-auto flex max-w-6xl gap-2 sm:px-2"><label for="q" class="sr-only">Search</label><input id="q" name="q" type="search" placeholder="Search brands, items, colours…" class="min-w-0 flex-1 rounded-full border border-sand bg-white px-4 py-2 text-sm focus:border-terracotta focus:outline-none"><button class="rounded-full bg-terracotta px-4 py-2 text-sm font-semibold text-cream">Search</button></div></form>
  <nav id="mobile-menu" class="hidden border-t border-sand px-4 py-4 md:hidden"><div class="flex flex-col gap-3"><?= $mobileLinks ?></div></nav>
</header>
<main class="flex-1"><?= $content ?></main>
<footer class="mt-auto border-t border-sand bg-charcoal text-cream">
  <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
    <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-3">
      <div>
        <?= $footerBrand ?>
        <p class="mt-3 max-w-xs text-sm text-sand/90"><?= e($s['tagline']) ?></p>
        <div class="mt-5 max-w-xs"><?= notify_button('dark') ?></div>
        <p class="mt-5 text-sm text-sand/90">Get new drops by email</p>
        <div class="mt-2"><?= email_signup('dark') ?></div>
      </div>
      <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-sand">Shop</p>
        <ul class="mt-4 space-y-2 text-sm">
          <li><a href="/shop" class="hover:text-terracotta">All items</a></li>
          <li><a href="/contact" class="hover:text-terracotta">Contact &amp; support</a></li>
          <li><a href="/cart" class="hover:text-terracotta">Your cart</a></li>
          <li><a href="/wishlist" class="hover:text-terracotta">Wishlist</a></li>
          <li><a href="/track" class="hover:text-terracotta">Track your order</a></li>
          <li><a href="/sell" class="hover:text-terracotta">Sell to us</a></li>
          <li><a href="/size-guide" class="hover:text-terracotta">Size guide</a></li>
          <li><a href="/returns" class="hover:text-terracotta">Returns &amp; refunds</a></li>
        </ul>
      </div>
      <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-sand">Connect</p>
        <div class="mt-4 flex flex-wrap gap-3"><?= $social ?></div>
        <p class="mt-4 text-sm text-sand/80"><?= e($s['customerCareHours']) ?></p>
      </div>
    </div>
    <p class="mt-10 border-t border-sand/20 pt-6 text-center text-xs text-sand/70">© <?= $year ?> <?= e($s['storeName']) ?>. All rights reserved. · <a href="/terms" class="hover:text-cream">Terms</a> · <a href="/privacy" class="hover:text-cream">Privacy</a></p>
  </div>
</footer>
<?= chat_widget_html($s) ?>
</div>
<script src="/assets/app.js?v=<?= ASSET_VERSION ?>"></script>
</body>
</html>
<?php
}

function chat_widget_html(array $s): string
{
    $quick = ["What's available now?", 'How do I pay?', 'Do you deliver nationwide?', 'Talk to customer care'];
    $btns = '';
    foreach ($quick as $q) {
        $btns .= '<button type="button" data-quick="' . e($q) . '" class="rounded-full border border-sand bg-white px-3 py-1.5 text-xs text-charcoal transition-colors hover:border-terracotta hover:text-terracotta">' . e($q) . '</button>';
    }
    return '<button type="button" id="chat-toggle" class="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-terracotta text-white shadow-lg transition-transform hover:scale-105 hover:bg-rust focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-charcoal focus-visible:ring-offset-2" aria-label="Chat with us">'
        . '<span data-icon-open>' . icon('message', 'h-6 w-6') . '</span><span data-icon-close class="hidden">' . icon('x', 'h-6 w-6') . '</span></button>'
        . '<div id="chat-panel" class="fixed bottom-24 right-5 z-50 hidden h-[32rem] w-[calc(100vw-2.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-sand bg-cream shadow-2xl">'
        . '<div class="flex items-center gap-3 bg-charcoal px-4 py-3 text-cream"><div class="flex h-9 w-9 items-center justify-center rounded-full bg-terracotta">' . icon('message', 'h-5 w-5') . '</div>'
        . '<div><p class="text-sm font-semibold leading-tight">' . e($s['storeName']) . ' Assistant</p><p class="text-xs text-sand/80">Typically replies instantly</p></div></div>'
        . '<div id="chat-log" role="log" aria-live="polite" aria-label="Chat messages" class="flex-1 space-y-3 overflow-y-auto p-4"></div>'
        . '<div id="chat-quick" class="hidden flex-wrap gap-2 px-4 pb-3">' . $btns . '</div>'
        . '<form id="chat-form" class="flex items-center gap-2 border-t border-sand bg-white p-3">'
        . '<input id="chat-input" placeholder="Ask about an item, price, delivery…" aria-label="Message" autocomplete="off" class="flex-1 rounded-full border border-sand bg-cream px-4 py-2 text-sm focus:border-terracotta focus:outline-none">'
        . '<button type="submit" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-terracotta text-white transition-colors hover:bg-rust disabled:opacity-50" aria-label="Send">' . icon('send') . '</button>'
        . '</form></div>';
}

function not_found_page(): void
{
    http_response_code(404);
    render_store('<div class="mx-auto max-w-lg px-4 py-24 text-center"><h1 class="font-display text-3xl font-semibold">Page not found</h1>'
        . '<p class="mt-2 text-warmgray">The page you\'re looking for doesn\'t exist.</p>'
        . '<a href="/shop" class="mt-8 ' . btn_class() . '">Browse shop</a></div>', ['title' => 'Not found']);
}
