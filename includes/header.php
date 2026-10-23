<?php
/** @var array $seo */
$seo = $seo ?? page_seo();
$flashes = pull_flashes();
$u = current_user();

/*
|--------------------------------------------------------------------------
| JSON-LD structured data
|--------------------------------------------------------------------------
| Per-page schemas come from $seo['json_ld'] (Breadcrumb, Event, Article…).
|
| Site-wide WebSite + Organization schemas are provided as static blocks
| in <head> below (client-supplied), so we do NOT auto-inject the dynamic
| organization_schema() / website_schema() here — that would duplicate them.
*/
$ldItems = $seo['json_ld'] ?? [];
if (!is_array($ldItems)) $ldItems = [];

/* Active nav helper */
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
$relPath = $basePath !== '' && str_starts_with($currentPath, $basePath)
    ? substr($currentPath, strlen($basePath))
    : $currentPath;
$relPath = '/' . trim($relPath, '/');

$isActive = static function (string $path) use ($relPath): bool {
    $path = '/' . trim($path, '/');
    if ($path === '/') return $relPath === '/' || $relPath === '';
    return str_starts_with($relPath, $path);
};
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0A2540">

  <title><?= e($seo['title']) ?></title>
  <meta name="description" content="<?= e($seo['description']) ?>">
  <?php if (!empty($seo['keywords'])): ?>
  <meta name="keywords" content="<?= e($seo['keywords']) ?>">
  <?php endif; ?>
  <meta name="robots" content="<?= e($seo['robots']) ?>">
  <link rel="canonical" href="<?= e($seo['canonical']) ?>">

  <?php if (defined('GOOGLE_SITE_VERIFICATION') && GOOGLE_SITE_VERIFICATION): ?>
  <meta name="google-site-verification" content="<?= e(GOOGLE_SITE_VERIFICATION) ?>">
  <?php endif; ?>

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=GT-MKTJ95TH"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'GT-MKTJ95TH');
  </script>

  <!-- Open Graph -->
  <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
  <meta property="og:type" content="<?= e($seo['type']) ?>">
  <meta property="og:title" content="<?= e($seo['title']) ?>">
  <meta property="og:description" content="<?= e($seo['description']) ?>">
  <meta property="og:url" content="<?= e($seo['canonical']) ?>">
  <meta property="og:image" content="<?= e($seo['image']) ?>">
  <meta property="og:locale" content="en_US">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($seo['title']) ?>">
  <meta name="twitter:description" content="<?= e($seo['description']) ?>">
  <meta name="twitter:image" content="<?= e($seo['image']) ?>">

  <!-- Favicon -->
  <link rel="icon" href="<?= e(url('/assets/images/favicon.jpeg')) ?>" type="image/jpeg">
  <link rel="apple-touch-icon" href="<?= e(url('/assets/images/favicon.jpeg')) ?>">

  <!-- Fonts (preconnect for speed) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800;900&display=swap"
    rel="stylesheet">

  <!-- Bootstrap 5 CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous">

  <!-- Bootstrap Icons -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../assets/css/app.css') ?>">

  <!-- ============================================================
       Structured data (JSON-LD)
       ============================================================ -->

 <!-- Per-page dynamic schemas (Breadcrumb, Event, Article, etc.) -->
  <?php foreach ($ldItems as $ld): ?>
  <script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <?php endforeach; ?>

  <!-- Site-wide Organization schema (client-supplied) -->
  <script type="application/ld+json">
  {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "International Conference",
      "brand": "International Conference",
      "url": "https://internationalconference.info/",
      "logo": "https://internationalconference.info/assets/images/company-logo.png",
      "email": "contact@internationalconference.info",
      "sameAs": [
          "https://x.com/intconfinfo",
          "https://www.linkedin.com/in/international-conference/",
          "https://www.instagram.com/intconfinfo/"
      ],
      "address": {
          "@type": "PostalAddress",
          "streetAddress": "Janani Vasathi, monnekolala road, Kasavanahalli Village, Marathahalli",
          "addressLocality": "Bengaluru",
          "addressRegion": "India",
          "postalCode": "560037"
      }
  }
  </script>
