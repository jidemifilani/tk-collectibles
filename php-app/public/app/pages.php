<?php
/** Storefront pages. Cart, checkout, wishlist and order pages are filled in by assets/app.js. */

const SHOP_SORTS = ['new' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];

function page_home(): void
{
    $s = settings();
    $featured = fetch_products('featured = 1 AND ' . available_sql(), [], 'ORDER BY createdAt DESC LIMIT 4');
    $nextDrop = next_drop_at();
    $reviews = db()->query('SELECT * FROM Review WHERE approved = 1 ORDER BY createdAt DESC LIMIT 6')->fetchAll();
    $spot = db()->query('SELECT * FROM Review WHERE featured = 1 AND approved = 1 LIMIT 1')->fetch();

    $perks = [
        ['sparkles', 'Curated picks', 'Every piece hand-selected — no bulk warehouse vibes.'],
        ['truck', 'Nationwide delivery', 'We ship across Nigeria. Track your order online or on WhatsApp.'],
        ['shield', 'Pay your way', 'Paystack card, bank or USSD' . (trim($s['bankDetails']) !== '' ? ', bank transfer' : '') . ($s['payOnDelivery'] ? ', or pay on delivery' : '') . '.'],
    ];
    $perkHtml = '';
    foreach ($perks as [$ic, $t, $d]) {
        $perkHtml .= '<div class="rounded-2xl bg-white p-6 ring-1 ring-sand/60">' . icon($ic, 'h-8 w-8 text-terracotta')
            . '<h3 class="mt-4 font-display text-lg font-semibold">' . e($t) . '</h3><p class="mt-2 text-sm text-warmgray">' . e($d) . '</p></div>';
    }

    $reviewHtml = '';
    foreach ($reviews as $rv) {
        $stars = str_repeat('★', (int) $rv['rating']) . '<span class="text-sand">' . str_repeat('★', 5 - (int) $rv['rating']) . '</span>';
        $reviewHtml .= '<figure class="flex flex-col rounded-2xl bg-white p-5 ring-1 ring-sand/60">'
            . ($rv['photoUrl'] ? '<img src="' . e($rv['photoUrl']) . '" alt="' . e($rv['name']) . ' wearing their find" loading="lazy" class="mb-4 aspect-square w-full rounded-xl object-cover">' : '')
            . '<p class="text-terracotta" aria-label="' . (int) $rv['rating'] . ' out of 5 stars">' . $stars . '</p>'
            . '<blockquote class="mt-2 flex-1 text-sm leading-relaxed text-charcoal">“' . e($rv['body']) . '”</blockquote>'
            . '<figcaption class="mt-3 text-xs font-semibold uppercase tracking-wide text-warmgray">' . e($rv['name']) . '</figcaption></figure>';
    }

    ob_start(); ?>
<section class="relative overflow-hidden bg-charcoal text-cream">
  <div class="absolute inset-0 opacity-40"><img src="<?= e($s['heroImageUrl']) ?>" alt="" class="h-full w-full object-cover"></div>
  <div class="relative mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32 lg:py-40">
    <p class="inline-flex items-center gap-2 rounded-full bg-terracotta/90 px-3 py-1 text-xs font-medium uppercase tracking-wider"><?= icon('sparkles', 'h-3.5 w-3.5') ?><?= e($s['tagline']) ?></p>
    <h1 class="mt-6 max-w-2xl font-display text-4xl font-semibold leading-tight sm:text-5xl lg:text-6xl text-balance"><?= e($s['heroTitle']) ?></h1>
    <p class="mt-6 max-w-lg text-lg text-sand/90"><?= e($s['heroSubtitle']) ?></p>
    <?php if ($nextDrop): ?>
    <div class="mt-8 inline-block rounded-2xl bg-white/10 px-5 py-4 backdrop-blur" data-countdown="<?= $nextDrop ?>">
      <p class="text-xs font-semibold uppercase tracking-wider text-sand">Next drop · <?= e(date('D j M, g:ia', intdiv($nextDrop, 1000))) ?></p>
      <p class="mt-1 font-display text-3xl font-semibold tabular-nums" data-countdown-text>&nbsp;</p>
    </div>
    <?php endif; ?>
    <div class="mt-10 flex flex-wrap gap-4">
      <a href="/shop" class="<?= btn_class('primary', false, 'bg-terracotta hover:bg-rust') ?>">Shop the drop <?= icon('arrow-right') ?></a>
      <a href="/contact" class="<?= btn_class('outline', false, 'border-cream text-cream hover:bg-cream hover:text-charcoal') ?>"><?= icon('phone') ?> Customer care</a>
    </div>
  </div>
</section>
<section class="mx-auto max-w-6xl px-4 py-16 sm:px-6"><div class="grid gap-8 sm:grid-cols-3"><?= $perkHtml ?></div></section>
<section class="bg-sand/40 py-16">
  <div class="mx-auto max-w-6xl px-4 sm:px-6">
    <div class="flex items-end justify-between gap-4">
      <div>
        <h2 class="font-display text-3xl font-semibold">This week&apos;s drop</h2>
        <p class="mt-2 text-warmgray">Limited pieces — when they&apos;re gone, they&apos;re gone.</p>
        <div class="mt-4"><?= notify_button() ?></div>
      </div>
      <a href="/shop" class="hidden text-sm font-medium text-terracotta hover:text-rust sm:block">View all →</a>
    </div>
    <?= $featured ? product_grid($featured) : '<p class="py-12 text-center text-warmgray">No featured items yet. Add products in the admin panel.</p>' ?>
  </div>
</section>
<section id="recently-viewed" class="mx-auto hidden max-w-6xl px-4 pt-16 sm:px-6">
  <h2 class="font-display text-2xl font-semibold">Recently viewed</h2>
  <div data-recent-grid class="mt-6 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4"></div>
</section>
<section id="reviews" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div><h2 class="font-display text-3xl font-semibold">Happy customers</h2><p class="mt-2 text-warmgray">Real buyers, real finds.</p></div>
    <button type="button" data-review-toggle class="<?= btn_class('outline') ?>"><?= icon('star') ?> Write a review</button>
  </div>
  <form id="review-form" class="mt-6 hidden max-w-xl space-y-3 rounded-2xl bg-white p-6 ring-1 ring-sand/60">
    <div class="flex gap-1 text-2xl text-sand" role="radiogroup" aria-label="Rating">
      <?php for ($i = 1; $i <= 5; $i++): ?><button type="button" data-star="<?= $i ?>" role="radio" aria-checked="false" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>" class="hover:text-terracotta">★</button><?php endfor; ?>
    </div>
    <input name="name" required maxlength="80" placeholder="Your first name" class="w-full rounded-xl border border-sand px-4 py-2 text-sm focus:border-terracotta focus:outline-none">
    <textarea name="body" required maxlength="1000" rows="3" placeholder="What did you buy and how do you like it?" class="w-full rounded-xl border border-sand px-4 py-2 text-sm focus:border-terracotta focus:outline-none"></textarea>
    <p data-review-msg class="hidden text-sm"></p>
    <button type="submit" class="<?= btn_class() ?>">Submit review</button>
  </form>
  <?php if ($spot): ?>
  <div class="mt-8 grid items-center gap-6 rounded-2xl bg-terracotta p-6 text-cream sm:grid-cols-[200px_1fr] sm:p-8">
    <?php if ($spot['photoUrl']): ?><img src="<?= e($spot['photoUrl']) ?>" alt="<?= e($spot['name']) ?>" class="aspect-square w-full rounded-xl object-cover"><?php endif; ?>
    <div><p class="text-xs font-semibold uppercase tracking-wider text-cream/80">Customer of the month</p><p class="mt-2 font-display text-2xl"><?= e($spot['name']) ?></p><blockquote class="mt-3 text-cream/90">“<?= e($spot['body']) ?>”</blockquote></div>
  </div>
  <?php endif; ?>
  <?= $reviewHtml ? '<div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">' . $reviewHtml . '</div>' : '<p class="mt-8 text-warmgray">Be the first to share your find!</p>' ?>
</section>
<section id="about" class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
  <div class="grid items-center gap-12 lg:grid-cols-2">
    <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-sand"><img src="https://images.unsplash.com/photo-1445205170230-053b83016050?w=800&q=80" alt="Thrift clothing" loading="lazy" class="absolute inset-0 h-full w-full object-cover"></div>
    <div>
      <h2 class="font-display text-3xl font-semibold"><?= e($s['aboutTitle']) ?></h2>
      <p class="mt-4 whitespace-pre-line leading-relaxed text-warmgray"><?= e($s['aboutText']) ?></p>
      <div class="mt-8 flex flex-wrap gap-4">
        <a href="/shop" class="<?= btn_class() ?>">Start shopping</a>
        <a href="<?= e(tel_link($s['customerCarePhone'])) ?>" class="<?= btn_class('outline') ?>">Call <?= e($s['customerCarePhone']) ?></a>
      </div>
      <div class="mt-10 rounded-2xl bg-sand/50 p-5"><p class="font-semibold">Never miss a drop</p><p class="mt-1 text-sm text-warmgray">We&apos;ll email you when new pieces land.</p><div class="mt-3"><?= email_signup('light', '', true) ?></div></div>
    </div>
  </div>
</section>
<?php
    render_store(ob_get_clean());
}

