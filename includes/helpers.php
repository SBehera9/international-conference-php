<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    if ($path === '' || $path === '/') return rtrim(BASE_URL, '/') . '/';
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function absolute_media_url(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') return '';
    if (preg_match('#^https?://#i', $value)) return $value;
    return url($value);
}

function redirect(string $path): never
{
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . url($path));
    }
    exit;
}

function slugify(string $text): string
{
    $text = trim($text);
    if ($text === '') return 'item';
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item';
}

function unique_slug(string $table, string $source, ?int $ignoreId = null): string
{
    $allowed = ['conferences', 'organizers', 'categories', 'blog_posts'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid slug table');
    }
    $base = slugify($source);
    $slug = $base;
    $n = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?" . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $params = [$slug];
        if ($ignoreId) $params[] = $ignoreId;
        $stmt->execute($params);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . $n++;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid or expired request token. Please go back and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function getq(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function normalize_url(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    if (!preg_match('#^https?://#i', $value)) $value = 'https://' . $value;
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
}

function format_date(?string $date): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date('j F Y', $ts) : $date;
}

function conference_live_status(array $conference): string
{
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    try {
        $start = new DateTimeImmutable((string) $conference['start_date']);
        $end   = new DateTimeImmutable((string) $conference['end_date']);
    } catch (Throwable) {
        return 'Upcoming';
    }
    if ($today > $end) return 'Completed';
    if ($today >= $start && $today <= $end) return 'Ongoing';
    return 'Upcoming';
}

function current_page_url(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $uri = strtok($uri, '?') ?: '/';
    return rtrim(BASE_URL, '/') . '/' . ltrim($uri, '/');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* =========================================================================
   PUBLIC FORM ANTI-SPAM / RATE LIMITING
   ========================================================================= */

/**
 * Get the visitor IP address used for public-form rate limiting.
 *
 * We intentionally use REMOTE_ADDR rather than trusting arbitrary
 * X-Forwarded-For headers supplied by visitors.
 */
function public_form_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    return substr($ip, 0, 45);
}


/**
 * Validate/normalize the internal form key.
 */
function public_form_key(string $formKey): string
{
    $formKey = strtolower(trim($formKey));

    $formKey = preg_replace(
        '/[^a-z0-9_-]/',
        '',
        $formKey
    ) ?? '';

    return substr($formKey, 0, 50);
}


/**
 * Check whether this IP has reached the submission limit
 * for the specified public form.
 */
function public_form_rate_limited(
    string $formKey,
    int $maxAttempts,
    int $windowMinutes
): bool {

    $formKey = public_form_key($formKey);

    if (
        $formKey === ''
        || $maxAttempts < 1
        || $windowMinutes < 1
    ) {
        return false;
    }

    $ip = public_form_client_ip();

    $cutoff = gmdate(
        'Y-m-d H:i:s',
        time() - ($windowMinutes * 60)
    );

    try {

        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM public_form_attempts
             WHERE form_key = ?
               AND ip_address = ?
               AND attempted_at >= ?'
        );

        $stmt->execute([
            $formKey,
            $ip,
            $cutoff,
        ]);

        return (int) $stmt->fetchColumn() >= $maxAttempts;

    } catch (Throwable) {

        /*
         * Fail open if the anti-spam table has a temporary problem.
         * This prevents the whole website form from breaking.
         */
        return false;
    }
}


/**
 * Record one public-form submission attempt.
 */
function record_public_form_attempt(
    string $formKey
): void {

    $formKey = public_form_key($formKey);

    if ($formKey === '') {
        return;
    }

    $ip = public_form_client_ip();

    try {

        $pdo = db();

        $stmt = $pdo->prepare(
            'INSERT INTO public_form_attempts
                (form_key, ip_address, attempted_at)
             VALUES
                (?, ?, NOW())'
        );

        $stmt->execute([
            $formKey,
            $ip,
        ]);


        /*
         * Occasionally delete old records automatically
         * so this table does not grow forever.
         */
        if (random_int(1, 30) === 1) {

            $pdo->exec(
                'DELETE FROM public_form_attempts
                 WHERE attempted_at < (NOW() - INTERVAL 7 DAY)'
            );

        }

    } catch (Throwable) {

        /*
         * Anti-spam logging must never crash a public form.
         */

    }
}


/**
 * Honeypot field for public forms.
 *
 * Normal users cannot see or interact with this field.
 * Many simple bots automatically fill it.
 */
function public_form_honeypot_field(): string
{
    return '
        <div
            aria-hidden="true"
            style="
                position:absolute !important;
                left:-9999px !important;
                width:1px !important;
                height:1px !important;
                overflow:hidden !important;
            "
        >
            <label for="_hp_company">
                Leave this field empty
            </label>

            <input
                type="text"
                name="_hp_company"
                id="_hp_company"
                value=""
                tabindex="-1"
                autocomplete="off"
            >
        </div>
    ';
}


/**
 * Returns true when the hidden honeypot field was filled.
 */
function public_form_honeypot_triggered(): bool
{
    $value = $_POST['_hp_company'] ?? '';

    return is_string($value)
        && trim($value) !== '';
}

function paginate(int $total, int $perPage = 18): array
{
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min($page, $pages);
    return [
        'page'     => $page,
        'pages'    => $pages,
        'per_page' => $perPage,
        'offset'   => ($page - 1) * $perPage,
        'total'    => $total,
    ];
}

function upload_image(string $field, string $folder = 'organizers'): ?string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }
if (($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
    throw new RuntimeException('Image is too large. Maximum size is 500 KB.');
}
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Unsupported image type. Use JPG, PNG, WEBP or GIF.');
    }
    $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?: 'misc';
    $dir    = UPLOAD_PATH . '/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload directory is not writable.');
    }
    $name   = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to store uploaded image.');
    }
    return '/uploads/' . $folder . '/' . $name;
}

/* audit_log() REMOVED — see instructions above */

function setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $cache[$key] = ($value !== false ? (string) $value : $default);
    } catch (Throwable) {
        return $default;
    }
}

function content(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = db()->prepare('SELECT content_value FROM site_content WHERE content_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $cache[$key] = ($value !== false ? (string) $value : $default);
    } catch (Throwable) {
        return $default;
    }
}

function excerpt(string $text, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    if (mb_strlen($text) <= $length) return $text;
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}


/* ==========================================================================
   AUDIT LOG — DISABLED (no-op stub)
   ========================================================================== */
function audit_log(
    string $action,
    string $details = '',
    ?int $conferenceId = null,
    ?int $organizerId = null
): void {
    /* Audit logging disabled */
}