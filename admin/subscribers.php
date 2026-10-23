<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   FILTER
   ========================================================================== */
$allowedFilters = ['all', 'active', 'inactive'];
$filter = strtolower(trim(getq('filter')));

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

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

    $stmt = db()->prepare('SELECT * FROM subscriber_emails WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $subscriber = $stmt->fetch();

    $redirectPath = '/admin/subscribers.php?filter='
        . rawurlencode($returnFilter)
        . '&page='
        . $returnPage;

    if (!$subscriber) {
        flash('danger', 'Subscriber not found.');
        redirect($redirectPath);
    }

    if ($action === 'activate') {
        db()->prepare("UPDATE subscriber_emails SET status = 'active' WHERE id = ?")
            ->execute([$id]);
        flash('success', 'Subscriber activated successfully.');

    } elseif ($action === 'deactivate') {
        db()->prepare("UPDATE subscriber_emails SET status = 'inactive' WHERE id = ?")
            ->execute([$id]);
        flash('success', 'Subscriber deactivated successfully.');

    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM subscriber_emails WHERE id = ?')->execute([$id]);
        flash('success', 'Subscriber permanently deleted.');

    } else {
        flash('danger', 'Invalid subscriber action.');
        redirect($redirectPath);
    }

    audit_log(
        'SUBSCRIBER_' . strtoupper($action),
        (string) $subscriber['email']
    );

    redirect($redirectPath);
}

/* ==========================================================================
   STATS
   ========================================================================== */
$counts = db()->query(
    "SELECT
        COUNT(*) AS total_count,
        SUM(status = 'active') AS active_count,
        SUM(status = 'inactive') AS inactive_count
     FROM subscriber_emails"
)->fetch() ?: [];

/* ==========================================================================
   PAGINATION + LIST
   ========================================================================== */
$page    = max(1, (int) getq('page', '1'));
$perPage = 50;

$where = '1=1';
if ($filter === 'active')   $where = "status = 'active'";
if ($filter === 'inactive') $where = "status = 'inactive'";

$totalRows  = (int) db()->query("SELECT COUNT(*) FROM subscriber_emails WHERE {$where}")->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

$rows = db()->query(
    "SELECT *
     FROM subscriber_emails
     WHERE {$where}
     ORDER BY created_at DESC, id DESC
     LIMIT {$perPage}
     OFFSET {$offset}"
)->fetchAll();

