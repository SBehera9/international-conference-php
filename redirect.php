<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$slug       = getq('slug');
$conference = find_conference_by_slug($slug);

/* --------------------------------------------------------------------------
   Bail out if conference not found or has no registration link
   -------------------------------------------------------------------------- */
if (!$conference || empty($conference['registration_link'])) {
    redirect('/conferences');
}

/* --------------------------------------------------------------------------
   Validate the target URL — prevents open redirect & javascript: injection
   -------------------------------------------------------------------------- */
$target = normalize_url((string) $conference['registration_link']);

if ($target === '') {
    redirect('/conferences');
}

/* --------------------------------------------------------------------------
   Count the click
   -------------------------------------------------------------------------- */
db()->prepare(
    'UPDATE conferences SET registration_clicks = registration_clicks + 1 WHERE id = ?'
)->execute([(int) $conference['id']]);

/* --------------------------------------------------------------------------
   Redirect
   -------------------------------------------------------------------------- */
header('Location: ' . $target, true, 302);
exit;