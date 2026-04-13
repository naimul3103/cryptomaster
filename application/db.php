<?php
/**
 * db.php — MySQL database layer for CryptoMaster
 *
 * Credentials are loaded from a .env file in the project root.
 * Copy .env.example → .env and fill in your values before running.
 */

function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

loadEnv(__DIR__ . '/.env');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $host    = getenv('DB_HOST')    ?: '127.0.0.1';
    $db      = getenv('DB_NAME')    ?: 'cryptomaster';
    $user    = getenv('DB_USER')    ?: 'root';
    $pass    = getenv('DB_PASS')    ?: '';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        initSchema($pdo);
        return $pdo;
    } catch (\PDOException $e) {
        $safe = (getenv('APP_ENV') === 'production')
            ? 'Database connection failed. Check server configuration.'
            : 'Database connection failed: ' . $e->getMessage();
        http_response_code(500);
        die(json_encode(['ok' => false, 'error' => $safe]));
    }
}

function initSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            username    VARCHAR(255) NOT NULL UNIQUE,
            password    VARCHAR(255) NOT NULL,
            role        ENUM('user', 'admin') NOT NULL DEFAULT 'user',
            balance     DECIMAL(15,2) NOT NULL DEFAULT 110.00,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login  TIMESTAMP NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS wallet_holdings (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            symbol      VARCHAR(50) NOT NULL,
            amount      DECIMAL(20,8) NOT NULL DEFAULT 0,
            cost_basis  DECIMAL(20,8) NOT NULL DEFAULT 0,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_symbol (user_id, symbol),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@example.com';
    $adminPass  = getenv('ADMIN_PASS')  ?: 'change_me';

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$adminEmail]);

    if (!$stmt->fetch()) {
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("INSERT INTO users (username, password, role, balance) VALUES (?, ?, 'admin', 0)")
            ->execute([$adminEmail, $hash]);
    }
}
