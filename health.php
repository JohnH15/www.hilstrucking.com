<?php
/**
 * HILS Trucking LLC: health check for the application form.
 *
 * Opened once a day by .github/workflows/health.yml (and by anyone, it is harmless).
 * Checks, without sending a lead anywhere:
 *   - config.php loads (a syntax error there silently breaks the form);
 *   - reCAPTCHA, Google Sheets and Telegram settings are filled in;
 *   - the Google Sheets Apps Script answers (a request with a wrong token is refused, no row is written);
 *   - the Telegram bot token is valid (getMe).
 * The answer is JSON with true/false per check, never any secret.
 * With ?notify=1 a failed check also sends a warning to the Telegram lead chat (at most once per 6 hours).
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$checks = [];
$config = null;

function healthPost(string $url, string $body, int $timeout = 10): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $resp = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp];
}

// 1. config.php loads
try {
    $loaded = include __DIR__ . '/config.php';
    $config = is_array($loaded) ? $loaded : null;
} catch (\Throwable $e) {
    $config = null;
}
$checks['config'] = $config !== null;

if ($config !== null) {
    $rc = $config['recaptcha'] ?? [];
    $gs = $config['google_sheets'] ?? [];
    $tg = $config['telegram'] ?? [];
    $tgToken = trim((string) ($tg['bot_token'] ?? ''));
    $tgChats = array_values(array_filter(array_map('trim', is_array($tg['chat_id'] ?? '') ? $tg['chat_id'] : explode(',', (string) ($tg['chat_id'] ?? ''))), 'strlen'));

    $checks['recaptcha_set'] = empty($rc['enabled']) || (trim((string) ($rc['secret_key'] ?? '')) !== '' && trim((string) ($rc['site_key'] ?? '')) !== '');
    $checks['sheets_set'] = !empty($gs['enabled']) && trim((string) ($gs['webhook_url'] ?? '')) !== '' && trim((string) ($gs['token'] ?? '')) !== '';
    $checks['telegram_set'] = !empty($tg['enabled']) && $tgToken !== '' && !empty($tgChats);
    $checks['curl'] = function_exists('curl_init');

    if ($checks['curl'] && $checks['sheets_set']) {
        // a deliberately wrong token: the script answers "unauthorized" and writes nothing
        [$code, $resp] = healthPost($gs['webhook_url'], json_encode(['token' => 'health-check']));
        $checks['sheets_reachable'] = $code > 0 && $code < 400 && strpos($resp, 'unauthorized') !== false;
    }
    if ($checks['curl'] && $checks['telegram_set']) {
        $ch = curl_init('https://api.telegram.org/bot' . $tgToken . '/getMe');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
        $resp = (string) curl_exec($ch);
        curl_close($ch);
        $checks['telegram_bot'] = strpos($resp, '"ok":true') !== false;
    }
}

$ok = !in_array(false, $checks, true);

// Warning to Telegram (only if the bot itself works), at most once per 6 hours
if (!$ok && isset($_GET['notify']) && $config !== null && !empty($checks['telegram_bot'])) {
    $stamp = dirname(__DIR__) . '/hils-health-last-alert';
    $last = is_file($stamp) ? (int) @file_get_contents($stamp) : 0;
    if (time() - $last > 6 * 3600) {
        $failed = implode(', ', array_keys(array_filter($checks, function ($v) { return $v === false; })));
        $text = "⚠️ Проверка формы на hilstrucking.com: не работает " . $failed . ". Заявки могут теряться, проверьте config.php и send-mail.log.";
        foreach ($tgChats as $chatId) {
            healthPost('https://api.telegram.org/bot' . $tgToken . '/sendMessage', json_encode(['chat_id' => $chatId, 'text' => $text], JSON_UNESCAPED_UNICODE), 8);
        }
        @file_put_contents($stamp, (string) time());
    }
}

http_response_code($ok ? 200 : 503);
echo json_encode(['ok' => $ok, 'checks' => $checks, 'time' => gmdate('c')], JSON_PRETTY_PRINT);
