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
   LOGIN HANDLING
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    $email    = post('email');
    $password = post('password');

    if (login_rate_limited($email)) {
        flash('danger', 'Too many login attempts. Please try again after 15 minutes.');
    } elseif (login_user($email, $password)) {
        $u = current_user();
        audit_log('LOGIN', 'User logged in');
        redirect(dashboard_home_for_user($u));
    } else {
        flash('danger', 'Invalid email or password, or the account is suspended.');
    }
}

/* ==========================================================================
   SEO
   ========================================================================== */
$seo = page_seo([
    'title'       => 'Login',
    'description' => 'Login to your International Conference organizer or administrator account.',
    'canonical'   => url('/login'),
    'robots'      => 'noindex,nofollow',
]);

require __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     LOGIN
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
            Welcome back.
          </h1>

          <p class="mb-4" style="color:rgba(255,255,255,0.80);line-height:1.65;">
            One secure login for organizers and administrators.
            Your role opens the correct dashboard automatically.
          </p>

          <div class="d-flex flex-column gap-3 mt-3">
            <div class="d-flex align-items-center gap-3" style="color:rgba(255,255,255,0.85);">
              <i class="bi bi-shield-check" style="color:var(--color-highlight);font-size:1.2rem;"></i>
              <span class="small">Admin-reviewed platform</span>
            </div>
            <div class="d-flex align-items-center gap-3" style="color:rgba(255,255,255,0.85);">
              <i class="bi bi-globe2" style="color:var(--color-highlight);font-size:1.2rem;"></i>
              <span class="small">Global conference directory</span>
            </div>
            <div class="d-flex align-items-center gap-3" style="color:rgba(255,255,255,0.85);">
              <i class="bi bi-people" style="color:var(--color-highlight);font-size:1.2rem;"></i>
              <span class="small">Trusted organizer network</span>
            </div>
          </div>

        </div>
      </div>

      <!-- ==================== RIGHT: FORM PANEL ==================== -->
      <div class="col-lg-7">
        <div class="auth-form-panel p-5">

          <div class="mb-5">
            <div class="section-kicker">One secure login</div>
            <h2 class="h3 fw-bold mt-2 mb-2">Sign in to your account</h2>
            <p class="text-secondary small mb-0">
              Enter your credentials below to continue.
            </p>
          </div>

          <form method="post" class="row g-4" autocomplete="on">
            <?= csrf_field() ?>

            <div class="col-12">
              <label class="form-label" for="loginEmail">
                Email address
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white">
                  <i class="bi bi-envelope text-secondary"></i>
                </span>
                <input
                  id="loginEmail"
                  type="email"
                  name="email"
                  class="form-control form-control-lg"
                  placeholder="you@example.com"
                  autocomplete="email"
                  required
                  autofocus>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label" for="loginPassword">
                Password
              </label>
              <div class="input-group">
                <span class="input-group-text bg-white">
                  <i class="bi bi-lock text-secondary"></i>
                </span>
                <input
                  id="loginPassword"
                  type="password"
                  name="password"
                  class="form-control form-control-lg"
                  placeholder="Enter your password"
                  autocomplete="current-password"
                  required>
              </div>
            </div>

            <div class="col-12">
              <button type="submit" class="btn btn-action btn-lg w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i>
                Sign In
              </button>
            </div>

<div class="col-12">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <a href="<?= e(url('/forgot-password')) ?>" class="small fw-semibold">
      <i class="bi bi-key me-1"></i>Forgot password?
    </a>
    <span class="small text-secondary">
      New organizer?
      <a href="<?= e(url('/organizer-signup')) ?>" class="fw-semibold">
        Create an account
      </a>
    </span>
  </div>
</div>

          </form>

          <hr class="my-5">

          <div class="text-center small text-secondary">
            <i class="bi bi-shield-lock me-1 text-brand"></i>
            Your connection is secure.
            <a href="<?= e(url('/')) ?>">Back to website</a>
          </div>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- Login-specific styles (only this page) -->
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
    min-height: 220px;
  }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>