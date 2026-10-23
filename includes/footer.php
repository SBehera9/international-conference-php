</main>

<!-- ==========================================================================
     Site Footer
     ========================================================================== -->
<footer class="site-footer">
  <div class="container">

    <div class="row g-5">

      <!-- Brand + Description + Social -->
      <div class="col-lg-5">

        <div class="mb-4">
          <img
            src="<?= e(url('/assets/images/company-logo.png')) ?>"
            alt="<?= e(APP_NAME) ?>"
            class="footer-logo-full">
        </div>

        <p class="footer-copy">
          <?= e(
            content(
              'footer_description',
              'A global directory helping researchers, professionals and organizers discover trusted international conferences.'
            )
          ) ?>
        </p>

        <div class="footer-socials">
          <?php
          $footerSocials = [
            'facebook'   => 'facebook_url',
            'instagram'  => 'instagram_url',
            'linkedin'   => 'linkedin_url',
            'twitter-x'  => 'twitter_url',
            'youtube'    => 'youtube_url',
          ];

          foreach ($footerSocials as $icon => $key):
            $link = setting($key);
            if ($link):
          ?>
            <a
              href="<?= e($link) ?>"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="<?= e(ucfirst($icon)) ?>"
              title="<?= e(ucfirst($icon)) ?>">
              <i class="bi bi-<?= e($icon) ?>"></i>
            </a>
          <?php
            endif;
          endforeach;
          ?>
        </div>

      </div>

      <!-- Explore -->
      <div class="col-6 col-lg-2">

        <h3 class="footer-title">Explore</h3>

        <ul class="footer-links">
          <li>
            <a href="<?= e(url('/conferences')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              Conferences
            </a>
          </li>
          <li>
            <a href="<?= e(url('/organizers')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              Organizers
            </a>
          </li>
          <li>
            <a href="<?= e(url('/media-partner')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              Media Partner
            </a>
          </li>
          <li>
            <a href="<?= e(url('/our-associates')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              Associates
            </a>
          </li>
          <li>
            <a href="<?= e(url('/blog')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              Blog
            </a>
          </li>
        </ul>

      </div>

      <!-- Company -->
      <div class="col-6 col-lg-2">

        <h3 class="footer-title">Company</h3>

        <ul class="footer-links">
          <li>
            <a href="<?= e(url('/about-us')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              About Us
            </a>
          </li>
          <li>
            <a href="<?= e(url('/contact-us')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              Contact Us
            </a>
          </li>
          <li>
            <a href="<?= e(url('/feedback')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              Feedback
            </a>
          </li>
          <li>
            <a href="<?= e(url('/login')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              Login
            </a>
          </li>
          <li>
            <a href="<?= e(url('/faq')) ?>">
              <i class="bi bi-chevron-right me-1 small"></i>
              FAQ
            </a>
          </li>
        </ul>

      </div>

      <!-- Newsletter -->
      <div class="col-lg-3" id="newsletter">

        <h3 class="footer-title">Newsletter</h3>

        <p class="small mb-4" style="color:rgba(255,255,255,0.60);line-height:1.6;">
          Subscribe to receive the latest conference announcements and updates.
        </p>

        <?php
        $newsletterResult = strtolower(trim(getq('newsletter')));
        ?>

        <?php if ($newsletterResult === 'success'): ?>
          <div class="alert alert-success py-2 px-3 small mb-3">
            <i class="bi bi-check-circle me-1"></i>
            Subscribed successfully.
          </div>
        <?php elseif ($newsletterResult === 'exists'): ?>
          <div class="alert alert-warning py-2 px-3 small mb-3">
            <i class="bi bi-exclamation-circle me-1"></i>
            Already subscribed.
          </div>
        <?php elseif ($newsletterResult === 'invalid'): ?>
          <div class="alert alert-danger py-2 px-3 small mb-3">
            <i class="bi bi-x-circle me-1"></i>
            Enter a valid email.
          </div>
        <?php elseif ($newsletterResult === 'error'): ?>
          <div class="alert alert-danger py-2 px-3 small mb-3">
            <i class="bi bi-x-circle me-1"></i>
            Try again later.
          </div>
        <?php endif; ?>

        <form
          method="post"
          action="<?= e(url('/subscribe.php')) ?>"
          class="footer-newsletter">
          <?= csrf_field() ?>

          <div class="mb-2">
            <label for="footerSubscriberEmail" class="visually-hidden">
              Email Address
            </label>
            <input
              id="footerSubscriberEmail"
              name="subscriber_email"
              type="email"
              class="form-control form-control-sm"
              placeholder="Enter your email"
              autocomplete="email"
              required>
          </div>

          <button type="submit" class="btn btn-action btn-sm w-100">
            <i class="bi bi-envelope-check me-1"></i>
            Subscribe
          </button>
        </form>

      </div>

    </div>

    <hr>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 small">
      <span>
        &copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.
      </span>

      <span class="d-flex flex-wrap gap-3 justify-content-center">
        <a href="<?= e(url('/privacy-policy')) ?>">Privacy Policy</a>
        <span style="opacity:.4;">·</span>
        <a href="<?= e(url('/terms-of-service')) ?>">Terms of Service</a>
    </div>

  </div>
  </div>
</footer>

<!-- ==========================================================================
     Scripts
     ========================================================================== -->
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
  crossorigin="anonymous"></script>

<script src="<?= e(url('/assets/js/app.js')) ?>?v=2.1.0"></script>

</body>
</html>