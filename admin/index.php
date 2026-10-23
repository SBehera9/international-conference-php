<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   DASHBOARD STATISTICS
   ========================================================================== */
$stats = [];

$stats['all_conferences'] = (int) db()->query(
    "SELECT COUNT(*) FROM conferences"
)->fetchColumn();

$stats['pending_reviews'] = (int) db()->query(
    "SELECT COUNT(*) FROM conferences WHERE status = 'pending'"
)->fetchColumn();

$stats['active_conferences'] = (int) db()->query(
    "SELECT COUNT(*)
     FROM conferences
     WHERE status = 'approved'
       AND is_deactivated = 0
       AND end_date >= CURDATE()"
)->fetchColumn();

$stats['completed_conferences'] = (int) db()->query(
    "SELECT COUNT(*)
     FROM conferences
     WHERE status = 'approved'
       AND end_date < CURDATE()"
)->fetchColumn();

$stats['rejected_conferences'] = (int) db()->query(
    "SELECT COUNT(*) FROM conferences WHERE status = 'rejected'"
)->fetchColumn();

$stats['organizers'] = (int) db()->query(
    "SELECT COUNT(*) FROM organizers"
)->fetchColumn();

$stats['media_partners'] = (int) db()->query(
    "SELECT COUNT(*) FROM media_partners"
)->fetchColumn();

$stats['associates'] = (int) db()->query(
    "SELECT COUNT(*) FROM associates"
)->fetchColumn();

$stats['feedback'] = (int) db()->query(
    "SELECT COUNT(*) FROM user_feedbacks"
)->fetchColumn();

$stats['subscribers'] = (int) db()->query(
    "SELECT COUNT(*) FROM subscriber_emails"
)->fetchColumn();

/* ==========================================================================
   RECENT CONFERENCE SUBMISSIONS
   ========================================================================== */
$recent = db()->query(
    "SELECT
        c.id,
        c.title,
        c.status,
        c.start_date,
        c.end_date,
        o.organization_name
     FROM conferences c
     LEFT JOIN organizers o ON o.id = c.organizer_id
     ORDER BY c.created_at DESC
     LIMIT 10"
)->fetchAll();

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Admin Dashboard';
$dashboardRole  = 'admin';

require dirname(__DIR__) . '/includes/dashboard-header.php';

/* ==========================================================================
   STAT CARDS — each with icon + accent color
   ========================================================================== */
$statCards = [
    [
        'label' => 'All Conferences',
        'key'   => 'all_conferences',
        'icon'  => 'bi-calendar2-event',
        'color' => 'primary',
        'url'   => '/admin/manage-conferences',
    ],
    [
        'label' => 'Pending Reviews',
        'key'   => 'pending_reviews',
        'icon'  => 'bi-hourglass-split',
        'color' => 'warning',
        'url'   => '/admin/pending-conferences',
    ],
    [
        'label' => 'Active Conferences',
        'key'   => 'active_conferences',
        'icon'  => 'bi-patch-check',
        'color' => 'success',
        'url'   => '/admin/manage-conferences?filter=active',
    ],
    [
        'label' => 'Completed',
        'key'   => 'completed_conferences',
        'icon'  => 'bi-archive',
        'color' => 'secondary',
        'url'   => '/admin/completed-conferences',
    ],
    [
        'label' => 'Rejected',
        'key'   => 'rejected_conferences',
        'icon'  => 'bi-x-circle',
        'color' => 'danger',
        'url'   => '/admin/manage-conferences',
    ],
    [
        'label' => 'Total Organizers',
        'key'   => 'organizers',
        'icon'  => 'bi-people',
        'color' => 'primary',
        'url'   => '/admin/organizers',
    ],
    [
        'label' => 'Media Partners',
        'key'   => 'media_partners',
        'icon'  => 'bi-megaphone',
        'color' => 'info',
        'url'   => '/admin/media-partners',
    ],
    [
        'label' => 'Our Associates',
        'key'   => 'associates',
        'icon'  => 'bi-building',
        'color' => 'primary',
        'url'   => '/admin/associates',
    ],
    [
        'label' => 'Feedback',
        'key'   => 'feedback',
        'icon'  => 'bi-chat-square-quote',
        'color' => 'warning',
        'url'   => '/admin/feedback',
    ],
    [
        'label' => 'Subscribers',
        'key'   => 'subscribers',
        'icon'  => 'bi-envelope-check',
        'color' => 'success',
        'url'   => '/admin/subscribers.php',
    ],
];
?>

