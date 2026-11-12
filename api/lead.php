<?php
// Public endpoint: POST JSON {name, phone, source, platform?, studio?, software?, topic?, message?, page?, website?}
// Saves the lead, answers the browser right away, then sends it to WhatsApp (AiBotFlow) / webhook.

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

function reply(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, ['success' => false, 'error' => 'POST only']);
    exit;
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;

$clip = fn($k, $n = 200) => mb_substr(trim((string)($in[$k] ?? '')), 0, $n);

// Honeypot: bots fill the hidden "website" field. Pretend success.
if ($clip('website') !== '') {
    reply(200, ['success' => true]);
    exit;
}

$s = settings();
$name  = $clip('name', 80);
$phone = normalize_phone($clip('phone', 30), $s['default_country']);
if ($name === '' || strlen($phone) < 11 || strlen($phone) > 15) {
    reply(400, ['success' => false, 'error' => 'Please enter your name and a valid WhatsApp number.']);
    exit;
}

$source = in_array($clip('source'), ['download', 'contact'], true) ? $clip('source') : 'download';

// Simple rate limit: 10 leads per IP per 10 minutes
$ip = client_ip();
$st = db()->prepare("SELECT COUNT(*) FROM leads WHERE ip = ? AND created_at >= ?");
$st->execute([$ip, (new DateTimeImmutable('-10 minutes', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s')]);
if ((int)$st->fetchColumn() >= 10) {
    reply(429, ['success' => false, 'error' => 'Too many requests. Please try again in a few minutes.']);
    exit;
}

$lead = [
    'name'       => $name,
    'phone'      => $phone,
    'source'     => $source,
    'platform'   => $clip('platform', 30),
    'studio'     => $clip('studio', 120),
    'software'   => $clip('software', 60),
    'topic'      => $clip('topic', 80),
    'message'    => $clip('message', 2000),
    'page'       => $clip('page', 300),
    'ip'         => $ip,
    'user_agent' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
    'created_at' => now(),
];
$lead['updated_at'] = $lead['created_at'];

$cols = array_keys($lead);
db()->prepare('INSERT INTO leads(' . implode(',', $cols) . ') VALUES(' . rtrim(str_repeat('?,', count($cols)), ',') . ')')
    ->execute(array_values($lead));
$lead['id'] = (int)db()->lastInsertId();

reply(200, ['success' => true, 'id' => $lead['id']]);

// Finish the HTTP response so the visitor does not wait for WhatsApp / webhook calls.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    flush();
}

try {
    dispatch_lead($lead, $s);
} catch (Throwable $e) {
    error_log('Lead dispatch failed: ' . $e->getMessage());
}
