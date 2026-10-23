<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   FILTER + PAGINATION
   ========================================================================== */
$allowedFilters = ['all', 'approved', 'pending', 'active', 'hidden'];
$filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
if (!in_array($filter, $allowedFilters, true)) $filter = 'all';

$page    = max(1, (int) ($_GET['page'] ?? 1));
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

    if (!in_array($returnFilter, $allowedFilters, true)) $returnFilter = 'all';

    $redirectTo = '/admin/media-partners?filter=' . rawurlencode($returnFilter)
                . '&page=' . $returnPage;

    $stmt = db()->prepare('SELECT id, name, status, is_deactivated FROM media_partners WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $partner = $stmt->fetch();

    if (!$partner) {
        flash('danger', 'Media partner not found.');
        redirect($redirectTo);
    }

    /* ---------- APPROVE ---------- */
    if ($action === 'approve') {
        db()->prepare(
            "UPDATE media_partners
             SET status = 'approved', is_deactivated = 0, updated_at = NOW()
             WHERE id = ?"
        )->execute([$id]);

        audit_log('MEDIA_PARTNER_APPROVED', (string) $partner['name']);
        flash('success', 'Media partner approved successfully.');
        redirect($redirectTo);
    }

    /* ---------- DEACTIVATE (hide from public) ---------- */
    if ($action === 'deactivate') {
        db()->prepare(
            "UPDATE media_partners
             SET is_deactivated = 1, updated_at = NOW()
             WHERE id = ?"
        )->execute([$id]);

        audit_log('MEDIA_PARTNER_DEACTIVATED', (string) $partner['name']);
        flash('success', 'Media partner hidden from the public site.');
        redirect($redirectTo);
    }

    /* ---------- ACTIVATE (show again) ---------- */
    if ($action === 'activate') {
        db()->prepare(
            "UPDATE media_partners
             SET is_deactivated = 0, updated_at = NOW()
             WHERE id = ?"
        )->execute([$id]);

        audit_log('MEDIA_PARTNER_ACTIVATED', (string) $partner['name']);
        flash('success', 'Media partner is now visible on the public site.');
        redirect($redirectTo);
    }

    /* ---------- DELETE ---------- */
    if ($action === 'delete') {
        db()->prepare('DELETE FROM media_partners WHERE id = ?')->execute([$id]);

        audit_log('MEDIA_PARTNER_DELETED', (string) $partner['name']);
        flash('success', 'Media partner deleted successfully.');
        redirect($redirectTo);
    }

    flash('danger', 'Invalid action.');
    redirect($redirectTo);
}

/* ==========================================================================
   STATS
   ========================================================================== */
$stats = db()->query(
    "SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = 'approved' AND is_deactivated = 0 THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN status = 'approved' AND is_deactivated = 1 THEN 1 ELSE 0 END) AS hidden_count,
        SUM(CASE WHEN status <> 'approved' THEN 1 ELSE 0 END) AS pending_count
     FROM media_partners"
)->fetch();

$totalPartners  = (int) ($stats['total_count']  ?? 0);
$activePartners = (int) ($stats['active_count'] ?? 0);
$hiddenPartners = (int) ($stats['hidden_count'] ?? 0);
$pendingPartners = (int) ($stats['pending_count'] ?? 0);

/* ==========================================================================
   LIST QUERY
   ========================================================================== */
$whereSql = '';
if ($filter === 'approved') {
    $whereSql = " WHERE status = 'approved' ";
} elseif ($filter === 'pending') {
    $whereSql = " WHERE status <> 'approved' ";
} elseif ($filter === 'active') {
    $whereSql = " WHERE status = 'approved' AND is_deactivated = 0 ";
} elseif ($filter === 'hidden') {
    $whereSql = " WHERE status = 'approved' AND is_deactivated = 1 ";
}

