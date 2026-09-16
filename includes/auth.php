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

function require_owner(): void {
    require_auth();
    if (current_role() !== 'owner') {
        set_flash('error', 'Access denied. Owner privileges required.');
        redirect('today.php');
    }
}

function do_login(string $username, string $password): bool {
    init_session();
    try {
        $db = db();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) {
            log_error('Auth', "Failed login: username '$username' not found", __FILE__, __LINE__);
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            log_error('Auth', "Failed login: wrong password for '$username'", __FILE__, __LINE__);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'owner';
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

function set_assistant_password(string $new): bool {
    try {
        $db = db();
        $stmt = $db->prepare("SELECT id FROM users WHERE role = 'assistant' LIMIT 1");
        $stmt->execute();
        $assistant = $stmt->fetch();
        $hash = password_hash($new, PASSWORD_DEFAULT);
        if ($assistant) {
            $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $assistant['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'assistant')");
            $stmt->execute(['assistant', $hash]);
        }
        return true;
    } catch (Throwable $e) {
        log_error('Error', 'Set assistant password failed: ' . $e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

function current_username(): string {
    init_session();
    return $_SESSION['username'] ?? '';
}

function current_role(): string {
    init_session();
    return $_SESSION['role'] ?? 'owner';
}

function is_owner(): bool {
    return current_role() === 'owner';
}

function is_assistant(): bool {
    return current_role() === 'assistant';
}
