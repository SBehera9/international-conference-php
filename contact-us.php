<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   CONTACT INFORMATION
   EDIT YOUR REAL DETAILS HERE
   ========================================================================== */

/* =========================
   EDIT HERE - CONTACT DETAILS
   ========================= */

$contactEmail   = 'contact@internationalconference.info';
$contactPhone   = '+91 1234567890';
$contactAddress = 'Janani Vasathi, monnekolala road, Kasavanahalli Village, Marathahalli, Bengaluru, India, 560037';


/* =========================
   EDIT HERE - SOCIAL LINKS
   If you do not use any social media, keep url as ''
   Example: 'url' => '',
   ========================= */

$socialLinks = [
    [
        'label' => 'Facebook',
        'icon'  => 'bi-facebook',
        'url'   => 'https://www.facebook.com/internationalconferencesinfo',
    ],
    [
        'label' => 'Instagram',
        'icon'  => 'bi-instagram',
        'url'   => 'https://www.instagram.com/intconfinfo/',
    ],
    [
        'label' => 'LinkedIn',
        'icon'  => 'bi-linkedin',
        'url'   => '#',
    ],
    [
        'label' => 'Twitter / X',
        'icon'  => 'bi-twitter-x',
        'url'   => 'https://x.com/intconfinfo',
    ],
    [
        'label' => 'YouTube',
        'icon'  => 'bi-youtube',
        'url'   => '#',
    ],
];


/* ==========================================================================
   SEO
   ========================================================================== */

/* ==========================================================================
   SEO — per-page keywords + CollectionPage schema
   ========================================================================== */
$pageKeywords = page_keywords('contact_us');

$contactCollectionName = 'Contact Us';
$contactCollectionDesc = 'Contact International Conference for support, inquiries and general information.';

