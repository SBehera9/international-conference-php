<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   ORGANIZER COUNT + PAGINATION
   ========================================================================== */
$total = (int) db()->query(
    "SELECT COUNT(*)
     FROM organizers o
     JOIN users u ON u.id = o.user_id
     WHERE o.is_profile_complete = 1
       AND o.is_suspended = 0
       AND u.status = 'active'
       AND EXISTS (
           SELECT 1 FROM conferences c
           WHERE c.organizer_id = o.id
             AND c.status = 'approved'
             AND c.is_deactivated = 0
       )"
)->fetchColumn();

$pager = paginate($total, 50);

/* ==========================================================================
   ORGANIZER LIST
   ========================================================================== */
$stmt = db()->prepare(
    "SELECT
        o.*,
        (
            SELECT COUNT(*)
            FROM conferences c
            WHERE c.organizer_id = o.id
              AND c.status = 'approved'
              AND c.is_deactivated = 0
        ) AS conference_count
     FROM organizers o
     JOIN users u ON u.id = o.user_id
     WHERE o.is_profile_complete = 1
       AND o.is_suspended = 0
       AND u.status = 'active'
       AND EXISTS (
           SELECT 1 FROM conferences c
           WHERE c.organizer_id = o.id
             AND c.status = 'approved'
             AND c.is_deactivated = 0
       )
     ORDER BY o.is_verified DESC, conference_count DESC, o.organization_name
     LIMIT ? OFFSET ?"
);

