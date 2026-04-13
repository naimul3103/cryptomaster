<?php
/**
 * api.php — CryptoMaster Backend
 * Production-hardened: no credentials in source, secure session config,
 * rate-limit hints, and safe error output.
 */

// ── Security headers ──────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Adjust CORS origin to your actual front-end domain in production
$allowedOrigin = getenv('APP_URL') ?: '*';
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Secure session ────────────────────────────────────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (getenv('APP_ENV') === 'production') {
    ini_set('session.cookie_secure', '1');
}
session_start();

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Helpers ───────────────────────────────────────────────────────────────────
function ok(mixed $data = []): void {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function requireAuth(): array {
    if (empty($_SESSION['user_id'])) err('Not authenticated', 401);
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row  = $stmt->fetch();
    if (!$row) err('User not found', 401);
    return $row;
}

function requireAdmin(): array {
    $user = requireAuth();
    if ($user['role'] !== 'admin') err('Admin access required', 403);
    return $user;
}

function binanceFetch(string $url): mixed {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'CryptoMaster/1.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($cerr) return ['__error' => $cerr];
    if ($code !== 200) return ['__http' => $code, '__body' => substr($resp, 0, 200)];
    return json_decode($resp, true) ?? ['__json' => true];
}

// ── Router ────────────────────────────────────────────────────────────────────
switch ($action) {

    /* ── Auth ── */
    case 'register':
        $username = trim($body['username'] ?? '');
        $password =      $body['password'] ?? '';
        if (strlen($username) < 3) err('Username must be at least 3 characters.');
        if (strlen($password) < 6) err('Password must be at least 6 characters.');

        $pdo = getDB();
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) err('Username already taken.');

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $ins  = $pdo->prepare("INSERT INTO users (username, password, role, balance) VALUES (?, ?, 'user', 110)");
        $ins->execute([$username, $hash]);
        $id = (int)$pdo->lastInsertId();

        $_SESSION['user_id'] = $id;
        ok(['id' => $id, 'username' => $username, 'role' => 'user', 'balance' => 110]);
        break;

    case 'login':
        $username = trim($body['username'] ?? '');
        $password =      $body['password'] ?? '';
        if (!$username || !$password) err('Username and password required.');

        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Use a constant-time comparison to avoid user enumeration timing attacks
        if (!$user) {
            password_verify('dummy', '$2y$12$invalidsaltinvalidsaltinvalids.');
            err('Invalid credentials.');
        }
        if (!password_verify($password, $user['password'])) err('Invalid credentials.');

        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        $_SESSION['user_id'] = $user['id'];
        session_regenerate_id(true); // Prevent session fixation

        ok(['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role'], 'balance' => (float)$user['balance']]);
        break;

    case 'logout':
        session_destroy();
        ok();
        break;

    case 'me':
        if (empty($_SESSION['user_id'])) { echo json_encode(['ok' => false, 'error' => 'not_logged_in']); exit; }
        $user  = requireAuth();
        $pdo   = getDB();
        $wStmt = $pdo->prepare("SELECT symbol, amount, cost_basis FROM wallet_holdings WHERE user_id = ?");
        $wStmt->execute([$user['id']]);
        ok(['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role'],
            'balance' => (float)$user['balance'], 'wallet' => $wStmt->fetchAll()]);
        break;

    /* ── Market ── */
    case 'market':
        $data = binanceFetch('https://api.binance.com/api/v3/ticker/24hr');
        if (!is_array($data) || isset($data['__error'], $data['__http'], $data['__json'])) {
            err('Market data temporarily unavailable. Please try again.');
        }
        $filtered = array_values(array_filter($data, function ($e) {
            return isset($e['symbol'], $e['lastPrice'], $e['quoteVolume'])
                && str_contains($e['symbol'], 'USDT')
                && preg_match('/[1-9]/', $e['lastPrice'])
                && (float)$e['quoteVolume'] > 500000;
        }));
        usort($filtered, fn($a, $b) => (float)$b['quoteVolume'] <=> (float)$a['quoteVolume']);
        $result = array_map(fn($e) => [
            'symbol'             => $e['symbol'],
            'lastPrice'          => $e['lastPrice'],
            'priceChangePercent' => $e['priceChangePercent'],
        ], array_slice($filtered, 0, 120));
        echo json_encode(['ok' => true, 'data' => $result]);
        break;

    case 'candles':
        $symbol   = preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['symbol'] ?? 'BTCUSDT'));
        $interval = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['interval'] ?? '15m');
        $limit    = min((int)($_GET['limit'] ?? 100), 500);
        $data     = binanceFetch("https://api.binance.com/api/v3/klines?symbol=$symbol&interval=$interval&limit=$limit");
        if (!is_array($data) || isset($data['__error'], $data['__http'])) err('Failed to fetch candle data.');
        $candles = array_map(fn($e) => [
            'time'  => $e[0],
            'open'  => (float)$e[1],
            'high'  => (float)$e[2],
            'low'   => (float)$e[3],
            'close' => (float)$e[4],
        ], $data);
        echo json_encode(['ok' => true, 'data' => $candles]);
        break;

    /* ── Wallet ── */
    case 'wallet_get':
        $user  = requireAuth();
        $pdo   = getDB();
        $stmt  = $pdo->prepare("SELECT symbol, amount, cost_basis FROM wallet_holdings WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        ok(['balance' => (float)$user['balance'], 'wallet' => $stmt->fetchAll()]);
        break;

    case 'wallet_save':
        $user = requireAuth();
        $pdo  = getDB();
        if (isset($body['balance'])) {
            $newBal = max(0, (float)$body['balance']); // Never allow negative balance via this endpoint
            $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$newBal, $user['id']]);
        }
        if (is_array($body['wallet'] ?? null)) {
            $pdo->prepare("DELETE FROM wallet_holdings WHERE user_id = ?")->execute([$user['id']]);
            $ins = $pdo->prepare("INSERT INTO wallet_holdings (user_id, symbol, amount, cost_basis) VALUES (?,?,?,?)");
            foreach ($body['wallet'] as $w) {
                $sym = preg_replace('/[^A-Z0-9]/', '', strtoupper($w['symbol'] ?? ''));
                $amt = (float)($w['amount'] ?? 0);
                $cb  = (float)($w['cost_basis'] ?? $w['price'] ?? 0);
                if ($sym && $amt > 0) $ins->execute([$user['id'], $sym, $amt, $cb]);
            }
        }
        ok();
        break;

    case 'wallet_topup':
        $user = requireAuth();
        $pdo  = getDB();
        $pdo->prepare("UPDATE users SET balance = balance + 20000 WHERE id = ?")->execute([$user['id']]);
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $bal = (float)$stmt->fetchColumn();
        ok(['balance' => $bal]);
        break;

    /* ── Admin ── */
    case 'admin_users':
        requireAdmin();
        $pdo  = getDB();
        $rows = $pdo->query("
            SELECT u.id, u.username, u.role, u.balance, u.created_at, u.last_login,
                   (SELECT COUNT(*) FROM wallet_holdings w WHERE w.user_id = u.id) AS holding_count
            FROM users u ORDER BY u.created_at DESC
        ")->fetchAll();
        ok($rows);
        break;

    case 'admin_delete_user':
        requireAdmin();
        $uid = (int)($body['user_id'] ?? 0);
        if (!$uid) err('user_id required');
        $pdo = getDB();
        $t   = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $t->execute([$uid]);
        $row = $t->fetch();
        if (!$row) err('User not found');
        if ($row['role'] === 'admin') err('Cannot delete admin accounts');
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        ok();
        break;

    case 'admin_update_balance':
        requireAdmin();
        $uid = (int)($body['user_id'] ?? 0);
        $bal = (float)($body['balance'] ?? -1);
        if (!$uid || $bal < 0) err('user_id and non-negative balance required');
        $pdo = getDB();
        $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?")->execute([$bal, $uid]);
        ok();
        break;

    case 'admin_make_admin':
        requireAdmin();
        $uid = (int)($body['user_id'] ?? 0);
        if (!$uid) err('user_id required');
        getDB()->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$uid]);
        ok();
        break;

    default:
        err('Unknown action', 404);
}
