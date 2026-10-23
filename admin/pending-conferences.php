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
            redirect('/admin/pending-conferences');
        }

        /* ---------- BULK APPROVE ---------- */
        if ($bulkAction === 'approve') {
            $pdo = db();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $pdo->prepare(
                "SELECT c.*, o.id AS organizer_profile_id
                 FROM conferences c
                 JOIN organizers o ON o.id = c.organizer_id
                 WHERE c.id IN ({$placeholders})
                   AND c.status = 'pending'"
            );
            $stmt->execute($ids);
            $conferences = $stmt->fetchAll();

            if (empty($conferences)) {
                flash('warning', 'No pending conferences found in selection.');
                redirect('/admin/pending-conferences');
            }

            $approvedCount = 0;
            try {
                $pdo->beginTransaction();

                $updateStmt = $pdo->prepare(
                    "UPDATE conferences
                     SET status = 'approved',
                         rejection_reason = NULL,
                         approved_by = ?,
                         approved_at = NOW(),
                         updated_at = NOW()
                     WHERE id = ?
                       AND status = 'pending'"
                );

                foreach ($conferences as $c) {
                    $updateStmt->execute([(int) $user['id'], (int) $c['id']]);

                    if ($updateStmt->rowCount() > 0) {
                        $approvedCount++;

                        audit_log(
                            'CONFERENCE_APPROVED',
                            (string) $c['title'],
                            (int) $c['id'],
                            (int) $c['organizer_profile_id']
                        );
                    }
                }

                $pdo->commit();

                flash('success', $approvedCount . ' conference(s) approved and published.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk approve failed: ' . $e->getMessage());
            }

            redirect('/admin/pending-conferences');
        }

        /* ---------- BULK REJECT ---------- */
        if ($bulkAction === 'reject') {
            $reason = trim(post('bulk_reason'));

            if ($reason === '') {
                flash('danger', 'Please enter a reason for rejecting the selected items.');
                redirect('/admin/pending-conferences');
            }

            if (mb_strlen($reason) > 2000) {
                flash('danger', 'Reason must be 2000 characters or fewer.');
                redirect('/admin/pending-conferences');
            }

            $pdo = db();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $pdo->prepare(
                "SELECT c.*, o.id AS organizer_profile_id
                 FROM conferences c
                 JOIN organizers o ON o.id = c.organizer_id
                 WHERE c.id IN ({$placeholders})
                   AND c.status = 'pending'"
            );
            $stmt->execute($ids);
            $conferences = $stmt->fetchAll();

            if (empty($conferences)) {
                flash('warning', 'No pending conferences found in selection.');
                redirect('/admin/pending-conferences');
            }

            $rejectedCount = 0;
            try {
                $pdo->beginTransaction();

                $updateStmt = $pdo->prepare(
                    "UPDATE conferences
                     SET status = 'rejected',
                         rejection_reason = ?,
                         approved_by = NULL,
                         approved_at = NULL,
                         updated_at = NOW()
                     WHERE id = ?
                       AND status = 'pending'"
                );

                foreach ($conferences as $c) {
                    $updateStmt->execute([$reason, (int) $c['id']]);

                    if ($updateStmt->rowCount() > 0) {
                        $rejectedCount++;

                        audit_log(
                            'CONFERENCE_REJECTED',
                            $reason,
                            (int) $c['id'],
                            (int) $c['organizer_profile_id']
                        );
                    }
                }

                $pdo->commit();

                flash('warning', $rejectedCount . ' conference(s) rejected.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk reject failed: ' . $e->getMessage());
            }

            redirect('/admin/pending-conferences');
        }

        flash('danger', 'Invalid bulk action.');
        redirect('/admin/pending-conferences');
    }

    /* ==================== SINGLE ACTIONS ==================== */
    $id     = (int) post('id');
    $action = post('action');

    $stmt = db()->prepare(
        "SELECT c.*, o.id AS organizer_profile_id
         FROM conferences c
         JOIN organizers o ON o.id = c.organizer_id
         WHERE c.id = ?
           AND c.status = 'pending'
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $conference = $stmt->fetch();

    if (!$conference) {
        flash('danger', 'Pending conference not found.');
        redirect('/admin/pending-conferences');
    }

    /* ---------- APPROVE ---------- */
    if ($action === 'approve') {
        $pdo = db();

        $update = $pdo->prepare(
            "UPDATE conferences
             SET status = 'approved',
                 rejection_reason = NULL,
                 approved_by = ?,
                 approved_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?
               AND status = 'pending'"
        );
        $update->execute([(int) $user['id'], $id]);

        if ($update->rowCount() === 0) {
            flash('warning', 'This conference is no longer pending.');
            redirect('/admin/pending-conferences');
        }

        audit_log(
            'CONFERENCE_APPROVED',
            (string) $conference['title'],
            $id,
            (int) $conference['organizer_profile_id']
        );

        flash('success', 'Conference approved and published successfully.');
        redirect('/admin/pending-conferences');
    }

    /* ---------- REJECT ---------- */
    if ($action === 'reject') {
        $reason = trim(post('reason'));

        if ($reason === '') {
            flash('danger', 'Please enter a rejection reason.');
            redirect('/admin/pending-conferences');
        }

        if (mb_strlen($reason) > 2000) {
            flash('danger', 'Rejection reason must be 2000 characters or fewer.');
            redirect('/admin/pending-conferences');
        }

        $pdo = db();

        $update = $pdo->prepare(
            "UPDATE conferences
             SET status = 'rejected',
                 rejection_reason = ?,
                 approved_by = NULL,
                 approved_at = NULL,
                 updated_at = NOW()
             WHERE id = ?
               AND status = 'pending'"
        );
        $update->execute([$reason, $id]);

        if ($update->rowCount() === 0) {
            flash('warning', 'This conference is no longer pending.');
            redirect('/admin/pending-conferences');
        }

        audit_log(
            'CONFERENCE_REJECTED',
            $reason,
            $id,
            (int) $conference['organizer_profile_id']
        );

        flash('warning', 'Conference rejected and the reason was saved for the organizer.');
        redirect('/admin/pending-conferences');
    }

    flash('danger', 'Invalid action.');
    redirect('/admin/pending-conferences');
}