$seo = page_seo([
    'title'       => $contactCollectionName,
    'description' => $contactCollectionDesc,
    'keywords'    => $pageKeywords,
    'canonical'   => url('/contact-us'),
    'json_ld'     => [
        collection_schema(
            $contactCollectionName,
            url('/contact-us'),
            $contactCollectionDesc,
            $pageKeywords
        ),
        breadcrumb_schema([
            ['name' => 'Home',       'url' => url('/')],
            ['name' => 'Contact Us', 'url' => url('/contact-us')],
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
        <li class="breadcrumb-item">
          <a href="<?= e(url('/')) ?>">Home</a>
        </li>

        <li class="breadcrumb-item active" aria-current="page">
          Contact Us
        </li>
      </ol>
    </nav>

    <div class="section-kicker">
      We are here to help
    </div>

    <h1 class="display-5 mt-2">
      Contact Us
    </h1>

    <p class="lead text-secondary col-lg-9 mb-0">
      Get in touch with International Conference using the contact details below.
    </p>

  </div>
</section>


<!-- ==========================================================================
     CONTACT CARDS
     ========================================================================== -->

<section class="py-5">
  <div class="container">

    <div class="text-center mb-5 reveal">

      <div class="section-kicker">
        Contact Information
      </div>

      <h2 class="section-title h2 mt-2">
        Reach out to us
      </h2>

      <p class="section-copy mx-auto">
        Use any of the details below to get in touch.
        We respond within 1-2 business days.
      </p>

    </div>


    <div class="row g-4 reveal-stagger">

      <!-- ================================================================
           EMAIL
           ================================================================ -->

      <div class="col-md-6">

        <div class="form-section h-100 text-center">

          <div
            class="mx-auto mb-3 d-flex align-items-center justify-content-center"
            style="
              width:64px;
              height:64px;
              border-radius:50%;
              background:rgba(37,99,235,0.10);
            "
          >

            <i
              class="bi bi-envelope-fill text-brand"
              style="font-size:1.5rem;"
            ></i>

          </div>


          <div
            class="small text-secondary fw-semibold text-uppercase mb-2"
            style="letter-spacing:.08em;"
          >
            Email
          </div>


          <?php if ($contactEmail !== ''): ?>

            <a
              href="mailto:<?= e($contactEmail) ?>"
              class="fw-bold text-decoration-none fs-5"
            >
              <?= e($contactEmail) ?>
            </a>

          <?php else: ?>

            <span class="fw-bold text-secondary fs-6">
              Not available
            </span>

          <?php endif; ?>


          <p class="small text-secondary mt-3 mb-0">
            Best for general inquiries and support.
          </p>

        </div>
      </div>


      <!-- ================================================================
           PHONE
           ================================================================ -->

      <!-- <div class="col-md-4">

        <div class="form-section h-100 text-center">

          <div
            class="mx-auto mb-3 d-flex align-items-center justify-content-center"
            style="
              width:64px;
              height:64px;
              border-radius:50%;
              background:rgba(249,115,22,0.10);
            "
          >

            <i
              class="bi bi-telephone-fill"
              style="
                font-size:1.5rem;
                color:#F97316;
              "
            ></i>

          </div>


          <div
            class="small text-secondary fw-semibold text-uppercase mb-2"
            style="letter-spacing:.08em;"
          >
            Phone
          </div>


          <?php if ($contactPhone !== ''): ?>

            <a
              href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"
              class="fw-bold text-decoration-none fs-5"
            >
              <?= e($contactPhone) ?>
            </a>

          <?php else: ?>

            <span class="fw-bold text-secondary fs-6">
              Not available
            </span>

          <?php endif; ?>


          <p class="small text-secondary mt-3 mb-0">
            Available Mon–Fri, 9 AM – 6 PM IST.
          </p>

        </div>
      </div> -->


      <!-- ================================================================
           ADDRESS
           ================================================================ -->

      <div class="col-md-6">

        <div class="form-section h-100 text-center">

          <div
            class="mx-auto mb-3 d-flex align-items-center justify-content-center"
            style="
              width:64px;
              height:64px;
              border-radius:50%;
              background:rgba(16,185,129,0.10);
            "
          >

            <i
              class="bi bi-geo-alt-fill"
              style="
                font-size:1.5rem;
                color:#10B981;
              "
            ></i>

          </div>


          <div
            class="small text-secondary fw-semibold text-uppercase mb-2"
            style="letter-spacing:.08em;"
          >
            Address
          </div>


          <div class="fw-bold fs-6">

            <?php if ($contactAddress !== ''): ?>

              <?= nl2br(e($contactAddress)) ?>

            <?php else: ?>

              <span class="text-secondary">
                Not available
              </span>

            <?php endif; ?>

          </div>


          <p class="small text-secondary mt-3 mb-0">
            Our official correspondence address.
          </p>

        </div>
      </div>

    </div>

  </div>
</section>


<!-- ==========================================================================
     SOCIAL LINKS
     ========================================================================== -->

<section class="py-5 bg-soft">

  <div class="container text-center">

    <div class="reveal">

      <div class="section-kicker">
        Stay connected
      </div>

      <h3 class="section-title h2 mt-2 mb-3">
        Follow Us
      </h3>

      <p class="section-copy mx-auto mb-4">
        Follow International Conference on social media for updates,
        announcements and event news.
      </p>

    </div>


    <div class="d-flex flex-wrap justify-content-center gap-3 reveal">

      <?php foreach ($socialLinks as $social): ?>

        <?php
        if (empty($social['url'])) {
            continue;
        }
        ?>

        <a
          href="<?= e($social['url']) ?>"
          target="_blank"
          rel="noopener noreferrer"
          class="
            footer-socials-item
            d-inline-flex
            flex-column
            align-items-center
            justify-content-center
          "
          style="
            width:84px;
            height:84px;
            background:#fff;
            border:1px solid var(--color-border);
            border-radius:var(--radius-lg);
            text-decoration:none;
            color:var(--color-text);
            transition:all var(--dur-base) var(--ease-standard);
          "
          aria-label="<?= e($social['label']) ?>"
          title="<?= e($social['label']) ?>"
        >

          <i
            class="bi <?= e($social['icon']) ?>"
            style="
              font-size:1.5rem;
              color:var(--color-accent);
            "
          ></i>

          <span class="small mt-1 fw-semibold">
            <?= e(explode(' ', $social['label'])[0]) ?>
          </span>

        </a>

      <?php endforeach; ?>

    </div>

  </div>
</section>


<!-- ==========================================================================
     ORGANIZER CTA
     ========================================================================== -->

<section class="py-5 bg-white">

  <div class="container">

    <div class="partner-apply-box text-center reveal">

      <div class="section-kicker mb-2">
        Want to host a conference?
      </div>

      <h3 class="h3 fw-bold mb-3">
        Become an Organizer
      </h3>

      <p class="text-secondary mb-4">
        Create an organizer account and submit your conference for admin review.
      </p>

      <a
        href="<?= e(url('/organizer-signup')) ?>"
        class="btn btn-action btn-lg px-4"
      >
        <i class="bi bi-person-plus me-1"></i>
        Organizer Signup
      </a>

    </div>

  </div>
</section>


<!-- ==========================================================================
     PAGE-ONLY STYLE
     ========================================================================== -->

<style>

.footer-socials-item:hover {
  transform: translateY(-4px);
  border-color: var(--color-accent);
  box-shadow: var(--shadow-lg);
}

</style>


<?php require __DIR__ . '/includes/footer.php'; ?>