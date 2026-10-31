<?php
// Usage: php make-config.php <path/to/.env> <APP_URL> > config.php
// Builds the PHP config from the original .env so the same keys keep working.
$env = [];
foreach (file($argv[1], FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) $env[$m[1]] = trim($m[2], " \"'");
}
$cfg = [
    'APP_URL' => $argv[2],
    'ADMIN_PASSWORD' => $env['ADMIN_PASSWORD'] ?? '',
    'PAYSTACK_SECRET_KEY' => $env['PAYSTACK_SECRET_KEY'] ?? '',
    'OPENAI_API_KEY' => $env['OPENAI_API_KEY'] ?? '',
    'OPENAI_MODEL' => $env['OPENAI_MODEL'] ?? 'gpt-4o-mini',
    'VAPID_PUBLIC_KEY' => $env['NEXT_PUBLIC_VAPID_PUBLIC_KEY'] ?? '',
    'VAPID_PRIVATE_KEY' => $env['VAPID_PRIVATE_KEY'] ?? '',
    'VAPID_SUBJECT' => $env['VAPID_SUBJECT'] ?? 'mailto:hello@example.com',
];
echo "<?php\n// Store secrets. Keep this file outside the public web folder.\nreturn " . var_export($cfg, true) . ";\n";
