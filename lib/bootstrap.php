<?php
// Shared code for the lead API and the admin panel.
// Leads + settings live in a SQLite file inside /data (blocked from the web by data/.htaccess).

declare(strict_types=1);

ini_set('display_errors', '0');

const DATA_DIR = __DIR__ . '/../data';
const DB_FILE  = DATA_DIR . '/leads.sqlite';

const LEAD_STATUSES = [
    'new'       => 'New',
    'contacted' => 'Contacted',
    'converted' => 'Converted',
    'lost'      => 'Lost',
];

const DEFAULT_SETTINGS = [
    // AiBotFlow WhatsApp API (https://dash.aibotflow.in/api/v1/messages/send)
    'abf_enabled'        => '0',
    'abf_base_url'       => 'https://dash.aibotflow.in/api/v1',
    'abf_api_key'        => '',
    'abf_template'       => '',          // approved WhatsApp template name
    'abf_language'       => 'en',
    'abf_params'         => '{name}',    // comma separated body params, placeholders allowed
    // Alert to the owner on every new lead (plain text message)
    'abf_owner_alert'    => '0',
    'abf_owner_phone'    => '',
    'abf_owner_text'     => "New lead ({source})\nName: {name}\nPhone: {phone}\nPlatform: {platform}\nMessage: {message}",
    // Generic webhook (AiBotFlow flow trigger, n8n, Make, Zapier, Google Sheets...)
    'webhook_enabled'    => '0',
    'webhook_url'        => '',
    'webhook_secret'     => '',          // sent as X-Webhook-Secret header
    'default_country'    => '91',
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0750, true);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leads (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT NOT NULL,
            phone       TEXT NOT NULL,
            source      TEXT NOT NULL DEFAULT 'download',
            platform    TEXT NOT NULL DEFAULT '',
            studio      TEXT NOT NULL DEFAULT '',
            software    TEXT NOT NULL DEFAULT '',
            topic       TEXT NOT NULL DEFAULT '',
            message     TEXT NOT NULL DEFAULT '',
            page        TEXT NOT NULL DEFAULT '',
            status      TEXT NOT NULL DEFAULT 'new',
            notes       TEXT NOT NULL DEFAULT '',
            wa_status   TEXT NOT NULL DEFAULT '',
            ip          TEXT NOT NULL DEFAULT '',
            user_agent  TEXT NOT NULL DEFAULT '',
            created_at  TEXT NOT NULL,
            updated_at  TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_leads_created ON leads(created_at);
        CREATE INDEX IF NOT EXISTS idx_leads_phone ON leads(phone);
        CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS webhook_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            lead_id     INTEGER,
            target      TEXT NOT NULL,
            http_code   INTEGER NOT NULL DEFAULT 0,
            ok          INTEGER NOT NULL DEFAULT 0,
            response    TEXT NOT NULL DEFAULT '',
            created_at  TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, at INTEGER NOT NULL);
    ");
    return $pdo;
}

function now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
}

function settings(): array
{
    $rows = db()->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return array_merge(DEFAULT_SETTINGS, ['admin_password_hash' => ''], $rows);
}

