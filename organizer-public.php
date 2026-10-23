<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/components.php';

/* ==========================================================================
   LOOKUP
   ========================================================================== */
$slug = getq('slug');
$o = find_public_organizer_by_slug($slug);

if (!$o) {
    http_response_code(404);
    $seo = page_seo(['title' => 'Organizer Not Found', 'robots' => 'noindex,follow']);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="py-5">
      <div class="container empty-state">
        <i class="bi bi-building"></i>
        <h1 class="h2 mt-3">Organizer not found</h1>
        <p class="text-secondary">
          This organizer profile may have been removed or is not public.
        </p>
        <a class="btn btn-action" href="<?= e(url('/organizers')) ?>">
          <i class="bi bi-arrow-left me-1"></i> Browse Organizers
        </a>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ==========================================================================
   ORGANIZER'S PUBLISHED CONFERENCES
   ========================================================================== */
$stmt = db()->prepare(
    "SELECT c.*,
            ca.name AS topic_name,
            co.name AS country_name,
            ci.name AS city_name
     FROM conferences c
     LEFT JOIN categories ca ON ca.id = c.category_id
     LEFT JOIN countries  co ON co.id = c.country_id
     LEFT JOIN cities     ci ON ci.id = c.city_id
     WHERE c.organizer_id = ?
       AND c.status = 'approved'
       AND c.is_deactivated = 0
     ORDER BY c.end_date >= CURDATE() DESC, c.start_date ASC"
);
$stmt->execute([(int) $o['id']]);
$confs = $stmt->fetchAll();

/* ==========================================================================
   SEO
   ========================================================================== */
$seo = page_seo([
    'title'       => $o['organization_name'] . ' - Conference Organizer',
    'description' => excerpt((string) $o['about_organization'], 158),
    'canonical'   => url('/organizers/' . $o['slug']),
    'image'       => absolute_media_url($o['logo'] ?? '') ?: url('/assets/images/company-logo.png'),
    'json_ld'     => [
        [
            '@context'    => 'https://schema.org',
            '@type'       => 'Organization',
            'name'        => $o['organization_name'],
            'url'         => $o['website'] ?: url('/organizers/' . $o['slug']),
            'logo'        => $o['logo'] ?: null,
            'description' => excerpt((string) $o['about_organization'], 300),
        ],
        breadcrumb_schema([
            ['name' => 'Home',       'url' => url('/')],
            ['name' => 'Organizers', 'url' => url('/organizers')],
            ['name' => $o['organization_name'], 'url' => url('/organizers/' . $o['slug'])],
        ]),
    ],
]);

require __DIR__ . '/includes/header.php';

$location = trim(($o['city'] ?? '') . ', ' . ($o['country'] ?? ''), ', ');
?>

<!-- ==========================================================================
     BREADCRUMB
     ========================================================================== -->
<section class="py-3 bg-soft border-bottom">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/organizers')) ?>">Organizers</a></li>
        <li class="breadcrumb-item active" aria-current="page">
          <?= e($o['organization_name']) ?>
        </li>
      </ol>
    </nav>
  </div>
</section>

<!-- ==========================================================================
     ORGANIZER HERO — the single H1 for this page
     ========================================================================== -->
<section class="page-hero">
  <div class="container">
    <div class="row align-items-center g-4">

      <div class="col-auto reveal">
        <?php if (!empty($o['logo'])): ?>
          <img src="<?= e($o['logo']) ?>"
               alt="<?= e($o['organization_name']) ?> logo"
               class="organizer-logo"
               style="width:110px;height:110px;">
        <?php else: ?>
          <div class="avatar-circle" style="width:110px;height:110px;font-size:36px;">
            <i class="bi bi-building"></i>
          </div>
        <?php endif; ?>
      </div>

      <div class="col reveal">
        <div class="section-kicker">Organizer Profile</div>

        <h1 class="display-6 mt-2 mb-2 d-flex flex-wrap align-items-center gap-2">
          <?= e($o['organization_name']) ?>

          <?php if (!empty($o['is_verified'])): ?>
            <span class="badge text-bg-success"
                  title="Verified organizer">
              <i class="bi bi-patch-check-fill me-1"></i> Verified
            </span>
          <?php endif; ?>
        </h1>

        <?php if (!empty($o['contact_person'])): ?>
          <p class="text-secondary mb-1">
            <i class="bi bi-person-badge me-1"></i>
            <?= e($o['contact_person']) ?>
          </p>
        <?php endif; ?>

        <?php if ($location !== ''): ?>
          <p class="text-secondary mb-2">
            <i class="bi bi-geo-alt me-1"></i>
            <?= e($location) ?>
          </p>
        <?php endif; ?>

        <?php if (!empty($o['website'])): ?>
          <a href="<?= e($o['website']) ?>"
             target="_blank"
             rel="nofollow noopener"
             class="text-brand fw-semibold">
            <i class="bi bi-globe2 me-1"></i>
            Official Website
            <i class="bi bi-box-arrow-up-right ms-1"></i>
          </a>
        <?php endif; ?>
      </div>

      <div class="col-lg-auto ms-lg-auto reveal">
        <div class="d-flex flex-column gap-2 align-items-lg-end">
          <span class="badge text-bg-light border px-3 py-2">
            <i class="bi bi-calendar2-check me-1"></i>
            <?= count($confs) ?> Published Conference<?= count($confs) === 1 ? '' : 's' ?>
          </span>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ==========================================================================
     ABOUT + CONFERENCES
     ========================================================================== -->
<section class="py-5">
  <div class="container">
    <div class="row g-5">

      <!-- About -->
      <div class="col-lg-4 reveal">
        <div class="form-section organizer-about-sticky">

          <!-- Demoted from H2 to H3 -->
          <h3 class="h5 fw-bold mb-3">
            <i class="bi bi-info-circle me-2 text-brand"></i>
            About Organizer
          </h3>

          <?php if (!empty($o['about_organization'])): ?>
            <p class="text-secondary lh-lg mb-4">
              <?= nl2br(e($o['about_organization'])) ?>
            </p>
          <?php else: ?>
            <p class="text-secondary mb-4">
              No description provided.
            </p>
          <?php endif; ?>

          <?php
          $socials = [
              'facebook'  => 'Facebook',
              'instagram' => 'Instagram',
              'linkedin'  => 'LinkedIn',
              'twitter'   => 'Twitter / X',
              'youtube'   => 'YouTube',
              'telegram'  => 'Telegram',
          ];

          $hasSocials = false;
          foreach ($socials as $key => $label) {
              if (!empty($o[$key])) { $hasSocials = true; break; }
          }
          ?>

          <?php if ($hasSocials): ?>
            <h4 class="h6 fw-bold mt-4 mb-3">Follow</h4>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($socials as $key => $label): ?>
                <?php if (!empty($o[$key])): ?>
                  <a href="<?= e($o[$key]) ?>"
                     target="_blank"
                     rel="noopener nofollow"
                     class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-<?= $key === 'twitter' ? 'twitter-x' : $key ?> me-1"></i>
                    <?= e($label) ?>
                  </a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- Conferences -->
      <div class="col-lg-8">

        <div class="d-flex justify-content-between align-items-end mb-4">
          <div>
            <div class="section-kicker">Conference listings</div>
            <!-- The single H2 for this page -->
            <h2 class="h3 fw-bold mb-1">Published Conferences</h2>
            <p class="text-secondary mb-0">
              Approved conferences organized by this organizer.
            </p>
          </div>
        </div>

        <?php if ($confs): ?>
          <div class="row g-4 reveal-stagger">
            <?php foreach ($confs as $c): ?>
              <div class="col-md-6 col-xl-4">
                <?php conference_card($c); ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state bg-soft rounded-4">
            <i class="bi bi-calendar-x"></i>
            <p class="mt-3 mb-0">
              No approved conferences are currently available.
            </p>
          </div>
        <?php endif; ?>

      </div>

    </div>
  </div>
</section>

<style>
/* ==========================================================================
   ABOUT ORGANIZER — STICKY ON DESKTOP
   ========================================================================== */
@media (min-width: 992px) {
  .organizer-about-sticky {
    position: sticky;
    top: 100px;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--color-border-strong) transparent;
  }

  .organizer-about-sticky::-webkit-scrollbar {
    width: 6px;
  }

  .organizer-about-sticky::-webkit-scrollbar-thumb {
    background: var(--color-border-strong);
    border-radius: 999px;
  }

  .organizer-about-sticky::-webkit-scrollbar-thumb:hover {
    background: var(--color-muted);
  }
}

/* Mobile + tablet: no sticky, normal flow */
@media (max-width: 991.98px) {
  .organizer-about-sticky {
    position: static;
    max-height: none;
    overflow: visible;
  }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>