<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   ALREADY LOGGED IN → REDIRECT
   ========================================================================== */
if ($u = current_user()) {
    redirect(dashboard_home_for_user($u));
}

/* ==========================================================================
   SIGNUP HANDLING
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    /* --------------------------------------------------------------
       Anti-spam honeypot
       -------------------------------------------------------------- */
    if (public_form_honeypot_triggered()) {
        flash(
            'success',
            'Your registration request has been received.'
        );

        redirect('/organizer-signup');
    }

    /* --------------------------------------------------------------
       Rate limit:
       Maximum 3 organizer signup attempts per 60 minutes
       from the same IP address.
       -------------------------------------------------------------- */
    if (public_form_rate_limited('organizer_signup', 3, 60)) {
        flash(
            'danger',
            'Too many registration attempts. Please wait about 1 hour and try again.'
        );

        redirect('/organizer-signup');
    }

    /* Record this signup attempt */
    record_public_form_attempt('organizer_signup');

    $name     = post('name');
    $email    = strtolower(post('email'));
    $password = post('password');
    $confirm  = post('confirm_password');

    try {
        if (mb_strlen($name) < 2) {
            throw new RuntimeException('Please enter your name.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }

        if ($password !== $confirm) {
            throw new RuntimeException('Passwords do not match.');
        }

        $pdo = db();
        $pdo->beginTransaction();

        /* Check existing */
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            throw new RuntimeException('An account already exists with this email.');
        }

        /* Insert user */
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, role, status, created_at, updated_at)
             VALUES (?, ?, ?, 'organizer', 'active', NOW(), NOW())"
        );
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $uid = (int) $pdo->lastInsertId();

        /* Insert organizer profile */
        $slug = unique_slug('organizers', $name);
        $stmt = $pdo->prepare(
            'INSERT INTO organizers (user_id, organization_name, contact_person, email, slug, is_profile_complete, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())'
        );
        $stmt->execute([$uid, $name, $name, $email, $slug]);

        $pdo->commit();

        /* Auto-login */
        session_regenerate_id(true);
        $_SESSION['user_id'] = $uid;

        audit_log('ORGANIZER_SIGNUP', 'Organizer account created', null, (int) $pdo->lastInsertId());

        flash('success', 'Account created. Complete your organizer profile to continue.');
        redirect('/organizer/profile?setup=1');

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('danger', $e->getMessage());
    }
}

/* ==========================================================================
   SEO
   ========================================================================== */
$seo = page_seo([
    'title'       => 'Organizer Signup',
    'description' => 'Create an organizer account to submit and manage international conferences.',
    'canonical'   => url('/organizer-signup'),
    'robots'      => 'noindex,follow',
]);

require __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     SIGNUP
     ========================================================================== -->
<section class="auth-wrap py-5">
  <div class="container">
    <div class="row g-0 auth-split mx-auto">

      <!-- ==================== LEFT: BRAND PANEL ==================== -->
      <div class="col-lg-5 auth-brand-panel">
        <div class="p-5 h-100 d-flex flex-column justify-content-center position-relative">

          <div class="mb-4">
            <img
              src="<?= e(url('/assets/images/company-logo.png')) ?>"
              alt="<?= e(APP_NAME) ?>"
              class="auth-brand-logo">
          </div>

          <h1 class="h3 fw-bold mb-3 text-white">
            Become an organizer.
          </h1>

          <p class="mb-4" style="color:rgba(255,255,255,0.80);line-height:1.65;">
            Create your organizer account, submit conferences,
            and reach a global audience of researchers and professionals.
          </p>

          <div class="d-flex flex-column gap-3 mt-3">
            <div class="d-flex align-items-center gap-3" style="color:rgba(255,255,255,0.85);">
              <i class="bi bi-check-circle-fill" style="color:var(--color-highlight);font-size:1.1rem;"></i>
              <span class="small">Free to join</span>
            </div>
            <div class="d-flex align-items-center gap-3" style="color:rgba(255,255,255,0.85);">
              <i class="bi bi-check-circle-fill" style="color:var(--color-highlight);font-size:1.1rem;"></i>
              <span class="small">Admin-reviewed listings</span>
            </div>
            <div class="d-flex align-items-center gap-3" style="color:rgba(255,255,255,0.85);">
              <i class="bi bi-check-circle-fill" style="color:var(--color-highlight);font-size:1.1rem;"></i>
              <span class="small">Global conference visibility</span>
            </div>
            <div class="d-flex align-items-center gap-3" style="color:rgba(255,255,255,0.85);">
              <i class="bi bi-check-circle-fill" style="color:var(--color-highlight);font-size:1.1rem;"></i>
              <span class="small">Dedicated organizer dashboard</span>
            </div>
          </div>

        </div>
      </div>

      <!-- ==================== RIGHT: FORM PANEL ==================== -->
      <div class="col-lg-7">
        <div class="auth-form-panel p-5">

          <div class="mb-5">
            <div class="section-kicker">Organizer registration</div>
            <h2 class="h3 fw-bold mt-2 mb-2">Create your account</h2>
            <p class="text-secondary small mb-0">
              After signup, complete your organization profile before accessing the dashboard.
            </p>
          </div>

