<?php
// AI Video Flow — Lead Admin
// /admin            leads list (search, filter, status, notes, CSV, resend)
// /admin?tab=settings  WhatsApp (AiBotFlow) + webhook settings, test send, password
// /admin?tab=logs      delivery log

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

session_name('avf_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/admin',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$msg, $type]; }
function go(string $qs = ''): void { header('Location: ./' . ($qs !== '' ? '?' . $qs : '')); exit; }

$s = settings();
$hasPassword = $s['admin_password_hash'] !== '';
$authed = !empty($_SESSION['admin']);
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = $_POST['action'] ?? '';

if ($post && !hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
    flash('Session expired, please try again.', 'err');
    go();
}

// ---------- First run: create password ----------
if (!$hasPassword && $post && $action === 'setup') {
    $p = (string)($_POST['password'] ?? '');
    if (strlen($p) < 8 || $p !== ($_POST['password2'] ?? '')) {
        flash('Password must be at least 8 characters and both fields must match.', 'err');
        go();
    }
    save_setting('admin_password_hash', password_hash($p, PASSWORD_DEFAULT));
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    flash('Admin password set. Welcome!');
    go('tab=settings');
}

// ---------- Login ----------
if ($hasPassword && !$authed && $post && $action === 'login') {
    $ip = client_ip();
    db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 900]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 5) {
        flash('Too many attempts. Try again in 15 minutes.', 'err');
        go();
    }
    if (password_verify((string)($_POST['password'] ?? ''), $s['admin_password_hash'])) {
        db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        go();
    }
    db()->prepare('INSERT INTO login_attempts(ip, at) VALUES(?, ?)')->execute([$ip, time()]);
    flash('Wrong password.', 'err');
    go();
}

if ($authed && $post && $action === 'logout') {
    $_SESSION = [];
    session_destroy();
    go();
}

// ---------- Authenticated actions ----------
if ($authed && $post) {
    $id = (int)($_POST['id'] ?? 0);
    $back = (string)($_POST['back'] ?? '');
    switch ($action) {
        case 'update_lead':
            $status = array_key_exists($_POST['status'] ?? '', LEAD_STATUSES) ? $_POST['status'] : 'new';
            db()->prepare('UPDATE leads SET status = ?, notes = ?, updated_at = ? WHERE id = ?')
                ->execute([$status, mb_substr((string)($_POST['notes'] ?? ''), 0, 2000), now(), $id]);
            flash('Lead #' . $id . ' updated.');
            go($back);
        case 'delete_lead':
            db()->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
            flash('Lead #' . $id . ' deleted.');
            go($back);
        case 'resend':
            $st = db()->prepare('SELECT * FROM leads WHERE id = ?');
            $st->execute([$id]);
            if ($lead = $st->fetch()) {
                $r = dispatch_lead($lead);
                flash('Lead #' . $id . ' sent: ' . ($r !== '' ? $r : 'nothing enabled in Settings'), $r === '' || str_contains($r, 'fail') ? 'err' : 'ok');
            }
            go($back);
        case 'save_settings':
            foreach (array_keys(DEFAULT_SETTINGS) as $k) {
                if (in_array($k, ['abf_enabled', 'abf_owner_alert', 'webhook_enabled'], true)) {
                    save_setting($k, isset($_POST[$k]) ? '1' : '0');
                } elseif ($k === 'abf_api_key' && ($_POST[$k] ?? '') === '') {
                    continue; // blank = keep the saved key
                } else {
                    save_setting($k, trim((string)($_POST[$k] ?? '')));
                }
            }
            if (!empty($_POST['clear_api_key'])) save_setting('abf_api_key', '');
            flash('Settings saved.');
            go('tab=settings');
        case 'test_send':
            $phone = normalize_phone((string)($_POST['test_phone'] ?? ''), $s['default_country']);
            if (strlen($phone) < 11) {
                flash('Enter a valid test number.', 'err');
                go('tab=settings');
            }
            $r = dispatch_lead([
                'name' => 'Test Lead', 'phone' => $phone, 'source' => 'test', 'platform' => 'windows',
                'message' => 'Test from admin panel', 'page' => '/admin', 'created_at' => now(),
            ]);
            flash('Test result: ' . ($r !== '' ? $r : 'nothing is enabled. Turn on a destination first.') . ' (details in Delivery Log)', $r === '' || str_contains($r, 'fail') ? 'err' : 'ok');
            go('tab=settings');
        case 'change_password':
            $p = (string)($_POST['new_password'] ?? '');
            if (!password_verify((string)($_POST['current_password'] ?? ''), $s['admin_password_hash'])) {
                flash('Current password is wrong.', 'err');
            } elseif (strlen($p) < 8) {
                flash('New password must be at least 8 characters.', 'err');
            } else {
                save_setting('admin_password_hash', password_hash($p, PASSWORD_DEFAULT));
                flash('Password changed.');
            }
            go('tab=settings');
        case 'clear_logs':
            db()->exec('DELETE FROM webhook_log');
            flash('Delivery log cleared.');
            go('tab=logs');
    }
}

