<?php
/** Admin pages for bulk upload, discount codes, reviews, activity log and security. */

function admin_bulk_page(): void
{
    admin_guard_page();
    $catOpts = '';
    foreach (CATEGORIES as $id => $label) if ($id !== 'all') $catOpts .= '<option value="' . $id . '">' . $label . '</option>';
    $condOpts = '';
    foreach (CONDITIONS as $c) $condOpts .= '<option' . ($c === 'Good' ? ' selected' : '') . '>' . e($c) . '</option>';

    $content = '<a href="/admin/products" class="text-sm text-gray-500 hover:text-gray-800">← Back to products</a>'
        . '<h1 class="mt-2 text-2xl font-semibold">Bulk upload</h1>'
        . '<p class="mt-1 max-w-2xl text-sm text-gray-500">Pick several photos at once — each photo becomes one item. Set shared details here, then fill in each item&apos;s name and prices below. Subscribers get one combined “new drop” alert.</p>'
        . '<div id="bulk" class="mt-8 max-w-5xl space-y-6">'
        . '<div class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 sm:grid-cols-4">'
        . '<div><label class="text-sm font-medium">Category</label><select data-bulk-default="category" class="' . ADMIN_INPUT . '">' . $catOpts . '</select></div>'
        . '<div><label class="text-sm font-medium">Condition</label><select data-bulk-default="condition" class="' . ADMIN_INPUT . '">' . $condOpts . '</select></div>'
        . '<div><label class="text-sm font-medium">Size</label><input data-bulk-default="size" value="M" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="text-sm font-medium">Drop time (optional)</label><input data-bulk-default="publishAt" type="datetime-local" class="' . ADMIN_INPUT . '"></div>'
        . '<label class="flex items-center gap-2 text-sm sm:col-span-4"><input type="checkbox" data-bulk-default="featured"> Mark all as featured drop</label></div>'
        . '<label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">' . icon('upload')
        . '<span data-bulk-label>Choose photos (up to 30)</span><input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" data-bulk-file></label>'
        . '<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white"><table class="w-full min-w-[760px] text-left text-sm">'
        . '<thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">Photo</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Actual ₦</th><th class="px-3 py-2">Discount ₦</th><th class="px-3 py-2">Size</th><th class="px-3 py-2">Brand</th><th class="px-3 py-2"></th></tr></thead>'
        . '<tbody data-bulk-rows><tr data-empty><td colspan="7" class="px-3 py-8 text-center text-gray-400">No photos yet.</td></tr></tbody></table></div>'
        . '<p data-bulk-error class="hidden text-sm text-red-600"></p>'
        . '<button type="button" data-bulk-save class="' . btn_class() . '" disabled>Create items</button></div>';
    render_admin($content, 'Bulk upload');
}

function admin_discounts_page(): void
{
    admin_guard_page();
    $rows = '';
    foreach (db()->query('SELECT * FROM DiscountCode ORDER BY createdAt DESC')->fetchAll() as $d) {
        $value = $d['kind'] === 'percent' ? (int) $d['value'] . '% off' : naira((int) $d['value'] * 100) . ' off';
        $expired = $d['expiresAt'] && (int) $d['expiresAt'] < now_ms();
        $usedUp = $d['maxUses'] > 0 && $d['uses'] >= $d['maxUses'];
        $rows .= '<tr><td class="px-4 py-3 font-mono font-semibold">' . e($d['code']) . '</td><td class="px-4 py-3">' . $value . '</td>'
            . '<td class="px-4 py-3">' . ((int) $d['minOrder'] ? naira((int) $d['minOrder'] * 100) : '—') . '</td>'
            . '<td class="px-4 py-3">' . (int) $d['uses'] . ((int) $d['maxUses'] ? ' / ' . (int) $d['maxUses'] : '') . '</td>'
            . '<td class="px-4 py-3">' . ($d['expiresAt'] ? e(date('j M Y', intdiv((int) $d['expiresAt'], 1000))) : 'Never') . '</td>'
            . '<td class="px-4 py-3">' . ($expired || $usedUp ? '<span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">' . ($expired ? 'Expired' : 'Used up') . '</span>' : '<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Active</span>') . '</td>'
            . '<td class="px-4 py-3"><button type="button" data-delete-discount="' . e($d['code']) . '" class="text-sm text-red-600 hover:underline">Delete</button></td></tr>';
    }
    $content = '<h1 class="text-2xl font-semibold">Discount codes</h1><p class="mt-1 text-gray-500">Shoppers enter these at checkout. Share them on Instagram, TikTok or WhatsApp.</p>'
        . '<form id="discount-form" class="mt-8 grid max-w-4xl gap-4 rounded-xl border border-gray-200 bg-white p-5 sm:grid-cols-3">'
        . '<div><label class="text-sm font-medium">Code</label><input name="code" required pattern="[A-Za-z0-9_\-]{3,30}" placeholder="FRIDAY10" class="' . ADMIN_INPUT . ' uppercase"></div>'
        . '<div><label class="text-sm font-medium">Type</label><select name="kind" class="' . ADMIN_INPUT . '"><option value="percent">Percent off</option><option value="fixed">Naira off</option></select></div>'
        . '<div><label class="text-sm font-medium">Value</label><input name="value" type="number" min="1" required placeholder="10" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="text-sm font-medium">Minimum order ₦ (optional)</label><input name="minOrder" type="number" min="0" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="text-sm font-medium">Max uses (0 = unlimited)</label><input name="maxUses" type="number" min="0" value="0" class="' . ADMIN_INPUT . '"></div>'
        . '<div><label class="text-sm font-medium">Expires (optional)</label><input name="expiresAt" type="date" class="' . ADMIN_INPUT . '"></div>'
        . '<div class="sm:col-span-3"><p data-error class="mb-2 hidden text-sm text-red-600"></p><button type="submit" class="' . btn_class() . '">Save code</button></div></form>'
        . '<div class="mt-8 overflow-x-auto rounded-xl border border-gray-200 bg-white"><table class="w-full min-w-[640px] text-left text-sm">'
        . '<thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Min order</th><th class="px-4 py-3">Used</th><th class="px-4 py-3">Expires</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>'
        . '<tbody class="divide-y divide-gray-100">' . ($rows ?: '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No codes yet.</td></tr>') . '</tbody></table></div>';
    render_admin($content, 'Discount codes');
}

