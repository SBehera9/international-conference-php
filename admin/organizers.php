<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   FILTER
   ========================================================================== */
$allowedFilters = ['all', 'verified', 'active', 'deactive'];
$filter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

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
            redirect('/admin/organizers');
        }

        $returnFilter = strtolower(trim(post('return_filter')));
        if (!in_array($returnFilter, $allowedFilters, true)) {
            $returnFilter = 'all';
        }
        $returnUrl = '/admin/organizers';
        if ($returnFilter !== 'all') {
            $returnUrl .= '?filter=' . rawurlencode($returnFilter);
        }

        $pdo = db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        /* ---------- Fetch selection ---------- */
        $stmt = $pdo->prepare(
            "SELECT o.id, o.organization_name, o.user_id, o.is_verified, o.is_suspended,
                    u.status AS user_status
             FROM organizers o
             INNER JOIN users u ON u.id = o.user_id
             WHERE o.id IN ({$placeholders})"
        );
        $stmt->execute($ids);
        $selected = $stmt->fetchAll();

        if (empty($selected)) {
            flash('warning', 'No organizers found in selection.');
            redirect($returnUrl);
        }

        /* ---------- BULK VERIFY ---------- */
        if ($bulkAction === 'verify') {
            $count = 0;

            try {
                $pdo->beginTransaction();

                $updateStmt = $pdo->prepare(
                    "UPDATE organizers SET is_verified = 1, updated_at = NOW() WHERE id = ?"
                );

                foreach ($selected as $o) {
                    $updateStmt->execute([(int) $o['id']]);
                    $count++;

                    audit_log(
                        'ORGANIZER_VERIFIED',
                        'Organizer verified: ' . (string) $o['organization_name'],
                        null,
                        (int) $o['id']
                    );
                }

                $pdo->commit();
                flash('success', $count . ' organizer(s) verified successfully.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk verify failed: ' . $e->getMessage());
            }

            redirect($returnUrl);
        }

        /* ---------- BULK DEACTIVATE ---------- */
        if ($bulkAction === 'deactivate') {
            $count = 0;

            try {
                $pdo->beginTransaction();

                $updateOrgStmt = $pdo->prepare(
                    "UPDATE organizers SET is_suspended = 1, updated_at = NOW() WHERE id = ?"
                );
                $updateUserStmt = $pdo->prepare(
                    "UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?"
                );

                foreach ($selected as $o) {
                    $updateOrgStmt->execute([(int) $o['id']]);
                    $updateUserStmt->execute([(int) $o['user_id']]);
                    $count++;

                    audit_log(
                        'ORGANIZER_DEACTIVATED',
                        'Organizer deactivated: ' . (string) $o['organization_name'],
                        null,
                        (int) $o['id']
                    );
                }

                $pdo->commit();
                flash('success', $count . ' organizer(s) deactivated successfully.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk deactivate failed: ' . $e->getMessage());
            }

            redirect($returnUrl);
        }

        /* ---------- BULK ACTIVATE ---------- */
        if ($bulkAction === 'activate') {
            $count = 0;

            try {
                $pdo->beginTransaction();

                $updateOrgStmt = $pdo->prepare(
                    "UPDATE organizers SET is_suspended = 0, updated_at = NOW() WHERE id = ?"
                );
                $updateUserStmt = $pdo->prepare(
                    "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?"
                );

                foreach ($selected as $o) {
                    $updateOrgStmt->execute([(int) $o['id']]);
                    $updateUserStmt->execute([(int) $o['user_id']]);
                    $count++;

                    audit_log(
                        'ORGANIZER_ACTIVATED',
                        'Organizer activated: ' . (string) $o['organization_name'],
                        null,
                        (int) $o['id']
                    );
                }

                $pdo->commit();
                flash('success', $count . ' organizer(s) activated successfully.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk activate failed: ' . $e->getMessage());
            }

            redirect($returnUrl);
        }

        /* ---------- BULK DELETE ---------- */
        if ($bulkAction === 'delete') {
            $count = 0;

            try {
                $pdo->beginTransaction();

                $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");

                foreach ($selected as $o) {
                    $deleteStmt->execute([(int) $o['user_id']]);
                    $count++;

                    audit_log(
                        'ORGANIZER_DELETED',
                        'Organizer deleted: ' . (string) $o['organization_name'],
                        null,
                        (int) $o['id']
                    );
                }

                $pdo->commit();
                flash('success', $count . ' organizer(s) permanently deleted.');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('danger', 'Bulk delete failed: ' . $e->getMessage());
            }

            redirect($returnUrl);
        }

        flash('danger', 'Invalid bulk action.');
        redirect($returnUrl);
    }

    /* ==================== SINGLE ACTIONS ==================== */
    $organizerId  = (int) post('id');
    $action       = post('action');
    $returnFilter = strtolower(trim(post('return_filter')));

    if (!in_array($returnFilter, $allowedFilters, true)) {
        $returnFilter = 'all';
    }

    $returnUrl = '/admin/organizers';
    if ($returnFilter !== 'all') {
        $returnUrl .= '?filter=' . rawurlencode($returnFilter);
    }

    $stmt = db()->prepare(
        'SELECT
            o.id,
            o.organization_name,
            o.user_id,
            o.is_verified,
            o.is_suspended,
            u.status AS user_status
         FROM organizers o
         INNER JOIN users u ON u.id = o.user_id
         WHERE o.id = ?
         LIMIT 1'
    );
    $stmt->execute([$organizerId]);
    $organizer = $stmt->fetch();

    if (!$organizer) {
        flash('danger', 'Organizer not found.');
        redirect($returnUrl);
    }

    try {
        if ($action === 'verify') {
            db()->prepare(
                'UPDATE organizers SET is_verified = 1, updated_at = NOW() WHERE id = ?'
            )->execute([$organizerId]);

            audit_log('ORGANIZER_VERIFIED', 'Organizer verified: ' . (string) $organizer['organization_name'], null, $organizerId);
            flash('success', 'Organizer verified successfully.');

        } elseif ($action === 'deactivate') {
            db()->beginTransaction();
            db()->prepare('UPDATE organizers SET is_suspended = 1, updated_at = NOW() WHERE id = ?')
                ->execute([$organizerId]);
            db()->prepare("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?")
                ->execute([(int) $organizer['user_id']]);
            db()->commit();

            audit_log('ORGANIZER_DEACTIVATED', 'Organizer deactivated: ' . (string) $organizer['organization_name'], null, $organizerId);
            flash('success', 'Organizer deactivated successfully.');

        } elseif ($action === 'activate') {
            db()->beginTransaction();
            db()->prepare('UPDATE organizers SET is_suspended = 0, updated_at = NOW() WHERE id = ?')
                ->execute([$organizerId]);
            db()->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?")
                ->execute([(int) $organizer['user_id']]);
            db()->commit();

            audit_log('ORGANIZER_ACTIVATED', 'Organizer activated: ' . (string) $organizer['organization_name'], null, $organizerId);
            flash('success', 'Organizer activated successfully.');

        } elseif ($action === 'delete') {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([(int) $organizer['user_id']]);

            audit_log('ORGANIZER_DELETED', 'Organizer deleted: ' . (string) $organizer['organization_name'], null, $organizerId);
            flash('success', 'Organizer deleted successfully.');

        } else {
            flash('warning', 'Invalid organizer action.');
        }

    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', 'Unable to update organizer.');
    }

    redirect($returnUrl);
}

