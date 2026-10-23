<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   FILTER
   ========================================================================== */
$allowedFilters = ['all', 'pending', 'active'];
$filter = strtolower(trim(getq('filter')));

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

/* ==========================================================================
   PAGINATION
   ========================================================================== */
$page    = max(1, (int) getq('page', '1'));
$perPage = 50;

/* ==========================================================================
   ACTIONS
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    $id           = (int) post('id');
    $action       = post('action');
    $returnFilter = strtolower(trim(post('return_filter')));
    $returnPage   = max(1, (int) post('return_page'));

    if (!in_array($returnFilter, $allowedFilters, true)) {
        $returnFilter = 'all';
    }

    $stmt = db()->prepare('SELECT * FROM user_feedbacks WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $feedbackItem = $stmt->fetch();

    $redirectPath = '/admin/feedback';
    $qsParts = [];
    if ($returnFilter !== 'all') $qsParts[] = 'filter=' . rawurlencode($returnFilter);
    if ($returnPage > 1)        $qsParts[] = 'page=' . $returnPage;
    if ($qsParts) $redirectPath .= '?' . implode('&', $qsParts);

    if (!$feedbackItem) {
        flash('danger', 'Feedback not found.');
        redirect($redirectPath);
    }

    if ($action === 'activate') {
        db()->prepare(
            "UPDATE user_feedbacks SET status = 'approved', updated_at = NOW() WHERE id = ?"
        )->execute([$id]);

        flash('success', 'Feedback activated and is now visible on the public Home Page.');

    } elseif ($action === 'deactivate') {
        db()->prepare(
            "UPDATE user_feedbacks SET status = 'pending', updated_at = NOW() WHERE id = ?"
        )->execute([$id]);

        flash('success', 'Feedback deactivated and hidden from the public Home Page.');

    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM user_feedbacks WHERE id = ?')->execute([$id]);

        flash('success', 'Feedback permanently deleted.');

    } else {
        flash('danger', 'Invalid feedback action.');
        redirect($redirectPath);
    }

    audit_log('FEEDBACK_' . strtoupper($action), 'Feedback ID ' . $id);

    redirect($redirectPath);
}

/* ==========================================================================
   STATS
   ========================================================================== */
$counts = db()->query(
    "SELECT
        COUNT(*) AS total_count,
        SUM(status = 'pending') AS pending_count,
        SUM(status = 'approved') AS active_count
     FROM user_feedbacks"
)->fetch() ?: [];

/* ==========================================================================
   LIST QUERY (PAGINATED)
   ========================================================================== */
$where = "status IN ('pending', 'approved')";

if ($filter === 'pending') {
    $where = "status = 'pending'";
} elseif ($filter === 'active') {
    $where = "status = 'approved'";
}

$filteredTotal = (int) db()->query(
    "SELECT COUNT(*) FROM user_feedbacks WHERE {$where}"
)->fetchColumn();

