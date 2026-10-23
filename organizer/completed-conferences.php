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
            redirect('/organizer/completed-conferences');
        }

        $pdo = db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare(
            "SELECT id, title
             FROM conferences
             WHERE id IN ({$placeholders})
               AND organizer_id = ?
               AND status = 'approved'
               AND end_date < CURDATE()"
        );
        $params = $ids;
        $params[] = (int) $profile['id'];
        $stmt->execute($params);
        $selected = $stmt->fetchAll();

        if (empty($selected)) {
            flash('warning', 'No completed conferences found in selection.');
            redirect('/organizer/completed-conferences');
        }

        $count = 0;
        try {
            $pdo->beginTransaction();

            $deleteStmt = $pdo->prepare(
                "DELETE FROM conferences
                 WHERE id = ?
                   AND organizer_id = ?"
            );

            foreach ($selected as $c) {
                $deleteStmt->execute([(int) $c['id'], (int) $profile['id']]);

                if ($deleteStmt->rowCount() > 0) {
                    $count++;

                    audit_log(
                        'ORGANIZER_DELETE_COMPLETED_CONFERENCE',
                        (string) $c['title'],
                        (int) $c['id'],
                        (int) $profile['id']
                    );
                }
            }

            $pdo->commit();
            flash('success', $count . ' completed conference(s) permanently deleted.');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', 'Bulk delete failed: ' . $e->getMessage());
        }

        redirect('/organizer/completed-conferences');
    }

    /* ==================== SINGLE DELETE ==================== */
    $id     = (int) post('id');
    $action = post('action');

    if ($action !== 'delete' || $id <= 0) {
        flash('danger', 'Invalid conference action.');
        redirect('/organizer/completed-conferences');
    }

    $stmt = db()->prepare(
        "SELECT id, title
         FROM conferences
         WHERE id = ?
           AND organizer_id = ?
           AND status = 'approved'
           AND end_date < CURDATE()
         LIMIT 1"
    );
    $stmt->execute([$id, (int) $profile['id']]);
    $conference = $stmt->fetch();

    if (!$conference) {
        flash('danger', 'Completed conference not found.');
        redirect('/organizer/completed-conferences');
    }

    db()->prepare(
        "DELETE FROM conferences WHERE id = ? AND organizer_id = ?"
    )->execute([$id, (int) $profile['id']]);

    audit_log(
        'ORGANIZER_DELETE_COMPLETED_CONFERENCE',
        (string) $conference['title'],
        $id,
        (int) $profile['id']
    );

    flash('success', 'Completed conference permanently deleted.');
    redirect('/organizer/completed-conferences');
}

/* ==========================================================================
   COMPLETED LIST (PAGINATED — 50 per page)
   ========================================================================== */
$perPage = 50;

/* Total rows for this organizer */
$countStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM conferences
     WHERE organizer_id = ?
       AND status = 'approved'
       AND end_date < CURDATE()"
);
$countStmt->execute([(int) $profile['id']]);
$completedCount = (int) $countStmt->fetchColumn();

