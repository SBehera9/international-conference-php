<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$title = content('about_title', 'About International Conference');
$body  = content('about_body', '');

/* ==========================================================================
   SEO — per-page keywords + CollectionPage schema
   ========================================================================== */
$pageKeywords = page_keywords('about_us');

$aboutCollectionName = 'About Us';
$aboutCollectionDesc = excerpt($body, 158);

$seo = page_seo([
    'title'       => $title,
    'description' => $aboutCollectionDesc,
    'keywords'    => $pageKeywords,
    'canonical'   => url('/about-us'),
    'json_ld'     => [
        collection_schema(
            $aboutCollectionName,
            url('/about-us'),
            $aboutCollectionDesc,
            $pageKeywords
        ),
        breadcrumb_schema([
            ['name' => 'Home',     'url' => url('/')],
            ['name' => 'About Us', 'url' => url('/about-us')],
        ]),
    ],
]);

require __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     PAGE HERO — the single H1 for this page
     ========================================================================== -->
<section class="page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">About Us</li>
      </ol>
    </nav>

    <div class="section-kicker">Our mission</div>
    <h1 class="display-5 mt-2"><?= e($title) ?></h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      Connecting researchers, professionals and trusted conference organizers
      through a structured global directory.
    </p>
  </div>
</section>

<!-- ==========================================================================
     MAIN CONTENT
     ========================================================================== -->
<section class="py-5">
  <div class="container">
    <div class="row g-5 align-items-start">

      <!-- Body -->
      <div class="col-lg-8">
        <article class="prose reveal">

          <!-- The single H2 for this page -->
          <h2 class="h3 fw-bold mb-4">
            <i class="bi bi-globe2 me-2 text-brand"></i>
            Built for trustworthy conference discovery
          </h2>

          <?php if (trim($body) !== ''): ?>
            <?php foreach (preg_split('/\R{2,}/', trim($body)) as $paragraph): ?>
              <p><?= nl2br(e($paragraph)) ?></p>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-secondary">
              Content will appear here once added by the administrator.
            </p>
          <?php endif; ?>

        </article>
      </div>

      <!-- How It Works (demoted to H3) -->
      <div class="col-lg-4">
        <div class="form-section reveal" style="position:sticky;top:110px;">

          <h3 class="h5 fw-bold mb-4">
            <i class="bi bi-diagram-3 me-2 text-brand"></i>
            How the platform works
          </h3>

          <ol class="list-unstyled mb-0">

            <li class="d-flex gap-3 mb-4">
              <span class="avatar-circle flex-shrink-0" style="width:36px;height:36px;font-size:.85rem;">
                1
              </span>
              <div>
                <div class="fw-semibold text-dark">Organizers submit</div>
                <div class="small text-secondary">
                  Organizers create profiles and submit conferences.
                </div>
              </div>
            </li>

            <li class="d-flex gap-3 mb-4">
              <span class="avatar-circle flex-shrink-0" style="width:36px;height:36px;font-size:.85rem;">
                2
              </span>
              <div>
                <div class="fw-semibold text-dark">Admin review</div>
                <div class="small text-secondary">
                  Submissions enter the admin review queue.
                </div>
              </div>
            </li>

            <li class="d-flex gap-3 mb-4">
              <span class="avatar-circle flex-shrink-0" style="width:36px;height:36px;font-size:.85rem;">
                3
              </span>
              <div>
                <div class="fw-semibold text-dark">Public listing</div>
                <div class="small text-secondary">
                  Approved conferences become publicly searchable.
                </div>
              </div>
            </li>

            <li class="d-flex gap-3">
              <span class="avatar-circle flex-shrink-0" style="width:36px;height:36px;font-size:.85rem;">
                4
              </span>
              <div>
                <div class="fw-semibold text-dark">Historical archive</div>
                <div class="small text-secondary">
                  Completed conferences are archived by date for history and
                  organizer management.
                </div>
              </div>
            </li>

          </ol>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- ==========================================================================
     TRUST HIGHLIGHTS
     ========================================================================== -->
<section class="py-5 bg-soft">
  <div class="container">

    <div class="text-center mb-5 reveal">
      <div class="section-kicker">Why use this platform</div>
      <!-- Demoted from H2 to H3 -->
      <h3 class="section-title h2 mt-2">Built for trust and clarity</h3>
    </div>

    <div class="row g-4 reveal-stagger">

      <div class="col-md-6 col-lg-3">
        <div class="stat-card h-100 text-center">
          <i class="bi bi-shield-check"></i>
          <!-- Demoted from H3 to H4 -->
          <h4 class="h6 fw-bold mt-3 mb-2">Admin Reviewed</h4>
          <p class="small text-secondary mb-0">
            Every conference is manually reviewed before going live.
          </p>
        </div>
      </div>

      <div class="col-md-6 col-lg-3">
        <div class="stat-card h-100 text-center">
          <i class="bi bi-globe2"></i>
          <h4 class="h6 fw-bold mt-3 mb-2">Global Reach</h4>
          <p class="small text-secondary mb-0">
            Browse conferences from dozens of countries and cities.
          </p>
        </div>
      </div>

      <div class="col-md-6 col-lg-3">
        <div class="stat-card h-100 text-center">
          <i class="bi bi-people"></i>
          <h4 class="h6 fw-bold mt-3 mb-2">Trusted Organizers</h4>
          <p class="small text-secondary mb-0">
            Verified profiles from active conference organizers.
          </p>
        </div>
      </div>

      <div class="col-md-6 col-lg-3">
        <div class="stat-card h-100 text-center">
          <i class="bi bi-lightning-charge"></i>
          <h4 class="h6 fw-bold mt-3 mb-2">Fast Discovery</h4>
          <p class="small text-secondary mb-0">
            Filter by topic, country, city and attendance type.
          </p>
        </div>
      </div>

    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>