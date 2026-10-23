<?php
/* ==========================================================================
   Dashboard Header
   Shared shell for both Admin and Organizer portals.
   ========================================================================== */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dashboardTitle = $dashboardTitle ?? 'Dashboard';
$dashboardRole  = $dashboardRole  ?? 'organizer';

$dashboardUser = current_user();

$dashboardProfile =
    $dashboardRole === 'organizer' && $dashboardUser
        ? organizer_profile_for_user((int) $dashboardUser['id'])
        : null;

$seo = page_seo([
    'title'     => $dashboardTitle,
    'robots'    => 'noindex,nofollow',
    'canonical' => current_page_url(),
]);

/* Current request path — used for active highlighting */
$dashPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$dashPath = '/' . trim($dashPath, '/');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0A2540">
  <meta name="robots" content="noindex,nofollow">

  <title><?= e($seo['title']) ?> | <?= e(APP_NAME) ?></title>

  <link rel="icon" href="<?= e(url('/assets/images/favicon.jpeg')) ?>" type="image/jpeg">
  <link rel="apple-touch-icon" href="<?= e(url('/assets/images/favicon.jpeg')) ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800;900&display=swap"
    rel="stylesheet">

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous">

  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>?v=2.0.0">
</head>
<body class="dashboard-body">

<div class="dashboard-shell">

  <!-- ======================================================================
       Sidebar
       ====================================================================== -->
  <aside class="dashboard-sidebar" id="dashboardSidebar">

    <!-- Brand -->
    <a
      href="<?= e(url($dashboardRole === 'admin' ? '/admin/' : '/organizer/')) ?>"
      class="dashboard-brand dashboard-brand-logo-only">
      <img
        src="<?= e(url('/assets/images/company-logo.png')) ?>"
        alt="<?= e(APP_NAME) ?>">
    </a>

    <!-- Role badge -->
    <div>
      <span class="dashboard-role-badge">
        <i class="bi bi-<?= $dashboardRole === 'admin' ? 'shield-lock' : 'person-badge' ?> me-1"></i>
        <?= e(ucfirst($dashboardRole)) ?> Portal
      </span>
    </div>

<?php if ($dashboardRole === 'organizer'): ?>
<?php
$organizerProfileComplete = !empty($dashboardProfile['is_profile_complete']);

$isOrganizerDashboard     = (bool) preg_match('#/organizer/?$#', $dashPath);
$isOrganizerConferences   =
    str_contains($dashPath, '/organizer/add-conference') ||
    str_contains($dashPath, '/organizer/pending-conferences') ||
    str_contains($dashPath, '/organizer/rejected-conferences') ||
    str_contains($dashPath, '/organizer/completed-conferences') ||
    str_contains($dashPath, '/organizer/manage-conferences');