$filteredTotal = (int) db()->query('SELECT COUNT(*) FROM media_partners' . $whereSql)->fetchColumn();
$totalPages    = max(1, (int) ceil($filteredTotal / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

$rows = db()->query(
    "SELECT id, name, description, logo, website, status, is_deactivated, created_at
     FROM media_partners
     {$whereSql}
     ORDER BY created_at DESC, id DESC
     LIMIT {$perPage} OFFSET {$offset}"
)->fetchAll();

$from = $filteredTotal > 0 ? $offset + 1 : 0;
$to   = min($offset + $perPage, $filteredTotal);

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Media Partners';
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
        Outreach Network
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Media Partner Applications
        <?php if ($pendingPartners > 0): ?>
          <span class="badge text-bg-warning ms-2" style="font-size:.75rem;vertical-align:middle;">
            <?= number_format($pendingPartners) ?> pending
          </span>
        <?php else: ?>
          <span class="badge text-bg-success ms-2" style="font-size:.75rem;vertical-align:middle;">
            All reviewed
          </span>
        <?php endif; ?>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Approve, activate/deactivate, or delete media partners.
      </p>
    </div>
  </div>
</div>

<!-- ==========================================================================
     STAT CARDS
     ========================================================================== -->
<div class="row row-cols-2 row-cols-md-4 g-4 mb-4 reveal-stagger">

  <div class="col">
    <div class="stat-card stat-accent-primary h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-newspaper"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format($totalPartners) ?></div>
      <div class="small text-secondary mt-1">Total</div>
    </div>
  </div>

  <div class="col">
    <div class="stat-card stat-accent-success h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-check-circle"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format($activePartners) ?></div>
      <div class="small text-secondary mt-1">Active on Site</div>
    </div>
  </div>

  <div class="col">
    <div class="stat-card stat-accent-secondary h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-eye-slash"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format($hiddenPartners) ?></div>
      <div class="small text-secondary mt-1">Hidden</div>
    </div>
  </div>

  <div class="col">
    <div class="stat-card stat-accent-warning h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-hourglass-split"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format($pendingPartners) ?></div>
      <div class="small text-secondary mt-1">Pending</div>
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
      <?= e(ucfirst($filter === 'all' ? 'All Partners' : $filter)) ?>
    </strong>
  </div>

  <form method="get" class="dashboard-filter-form" style="max-width:240px;">
    <select name="filter"
            class="form-select form-select-sm"
            onchange="this.form.submit()">
      <option value="all"      <?= $filter === 'all'      ? 'selected' : '' ?>>All Partners</option>
      <option value="active"   <?= $filter === 'active'   ? 'selected' : '' ?>>Active on Site</option>
      <option value="hidden"   <?= $filter === 'hidden'   ? 'selected' : '' ?>>Hidden</option>
      <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>Approved (all)</option>
      <option value="pending"  <?= $filter === 'pending'  ? 'selected' : '' ?>>Not Approved</option>
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
            <th style="width:90px;">Logo</th>
            <th>Media Partner</th>
            <th>Description</th>
            <th>Website</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>

          <?php foreach ($rows as $row): ?>
            <?php
            $logoUrl = absolute_media_url($row['logo'] ?? null);
            $isApproved    = $row['status'] === 'approved';
            $isDeactivated = !empty($row['is_deactivated']);
            ?>

            <tr>

              <td>
                <?php if ($logoUrl !== ''): ?>
                  <img src="<?= e($logoUrl) ?>"
                       alt="<?= e($row['name']) ?> logo"
                       style="width:56px;height:56px;object-fit:contain;border:1px solid var(--color-border);border-radius:10px;background:#fff;padding:5px;">
                <?php else: ?>
                  <div class="avatar-circle" style="width:56px;height:56px;">
                    <i class="bi bi-image"></i>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <strong><?= e($row['name']) ?></strong>
                <div class="small text-secondary mt-1">
                  <i class="bi bi-clock me-1"></i>
                  <?= e(date('j M Y, g:i A', strtotime((string) $row['created_at']))) ?>
                </div>
              </td>

              <td style="min-width:220px;max-width:400px;">
                <span class="small">
                  <?= e(excerpt((string) $row['description'], 140)) ?>
                </span>
              </td>

              <td class="text-nowrap">
                <a href="<?= e($row['website']) ?>"
                   target="_blank"
                   rel="noopener nofollow"
                   class="small">
                  Visit
                  <i class="bi bi-box-arrow-up-right ms-1"></i>
                </a>
              </td>

              <td>
                <?php if (!$isApproved): ?>
                  <span class="badge text-bg-warning">
                    <i class="bi bi-hourglass-split me-1"></i>Pending
                  </span>
                <?php elseif ($isDeactivated): ?>
                  <span class="badge text-bg-secondary">
                    <i class="bi bi-eye-slash me-1"></i>Hidden
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Active
                  </span>
                <?php endif; ?>
              </td>

              <td class="text-end text-nowrap">
                <div class="d-inline-flex flex-wrap justify-content-end gap-1">

                  <?php if (!$isApproved): ?>
                    <form method="post" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                      <input type="hidden" name="return_page" value="<?= $page ?>">
                      <button type="submit"
                              class="btn btn-sm btn-outline-success"
                              data-confirm="Approve this media partner? They will appear on the public site.">
                        <i class="bi bi-check-lg me-1"></i>Approve
                      </button>
                    </form>
                  <?php elseif ($isDeactivated): ?>
                    <form method="post" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <input type="hidden" name="action" value="activate">
                      <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                      <input type="hidden" name="return_page" value="<?= $page ?>">
                      <button type="submit"
                              class="btn btn-sm btn-outline-success"
                              data-confirm="Activate this media partner? They will appear on the public site.">
                        <i class="bi bi-play-circle me-1"></i>Activate
                      </button>
                    </form>
                  <?php else: ?>
                    <form method="post" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                      <input type="hidden" name="action" value="deactivate">
                      <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                      <input type="hidden" name="return_page" value="<?= $page ?>">
                      <button type="submit"
                              class="btn btn-sm btn-outline-secondary"
                              data-confirm="Deactivate this media partner? They will be hidden from the public site.">
                        <i class="bi bi-pause-circle me-1"></i>Deactivate
                      </button>
                    </form>
                  <?php endif; ?>

                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                    <input type="hidden" name="return_page" value="<?= $page ?>">
                    <button type="submit"
                            class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this media partner permanently?">
                      <i class="bi bi-trash me-1"></i>Delete
                    </button>
                  </form>

                </div>
              </td>

            </tr>

          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($filteredTotal > 0): ?>
      <div class="p-4 border-top d-flex flex-wrap align-items-center justify-content-between gap-3">

        <div class="small text-secondary">
          Showing <strong><?= $from ?></strong>–<strong><?= $to ?></strong>
          of <strong><?= number_format($filteredTotal) ?></strong> media partners
        </div>

        <?php if ($totalPages > 1): ?>
          <?php
          $startPage = max(1, $page - 2);
          $endPage   = min($totalPages, $page + 2);
          ?>

          <nav aria-label="Media partner pagination">
            <ul class="pagination pagination-sm mb-0">

              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="<?= e(url('/admin/media-partners?filter=' . rawurlencode($filter) . '&page=' . max(1, $page - 1))) ?>">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>

              <?php if ($startPage > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="<?= e(url('/admin/media-partners?filter=' . rawurlencode($filter) . '&page=1')) ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
              <?php endif; ?>

              <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= e(url('/admin/media-partners?filter=' . rawurlencode($filter) . '&page=' . $p)) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>

              <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
                <li class="page-item">
                  <a class="page-link" href="<?= e(url('/admin/media-partners?filter=' . rawurlencode($filter) . '&page=' . $totalPages)) ?>"><?= $totalPages ?></a>
                </li>
              <?php endif; ?>

              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link"
                   href="<?= e(url('/admin/media-partners?filter=' . rawurlencode($filter) . '&page=' . min($totalPages, $page + 1))) ?>">
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
      <i class="bi bi-newspaper"></i>
      <h2 class="h5 mt-3">No media partners found</h2>
      <p class="text-secondary mb-3">
        <?php if ($filter !== 'all'): ?>
          No media partners match this filter.
        <?php else: ?>
          No media partner applications yet.
        <?php endif; ?>
      </p>
      <?php if ($filter !== 'all'): ?>
        <a href="<?= e(url('/admin/media-partners')) ?>" class="btn btn-outline-secondary btn-sm">
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
.admin-welcome-banner > * { position: relative; z-index: 1; }

.stat-card.stat-accent-primary   { border-left: 4px solid var(--color-accent); }
.stat-card.stat-accent-success   { border-left: 4px solid var(--color-success); }
.stat-card.stat-accent-warning   { border-left: 4px solid var(--color-warning); }
.stat-card.stat-accent-secondary { border-left: 4px solid var(--color-muted); }

.stat-card.stat-accent-primary i   { color: var(--color-accent); }
.stat-card.stat-accent-success i   { color: var(--color-success); }
.stat-card.stat-accent-warning i   { color: var(--color-warning); }
.stat-card.stat-accent-secondary i { color: var(--color-muted); }
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>