// ---------- Query for leads ----------
$tab = $authed ? ($_GET['tab'] ?? 'leads') : 'login';
$q = trim((string)($_GET['q'] ?? ''));
$fStatus = (string)($_GET['status'] ?? '');
$fSource = (string)($_GET['source'] ?? '');
$where = [];
$args = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR phone LIKE ? OR studio LIKE ? OR message LIKE ?)';
    $like = '%' . $q . '%';
    array_push($args, $like, '%' . preg_replace('/\D/', '', $q) . '%', $like, $like);
    if (preg_replace('/\D/', '', $q) === '') $args[1] = $like;
}
if (array_key_exists($fStatus, LEAD_STATUSES)) { $where[] = 'status = ?'; $args[] = $fStatus; }
if (in_array($fSource, ['download', 'contact'], true)) { $where[] = 'source = ?'; $args[] = $fSource; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

if ($authed && ($_GET['export'] ?? '') === 'csv') {
    $st = db()->prepare('SELECT id, created_at, name, phone, source, platform, studio, software, topic, message, status, notes, wa_status, page FROM leads' . $whereSql . ' ORDER BY id DESC');
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aivideoflow-leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Date', 'Name', 'Phone', 'Source', 'Platform', 'Studio', 'Software', 'Topic', 'Message', 'Status', 'Notes', 'WhatsApp', 'Page'], ',', '"', '');
    while ($row = $st->fetch()) {
        // Stop spreadsheet formula injection
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) && !preg_match('/^\+\d+$/', (string)$v) ? "'" . $v : $v, $row), ',', '"', '');
    }
    exit;
}