$isOrganizerAccount       = str_contains($dashPath, '/organizer/profile');
?>

  <?php if (!$organizerProfileComplete): ?>
  <!-- ==========================================================
       INCOMPLETE PROFILE — locked state
       ========================================================== -->

  <div class="dashboard-disabled-link">
    <i class="bi bi-grid-1x2"></i>
    <span>Dashboard</span>
    <i class="bi bi-lock-fill ms-auto"></i>
  </div>

  <div class="organizer-locked-group">
    <div class="organizer-locked-heading">
      <span>
        <i class="bi bi-calendar-event"></i>
        Conferences
      </span>
      <i class="bi bi-lock-fill"></i>
    </div>
  </div>

  <div class="admin-sidebar-accordion">
    <div class="admin-menu-group open">
      <button type="button" class="admin-menu-toggle" data-admin-menu-toggle>
        <span>
          <i class="bi bi-person"></i>
          Account
        </span>
        <i class="bi bi-chevron-down admin-menu-arrow"></i>
      </button>

      <div class="admin-submenu">
        <a href="<?= e(url('/organizer/profile')) ?>" class="active">
          <i class="bi bi-person-circle"></i>
          Profile Setup
        </a>
      </div>
    </div>
  </div>

  <div class="organizer-profile-warning">
    <i class="bi bi-info-circle"></i>
    <div>Complete your profile to unlock the Organizer Portal.</div>
  </div>

  <div class="admin-sidebar-logout">
    <a href="<?= e(url('/organizer/logout')) ?>" class="admin-logout-link">
      <i class="bi bi-box-arrow-left"></i>
      Logout
    </a>
  </div>

  <?php else: ?>
  <!-- ==========================================================
       COMPLETE PROFILE — full menu
       ========================================================== -->

  <a
    href="<?= e(url('/organizer/')) ?>"
    class="dashboard-main-link <?= $isOrganizerDashboard ? 'active' : '' ?>">
    <i class="bi bi-grid-1x2"></i>
    <span>Dashboard</span>
  </a>

  <div class="admin-sidebar-accordion">

    <!-- Conferences -->
    <div class="admin-menu-group <?= $isOrganizerConferences ? 'open' : '' ?>">
      <button type="button" class="admin-menu-toggle" data-admin-menu-toggle>
        <span>
          <i class="bi bi-calendar-event"></i>
          Conferences
        </span>
        <i class="bi bi-chevron-down admin-menu-arrow"></i>
      </button>

      <div class="admin-submenu">
        <a
          href="<?= e(url('/organizer/add-conference')) ?>"
          class="<?= str_contains($dashPath, '/organizer/add-conference') ? 'active' : '' ?>">
          <i class="bi bi-plus-circle"></i>
          Add Conference
        </a>

        <a
          href="<?= e(url('/organizer/pending-conferences')) ?>"
          class="<?= str_contains($dashPath, '/organizer/pending-conferences') ? 'active' : '' ?>">
          <i class="bi bi-hourglass-split"></i>
          Pending
        </a>

        <a
          href="<?= e(url('/organizer/manage-conferences')) ?>"
          class="<?= str_contains($dashPath, '/organizer/manage-conferences') ? 'active' : '' ?>">
          <i class="bi bi-pencil-square"></i>
          Manage
        </a>

        <a
          href="<?= e(url('/organizer/rejected-conferences')) ?>"
          class="<?= str_contains($dashPath, '/organizer/rejected-conferences') ? 'active' : '' ?>">
          <i class="bi bi-x-octagon"></i>
          Rejected
        </a>

        <a
          href="<?= e(url('/organizer/completed-conferences')) ?>"
          class="<?= str_contains($dashPath, '/organizer/completed-conferences') ? 'active' : '' ?>">
          <i class="bi bi-archive"></i>
          Completed
        </a>
      </div>
    </div>

    <!-- Account -->
    <div class="admin-menu-group <?= $isOrganizerAccount ? 'open' : '' ?>">
      <button type="button" class="admin-menu-toggle" data-admin-menu-toggle>
        <span>
          <i class="bi bi-person"></i>
          Account
        </span>
        <i class="bi bi-chevron-down admin-menu-arrow"></i>
      </button>

      <div class="admin-submenu">
        <a
          href="<?= e(url('/organizer/profile')) ?>"
          class="<?= $isOrganizerAccount ? 'active' : '' ?>">
          <i class="bi bi-person-circle"></i>
          Profile
        </a>
      </div>
    </div>

  </div>

  <div class="admin-sidebar-logout">
    <a href="<?= e(url('/organizer/logout')) ?>" class="admin-logout-link">
      <i class="bi bi-box-arrow-left"></i>
      Logout
    </a>
  </div>

  <?php endif; ?>

<?php else: ?>
<?php
/* ==========================================================================
   ADMIN PORTAL
   ========================================================================== */

$isAdminDashboard = (bool) preg_match('#/admin/?$#', $dashPath);

