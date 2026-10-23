<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

$adminId = (int) $user['id'];

/* ==========================================================================
   ACTIONS
   ========================================================================== */
if (is_post()) {
    verify_csrf();
    $action = post('action');

    /* ==================== UPDATE PROFILE ==================== */
    if ($action === 'update_profile') {
        $name  = post('name');
        $email = strtolower(post('email'));

        if ($name === '') {
            flash('danger', 'Admin name is required.');
            redirect('/admin/profile');
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
            flash('danger', 'Name must be between 2 and 150 characters.');
            redirect('/admin/profile');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Enter a valid email address.');
            redirect('/admin/profile');
        }

        /* Duplicate check */
        $check = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $check->execute([$email, $adminId]);

        if ($check->fetch()) {
            flash('danger', 'This email address is already used by another account.');
            redirect('/admin/profile');
        }

        $stmt = db()->prepare(
            'UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$name, $email, $adminId]);

        audit_log('ADMIN_PROFILE_UPDATED', 'Admin updated profile information.');
        flash('success', 'Profile information updated successfully.');
        redirect('/admin/profile');
    }

    /* ==================== CHANGE PASSWORD ==================== */
    if ($action === 'change_password') {
        $currentPassword = isset($_POST['current_password']) && is_string($_POST['current_password'])
            ? $_POST['current_password'] : '';
        $newPassword     = isset($_POST['new_password']) && is_string($_POST['new_password'])
            ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
            ? $_POST['confirm_password'] : '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            flash('danger', 'Complete all password fields.');
            redirect('/admin/profile');
        }

        if (strlen($newPassword) < 6) {
            flash('danger', 'New password must contain at least 8 characters.');
            redirect('/admin/profile');
        }

        if ($newPassword !== $confirmPassword) {
            flash('danger', 'New password and confirm password do not match.');
            redirect('/admin/profile');
        }

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId]);
        $passwordHash = $stmt->fetchColumn();

        if (!$passwordHash || !password_verify($currentPassword, (string) $passwordHash)) {
            flash('danger', 'Current password is incorrect.');
            redirect('/admin/profile');
        }

        if (password_verify($newPassword, (string) $passwordHash)) {
            flash('warning', 'Your new password must be different from your current password.');
            redirect('/admin/profile');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newHash, $adminId]);

        session_regenerate_id(true);

        audit_log('ADMIN_PASSWORD_CHANGED', 'Admin changed account password.');
        flash('success', 'Password changed successfully.');
        redirect('/admin/profile');
    }
}

/* ==========================================================================
   LOAD ADMIN
   ========================================================================== */
$stmt = db()->prepare(
    'SELECT id, name, email, role, status, created_at, last_login_at
     FROM users
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    logout_user();
    redirect('/login');
}

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Profile';
$dashboardRole  = 'admin';

require dirname(__DIR__) . '/includes/dashboard-header.php';
?>

<!-- ==========================================================================
     INFO BANNER
     ========================================================================== -->
<div class="admin-welcome-banner mb-4 reveal">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="avatar-circle" style="width:56px;height:56px;font-size:20px;background:rgba(255,255,255,0.15);color:#fff;">
        <?= e(strtoupper(mb_substr((string) $admin['name'], 0, 1))) ?>
      </div>
      <div>
        <div class="section-kicker mb-1" style="color:var(--color-highlight);">
          Account Settings
        </div>
        <h2 class="h4 fw-bold mb-1 text-white">
          <?= e($admin['name']) ?>
        </h2>
        <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
          <i class="bi bi-shield-lock me-1"></i>
          Administrator account
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================================================
     PROFILE + ACCOUNT INFO
     ========================================================================== -->
<div class="row g-4 mb-4">

  <!-- Profile Info -->
  <div class="col-xl-7">
    <div class="form-section h-100 reveal">

      <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
        <i class="bi bi-person me-2 text-brand"></i>
        Profile Information
      </h3>

      <form method="post" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="col-12">
          <label class="form-label">
            Admin Name <span class="text-danger">*</span>
          </label>
          <input type="text"
                 name="name"
                 class="form-control"
                 value="<?= e($admin['name']) ?>"
                 maxlength="150"
                 required>
        </div>

        <div class="col-12">
          <label class="form-label">
            Email Address <span class="text-danger">*</span>
          </label>
          <input type="email"
                 name="email"
                 class="form-control"
                 value="<?= e($admin['email']) ?>"
                 maxlength="190"
                 required>
          <div class="form-text">
            This email is used for login.
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end border-top pt-4">
          <button type="submit" class="btn btn-action px-4">
            <i class="bi bi-check-circle me-1"></i>
            Save Changes
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- Account Info -->
  <div class="col-xl-5">
    <div class="form-section h-100 reveal">

      <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
        <i class="bi bi-info-circle me-2 text-brand"></i>
        Account Information
      </h3>

      <div class="d-flex flex-column gap-3">

        <div class="d-flex justify-content-between align-items-center">
          <span class="small text-secondary">
            <i class="bi bi-shield-lock me-1"></i>Role
          </span>
          <span class="badge text-bg-primary">
            Administrator
          </span>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <span class="small text-secondary">
            <i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;color:var(--color-success);"></i>Status
          </span>
          <span class="badge text-bg-success">
            <?= e(ucfirst($admin['status'])) ?>
          </span>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <span class="small text-secondary">
            <i class="bi bi-calendar3 me-1"></i>Account Created
          </span>
          <span class="fw-semibold small">
            <?= e(date('j F Y', strtotime((string) $admin['created_at']))) ?>
          </span>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <span class="small text-secondary">
            <i class="bi bi-clock-history me-1"></i>Last Login
          </span>
          <span class="fw-semibold small">
            <?php if (!empty($admin['last_login_at'])): ?>
              <?= e(date('j F Y, g:i A', strtotime((string) $admin['last_login_at']))) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </span>
        </div>

      </div>
    </div>
  </div>

</div>

<!-- ==========================================================================
     CHANGE PASSWORD
     ========================================================================== -->
<div class="form-section reveal">

  <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
    <i class="bi bi-key me-2 text-brand"></i>
    Change Password
  </h3>

  <p class="small text-secondary mb-4">
    For security, you must enter your current password before creating a new one.
    Session will be regenerated after successful change.
  </p>

  <form method="post" class="row g-3" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">

    <div class="col-lg-4">
      <label class="form-label">
        Current Password <span class="text-danger">*</span>
      </label>
      <input type="password"
             name="current_password"
             class="form-control"
             autocomplete="current-password"
             required>
    </div>

    <div class="col-lg-4">
      <label class="form-label">
        New Password <span class="text-danger">*</span>
      </label>
      <input type="password"
             name="new_password"
             class="form-control"
             minlength="8"
             autocomplete="new-password"
             required>
      <div class="form-text">Minimum 8 characters.</div>
    </div>

    <div class="col-lg-4">
      <label class="form-label">
        Confirm New Password <span class="text-danger">*</span>
      </label>
      <input type="password"
             name="confirm_password"
             class="form-control"
             minlength="8"
             autocomplete="new-password"
             required>
    </div>

    <div class="col-12 d-flex justify-content-end border-top pt-4">
      <button type="submit" class="btn btn-dark px-4">
        <i class="bi bi-shield-lock me-1"></i>
        Update Password
      </button>
    </div>

  </form>
</div>

<!-- Admin banner style -->
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