<?php
declare(strict_types=1);

function conference_card(array $c): void
{
    $slug    = $c['slug'] ?? '';
    $topic   = $c['topic_name'] ?? 'General';
    $country = $c['country_name'] ?? '';
    $city    = $c['city_name'] ?? '';
    ?>
    <article class="conference-card">
      <div class="p-4 d-flex flex-column h-100">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <span class="badge rounded-pill topic-badge"><?= e($topic) ?></span>
          <?php if (!empty($c['is_verified'])): ?>
            <span class="text-brand small" title="Verified conference">
              <i class="bi bi-patch-check-fill"></i>
            </span>
          <?php endif; ?>
        </div>

        <h3 class="h6 fw-bold lh-sm mb-2">
          <a class="text-dark text-decoration-none"
             href="<?= e(url('/conference/' . $slug)) ?>"
             target="_blank"
             rel="noopener">
            <?= e($c['title']) ?>
          </a>
        </h3>

        <p class="small text-secondary mb-3">
          <?= e(excerpt((string) $c['description'], 115)) ?>
        </p>

        <div class="mt-auto meta-row d-grid gap-2">
          <div>
            <i class="bi bi-calendar3 me-1"></i>
            <?= e(format_date($c['start_date'])) ?>
            <?php if (($c['end_date'] ?? '') !== ($c['start_date'] ?? '')): ?>
              – <?= e(format_date($c['end_date'])) ?>
            <?php endif; ?>
          </div>

          <div>
            <i class="bi bi-geo-alt me-1"></i>
            <?= e(trim($city . ($city && $country ? ', ' : '') . $country) ?: ($c['attendance_type'] ?? 'Online')) ?>
          </div>

          <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="badge text-bg-light border"><?= e($c['attendance_type'] ?? 'Offline') ?></span>
            <a class="fw-semibold text-brand text-decoration-none"
               href="<?= e(url('/conference/' . $slug)) ?>"
               target="_blank"
               rel="noopener">
              Details <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>
    </article>
    <?php
}

function render_pagination(array $pager, array $query = []): void
{
    if (($pager['pages'] ?? 1) <= 1) return;
    $current = (int)$pager['page'];
    $pages = (int)$pager['pages'];
    echo '<nav class="mt-4" aria-label="Pagination"><ul class="pagination justify-content-center flex-wrap">';
    for ($i = max(1, $current - 2); $i <= min($pages, $current + 2); $i++) {
        $q = http_build_query(array_merge($query, ['page' => $i]));
        $active = $i === $current ? ' active' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . e($q) . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}

function status_badge(string $status): string
{
    $map = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger'];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $class . ' status-badge">' . e(ucfirst($status)) . '</span>';
}


/**
 * Compute a display status that includes "completed"
 * for approved conferences whose end date has passed.
 */
function conference_display_status(array $conference): string
{
    $rawStatus = (string) ($conference['status'] ?? '');

    if (
        $rawStatus === 'approved'
        && !empty($conference['end_date'])
        && $conference['end_date'] < date('Y-m-d')
    ) {
        return 'completed';
    }

    return $rawStatus;
}

/**
 * Badge for computed display status.
 * Extends status_badge() with "Completed".
 */
function display_status_badge(array $conference): string
{
    $status = conference_display_status($conference);

    if ($status === 'completed') {
        return '<span class="badge text-bg-secondary status-badge">'
             . '<i class="bi bi-archive me-1"></i>Completed</span>';
    }

    return status_badge($status);
}