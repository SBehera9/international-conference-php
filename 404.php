<?php
declare(strict_types=1);

http_response_code(404);

require_once __DIR__ . '/includes/bootstrap.php';

$seo = page_seo([
    'title'       => 'Page Not Found',
    'description' => 'The requested page could not be found.',
    'robots'      => 'noindex,follow',
]);

require __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     404 PAGE
     ========================================================================== -->
<section class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9 col-xl-7">
        <div class="text-center reveal">

          <!-- 404 Number -->
          <div class="error-404-number">
            404
          </div>

          <!-- Icon -->
          <div class="error-404-icon mb-4">
            <i class="bi bi-compass"></i>
          </div>

          <!-- Heading — the single H1 for this page -->
          <h1 class="display-5 fw-bold mb-3">
            Page not found
          </h1>

          <!-- Message -->
          <p class="lead text-secondary mb-5">
            The page may have moved, or the address may be incorrect.
            Let's get you back on track.
          </p>

          <!-- CTAs -->
          <div class="d-flex flex-wrap justify-content-center gap-3 mb-5">
            <a href="<?= e(url('/')) ?>" class="btn btn-action btn-lg px-4">
              <i class="bi bi-house-door me-1"></i>
              Back to Home
            </a>
            <a href="<?= e(url('/conferences')) ?>" class="btn btn-outline-dark btn-lg px-4">
              <i class="bi bi-calendar2-event me-1"></i>
              Browse Conferences
            </a>
          </div>

          <!-- Helpful links -->
          <div class="error-404-links mx-auto">
            <!-- Visible H2 for this page -->
            <h2 class="small text-secondary text-uppercase fw-bold mb-3"
                style="letter-spacing:.10em;">
              You might be looking for
            </h2>

            <div class="d-flex flex-wrap justify-content-center gap-2">
              <a href="<?= e(url('/organizers')) ?>" class="error-404-link">
                <i class="bi bi-people me-1"></i> Organizers
              </a>
              <a href="<?= e(url('/media-partner')) ?>" class="error-404-link">
                <i class="bi bi-newspaper me-1"></i> Media Partners
              </a>
              <a href="<?= e(url('/our-associates')) ?>" class="error-404-link">
                <i class="bi bi-diagram-3 me-1"></i> Associates
              </a>
              <a href="<?= e(url('/about-us')) ?>" class="error-404-link">
                <i class="bi bi-info-circle me-1"></i> About Us
              </a>
              <a href="<?= e(url('/contact-us')) ?>" class="error-404-link">
                <i class="bi bi-envelope me-1"></i> Contact
              </a>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

<!-- 404-specific styles -->
<style>
.error-404-number {
  font-family: var(--font-heading);
  font-size: clamp(5rem, 15vw, 11rem);
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.05em;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-accent) 60%, var(--color-action) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 1rem;
}

.error-404-icon {
  font-size: 2.5rem;
  color: var(--color-action);
  animation: compassSpin 6s linear infinite;
  display: inline-block;
}

@keyframes compassSpin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

.error-404-links {
  max-width: 560px;
}

.error-404-link {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-text);
  background: var(--color-white);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  text-decoration: none;
  transition:
    background var(--dur-fast) var(--ease-standard),
    color      var(--dur-fast) var(--ease-standard),
    border-color var(--dur-fast) var(--ease-standard),
    transform  var(--dur-fast) var(--ease-standard);
}

.error-404-link:hover {
  color: var(--color-accent);
  border-color: var(--color-accent);
  background: rgba(37, 99, 235, 0.04);
  transform: translateY(-2px);
}

@media (prefers-reduced-motion: reduce) {
  .error-404-icon { animation: none; }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>