/* ==========================================================================
   PENDING LIST (PAGINATED — 50 per page)
   ========================================================================== */
$perPage = 50;

/* Total count */
$totalPending = (int) db()->query(
    "SELECT COUNT(*)
     FROM conferences
     WHERE status = 'pending'"
)->fetchColumn();

$pager = paginate($totalPending, $perPage);

/* Fetch this page */
$stmt = db()->prepare(
    "SELECT
        c.*,
        ca.name AS topic_name,
        co.name AS country_name,
        ci.name AS city_name,
        o.organization_name
     FROM conferences c
     LEFT JOIN categories ca ON ca.id = c.category_id
     LEFT JOIN countries  co ON co.id = c.country_id
     LEFT JOIN cities     ci ON ci.id = c.city_id
     LEFT JOIN organizers o  ON o.id = c.organizer_id
     WHERE c.status = 'pending'
     ORDER BY c.created_at DESC, c.id DESC
     LIMIT ? OFFSET ?"
);

$stmt->bindValue(1, $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue(2, $pager['offset'],   PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$pendingCount = $totalPending;

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Pending Conferences';
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
        Review Queue
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Pending Conferences
        <?php if ($pendingCount > 0): ?>
          <span class="badge text-bg-warning ms-2" style="font-size:.75rem;vertical-align:middle;">
            <?= number_format($pendingCount) ?> awaiting review
          </span>
        <?php else: ?>
          <span class="badge text-bg-success ms-2" style="font-size:.75rem;vertical-align:middle;">
            All caught up
          </span>
        <?php endif; ?>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Review submitted conferences, view complete details, then approve or reject them.
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
          <option value="approve">Approve Selected</option>
          <option value="reject">Reject Selected</option>
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
              <th>Organizer</th>
              <th>Location</th>
              <th>Start Date</th>
              <th>View</th>
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
                  <strong><?= e($conference['title']) ?></strong>
                  <?php if (!empty($conference['topic_name'])): ?>
                    <div class="small text-secondary mt-1">
                      <span class="topic-badge" style="font-size:.65rem;">
                        <?= e($conference['topic_name']) ?>
                      </span>
                    </div>
                  <?php endif; ?>
                </td>

                <td>
                  <div class="text-truncate" style="max-width:180px;">
                    <?= e($conference['organization_name'] ?: 'Unknown') ?>
                  </div>
                </td>

                <td>
                  <div class="small text-secondary">
                    <?= e(trim(($conference['city_name'] ?? '') . ', ' . ($conference['country_name'] ?? ''), ', ')) ?>
                  </div>
                </td>

                <td class="text-nowrap">
                  <?= e(format_date($conference['start_date'])) ?>
                </td>

                <td>
                  <button type="button"
                          class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#conferenceViewModal<?= $conferenceId ?>">
                    <i class="bi bi-eye me-1"></i>View
                  </button>
                </td>

                <td>
                  <span class="badge text-bg-warning">Pending</span>
                </td>

                <td class="text-end">
                  <div class="d-flex flex-wrap justify-content-end gap-1">

                    <button type="submit"
                            name="action"
                            value="approve"
                            formnovalidate
                            class="btn btn-sm btn-success"
                            data-confirm="Approve and publish this conference?"
                            onclick="document.getElementById('hiddenSingleId').value = '<?= $conferenceId ?>';">
                      <i class="bi bi-check-circle me-1"></i>Approve
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#conferenceRejectModal<?= $conferenceId ?>">
                      <i class="bi bi-x-circle me-1"></i>Reject
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
        <i class="bi bi-check-circle text-success"></i>
        <h2 class="h5 mt-3">All caught up!</h2>
        <p class="text-secondary mb-0">
          No conferences are waiting for review.
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
        $qs = $_GET;
        $qs['page'] = $p;
        return '?' . http_build_query($qs);
    };
    ?>

    <nav class="mt-4" aria-label="Pending conferences pagination">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <div class="small text-secondary">
          Showing
          <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $totalPending) ?></strong>
          of <strong><?= number_format($totalPending) ?></strong> pending conferences
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

  <input type="hidden" name="id" id="hiddenSingleId" value="">