$from = $totalRows > 0 ? $offset + 1 : 0;
$to   = min($offset + $perPage, $totalRows);

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Subscribers';
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
        Newsletter
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Newsletter Subscribers
        <span class="badge text-bg-light ms-2" style="font-size:.75rem;vertical-align:middle;color:var(--color-text-strong);">
          <?= number_format((int) ($counts['total_count'] ?? 0)) ?> total
        </span>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Manage email addresses submitted through the website Newsletter form.
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
        <i class="bi bi-envelope"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format((int) ($counts['total_count'] ?? 0)) ?></div>
      <div class="small text-secondary mt-1">Total Subscribers</div>
    </div>
  </div>

  <div class="col">
    <div class="stat-card stat-accent-success h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-envelope-check"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format((int) ($counts['active_count'] ?? 0)) ?></div>
      <div class="small text-secondary mt-1">Active</div>
    </div>
  </div>

  <div class="col">
    <div class="stat-card stat-accent-secondary h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-envelope-dash"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format((int) ($counts['inactive_count'] ?? 0)) ?></div>
      <div class="small text-secondary mt-1">Inactive</div>
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
      <?= e(ucfirst($filter === 'all' ? 'All Subscribers' : $filter)) ?>
    </strong>
  </div>

  <form method="get" class="dashboard-filter-form" style="max-width:220px;">
    <select name="filter"
            class="form-select form-select-sm"
            onchange="this.form.submit()">
      <option value="all"      <?= $filter === 'all'      ? 'selected' : '' ?>>All Subscribers</option>
      <option value="active"   <?= $filter === 'active'   ? 'selected' : '' ?>>Active Only</option>
      <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
    </select>
  </form>

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
            <th>Email Address</th>
            <th>Status</th>
            <th>Subscribed Date</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>

          <?php foreach ($rows as $row): ?>
            <tr>

              <td>
                <a href="mailto:<?= e($row['email']) ?>"
                   class="fw-semibold text-decoration-none">
                  <i class="bi bi-envelope me-1 text-secondary"></i>
                  <?= e($row['email']) ?>
                </a>
              </td>

              <td>
                <?php if ($row['status'] === 'active'): ?>
                  <span class="badge text-bg-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Active
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">
                    <i class="bi bi-dash-circle me-1"></i>Inactive
                  </span>
                <?php endif; ?>
              </td>

              <td class="text-nowrap small">
                <i class="bi bi-clock me-1 text-secondary"></i>
                <?= e(format_date($row['created_at'])) ?>
              </td>

              <td class="text-end text-nowrap">
                <form method="post" class="d-inline-flex gap-1">

                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                  <input type="hidden" name="return_page" value="<?= $page ?>">

                  <?php if ($row['status'] === 'active'): ?>

                    <button type="submit"
                            name="action"
                            value="deactivate"
                            class="btn btn-sm btn-outline-secondary"
                            data-confirm="Deactivate this subscriber?">
                      <i class="bi bi-pause-circle me-1"></i>Deactivate
                    </button>

                  <?php else: ?>

                    <button type="submit"
                            name="action"
                            value="activate"
                            class="btn btn-sm btn-outline-success"
                            data-confirm="Activate this subscriber?">
                      <i class="bi bi-play-circle me-1"></i>Activate
                    </button>

                  <?php endif; ?>

                  <button type="submit"
                          name="action"
                          value="delete"
                          class="btn btn-sm btn-outline-danger"
                          data-confirm="Permanently delete this subscriber?">
                    <i class="bi bi-trash me-1"></i>Delete
                  </button>

                </form>
              </td>

            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalRows > 0): ?>
      <div class="p-4 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">

        <div class="small text-secondary">
          Showing <strong><?= $from ?></strong>–<strong><?= $to ?></strong>
          of <strong><?= number_format($totalRows) ?></strong> subscribers
        </div>

        <?php if ($totalPages > 1): ?>
          <?php
          $startPage = max(1, $page - 2);
          $endPage   = min($totalPages, $page + 2);
          ?>

          <nav aria-label="Subscriber pagination">
            <ul class="pagination pagination-sm mb-0">

              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="<?= e(url('/admin/subscribers.php?filter=' . rawurlencode($filter) . '&page=' . max(1, $page - 1))) ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>

              <?php if ($startPage > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="<?= e(url('/admin/subscribers.php?filter=' . rawurlencode($filter) . '&page=1')) ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
              <?php endif; ?>

              <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= e(url('/admin/subscribers.php?filter=' . rawurlencode($filter) . '&page=' . $p)) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>

              <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <li class="page-item">
                  <a class="page-link" href="<?= e(url('/admin/subscribers.php?filter=' . rawurlencode($filter) . '&page=' . $totalPages)) ?>"><?= $totalPages ?></a>
                </li>
              <?php endif; ?>

              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="<?= e(url('/admin/subscribers.php?filter=' . rawurlencode($filter) . '&page=' . min($totalPages, $page + 1))) ?>">
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
      <i class="bi bi-envelope"></i>
      <h2 class="h5 mt-3">No subscribers found</h2>
      <p class="text-secondary mb-3">
        <?php if ($filter !== 'all'): ?>
          No subscribers match this filter.
        <?php else: ?>
          No newsletter subscribers yet.
        <?php endif; ?>
      </p>

      <?php if ($filter !== 'all'): ?>
        <a href="<?= e(url('/admin/subscribers.php')) ?>" class="btn btn-outline-secondary btn-sm">
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

.stat-card.stat-accent-primary   { border-left: 4px solid var(--color-accent); }
.stat-card.stat-accent-success   { border-left: 4px solid var(--color-success); }
.stat-card.stat-accent-secondary { border-left: 4px solid var(--color-muted); }

.stat-card.stat-accent-primary i   { color: var(--color-accent); }
.stat-card.stat-accent-success i   { color: var(--color-success); }
.stat-card.stat-accent-secondary i { color: var(--color-muted); }
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>