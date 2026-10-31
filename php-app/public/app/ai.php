<?php
/** Customer-care chat: OpenAI when a key is configured, otherwise rule-based replies. */

function ai_catalog(array $products): string
{
    if (!$products) return 'No products are currently listed.';
    $lines = [];
    foreach ($products as $p) {
        $sale = (int) $p['salePrice'];
        $orig = (int) $p['originalPrice'];
        $price = $sale < $orig ? naira($sale) . ' (was ' . naira($orig) . ', -' . discount_percent($orig, $sale) . '%)' : naira($sale);
        $stock = product_availability($p) === 'sold' ? 'SOLD OUT' : 'IN STOCK';
        $lines[] = "- {$p['name']} | {$p['category']} | size {$p['size']} | {$p['condition']} | $price | $stock | link: /product/{$p['slug']}";
    }
    return implode("\n", $lines);
}

function ai_system_prompt(array $s, array $products): string
{
    $wa = $s['whatsappNumber'] ?: 'not provided';
    $ig = $s['instagramUrl'] ?: 'not provided';
    $catalog = ai_catalog($products);
    return <<<TXT
You are "{$s['storeName']} Assistant", a friendly, helpful customer-care agent for an online thrift (secondhand) clothing store based in Nigeria.

YOUR JOB: Answer customer enquiries about products, prices, sizes, availability, delivery, payment, and how to order. Be warm, concise, and persuasive — like a great shop attendant. Use the occasional emoji, but stay professional.

STORE INFO:
- Store name: {$s['storeName']}
- Tagline: {$s['tagline']}
- About: {$s['aboutText']}
- Customer care phone: {$s['customerCarePhone']} ({$s['customerCareHours']})
- Email: {$s['contactEmail']}
- Location: {$s['contactAddress']}
- WhatsApp: $wa
- Instagram: $ig

PAYMENT & DELIVERY:
- Payment is via Paystack at checkout: card, bank transfer, or USSD.
- Items are one-of-one, so they can sell out fast.
- Delivery is nationwide across Nigeria; the address is collected at checkout.
- To order: open a product, "Add to cart", go to cart, then "Proceed to checkout" and pay with Paystack.

CURRENT CATALOG (use ONLY this; never invent items or prices):
$catalog

RULES:
- If an item is SOLD OUT, say so kindly and suggest similar in-stock items.
- If an item isn't in the catalog, say it's not currently available.
- Never invent prices, sizes, or stock.
- For complaints or refunds, share the customer care phone ({$s['customerCarePhone']}) and WhatsApp.
- Keep replies short (1-4 sentences) unless asked for detail.
- You may include product link paths (e.g. /product/the-slug).
TXT;
}

function ai_reply(string $system, array $messages): ?string
{
    $key = config('OPENAI_API_KEY');
    if (!$key) return null;
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => config('OPENAI_MODEL') ?: 'gpt-4o-mini',
            'temperature' => 0.6,
            'max_tokens' => 350,
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
        ]),
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400 || $raw === false) throw new RuntimeException("OpenAI error $status: " . substr((string) $raw, 0, 200));
    $reply = json_decode($raw, true)['choices'][0]['message']['content'] ?? null;
    return is_string($reply) ? trim($reply) : null;
}

function ai_fallback_reply(string $question, array $s, array $products): string
{
    $q = mb_strtolower($question);
    $mentioned = array_values(array_filter($products, function ($p) use ($q) {
        $name = mb_strtolower($p['name']);
        if (str_contains($q, $name)) return true;
        foreach (explode(' ', $name) as $w) if (mb_strlen($w) >= 4 && str_contains($q, $w)) return true;
        return false;
    }));
    $inStock = fn($p) => product_availability($p) !== 'sold';

    if (preg_match('/price|cost|how much|much is|amount/', $q)) {
        if ($mentioned) {
            return implode(' ', array_map(fn($p) => "{$p['name']} is " . naira((int) $p['salePrice'])
                . ((int) $p['salePrice'] < (int) $p['originalPrice'] ? ' (was ' . naira((int) $p['originalPrice']) . ')' : '')
                . ($inStock($p) ? '' : " — but it's sold out right now") . ". View it here: /product/{$p['slug']}", $mentioned));
        }
        return 'Each piece shows its price right on the product page — the sale price and the original price. Browse the shop to see them all 🙂';
    }
    if (preg_match('/deliver|shipping|ship|send|location|where are you/', $q)) {
        return "We deliver nationwide across Nigeria 🚚. You enter your delivery address at checkout. We're based in {$s['contactAddress']}.";
    }
    if (preg_match('/pay|payment|paystack|card|transfer|ussd|how do i (buy|order|pay)/', $q)) {
        return 'To order: open a product, tap Add to cart, go to your cart, then Proceed to checkout. Payment is secure via Paystack — card, bank transfer, or USSD 🔒.';
    }
    if (preg_match('/size|fit|measurement/', $q)) {
        if ($mentioned) return implode(' ', array_map(fn($p) => "{$p['name']} is size {$p['size']} ({$p['condition']}).", $mentioned));
        return "Each product page lists the exact size and condition. Tell me which item you like and I'll check for you!";
    }
    if (preg_match('/available|in stock|do you have|got any|sold/', $q)) {
        if ($mentioned) {
            return implode(' ', array_map(fn($p) => "{$p['name']} is " . ($inStock($p) ? 'in stock ✅ at ' . naira((int) $p['salePrice']) . " — /product/{$p['slug']}" : 'sold out 😢') . '.', $mentioned));
        }
        $avail = array_slice(array_filter($products, $inStock), 0, 4);
        return $avail
            ? 'Here are a few pieces available now: ' . implode(', ', array_column($avail, 'name')) . '. Browse the shop for the full drop!'
            : 'New drops are added weekly — check back soon or follow us on Instagram!';
    }
    if (preg_match('/contact|call|phone|whatsapp|speak|human|agent|help/', $q)) {
        return "You can reach our customer care on {$s['customerCarePhone']} ({$s['customerCareHours']})"
            . ($s['whatsappNumber'] ? ", or WhatsApp us at {$s['whatsappNumber']}" : '') . '. Happy to help! 💬';
    }
    if (preg_match('/\b(hi|hello|hey)\b|good (morning|afternoon|evening)/', $q)) {
        return "Hi there! 👋 Welcome to {$s['storeName']}. Ask me about any item, prices, sizes, delivery, or how to order.";
    }
    return "I can help with product info, prices, sizes, delivery, and how to order. For anything else, call our customer care on {$s['customerCarePhone']} or browse the shop to see this week's drop 🛍️.";
}