function admin_reviews_page(): void
{
    admin_guard_page();
    $cards = '';
    foreach (db()->query('SELECT * FROM Review ORDER BY approved ASC, createdAt DESC LIMIT 100')->fetchAll() as $r) {
        $cards .= '<div class="rounded-xl border border-gray-200 bg-white p-5" data-review="' . e($r['id']) . '">'
            . '<div class="flex flex-wrap items-center justify-between gap-2"><p class="font-semibold">' . e($r['name']) . ' <span class="text-amber-500">' . str_repeat('★', (int) $r['rating']) . '</span></p>'
            . ($r['approved'] ? '<span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Live on homepage</span>' : '<span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">Waiting for approval</span>') . '</div>'
            . '<p class="mt-2 text-sm text-gray-700">' . e($r['body']) . '</p><p class="mt-1 text-xs text-gray-400">' . e(date('j M Y', intdiv((int) $r['createdAt'], 1000))) . '</p>'
            . '<div class="mt-3">' . image_field('photoUrl', 'Customer photo (optional)', $r['photoUrl'], 'product', false) . '</div>'
            . '<div class="mt-3 flex gap-2">'
            . '<button type="button" data-review-save="1" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white">' . ($r['approved'] ? 'Save' : 'Approve') . '</button>'
            . ($r['approved'] ? '<button type="button" data-review-save="0" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium">Hide</button>' : '')
            . '<button type="button" data-review-delete class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700">Delete</button></div></div>';
    }
    $content = '<h1 class="text-2xl font-semibold">Reviews</h1><p class="mt-1 text-gray-500">Shoppers submit reviews from the homepage. Approve the good ones and add a photo of them wearing their find.</p>'
        . '<div class="mt-8 grid max-w-4xl gap-4">' . ($cards ?: '<p class="text-gray-500">No reviews yet.</p>') . '</div>';
    render_admin($content, 'Reviews');
}

