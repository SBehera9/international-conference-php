<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   ACTIONS
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    /* ==================== BULK ACTIONS ==================== */
    $bulkAction = post('bulk_action');
    $bulkIds    = $_POST['bulk_ids'] ?? [];

    if ($bulkAction !== '' && is_array($bulkIds) && count($bulkIds) > 0) {

        $ids = [];
        foreach ($bulkIds as $rawId) {
            $cleanId = (int) $rawId;
            if ($cleanId > 0) $ids[] = $cleanId;
        }
        $ids = array_unique($ids);

        if (empty($ids)) {
            flash('danger', 'No valid items selected.');
            redirect('/admin/manage-conferences');
        }

        $returnFilter = trim(post('return_filter'));
        $redirectPath = '/admin/manage-conferences';
        if (in_array($returnFilter, ['featured', 'active', 'deactivated'], true)) {
            $redirectPath .= '?filter=' . rawurlencode($returnFilter);
        }

        $pdo = db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        /* ---------- Fetch selection ---------- */
        $stmt = $pdo->prepare(
            "SELECT * FROM conferences WHERE id IN ({$placeholders})"
        );
        $stmt->execute($ids);
        $selected = $stmt->fetchAll();

        if (empty($selected)) {
            flash('warning', 'No conferences found in selection.');
            redirect($redirectPath);
        }

        /* ---------- BULK FEATURE / UNFEATURE ---------- */
        if ($bulkAction === 'feature' || $bulkAction === 'unfeature') {
            $flag = ($bulkAction === 'feature') ? 1 : 0;
            $count = 0;

            try {
                $pdo->beginTransaction();

                $updateStmt = $pdo->prepare(
                    "UPDATE conferences
                     SET is_featured = ?, updated_at = NOW()
                     WHERE id = ?"
                );

                foreach ($selected as $c) {
                    $updateStmt->execute([$flag, (int) $c['id']]);
                    $count++;

                    audit_log(
                        'CONFERENCE_ADMIN_' . strtoupper($bulkAction),
                        (string) $c['title'],
                        (int) $c['id'],
                        (int) $c['organizer_id']
                    );
                }

                $pdo->commit();
                flash('success', $count . ' conference(s) ' . ($flag ? 'marked as featured' : 'removed from featured') . '.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk action failed: ' . $e->getMessage());
            }

            redirect($redirectPath);
        }

        /* ---------- BULK ACTIVATE / DEACTIVATE ---------- */
        if ($bulkAction === 'activate' || $bulkAction === 'deactivate') {
            $flag = ($bulkAction === 'deactivate') ? 1 : 0;
            $count = 0;

            try {
                $pdo->beginTransaction();

                $updateStmt = $pdo->prepare(
                    "UPDATE conferences
                     SET is_deactivated = ?, updated_at = NOW()
                     WHERE id = ?"
                );

                foreach ($selected as $c) {
                    $updateStmt->execute([$flag, (int) $c['id']]);
                    $count++;

                    audit_log(
                        'CONFERENCE_ADMIN_' . strtoupper($bulkAction),
                        (string) $c['title'],
                        (int) $c['id'],
                        (int) $c['organizer_id']
                    );
                }

                $pdo->commit();
                flash('success', $count . ' conference(s) ' . ($flag ? 'deactivated' : 'activated') . '.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk action failed: ' . $e->getMessage());
            }

            redirect($redirectPath);
        }

        /* ---------- BULK DELETE ---------- */
        if ($bulkAction === 'delete') {
            $count = 0;

            try {
                $pdo->beginTransaction();

                $deleteStmt = $pdo->prepare("DELETE FROM conferences WHERE id = ?");

                foreach ($selected as $c) {
                    $deleteStmt->execute([(int) $c['id']]);
                    $count++;

                    audit_log(
                        'CONFERENCE_ADMIN_DELETE',
                        (string) $c['title'],
                        (int) $c['id'],
                        (int) $c['organizer_id']
                    );
                }

                $pdo->commit();
                flash('success', $count . ' conference(s) permanently deleted.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk delete failed: ' . $e->getMessage());
            }

            redirect($redirectPath);
        }

        flash('danger', 'Invalid bulk action.');
        redirect($redirectPath);
    }

    /* ==================== SINGLE ACTIONS ==================== */
    $id     = (int) post('id');
    $action = post('action');

    $stmt = db()->prepare("SELECT * FROM conferences WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $conference = $stmt->fetch();

    if (!$conference) {
        flash('danger', 'Conference not found.');
        redirect('/admin/manage-conferences');
    }

    if ($action === 'feature') {
        db()->prepare(
            "UPDATE conferences
             SET is_featured = 1 - is_featured,
                 updated_at = NOW()
             WHERE id = ?"
        )->execute([$id]);

        flash(
            'success',
            !empty($conference['is_featured'])
                ? 'Conference removed from featured conferences.'
                : 'Conference marked as featured.'
        );
    } elseif ($action === 'deactivate') {
        db()->prepare(
            "UPDATE conferences
             SET is_deactivated = 1 - is_deactivated,
                 updated_at = NOW()
             WHERE id = ?"
        )->execute([$id]);

        flash(
            'success',
            !empty($conference['is_deactivated'])
                ? 'Conference activated successfully.'
                : 'Conference deactivated successfully.'
        );
    } elseif ($action === 'delete') {
        db()->prepare("DELETE FROM conferences WHERE id = ?")->execute([$id]);
        flash('success', 'Conference permanently deleted.');
    } else {
        flash('danger', 'Invalid conference action.');
        redirect('/admin/manage-conferences');
    }

    audit_log(
        'CONFERENCE_ADMIN_' . strtoupper($action),
        (string) $conference['title'],
        $id,
        (int) $conference['organizer_id']
    );

    $returnFilter = trim(post('return_filter'));
    $redirectPath = '/admin/manage-conferences';

    if (in_array($returnFilter, ['featured', 'active', 'deactivated'], true)) {
        $redirectPath .= '?filter=' . rawurlencode($returnFilter);
    }

    redirect($redirectPath);
}