$isConferenceControl =
    str_contains($dashPath, '/admin/pending-conferences') ||
    str_contains($dashPath, '/admin/manage-conferences') ||
    str_contains($dashPath, '/admin/completed-conferences') ||
    str_contains($dashPath, '/admin/organizers');

$isPartnersContent =
    str_contains($dashPath, '/admin/media-partners') ||
    str_contains($dashPath, '/admin/associates') ||
    str_contains($dashPath, '/admin/topics') ||
    str_contains($dashPath, '/admin/locations') ||
    str_contains($dashPath, '/admin/feedback') ||
    str_contains($dashPath, '/admin/subscribers');

$isAdminAccount = str_contains($dashPath, '/admin/profile')
               || str_contains($dashPath, '/admin/password-reset');
?>

  <a
    href="<?= e(url('/admin/')) ?>"
    class="dashboard-main-link <?= $isAdminDashboard ? 'active' : '' ?>">
    <i class="bi bi-grid-1x2"></i>
    <span>Dashboard</span>
  </a>

  <div class="admin-sidebar-accordion">

    <!-- Conference Control -->
    <div class="admin-menu-group <?= $isConferenceControl ? 'open' : '' ?>">
      <button type="button" class="admin-menu-toggle" data-admin-menu-toggle>
        <span>
          <i class="bi bi-calendar-event"></i>
          Conference Control
        </span>
        <i class="bi bi-chevron-down admin-menu-arrow"></i>
      </button>

      <div class="admin-submenu">
        <a
          href="<?= e(url('/admin/pending-conferences')) ?>"
          class="<?= str_contains($dashPath, '/admin/pending-conferences') ? 'active' : '' ?>">
          <i class="bi bi-hourglass-split"></i>
          Pending
        </a>

        <a
          href="<?= e(url('/admin/manage-conferences')) ?>"
          class="<?= str_contains($dashPath, '/admin/manage-conferences') ? 'active' : '' ?>">
          <i class="bi bi-calendar2-check"></i>
          Manage
        </a>

        <a
          href="<?= e(url('/admin/completed-conferences')) ?>"
          class="<?= str_contains($dashPath, '/admin/completed-conferences') ? 'active' : '' ?>">
          <i class="bi bi-archive"></i>
          Completed
        </a>

        <a
          href="<?= e(url('/admin/organizers')) ?>"
          class="<?= str_contains($dashPath, '/admin/organizers') ? 'active' : '' ?>">
          <i class="bi bi-people"></i>
          Organizers
        </a>
      </div>
    </div>

    <!-- Partners & Content -->
    <div class="admin-menu-group <?= $isPartnersContent ? 'open' : '' ?>">
      <button type="button" class="admin-menu-toggle" data-admin-menu-toggle>
        <span>
          <i class="bi bi-folder2-open"></i>
          Partners & Content
        </span>
        <i class="bi bi-chevron-down admin-menu-arrow"></i>
      </button>

      <div class="admin-submenu">
        <a
          href="<?= e(url('/admin/media-partners')) ?>"
          class="<?= str_contains($dashPath, '/admin/media-partners') ? 'active' : '' ?>">
          <i class="bi bi-newspaper"></i>
          Media Partners
        </a>

        <a
          href="<?= e(url('/admin/associates')) ?>"
          class="<?= str_contains($dashPath, '/admin/associates') ? 'active' : '' ?>">
          <i class="bi bi-diagram-3"></i>
          Associates
        </a>

        <a
          href="<?= e(url('/admin/topics')) ?>"
          class="<?= str_contains($dashPath, '/admin/topics') ? 'active' : '' ?>">
          <i class="bi bi-tags"></i>
          Topics
        </a>

        <a
          href="<?= e(url('/admin/locations')) ?>"
          class="<?= str_contains($dashPath, '/admin/locations') ? 'active' : '' ?>">
          <i class="bi bi-geo-alt"></i>
          Countries & Cities
        </a>

        <a
          href="<?= e(url('/admin/feedback')) ?>"
          class="<?= str_contains($dashPath, '/admin/feedback') ? 'active' : '' ?>">
          <i class="bi bi-chat-quote"></i>
          Feedback
        </a>

        <a
          href="<?= e(url('/admin/subscribers.php')) ?>"
          class="<?= str_contains($dashPath, '/admin/subscribers') ? 'active' : '' ?>">
          <i class="bi bi-envelope-check"></i>
          Subscribers
        </a>

        <a
          href="<?= e(url('/admin/blog')) ?>"
          class="<?= str_contains($dashPath, '/admin/blog') ? 'active' : '' ?>">
          <i class="bi bi-journal-text"></i>
          Blog
        </a>

        <a
          href="<?= e(url('/admin/content')) ?>"
          class="<?= str_contains($dashPath, '/admin/content') ? 'active' : '' ?>">
          <i class="bi bi-pencil-square"></i>
          Website Content
        </a>

      </div>
    </div>


