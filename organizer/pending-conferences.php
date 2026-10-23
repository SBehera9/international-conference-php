<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   ACTIONS
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    /* ==================== BULK DELETE ==================== */
    $bulkAction = post('bulk_action');
    $bulkIds    = $_POST['bulk_ids'] ?? [];

    if ($bulkAction === 'delete' && is_array($bulkIds) && count($bulkIds) > 0) {

        $ids = [];
        foreach ($bulkIds as $rawId) {
            $cleanId = (int) $rawId;
            if ($cleanId > 0) $ids[] = $cleanId;
        }
        $ids = array_unique($ids);

        if (empty($ids)) {
            flash('danger', 'No valid items selected.');
            redirect('/organizer/pending-conferences');
        }

        $pdo = db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare(
            "SELECT id, title
             FROM conferences
             WHERE id IN ({$placeholders})
               AND organizer_id = ?
               AND status = 'pending'"
        );
        $params = $ids;
        $params[] = (int) $profile['id'];
        $stmt->execute($params);
        $selected = $stmt->fetchAll();

        if (empty($selected)) {
            flash('warning', 'No pending conferences found in selection.');
            redirect('/organizer/pending-conferences');
        }

        $count = 0;
        try {
            $pdo->beginTransaction();

            $deleteStmt = $pdo->prepare(
                "DELETE FROM conferences
                 WHERE id = ?
                   AND organizer_id = ?
                   AND status = 'pending'"
            );

            foreach ($selected as $c) {
                $deleteStmt->execute([(int) $c['id'], (int) $profile['id']]);

                if ($deleteStmt->rowCount() > 0) {
                    $count++;

                    audit_log(
                        'CONFERENCE_DELETED',
                        'Organizer permanently deleted pending conference: ' . (string) $c['title'],
                        (int) $c['id'],
                        (int) $profile['id']
                    );
                }
            }

            $pdo->commit();
            flash('success', $count . ' pending conference(s) permanently deleted.');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', 'Bulk delete failed: ' . $e->getMessage());
        }

        redirect('/organizer/pending-conferences');
    }

    /* ==================== SINGLE DELETE ==================== */
    try {
        $action       = post('action');
        $conferenceId = (int) post('conference_id');

        if ($action !== 'delete' || $conferenceId <= 0) {
            throw new RuntimeException('Invalid conference action.');
        }

        $findStmt = db()->prepare(
            "SELECT id, title
             FROM conferences
             WHERE id = ?
               AND organizer_id = ?
               AND status = 'pending'
             LIMIT 1"
        );
        $findStmt->execute([$conferenceId, (int) $profile['id']]);
        $conference = $findStmt->fetch();

        if (!$conference) {
            throw new RuntimeException('Pending conference not found or it can no longer be deleted.');
        }

        $deleteStmt = db()->prepare(
            "DELETE FROM conferences
             WHERE id = ?
               AND organizer_id = ?
               AND status = 'pending'"
        );
        $deleteStmt->execute([$conferenceId, (int) $profile['id']]);

        if ($deleteStmt->rowCount() !== 1) {
            throw new RuntimeException('Conference could not be deleted. Please try again.');
        }

        audit_log(
            'CONFERENCE_DELETED',
            'Organizer permanently deleted pending conference: ' . (string) $conference['title'],
            $conferenceId,
            (int) $profile['id']
        );

        flash('success', 'Conference deleted permanently. It has also been removed from the Admin Pending Conferences list.');
        redirect('/organizer/pending-conferences');

    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect('/organizer/pending-conferences');
    }
}

/* ==========================================================================
   PENDING LIST (PAGINATED — 50 per page)
   ========================================================================== */
$perPage = 50;

/* Total rows for this organizer */
$countStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM conferences
     WHERE organizer_id = ?
       AND status = 'pending'"
);
$countStmt->execute([(int) $profile['id']]);
$pendingCount = (int) $countStmt->fetchColumn();

