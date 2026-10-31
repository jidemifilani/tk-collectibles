<?php
/** Batch 2 pages: looks/outfits, blog, style quiz, top buyers, wholesale and sourcing requests, plus their admin screens. */

function outfit_products(array $o): array
{
    $ids = json_decode($o['productIds'], true) ?: [];
    if (!$ids) return [];
    $rows = fetch_products('id IN (' . rtrim(str_repeat('?,', count($ids)), ',') . ') AND ' . published_sql(), $ids, '');
    $by = array_column($rows, null, 'id');
    return array_values(array_filter(array_map(fn($id) => $by[$id] ?? null, $ids)));
}

function page_looks(): void
{
    $cards = '';
    foreach (db()->query('SELECT * FROM Outfit ORDER BY createdAt DESC')->fetchAll() as $o) {
        $items = outfit_products($o);
        $img = $o['imageUrl'] ?: ($items[0]['imageUrl'] ?? '');
        $avail = count(array_filter($items, fn($p) => product_availability($p) === 'available'));
        $cards .= '<a href="/looks/' . e($o['slug']) . '" class="group overflow-hidden rounded-2xl bg-white ring-1 ring-sand/60 hover:shadow-md">'
            . '<div class="relative aspect-[4/5] bg-sand"><img src="' . e($img) . '" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"></div>'
            . '<div class="p-4"><h2 class="font-display text-xl">' . e($o['title']) . '</h2><p class="mt-1 text-sm text-warmgray">' . count($items) . ' pieces · ' . $avail . ' available</p></div></a>';
    }
    render_store('<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6"><h1 class="font-display text-3xl font-semibold sm:text-4xl">Styled by us</h1>'
        . '<p class="mt-2 text-warmgray">Complete outfits from one-of-one pieces. Shop the whole look or just your favourite part.</p>'
        . ($cards ? '<div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">' . $cards . '</div>' : '<p class="mt-10 text-warmgray">New looks coming soon.</p>') . '</div>', ['title' => 'Looks']);
}