/* ==========================================================================
   QUERY (PAGINATED — 50 per page)
   ========================================================================== */
$where  = [];
$params = [];

if ($filter === 'verified') {
    $where[] = 'o.is_verified = 1';
} elseif ($filter === 'active') {
    $where[] = "u.status = 'active'";
    $where[] = 'o.is_suspended = 0';
} elseif ($filter === 'deactive') {
    $where[] = "(u.status = 'suspended' OR o.is_suspended = 1)";
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

/* Total rows */
$countStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM organizers o
     INNER JOIN users u ON u.id = o.user_id"
     . $whereSql
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$perPage = 50;
$pager   = paginate($totalRows, $perPage);

/* Fetch this page */
$sql = "SELECT
            o.*,
            u.name AS user_name,
            u.email AS user_email,
            u.status AS user_status,
            u.last_login_at,
            u.created_at AS account_created_at,
            (
                SELECT COUNT(*)
                FROM conferences c
                WHERE c.organizer_id = o.id
            ) AS conference_count
        FROM organizers o
        INNER JOIN users u ON u.id = o.user_id"
    . $whereSql
    . ' ORDER BY o.created_at DESC, o.id DESC
        LIMIT ? OFFSET ?';

$stmt = db()->prepare($sql);

$bindIndex = 1;
foreach ($params as $value) {
    $stmt->bindValue($bindIndex++, $value, PDO::PARAM_STR);
}
$stmt->bindValue($bindIndex++, $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue($bindIndex,   $pager['offset'],   PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Manage Organizers';
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
        Conference Hosts
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Manage Organizers
        <span class="badge text-bg-light ms-2" style="font-size:.75rem;vertical-align:middle;color:var(--color-text-strong);">
          <?= number_format($totalRows) ?> total
        </span>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Verify, activate, deactivate, or remove organizer accounts.
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
      <?= e(ucfirst($filter === 'all' ? 'All Organizers' : $filter)) ?>
    </strong>
  </div>

  <form method="get" class="dashboard-filter-form" style="max-width:220px;">
    <select name="filter"
            class="form-select form-select-sm"
            onchange="this.form.submit()">
      <option value="all"      <?= $filter === 'all'      ? 'selected' : '' ?>>All Organizers</option>
      <option value="verified" <?= $filter === 'verified' ? 'selected' : '' ?>>Verified Only</option>
      <option value="active"   <?= $filter === 'active'   ? 'selected' : '' ?>>Active Only</option>
      <option value="deactive" <?= $filter === 'deactive' ? 'selected' : '' ?>>Deactivated Only</option>
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
          <option value="verify">Verify Selected</option>
          <option value="activate">Activate Selected</option>
          <option value="deactivate">Deactivate Selected</option>
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
              <th>Organizer</th>
              <th>Email</th>
              <th>Conferences</th>
              <th>Status</th>
              <th>View</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $o): ?>
              <?php
              $isActive   = ($o['user_status'] === 'active' && empty($o['is_suspended']));
              $isVerified = !empty($o['is_verified']);
              $modalId    = 'organizerProfileModal' . (int) $o['id'];
              ?>

              <tr>
                <td>
                  <input type="checkbox"
                         class="form-check-input row-checkbox"
                         name="bulk_ids[]"
                         value="<?= (int) $o['id'] ?>">
                </td>

                <td>
                  <div>
                    <strong><?= e((string) $o['organization_name']) ?></strong>
                    <?php if (empty($o['is_profile_complete'])): ?>
                      <div class="small text-warning">
                        <i class="bi bi-exclamation-circle me-1"></i>Profile incomplete
                      </div>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <span class="small"><?= e((string) $o['user_email']) ?></span>
                </td>

                <td>
                  <span class="fw-semibold"><?= (int) $o['conference_count'] ?></span>
                </td>

                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <?php if ($isVerified): ?>
                      <span class="badge text-bg-success">
                        <i class="bi bi-patch-check-fill me-1"></i>Verified
                      </span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">Not Verified</span>
                    <?php endif; ?>

                    <?php if ($isActive): ?>
                      <span class="badge text-bg-primary">
                        <i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>Active
                      </span>
                    <?php else: ?>
                      <span class="badge text-bg-danger">Deactivated</span>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <button type="button"
                          class="btn btn-sm btn-outline-dark"
                          data-bs-toggle="modal"
                          data-bs-target="#<?= e($modalId) ?>">
                    <i class="bi bi-eye me-1"></i>View
                  </button>
                </td>

                <td class="text-end">
                  <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                    <?php if (!$isVerified): ?>
                      <button type="submit"
                              name="action"
                              value="verify"
                              formnovalidate
                              class="btn btn-sm btn-outline-success"
                              data-confirm="Verify this organizer?"
                              onclick="document.getElementById('hiddenSingleId').value = '<?= (int) $o['id'] ?>';">
                        <i class="bi bi-patch-check me-1"></i>Verify
                      </button>
                    <?php endif; ?>

                    <?php if ($isActive): ?>
                      <button type="submit"
                              name="action"
                              value="deactivate"
                              formnovalidate
                              class="btn btn-sm btn-outline-warning"
                              data-confirm="Deactivate this organizer? They will not be able to log in until reactivated."
                              onclick="document.getElementById('hiddenSingleId').value = '<?= (int) $o['id'] ?>';">
                        <i class="bi bi-pause-circle me-1"></i>Deactivate
                      </button>
                    <?php else: ?>
                      <button type="submit"
                              name="action"
                              value="activate"
                              formnovalidate
                              class="btn btn-sm btn-outline-primary"
                              data-confirm="Activate this organizer?"
                              onclick="document.getElementById('hiddenSingleId').value = '<?= (int) $o['id'] ?>';">
                        <i class="bi bi-play-circle me-1"></i>Activate
                      </button>
                    <?php endif; ?>

                    <button type="submit"
                            name="action"
                            value="delete"
                            formnovalidate
                            class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this organizer permanently? Their organizer profile and conferences will also be deleted."
                            onclick="document.getElementById('hiddenSingleId').value = '<?= (int) $o['id'] ?>';">
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
        <i class="bi bi-people"></i>
        <h2 class="h5 mt-3">No organizers found</h2>
        <p class="text-secondary mb-3">
          <?php if ($filter !== 'all'): ?>
            No organizers match this filter.
          <?php else: ?>
            No organizers have signed up yet.
          <?php endif; ?>
        </p>

        <?php if ($filter !== 'all'): ?>
          <a href="<?= e(url('/admin/organizers')) ?>" class="btn btn-outline-secondary btn-sm">
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

    <nav class="mt-4" aria-label="Organizers pagination">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
        <div class="small text-secondary">
          Showing
          <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $totalRows) ?></strong>
          of <strong><?= number_format($totalRows) ?></strong> organizers
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
     PROFILE MODALS — OUTSIDE THE TABLE
     ========================================================================== -->
<?php foreach ($rows as $o): ?>
  <?php
  $isActive   = ($o['user_status'] === 'active' && empty($o['is_suspended']));
  $isVerified = !empty($o['is_verified']);

  $logo = trim((string) ($o['logo'] ?? ''));
  if ($logo !== '' && !preg_match('#^https?://#i', $logo)) {
      $logo = url($logo);
  }

  $modalId = 'organizerProfileModal' . (int) $o['id'];
  ?>

  <div class="modal fade"
       id="<?= e($modalId) ?>"
       tabindex="-1"
       aria-labelledby="<?= e($modalId) ?>Label"
       aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <div>
            <h2 class="modal-title h5 fw-bold mb-1" id="<?= e($modalId) ?>Label">
              Organizer Profile
            </h2>
            <div class="small text-secondary">
              Full account and public profile details.
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">

          <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 mb-4">
            <?php if ($logo !== ''): ?>
              <img src="<?= e($logo) ?>"
                   alt="<?= e((string) $o['organization_name']) ?> logo"
                   width="82" height="82"
                   class="rounded-4 border bg-white"
                   style="object-fit:contain;padding:8px;">
            <?php else: ?>
              <div class="avatar-circle" style="width:82px;height:82px;font-size:28px;">
                <i class="bi bi-building"></i>
              </div>
            <?php endif; ?>

            <div>
              <h3 class="h4 fw-bold mb-2">
                <?= e((string) $o['organization_name']) ?>
              </h3>

              <div class="d-flex flex-wrap gap-2">
                <?php if ($isVerified): ?>
                  <span class="badge text-bg-success">
                    <i class="bi bi-patch-check-fill me-1"></i>Verified
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Not Verified</span>
                <?php endif; ?>

                <?php if ($isActive): ?>
                  <span class="badge text-bg-primary">Active</span>
                <?php else: ?>
                  <span class="badge text-bg-danger">Deactivated</span>
                <?php endif; ?>

                <?php if (!empty($o['is_profile_complete'])): ?>
                  <span class="badge text-bg-light border">Profile Complete</span>
                <?php else: ?>
                  <span class="badge text-bg-warning">Profile Incomplete</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="row g-4">

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Organization Name</div>
              <div class="fw-semibold"><?= e((string) $o['organization_name']) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Contact Person</div>
              <div class="fw-semibold"><?= e((string) ($o['contact_person'] ?: '—')) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Account Email</div>
              <div class="fw-semibold"><?= e((string) $o['user_email']) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Contact Email</div>
              <div class="fw-semibold"><?= e((string) ($o['email'] ?: '—')) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Phone</div>
              <div class="fw-semibold"><?= e((string) ($o['phone'] ?: '—')) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Website</div>
              <?php if (!empty($o['website'])): ?>
                <a href="<?= e((string) $o['website']) ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="fw-semibold text-decoration-none">
                  <?= e((string) $o['website']) ?>
                  <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                </a>
              <?php else: ?>
                <div class="fw-semibold">—</div>
              <?php endif; ?>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Country</div>
              <div class="fw-semibold"><?= e((string) ($o['country'] ?: '—')) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">City</div>
              <div class="fw-semibold"><?= e((string) ($o['city'] ?: '—')) ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Total Conferences</div>
              <div class="fw-semibold"><?= (int) $o['conference_count'] ?></div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Joined</div>
              <div class="fw-semibold">
                <?= !empty($o['account_created_at'])
                    ? e(date('j F Y', strtotime((string) $o['account_created_at'])))
                    : '—' ?>
              </div>
            </div>

            <div class="col-md-6">
              <div class="small text-secondary mb-1">Last Login</div>
              <div class="fw-semibold">
                <?= !empty($o['last_login_at'])
                    ? e(date('j F Y, g:i A', strtotime((string) $o['last_login_at'])))
                    : '—' ?>
              </div>
            </div>

            <div class="col-12">
              <div class="small text-secondary mb-2">About Organization</div>
              <div class="border rounded-3 p-3 bg-light">
                <?= nl2br(e((string) ($o['about_organization'] ?: 'No description added.'))) ?>
              </div>
            </div>

            <?php
            $socialLinks = [
                'Facebook'     => $o['facebook']  ?? null,
                'Instagram'    => $o['instagram'] ?? null,
                'LinkedIn'     => $o['linkedin']  ?? null,
                'X / Twitter'  => $o['twitter']   ?? null,
                'YouTube'      => $o['youtube']   ?? null,
                'WhatsApp'     => $o['whatsapp']  ?? null,
                'Telegram'     => $o['telegram']  ?? null,
                'TikTok'       => $o['tiktok']    ?? null,
                'GitHub'       => $o['github']    ?? null,
                'Pinterest'    => $o['pinterest'] ?? null,
            ];

            $hasSocialLinks = false;
            foreach ($socialLinks as $v) {
                if (trim((string) $v) !== '') { $hasSocialLinks = true; break; }
            }
            ?>

            <?php if ($hasSocialLinks): ?>
              <div class="col-12">
                <div class="small text-secondary mb-2">Social Links</div>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach ($socialLinks as $name => $value): ?>
                    <?php if (trim((string) $value) !== ''): ?>
                      <?php
                      $href = (string) $value;
                      if ($name === 'WhatsApp' && !preg_match('#^https?://#i', $href)) {
                          $digits = preg_replace('/\D+/', '', $href) ?? '';
                          $href = $digits !== '' ? 'https://wa.me/' . $digits : $href;
                      }
                      ?>
                      <a href="<?= e($href) ?>"
                         target="_blank"
                         rel="noopener noreferrer"
                         class="btn btn-sm btn-outline-secondary">
                        <?= e($name) ?>
                      </a>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
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
        alert('Please select at least one organizer.');
        return;
      }

      if (action === '') {
        alert('Please choose a bulk action from the dropdown.');
        return;
      }

      var messages = {
        verify:     'Verify ' + selected.length + ' organizer(s)?',
        activate:   'Activate ' + selected.length + ' organizer(s)? They will be able to log in again.',
        deactivate: 'DEACTIVATE ' + selected.length + ' organizer(s)? They will be locked out until reactivated.',
        delete:     'PERMANENTLY DELETE ' + selected.length + ' organizer(s)? Their conferences will also be deleted! This cannot be undone!'
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