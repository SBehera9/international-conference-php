<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   REJECTED LIST (PAGINATED — 50 per page)
   ========================================================================== */
$perPage = 50;

/* Total rows for this organizer */
$countStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM conferences
     WHERE organizer_id = ?
       AND status = 'rejected'
       AND end_date >= CURDATE()"
);
$countStmt->execute([(int) $profile['id']]);
$rejectedCount = (int) $countStmt->fetchColumn();

$pager = paginate($rejectedCount, $perPage);

/* Fetch this page */
$stmt = db()->prepare(
    "SELECT
        c.*,
        ca.name AS topic_name
     FROM conferences c
     LEFT JOIN categories ca ON ca.id = c.category_id
     WHERE c.organizer_id = ?
       AND c.status = 'rejected'
       AND c.end_date >= CURDATE()
     ORDER BY c.updated_at DESC, c.id DESC
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
$dashboardTitle = 'Rejected Conferences';
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
        Action Required
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Rejected Conferences
        <?php if ($rejectedCount > 0): ?>
          <span class="badge text-bg-danger ms-2" style="font-size:.75rem;vertical-align:middle;">
            <?= number_format($rejectedCount) ?> need<?= $rejectedCount === 1 ? 's' : '' ?> attention
          </span>
        <?php else: ?>
          <span class="badge text-bg-success ms-2" style="font-size:.75rem;vertical-align:middle;">
            None rejected
          </span>
        <?php endif; ?>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Review the administrator's rejection reason, edit the conference, and resubmit it for review.
      </p>
    </div>

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
            <th>Reason for Rejection</th>
            <th>Last Updated</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>

          <?php foreach ($rows as $conference): ?>
            <tr>

              <td>
                <strong><?= e($conference['title']) ?></strong>
              </td>

              <td>
                <span class="small text-secondary">
                  <?= e($conference['topic_name'] ?: '—') ?>
                </span>
              </td>

              <td style="min-width:260px;max-width:440px;">
                <div class="rejection-reason">
                  <i class="bi bi-exclamation-triangle-fill me-1"></i>
                  <span class="small">
                    <?= e($conference['rejection_reason'] ?: 'No reason supplied.') ?>
                  </span>
                </div>
              </td>

              <td class="text-nowrap small">
                <i class="bi bi-clock me-1 text-secondary"></i>
                <?= e(format_date($conference['updated_at'])) ?>
              </td>

              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-action"
                   href="<?= e(url('/organizer/add-conference?id=' . (int) $conference['id'])) ?>">
                  <i class="bi bi-pencil-square me-1"></i>
                  Edit &amp; Resubmit
                </a>
              </td>

            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

  <?php else: ?>

    <div class="empty-state bg-white">
      <i class="bi bi-check-circle text-success"></i>
      <h2 class="h5 mt-3">No rejected conferences</h2>
      <p class="text-secondary mb-0">
        Great! None of your conferences have been rejected.
      </p>
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

  <nav class="mt-4" aria-label="Rejected conferences pagination">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
      <div class="small text-secondary">
        Showing
        <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $rejectedCount) ?></strong>
        of <strong><?= number_format($rejectedCount) ?></strong> rejected conferences
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

<!-- Organizer banner style + rejection reason styling -->
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

/* Rejection reason callout */
.rejection-reason {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  padding: 0.5rem 0.75rem;
  background: rgba(239, 68, 68, 0.06);
  border-left: 3px solid var(--color-error);
  border-radius: var(--radius-sm);
  color: var(--color-error-dark);
  line-height: 1.5;
}

.rejection-reason i {
  flex-shrink: 0;
  margin-top: 0.15rem;
}

@media (max-width: 767.98px) {
  .organizer-welcome-banner { padding: 1.25rem; }
}
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>