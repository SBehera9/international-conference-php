<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('/');
}

verify_csrf();

/* --------------------------------------------------------------
   Anti-spam honeypot
   -------------------------------------------------------------- */
if (public_form_honeypot_triggered()) {
    $result = 'success';
} elseif (public_form_rate_limited('newsletter', 10, 60)) {
    $result = 'rate_limited';
} else {
    /* Record this newsletter submission attempt */
    record_public_form_attempt('newsletter');

    $email  = strtolower(trim(post('subscriber_email')));
    $result = 'error';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = 'invalid';
    } else {
        try {
            $checkStmt = db()->prepare(
                "SELECT id
                 FROM subscriber_emails
                 WHERE LOWER(email) = LOWER(?)
                 LIMIT 1"
            );
            $checkStmt->execute([$email]);

            if ($checkStmt->fetchColumn()) {
                $result = 'exists';
            } else {
                $stmt = db()->prepare(
                    "INSERT INTO subscriber_emails (email, status, created_at)
                     VALUES (?, 'active', NOW())"
                );
                $stmt->execute([$email]);

                $result = 'success';
            }
        } catch (Throwable $e) {
            $result = 'error';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Return to the same page and show the result beside the Newsletter.
|--------------------------------------------------------------------------
*/
$referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
$target  = url('/');

if ($referer !== '') {
    $refererParts = parse_url($referer);
    $baseParts    = parse_url(BASE_URL);

    $sameHost =
        isset($refererParts['host'], $baseParts['host']) &&
        strcasecmp(
            (string) $refererParts['host'],
            (string) $baseParts['host']
        ) === 0;

    $samePort =
        (!isset($refererParts['port']) && !isset($baseParts['port'])) ||
        ((int) ($refererParts['port'] ?? 0) === (int) ($baseParts['port'] ?? 0));

    if ($sameHost && $samePort) {
        $target = preg_replace('/#.*$/', '', $referer) ?: $referer;
    }
}

$parts = parse_url($target);
$query = [];

if (!empty($parts['query'])) {
    parse_str((string) $parts['query'], $query);
}

$query['newsletter'] = $result;

$scheme      = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
$host        = $parts['host'] ?? '';
$port        = isset($parts['port']) ? ':' . $parts['port'] : '';
$path        = $parts['path'] ?? '/';
$queryString = http_build_query($query);

if ($host !== '') {
    $target =
        $scheme .
        $host .
        $port .
        $path .
        ($queryString !== '' ? '?' . $queryString : '');
} else {
    $target =
        $path .
        ($queryString !== '' ? '?' . $queryString : '');
}

header('Location: ' . $target . '#newsletter');
exit;