function page_look(string $slug): void
{
    $st = db()->prepare('SELECT * FROM Outfit WHERE slug = ?');
    $st->execute([$slug]);
    $o = $st->fetch();
    if (!$o) { not_found_page(); return; }
    $items = outfit_products($o);
    $cart = [];
    $total = 0;
    foreach ($items as $p) {
        if (product_availability($p) !== 'available') continue;
        $price = effective_price($p);
        $total += $price;
        $cart[] = ['productId' => $p['id'], 'name' => $p['name'], 'slug' => $p['slug'], 'salePrice' => $price, 'originalPrice' => (int) $p['originalPrice'], 'imageUrl' => $p['imageUrl'], 'size' => $p['size']];
    }
    render_store('<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6"><a href="/looks" class="text-sm text-warmgray hover:text-charcoal">← All looks</a>'
        . '<div class="mt-6 grid gap-10 lg:grid-cols-2">'
        . ($o['imageUrl'] ? '<img src="' . e($o['imageUrl']) . '" alt="' . e($o['title']) . '" class="w-full rounded-2xl object-cover">' : '')
        . '<div><h1 class="font-display text-3xl font-semibold sm:text-4xl">' . e($o['title']) . '</h1>'
        . ($o['description'] ? '<p class="mt-3 whitespace-pre-line text-warmgray">' . e($o['description']) . '</p>' : '')
        . ($cart ? '<p class="mt-6 text-lg font-semibold text-terracotta">' . count($cart) . ' available pieces · ' . naira($total) . '</p>'
            . '<button type="button" data-add-look="' . e(json_encode($cart, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '" class="mt-4 ' . btn_class() . '">Add the whole look to cart</button>'
            : '<p class="mt-6 text-warmgray">Every piece in this look has sold.</p>')
        . '</div></div>' . product_grid($items) . '</div>', ['title' => $o['title'], 'image' => $o['imageUrl'] ?: ($items[0]['imageUrl'] ?? null)]);
}

/** "Complete the look" block for a product page. */
function complete_the_look(string $productId): string
{
    $st = db()->prepare("SELECT * FROM Outfit WHERE productIds LIKE ? ORDER BY createdAt DESC LIMIT 1");
    $st->execute(['%"' . $productId . '"%']);
    $o = $st->fetch();
    if (!$o) return '';
    $others = array_values(array_filter(outfit_products($o), fn($p) => $p['id'] !== $productId));
    if (!$others) return '';
    return '<div class="mt-16"><div class="flex items-end justify-between gap-4"><h2 class="font-display text-2xl font-semibold">Complete the look</h2>'
        . '<a href="/looks/' . e($o['slug']) . '" class="text-sm font-medium text-terracotta">See “' . e($o['title']) . '” →</a></div>' . product_grid($others) . '</div>';
}

function render_post_body(string $body): string
{
    $html = '';
    foreach (preg_split('/\n{2,}/', trim($body)) as $block) {
        $block = trim($block);
        if (str_starts_with($block, '## ')) $html .= '<h2 class="mt-8 font-display text-2xl font-semibold">' . e(substr($block, 3)) . '</h2>';
        else $html .= '<p class="mt-4 whitespace-pre-line leading-relaxed">' . e($block) . '</p>';
    }
    // Turn /product/... and /shop... paths into links (they're already escaped above).
    return preg_replace('#(?<![\w/"])(/(?:product|shop|looks)[\w/?=&;-]*)#', '<a href="$1" class="text-terracotta underline">$1</a>', $html);
}

function page_blog(): void
{
    $cards = '';
    foreach (db()->query('SELECT * FROM Post WHERE published = 1 ORDER BY createdAt DESC LIMIT 50')->fetchAll() as $p) {
        $cards .= '<a href="/blog/' . e($p['slug']) . '" class="flex flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-sand/60 hover:shadow-md">'
            . ($p['imageUrl'] ? '<img src="' . e($p['imageUrl']) . '" alt="" loading="lazy" class="aspect-video w-full object-cover">' : '')
            . '<div class="p-5"><p class="text-xs text-warmgray">' . e(date('j M Y', intdiv((int) $p['createdAt'], 1000))) . '</p><h2 class="mt-1 font-display text-xl">' . e($p['title']) . '</h2>'
            . '<p class="mt-2 line-clamp-3 text-sm text-warmgray">' . e(mb_substr(preg_replace('/\s+/', ' ', $p['body']), 0, 200)) . '…</p></div></a>';
    }
    render_store('<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6"><h1 class="font-display text-3xl font-semibold sm:text-4xl">Style notes</h1><p class="mt-2 text-warmgray">Styling tips, care guides and thrift stories.</p>'
        . ($cards ? '<div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">' . $cards . '</div>' : '<p class="mt-10 text-warmgray">First post coming soon.</p>') . '</div>', ['title' => 'Blog']);
}

function page_post(string $slug): void
{
    $st = db()->prepare('SELECT * FROM Post WHERE slug = ?' . (is_admin() ? '' : ' AND published = 1'));
    $st->execute([$slug]);
    $p = $st->fetch();
    if (!$p) { not_found_page(); return; }
    render_store('<article class="mx-auto max-w-3xl px-4 py-10 sm:px-6"><a href="/blog" class="text-sm text-warmgray hover:text-charcoal">← Style notes</a>'
        . '<h1 class="mt-4 font-display text-3xl font-semibold sm:text-4xl">' . e($p['title']) . '</h1><p class="mt-2 text-sm text-warmgray">' . e(date('j F Y', intdiv((int) $p['createdAt'], 1000))) . '</p>'
        . ($p['imageUrl'] ? '<img src="' . e($p['imageUrl']) . '" alt="" class="mt-6 w-full rounded-2xl object-cover">' : '')
        . '<div class="text-charcoal">' . render_post_body($p['body']) . '</div>'
        . '<div class="mt-12 rounded-2xl bg-sand/50 p-6 text-center"><p class="font-semibold">Love this style?</p><a href="/shop" class="mt-3 ' . btn_class() . '">Shop the latest pieces</a></div></article>',
        ['title' => $p['title'], 'description' => mb_substr(preg_replace('/\s+/', ' ', $p['body']), 0, 160), 'image' => $p['imageUrl'] ?: null]);
}

function page_quiz(): void
{
    $sizes = db()->query("SELECT DISTINCT size FROM Product WHERE inStock = 1 AND " . published_sql() . " ORDER BY size")->fetchAll(PDO::FETCH_COLUMN);
    $opt = fn(string $name, string $value, string $label) => '<label class="cursor-pointer rounded-xl border border-sand bg-white px-4 py-3 text-sm has-[:checked]:border-terracotta has-[:checked]:ring-1 has-[:checked]:ring-terracotta"><input type="radio" name="' . $name . '" value="' . e($value) . '" class="sr-only">' . e($label) . '</label>';
    $q = function (string $title, string $html) { return '<fieldset class="mt-8"><legend class="font-display text-xl font-semibold">' . e($title) . '</legend><div class="mt-3 flex flex-wrap gap-2">' . $html . '</div></fieldset>'; };
    $catHtml = $opt('category', '', 'Surprise me');
    foreach (CATEGORIES as $k => $l) if ($k !== 'all') $catHtml .= $opt('category', $k, $l);
    $styleHtml = $opt('style', '', 'A bit of everything') . $opt('style', 'vintage', 'Vintage & retro') . $opt('style', 'rare', 'Rare statement pieces') . $opt('style', 'era:Modern', 'Clean & modern') . $opt('style', 'era:90s', '90s / Y2K energy');
    $sizeHtml = $opt('size', '', 'Show all sizes');
    foreach ($sizes as $sz) $sizeHtml .= $opt('size', $sz, $sz);
    $budgetHtml = $opt('max', '', 'No limit') . $opt('max', '10000', 'Under ₦10k') . $opt('max', '20000', 'Under ₦20k') . $opt('max', '40000', 'Under ₦40k');
    render_store('<div class="mx-auto max-w-2xl px-4 py-10 sm:px-6"><h1 class="font-display text-3xl font-semibold sm:text-4xl">Find your style</h1><p class="mt-2 text-warmgray">Four quick questions and we\'ll pick pieces for you.</p>'
        . '<form id="style-quiz">' . $q('What are you shopping for?', $catHtml) . $q('Which vibe is you?', $styleHtml) . $q('Your size', $sizeHtml) . $q('Budget per piece', $budgetHtml)
        . '<button type="submit" class="mt-10 ' . btn_class('primary', true) . '">Show my picks</button></form></div>', ['title' => 'Style quiz']);
}

function page_top_buyers(): void
{
    $rows = db()->query("SELECT customerName, customerEmail, COUNT(DISTINCT o.id) orders, SUM(oi.quantity) pieces FROM \"Order\" o JOIN OrderItem oi ON oi.orderId = o.id WHERE o.status = 'paid' AND o.leaderboard = 1 GROUP BY lower(customerEmail) ORDER BY pieces DESC LIMIT 20")->fetchAll();
    $list = '';
    foreach ($rows as $i => $r) {
        $first = preg_split('/\s+/', trim($r['customerName']))[0] ?? 'Shopper';
        $list .= '<li class="flex items-center justify-between rounded-xl bg-white px-5 py-3 ring-1 ring-sand/60"><span class="flex items-center gap-3"><span class="w-6 font-display text-xl text-terracotta">' . ($i + 1) . '</span>' . e(mb_substr($first, 0, 20)) . '</span><span class="text-sm text-warmgray">' . (int) $r['pieces'] . ' pieces</span></li>';
    }
    render_store('<div class="mx-auto max-w-xl px-4 py-10 sm:px-6"><h1 class="font-display text-3xl font-semibold sm:text-4xl">Top thrifters</h1>'
        . '<p class="mt-2 text-warmgray">Our most loyal shoppers (first names only, and only those who opted in at checkout). Thank you! 💛</p>'
        . ($list ? '<ol class="mt-8 space-y-2">' . $list . '</ol>' : '<p class="mt-8 text-warmgray">Be the first on the board — tick “show my first name” at checkout.</p>') . '</div>', ['title' => 'Top thrifters']);
}

/** Shared form for wholesale enquiries and "find me a piece" requests (stored with Sell requests). */
function page_request_form(string $kind): void
{
    $copy = [
        'wholesale' => ['Wholesale & bales', 'Buying in bulk for your own store? Tell us what you need — we sell curated bundles and bale picks at trade prices.', 'What do you want? (categories, quantities, budget)', 'Budget (₦, optional)'],
        'request' => ['Find me a piece', 'Looking for something specific — a brand, a style, a size? Tell us and we\'ll message you when we find it.', 'Describe the piece (brand, style, size, colour)', 'Max price (₦, optional)'],
    ][$kind];
    $in = 'mt-1 w-full rounded-xl border border-sand bg-white px-4 py-3 text-sm focus:border-terracotta focus:outline-none';
    render_store('<div class="mx-auto max-w-xl px-4 py-12 sm:px-6"><h1 class="font-display text-3xl font-semibold sm:text-4xl">' . e($copy[0]) . '</h1><p class="mt-3 text-warmgray">' . e($copy[1]) . '</p>'
        . '<form id="sell-form" class="mt-8 space-y-4"><input type="hidden" name="kind" value="' . $kind . '">'
        . '<div class="grid gap-4 sm:grid-cols-2"><div><label class="text-sm font-medium" for="s-name">Name</label><input id="s-name" name="name" required maxlength="80" class="' . $in . '"></div>'
        . '<div><label class="text-sm font-medium" for="s-phone">WhatsApp number</label><input id="s-phone" name="phone" required maxlength="30" class="' . $in . '"></div></div>'
        . '<div><label class="text-sm font-medium" for="s-email">Email (optional)</label><input id="s-email" name="email" type="email" class="' . $in . '"></div>'
        . '<div><label class="text-sm font-medium" for="s-desc">' . e($copy[2]) . '</label><textarea id="s-desc" name="description" required rows="4" maxlength="2000" class="' . $in . '"></textarea></div>'
        . '<div><label class="text-sm font-medium" for="s-price">' . e($copy[3]) . '</label><input id="s-price" name="askingPrice" type="number" min="0" class="' . $in . '"></div>'
        . '<p data-sell-msg class="hidden text-sm"></p><button type="submit" class="' . btn_class('primary', true) . '">Send</button></form></div>', ['title' => $copy[0]]);
}

// ---------- admin ----------

function admin_looks_page(): void
{
    admin_guard_page();
    $opts = '';
    foreach (fetch_products(published_sql(), [], 'ORDER BY inStock DESC, createdAt DESC LIMIT 300') as $p) {
        $opts .= '<label class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-2 text-xs"><input type="checkbox" name="pid" value="' . e($p['id']) . '"><img src="' . e($p['imageUrl']) . '" alt="" class="h-10 w-8 rounded object-cover"><span>' . e($p['name']) . ($p['inStock'] ? '' : ' (sold)') . '</span></label>';
    }
    $list = '';
    foreach (db()->query('SELECT * FROM Outfit ORDER BY createdAt DESC')->fetchAll() as $o) {
        $list .= '<li class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm"><a href="/looks/' . e($o['slug']) . '" target="_blank" class="font-medium hover:underline">' . e($o['title']) . '</a>'
            . '<span class="text-gray-500">' . count(json_decode($o['productIds'], true) ?: []) . ' pieces</span><button type="button" data-delete-outfit="' . e($o['id']) . '" class="text-red-600 hover:underline">Delete</button></li>';
    }
    render_admin('<h1 class="text-2xl font-semibold">Looks &amp; outfits</h1><p class="mt-1 text-gray-500">Group pieces into outfits. They appear on the “Looks” page and as “Complete the look” on each product.</p>'
        . '<form id="outfit-form" class="mt-8 max-w-4xl space-y-4 rounded-xl border border-gray-200 bg-white p-5">'
        . '<div class="grid gap-4 sm:grid-cols-2"><div><label class="text-sm font-medium">Title</label><input name="title" required maxlength="150" placeholder="Weekend brunch" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="text-sm font-medium">Description (optional)</label><input name="description" maxlength="1000" class="' . ADMIN_INPUT . '"></div></div>'
        . image_field('imageUrl', 'Look photo (optional — styled flat-lay or model)', '', 'product', false)
        . '<p class="text-sm font-medium">Pieces (2–8)</p><div class="grid max-h-96 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">' . $opts . '</div>'
        . '<p data-error class="hidden text-sm text-red-600"></p><button type="submit" class="' . btn_class() . '">Save look</button></form>'
        . '<ul class="mt-8 max-w-4xl space-y-2">' . ($list ?: '<li class="text-gray-500">No looks yet.</li>') . '</ul>', 'Looks');
}

function admin_blog_page(): void
{
    admin_guard_page();
    $list = '';
    foreach (db()->query('SELECT * FROM Post ORDER BY createdAt DESC')->fetchAll() as $p) {
        $list .= '<li class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm"><span><a href="/admin/blog/' . e($p['id']) . '" class="font-medium text-blue-600 hover:underline">' . e($p['title']) . '</a>'
            . ($p['published'] ? '' : ' <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs">Draft</span>') . '</span><span class="text-gray-500">' . e(date('j M Y', intdiv((int) $p['createdAt'], 1000))) . '</span>'
            . '<button type="button" data-delete-post="' . e($p['id']) . '" class="text-red-600 hover:underline">Delete</button></li>';
    }
    render_admin('<div class="flex items-center justify-between"><h1 class="text-2xl font-semibold">Blog</h1><a href="/admin/blog/new" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">+ New post</a></div>'
        . '<p class="mt-1 text-gray-500">Style tips and stories help people find you on Google.</p><ul class="mt-8 max-w-3xl space-y-2">' . ($list ?: '<li class="text-gray-500">No posts yet.</li>') . '</ul>', 'Blog');
}

function admin_post_editor(?string $id): void
{
    admin_guard_page();
    $p = null;
    if ($id) {
        $st = db()->prepare('SELECT * FROM Post WHERE id = ?');
        $st->execute([$id]);
        $p = $st->fetch() ?: null;
        if (!$p) { http_response_code(404); render_admin('<p>Post not found.</p>', 'Not found'); return; }
    }
    render_admin('<a href="/admin/blog" class="text-sm text-gray-500">← Blog</a><h1 class="mt-2 text-2xl font-semibold">' . ($p ? 'Edit post' : 'New post') . '</h1>'
        . '<form id="post-form" data-id="' . e($p['id'] ?? '') . '" class="mt-8 max-w-3xl space-y-4">'
        . '<div><label class="text-sm font-medium">Title</label><input name="title" required maxlength="200" value="' . e($p['title'] ?? '') . '" class="' . ADMIN_INPUT . '"></div>'
        . image_field('imageUrl', 'Cover image (optional)', $p['imageUrl'] ?? '', 'hero', false)
        . '<div><label class="text-sm font-medium">Post</label><p class="text-xs text-gray-500">Blank line = new paragraph. Start a line with “## ” for a heading. Paste /product/… links to link items.</p>'
        . '<textarea name="body" required rows="16" maxlength="30000" class="' . ADMIN_INPUT . ' font-mono">' . e($p['body'] ?? '') . '</textarea></div>'
        . '<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="published"' . (($p['published'] ?? 1) ? ' checked' : '') . '> Published</label>'
        . '<p data-error class="hidden text-sm text-red-600"></p><button type="submit" class="' . btn_class() . '">Save post</button></form>', 'Blog post');
}

function admin_staff_section(): string
{
    if (admin_role() !== 'owner') return '';
    $rows = '';
    foreach (db()->query('SELECT id, name, createdAt FROM Staff ORDER BY createdAt')->fetchAll() as $s) {
        $rows .= '<li class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-sm"><span>' . e($s['name']) . '</span><button type="button" data-delete-staff="' . e($s['id']) . '" class="text-red-600 hover:underline">Remove</button></li>';
    }
    return '<section class="mt-6 max-w-2xl rounded-xl border border-gray-200 bg-white p-6"><h2 class="font-semibold">Staff accounts</h2>'
        . '<p class="mt-1 text-sm text-gray-500">Helpers sign in on the same login page with their own password. They can only see and update orders and print packing slips — no products, prices, settings or reports.</p>'
        . '<ul class="mt-4 space-y-2">' . ($rows ?: '<li class="text-sm text-gray-400">No staff yet.</li>') . '</ul>'
        . '<form id="staff-form" class="mt-4 flex flex-wrap items-end gap-2"><label class="text-sm">Name<input name="name" required maxlength="60" class="' . ADMIN_INPUT . '"></label>'
        . '<label class="text-sm">Password (10+ characters)<input name="password" type="password" required minlength="10" autocomplete="new-password" class="' . ADMIN_INPUT . '"></label>'
        . '<button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">Add staff</button><p data-error class="hidden w-full text-sm text-red-600"></p></form></section>';
}