$totalPages = max(1, (int) ceil($filteredTotal / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

$rows = db()->query(
    "SELECT *
     FROM user_feedbacks
     WHERE {$where}
     ORDER BY
        CASE WHEN status = 'pending' THEN 0 ELSE 1 END ASC,
        created_at DESC,
        id DESC
     LIMIT {$perPage} OFFSET {$offset}"
)->fetchAll();

$from = $filteredTotal > 0 ? $offset + 1 : 0;
$to   = min($offset + $perPage, $filteredTotal);

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Feedback';
$dashboardRole  = 'admin';

require dirname(__DIR__) . '/includes/dashboard-header.php';
?>

<!-- ==========================================================================
     INFO BANNER
     ========================================================================== -->
<div class="admin-welcome-banner mb-4 reveal">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <div class="section-kicker mb-1" style="color:var(--color-highlight);">
        Community Voice
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Customer Feedback
        <?php if ((int) ($counts['pending_count'] ?? 0) > 0): ?>
          <span class="badge text-bg-warning ms-2" style="font-size:.75rem;vertical-align:middle;">
            <?= number_format((int) $counts['pending_count']) ?> pending
          </span>
        <?php else: ?>
          <span class="badge text-bg-success ms-2" style="font-size:.75rem;vertical-align:middle;">
            All reviewed
          </span>
        <?php endif; ?>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Activate feedback to publish it on the public Home Page. Deactivate to hide it.
      </p>
    </div>
  </div>
</div>

<!-- ==========================================================================
     STAT CARDS
     ========================================================================== -->
<div class="row row-cols-1 row-cols-md-3 g-4 mb-4 reveal-stagger">

  <div class="col">
    <div class="stat-card stat-accent-primary h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-chat-square-text"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format((int) ($counts['total_count'] ?? 0)) ?></div>
      <div class="small text-secondary mt-1">Total Feedback</div>
    </div>
  </div>

  <div class="col">
    <div class="stat-card stat-accent-warning h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-hourglass-split"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format((int) ($counts['pending_count'] ?? 0)) ?></div>
      <div class="small text-secondary mt-1">Pending Review</div>
    </div>
  </div>

  <div class="col">
    <div class="stat-card stat-accent-success h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-check-circle"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format((int) ($counts['active_count'] ?? 0)) ?></div>
      <div class="small text-secondary mt-1">Active on Site</div>
    </div>
  </div>

</div>

<!-- ==========================================================================
     FILTER
     ========================================================================== -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 reveal">

  <div class="small text-secondary">
    <i class="bi bi-funnel me-1"></i>
    Filter:
    <strong class="text-dark">
      <?= e(ucfirst($filter === 'all' ? 'All Feedback' : $filter)) ?>
    </strong>
  </div>

  <form method="get" class="dashboard-filter-form" style="max-width:220px;">
    <select name="filter"
            class="form-select form-select-sm"
            onchange="this.form.submit()">
      <option value="all"     <?= $filter === 'all'     ? 'selected' : '' ?>>All Feedback</option>
      <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending Only</option>
      <option value="active"  <?= $filter === 'active'  ? 'selected' : '' ?>>Active Only</option>
    </select>
  </form>

</div>

<!-- ==========================================================================
     TABLE
     ========================================================================== -->
<div class="table-card reveal">

  <?php if ($rows): ?>

    <div class="table-responsive">
      <table class="table feedback-table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="min-width:180px;">Name</th>
            <th style="min-width:90px;">Country</th>
            <th style="min-width:90px;">City</th>
            <th style="min-width:100px;">Rating</th>
            <th>Feedback</th>
            <th style="min-width:100px;">Status</th>
            <th class="text-end" style="min-width:190px;">Actions</th>
          </tr>
        </thead>
        <tbody>

          <?php foreach ($rows as $row): ?>
            <tr>

              <td>
                <div class="d-flex align-items-center gap-3">

                  <?php if (!empty($row['image'])): ?>
                    <img src="<?= e(absolute_media_url($row['image'])) ?>"
                         alt="<?= e($row['name']) ?>"
                         style="width:44px;height:44px;object-fit:cover;border-radius:50%;border:1px solid var(--color-border);">
                  <?php else: ?>
                    <div class="avatar-circle" style="width:44px;height:44px;font-size:1rem;">
                      <i class="bi bi-person"></i>
                    </div>
                  <?php endif; ?>

                  <div style="white-space:nowrap;">
                    <strong><?= e($row['name']) ?></strong>
                    <div class="small text-secondary">
                      <i class="bi bi-clock me-1"></i>
                      <?= e(date('j M Y', strtotime((string) $row['created_at']))) ?>
                    </div>
                  </div>

                </div>
              </td>

              <td>
                <span class="small"><?= e($row['country'] ?: '—') ?></span>
              </td>

              <td>
                <span class="small"><?= e($row['organization'] ?: '—') ?></span>
              </td>

              <td class="text-nowrap">
                <span class="text-warning">
                  <?php
                  $rating = max(1, min(5, (int) $row['rating']));
                  for ($star = 1; $star <= 5; $star++):
                  ?>
                    <i class="bi <?= $star <= $rating ? 'bi-star-fill' : 'bi-star' ?>"></i>
                  <?php endfor; ?>
                </span>
              </td>

              <td>
                <span class="small feedback-message"
                      title="<?= e($row['message']) ?>">
                  <?= e($row['message']) ?>
                </span>
              </td>

              <td>
                <?php if ($row['status'] === 'approved'): ?>
                  <span class="badge text-bg-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Active
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-warning">
                    <i class="bi bi-hourglass-split me-1"></i>Pending
                  </span>
                <?php endif; ?>
              </td>

              <td class="text-end text-nowrap">
                <form method="post" class="d-inline-flex gap-1 align-items-center">

                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                  <input type="hidden" name="return_page" value="<?= (int) $page ?>">

                  <?php if ($row['status'] === 'approved'): ?>

                    <button type="submit"
                            name="action"
                            value="deactivate"
                            class="btn btn-sm btn-outline-secondary"
                            title="Deactivate"
                            data-confirm="Deactivate this feedback? It will be hidden from the public Home Page.">
                      <i class="bi bi-pause-circle"></i>
                      <span class="d-none d-xxl-inline ms-1">Deactivate</span>
                    </button>

                  <?php else: ?>

                    <button type="submit"
                            name="action"
                            value="activate"
                            class="btn btn-sm btn-outline-success"
                            title="Activate"
                            data-confirm="Activate this feedback? It will appear on the public Home Page.">
                      <i class="bi bi-check-circle"></i>
                      <span class="d-none d-xxl-inline ms-1">Activate</span>
                    </button>

                  <?php endif; ?>

                  <button type="submit"
                          name="action"
                          value="delete"
                          class="btn btn-sm btn-outline-danger"
                          title="Delete"
                          data-confirm="Permanently delete this feedback? This cannot be undone.">
                    <i class="bi bi-trash"></i>
                    <span class="d-none d-xxl-inline ms-1">Delete</span>
                  </button>

                </form>
              </td>

            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <!-- ==================== PAGINATION ==================== -->
    <?php if ($filteredTotal > 0): ?>
      <div class="p-4 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">

        <div class="small text-secondary">
          Showing <strong><?= $from ?></strong>–<strong><?= $to ?></strong>
          of <strong><?= number_format($filteredTotal) ?></strong> entries
        </div>

        <?php if ($totalPages > 1): ?>
          <?php
          $startPage = max(1, $page - 2);
          $endPage   = min($totalPages, $page + 2);

          $buildQs = static function (int $p) use ($filter): string {
              $qs = [];
              if ($filter !== 'all') $qs[] = 'filter=' . rawurlencode($filter);
              if ($p > 1)            $qs[] = 'page=' . $p;
              return $qs ? '?' . implode('&', $qs) : '';
          };
          ?>

          <nav aria-label="Feedback pagination">
            <ul class="pagination pagination-sm mb-0">

              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="<?= e(url('/admin/feedback' . $buildQs(max(1, $page - 1)))) ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>

              <?php if ($startPage > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="<?= e(url('/admin/feedback' . $buildQs(1))) ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
              <?php endif; ?>

              <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= e(url('/admin/feedback' . $buildQs($p))) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>

              <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <li class="page-item">
                  <a class="page-link" href="<?= e(url('/admin/feedback' . $buildQs($totalPages))) ?>"><?= $totalPages ?></a>
                </li>
              <?php endif; ?>

              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="<?= e(url('/admin/feedback' . $buildQs(min($totalPages, $page + 1)))) ?>">
                  <i class="bi bi-chevron-right"></i>
                </a>
              </li>

            </ul>
          </nav>
        <?php endif; ?>

      </div>
    <?php endif; ?>

  <?php else: ?>

    <div class="empty-state bg-white">
      <i class="bi bi-chat-square-quote"></i>
      <h2 class="h5 mt-3">No feedback found</h2>
      <p class="text-secondary mb-3">
        <?php if ($filter !== 'all'): ?>
          No feedback matches this filter.
        <?php else: ?>
          No feedback submitted yet.
        <?php endif; ?>
      </p>

      <?php if ($filter !== 'all'): ?>
        <a href="<?= e(url('/admin/feedback')) ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-x-circle me-1"></i>Clear Filter
        </a>
      <?php endif; ?>
    </div>

  <?php endif; ?>

</div>

<!-- Admin banner + stat card accents -->
<style>
.admin-welcome-banner {
  background:
    radial-gradient(circle at 90% 10%, rgba(37, 99, 235, 0.35), transparent 55%),
    radial-gradient(circle at 10% 90%, rgba(249, 115, 22, 0.20), transparent 55%),
    linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
  border-radius: var(--radius-xl);
  padding: 1.5rem 1.75rem;
  position: relative;
  overflow: hidden;
}

.admin-welcome-banner::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
  background-size: 28px 28px;
  pointer-events: none;
}

.admin-welcome-banner > * {
  position: relative;
  z-index: 1;
}

.stat-card.stat-accent-primary  { border-left: 4px solid var(--color-accent); }
.stat-card.stat-accent-success  { border-left: 4px solid var(--color-success); }
.stat-card.stat-accent-warning  { border-left: 4px solid var(--color-warning); }

.stat-card.stat-accent-primary i { color: var(--color-accent); }
.stat-card.stat-accent-success i { color: var(--color-success); }
.stat-card.stat-accent-warning i { color: var(--color-warning); }

/* ==========================================================================
   Feedback table — fit within screen, no horizontal scroll
   ========================================================================== */
.feedback-table {
  table-layout: auto;
  width: 100%;
  word-break: break-word;
}

.feedback-table td {
  vertical-align: middle;
  overflow-wrap: anywhere;
}

/* Feedback message cell — 2 lines max, ellipsis, hover shows full text */
.feedback-table .feedback-message {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.45;
  max-width: 100%;
}

/* Prevent name column from collapsing */
.feedback-table td:first-child {
  white-space: nowrap;
}

/* On smaller screens, allow tables to shrink further */
@media (max-width: 1399.98px) {
  .feedback-table {
    font-size: 0.82rem;
  }

  .feedback-table .btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
  }
}

/* Full-width dashboard container on this page */
.table-card {
  overflow: hidden;
}
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>