$pager = paginate($completedCount, $perPage);

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
       AND c.end_date < CURDATE()
     ORDER BY c.end_date DESC, c.id DESC
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
$dashboardTitle = 'Completed Conferences';
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
        Historical Archive
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Completed Conferences
        <span class="badge text-bg-light ms-2" style="font-size:.75rem;vertical-align:middle;color:var(--color-text-strong);">
          <?= number_format($completedCount) ?> total
        </span>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Your approved conferences appear here automatically after their end date has passed.
      </p>
    </div>
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
              <th>End Date</th>
              <th>Status</th>
              <th>View</th>
              <th class="text-end">Action</th>
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
                  <strong><?= e($conference['title']) ?></strong>
                </td>

                <td>
                  <span class="small text-secondary">
                    <?= e($conference['topic_name'] ?: '—') ?>
                  </span>
                </td>

                <td class="text-nowrap small">
                  <?= e(format_date($conference['start_date'])) ?>
                </td>

                <td class="text-nowrap small">
                  <?= e(format_date($conference['end_date'])) ?>
                </td>

                <td>
                  <span class="badge text-bg-secondary">
                    <i class="bi bi-archive me-1"></i>Completed
                  </span>
                </td>

                <td>
                  <button type="button"
                          class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#conferenceView<?= $conferenceId ?>">
                    <i class="bi bi-eye me-1"></i>View
                  </button>
                </td>

                <td class="text-end">
                  <button type="submit"
                          name="action"
                          value="delete"
                          formnovalidate
                          class="btn btn-sm btn-outline-danger"
                          data-confirm="Permanently delete this completed conference? This cannot be undone."
                          onclick="document.getElementById('hiddenSingleId').value = '<?= $conferenceId ?>';">
                    <i class="bi bi-trash me-1"></i>Delete
                  </button>
                </td>
              </tr>

            <?php endforeach; ?>

          </tbody>
        </table>
      </div>

    <?php else: ?>

      <div class="empty-state bg-white">
        <i class="bi bi-archive"></i>
        <h2 class="h5 mt-3">No completed conferences</h2>
        <p class="text-secondary mb-0">
          Your approved conferences will appear here after their end date has passed.
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

    <nav class="mt-4" aria-label="Completed conferences pagination">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <div class="small text-secondary">
          Showing
          <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $completedCount) ?></strong>
          of <strong><?= number_format($completedCount) ?></strong> completed conferences
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

  <!-- Hidden input for single-row delete -->
  <input type="hidden" name="id" id="hiddenSingleId" value="">
</form>

<!-- ==========================================================================
     VIEW MODALS
     ========================================================================== -->
<?php foreach ($rows as $conference): ?>
  <?php $conferenceId = (int) $conference['id']; ?>

  <div class="modal fade"
       id="conferenceView<?= $conferenceId ?>"
       tabindex="-1"
       aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1">
              <?= e($conference['title']) ?>
            </h5>
            <span class="badge text-bg-secondary">
              <i class="bi bi-archive me-1"></i>Completed
            </span>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-4">

            <div class="col-md-6">
              <div class="small text-secondary">Topic</div>
              <div class="fw-semibold"><?= e($conference['topic_name'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Attendance Type</div>
              <div class="fw-semibold"><?= e($conference['attendance_type'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Start Date</div>
              <div class="fw-semibold"><?= e(format_date($conference['start_date'])) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">End Date</div>
              <div class="fw-semibold"><?= e(format_date($conference['end_date'])) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Country</div>
              <div class="fw-semibold"><?= e($conference['country_name'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">City</div>
              <div class="fw-semibold"><?= e($conference['city_name'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Venue Address</div>
              <div class="fw-semibold"><?= e($conference['venue'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Contact Email</div>
              <div class="fw-semibold"><?= e($conference['contact_email'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Views</div>
              <div class="fw-semibold"><?= number_format((int) $conference['views_count']) ?></div>
            </div>

            <div class="col-12">
              <div class="small text-secondary">Official Conference Website</div>
              <?php if (!empty($conference['conference_website'])): ?>
                <a href="<?= e($conference['conference_website']) ?>"
                   target="_blank"
                   rel="noopener noreferrer">
                  <?= e($conference['conference_website']) ?>
                </a>
              <?php else: ?>
                <div class="fw-semibold">—</div>
              <?php endif; ?>
            </div>

            <div class="col-12">
              <div class="small text-secondary mb-1">Call for Papers</div>
              <div class="border rounded p-3 bg-light"
                   style="white-space: pre-wrap;"><?= e($conference['description'] ?: '—') ?></div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-secondary"
                  data-bs-dismiss="modal">
            Close
          </button>
        </div>

      </div>
    </div>
  </div>

<?php endforeach; ?>

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
  background: rgba(107, 114, 128, 0.08) !important;
}

tr:has(.row-checkbox:checked) td {
  border-bottom-color: rgba(107, 114, 128, 0.20);
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

  /* Clear bulk checkboxes when a single-row delete is clicked */
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
        var msg = 'PERMANENTLY DELETE ' + selected.length + ' completed conference(s)? This cannot be undone!';
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