</form>

<!-- ==========================================================================
     VIEW MODALS
     ========================================================================== -->
<?php foreach ($rows as $conference): ?>
  <?php $conferenceId = (int) $conference['id']; ?>

  <div class="modal fade" id="conferenceViewModal<?= $conferenceId ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1">
              <i class="bi bi-calendar-event me-2 text-brand"></i>
              Conference Details
            </h5>
            <div class="small text-secondary"><?= e($conference['title']) ?></div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">

            <div class="col-md-6">
              <div class="small text-secondary">Conference Title</div>
              <div class="fw-semibold"><?= e($conference['title']) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Organizer</div>
              <div class="fw-semibold"><?= e($conference['organization_name'] ?: 'Unknown') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Topic</div>
              <div><?= e($conference['topic_name'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Status</div>
              <div><span class="badge text-bg-warning">Pending</span></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Start Date</div>
              <div><?= e(format_date($conference['start_date'])) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">End Date</div>
              <div><?= e(format_date($conference['end_date'])) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Country</div>
              <div><?= e($conference['country_name'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">City</div>
              <div><?= e($conference['city_name'] ?: '—') ?></div>
            </div>

            <div class="col-12">
              <div class="small text-secondary">Venue Address</div>
              <div><?= e($conference['venue'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Attendance Type</div>
              <div><?= e($conference['attendance_type'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Contact Email</div>
              <div>
                <?php if (!empty($conference['contact_email'])): ?>
                  <a href="mailto:<?= e($conference['contact_email']) ?>">
                    <?= e($conference['contact_email']) ?>
                  </a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </div>
            </div>

            <div class="col-12">
              <div class="small text-secondary">Official Conference Website</div>
              <div>
                <?php if (!empty($conference['conference_website'])): ?>
                  <a href="<?= e($conference['conference_website']) ?>" target="_blank" rel="noopener">
                    <?= e($conference['conference_website']) ?>
                  </a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </div>
            </div>

            <div class="col-12">
              <div class="small text-secondary mb-1">Call for Papers</div>
              <div class="border rounded p-3 bg-light" style="white-space: pre-wrap;"><?= e($conference['description'] ?: '—') ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary">Submitted On</div>
              <div><?= e(format_date($conference['created_at'])) ?></div>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>
<?php endforeach; ?>

<!-- ==========================================================================
     REJECT MODALS
     ========================================================================== -->
<?php foreach ($rows as $conference): ?>
  <?php $conferenceId = (int) $conference['id']; ?>

  <div class="modal fade" id="conferenceRejectModal<?= $conferenceId ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $conferenceId ?>">
          <input type="hidden" name="action" value="reject">

          <div class="modal-header">
            <div>
              <h5 class="modal-title">
                <i class="bi bi-x-circle me-2 text-danger"></i>
                Reject Conference
              </h5>
              <div class="small text-secondary mt-1"><?= e($conference['title']) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <label class="form-label">
              Reason for Rejection <span class="text-danger">*</span>
            </label>
            <textarea name="reason"
                      class="form-control"
                      rows="5"
                      maxlength="2000"
                      placeholder="Explain why this conference is being rejected..."
                      required></textarea>
            <div class="form-text">
              This reason will be shown to the organizer in their Rejected Conferences list.
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">
              <i class="bi bi-x-circle me-1"></i> Submit Rejection
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<!-- ==========================================================================
     BULK REJECT MODAL
     ========================================================================== -->
<div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title">
            <i class="bi bi-x-circle me-2 text-danger"></i>
            Reject Selected Conferences
          </h5>
          <div class="small text-secondary mt-1">
            <span id="bulkRejectCount">0</span> conference(s) will be rejected.
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <label class="form-label">
          Reason for Rejection <span class="text-danger">*</span>
        </label>
        <textarea id="bulkRejectReason"
                  class="form-control"
                  rows="5"
                  maxlength="2000"
                  placeholder="This reason will be applied to all selected conferences..."></textarea>
        <div class="form-text">
          This single reason will be sent to all selected organizers.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="bulkRejectConfirmBtn">
          <i class="bi bi-x-circle me-1"></i> Reject All Selected
        </button>
      </div>

    </div>
  </div>
</div>

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
.admin-welcome-banner > * { position: relative; z-index: 1; }

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

  var selectAll    = document.getElementById('selectAll');
  var rowCheckboxes = Array.from(document.querySelectorAll('.row-checkbox'));
  var bulkBar      = document.getElementById('bulkBar');
  var bulkCount    = document.getElementById('bulkCount');
  var bulkAction   = document.getElementById('bulkAction');
  var bulkApplyBtn = document.getElementById('bulkApplyBtn');
  var bulkClearBtn = document.getElementById('bulkClearBtn');
  var bulkForm     = document.getElementById('bulkForm');
  var bulkRejectModal = document.getElementById('bulkRejectModal');
  var bulkRejectReason = document.getElementById('bulkRejectReason');
  var bulkRejectCount = document.getElementById('bulkRejectCount');
  var bulkRejectConfirmBtn = document.getElementById('bulkRejectConfirmBtn');

  if (!bulkForm || !rowCheckboxes.length) return;

  function updateBulkUI() {
    var checked = rowCheckboxes.filter(function (cb) { return cb.checked; });
    var count = checked.length;

    if (bulkCount) bulkCount.textContent = count;
    if (bulkBar) bulkBar.hidden = count === 0;

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

      if (action === 'approve') {
        var msg = 'Approve ' + selected.length + ' selected conference(s)?';
        if (!window.confirm(msg)) return;
        bulkForm.submit();
        return;
      }

      if (action === 'reject') {
        if (bulkRejectCount) bulkRejectCount.textContent = selected.length;
        if (bulkRejectReason) bulkRejectReason.value = '';
        if (window.bootstrap && bulkRejectModal) {
          bootstrap.Modal.getOrCreateInstance(bulkRejectModal).show();
        }
        return;
      }

      alert('Unknown bulk action.');
    });
  }

  if (bulkRejectConfirmBtn) {
    bulkRejectConfirmBtn.addEventListener('click', function () {
      var reason = bulkRejectReason ? bulkRejectReason.value.trim() : '';

      if (reason === '') {
        alert('Please enter a rejection reason.');
        return;
      }

      var existing = bulkForm.querySelector('input[name="bulk_reason"]');
      if (!existing) {
        existing = document.createElement('input');
        existing.type = 'hidden';
        existing.name = 'bulk_reason';
        bulkForm.appendChild(existing);
      }
      existing.value = reason;

      if (bulkAction) bulkAction.value = 'reject';

      bulkForm.submit();
    });
  }

  updateBulkUI();

});
</script>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>