function distinct_values(string $col): array
{
    return db()->query("SELECT DISTINCT \"$col\" FROM Product WHERE \"$col\" != '' AND " . published_sql() . " ORDER BY \"$col\" COLLATE NOCASE")->fetchAll(PDO::FETCH_COLUMN);
}

function page_shop(): void
{
    $pageSize = 12;
    $raw = (string) ($_GET['category'] ?? '');
    $category = $raw === 'featured' ? 'featured' : (isset(CATEGORIES[$raw]) ? $raw : 'all');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $brand = trim((string) ($_GET['brand'] ?? ''));
    $color = trim((string) ($_GET['color'] ?? ''));
    $sort = isset(SHOP_SORTS[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'new';
    $min = max(0, (int) ($_GET['min'] ?? 0));
    $max = max(0, (int) ($_GET['max'] ?? 0));
    $showSold = ($_GET['sold'] ?? '1') !== '0';
    $search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
    $era = in_array($_GET['era'] ?? '', ERAS, true) ? $_GET['era'] : '';
    $tag = isset(PRODUCT_TAGS[$_GET['tag'] ?? '']) ? $_GET['tag'] : '';
    $size = mb_substr(trim((string) ($_GET['size'] ?? '')), 0, 40);

    // Sold pieces stay visible (marked "Sold") so the shop looks active; available pieces come first.
    $where = published_sql() . ($showSold ? '' : ' AND inStock = 1');
    $params = [];
    if ($category === 'featured') $where .= ' AND featured = 1 AND inStock = 1';
    elseif ($category !== 'all') { $where .= ' AND category = ?'; $params[] = $category; }
    if ($brand !== '') { $where .= ' AND brand = ? COLLATE NOCASE'; $params[] = $brand; }
    if ($color !== '') { $where .= ' AND color = ? COLLATE NOCASE'; $params[] = $color; }
    if ($search !== '') {
        $where .= " AND (name LIKE ? ESCAPE '\\' OR brand LIKE ? ESCAPE '\\' OR description LIKE ? ESCAPE '\\' OR color LIKE ? ESCAPE '\\' OR category LIKE ? ESCAPE '\\')";
        $like = '%' . addcslashes($search, '%_\\') . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($era !== '') { $where .= ' AND era = ?'; $params[] = $era; }
    if ($size !== '') { $where .= ' AND size = ? COLLATE NOCASE'; $params[] = $size; }
    if ($tag !== '') { $where .= " AND (',' || tags || ',') LIKE ?"; $params[] = '%,' . $tag . ',%'; }
    if ($min > 0) { $where .= ' AND salePrice >= ?'; $params[] = $min * 100; }
    if ($max > 0) { $where .= ' AND salePrice <= ?'; $params[] = $max * 100; }
    $order = 'ORDER BY inStock DESC, ' . ['new' => 'createdAt DESC', 'price_asc' => 'salePrice ASC', 'price_desc' => 'salePrice DESC'][$sort];

    $total = count_products($where, $params);
    $products = fetch_products($where, $params, $order . ' LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize));
    $totalPages = max(1, (int) ceil($total / $pageSize));

    $query = array_filter(['size' => $size, 'q' => $search, 'era' => $era, 'tag' => $tag, 'brand' => $brand, 'color' => $color, 'sort' => $sort === 'new' ? '' : $sort, 'min' => $min ?: '', 'max' => $max ?: '', 'sold' => $showSold ? '' : '0'], fn($v) => $v !== '');
    $href = function (array $over) use ($category, $query): string {
        $q = array_filter(array_merge($category !== 'all' ? ['category' => $category] : [], $query, $over), fn($v) => $v !== '' && $v !== null);
        return $q ? '/shop?' . http_build_query($q) : '/shop';
    };

    $pill = function (string $href, string $label, bool $active): string {
        return '<a href="' . e($href) . '"' . ($active ? ' aria-current="page"' : '') . ' class="shrink-0 rounded-full px-4 py-2 text-sm font-medium transition-colors '
            . ($active ? 'bg-terracotta text-white' : 'bg-white text-charcoal ring-1 ring-sand hover:bg-sand/50') . '">' . e($label) . '</a>';
    };
    $pills = $pill('/shop?category=featured', 'New drops', $category === 'featured');
    foreach (CATEGORIES as $id => $label) {
        $q = array_filter(array_merge($id === 'all' ? [] : ['category' => $id], $query));
        $pills .= $pill($q ? '/shop?' . http_build_query($q) : '/shop', $label, $category === $id);
    }

    $select = function (string $name, string $label, array $opts, string $current): string {
        $html = '<label class="flex flex-col text-xs font-medium text-warmgray">' . e($label) . '<select name="' . $name . '" class="mt-1 rounded-xl border border-sand bg-white px-3 py-2 text-sm text-charcoal">';
        foreach ($opts as $v => $t) $html .= '<option value="' . e((string) $v) . '"' . ((string) $v === $current ? ' selected' : '') . '>' . e($t) . '</option>';
        return $html . '</select></label>';
    };
    $brands = distinct_values('brand');
    $colors = distinct_values('color');
    $filters = '<form method="get" action="/shop" data-size-memory class="mt-6 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 ring-1 ring-sand/60" data-autosubmit>'
        . ($category !== 'all' ? '<input type="hidden" name="category" value="' . e($category) . '">' : '')
        . '<label class="flex flex-col text-xs font-medium text-warmgray">Search<input name="q" type="search" value="' . e($search) . '" placeholder="Denim, Nike, red…" class="mt-1 w-44 rounded-xl border border-sand px-3 py-2 text-sm text-charcoal"></label>'
        . $select('sort', 'Sort', SHOP_SORTS, $sort)
        . $select('era', 'Era', ['' => 'Any era'] + array_combine(ERAS, ERAS), $era)
        . $select('tag', 'Type', ['' => 'All pieces'] + PRODUCT_TAGS, $tag)
        . (($sizes = distinct_values('size')) ? $select('size', 'Size', ['' => 'All sizes'] + array_combine($sizes, $sizes), $size) : '')
        . ($brands ? $select('brand', 'Brand', ['' => 'All brands'] + array_combine($brands, $brands), $brand) : '')
        . ($colors ? $select('color', 'Colour', ['' => 'All colours'] + array_combine($colors, $colors), $color) : '')
        . '<label class="flex flex-col text-xs font-medium text-warmgray">Min ₦<input name="min" type="number" min="0" step="500" value="' . ($min ?: '') . '" class="mt-1 w-28 rounded-xl border border-sand px-3 py-2 text-sm text-charcoal"></label>'
        . '<label class="flex flex-col text-xs font-medium text-warmgray">Max ₦<input name="max" type="number" min="0" step="500" value="' . ($max ?: '') . '" class="mt-1 w-28 rounded-xl border border-sand px-3 py-2 text-sm text-charcoal"></label>'
        . '<label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="sold" value="0"' . ($showSold ? '' : ' checked') . '> Hide sold</label>'
        . '<button type="submit" class="rounded-full bg-charcoal px-4 py-2 text-sm font-medium text-cream">Apply</button>'
        . ($query ? '<a href="' . e($category !== 'all' ? '/shop?category=' . $category : '/shop') . '" class="pb-2 text-sm text-terracotta underline">Clear</a>' : '')
        . '</form>';

    $pager = '';
    if ($totalPages > 1) {
        $navCls = fn(bool $off) => 'rounded-full px-4 py-2 text-sm font-medium ring-1 ring-sand transition-colors ' . ($off ? 'pointer-events-none text-warmgray/50' : 'bg-white text-charcoal hover:bg-sand/50');
        $pager = '<nav class="mt-10 flex items-center justify-center gap-2" aria-label="Shop pagination">'
            . '<a href="' . e($href(['page' => max(1, $page - 1) > 1 ? $page - 1 : null])) . '" class="' . $navCls($page === 1) . '">Previous</a>'
            . '<span class="text-sm text-warmgray">Page ' . $page . ' of ' . $totalPages . '</span>'
            . '<a href="' . e($href(['page' => min($totalPages, $page + 1)])) . '" class="' . $navCls($page === $totalPages) . '">Next</a></nav>';
    }

    $body = '<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">'
        . '<div class="max-w-xl"><h1 class="font-display text-3xl font-semibold sm:text-4xl">Shop all</h1>'
        . '<p class="mt-2 text-warmgray">' . ($search !== '' ? 'Results for “' . e($search) . '”' : 'Browse our full collection of curated thrift — updated weekly.') . '</p></div>'
        . '<div class="relative mt-8"><div class="flex gap-2 overflow-x-auto pb-2">' . $pills . '</div>'
        . '<div class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-cream to-transparent"></div></div>'
        . $filters
        . '<p class="mt-4 text-sm text-warmgray">' . $total . ' ' . ($total === 1 ? 'piece' : 'pieces') . '</p>'
        . ($products ? product_grid($products) . $pager : '<p class="mt-16 text-center text-warmgray">No items match these filters right now.</p>')
        . '</div>';
    render_store($body, ['title' => 'Shop']);
}

function page_product(string $slug): void
{
    $st = db()->prepare('SELECT * FROM Product WHERE slug = ?');
    $st->execute([$slug]);
    $p = $st->fetch();
    $availability = $p ? product_availability($p) : null;
    if (!$p || ($availability === 'scheduled' && !is_admin())) { not_found_page(); return; }

    $s = settings();
    $images = product_images($p);
    $similar = fetch_products('category = ? AND id != ? AND ' . available_sql(), [$p['category'], $p['id']], 'ORDER BY createdAt DESC LIMIT 4');
    $price = $availability === 'available' ? effective_price($p) : (int) $p['salePrice'];
    $viewing = $availability === 'available' ? record_view_and_count($p['id']) : 0;

    $badge = match ($availability) {
        'sold' => '<span class="rounded-full bg-rust/10 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-rust">Sold</span>',
        'reserved' => '<span class="rounded-full bg-olive/10 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-olive">Reserved</span>',
        'scheduled' => '<span class="rounded-full bg-sand px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-charcoal">Scheduled — admin preview</span>',
        default => '<span class="rounded-full bg-terracotta/10 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-terracotta">Last one · 1 of 1</span>',
    };

    $thumbs = '';
    if (count($images) > 1) {
        foreach ($images as $i => $src) {
            $thumbs .= '<button type="button" data-thumb="' . $i . '" aria-label="View photo ' . ($i + 1) . ' of ' . count($images) . '" class="relative h-16 w-14 shrink-0 overflow-hidden rounded-lg bg-sand ring-2 transition-all ' . ($i === 0 ? 'ring-terracotta' : 'ring-transparent hover:ring-warmgray/40') . '">'
                . '<img src="' . e($src) . '" alt="" class="absolute inset-0 h-full w-full object-cover"></button>';
        }
        $thumbs = '<div class="mt-3 flex gap-2 overflow-x-auto">' . $thumbs . '</div>';
    }

    $productUrl = app_url() . '/product/' . rawurlencode($p['slug']);
    $waText = 'Hi ' . $s['storeName'] . '! I want to order "' . $p['name'] . '" (Size ' . $p['size'] . ', ' . naira((int) $p['salePrice']) . '). ' . $productUrl;
    $waBtn = $s['whatsappNumber']
        ? '<a href="' . e(whatsapp_link($s['whatsappNumber']) . '?text=' . rawurlencode($waText)) . '" target="_blank" rel="noopener noreferrer" class="' . btn_class('secondary', true, 'mt-3') . '">' . icon('message') . ' Order on WhatsApp</a>'
        : '';

    if ($availability === 'sold') {
        $cta = '<button disabled class="' . btn_class('outline', true) . '">Sold</button>'
            . '<div class="mt-6 rounded-2xl bg-sand/50 p-5"><p class="font-semibold">Missed this one?</p><p class="mt-1 text-sm text-warmgray">Get an email when similar ' . e(CATEGORIES[$p['category']] ?? 'pieces') . ' arrive.</p><div class="mt-3">' . email_signup('light', $p['category']) . '</div></div>';
    } elseif ($availability === 'reserved') {
        $cta = '<button disabled class="' . btn_class('outline', true) . '">Reserved — checkout in progress</button>'
            . '<p class="mt-2 text-center text-xs text-warmgray" data-countdown="' . (int) $p['reservedUntil'] . '" data-countdown-soft>Someone&apos;s completing checkout on this piece. If their payment doesn&apos;t go through it reopens in <strong data-countdown-text class="tabular-nums"></strong>.</p>';
    } elseif ($availability === 'scheduled') {
        $cta = '<p class="rounded-xl bg-sand/60 p-4 text-sm">Goes live ' . e(date('D j M Y, g:ia', intdiv((int) $p['publishAt'], 1000))) . '. Shoppers can&apos;t see it yet.</p>';
    } else {
        $data = e(json_encode([
            'productId' => $p['id'], 'name' => $p['name'], 'slug' => $p['slug'],
            'salePrice' => $price, 'originalPrice' => (int) $p['originalPrice'],
            'imageUrl' => $p['imageUrl'], 'size' => $p['size'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $cta = '<button type="button" data-add-to-cart="' . $data . '" class="' . btn_class('primary', true) . '">Add to cart</button>' . $waBtn;
        if ($s['offersEnabled']) {
            $in = 'w-full rounded-xl border border-sand bg-white px-3 py-2 text-sm focus:border-terracotta focus:outline-none';
            $cta .= '<details class="mt-4 rounded-xl bg-white p-4 ring-1 ring-sand/60"><summary class="cursor-pointer text-sm font-semibold">Make an offer</summary>'
                . '<form data-offer="' . e($p['id']) . '" class="mt-3 grid gap-2 sm:grid-cols-2">'
                . '<input name="amount" type="number" required min="' . (int) ceil($p['salePrice'] / 200) . '" max="' . (int) ($p['salePrice'] / 100 - 1) . '" placeholder="Your offer (₦)" class="' . $in . ' sm:col-span-2">'
                . '<input name="name" required maxlength="80" placeholder="Name" class="' . $in . '"><input name="phone" required maxlength="30" placeholder="WhatsApp number" class="' . $in . '">'
                . '<input name="email" type="email" required placeholder="Email" class="' . $in . ' sm:col-span-2">'
                . '<button type="submit" class="rounded-full bg-charcoal px-4 py-2 text-sm font-semibold text-cream sm:col-span-2">Send offer</button>'
                . '<p data-offer-msg class="hidden text-sm sm:col-span-2"></p></form>'
                . '<p class="mt-2 text-xs text-warmgray">If we accept, you get a code to buy it at your price within 48 hours.</p></details>';
        }
    }

    $details = [['Size', $p['size']], ['Condition', $p['condition']]];
    if ($p['brand'] !== '') $details[] = ['Brand', $p['brand']];
    if ($p['color'] !== '') $details[] = ['Colour', $p['color']];
    if ($p['era'] !== '') $details[] = ['Era', $p['era']];
    if ($p['fabric'] !== '') $details[] = ['Fabric & care', $p['fabric']];
    $dl = '';
    foreach ($details as [$k, $v]) $dl .= '<div><dt class="text-xs uppercase text-warmgray">' . e($k) . '</dt><dd class="mt-1 font-medium">' . e($v) . '</dd></div>';

    ob_start(); ?>
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12" data-recent="<?= e($p['id']) ?>">
  <a href="/shop" class="inline-flex items-center gap-2 text-sm text-warmgray hover:text-charcoal"><?= icon('arrow-left') ?> Back to shop</a>
  <div class="mt-8 grid gap-10 lg:grid-cols-2">
    <div id="gallery" data-images="<?= e(json_encode($images, JSON_UNESCAPED_SLASHES)) ?>">
      <div class="relative">
        <button type="button" data-zoom class="group relative block aspect-[3/4] w-full overflow-hidden rounded-2xl bg-sand" aria-label="Enlarge photo">
          <img data-main src="<?= e($images[0] ?? '') ?>" alt="<?= e($p['name']) ?>" class="absolute inset-0 h-full w-full object-cover<?= $availability === 'sold' ? ' grayscale' : '' ?>">
          <span class="absolute bottom-3 right-3 flex h-9 w-9 items-center justify-center rounded-full bg-charcoal/70 text-cream opacity-0 transition-opacity group-hover:opacity-100"><?= icon('zoom-in') ?></span>
        </button>
        <?= wish_button($p['id'], 'absolute right-3 top-3') ?>
      </div>
      <?= $thumbs ?>
      <?php if ($p['videoUrl'] !== ''): ?><video src="<?= e($p['videoUrl']) ?>" controls playsinline preload="metadata" class="mt-3 w-full rounded-2xl bg-charcoal"></video><?php endif; ?>
      <?php if (count($images) > 1): ?><p class="mt-2 text-xs text-warmgray">Tap a photo to zoom in — close-ups show any wear honestly.</p><?php endif; ?>
      <div data-lightbox class="fixed inset-0 z-[60] hidden items-center justify-center bg-charcoal/95 p-4" role="dialog" aria-modal="true" aria-label="<?= e($p['name']) ?> — enlarged photo">
        <button type="button" data-close class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-cream hover:bg-white/20" aria-label="Close enlarged photo"><?= icon('x', 'h-5 w-5') ?></button>
        <img data-big src="" alt="<?= e($p['name']) ?>" class="max-h-[85vh] max-w-full object-contain">
      </div>
    </div>
    <div class="flex flex-col">
      <div class="flex flex-wrap items-center gap-3"><p class="text-sm uppercase tracking-wide text-warmgray"><?= e($p['category']) ?></p><?= $badge ?></div>
      <h1 class="mt-2 font-display text-3xl font-semibold sm:text-4xl"><?= e($p['name']) ?></h1>
      <div class="mt-4"><?= price_html((int) $p['originalPrice'], $price, 'lg') ?></div>
      <?php if ($price < (int) $p['salePrice']): ?><p class="mt-1 text-sm font-semibold text-olive">⚡ Flash sale price — was <?= naira((int) $p['salePrice']) ?></p><?php endif; ?>
      <?php if ($viewing >= 2): ?><p class="mt-2 text-sm text-rust">👀 <?= $viewing ?> people are looking at this right now</p><?php endif; ?>
      <dl class="mt-6 grid grid-cols-2 gap-4 rounded-xl bg-white p-4 ring-1 ring-sand/60"><?= $dl ?></dl>
      <?php if ($p['tags'] !== ''): ?><div class="mt-4 flex flex-wrap gap-2"><?php foreach (explode(',', $p['tags']) as $t): if (isset(PRODUCT_TAGS[$t])): ?><a href="/shop?tag=<?= e($t) ?>" class="rounded-full bg-terracotta/10 px-3 py-1 text-xs font-semibold text-terracotta"><?= e(PRODUCT_TAGS[$t]) ?></a><?php endif; endforeach; ?></div><?php endif; ?>
      <?php if (trim($p['fit']) !== '' || trim($p['modelInfo']) !== ''): ?>
      <div class="mt-4 rounded-xl bg-white p-4 text-sm ring-1 ring-sand/60"><p class="text-xs font-semibold uppercase text-warmgray">Fit</p>
        <?php if (trim($p['fit']) !== ''): ?><p class="mt-1"><?= e($p['fit']) ?></p><?php endif; ?>
        <?php if (trim($p['modelInfo']) !== ''): ?><p class="mt-1 text-warmgray"><?= e($p['modelInfo']) ?></p><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if (trim($p['measurements']) !== ''): ?>
      <div class="mt-4 rounded-xl bg-white p-4 ring-1 ring-sand/60"><p class="text-xs font-semibold uppercase text-warmgray">Measurements (laid flat)</p><p class="mt-1 whitespace-pre-line text-sm"><?= e($p['measurements']) ?></p></div>
      <?php endif; ?>
      <?php if (trim($p['flaws']) !== ''): ?>
      <div class="mt-4 flex gap-3 rounded-xl bg-olive/10 p-4 text-sm"><?= icon('alert', 'mt-0.5 h-4 w-4 shrink-0 text-olive') ?><div><p class="font-semibold text-olive">Honest condition notes</p><p class="mt-1 whitespace-pre-line text-charcoal"><?= e($p['flaws']) ?></p></div></div>
      <?php endif; ?>
      <p class="mt-6 whitespace-pre-line leading-relaxed text-warmgray"><?= e($p['description']) ?></p>
      <p class="mt-4 text-sm text-warmgray">One-of-one thrift piece. Delivery across Nigeria — fee shown at checkout. <a href="/size-guide" class="underline">Size guide</a> · <a href="/returns" class="underline">Returns</a></p>
      <div class="mt-8"><?= $cta ?></div>
      <?php if ($availability === 'available'): ?>
      <form data-price-watch="<?= e($p['id']) ?>" class="mt-4 flex flex-wrap items-center gap-2 text-sm"><label for="pw-<?= e($p['id']) ?>" class="text-warmgray">Waiting for a lower price?</label>
        <input id="pw-<?= e($p['id']) ?>" type="email" required placeholder="Your email" class="min-w-0 flex-1 rounded-full border border-sand bg-white px-3 py-1.5 text-sm focus:border-terracotta focus:outline-none">
        <button type="submit" class="rounded-full border border-charcoal px-3 py-1.5 text-xs font-semibold hover:bg-charcoal hover:text-cream">Alert me</button><p data-pw-msg class="hidden w-full text-xs"></p></form>
      <?php endif; ?>
    </div>
  </div>
  <?= complete_the_look($p['id']) ?>
  <?php if ($similar): ?>
  <div class="mt-16"><h2 class="font-display text-2xl font-semibold">You might also like</h2><?= product_grid($similar) ?></div>
  <?php endif; ?>
</div>
<?php
    render_store(ob_get_clean(), [
        'title' => $p['name'],
        'description' => mb_substr($p['description'], 0, 160) ?: $p['name'],
        'image' => $p['imageUrl'],
        'price' => $price,
    ]);
}

function page_cart(): void
{
    render_store('<div id="cart-page" class="mx-auto max-w-6xl px-4 py-10 sm:px-6"><p class="py-20 text-center text-warmgray">Loading your cart…</p></div>', ['title' => 'Cart']);
}

const NG_STATES = ['Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara'];

function page_checkout(): void
{
    $s = settings();
    $opts = '<option value="" disabled selected>Select state</option>';
    foreach (NG_STATES as $st) $opts .= '<option value="' . e($st) . '">' . e($st) . '</option>';
    $input = 'mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none focus:ring-1 focus:ring-terracotta';

    $methods = ['paystack' => ['Pay online with Paystack', 'Card, bank transfer or USSD (e.g. GTBank *737#, First Bank *894#, Access *901#, Zenith *966#, UBA *919#). Instant confirmation.']];
    if (trim($s['bankDetails']) !== '') $methods['transfer'] = ['Bank transfer', 'We show our account details after you order. Items held for 48 hours.'];
    if ($s['payOnDelivery']) $methods['pod'] = ['Pay on delivery', 'Pay the rider in cash or by transfer when your order arrives.'];
    $methodHtml = '';
    $first = true;
    foreach ($methods as $k => [$t, $d]) {
        $methodHtml .= '<label class="flex cursor-pointer gap-3 rounded-xl border border-sand bg-white p-4 has-[:checked]:border-terracotta has-[:checked]:ring-1 has-[:checked]:ring-terracotta">'
            . '<input type="radio" name="paymentMethod" value="' . $k . '"' . ($first ? ' checked' : '') . ' class="mt-1 accent-terracotta">'
            . '<span><span class="block text-sm font-semibold">' . e($t) . '</span><span class="block text-xs text-warmgray">' . e($d) . '</span></span></label>';
        $first = false;
    }
    $freeOver = (int) $s['freeDeliveryOver'];

    ob_start(); ?>
<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
  <div id="checkout-empty" class="hidden mx-auto max-w-lg py-20 text-center"><p class="text-warmgray">Your cart is empty.</p><a href="/shop" class="mt-4 inline-block text-terracotta hover:text-rust">Go to shop</a></div>
  <div id="checkout-main">
    <h1 class="font-display text-3xl font-semibold">Checkout</h1>
    <p class="mt-2 flex items-center gap-2 text-sm text-warmgray"><?= icon('lock') ?> Secure checkout</p>
    <form id="checkout-form" class="mt-10 grid gap-10 lg:grid-cols-2">
      <div class="space-y-5">
        <h2 class="font-display text-lg font-semibold">Delivery details</h2>
        <div><label for="name" class="block text-sm font-medium">Full name</label><input id="name" name="name" required class="<?= $input ?>" placeholder="Ada Okonkwo"></div>
        <div class="grid gap-5 sm:grid-cols-2">
          <div><label for="email" class="block text-sm font-medium">Email</label><input id="email" name="email" type="email" required class="<?= $input ?>" placeholder="you@email.com"></div>
          <div><label for="phone" class="block text-sm font-medium">Phone (WhatsApp)</label><input id="phone" name="phone" type="tel" required pattern="^[0-9+\-\s()]{10,20}$" title="Enter a valid Nigerian phone number, e.g. 0801 234 5678" class="<?= $input ?>" placeholder="08012345678"></div>
        </div>
        <div><label for="address" class="block text-sm font-medium">Street address</label><textarea id="address" name="address" required rows="2" class="<?= $input ?>" placeholder="House number, street, landmark"></textarea></div>
        <div class="grid gap-5 sm:grid-cols-2">
          <div><label for="city" class="block text-sm font-medium">City</label><input id="city" name="city" required class="<?= $input ?>" placeholder="Lagos"></div>
          <div><label for="state" class="block text-sm font-medium">State</label><select id="state" name="state" required class="<?= $input ?>"><?= $opts ?></select></div>
        </div>
        <p class="text-xs text-warmgray">Delivery: <?= naira((int) $s['deliveryLagos'] * 100) ?> within Lagos · <?= naira((int) $s['deliveryOther'] * 100) ?> other states<?= $freeOver > 0 ? ' · Free on orders over ' . naira($freeOver * 100) : '' ?></p>
        <?php if ($s['pickupEnabled']): ?>
        <div class="space-y-3"><p class="text-sm font-medium">Delivery or pickup?</p>
          <label class="flex cursor-pointer gap-3 rounded-xl border border-sand bg-white p-3 has-[:checked]:border-terracotta"><input type="radio" name="deliveryMethod" value="delivery" checked class="accent-terracotta"><span class="text-sm">Deliver to my address</span></label>
          <label class="flex cursor-pointer gap-3 rounded-xl border border-sand bg-white p-3 has-[:checked]:border-terracotta"><input type="radio" name="deliveryMethod" value="pickup" class="accent-terracotta"><span class="text-sm">Free pickup — <?= e($s['pickupAddress'] ?: $s['contactAddress']) ?></span></label>
        </div>
        <?php endif; ?>
        <div><label for="notes" class="block text-sm font-medium">Order note (optional)</label><textarea id="notes" name="notes" rows="2" maxlength="500" class="<?= $input ?>" placeholder="e.g. Call before delivery"></textarea></div>
        <?php if ((int) $s['giftWrapPrice'] > 0): ?>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="giftWrap" value="1" class="accent-terracotta"> 🎁 Gift wrap (+<?= naira((int) $s['giftWrapPrice'] * 100) ?>)</label>
        <?php endif; ?>
        <div><label for="tip" class="block text-sm font-medium">Support a small business (optional tip)</label>
          <select id="tip" name="tip" class="<?= $input ?>"><option value="0">No tip</option><option value="500">₦500</option><option value="1000">₦1,000</option><option value="2000">₦2,000</option><option value="5000">₦5,000</option></select></div>
        <label class="flex items-center gap-2 text-xs text-warmgray"><input type="checkbox" name="leaderboard" value="1"> Show my first name on the <a href="/top-buyers" class="underline">Top thrifters</a> board</label>
        <label class="flex items-center gap-2 text-xs text-warmgray"><input type="checkbox" id="remember-details" checked> Remember my details on this device</label>
        <h2 class="pt-4 font-display text-lg font-semibold">Payment</h2>
        <div class="space-y-3"><?= $methodHtml ?></div>
      </div>
      <div class="h-fit rounded-2xl bg-white p-6 ring-1 ring-sand/60">
        <h2 class="font-display text-lg font-semibold">Your order</h2>
        <ul id="checkout-items" class="mt-4 space-y-3 border-b border-sand pb-4"></ul>
        <div class="mt-4 flex gap-2">
          <label for="discount" class="sr-only">Discount code</label>
          <input id="discount" name="discountCode" placeholder="Discount code" autocomplete="off" class="min-w-0 flex-1 rounded-xl border border-sand px-4 py-2 text-sm uppercase focus:border-terracotta focus:outline-none">
          <button type="button" id="discount-apply" class="rounded-xl border border-charcoal px-4 py-2 text-sm font-medium hover:bg-charcoal hover:text-cream">Apply</button>
        </div>
        <p id="discount-msg" class="mt-2 hidden text-xs"></p>
        <dl class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-warmgray">Subtotal</dt><dd id="checkout-subtotal"></dd></div>
          <div class="flex justify-between"><dt class="text-warmgray">Delivery</dt><dd id="checkout-delivery" class="text-warmgray">Select your state</dd></div>
          <div id="checkout-discount-row" class="hidden justify-between text-olive"><dt>Discount</dt><dd id="checkout-discount"></dd></div>
          <div id="checkout-wrap-row" class="hidden justify-between"><dt class="text-warmgray">Gift wrap</dt><dd id="checkout-wrap"></dd></div>
          <div id="checkout-bundle-row" class="hidden justify-between text-olive"><dt>Bundle deal</dt><dd id="checkout-bundle"></dd></div>
          <div id="checkout-tip-row" class="hidden justify-between"><dt class="text-warmgray">Tip</dt><dd id="checkout-tip"></dd></div>
        </dl>
        <div class="mt-4 flex justify-between border-t border-sand pt-4 font-semibold"><span>Total</span><span id="checkout-total" class="text-terracotta"></span></div>
        <p id="checkout-gift" class="mt-3 hidden rounded-lg bg-olive/10 px-3 py-2 text-sm text-olive"></p>
        <?php if ((int) $s['bundleMin'] >= 2 && (int) $s['bundlePct'] > 0): ?><p class="mt-3 text-xs text-warmgray">Bundle deal: buy <?= (int) $s['bundleMin'] ?>+ pieces, get <?= (int) $s['bundlePct'] ?>% off.</p><?php endif; ?>
        <p id="checkout-unavailable" class="mt-4 hidden items-start gap-2 rounded-lg bg-rust/10 px-3 py-2 text-sm text-rust"><?= icon('alert', 'mt-0.5 h-4 w-4 shrink-0') ?> Some items in your cart just sold or are being purchased by someone else. Remove them to continue.</p>
        <p id="checkout-error" class="mt-4 hidden rounded-lg bg-rust/10 px-3 py-2 text-sm text-rust"></p>
        <p class="mt-4 text-xs text-warmgray">By ordering you agree to our <a href="/terms" class="underline">terms</a> and <a href="/returns" class="underline">returns policy</a>.</p>
        <button type="submit" id="checkout-submit" class="<?= btn_class('primary', true, 'mt-6') ?>" disabled>Checking availability…</button>
      </div>
    </form>
  </div>
</div>
<?php
    render_store(ob_get_clean(), ['title' => 'Checkout']);
}

function page_contact(): void
{
    $s = settings();
    $links = '';
    $pillCls = 'inline-flex items-center gap-2 rounded-full border border-sand px-4 py-2 text-sm hover:border-terracotta hover:text-terracotta';
    if ($s['whatsappNumber']) $links .= '<a href="' . e(whatsapp_link($s['whatsappNumber'])) . '" target="_blank" rel="noopener noreferrer" class="' . $pillCls . '">' . icon('message') . 'WhatsApp</a>';
    if ($s['instagramUrl']) $links .= '<a href="' . e($s['instagramUrl']) . '" target="_blank" rel="noopener noreferrer" class="' . $pillCls . '">' . icon('instagram') . 'Instagram</a>';
    if ($s['tiktokUrl']) $links .= '<a href="' . e($s['tiktokUrl']) . '" target="_blank" rel="noopener noreferrer" class="' . $pillCls . '">' . icon('tiktok') . 'TikTok</a>';

    ob_start(); ?>
<div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
  <div class="max-w-2xl">
    <h1 class="font-display text-4xl font-semibold sm:text-5xl">Contact us</h1>
    <p class="mt-4 text-lg leading-relaxed text-warmgray">Need help with an order, sizing, or a piece you saw on our socials? Our customer care team is here for you. You can also <a href="/track" class="text-terracotta underline">track your order</a>.</p>
  </div>
  <div class="mt-12 grid gap-8 lg:grid-cols-2">
    <div class="rounded-2xl bg-terracotta p-8 text-white sm:p-10">
      <p class="text-sm font-semibold uppercase tracking-wider text-white/80">Customer care hotline</p>
      <a href="<?= e(tel_link($s['customerCarePhone'])) ?>" class="mt-4 flex items-center gap-4 font-display text-3xl font-semibold hover:underline sm:text-4xl"><?= icon('phone', 'h-10 w-10 shrink-0') ?><?= e($s['customerCarePhone']) ?></a>
      <p class="mt-4 flex items-center gap-2 text-white/90"><?= icon('clock') ?><?= e($s['customerCareHours']) ?></p>
      <a href="<?= e(tel_link($s['customerCarePhone'])) ?>" class="mt-8 <?= btn_class('primary', false, 'bg-white !text-terracotta hover:bg-cream') ?>">Call now</a>
    </div>
    <div class="space-y-6">
      <div class="rounded-2xl bg-white p-6 ring-1 ring-sand/60"><div class="flex items-start gap-4"><?= icon('mail', 'h-6 w-6 shrink-0 text-terracotta') ?><div><p class="font-semibold">Email</p><a href="mailto:<?= e($s['contactEmail']) ?>" class="mt-1 text-warmgray hover:text-terracotta"><?= e($s['contactEmail']) ?></a></div></div></div>
      <div class="rounded-2xl bg-white p-6 ring-1 ring-sand/60"><div class="flex items-start gap-4"><?= icon('map-pin', 'h-6 w-6 shrink-0 text-terracotta') ?><div><p class="font-semibold">Location</p><p class="mt-1 text-warmgray"><?= e($s['contactAddress']) ?></p></div></div></div>
      <div class="rounded-2xl bg-white p-6 ring-1 ring-sand/60"><p class="font-semibold">Also reach us on</p><div class="mt-4 flex flex-wrap gap-3"><?= $links ?></div></div>
    </div>
  </div>
  <div class="mt-12 rounded-2xl bg-sand/50 p-8 text-center">
    <p class="text-warmgray">Prefer to shop first? Browse our latest thrift drops.</p>
    <a href="/shop" class="mt-4 <?= btn_class() ?>">Shop now</a>
  </div>
</div>
<?php
    render_store(ob_get_clean(), ['title' => 'Contact']);
}

function page_order_success(): void
{
    render_store('<div id="order-success" class="flex flex-col items-center py-20">'
        . icon('loader', 'h-10 w-10 animate-spin text-terracotta')
        . '<p class="mt-4 text-warmgray">Confirming your order…</p></div>', ['title' => 'Order']);
}

function page_track(): void
{
    $input = 'mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none';
    $body = '<div class="mx-auto max-w-xl px-4 py-12 sm:px-6">'
        . '<h1 class="font-display text-3xl font-semibold">Track your order</h1>'
        . '<p class="mt-2 text-warmgray">Enter the order reference from your confirmation and the email or phone number you used.</p>'
        . '<form id="track-form" class="mt-8 space-y-4">'
        . '<div><label for="ref" class="text-sm font-medium">Order reference</label><input id="ref" name="ref" required value="' . e((string) ($_GET['ref'] ?? '')) . '" placeholder="TK_…" class="' . $input . '"></div>'
        . '<div><label for="contact" class="text-sm font-medium">Email or phone</label><input id="contact" name="contact" required class="' . $input . '"></div>'
        . '<button type="submit" class="' . btn_class('primary', true) . '">' . icon('search') . ' Track order</button></form>'
        . '<div id="track-result" class="mt-8"></div></div>';
    render_store($body, ['title' => 'Track order']);
}

function page_wishlist(): void
{
    render_store('<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6"><h1 class="font-display text-3xl font-semibold">Your wishlist</h1>'
        . '<p class="mt-2 text-warmgray">Pieces you&apos;ve saved on this device. One-of-ones go fast!</p>'
        . '<div id="wishlist-page" class="mt-10"><p class="text-warmgray">Loading…</p></div></div>', ['title' => 'Wishlist']);
}

function page_unsubscribe(): void
{
    $token = (string) ($_GET['t'] ?? '');
    $done = false;
    if (preg_match('/^[a-f0-9]{32}$/', $token)) {
        $st = db()->prepare('SELECT email FROM Subscriber WHERE token = ?');
        $st->execute([$token]);
        if ($email = $st->fetchColumn()) {
            db()->prepare('DELETE FROM Subscriber WHERE email = ?')->execute([$email]);
            $done = true;
        }
    }
    render_store('<div class="mx-auto max-w-lg px-4 py-24 text-center"><h1 class="font-display text-3xl font-semibold">'
        . ($done ? 'You&apos;re unsubscribed' : 'Link not valid') . '</h1><p class="mt-2 text-warmgray">'
        . ($done ? 'You won&apos;t get any more new-drop emails from us.' : 'This unsubscribe link has expired or was already used.')
        . '</p><a href="/shop" class="mt-8 ' . btn_class() . '">Back to shop</a></div>', ['title' => 'Unsubscribe']);
}

// ---------- SEO + marketing feeds ----------

function sitemap_xml(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $base = app_url();
    $urls = [['/', null], ['/shop', null], ['/contact', null]];
    foreach (CATEGORIES as $id => $_) if ($id !== 'all') $urls[] = ['/shop?category=' . $id, null];
    foreach (fetch_products(published_sql()) as $p) $urls[] = ['/product/' . rawurlencode($p['slug']), (int) ($p['soldAt'] ?: $p['createdAt'])];
    foreach (['/looks', '/blog', '/returns', '/size-guide', '/sell', '/quiz'] as $u) $urls[] = [$u, null];
    foreach (db()->query('SELECT slug, createdAt FROM Post WHERE published = 1')->fetchAll() as $po) $urls[] = ['/blog/' . rawurlencode($po['slug']), (int) $po['createdAt']];
    foreach (db()->query('SELECT slug, createdAt FROM Outfit')->fetchAll() as $ou) $urls[] = ['/looks/' . rawurlencode($ou['slug']), (int) $ou['createdAt']];
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as [$u, $ts]) {
        echo '<url><loc>' . e($base . $u) . '</loc>' . ($ts ? '<lastmod>' . date('Y-m-d', intdiv($ts, 1000)) . '</lastmod>' : '') . '</url>';
    }
    echo '</urlset>';
}

function robots_txt(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: /api/\nDisallow: /cart\nDisallow: /checkout\nDisallow: /order/\nDisallow: /admin\n\nSitemap: " . app_url() . "/sitemap.xml\n";
}

/** Product feed for Facebook / Instagram Shopping (Commerce Manager → Data source → scheduled feed URL). */
function product_feed_csv(): void
{
    $s = settings();
    header('Content-Type: text/csv; charset=utf-8');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'title', 'description', 'availability', 'condition', 'price', 'sale_price', 'link', 'image_link', 'additional_image_link', 'brand', 'color', 'size', 'google_product_category']);
    foreach (fetch_products(published_sql()) as $p) {
        $imgs = array_map('absolute_url', product_images($p));
        fputcsv($out, [
            $p['id'], mb_substr($p['name'], 0, 150), mb_substr($p['description'] ?: $p['name'], 0, 5000),
            product_availability($p) === 'available' ? 'in stock' : 'out of stock',
            $p['condition'] === 'New with tags' ? 'new' : 'used',
            number_format((int) $p['originalPrice'] / 100, 2, '.', '') . ' NGN',
            effective_price($p) < (int) $p['originalPrice'] ? number_format(effective_price($p) / 100, 2, '.', '') . ' NGN' : '',
            app_url() . '/product/' . rawurlencode($p['slug']), $imgs[0] ?? '', implode(',', array_slice($imgs, 1, 10)),
            $p['brand'] ?: $s['storeName'], $p['color'], $p['size'], 'Apparel & Accessories > Clothing',
        ]);
    }
    fclose($out);
}

function page_policy(string $key, string $title): void
{
    $text = policy_text($key);
    $html = '';
    foreach (preg_split('/\n{2,}/', $text) as $block) {
        $lines = explode("\n", trim($block));
        if (count($lines) > 1 && str_contains($lines[0], '|')) {
            $rows = '';
            foreach ($lines as $i => $line) {
                $cells = array_map('trim', explode('|', $line));
                $tag = $i === 0 ? 'th' : 'td';
                $rows .= '<tr>' . implode('', array_map(fn($c) => "<$tag class=\"border border-sand px-3 py-2 text-left\">" . e($c) . "</$tag>", $cells)) . '</tr>';
            }
            $html .= '<div class="overflow-x-auto"><table class="mt-4 w-full border-collapse bg-white text-sm">' . $rows . '</table></div>';
        } elseif (str_starts_with(ltrim($lines[0]), '•')) {
            $html .= '<ul class="mt-4 list-disc space-y-2 pl-5">' . implode('', array_map(fn($l) => '<li>' . e(ltrim($l, "• ")) . '</li>', $lines)) . '</ul>';
        } else {
            $html .= '<p class="mt-4 whitespace-pre-line">' . e(trim($block)) . '</p>';
        }
    }
    render_store('<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6"><h1 class="font-display text-3xl font-semibold sm:text-4xl">' . e($title) . '</h1>'
        . '<div class="mt-4 leading-relaxed text-charcoal">' . $html . '</div>'
        . '<p class="mt-10 text-sm text-warmgray">Questions? <a href="/contact" class="text-terracotta underline">Contact us</a>.</p></div>', ['title' => $title]);
}

function page_sell(): void
{
    $in = 'mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none';
    $body = '<div class="mx-auto max-w-xl px-4 py-12 sm:px-6">'
        . '<h1 class="font-display text-3xl font-semibold sm:text-4xl">Sell to us</h1>'
        . '<p class="mt-3 text-warmgray">Got quality pre-loved pieces? Tell us about them — we buy outright or swap for store credit. Clean, good-condition items only.</p>'
        . '<form id="sell-form" class="mt-8 space-y-4" enctype="multipart/form-data">'
        . '<div class="grid gap-4 sm:grid-cols-2"><div><label class="text-sm font-medium" for="s-name">Name</label><input id="s-name" name="name" required maxlength="80" class="' . $in . '"></div>'
        . '<div><label class="text-sm font-medium" for="s-phone">WhatsApp number</label><input id="s-phone" name="phone" required maxlength="30" class="' . $in . '"></div></div>'
        . '<div><label class="text-sm font-medium" for="s-email">Email (optional)</label><input id="s-email" name="email" type="email" class="' . $in . '"></div>'
        . '<div><label class="text-sm font-medium" for="s-desc">What are you selling?</label><textarea id="s-desc" name="description" required rows="4" maxlength="2000" placeholder="e.g. 5 pieces: Levi\'s 501 jeans W30, Zara blazer M…" class="' . $in . '"></textarea></div>'
        . '<div><label class="text-sm font-medium" for="s-price">Asking price for all (₦, optional)</label><input id="s-price" name="askingPrice" type="number" min="0" class="' . $in . '"></div>'
        . '<div><label class="text-sm font-medium" for="s-photos">Photos (up to 4)</label><input id="s-photos" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm"></div>'
        . '<p data-sell-msg class="hidden text-sm"></p>'
        . '<button type="submit" class="' . btn_class('primary', true) . '">Send</button></form></div>';
    render_store($body, ['title' => 'Sell to us']);
}