<!-- ==========================================================================
     WELCOME BANNER
     ========================================================================== -->
<div class="admin-welcome-banner mb-4 reveal">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Welcome back, <?= e($user['name'] ?? 'Admin') ?> 👋
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Here's what's happening on your platform today.
      </p>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= e(url('/admin/pending-conferences')) ?>"
         class="btn btn-light btn-sm">
        <i class="bi bi-hourglass-split me-1"></i>
        Review Pending
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
        'info'      => 'stat-accent-info',
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
          <?= number_format((int) $stats[$card['key']]) ?>
        </div>

        <div class="small text-secondary mt-1">
          <?= e($card['label']) ?>
        </div>

      </a>
    </div>
  <?php endforeach; ?>
</div>

<!-- ==========================================================================
     RECENT SUBMISSIONS
     ========================================================================== -->
<div class="table-card reveal">
  <div class="p-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h2 class="h5 fw-bold mb-1">Recent Submissions</h2>
      <p class="small text-secondary mb-0">
        Latest conference activity across the platform.
      </p>
    </div>

    <a href="<?= e(url('/admin/pending-conferences')) ?>"
       class="btn btn-outline-dark btn-sm">
      View All Pending
      <i class="bi bi-arrow-right ms-1"></i>
    </a>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Conference</th>
          <th>Organizer</th>
          <th>Status</th>
          <th>Start Date</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($recent as $row): ?>
          <?php
          /* Show "Completed" if approved and end date has passed */
$displayStatus = (string) $row['status'];
if ($displayStatus === 'approved' && !empty($row['end_date']) && $row['end_date'] < date('Y-m-d')) {
    $displayStatus = 'completed';
}
          ?>

          <tr>
            <td>
              <strong><?= e($row['title']) ?></strong>
            </td>

            <td>
              <span class="text-secondary">
                <?= e($row['organization_name'] ?: '—') ?>
              </span>
            </td>

            <td>
              <?= display_status_badge($row) ?>
            </td>

            <td class="text-nowrap">
              <?= e(format_date($row['start_date'])) ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$recent): ?>
          <tr>
            <td colspan="4" class="empty-state">
              <i class="bi bi-calendar-x"></i>
              <div class="mt-2">No conference submissions yet.</div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ==========================================================================
     Admin-specific styles
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

/* Stat card color accents — left border */
.stat-card {
  position: relative;
}

.stat-card.stat-accent-primary  { border-left: 4px solid var(--color-accent); }
.stat-card.stat-accent-success  { border-left: 4px solid var(--color-success); }
.stat-card.stat-accent-warning  { border-left: 4px solid var(--color-warning); }
.stat-card.stat-accent-danger   { border-left: 4px solid var(--color-error); }
.stat-card.stat-accent-info     { border-left: 4px solid var(--color-info); }
.stat-card.stat-accent-secondary{ border-left: 4px solid var(--color-muted); }

.stat-card.stat-accent-primary  i { color: var(--color-accent); }
.stat-card.stat-accent-success  i { color: var(--color-success); }
.stat-card.stat-accent-warning  i { color: var(--color-warning); }
.stat-card.stat-accent-danger   i { color: var(--color-error); }
.stat-card.stat-accent-info     i { color: var(--color-info); }
.stat-card.stat-accent-secondary i { color: var(--color-muted); }

.stat-card:hover i:last-child {
  opacity: 1 !important;
  color: var(--color-accent) !important;
}

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
  .admin-welcome-banner {
    padding: 1.25rem;
  }
}
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>