<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   GENERATE RESET LINK
   ========================================================================== */
if (is_post()) {
    verify_csrf();
    $action = post('action');
    $email  = strtolower(trim(post('email')));

    if ($action === 'generate' && $email !== '') {

        $stmt = db()->prepare(
            "SELECT u.id AS user_id, u.email,
                    o.id AS organizer_id, o.organization_name
             FROM users u
             INNER JOIN organizers o ON o.user_id = u.id
             WHERE LOWER(u.email) = LOWER(?)
               AND u.role = 'organizer'
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row) {
            flash('danger', 'No organizer found with that email address.');
            redirect('/admin/password-reset');
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            /* Invalidate all previous unused links for this organizer */
            $pdo->prepare(
                "UPDATE password_resets
                 SET invalidated = 1
                 WHERE organizer_id = ?
                   AND used = 0
                   AND invalidated = 0"
            )->execute([(int) $row['organizer_id']]);

            /* Generate new token */
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash  = hash('sha256', $plainToken);
            $expiresAt  = date('Y-m-d H:i:s', time() + 86400);

            $pdo->prepare(
                "INSERT INTO password_resets
                    (organizer_id, user_id, email, token_hash, expires_at, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            )->execute([
                (int) $row['organizer_id'],
                (int) $row['user_id'],
                $row['email'],
                $tokenHash,
                $expiresAt,
                (int) $user['id'],
            ]);

            $pdo->commit();

            /* Keep the plain token in session just long enough to display once */
            $_SESSION['last_reset_token']   = $plainToken;
            $_SESSION['last_reset_email']   = $row['email'];
            $_SESSION['last_reset_expires'] = time() + 300;

            audit_log('ADMIN_PASSWORD_RESET_LINK', 'Reset link generated for ' . $row['email']);
            flash('success', 'Reset link generated. Copy it and send it to the organizer.');
            redirect('/admin/password-reset?email=' . rawurlencode($row['email']) . '&show=1');

        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash('danger', 'Unable to generate reset link. Please try again.');
            redirect('/admin/password-reset');
        }
    }
}

/* ==========================================================================
   LOOK UP ORGANIZER (via ?email=...)
   ========================================================================== */
$emailQuery = strtolower(trim((string) getq('email')));
$organizer  = null;

if ($emailQuery !== '') {
    $stmt = db()->prepare(
        "SELECT
            o.id AS organizer_id,
            o.organization_name,
            o.contact_person,
            o.email AS contact_email,
            o.phone,
            o.website,
            o.country,
            o.city,
            o.logo,
            o.is_verified,
            o.is_suspended,
            o.is_profile_complete,
            u.id AS user_id,
            u.name AS user_name,
            u.email AS user_email,
            u.status AS user_status,
            u.created_at AS user_created_at,
            u.last_login_at
         FROM organizers o
         INNER JOIN users u ON u.id = o.user_id
         WHERE LOWER(u.email) = LOWER(?)
           AND u.role = 'organizer'
         LIMIT 1"
    );
    $stmt->execute([$emailQuery]);
    $organizer = $stmt->fetch() ?: null;
}

/* ==========================================================================
   SHOW JUST-GENERATED TOKEN (once)
   ========================================================================== */
$showToken   = null;
$showExpires = null;
$showEmail   = null;

if (getq('show') === '1' && !empty($_SESSION['last_reset_token'])) {
    if (($_SESSION['last_reset_expires'] ?? 0) > time()) {
        $showToken   = $_SESSION['last_reset_token'];
        $showEmail   = $_SESSION['last_reset_email'] ?? '';
        $showExpires = $_SESSION['last_reset_expires'];
    }
    unset(
        $_SESSION['last_reset_token'],
        $_SESSION['last_reset_email'],
        $_SESSION['last_reset_expires']
    );
}

/* ==========================================================================
   RECENT LINKS HISTORY
   ========================================================================== */
$history = db()->query(
    "SELECT
        pr.*,
        u.email AS user_email,
        o.organization_name
     FROM password_resets pr
     LEFT JOIN users u ON u.id = pr.user_id
     LEFT JOIN organizers o ON o.id = pr.organizer_id
     ORDER BY pr.created_at DESC, pr.id DESC
     LIMIT 30"
)->fetchAll();

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Password Reset';
$dashboardRole  = 'admin';

require dirname(__DIR__) . '/includes/dashboard-header.php';
?>

<!-- ==========================================================================
     INFO BANNER
     ========================================================================== -->
<div class="admin-welcome-banner mb-4 reveal">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <div class="section-kicker mb-1" style="color:var(--color-highlight);">
        Account Recovery
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Organizer Password Reset
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Verify the organizer's email, generate a secure 24-hour link, and send it manually.
      </p>
    </div>
  </div>
</div>

<!-- ==========================================================================
     JUST-GENERATED LINK
     ========================================================================== -->
<?php if ($showToken !== null): ?>
  <?php $resetUrl = url('/reset-password/' . $showToken); ?>

  <div class="form-section reveal mb-4" id="generatedLinkBox">
    <h3 class="h6 fw-bold mb-3">
      <i class="bi bi-check-circle-fill text-success me-2"></i>
      Reset Link Generated Successfully
    </h3>

    <p class="small text-secondary mb-3">
      Copy this link and send it to <strong><?= e((string) $showEmail) ?></strong>.
      Valid for <strong>24 hours</strong>.
    </p>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <input type="text"
             id="generatedResetUrl"
             class="form-control"
             value="<?= e($resetUrl) ?>"
             readonly
             onclick="this.select();">

      <button type="button" class="btn btn-action" data-copy-url>
        <i class="bi bi-clipboard me-1"></i> Copy Link
      </button>
    </div>

    <div class="alert alert-warning small mb-0">
      <i class="bi bi-exclamation-triangle me-1"></i>
      This link is shown once for security. Copy it now.
      Refreshing this page will not show it again.
    </div>
  </div>
<?php endif; ?>

<!-- ==========================================================================
     LOOKUP + GENERATE
     ========================================================================== -->
<div class="form-section reveal mb-4">

  <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
    <i class="bi bi-search me-2 text-brand"></i>
    Look Up Organizer
  </h3>

  <form method="get" class="row g-3 align-items-end">
    <div class="col-md-9">
      <label class="form-label">Organizer Login Email</label>
      <input type="email"
             name="email"
             class="form-control"
             value="<?= e($emailQuery) ?>"
             placeholder="organizer@example.com"
             required>
      <div class="form-text">
        Type the exact email the organizer uses to log in.
      </div>
    </div>
    <div class="col-md-3">
      <button type="submit" class="btn btn-dark w-100">
        <i class="bi bi-search me-1"></i> Look Up
      </button>
    </div>
  </form>

  <?php if ($emailQuery !== '' && !$organizer): ?>
    <div class="alert alert-danger mt-4 mb-0">
      <i class="bi bi-x-circle me-1"></i>
      No organizer account found with that email address.
      Check the spelling and try again.
    </div>
  <?php endif; ?>

</div>

<!-- ==========================================================================
     ORGANIZER DETAILS
     ========================================================================== -->
<?php if ($organizer): ?>
  <div class="form-section reveal mb-4">

    <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
      <i class="bi bi-person-check me-2 text-brand"></i>
      Verify Organizer Details
    </h3>

    <div class="row g-4 align-items-center mb-4">

      <div class="col-md-2 text-center">
        <?php
        $logo = trim((string) ($organizer['logo'] ?? ''));
        if ($logo !== '' && !preg_match('#^https?://#i', $logo)) {
            $logo = url($logo);
        }
        ?>
        <?php if ($logo !== ''): ?>
          <img src="<?= e($logo) ?>"
               alt=""
               style="width:96px;height:96px;object-fit:contain;background:#fff;border:1px solid var(--color-border);border-radius:12px;padding:6px;">
        <?php else: ?>
          <div class="avatar-circle mx-auto" style="width:96px;height:96px;font-size:32px;">
            <i class="bi bi-building"></i>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-md-10">
        <h4 class="h5 fw-bold mb-1"><?= e((string) $organizer['organization_name']) ?></h4>
        <div class="small text-secondary mb-2">
          <i class="bi bi-envelope me-1"></i>
          <?= e((string) $organizer['user_email']) ?>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <?php if (!empty($organizer['is_verified'])): ?>
            <span class="badge text-bg-success">
              <i class="bi bi-patch-check-fill me-1"></i>Verified
            </span>
          <?php endif; ?>

          <?php if ($organizer['user_status'] === 'active' && empty($organizer['is_suspended'])): ?>
            <span class="badge text-bg-primary">Active</span>
          <?php else: ?>
            <span class="badge text-bg-danger">Suspended</span>
          <?php endif; ?>

          <?php if (!empty($organizer['is_profile_complete'])): ?>
            <span class="badge text-bg-light border">Profile Complete</span>
          <?php else: ?>
            <span class="badge text-bg-warning">Profile Incomplete</span>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <div class="row g-4 mb-4">
      <div class="col-md-6">
        <div class="small text-secondary">Contact Person</div>
        <div class="fw-semibold"><?= e((string) ($organizer['contact_person'] ?: '—')) ?></div>
      </div>
      <div class="col-md-6">
        <div class="small text-secondary">Contact Email</div>
        <div class="fw-semibold"><?= e((string) ($organizer['contact_email'] ?: '—')) ?></div>
      </div>
      <div class="col-md-6">
        <div class="small text-secondary">Phone</div>
        <div class="fw-semibold"><?= e((string) ($organizer['phone'] ?: '—')) ?></div>
      </div>
      <div class="col-md-6">
        <div class="small text-secondary">Location</div>
        <div class="fw-semibold">
          <?= e(trim(($organizer['city'] ?? '') . ', ' . ($organizer['country'] ?? ''), ', ') ?: '—') ?>
        </div>
      </div>
      <div class="col-md-6">
        <div class="small text-secondary">Registered On</div>
        <div class="fw-semibold">
          <?= e(date('j F Y', strtotime((string) $organizer['user_created_at']))) ?>
        </div>
      </div>
      <div class="col-md-6">
        <div class="small text-secondary">Last Login</div>
        <div class="fw-semibold">
          <?= !empty($organizer['last_login_at'])
              ? e(date('j F Y, g:i A', strtotime((string) $organizer['last_login_at'])))
              : '—' ?>
        </div>
      </div>
    </div>

    <div class="alert alert-warning small mb-4">
      <i class="bi bi-shield-check me-1"></i>
      <strong>Verify before generating:</strong>
      Confirm this organizer's identity matches the request you received by email.
      Once you generate a link, the previous unused links will be invalidated.
    </div>

    <form method="post" class="d-flex justify-content-end border-top pt-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="generate">
      <input type="hidden" name="email" value="<?= e((string) $organizer['user_email']) ?>">

      <button type="submit"
              class="btn btn-action px-4"
              data-confirm="Generate a new reset link for <?= e((string) $organizer['user_email']) ?>? Any existing unused links will be invalidated.">
        <i class="bi bi-key me-1"></i>
        Generate Reset Link
      </button>
    </form>

  </div>
<?php endif; ?>

<!-- ==========================================================================
     RECENT LINKS HISTORY
     ========================================================================== -->
<div class="table-card reveal">

  <div class="p-4 border-bottom">
    <h3 class="h5 fw-bold mb-1">Recent Reset Links</h3>
    <p class="small text-secondary mb-0">
      Last 30 generated links (any organizer).
    </p>
  </div>

  <?php if ($history): ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Organizer</th>
            <th>Email</th>
            <th>Generated</th>
            <th>Expires</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $row): ?>
            <?php
            $expired   = strtotime((string) $row['expires_at']) < time();
            $status    = 'Active';
            $badge     = 'text-bg-success';
            if ((int) $row['used'] === 1) {
                $status = 'Used';
                $badge  = 'text-bg-secondary';
            } elseif ((int) $row['invalidated'] === 1) {
                $status = 'Invalidated';
                $badge  = 'text-bg-dark';
            } elseif ($expired) {
                $status = 'Expired';
                $badge  = 'text-bg-warning';
            }
            ?>
            <tr>
              <td>
                <strong><?= e((string) ($row['organization_name'] ?: '—')) ?></strong>
              </td>
              <td><span class="small"><?= e((string) $row['email']) ?></span></td>
              <td class="small text-nowrap">
                <?= e(date('j M Y, g:i A', strtotime((string) $row['created_at']))) ?>
              </td>
              <td class="small text-nowrap">
                <?= e(date('j M Y, g:i A', strtotime((string) $row['expires_at']))) ?>
              </td>
              <td>
                <span class="badge <?= e($badge) ?>"><?= e($status) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state bg-white">
      <i class="bi bi-key"></i>
      <h3 class="h5 mt-3">No reset links yet</h3>
      <p class="text-secondary mb-0">Generated links will appear here.</p>
    </div>
  <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var copyBtn = document.querySelector('[data-copy-url]');
  var input   = document.getElementById('generatedResetUrl');

  if (copyBtn && input) {
    copyBtn.addEventListener('click', function () {
      input.select();
      input.setSelectionRange(0, 99999);

      function done(ok) {
        var old = copyBtn.innerHTML;
        copyBtn.innerHTML = ok
          ? '<i class="bi bi-check-circle me-1"></i>Copied!'
          : '<i class="bi bi-x-circle me-1"></i>Failed';
        setTimeout(function () { copyBtn.innerHTML = old; }, 1800);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value)
          .then(function () { done(true); })
          .catch(function () { done(false); });
      } else {
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        done(ok);
      }
    });
  }
});
</script>

<style>
.admin-welcome-banner {
  background:
    radial-gradient(circle at 90% 10%, rgba(37, 99, 235, 0.35), transparent 55%),
    radial-gradient(circle at 10% 90%, rgba(249, 115, 22, 0.20), transparent 55%),
    linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
  border-radius: var(--radius-xl);
  padding: 1.5rem 1.75rem;
  position: relative;
  overflow: hidden;
}

.admin-welcome-banner::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
  background-size: 28px 28px;
  pointer-events: none;
}

.admin-welcome-banner > * {
  position: relative;
  z-index: 1;
}
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>