<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string) getq('token'));

$error         = '';
$success       = false;
$resetRow      = null;
$organizerName = '';
$lockedEmail   = '';

/* ==========================================================================
   VALIDATE TOKEN
   ========================================================================== */
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Invalid reset link. Please ask the administrator to send you a new link.';
} else {
    $tokenHash = hash('sha256', $token);

    $stmt = db()->prepare(
        "SELECT pr.*, u.email AS user_email, u.id AS user_id, o.organization_name
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         LEFT  JOIN organizers o ON o.id = pr.organizer_id
         WHERE pr.token_hash = ?
         LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        $error = 'This reset link is invalid. Please contact the administrator.';
    } elseif ((int) $row['used'] === 1) {
        $error = 'This reset link has already been used. Please contact the administrator for a new one.';
    } elseif ((int) $row['invalidated'] === 1) {
        $error = 'This reset link was replaced by a newer one. Please use the most recent link from the administrator.';
} elseif (strtotime((string) $row['expires_at']) <= time()) {
    $error = 'This reset link has expired (links are valid for 24 hours). Please contact the administrator for a new link.';
} else {
        $resetRow      = $row;
        $organizerName = (string) ($row['organization_name'] ?? '');
        $lockedEmail   = (string) $row['user_email'];
    }
}

/* ==========================================================================
   HANDLE NEW PASSWORD
   ========================================================================== */
if ($resetRow !== null && is_post()) {
    verify_csrf();

    $newPassword     = isset($_POST['new_password'])     && is_string($_POST['new_password'])
                     ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
                     ? $_POST['confirm_password'] : '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Please fill in both password fields.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must contain at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } else {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $pdo->prepare(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?"
            )->execute([$newHash, (int) $resetRow['user_id']]);

            $pdo->prepare(
                "UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = ?"
            )->execute([(int) $resetRow['id']]);

            $pdo->prepare(
                "UPDATE password_resets
                 SET invalidated = 1
                 WHERE user_id = ?
                   AND id <> ?
                   AND used = 0
                   AND invalidated = 0"
            )->execute([(int) $resetRow['user_id'], (int) $resetRow['id']]);

            $pdo->commit();

            $success = true;
            $resetRow = null;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Something went wrong while saving your password. Please try again.';
        }
    }
}

$seo = page_seo([
    'title'       => 'Reset Password',
    'description' => 'Set a new password for your organizer account.',
    'canonical'   => url('/reset-password'),
    'robots'      => 'noindex,nofollow',
]);

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/login')) ?>">Login</a></li>
        <li class="breadcrumb-item active" aria-current="page">Reset Password</li>
      </ol>
    </nav>

    <div class="section-kicker">Password Reset</div>
    <h1 class="display-5 mt-2">Set a New Password</h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      Enter your new password below. Once saved, your old password will stop working.
    </p>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-7">

        <?php if ($success): ?>

          <div class="form-section text-center reveal">
            <div style="font-size:3rem;color:var(--color-success);">
              <i class="bi bi-check-circle-fill"></i>
            </div>
            <h2 class="h3 fw-bold mt-3 mb-2">Password Changed</h2>
            <p class="text-secondary mb-4">
              Your new password has been saved. You can now log in with it.
            </p>
            <a href="<?= e(url('/login')) ?>" class="btn btn-action">
              <i class="bi bi-box-arrow-in-right me-1"></i>
              Go to Login
            </a>
          </div>

        <?php elseif ($error !== ''): ?>

          <div class="form-section text-center reveal">
            <div style="font-size:3rem;color:var(--color-error);">
              <i class="bi bi-exclamation-circle-fill"></i>
            </div>
            <h2 class="h4 fw-bold mt-3 mb-2">Cannot Use This Link</h2>
            <p class="text-secondary mb-4"><?= e($error) ?></p>
            <a href="<?= e(url('/forgot-password')) ?>" class="btn btn-outline-dark">
              <i class="bi bi-key me-1"></i> Request a New Link
            </a>
          </div>

        <?php else: ?>

          <div class="form-section reveal">

            <div class="alert alert-info small mb-4">
              <i class="bi bi-info-circle me-1"></i>
              Link expires at
              <strong><?= e(date('j M Y, g:i A', strtotime((string) $resetRow['expires_at']))) ?></strong>.
            </div>

            <?php if ($organizerName !== ''): ?>
              <div class="mb-4">
                <div class="small text-secondary">Organization</div>
                <div class="fw-semibold"><?= e($organizerName) ?></div>
              </div>
            <?php endif; ?>

            <form method="post" autocomplete="off" class="row g-4">
              <?= csrf_field() ?>

              <div class="col-12">
                <label class="form-label">Registered Email (locked)</label>
                <input type="email"
                       class="form-control"
                       value="<?= e($lockedEmail) ?>"
                       readonly
                       disabled>
                <div class="form-text">You cannot change this email.</div>
              </div>

              <div class="col-12">
                <label class="form-label" for="newPassword">
                  New Password <span class="text-danger">*</span>
                </label>
                <input id="newPassword"
                       type="password"
                       name="new_password"
                       class="form-control"
                       minlength="8"
                       autocomplete="new-password"
                       required>
                <div class="form-text">Minimum 8 characters.</div>
              </div>

              <div class="col-12">
                <label class="form-label" for="confirmPassword">
                  Confirm New Password <span class="text-danger">*</span>
                </label>
                <input id="confirmPassword"
                       type="password"
                       name="confirm_password"
                       class="form-control"
                       minlength="8"
                       autocomplete="new-password"
                       required>
              </div>

              <div class="col-12 d-flex justify-content-between align-items-center border-top pt-4 flex-wrap gap-2">
                <a href="<?= e(url('/login')) ?>" class="btn btn-outline-secondary">
                  Cancel
                </a>
                <button type="submit" class="btn btn-action px-4">
                  <i class="bi bi-shield-lock me-1"></i>
                  Save New Password
                </button>
              </div>

            </form>

          </div>

        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>