/* ==========================================================================
   FILTER
   ========================================================================== */
$filter = strtolower(trim(getq('filter')));
$allowedFilters = ['all', 'featured', 'active', 'deactivated'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$where = "c.status = 'approved' AND c.end_date >= CURDATE()";

if ($filter === 'featured') {
    $where .= ' AND c.is_featured = 1';
} elseif ($filter === 'active') {
    $where .= ' AND c.is_deactivated = 0';
} elseif ($filter === 'deactivated') {
    $where .= ' AND c.is_deactivated = 1';
}

/* ==========================================================================
   QUERY (PAGINATED — 50 per page)
   ========================================================================== */
$perPage = 50;

/* Total rows for current filter */
$totalRows = (int) db()->query(
    "SELECT COUNT(*)
     FROM conferences c
     WHERE {$where}"
)->fetchColumn();

$pager = paginate($totalRows, $perPage);

/* Fetch this page */
$stmt = db()->prepare(
    "SELECT c.*, o.organization_name
     FROM conferences c
     LEFT JOIN organizers o ON o.id = c.organizer_id
     WHERE {$where}
     ORDER BY c.created_at DESC, c.id DESC
     LIMIT ? OFFSET ?"
);

$stmt->bindValue(1, $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue(2, $pager['offset'],   PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Manage Conferences';
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
        Approved Conferences
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Manage Conferences
        <span class="badge text-bg-light ms-2" style="font-size:.75rem;vertical-align:middle;color:var(--color-text-strong);">
          <?= number_format($totalRows) ?> total
        </span>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Feature, deactivate, or delete approved public conferences.
      </p>
    </div>
  </div>
</div>

<!-- ==========================================================================
     FILTER BAR
     ========================================================================== -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 reveal">

  <div class="small text-secondary">
    <i class="bi bi-funnel me-1"></i>
    Filter:
    <strong class="text-dark">
      <?= e(ucfirst($filter === 'all' ? 'All Conferences' : $filter)) ?>
    </strong>
  </div>

  <form method="get" class="dashboard-filter-form" style="max-width:240px;">
    <select name="filter"
            class="form-select form-select-sm"
            onchange="this.form.submit()">
      <option value="all"         <?= $filter === 'all'         ? 'selected' : '' ?>>All Conferences</option>
      <option value="featured"    <?= $filter === 'featured'    ? 'selected' : '' ?>>Featured Only</option>
      <option value="active"      <?= $filter === 'active'      ? 'selected' : '' ?>>Active Only</option>
      <option value="deactivated" <?= $filter === 'deactivated' ? 'selected' : '' ?>>Deactivated Only</option>
    </select>
  </form>

</div>

<!-- ==========================================================================
     BULK ACTION FORM WRAPPER
     ========================================================================== -->
<form method="post" id="bulkForm" data-no-guard>
  <?= csrf_field() ?>
  <input type="hidden" name="return_filter" value="<?= e($filter) ?>">

  <!-- ========== BULK ACTION BAR ========== -->
  <div id="bulkBar" class="bulk-bar mb-3" hidden>
    <div class="d-flex flex-wrap align-items-center gap-3">

      <div class="fw-bold text-dark">
        <i class="bi bi-check2-square me-1"></i>
        <span id="bulkCount">0</span> selected
      </div>

      <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">

        <select name="bulk_action" id="bulkAction" class="form-select form-select-sm" style="min-width:220px;">
          <option value="">— Bulk Actions —</option>
          <option value="feature">Feature Selected</option>
          <option value="unfeature">Unfeature Selected</option>
          <option value="deactivate">Deactivate Selected</option>
          <option value="activate">Activate Selected</option>
          <option value="delete">Delete Selected</option>
        </select>

        <button type="button" id="bulkApplyBtn" class="btn btn-action btn-sm">
          <i class="bi bi-lightning-charge me-1"></i> Apply
        </button>

        <button type="button" id="bulkClearBtn" class="btn btn-outline-secondary btn-sm">
          Clear
        </button>

      </div>
    </div>
  </div>

  <!-- ========== TABLE ========== -->
  <div class="table-card reveal">

    <?php if ($rows): ?>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:44px;">
                <input type="checkbox" id="selectAll" class="form-check-input">
              </th>
              <th>Conference</th>
              <th>Organizer</th>
              <th>Start Date</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>

            <?php foreach ($rows as $conference): ?>
              <?php $conferenceId = (int) $conference['id']; ?>

              <tr>
                <td>
                  <input type="checkbox"
                         class="form-check-input row-checkbox"
                         name="bulk_ids[]"
                         value="<?= $conferenceId ?>">
                </td>

                <td>
                  <a href="<?= e(url('/conference/' . $conference['slug'])) ?>"
                     target="_blank"
                     rel="noopener"
                     class="fw-semibold text-decoration-none d-inline-flex align-items-center gap-1"
                     title="Open public page">
                    <?= e($conference['title']) ?>
                    <i class="bi bi-box-arrow-up-right small text-secondary"></i>
                  </a>
                </td>

                <td>
                  <span class="text-secondary small">
                    <?= e($conference['organization_name'] ?: '—') ?>
                  </span>
                </td>

                <td class="text-nowrap small">
                  <?= e(format_date($conference['start_date'])) ?>
                </td>

                <td>
                  <div class="d-flex flex-wrap gap-1 align-items-center">

                    <?php if (!empty($conference['is_featured'])): ?>
                      <span class="badge badge-featured" title="Featured Conference">
                        <i class="bi bi-star-fill"></i>
                        Featured
                      </span>
                    <?php endif; ?>

                    <?php if (!empty($conference['is_deactivated'])): ?>
                      <span class="badge text-bg-secondary">
                        <i class="bi bi-eye-slash me-1"></i>Deactivated
                      </span>
                    <?php else: ?>
                      <span class="badge text-bg-success">
                        <i class="bi bi-check-circle me-1"></i>Active
                      </span>
                    <?php endif; ?>

                  </div>
                </td>

                <td class="text-end text-nowrap">
                  <div class="d-inline-flex gap-1 flex-wrap justify-content-end">

                    <button type="submit"
                            name="action"
                            value="feature"
                            formnovalidate
                            class="btn btn-sm <?= !empty($conference['is_featured'])
                                ? 'btn-warning'
                                : 'btn-outline-warning' ?>"
                            title="<?= !empty($conference['is_featured'])
                                ? 'Remove from Featured'
                                : 'Mark as Featured' ?>"
                            onclick="document.getElementById('hiddenSingleId').value = '<?= $conferenceId ?>';">
                      <i class="bi bi-star<?= !empty($conference['is_featured']) ? '-fill' : '' ?> me-1"></i>
                      <?= !empty($conference['is_featured']) ? 'Unfeature' : 'Feature' ?>
                    </button>

                    <button type="submit"
                            name="action"
                            value="deactivate"
                            formnovalidate
                            class="btn btn-sm <?= !empty($conference['is_deactivated'])
                                ? 'btn-outline-success'
                                : 'btn-outline-secondary' ?>"
                            title="<?= !empty($conference['is_deactivated'])
                                ? 'Activate conference'
                                : 'Deactivate conference' ?>"
                            onclick="document.getElementById('hiddenSingleId').value = '<?= $conferenceId ?>';">
                      <i class="bi bi-<?= !empty($conference['is_deactivated']) ? 'play-circle' : 'pause-circle' ?> me-1"></i>
                      <?= !empty($conference['is_deactivated']) ? 'Activate' : 'Deactivate' ?>
                    </button>

                    <button type="submit"
                            name="action"
                            value="delete"
                            formnovalidate
                            class="btn btn-sm btn-outline-danger"
                            data-confirm="Permanently delete this conference? This will remove it from BOTH the admin and organizer portals."
                            onclick="document.getElementById('hiddenSingleId').value = '<?= $conferenceId ?>';">
                      <i class="bi bi-trash me-1"></i>Delete
                    </button>

                  </div>
                </td>
              </tr>

            <?php endforeach; ?>

          </tbody>
        </table>
      </div>

    <?php else: ?>

      <div class="empty-state bg-white">
        <i class="bi bi-inbox"></i>
        <h2 class="h5 mt-3">No conferences found</h2>
        <p class="text-secondary mb-3">
          <?php if ($filter !== 'all'): ?>
            No conferences match this filter.
          <?php else: ?>
            No approved conferences yet.
          <?php endif; ?>
        </p>

        <?php if ($filter !== 'all'): ?>
          <a href="<?= e(url('/admin/manage-conferences')) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle me-1"></i>Clear Filter
          </a>
        <?php endif; ?>
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

    $pageUrl = static function (int $p) use ($filter) : string {
        $qs = [];
        if ($filter !== 'all') {
            $qs['filter'] = $filter;
        }
        $qs['page'] = $p;
        return '?' . http_build_query($qs);
    };
    ?>

    <nav class="mt-4" aria-label="Manage conferences pagination">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <div class="small text-secondary">
          Showing
          <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $totalRows) ?></strong>
          of <strong><?= number_format($totalRows) ?></strong> conferences
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

  <!-- Hidden input for single-row actions -->
  <input type="hidden" name="id" id="hiddenSingleId" value="">
</form>

<!-- ==========================================================================
     STYLES
     ========================================================================== -->
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

.bulk-bar {
  background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
  border: 1px solid #93C5FD;
  border-radius: var(--radius-lg);
  padding: 0.75rem 1rem;
  animation: slideDown 0.25s ease-out;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}

.row-checkbox,
#selectAll {
  width: 1.15rem;
  height: 1.15rem;
  cursor: pointer;
  accent-color: var(--color-accent);
}

tr:has(.row-checkbox:checked) {
  background: rgba(37, 99, 235, 0.06) !important;
}

tr:has(.row-checkbox:checked) td {
  border-bottom-color: rgba(37, 99, 235, 0.15);
}
</style>

<!-- ==========================================================================
     BULK ACTIONS SCRIPT
     ========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {

  var selectAll     = document.getElementById('selectAll');
  var rowCheckboxes = Array.from(document.querySelectorAll('.row-checkbox'));
  var bulkBar       = document.getElementById('bulkBar');
  var bulkCount     = document.getElementById('bulkCount');
  var bulkAction    = document.getElementById('bulkAction');
  var bulkApplyBtn  = document.getElementById('bulkApplyBtn');
  var bulkClearBtn  = document.getElementById('bulkClearBtn');
  var bulkForm      = document.getElementById('bulkForm');

  if (!bulkForm || !rowCheckboxes.length) return;

  function updateBulkUI() {
    var checked = rowCheckboxes.filter(function (cb) { return cb.checked; });
    var count = checked.length;

    if (bulkCount) bulkCount.textContent = count;
    if (bulkBar)   bulkBar.hidden = count === 0;

    if (selectAll) {
      selectAll.checked = count > 0 && count === rowCheckboxes.length;
      selectAll.indeterminate = count > 0 && count < rowCheckboxes.length;
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      rowCheckboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
      updateBulkUI();
    });
  }

  rowCheckboxes.forEach(function (cb) {
    cb.addEventListener('change', updateBulkUI);
  });

  if (bulkClearBtn) {
    bulkClearBtn.addEventListener('click', function () {
      rowCheckboxes.forEach(function (cb) { cb.checked = false; });
      if (selectAll) selectAll.checked = false;
      updateBulkUI();
    });
  }

  /* When single-row actions are clicked, clear bulk checkboxes first */
  bulkForm.querySelectorAll('button[name="action"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      rowCheckboxes.forEach(function (cb) { cb.checked = false; });
      updateBulkUI();
    });
  });

  if (bulkApplyBtn) {
    bulkApplyBtn.addEventListener('click', function () {
      var action = bulkAction ? bulkAction.value : '';
      var selected = rowCheckboxes.filter(function (cb) { return cb.checked; });

      if (selected.length === 0) {
        alert('Please select at least one conference.');
        return;
      }

      if (action === '') {
        alert('Please choose a bulk action from the dropdown.');
        return;
      }

      var messages = {
        feature:    'Mark ' + selected.length + ' conference(s) as FEATURED?',
        unfeature:  'Remove FEATURED flag from ' + selected.length + ' conference(s)?',
        deactivate: 'DEACTIVATE ' + selected.length + ' conference(s)? They will be hidden from the public site.',
        activate:   'ACTIVATE ' + selected.length + ' conference(s)? They will be visible publicly again.',
        delete:     'PERMANENTLY DELETE ' + selected.length + ' conference(s)? This cannot be undone!'
      };

      var msg = messages[action] || ('Apply "' + action + '" to ' + selected.length + ' item(s)?');

      if (!window.confirm(msg)) return;

      bulkForm.submit();
    });
  }

  updateBulkUI();

});
</script>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>