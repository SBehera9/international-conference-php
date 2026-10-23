<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$body = content('terms_of_service', '');

/* ==========================================================================
   SEO — per-page keywords + CollectionPage schema
   ========================================================================== */
$pageKeywords = page_keywords('terms_of_service');

$termsCollectionName = 'Terms of Service';
$termsCollectionDesc = 'Terms of Service for International Conference conference listings and organizer accounts.';

$seo = page_seo([
    'title'       => $termsCollectionName,
    'description' => $termsCollectionDesc,
    'keywords'    => $pageKeywords,
    'canonical'   => url('/terms-of-service'),
    'json_ld'     => [
        collection_schema(
            $termsCollectionName,
            url('/terms-of-service'),
            $termsCollectionDesc,
            $pageKeywords
        ),
        breadcrumb_schema([
            ['name' => 'Home',             'url' => url('/')],
            ['name' => 'Terms of Service', 'url' => url('/terms-of-service')],
        ]),
    ],
]);

require __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     PAGE HERO
     ========================================================================== -->
<section class="page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Terms of Service</li>
      </ol>
    </nav>

    <div class="section-kicker">Legal</div>
    <h1 class="display-5 mt-2">Terms of Service</h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      The rules and guidelines for using <?= e(APP_NAME) ?>.
    </p>
  </div>
</section>

<!-- ==========================================================================
     TERMS BODY
     ========================================================================== -->
<section class="py-5">
  <div class="container">
    <div class="row g-5 align-items-start">

      <!-- Body -->
      <div class="col-lg-8">
        <article class="prose reveal">

          <div class="d-flex align-items-center gap-2 text-secondary small mb-4">
            <i class="bi bi-clock-history"></i>
            <span>Last updated: <?= date('F j, Y') ?></span>
          </div>

          <?php if (trim($body) !== ''): ?>
            <?php foreach (preg_split('/\R{2,}/', trim($body)) as $paragraph): ?>
              <p><?= nl2br(e($paragraph)) ?></p>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-secondary">
              Terms of service content will appear here once added by the administrator.
            </p>
          <?php endif; ?>

        </article>
      </div>

      <!-- Sidebar: Quick links -->
      <div class="col-lg-4">
        <div class="form-section reveal" style="position:sticky;top:110px;">

          <h2 class="h5 fw-bold mb-4">
            <i class="bi bi-link-45deg me-2 text-brand"></i>
            Quick Links
          </h2>

          <ul class="list-unstyled mb-4">
            <li class="mb-3">
              <a href="<?= e(url('/privacy-policy')) ?>"
                 class="d-flex align-items-center gap-2 text-decoration-none text-dark fw-semibold">
                <i class="bi bi-shield-lock text-brand"></i>
                Privacy Policy
                <i class="bi bi-arrow-right ms-auto text-secondary"></i>
              </a>
            </li>
            <li class="mb-3">
              <a href="<?= e(url('/about-us')) ?>"
                 class="d-flex align-items-center gap-2 text-decoration-none text-dark fw-semibold">
                <i class="bi bi-info-circle text-brand"></i>
                About Us
                <i class="bi bi-arrow-right ms-auto text-secondary"></i>
              </a>
            </li>
            <li class="mb-3">
              <a href="<?= e(url('/contact-us')) ?>"
                 class="d-flex align-items-center gap-2 text-decoration-none text-dark fw-semibold">
                <i class="bi bi-envelope text-brand"></i>
                Contact Us
                <i class="bi bi-arrow-right ms-auto text-secondary"></i>
              </a>
            </li>
            <li>
              <a href="<?= e(url('/feedback')) ?>"
                 class="d-flex align-items-center gap-2 text-decoration-none text-dark fw-semibold">
                <i class="bi bi-chat-square-heart text-brand"></i>
                Share Feedback
                <i class="bi bi-arrow-right ms-auto text-secondary"></i>
              </a>
            </li>
          </ul>

          <hr>

          <div class="small text-secondary">
            <i class="bi bi-info-circle me-1 text-brand"></i>
            Questions about these terms?
            <a href="<?= e(url('/contact-us')) ?>">Contact us</a>.
          </div>

        </div>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>