<form method="post" class="row g-4" autocomplete="on">

    <?= csrf_field() ?>

    <?= public_form_honeypot_field() ?>

            <!-- Name -->
            <div class="col-12">
              <label class="form-label" for="signupName">
                Your Name <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white">
                  <i class="bi bi-person text-secondary"></i>
                </span>
                <input
                  id="signupName"
                  name="name"
                  class="form-control form-control-lg"
                  placeholder="Your full name"
                  autocomplete="name"
                  value="<?= e(post('name')) ?>"
                  required
                  autofocus>
              </div>
            </div>

            <!-- Email -->
            <div class="col-12">
              <label class="form-label" for="signupEmail">
                Email Address <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white">
                  <i class="bi bi-envelope text-secondary"></i>
                </span>
                <input
                  id="signupEmail"
                  type="email"
                  name="email"
                  class="form-control form-control-lg"
                  placeholder="you@example.com"
                  autocomplete="email"
                  value="<?= e(post('email')) ?>"
                  required>
              </div>
            </div>

            <!-- Password -->
            <div class="col-md-6">
              <label class="form-label" for="signupPassword">
                Password <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white">
                  <i class="bi bi-lock text-secondary"></i>
                </span>
                <input
                  id="signupPassword"
                  type="password"
                  name="password"
                  class="form-control form-control-lg"
                  placeholder="At least 8 characters"
                  minlength="8"
                  autocomplete="new-password"
                  required>
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="col-md-6">
              <label class="form-label" for="signupConfirm">
                Confirm Password <span class="text-danger">*</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white">
                  <i class="bi bi-lock-fill text-secondary"></i>
                </span>
                <input
                  id="signupConfirm"
                  type="password"
                  name="confirm_password"
                  class="form-control form-control-lg"
                  placeholder="Re-enter password"
                  minlength="8"
                  autocomplete="new-password"
                  required>
              </div>
              <div class="form-text">Use at least 8 characters.</div>
            </div>

            <!-- Submit -->
            <div class="col-12">
              <button type="submit" class="btn btn-action btn-lg w-100">
                <i class="bi bi-person-plus me-1"></i>
                Create Account
              </button>
            </div>

            <div class="col-12 text-center">
              <p class="small text-secondary mb-0">
                Already registered?
                <a href="<?= e(url('/login')) ?>" class="fw-semibold">
                  Sign in
                </a>
              </p>
            </div>

          </form>

          <hr class="my-5">

          <div class="text-center small text-secondary">
            <i class="bi bi-shield-check me-1 text-brand"></i>
            By signing up, you agree to our
            <a href="<?= e(url('/terms-of-service')) ?>">Terms</a>
            and
            <a href="<?= e(url('/privacy-policy')) ?>">Privacy Policy</a>.
          </div>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- Signup-specific styles -->
<style>
.auth-split {
  max-width: 1080px;
  background: var(--color-white);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-2xl);
  overflow: hidden;
  box-shadow: var(--shadow-2xl);
}

.auth-brand-panel {
  background:
    radial-gradient(circle at 80% 20%, rgba(37, 99, 235, 0.35), transparent 55%),
    radial-gradient(circle at 20% 80%, rgba(249, 115, 22, 0.20), transparent 55%),
    linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 60%, var(--color-accent-dark) 100%);
  position: relative;
  overflow: hidden;
}

.auth-brand-panel::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
  background-size: 28px 28px;
  pointer-events: none;
}

.auth-form-panel {
  background: var(--color-white);
}

@media (max-width: 991.98px) {
  .auth-brand-panel {
    min-height: 240px;
  }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>