</head>
<body>

<a class="skip-link" href="#main-content">Skip to content</a>

<!-- ==========================================================================
     Site Navbar
     ========================================================================== -->
<nav class="navbar navbar-expand-xl site-navbar" aria-label="Primary navigation">
  <div class="container">

    <a class="navbar-brand" href="<?= e(url('/')) ?>">
      <img
        src="<?= e(url('/assets/images/company-logo.png')) ?>"
        alt="<?= e(APP_NAME) ?>"
        class="brand-logo-full">
    </a>

    <button
      class="navbar-toggler"
      type="button"
      data-navbar-toggle
      aria-controls="siteNav"
      aria-expanded="false"
      aria-label="Toggle navigation">
      <i class="bi bi-list"></i>
    </button>

    <ul class="navbar-nav" id="siteNav">

      <li class="nav-item">
        <a class="nav-link<?= $isActive('/') && $relPath === '/' ? ' active' : '' ?>"
           href="<?= e(url('/')) ?>">Home</a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?= $isActive('/conferences') || $isActive('/conference/') ? ' active' : '' ?>"
           href="<?= e(url('/conferences')) ?>">Conferences</a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?= $isActive('/organizers') ? ' active' : '' ?>"
           href="<?= e(url('/organizers')) ?>">Organizers</a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?= $isActive('/media-partner') ? ' active' : '' ?>"
           href="<?= e(url('/media-partner')) ?>">Media Partner</a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?= $isActive('/our-associates') ? ' active' : '' ?>"
           href="<?= e(url('/our-associates')) ?>">Associates</a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?= $isActive('/blog') ? ' active' : '' ?>"
           href="<?= e(url('/blog')) ?>">Blog</a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?= $isActive('/about-us') ? ' active' : '' ?>"
           href="<?= e(url('/about-us')) ?>">About</a>
      </li>

      <li class="nav-item">
        <a class="nav-link<?= $isActive('/contact-us') ? ' active' : '' ?>"
           href="<?= e(url('/contact-us')) ?>">Contact</a>
      </li>

      <li class="nav-item d-xl-none mt-3">
        <div class="d-grid gap-2">
          <?php if ($u): ?>
            <a class="btn btn-outline-dark" href="<?= e(url(dashboard_home_for_user($u))) ?>">
              <i class="bi bi-grid me-1"></i> Dashboard
            </a>
          <?php else: ?>
            <a class="btn btn-outline-dark" href="<?= e(url('/login')) ?>">
              <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </a>
            <a class="btn btn-action" href="<?= e(url('/organizer-signup')) ?>">
              <i class="bi bi-person-plus me-1"></i> Organizer Signup
            </a>
          <?php endif; ?>
        </div>
      </li>

    </ul>

    <div class="d-none d-xl-flex align-items-center gap-2 ms-3">
      <?php if ($u): ?>
        <a class="btn btn-outline-dark btn-sm" href="<?= e(url(dashboard_home_for_user($u))) ?>">
          <i class="bi bi-grid me-1"></i> Dashboard
        </a>
      <?php else: ?>
        <a class="btn btn-outline-dark btn-sm" href="<?= e(url('/login')) ?>">
          Login
        </a>
        <a class="btn btn-action btn-sm" href="<?= e(url('/organizer-signup')) ?>">
          <i class="bi bi-person-plus me-1"></i> Organizer Signup
        </a>
      <?php endif; ?>
    </div>

  </div>
</nav>

<!-- ==========================================================================
     Flash messages
     ========================================================================== -->
<?php if ($flashes): ?>
<div class="container pt-3" aria-live="polite">
  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
      <i class="bi bi-<?= $f['type'] === 'success' ? 'check-circle' : ($f['type'] === 'danger' ? 'exclamation-circle' : 'info-circle') ?> me-2"></i>
      <?= e($f['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ==========================================================================
     Main content opens here — pages put their content inside
     ========================================================================== -->
<main id="main-content">