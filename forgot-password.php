<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$adminEmail = 'internationalconference.info@gmail.com';

$seo = page_seo([
    'title'       => 'Forgot Password',
    'description' => 'How to reset your organizer account password.',
    'canonical'   => url('/forgot-password'),
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
        <li class="breadcrumb-item active" aria-current="page">Forgot Password</li>
      </ol>
    </nav>

    <div class="section-kicker">Password Reset</div>
    <h1 class="display-5 mt-2">Forgot Your Password?</h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      Follow the steps below to get a password reset link from our administrator.
    </p>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <div class="form-section reveal mb-4">
          <h2 class="h4 fw-bold mb-4">
            <i class="bi bi-info-circle me-2 text-brand"></i>
            How to Reset Your Password
          </h2>

          <div class="d-flex gap-3 mb-4">
            <span class="avatar-circle flex-shrink-0" style="width:40px;height:40px;font-size:.9rem;">1</span>
            <div>
              <div class="fw-semibold text-dark">Send an email to our admin</div>
              <div class="small text-secondary">
                Email us at <strong><?= e($adminEmail) ?></strong> from your
                registered email address. Include your login email ID and
                organization name so we can verify your account.
              </div>
            </div>
          </div>

          <div class="d-flex gap-3 mb-4">
            <span class="avatar-circle flex-shrink-0" style="width:40px;height:40px;font-size:.9rem;">2</span>
            <div>
              <div class="fw-semibold text-dark">Admin verifies your account</div>
              <div class="small text-secondary">
                Our administrator will check your details against our system
                and generate a secure password reset link for you.
              </div>
            </div>
          </div>

          <div class="d-flex gap-3 mb-4">
            <span class="avatar-circle flex-shrink-0" style="width:40px;height:40px;font-size:.9rem;">3</span>
            <div>
              <div class="fw-semibold text-dark">You receive the reset link by email</div>
              <div class="small text-secondary">
                The admin will reply to your email with a secure link.
                The link is valid for <strong>1 hour</strong>.
              </div>
            </div>
          </div>

          <div class="d-flex gap-3">
            <span class="avatar-circle flex-shrink-0" style="width:40px;height:40px;font-size:.9rem;">4</span>
            <div>
              <div class="fw-semibold text-dark">Set your new password</div>
              <div class="small text-secondary">
                Click the link, enter your new password, and confirm it.
                Your old password will stop working immediately.
              </div>
            </div>
          </div>
        </div>

        <div class="form-section reveal">
          <h3 class="h5 fw-bold mb-3">
            <i class="bi bi-envelope me-2 text-brand"></i>
            Send Your Request Now
          </h3>

          <p class="small text-secondary mb-4">
            Click the button below to open your email app with a ready-made message.
            Fill in your details and send it to our admin.
          </p>

          <?php
          $mailSubject = 'Password Reset Request';
          $mailBody = "Hello Admin,\n\n"
                    . "I forgot my password and need a reset link for my organizer account.\n\n"
                    . "My registered login email: [type here]\n"
                    . "My organization name: [type here]\n"
                    . "My contact phone: [type here]\n\n"
                    . "Thank you.";
          $mailto = 'mailto:' . $adminEmail
                  . '?subject=' . rawurlencode($mailSubject)
                  . '&body=' . rawurlencode($mailBody);
          ?>

          <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="<?= e($mailto) ?>" class="btn btn-action">
              <i class="bi bi-envelope-paper me-1"></i>
              Open Email App
            </a>

            <button type="button"
                    class="btn btn-outline-dark"
                    data-copy-template
                    data-copy-subject="<?= e($mailSubject) ?>"
                    data-copy-body="<?= e($mailBody) ?>"
                    data-copy-target="<?= e($adminEmail) ?>">
              <i class="bi bi-clipboard me-1"></i>
              Copy Template
            </button>
          </div>

          <div class="alert alert-info small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            If you don't have access to email from your registered address,
            contact your hosting administrator directly.
          </div>
        </div>

        <div class="text-center mt-4">
          <a href="<?= e(url('/login')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Login
          </a>
        </div>

      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-copy-template]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target  = btn.getAttribute('data-copy-target') || '';
      var subject = btn.getAttribute('data-copy-subject') || '';
      var body    = btn.getAttribute('data-copy-body') || '';

      var text = 'To: ' + target + '\n\n'
               + 'Subject: ' + subject + '\n\n'
               + body;

      function showCopied(ok) {
        var old = btn.innerHTML;
        btn.innerHTML = ok
          ? '<i class="bi bi-check-circle me-1"></i>Copied!'
          : '<i class="bi bi-x-circle me-1"></i>Copy failed';
        btn.disabled = true;
        setTimeout(function () {
          btn.innerHTML = old;
          btn.disabled = false;
        }, 1800);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          showCopied(true);
        }).catch(function () {
          showCopied(false);
        });
      } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        showCopied(ok);
      }
    });
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>