function save_setting(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $st->execute([$key, $value]);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Digits only, with country code. "98765 43210" -> "919876543210". */
function normalize_phone(string $raw, string $country = '91'): string
{
    $d = preg_replace('/\D+/', '', $raw);
    $d = ltrim($d, '0');
    if (strlen($d) === 10) $d = $country . $d;
    return $d;
}

function fill_placeholders(string $tpl, array $lead): string
{
    $map = [];
    foreach (['id', 'name', 'phone', 'source', 'platform', 'studio', 'software', 'topic', 'message', 'page', 'created_at'] as $k) {
        $map['{' . $k . '}'] = (string)($lead[$k] ?? '');
    }
    return strtr($tpl, $map);
}

function http_post_json(string $url, array $payload, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    if ($body === false) $body = 'cURL error: ' . $err;
    return ['code' => $code, 'ok' => $code >= 200 && $code < 300, 'body' => substr((string)$body, 0, 2000)];
}

function log_webhook(?int $leadId, string $target, array $res): void
{
    db()->prepare('INSERT INTO webhook_log(lead_id, target, http_code, ok, response, created_at) VALUES(?,?,?,?,?,?)')
        ->execute([$leadId, $target, $res['code'], $res['ok'] ? 1 : 0, $res['body'], now()]);
}

/**
 * Send a lead to every enabled destination. Returns a short status string, e.g. "template:ok webhook:fail(500)".
 */
function dispatch_lead(array $lead, ?array $s = null): string
{
    $s = $s ?? settings();
    $id = isset($lead['id']) ? (int)$lead['id'] : null;
    $out = [];
    $auth = ['Authorization: Bearer ' . $s['abf_api_key']];
    $sendUrl = rtrim($s['abf_base_url'], '/') . '/messages/send';

    // 1. WhatsApp template to the lead via AiBotFlow
    if ($s['abf_enabled'] === '1' && $s['abf_api_key'] !== '' && $s['abf_template'] !== '') {
        $params = [];
        foreach (array_filter(array_map('trim', explode(',', $s['abf_params'])), 'strlen') as $p) {
            $params[] = ['type' => 'text', 'text' => fill_placeholders($p, $lead) ?: '-'];
        }
        $template = ['name' => $s['abf_template'], 'language' => $s['abf_language'] ?: 'en'];
        if ($params) $template['components'] = [['type' => 'body', 'parameters' => $params]];
        $res = http_post_json($sendUrl, ['to' => '+' . $lead['phone'], 'type' => 'template', 'template' => $template], $auth);
        log_webhook($id, 'aibotflow:template', $res);
        $out[] = 'template:' . ($res['ok'] ? 'ok' : 'fail(' . $res['code'] . ')');
    }

    // 2. Plain text alert to the owner via AiBotFlow
    if ($s['abf_owner_alert'] === '1' && $s['abf_api_key'] !== '' && $s['abf_owner_phone'] !== '') {
        $to = '+' . normalize_phone($s['abf_owner_phone'], $s['default_country']);
        $res = http_post_json($sendUrl, [
            'to'   => $to,
            'type' => 'text',
            'text' => ['body' => fill_placeholders($s['abf_owner_text'], $lead)],
        ], $auth);
        log_webhook($id, 'aibotflow:owner', $res);
        $out[] = 'owner:' . ($res['ok'] ? 'ok' : 'fail(' . $res['code'] . ')');
    }

    // 3. Generic webhook with the full lead
    if ($s['webhook_enabled'] === '1' && filter_var($s['webhook_url'], FILTER_VALIDATE_URL)) {
        $headers = $s['webhook_secret'] !== '' ? ['X-Webhook-Secret: ' . $s['webhook_secret']] : [];
        $payload = [
            'event'     => 'lead.created',
            'timestamp' => time(),
            'data'      => [
                'id'         => $id,
                'name'       => $lead['name'],
                'phone'      => '+' . $lead['phone'],
                'source'     => $lead['source'] ?? '',
                'platform'   => $lead['platform'] ?? '',
                'studio'     => $lead['studio'] ?? '',
                'software'   => $lead['software'] ?? '',
                'topic'      => $lead['topic'] ?? '',
                'message'    => $lead['message'] ?? '',
                'page'       => $lead['page'] ?? '',
                'created_at' => $lead['created_at'] ?? now(),
            ],
        ];
        $res = http_post_json($s['webhook_url'], $payload, $headers);
        log_webhook($id, 'webhook', $res);
        $out[] = 'webhook:' . ($res['ok'] ? 'ok' : 'fail(' . $res['code'] . ')');
    }

    $status = implode(' ', $out);
    if ($id) {
        db()->prepare('UPDATE leads SET wa_status = ?, updated_at = ? WHERE id = ?')->execute([$status, now(), $id]);
    }
    return $status;
}