<!-- =========================================================
     SITEMAP
     ========================================================= -->
<div class="admin-menu-group <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/sitemap') ? 'open' : '' ?>">

<button
    type="button"
    class="admin-menu-toggle"
    data-admin-menu-toggle
>
        <span>
            <i class="bi bi-diagram-3"></i>
            Sitemap
        </span>

        <i class="bi bi-chevron-down admin-menu-arrow"></i>
    </button>

    <div class="admin-submenu">

        <a
            href="<?= e(url('/admin/sitemap-generator')) ?>"
            class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/sitemap-generator') ? 'active' : '' ?>"
        >
            <i class="bi bi-file-earmark-code"></i>
            Sitemap Generator
        </a>

    </div>
</div>

    <!-- Account -->
    <div class="admin-menu-group <?= $isAdminAccount ? 'open' : '' ?>">
      <button type="button" class="admin-menu-toggle" data-admin-menu-toggle>
        <span>
          <i class="bi bi-person"></i>
          Account
        </span>
        <i class="bi bi-chevron-down admin-menu-arrow"></i>
      </button>

      <div class="admin-submenu">
        <a
          href="<?= e(url('/admin/profile')) ?>"
          class="<?= str_contains($dashPath, '/admin/profile') ? 'active' : '' ?>">
          <i class="bi bi-person-circle"></i>
          Profile
        </a>

        <a
          href="<?= e(url('/admin/password-reset')) ?>"
          class="<?= str_contains($dashPath, '/admin/password-reset') ? 'active' : '' ?>">
          <i class="bi bi-key"></i>
          Password Reset
        </a>
      </div>
    </div>

  </div>

  <div class="admin-sidebar-logout">
    <a href="<?= e(url('/admin/logout')) ?>" class="admin-logout-link">
      <i class="bi bi-box-arrow-left"></i>
      Logout
    </a>
  </div>

<?php endif; ?>

  </aside>

  <!-- ======================================================================
       Main content
       ====================================================================== -->
  <div class="dashboard-main">

    <header class="dashboard-topbar">

      <button
        class="btn btn-sm btn-outline-secondary d-lg-none"
        type="button"
        data-dashboard-toggle
        aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
      </button>

      <div class="flex-grow-1 min-w-0">
        <h1 class="h5 mb-0 fw-bold"><?= e($dashboardTitle) ?></h1>
        <div class="small text-secondary text-truncate">
          <?= e($dashboardUser['email'] ?? '') ?>
        </div>
      </div>

      <a
        href="<?= e(url('/')) ?>"
        target="_blank"
        rel="noopener"
        class="btn btn-sm btn-light border flex-shrink-0">
        <i class="bi bi-box-arrow-up-right me-1"></i>
        <span class="d-none d-sm-inline">View Website</span>
      </a>

    </header>

    <div class="dashboard-content">

      <?php foreach (pull_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
          <?= e($f['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>