$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$leads = [];
$total = 0;
$counts = [];
if ($authed && $tab === 'leads') {
    $st = db()->prepare('SELECT COUNT(*) FROM leads' . $whereSql);
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    $st = db()->prepare('SELECT * FROM leads' . $whereSql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
    $st->execute($args);
    $leads = $st->fetchAll();
    $today = substr(now(), 0, 10);
    $counts = db()->query("SELECT COUNT(*) AS total,
        SUM(created_at >= '$today') AS today,
        SUM(status = 'new') AS fresh,
        SUM(status = 'converted') AS converted
        FROM leads")->fetch();
}
$logs = ($authed && $tab === 'logs') ? db()->query('SELECT * FROM webhook_log ORDER BY id DESC LIMIT 200')->fetchAll() : [];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$backQs = http_build_query(array_filter(['q' => $q, 'status' => $fStatus, 'source' => $fSource, 'p' => $page > 1 ? $page : null]));
$waLink = fn($phone) => 'https://wa.me/' . preg_replace('/\D/', '', (string)$phone);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Lead Admin — AI Video Flow</title>
<style>
:root{--bg:#0b0d12;--panel:#12151c;--line:#232835;--text:#e8ebf2;--muted:#8b93a7;--accent:#6366f1;--ok:#10b981;--err:#ef4444;--warn:#f59e0b}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
a{color:#a5b4fc}
.wrap{max-width:1280px;margin:0 auto;padding:20px 16px 60px}
header{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px}
header h1{font-size:18px;margin:0;flex:1}
nav a{padding:8px 14px;border-radius:8px;text-decoration:none;color:var(--muted)}
nav a.on{background:var(--panel);color:var(--text);border:1px solid var(--line)}
.btn{display:inline-block;background:var(--accent);color:#fff;border:0;border-radius:8px;padding:8px 14px;font:inherit;cursor:pointer;text-decoration:none}
.btn.ghost{background:transparent;border:1px solid var(--line);color:var(--text)}
.btn.sm{padding:5px 10px;font-size:12px}
.btn.danger{background:transparent;border:1px solid #5b2020;color:#fca5a5}
.btn.wa{background:#16a34a}
input,select,textarea{background:#0e1117;border:1px solid var(--line);color:var(--text);border-radius:8px;padding:8px 10px;font:inherit;width:100%}
textarea{resize:vertical}
label{display:block;color:var(--muted);font-size:12px;margin:12px 0 4px}
.card{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:18px}
.flash{padding:10px 14px;border-radius:8px;margin-bottom:16px;border:1px solid}
.flash.ok{border-color:#065f46;background:#052e22;color:#a7f3d0}
.flash.err{border-color:#7f1d1d;background:#2a0b0b;color:#fecaca}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.stat b{display:block;font-size:24px}
.stat span{color:var(--muted);font-size:12px}
.filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.filters input{flex:1;min-width:200px}
.filters select{width:auto}
.table{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--line);vertical-align:top}
th{color:var(--muted);font-weight:500;font-size:12px;white-space:nowrap}
td small{color:var(--muted)}
.pill{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;border:1px solid var(--line)}
.pill.new{color:#93c5fd;border-color:#1e3a8a}.pill.contacted{color:#fcd34d;border-color:#78350f}
.pill.converted{color:#6ee7b7;border-color:#065f46}.pill.lost{color:#9ca3af}
.pill.download{color:#c4b5fd}.pill.contact{color:#f9a8d4}
.wa-ok{color:var(--ok)}.wa-fail{color:var(--err)}
details summary{cursor:pointer;color:#a5b4fc;font-size:12px}
details form{margin-top:8px;display:grid;gap:6px;min-width:220px}
.row{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.switch{display:flex;align-items:center;gap:8px;color:var(--text);font-size:14px;margin:0}
.switch input{width:auto}
.hint{color:var(--muted);font-size:12px;margin:4px 0 0}
code{background:#0e1117;padding:1px 5px;border-radius:4px;font-size:12px}
.pager{display:flex;gap:8px;justify-content:flex-end;margin-top:12px;color:var(--muted);align-items:center}
.login{max-width:380px;margin:12vh auto}
pre{white-space:pre-wrap;word-break:break-all;margin:0;font-size:12px;color:var(--muted);max-height:120px;overflow:auto}
h2{font-size:16px;margin:0 0 4px}
@media (max-width:800px){.stats{grid-template-columns:repeat(2,1fr)}.grid2{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

<?php if ($flash): ?><div class="flash <?= h($flash[1]) ?>"><?= h($flash[0]) ?></div><?php endif; ?>

<?php if (!$authed): ?>
  <div class="card login">
    <?php if (!$hasPassword): ?>
      <h2>Create admin password</h2>
      <p class="hint">First-time setup. This password protects your leads.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="setup">
        <label>New password (min 8 characters)</label><input type="password" name="password" required minlength="8" autocomplete="new-password">
        <label>Repeat password</label><input type="password" name="password2" required minlength="8" autocomplete="new-password">
        <p><button class="btn">Save &amp; continue</button></p>
      </form>
    <?php else: ?>
      <h2>Lead Admin</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="login">
        <label>Password</label><input type="password" name="password" required autofocus autocomplete="current-password">
        <p><button class="btn">Log in</button></p>
      </form>
    <?php endif; ?>
  </div>

<?php else: ?>
  <header>
    <h1>AI Video Flow · Lead Admin</h1>
    <nav>
      <a href="./" class="<?= $tab === 'leads' ? 'on' : '' ?>">Leads</a>
      <a href="?tab=settings" class="<?= $tab === 'settings' ? 'on' : '' ?>">WhatsApp &amp; Webhook</a>
      <a href="?tab=logs" class="<?= $tab === 'logs' ? 'on' : '' ?>">Delivery Log</a>
    </nav>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="btn ghost sm">Log out</button></form>
  </header>

  <?php if ($tab === 'leads'): ?>
    <div class="stats">
      <div class="card stat"><b><?= (int)$counts['total'] ?></b><span>Total leads</span></div>
      <div class="card stat"><b><?= (int)$counts['today'] ?></b><span>Today</span></div>
      <div class="card stat"><b><?= (int)$counts['fresh'] ?></b><span>New (not contacted)</span></div>
      <div class="card stat"><b><?= (int)$counts['converted'] ?></b><span>Converted</span></div>
    </div>

    <form class="filters" method="get">
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search name, phone, studio, message">
      <select name="status"><option value="">All statuses</option>
        <?php foreach (LEAD_STATUSES as $k => $v): ?><option value="<?= h($k) ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?>
      </select>
      <select name="source"><option value="">All sources</option>
        <option value="download" <?= $fSource === 'download' ? 'selected' : '' ?>>Download popup</option>
        <option value="contact" <?= $fSource === 'contact' ? 'selected' : '' ?>>Contact form</option>
      </select>
      <button class="btn">Filter</button>
      <a class="btn ghost" href="?<?= h(http_build_query(array_filter(['q' => $q, 'status' => $fStatus, 'source' => $fSource]) + ['export' => 'csv'])) ?>">Export CSV</a>
    </form>

    <div class="card table">
      <table>
        <thead><tr><th>#</th><th>Date</th><th>Lead</th><th>Source</th><th>Details</th><th>Status</th><th>WhatsApp</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$leads): ?><tr><td colspan="8" style="color:var(--muted);text-align:center;padding:30px">No leads yet.</td></tr><?php endif; ?>
        <?php foreach ($leads as $l): ?>
          <tr>
            <td><?= (int)$l['id'] ?></td>
            <td style="white-space:nowrap"><?= h(substr($l['created_at'], 0, 16)) ?></td>
            <td><b><?= h($l['name']) ?></b><br><a href="<?= h($waLink($l['phone'])) ?>" target="_blank" rel="noopener">+<?= h($l['phone']) ?></a></td>
            <td><span class="pill <?= h($l['source']) ?>"><?= h($l['source']) ?></span><?php if ($l['platform']): ?><br><small><?= h($l['platform']) ?></small><?php endif; ?></td>
            <td style="max-width:320px">
              <?php if ($l['studio']): ?><small>Studio:</small> <?= h($l['studio']) ?><br><?php endif; ?>
              <?php if ($l['software'] || $l['topic']): ?><small><?= h(trim($l['software'] . ' · ' . $l['topic'], ' ·')) ?></small><br><?php endif; ?>
              <?php if ($l['message']): ?><?= nl2br(h(mb_strimwidth($l['message'], 0, 220, '…'))) ?><br><?php endif; ?>
              <?php if ($l['notes']): ?><small>📝 <?= h($l['notes']) ?></small><?php endif; ?>
            </td>
            <td><span class="pill <?= h($l['status']) ?>"><?= h(LEAD_STATUSES[$l['status']] ?? $l['status']) ?></span></td>
            <td><small class="<?= str_contains($l['wa_status'], 'fail') ? 'wa-fail' : ($l['wa_status'] ? 'wa-ok' : '') ?>"><?= h($l['wa_status'] ?: '—') ?></small></td>
            <td>
              <div class="row">
                <a class="btn wa sm" href="<?= h($waLink($l['phone'])) ?>?text=<?= rawurlencode('Namaste ' . $l['name'] . ', AI Video Flow se baat kar rahe hain.') ?>" target="_blank" rel="noopener">Chat</a>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="back" value="<?= h($backQs) ?>"><button class="btn ghost sm" title="Send again to WhatsApp / webhook">Resend</button></form>
              </div>
              <details>
                <summary>Edit</summary>
                <form method="post">
                  <?= csrf_field() ?><input type="hidden" name="action" value="update_lead"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="back" value="<?= h($backQs) ?>">
                  <select name="status"><?php foreach (LEAD_STATUSES as $k => $v): ?><option value="<?= h($k) ?>" <?= $l['status'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
                  <textarea name="notes" rows="2" placeholder="Notes"><?= h($l['notes']) ?></textarea>
                  <button class="btn sm">Save</button>
                </form>
                <form method="post" onsubmit="return confirm('Delete lead #<?= (int)$l['id'] ?>?')" style="margin-top:6px">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete_lead"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="back" value="<?= h($backQs) ?>">
                  <button class="btn danger sm">Delete</button>
                </form>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php $pages = max(1, (int)ceil($total / $perPage)); if ($pages > 1): $base = array_filter(['q' => $q, 'status' => $fStatus, 'source' => $fSource]); ?>
        <div class="pager">
          <?php if ($page > 1): ?><a class="btn ghost sm" href="?<?= h(http_build_query($base + ['p' => $page - 1])) ?>">← Prev</a><?php endif; ?>
          Page <?= $page ?> of <?= $pages ?> (<?= $total ?> leads)
          <?php if ($page < $pages): ?><a class="btn ghost sm" href="?<?= h(http_build_query($base + ['p' => $page + 1])) ?>">Next →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

  <?php elseif ($tab === 'settings'): ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_settings">
      <div class="grid2">
        <div class="card">
          <h2>AiBotFlow WhatsApp API</h2>
          <p class="hint">Har naye lead ko aapka approved WhatsApp template bheja jayega. API key: AiBotFlow dashboard → Settings → API Keys.</p>
          <label class="switch"><input type="checkbox" name="abf_enabled" <?= $s['abf_enabled'] === '1' ? 'checked' : '' ?>> Lead ko WhatsApp template bhejo</label>
          <label>API Base URL</label><input name="abf_base_url" value="<?= h($s['abf_base_url']) ?>">
          <p class="hint">Messages go to <code>{base}/messages/send</code></p>
          <label>API Key <?= $s['abf_api_key'] !== '' ? '(saved: …' . h(substr($s['abf_api_key'], -4)) . ')' : '' ?></label>
          <input name="abf_api_key" type="password" autocomplete="off" placeholder="<?= $s['abf_api_key'] !== '' ? 'Leave blank to keep the saved key' : 'abf_live_…' ?>">
          <?php if ($s['abf_api_key'] !== ''): ?><label class="switch" style="margin-top:6px"><input type="checkbox" name="clear_api_key"> Remove saved key</label><?php endif; ?>
          <label>Template name</label><input name="abf_template" value="<?= h($s['abf_template']) ?>" placeholder="e.g. download_welcome">
          <label>Template language</label><input name="abf_language" value="<?= h($s['abf_language']) ?>" placeholder="en / hi / en_US">
          <label>Template body parameters (comma separated, in order {{1}}, {{2}}…)</label>
          <input name="abf_params" value="<?= h($s['abf_params']) ?>" placeholder="{name},{platform}">
          <p class="hint">Placeholders: <code>{name}</code> <code>{phone}</code> <code>{platform}</code> <code>{source}</code> <code>{studio}</code> <code>{software}</code> <code>{topic}</code> <code>{message}</code>. Template has no variables? Leave empty.</p>

          <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
          <h2>Owner alert</h2>
          <label class="switch"><input type="checkbox" name="abf_owner_alert" <?= $s['abf_owner_alert'] === '1' ? 'checked' : '' ?>> Har naye lead par mujhe WhatsApp alert bhejo</label>
          <label>Your WhatsApp number</label><input name="abf_owner_phone" value="<?= h($s['abf_owner_phone']) ?>" placeholder="9939800780">
          <label>Alert message</label><textarea name="abf_owner_text" rows="5"><?= h($s['abf_owner_text']) ?></textarea>
          <p class="hint">Plain text message. WhatsApp only delivers it if you messaged your business number in the last 24 hours. Otherwise use a template or the webhook.</p>
        </div>

        <div class="card">
          <h2>Webhook</h2>
          <p class="hint">Har lead ko JSON me kisi bhi URL par POST karein: AiBotFlow flow trigger, n8n, Make, Zapier, Google Sheets, etc.</p>
          <label class="switch"><input type="checkbox" name="webhook_enabled" <?= $s['webhook_enabled'] === '1' ? 'checked' : '' ?>> Webhook on</label>
          <label>Webhook URL</label><input name="webhook_url" type="url" value="<?= h($s['webhook_url']) ?>" placeholder="https://…">
          <label>Secret (sent as <code>X-Webhook-Secret</code> header, optional)</label><input name="webhook_secret" value="<?= h($s['webhook_secret']) ?>">
          <p class="hint">Payload:</p>
<pre style="max-height:none;background:#0e1117;padding:10px;border-radius:8px">{
  "event": "lead.created",
  "timestamp": 1789650000,
  "data": {
    "id": 12, "name": "Rahul Sharma",
    "phone": "+919876543210",
    "source": "download", "platform": "windows",
    "studio": "", "software": "", "topic": "",
    "message": "", "page": "/pricing",
    "created_at": "2026-11-12 18:30:00"
  }
}</pre>
          <label>Default country code</label><input name="default_country" value="<?= h($s['default_country']) ?>" style="max-width:100px">
          <p class="hint">10-digit numbers get this code in front.</p>
          <p style="margin-top:18px"><button class="btn">Save settings</button></p>
        </div>
      </div>
    </form>

    <div class="grid2" style="margin-top:16px">
      <div class="card">
        <h2>Send a test</h2>
        <p class="hint">Saves nothing to Leads. Runs every enabled destination with a dummy lead (name "Test Lead") to this number.</p>
        <form method="post" class="row" style="margin-top:10px">
          <?= csrf_field() ?><input type="hidden" name="action" value="test_send">
          <input name="test_phone" placeholder="Your WhatsApp number" style="flex:1" required>
          <button class="btn wa">Send test</button>
        </form>
      </div>
      <div class="card">
        <h2>Change password</h2>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
          <label>Current password</label><input type="password" name="current_password" required autocomplete="current-password">
          <label>New password</label><input type="password" name="new_password" required minlength="8" autocomplete="new-password">
          <p><button class="btn ghost">Change password</button></p>
        </form>
      </div>
    </div>

  <?php elseif ($tab === 'logs'): ?>
    <div class="card table">
      <div class="row" style="justify-content:space-between;margin-bottom:8px">
        <h2>Delivery log (last 200)</h2>
        <form method="post" onsubmit="return confirm('Clear the log?')"><?= csrf_field() ?><input type="hidden" name="action" value="clear_logs"><button class="btn ghost sm">Clear</button></form>
      </div>
      <table>
        <thead><tr><th>Time</th><th>Lead</th><th>Target</th><th>HTTP</th><th>Response</th></tr></thead>
        <tbody>
        <?php if (!$logs): ?><tr><td colspan="5" style="color:var(--muted);text-align:center;padding:30px">Nothing sent yet.</td></tr><?php endif; ?>
        <?php foreach ($logs as $g): ?>
          <tr>
            <td style="white-space:nowrap"><?= h($g['created_at']) ?></td>
            <td><?= $g['lead_id'] ? '#' . (int)$g['lead_id'] : '<small>test</small>' ?></td>
            <td><?= h($g['target']) ?></td>
            <td class="<?= $g['ok'] ? 'wa-ok' : 'wa-fail' ?>"><?= (int)$g['http_code'] ?: 'error' ?></td>
            <td><pre><?= h($g['response']) ?></pre></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