$pager = paginate($pendingCount, $perPage);

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
       AND c.status = 'pending'
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
$dashboardTitle = 'Pending Conferences';
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
        Awaiting Review
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Pending Conferences
        <?php if ($pendingCount > 0): ?>
          <span class="badge text-bg-warning ms-2" style="font-size:.75rem;vertical-align:middle;">
            <?= number_format($pendingCount) ?> awaiting review
          </span>
        <?php else: ?>
          <span class="badge text-bg-success ms-2" style="font-size:.75rem;vertical-align:middle;">
            Nothing pending
          </span>
        <?php endif; ?>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        You can edit or permanently delete a conference while it is still pending review.
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
     BULK ACTION FORM WRAPPER
     ========================================================================== -->
<form method="post" id="bulkForm" data-no-guard>
  <?= csrf_field() ?>

  <!-- ========== BULK ACTION BAR ========== -->
  <div id="bulkBar" class="bulk-bar mb-3" hidden>
    <div class="d-flex flex-wrap align-items-center gap-3">

      <div class="fw-bold text-dark">
        <i class="bi bi-check2-square me-1"></i>
        <span id="bulkCount">0</span> selected
      </div>

      <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">

        <select name="bulk_action" id="bulkAction" class="form-select form-select-sm" style="min-width:200px;">
          <option value="">— Bulk Actions —</option>
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
              <th>Conference Title</th>
              <th>Topic</th>
              <th>Start Date</th>
              <th>Location</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>

            <?php foreach ($rows as $conference): ?>
              <tr>

                <td>
                  <input type="checkbox"
                         class="form-check-input row-checkbox"
                         name="bulk_ids[]"
                         value="<?= (int) $conference['id'] ?>">
                </td>

                <td>
                  <strong><?= e((string) $conference['title']) ?></strong>
                </td>

                <td>
                  <span class="small text-secondary">
                    <?= e((string) ($conference['topic_name'] ?? '—')) ?>
                  </span>
                </td>

                <td class="text-nowrap">
                  <?= e(format_date((string) $conference['start_date'])) ?>
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
                  <?= status_badge('pending') ?>
                </td>

                <td class="text-end text-nowrap">
                  <div class="d-inline-flex flex-wrap justify-content-end gap-1">

                    <a href="<?= e(url('/organizer/add-conference?id=' . (int) $conference['id'])) ?>"
                       class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil-square me-1"></i>Edit
                    </a>

                    <button type="submit"
                            name="action"
                            value="delete"
                            formnovalidate
                            class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this conference permanently? It will also disappear from the Admin Pending Conferences list. This cannot be undone."
                            onclick="document.getElementById('hiddenSingleId').value = '<?= (int) $conference['id'] ?>'; document.getElementById('hiddenSingleConferenceId').value = '<?= (int) $conference['id'] ?>';">
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
        <i class="bi bi-hourglass-split text-warning"></i>
        <h2 class="h5 mt-3">No pending conferences</h2>
        <p class="text-secondary mb-0">
          All your submitted conferences have been reviewed, or you haven't submitted any yet.
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

    <nav class="mt-4" aria-label="Pending conferences pagination">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <div class="small text-secondary">
          Showing
          <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $pendingCount) ?></strong>
          of <strong><?= number_format($pendingCount) ?></strong> pending conferences
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

  <!-- Hidden inputs for single-row delete -->
  <input type="hidden" name="conference_id" id="hiddenSingleConferenceId" value="">
</form>

<!-- ==========================================================================
     STYLES
     ========================================================================== -->
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

@media (max-width: 767.98px) {
  .organizer-welcome-banner { padding: 1.25rem; }
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

      if (action === 'delete') {
        var msg = 'PERMANENTLY DELETE ' + selected.length + ' pending conference(s)? This cannot be undone!';
        if (!window.confirm(msg)) return;
        bulkForm.submit();
        return;
      }

      alert('Unknown bulk action.');
    });
  }

  updateBulkUI();

});
</script>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>