$stmt->bindValue(1, $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue(2, $pager['offset'], PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll();

/* ==========================================================================
   SEO — per-page keywords + CollectionPage schema
   ========================================================================== */
$pageKeywords = page_keywords('organizers');

$organizerCollectionName = 'Conference Organizers';
$organizerCollectionDesc = 'Browse organizer profiles that have published approved international conferences.';

$seo = page_seo([
    'title'       => $organizerCollectionName,
    'description' => $organizerCollectionDesc,
    'keywords'    => $pageKeywords,
    'canonical'   => url('/organizers'),
    'json_ld'     => [
        collection_schema(
            $organizerCollectionName,
            url('/organizers'),
            $organizerCollectionDesc,
            $pageKeywords
        ),
        breadcrumb_schema([
            ['name' => 'Home',       'url' => url('/')],
            ['name' => 'Organizers', 'url' => url('/organizers')],
        ]),
    ],
]);

require __DIR__ . '/includes/header.php';
?>

<style>
/* ==========================================================================
   ORGANIZER CARD FIX
   ========================================================================== */

.organizer-card {
    overflow: hidden;
}

.organizer-card-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.organizer-card-logo-wrap {
    flex: 0 0 auto;
}

.organizer-card-info {
    flex: 1 1 auto;
    min-width: 0;
}

.organizer-card-title-row {
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    min-width: 0;
}

.organizer-card-title {
    flex: 1 1 auto;
    min-width: 0;
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.35;
    color: var(--bs-body-color);

    /* Keep long names inside card */
    overflow-wrap: anywhere;
    word-break: normal;
}

.organizer-card-verified {
    flex: 0 0 auto;
    margin-top: 0.12rem;
    font-size: 1rem;
}

.organizer-card-location {
    margin-top: 0.3rem;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.organizer-card-description {
    min-height: 60px;
    line-height: 1.55;
}

.organizer-card-footer {
    gap: 0.75rem;
}

.organizer-card-footer .view-profile-link {
    white-space: nowrap;
}

@media (max-width: 575.98px) {
    .organizer-card-title {
        font-size: 1rem;
    }

    .organizer-card-footer {
        align-items: flex-start !important;
        flex-direction: column;
    }
}
</style>

<!-- ==========================================================================
     PAGE HERO — the single H1 for this page
     ========================================================================== -->
<section class="page-hero">
  <div class="container">

    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item">
          <a href="<?= e(url('/')) ?>">Home</a>
        </li>

        <li class="breadcrumb-item active" aria-current="page">
          Organizers
        </li>
      </ol>
    </nav>

    <div class="section-kicker">
      Trusted conference hosts
    </div>

    <h1 class="display-5 mt-2">
      Conference Organizers
    </h1>

    <p class="lead text-secondary col-lg-9 mb-0">
      Explore public profiles of organizers with approved conference listings.
      <br>

      <strong class="text-brand">
        <?= number_format($total) ?>
      </strong>

      organizer<?= $total === 1 ? '' : 's' ?> found.
    </p>

  </div>
</section>

<!-- ==========================================================================
     ORGANIZER GRID
     ========================================================================== -->
<section class="py-5">
  <div class="container">

    <!-- Visible H2 for this page -->
    <h2 class="section-title h3 fw-bold mb-4">Organizer Directory</h2>

    <?php if ($rows): ?>

      <div class="row g-4 reveal-stagger">

        <?php foreach ($rows as $organizer): ?>

          <?php
          $logo = trim((string)($organizer['logo'] ?? ''));

          if ($logo !== '' && !preg_match('#^https?://#i', $logo)) {
              $logo = url($logo);
          }

          $location = trim(
              (string)($organizer['city'] ?? '') .
              (
                  !empty($organizer['city']) &&
                  !empty($organizer['country'])
                      ? ', '
                      : ''
              ) .
              (string)($organizer['country'] ?? '')
          );
          ?>

          <div class="col-sm-6 col-lg-4 col-xl-3">

            <article class="organizer-card h-100">

              <!-- ==========================================================
                   ORGANIZER HEADER
                   ========================================================== -->
              <div class="organizer-card-header">

                <div class="organizer-card-logo-wrap">

                  <?php if ($logo !== ''): ?>

                    <img
                      src="<?= e($logo) ?>"
                      alt="<?= e($organizer['organization_name']) ?> logo"
                      class="organizer-logo"
                      loading="lazy"
                    >

                  <?php else: ?>

                    <div
                      class="avatar-circle"
                      style="width:70px;height:70px;font-size:24px;"
                    >
                      <i class="bi bi-building"></i>
                    </div>

                  <?php endif; ?>

                </div>

                <div class="organizer-card-info">

                  <div class="organizer-card-title-row">

                    <!-- Demoted from H2 to H3 -->
                    <h3 class="organizer-card-title">
                      <?= e($organizer['organization_name']) ?>
                    </h3>

                    <?php if (!empty($organizer['is_verified'])): ?>

                      <i
                        class="bi bi-patch-check-fill text-brand organizer-card-verified"
                        title="Verified organizer"
                        aria-label="Verified organizer"
                      ></i>

                    <?php endif; ?>

                  </div>

                  <div class="small text-secondary organizer-card-location">
                    <?= e($location ?: 'International Organizer') ?>
                  </div>

                </div>

              </div>

              <!-- ==========================================================
                   DESCRIPTION
                   ========================================================== -->
              <p class="small text-secondary mb-3 organizer-card-description">
                <?= e(
                    excerpt(
                        (string)($organizer['about_organization'] ?? ''),
                        150
                    )
                ) ?>
              </p>

              <!-- ==========================================================
                   CARD FOOTER
                   ========================================================== -->
              <div class="d-flex justify-content-between align-items-center pt-3 border-top organizer-card-footer">

                <span class="badge text-bg-light border">

                  <i class="bi bi-calendar2-check me-1"></i>

                  <?= (int)$organizer['conference_count'] ?>
                  published

                </span>

                <a
                  class="text-brand text-decoration-none fw-semibold view-profile-link"
                  href="<?= e(
                      url(
                          '/organizers/' .
                          $organizer['slug']
                      )
                  ) ?>"
                >
                  View Profile
                  <i class="bi bi-arrow-right ms-1"></i>
                </a>

              </div>

            </article>

          </div>

        <?php endforeach; ?>

      </div>

      <!-- ==========================================================================
           PAGINATION
           ========================================================================== -->
      <?php if ($pager['pages'] > 1): ?>

        <?php
        $currentPage = (int)$pager['page'];
        $totalPages  = (int)$pager['pages'];

        $startPage = max(1, $currentPage - 2);
        $endPage   = min($totalPages, $currentPage + 2);

        $pageUrl = static fn(int $p): string => '?page=' . $p;
        ?>

        <nav
          class="mt-5"
          aria-label="Organizer pagination"
        >

          <ul class="pagination justify-content-center flex-wrap">

            <!-- Previous -->
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">

              <a
                class="page-link"
                href="<?= $currentPage > 1
                    ? e($pageUrl($currentPage - 1))
                    : '#' ?>"
              >
                <i class="bi bi-chevron-left"></i>
              </a>

            </li>

            <!-- First page -->
            <?php if ($startPage > 1): ?>

              <li class="page-item">

                <a
                  class="page-link"
                  href="<?= e($pageUrl(1)) ?>"
                >
                  1
                </a>

              </li>

              <?php if ($startPage > 2): ?>

                <li class="page-item disabled">
                  <span class="page-link">…</span>
                </li>

              <?php endif; ?>

            <?php endif; ?>

            <!-- Current range -->
            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>

              <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">

                <a
                  class="page-link"
                  href="<?= e($pageUrl($p)) ?>"
                >
                  <?= $p ?>
                </a>

              </li>

            <?php endfor; ?>

            <!-- Last page -->
            <?php if ($endPage < $totalPages): ?>

              <?php if ($endPage < $totalPages - 1): ?>

                <li class="page-item disabled">
                  <span class="page-link">…</span>
                </li>

              <?php endif; ?>

              <li class="page-item">

                <a
                  class="page-link"
                  href="<?= e($pageUrl($totalPages)) ?>"
                >
                  <?= $totalPages ?>
                </a>

              </li>

            <?php endif; ?>

            <!-- Next -->
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">

              <a
                class="page-link"
                href="<?= $currentPage < $totalPages
                    ? e($pageUrl($currentPage + 1))
                    : '#' ?>"
              >
                <i class="bi bi-chevron-right"></i>
              </a>

            </li>

          </ul>

        </nav>

      <?php endif; ?>

    <?php else: ?>

      <!-- ==========================================================================
           EMPTY STATE
           ========================================================================== -->
      <div class="empty-state bg-soft rounded-4">

        <i class="bi bi-people"></i>

        <!-- Demoted from H2 to H3 -->
        <h3 class="h5 mt-3">
          No organizers yet
        </h3>

        <p class="text-secondary mb-0">
          Organizer profiles will appear once conferences are approved.
        </p>

      </div>

    <?php endif; ?>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>