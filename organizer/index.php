<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   DASHBOARD STATISTICS
   ========================================================================== */
$stmt = db()->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'pending' AND end_date >= CURDATE()) AS pending_count,
        SUM(status = 'approved' AND end_date >= CURDATE()) AS approved_count,
        SUM(status = 'rejected' AND end_date >= CURDATE()) AS rejected_count,
        SUM(status = 'approved' AND end_date < CURDATE()) AS completed_count
     FROM conferences
     WHERE organizer_id = ?"
);

$stmt->execute([(int) $profile['id']]);
$stats = $stmt->fetch() ?: [];

/* ==========================================================================
   RECENT CONFERENCES
   ========================================================================== */
$stmt = db()->prepare(
    "SELECT *
     FROM conferences
     WHERE organizer_id = ?
     ORDER BY created_at DESC
     LIMIT 8"
);
$stmt->execute([(int) $profile['id']]);
$recent = $stmt->fetchAll();

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Organizer Dashboard';
$dashboardRole  = 'organizer';

require dirname(__DIR__) . '/includes/dashboard-header.php';

/* ==========================================================================
   STAT CARDS
   ========================================================================== */
$statCards = [
    [
        'label' => 'Total Conferences',
        'key'   => 'total',
        'icon'  => 'bi-calendar2-event',
        'color' => 'primary',
        'url'   => '/organizer/manage-conferences',
    ],
    [
        'label' => 'Pending Review',
        'key'   => 'pending_count',
        'icon'  => 'bi-hourglass-split',
        'color' => 'warning',
        'url'   => '/organizer/pending-conferences',
    ],
    [
        'label' => 'Approved',
        'key'   => 'approved_count',
        'icon'  => 'bi-patch-check',
        'color' => 'success',
        'url'   => '/organizer/manage-conferences',
    ],
    [
        'label' => 'Rejected',
        'key'   => 'rejected_count',
        'icon'  => 'bi-x-octagon',
        'color' => 'danger',
        'url'   => '/organizer/rejected-conferences',
    ],
    [
        'label' => 'Completed',
        'key'   => 'completed_count',
        'icon'  => 'bi-archive',
        'color' => 'secondary',
        'url'   => '/organizer/completed-conferences',
    ],
];
?>

<!-- ==========================================================================
     WELCOME BANNER
     ========================================================================== -->
<div class="organizer-welcome-banner mb-4 reveal">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">

    <div>
      <div class="section-kicker mb-1" style="color:var(--color-highlight);">
        Organizer Portal
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Welcome, <?= e($profile['organization_name'] ?: $user['name']) ?> 👋
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Manage your conferences, track approval status, and reach a global audience.
      </p>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= e(url('/organizer/add-conference')) ?>"
         class="btn btn-light btn-sm">
        <i class="bi bi-plus-circle me-1"></i>
        Add Conference
      </a>
      <a href="<?= e(url('/organizer/manage-conferences')) ?>"
         class="btn btn-outline-light btn-sm">
        <i class="bi bi-list-ul me-1"></i>
        Manage
      </a>
    </div>

  </div>
</div>

<!-- ==========================================================================
     STAT CARDS
     ========================================================================== -->
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-4 mb-4 reveal-stagger">
  <?php foreach ($statCards as $card): ?>
    <?php
    $colorClass = match ($card['color']) {
        'success'   => 'stat-accent-success',
        'warning'   => 'stat-accent-warning',
        'danger'    => 'stat-accent-danger',
        'secondary' => 'stat-accent-secondary',
        default     => 'stat-accent-primary',
    };
    ?>

    <div class="col">
      <a href="<?= e(url($card['url'])) ?>"
         class="stat-card h-100 text-decoration-none d-block <?= e($colorClass) ?>">

        <div class="d-flex justify-content-between align-items-start mb-3">
          <i class="bi <?= e($card['icon']) ?>"></i>
          <i class="bi bi-arrow-up-right text-secondary small opacity-50"></i>
        </div>

        <div class="display-6 fw-bold">
          <?= (int) ($stats[$card['key']] ?? 0) ?>
        </div>

        <div class="small text-secondary mt-1">
          <?= e($card['label']) ?>
        </div>

      </a>
    </div>
  <?php endforeach; ?>
</div>

<!-- ==========================================================================
     RECENT CONFERENCES
     ========================================================================== -->
<div class="table-card reveal">

  <div class="p-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h2 class="h5 fw-bold mb-1">Recent Conferences</h2>
      <p class="small text-secondary mb-0">
        Your latest conference submissions.
      </p>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Conference</th>
          <th>Status</th>
          <th>Start Date</th>
          <th>Venue</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($recent as $conference): ?>
          <?php
          /* Compute a display status that reflects "Completed" for past conferences.
             Raw DB status stays: draft | pending | approved | rejected */
          $displayStatus = (string) $conference['status'];

          if (
              $displayStatus === 'approved'
              && !empty($conference['end_date'])
              && $conference['end_date'] < date('Y-m-d')
          ) {
              $displayStatus = 'completed';
          }
          ?>
          <tr>
            <td>
              <strong><?= e($conference['title']) ?></strong>
            </td>

            <td>
              <?php if ($displayStatus === 'completed'): ?>
                <span class="badge text-bg-secondary status-badge">
                  <i class="bi bi-archive me-1"></i>Completed
                </span>
              <?php else: ?>
                <?= display_status_badge($conference) ?>
              <?php endif; ?>
            </td>

            <td class="text-nowrap">
              <?= e(format_date($conference['start_date'])) ?>
            </td>

            <td>
              <span class="small text-secondary">
                <?= e($conference['venue'] ?: '—') ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$recent): ?>
          <tr>
            <td colspan="4" class="empty-state">
              <i class="bi bi-calendar-x"></i>
              <div class="mt-2">No conferences yet. Use the "Add Conference" button above to submit your first one.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>


<!-- ==========================================================================
     Organizer-specific styles
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

/* Stat card color accents */
.stat-card.stat-accent-primary  { border-left: 4px solid var(--color-accent); }
.stat-card.stat-accent-success  { border-left: 4px solid var(--color-success); }
.stat-card.stat-accent-warning  { border-left: 4px solid var(--color-warning); }
.stat-card.stat-accent-danger   { border-left: 4px solid var(--color-error); }
.stat-card.stat-accent-secondary{ border-left: 4px solid var(--color-muted); }

.stat-card.stat-accent-primary  i { color: var(--color-accent); }
.stat-card.stat-accent-success  i { color: var(--color-success); }
.stat-card.stat-accent-warning  i { color: var(--color-warning); }
.stat-card.stat-accent-danger   i { color: var(--color-error); }
.stat-card.stat-accent-secondary i { color: var(--color-muted); }

/* Quick action cards */
.quick-action-card {
  transition: transform var(--dur-base) var(--ease-standard),
              box-shadow var(--dur-base) var(--ease-standard),
              border-color var(--dur-base) var(--ease-standard);
}

.quick-action-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
  border-color: var(--color-accent);
}

@media (max-width: 767.98px) {
  .organizer-welcome-banner { padding: 1.25rem; }
}
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>