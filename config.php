<?php
// ============================================================
//  Omnes MarketPlace — Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'omnes_marketplace');

define('SITE_NAME', 'Omnes MarketPlace');
define('SITE_URL',  'http://localhost/omnes');

define('MAIL_FROM', 'noreply@omnes-marketplace.fr');

// Connexion PDO
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// Démarrage session sécurisé
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helpers
function isLoggedIn(): bool       { return isset($_SESSION['user_id']); }
function isAdmin(): bool          { return ($_SESSION['role'] ?? '') === 'admin'; }
function isVendeur(): bool        { return in_array($_SESSION['role'] ?? '', ['admin','vendeur']); }
function currentUserId(): ?int    { return $_SESSION['user_id'] ?? null; }
function currentRole(): string    { return $_SESSION['role'] ?? 'guest'; }
function e(string $s): string     { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): void { header('Location: '.$url); exit; }

function requireLogin(): void {
    if (!isLoggedIn()) redirect(SITE_URL.'/pages/login.php?redirect='.urlencode($_SERVER['REQUEST_URI']));
}
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) { http_response_code(403); die('Accès refusé.'); }
}

function notify(int $userId, string $message, string $type = 'info'): void {
    $pdo = getPDO();
    $s = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?,?,?)');
    $s->execute([$userId, $message, $type]);
}

function formatPrice(float $p): string { return number_format($p, 2, ',', ' ').' €'; }
