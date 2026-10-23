<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   APPROVED ACTIVE CONFERENCES (PAGINATED — 50 per page)
   ==========================================================================
   Pending, Rejected and Completed conferences have their own pages.
   Manage Conferences shows only organizer's approved + active conferences.
   ========================================================================== */
$perPage = 50;

/* Total rows for this organizer */
$countStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM conferences
     WHERE organizer_id = ?
       AND status = 'approved'
       AND end_date >= CURDATE()"
);
$countStmt->execute([(int) $profile['id']]);
$activeCount = (int) $countStmt->fetchColumn();

$pager = paginate($activeCount, $perPage);

/* Fetch this page */
$stmt = db()->prepare(
    "SELECT
        c.*,
        ca.name AS topic_name,
        co.name AS country_name,
        ci.name AS city_name
     FROM conferences c
     LEFT JOIN categories ca ON ca.id = c.category_id
     LEFT JOIN countries  co ON co.id = c.country_id
     LEFT JOIN cities     ci ON ci.id = c.city_id
     WHERE c.organizer_id = ?
       AND c.status = 'approved'
       AND c.end_date >= CURDATE()
     ORDER BY c.created_at DESC, c.id DESC
     LIMIT ? OFFSET ?"
);

$stmt->bindValue(1, (int) $profile['id'], PDO::PARAM_INT);
$stmt->bindValue(2, $pager['per_page'],    PDO::PARAM_INT);
$stmt->bindValue(3, $pager['offset'],      PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Manage Conferences';
$dashboardRole  = 'organizer';

require dirname(__DIR__) . '/includes/dashboard-header.php';
?>

<!-- ==========================================================================
     INFO BANNER
     ========================================================================== -->
<div class="organizer-welcome-banner mb-4 reveal">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">

    <div>
      <div class="section-kicker mb-1" style="color:var(--color-highlight);">
        Live & Published
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Approved Conferences
        <span class="badge text-bg-light ms-2" style="font-size:.75rem;vertical-align:middle;color:var(--color-text-strong);">
          <?= number_format($activeCount) ?> active
        </span>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Conferences approved by the administrator and published on the website.
      </p>
    </div>

    <a href="<?= e(url('/organizer/add-conference')) ?>"
       class="btn btn-light btn-sm flex-shrink-0">
      <i class="bi bi-plus-circle me-1"></i>
      Add Conference
    </a>

  </div>
</div>

<!-- ==========================================================================
     TABLE
     ========================================================================== -->
<div class="table-card reveal">

  <?php if ($rows): ?>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Conference Title</th>
            <th>Topic</th>
            <th>Start Date</th>
            <th>Location</th>
            <th>Status</th>
            <th>View</th>
          </tr>
        </thead>
        <tbody>

          <?php foreach ($rows as $conference): ?>
            <?php $isDeactivated = (int) $conference['is_deactivated'] === 1; ?>

            <tr>

              <td>
                <?php if (!$isDeactivated): ?>
                  <a href="<?= e(url('/conference/' . $conference['slug'])) ?>"
                     target="_blank"
                     rel="noopener noreferrer"
                     class="fw-semibold text-decoration-none d-inline-flex align-items-center gap-1"
                     title="Open public conference page">
                    <?= e($conference['title']) ?>
                    <i class="bi bi-box-arrow-up-right small text-secondary"></i>
                  </a>
                <?php else: ?>
                  <strong><?= e($conference['title']) ?></strong>
                <?php endif; ?>
              </td>

              <td>
                <span class="small text-secondary">
                  <?= e($conference['topic_name'] ?: '—') ?>
                </span>
              </td>

              <td class="text-nowrap small">
                <?= e(format_date($conference['start_date'])) ?>
              </td>

              <td>
                <span class="small text-secondary">
                  <?= e(trim(
                      (string) ($conference['city_name'] ?? '') . ', ' .
                      (string) ($conference['country_name'] ?? ''),
                      ', '
                  )) ?: '—' ?>
                </span>
              </td>

              <td>
                <?php if ($isDeactivated): ?>
                  <span class="badge text-bg-secondary">
                    <i class="bi bi-eye-slash me-1"></i>Deactivated
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Approved
                  </span>
                <?php endif; ?>
              </td>

              <td>
                <?php if (!$isDeactivated): ?>
                  <a class="btn btn-sm btn-outline-primary"
                     href="<?= e(url('/conference/' . $conference['slug'])) ?>"
                     target="_blank"
                     rel="noopener noreferrer">
                    <i class="bi bi-eye me-1"></i>View
                  </a>
                <?php else: ?>
                  <span class="text-secondary small">
                    <i class="bi bi-slash-circle me-1"></i>Not public
                  </span>
                <?php endif; ?>
              </td>

            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

  <?php else: ?>

    <div class="empty-state bg-white">
      <i class="bi bi-calendar2-check"></i>
      <h2 class="h5 mt-3">No approved conferences</h2>
      <p class="text-secondary mb-3">
        Once your conference is approved by the administrator, it will appear here.
      </p>
      <a href="<?= e(url('/organizer/pending-conferences')) ?>"
         class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-hourglass-split me-1"></i>
        View Pending Conferences
      </a>
    </div>

  <?php endif; ?>

</div>

<!-- ========== PAGINATION ========== -->
<?php if ($pager['pages'] > 1): ?>
  <?php
  $currentPage = (int) $pager['page'];
  $totalPages  = (int) $pager['pages'];
  $startPage   = max(1, $currentPage - 2);
  $endPage     = min($totalPages, $currentPage + 2);

  $pageUrl = static function (int $p) : string {
      return '?page=' . $p;
  };
  ?>

  <nav class="mt-4" aria-label="Manage conferences pagination">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
      <div class="small text-secondary">
        Showing
        <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $activeCount) ?></strong>
        of <strong><?= number_format($activeCount) ?></strong> approved conferences
      </div>
    </div>

    <ul class="pagination justify-content-center flex-wrap">

      <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $currentPage > 1 ? e($pageUrl($currentPage - 1)) : '#' ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
      </li>

      <?php if ($startPage > 1): ?>
        <li class="page-item"><a class="page-link" href="<?= e($pageUrl(1)) ?>">1</a></li>
        <?php if ($startPage > 2): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
      <?php endif; ?>

      <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
          <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
        </li>
      <?php endfor; ?>

      <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= e($pageUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
      <?php endif; ?>

      <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $currentPage < $totalPages ? e($pageUrl($currentPage + 1)) : '#' ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
      </li>

    </ul>
  </nav>
<?php endif; ?>

<!-- Organizer banner style -->
<style>
.organizer-welcome-banner {
  background:
    radial-gradient(circle at 90% 10%, rgba(249, 115, 22, 0.35), transparent 55%),
    radial-gradient(circle at 10% 90%, rgba(37, 99, 235, 0.20), transparent 55%),
    linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
  border-radius: var(--radius-xl);
  padding: 1.5rem 1.75rem;
  position: relative;
  overflow: hidden;
}

.organizer-welcome-banner::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
  background-size: 28px 28px;
  pointer-events: none;
}

.organizer-welcome-banner > * {
  position: relative;
  z-index: 1;
}

@media (max-width: 767.98px) {
  .organizer-welcome-banner { padding: 1.25rem; }
}
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>