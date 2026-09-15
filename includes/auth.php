<?php

function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    init_session();
    return !empty($_SESSION['user_id']);
}

function require_auth(): void {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function do_login(string $username, string $password): bool {
    init_session();
    try {
        $db = db();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) return false;
        if (!password_verify($password, $user['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    } catch (Throwable $e) {
        log_error('Error', 'Login failed: ' . $e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

function do_logout(): void {
    init_session();
    $_SESSION = [];
    session_destroy();
}

function change_password(string $current, string $new): bool {
    init_session();
    try {
        $db = db();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user) return false;
        if (!password_verify($current, $user['password_hash'])) return false;
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $user['id']]);
        return true;
    } catch (Throwable $e) {
        log_error('Error', 'Password change failed: ' . $e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

function current_username(): string {
    init_session();
    return $_SESSION['username'] ?? '';
}