function admin_activity_page(): void
{
    admin_guard_page();
    $labels = [
        'login' => 'Signed in', 'login_failed' => 'Failed sign-in', 'product_create' => 'Added product', 'product_update' => 'Edited product',
        'product_delete' => 'Deleted product', 'product_bulk_create' => 'Bulk upload', 'settings_update' => 'Changed settings',
        'order_paid' => 'Marked order paid', 'order_cancel' => 'Cancelled order', 'order_fulfillment' => 'Updated delivery status',
        'discount_save' => 'Saved discount code', 'discount_delete' => 'Deleted discount code', 'review_update' => 'Updated review',
        'review_delete' => 'Deleted review', 'export_orders' => 'Exported orders', 'backup_download' => 'Downloaded backup',
        '2fa_enable' => 'Turned on 2-step login', '2fa_disable' => 'Turned off 2-step login',
    ];
    $rows = '';
    foreach (db()->query('SELECT * FROM AdminLog ORDER BY id DESC LIMIT 200')->fetchAll() as $l) {
        $warn = $l['action'] === 'login_failed';
        $rows .= '<tr' . ($warn ? ' class="bg-red-50"' : '') . '><td class="whitespace-nowrap px-4 py-2 text-gray-500">' . e(date('j M Y, g:ia', intdiv((int) $l['createdAt'], 1000))) . '</td>'
            . '<td class="px-4 py-2 font-medium">' . e($labels[$l['action']] ?? $l['action']) . '</td><td class="px-4 py-2 text-gray-600">' . e($l['detail']) . '</td>'
            . '<td class="px-4 py-2 font-mono text-xs text-gray-400">' . e($l['ip']) . '</td></tr>';
    }
    $content = '<h1 class="text-2xl font-semibold">Activity log</h1><p class="mt-1 text-gray-500">The last 200 admin actions. Failed sign-ins are highlighted in red.</p>'
        . '<div class="mt-8 overflow-x-auto rounded-xl border border-gray-200 bg-white"><table class="w-full min-w-[640px] text-left text-sm">'
        . '<thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Details</th><th class="px-4 py-3">IP address</th></tr></thead>'
        . '<tbody class="divide-y divide-gray-100">' . ($rows ?: '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Nothing yet.</td></tr>') . '</tbody></table></div>';
    render_admin($content, 'Activity log');
}

function admin_security_page(): void
{
    admin_guard_page();
    $on = (bool) meta_get('totp_secret');
    $hidden = admin_base() !== '/admin';
    $content = '<h1 class="text-2xl font-semibold">Security</h1>'
        . '<section class="mt-8 max-w-2xl rounded-xl border border-gray-200 bg-white p-6" id="twofa" data-on="' . ($on ? '1' : '0') . '">'
        . '<h2 class="font-semibold">2-step login</h2>'
        . '<p class="mt-1 text-sm text-gray-500">After your password, you&apos;ll also type a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator or Authy) on your phone. Someone who learns your password still can&apos;t get in.</p>'
        . ($on
            ? '<p class="mt-4 inline-flex items-center gap-2 rounded-full bg-green-100 px-3 py-1 text-sm text-green-800">' . icon('check') . ' On</p>'
              . '<div class="mt-4 flex flex-wrap items-end gap-2"><label class="text-sm">Current code<input data-2fa-code inputmode="numeric" maxlength="6" class="' . ADMIN_INPUT . ' w-36"></label><button type="button" data-2fa-disable class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700">Turn off</button></div>'
            : '<button type="button" data-2fa-start class="mt-4 ' . btn_class() . '">Set up 2-step login</button>'
              . '<div data-2fa-setup class="mt-4 hidden space-y-3 text-sm"><p>1. In your authenticator app, tap <strong>+</strong> → <strong>Enter a setup key</strong>, and type this key:</p>'
              . '<p data-2fa-secret class="select-all rounded-lg bg-gray-100 px-3 py-2 font-mono text-base tracking-wider"></p>'
              . '<p>Or on this phone, <a data-2fa-link href="#" class="text-blue-600 underline">tap here to add it</a>.</p>'
              . '<p>2. Enter the 6-digit code the app shows:</p><div class="flex flex-wrap items-end gap-2"><input data-2fa-code inputmode="numeric" maxlength="6" class="' . ADMIN_INPUT . ' w-36"><button type="button" data-2fa-enable class="' . btn_class() . '">Turn on</button></div></div>')
        . '<p data-2fa-msg class="mt-3 hidden text-sm"></p></section>'
        . '<section class="mt-6 max-w-2xl rounded-xl border border-gray-200 bg-white p-6"><h2 class="font-semibold">Other protections</h2><ul class="mt-3 space-y-2 text-sm text-gray-600">'
        . '<li>✓ Login locks for 30 minutes after 5 wrong attempts</li>'
        . '<li>✓ Every admin action is recorded in the <a href="/admin/activity" class="text-blue-600 underline">activity log</a></li>'
        . '<li>✓ Secure cookies (HTTPS-only, not readable by scripts, not sent from other sites)</li>'
        . '<li>✓ Content-Security-Policy and anti-framing headers on every page</li>'
        . '<li>' . ($hidden ? '✓ Admin panel is at a hidden address — the old /admin link shows “not found”' : '• Admin panel is at /admin. Set ADMIN_PATH in config.php to hide it') . '</li></ul>'
        . '<a href="/api/admin/backup" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">' . icon('download') . 'Download database backup</a></section>';
    render_admin($content, 'Security');
}
