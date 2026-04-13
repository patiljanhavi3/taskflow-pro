<?php
// ============================================================
// auth.php — Session & Auth Helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireGuest(): void {
    if (isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'           => $_SESSION['user_id'] ?? null,
        'username'     => $_SESSION['username'] ?? '',
        'email'        => $_SESSION['email'] ?? '',
        'avatar_color' => $_SESSION['avatar_color'] ?? '#a78bfa',
        'theme'        => $_SESSION['theme'] ?? 'dark',
    ];
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['email']        = $user['email'];
    $_SESSION['avatar_color'] = $user['avatar_color'];
    $_SESSION['theme']        = $user['theme'];
}

function logoutUser(): void {
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}