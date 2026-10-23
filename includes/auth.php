<?php
declare(strict_types=1);

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) return null;
    $stmt = db()->prepare('SELECT id, name, email, role, status, last_login_at, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }
    return $row;
}

function login_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function login_rate_limited(string $email, int $maxAttempts = 8, int $windowMinutes = 15): bool
{
    $email = strtolower(trim($email));
    $ip = login_client_ip();
    try {
        $stmt = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= (NOW() - INTERVAL {$windowMinutes} MINUTE) AND (email = ? OR ip_address = ?)");
        $stmt->execute([$email, $ip]);
        return (int)$stmt->fetchColumn() >= $maxAttempts;
    } catch (Throwable) {
        return false;
    }
}

function record_login_failure(string $email): void
{
    try {
        $pdo = db();
        $pdo->prepare('INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())')
            ->execute([strtolower(trim($email)), login_client_ip()]);
        // Opportunistic cleanup keeps the table small without a cron job.
        if (random_int(1, 20) === 1) {
            $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
        }
    } catch (Throwable) {
    }
}

function clear_login_failures(string $email): void
{
    try {
        db()->prepare('DELETE FROM login_attempts WHERE email = ? OR ip_address = ?')
            ->execute([strtolower(trim($email)), login_client_ip()]);
    } catch (Throwable) {
    }
}

function login_user(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    if (login_rate_limited($email)) return false;

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        record_login_failure($email);
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    clear_login_failures($email);

    if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE users SET password_hash = ?, last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$newHash, (int)$user['id']]);
    } else {
        $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([(int)$user['id']]);
    }
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('warning', 'Please log in to continue.');
        redirect('/login');
    }
    return $u;
}

function require_role(string $role): array
{
    $u = require_login();
    if ($u['role'] !== $role) {
        redirect($u['role'] === 'admin' ? '/admin/' : '/organizer/');
    }
    return $u;
}

function organizer_profile_for_user(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM organizers WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function require_organizer_profile(bool $allowIncomplete = false): array
{
    $u = require_role('organizer');
    $profile = organizer_profile_for_user((int)$u['id']);
    if (!$profile) {
        // A profile row should exist after signup. Repair gracefully if it does not.
        $stmt = db()->prepare('INSERT INTO organizers (user_id, organization_name, contact_person, email, slug, is_profile_complete, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())');
        $slug = unique_slug('organizers', $u['name']);
        $stmt->execute([(int)$u['id'], $u['name'], $u['name'], $u['email'], $slug]);
        $profile = organizer_profile_for_user((int)$u['id']);
    }
    if (!$allowIncomplete && empty($profile['is_profile_complete'])) {
        flash('info', 'Complete your organizer profile before using the dashboard.');
        redirect('/organizer/profile?setup=1');
    }
    return $profile;
}

function dashboard_home_for_user(array $u): string
{
    if ($u['role'] === 'admin') {
        return '/admin/';
    }

    if ($u['role'] === 'organizer') {

        $profile = organizer_profile_for_user(
            (int)$u['id']
        );

        if (
            !$profile ||
            empty($profile['is_profile_complete'])
        ) {
            return '/organizer/profile?setup=1';
        }

        return '/organizer/';
    }

    return '/login';
}