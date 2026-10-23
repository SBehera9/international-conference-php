<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   FILTER — star rating
   ========================================================================== */
$allowedRatings = [0, 1, 2, 3, 4, 5];
$rating = (int) getq('rating', '0');

if (!in_array($rating, $allowedRatings, true)) {
    $rating = 0;
}

/* ==========================================================================
   PAGINATION — 40 per page (10 rows × 4 cols)
   ========================================================================== */
$perPage = 40;

/* Base WHERE */
$where  = "status = 'approved'";
$params = [];

if ($rating > 0) {
    $where  .= " AND rating = ?";
    $params[] = $rating;
}

/* Total rows for current filter */
$countStmt = db()->prepare(
    "SELECT COUNT(*)
     FROM user_feedbacks
     WHERE {$where}"
);
$countStmt->execute($params);
$totalFeedback = (int) $countStmt->fetchColumn();

$pager = paginate($totalFeedback, $perPage);

/* Fetch this page — latest approved on top */
$stmt = db()->prepare(
    "SELECT *
     FROM user_feedbacks
     WHERE {$where}
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
);

$bindIndex = 1;
foreach ($params as $value) {
    $stmt->bindValue($bindIndex++, $value, PDO::PARAM_INT);
}
$stmt->bindValue($bindIndex++, $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue($bindIndex,   $pager['offset'],   PDO::PARAM_INT);
$stmt->execute();
$feedback = $stmt->fetchAll();

/* ==========================================================================
   SEO
   ========================================================================== */
$seoTitle = 'All Customer Feedback';
if ($rating > 0) {
    $seoTitle = $rating . '-Star Customer Feedback';
}

$seo = page_seo([
    'title'       => $seoTitle,
    'description' => 'Read approved customer feedback about International Conference — real experiences from visitors, researchers and professionals.',
    'canonical'   => $rating > 0
        ? url('/feedback/all?rating=' . $rating)
        : url('/feedback/all'),
    'robots'      => $rating > 0 ? 'noindex,follow' : 'index,follow',
    'json_ld'     => [
        breadcrumb_schema([
            ['name' => 'Home',             'url' => url('/')],
            ['name' => 'Feedback',         'url' => url('/feedback')],
            ['name' => 'All Customer Feedback', 'url' => url('/feedback/all')],
        ]),
    ],
]);

require __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     PAGE HERO — the single H1
     ========================================================================== -->
<section class="page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/feedback')) ?>">Feedback</a></li>
        <li class="breadcrumb-item active" aria-current="page">All Feedback</li>
      </ol>
    </nav>

    <div class="section-kicker">Community Voice</div>
    <h1 class="display-5 mt-2">All Customer Feedback</h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      Real experiences from visitors, researchers and professionals who use our conference directory.
      <?php if ($totalFeedback > 0): ?>
        <span class="text-dark fw-semibold"><?= number_format($totalFeedback) ?></span>
        approved review<?= $totalFeedback === 1 ? '' : 's' ?>
        <?= $rating > 0 ? 'with ' . $rating . ' star' . ($rating === 1 ? '' : 's') : '' ?>.
      <?php endif; ?>
    </p>
  </div>
</section>

<!-- ==========================================================================
     FILTER BAR
     ========================================================================== -->
<section class="pt-4 pb-3 bg-soft">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 reveal">

      <div class="small text-secondary">
        <i class="bi bi-funnel me-1"></i>
        Filter:
        <strong class="text-dark">
          <?= $rating > 0 ? $rating . '-Star Reviews' : 'All Ratings' ?>
        </strong>
      </div>

      <form method="get" class="dashboard-filter-form" style="max-width:220px;">
        <select name="rating"
                class="form-select form-select-sm"
                onchange="this.form.submit()">
          <option value="0" <?= $rating === 0 ? 'selected' : '' ?>>All Ratings</option>
          <option value="5" <?= $rating === 5 ? 'selected' : '' ?>>★★★★★ 5 Stars</option>
          <option value="4" <?= $rating === 4 ? 'selected' : '' ?>>★★★★☆ 4 Stars</option>
          <option value="3" <?= $rating === 3 ? 'selected' : '' ?>>★★★☆☆ 3 Stars</option>
          <option value="2" <?= $rating === 2 ? 'selected' : '' ?>>★★☆☆☆ 2 Stars</option>
          <option value="1" <?= $rating === 1 ? 'selected' : '' ?>>★☆☆☆☆ 1 Star</option>
        </select>
      </form>

    </div>
  </div>
</section>

<!-- ==========================================================================
     FEEDBACK GRID
     ========================================================================== -->
<section class="py-5 bg-soft">
  <div class="container">

    <!-- Visible H2 for this page -->
    <h2 class="section-title h3 fw-bold mb-4">Customer Reviews</h2>

    <?php if ($feedback): ?>

      <div class="feedback-all-grid reveal-stagger">
        <?php foreach ($feedback as $item): ?>
          <article class="feedback-public-card">

            <?php if (!empty($item['image'])): ?>
              <div class="feedback-public-photo">
                <img src="<?= e(absolute_media_url($item['image'])) ?>"
                     alt="<?= e($item['name']) ?>"
                     loading="lazy">
              </div>
            <?php else: ?>
              <div class="feedback-public-photo">
                <div class="feedback-public-photo-placeholder">
                  <i class="bi bi-person-fill"></i>
                </div>
              </div>
            <?php endif; ?>

            <div class="feedback-public-name">
              <?= e($item['name']) ?>
            </div>

            <div class="feedback-public-location">
              <?php
              $feedbackLocation = trim(
                  (string) ($item['organization'] ?? '') .
                  (!empty($item['organization']) && !empty($item['country']) ? ', ' : '') .
                  (string) ($item['country'] ?? '')
              );
              ?>
              <?= e($feedbackLocation ?: 'Conference Visitor') ?>
            </div>

            <div class="feedback-public-stars">
              <?php
              $ratingValue = max(1, min(5, (int) $item['rating']));
              for ($star = 1; $star <= 5; $star++):
              ?>
                <i class="bi <?= $star <= $ratingValue ? 'bi-star-fill' : 'bi-star' ?>"></i>
              <?php endfor; ?>
            </div>

            <p class="feedback-public-message">
              "<?= e(excerpt((string) $item['message'], 260)) ?>"
            </p>

          </article>
        <?php endforeach; ?>
      </div>

      <!-- ========== PAGINATION ========== -->
      <?php if ($pager['pages'] > 1): ?>
        <?php
        $currentPage = (int) $pager['page'];
        $totalPages  = (int) $pager['pages'];
        $startPage   = max(1, $currentPage - 2);
        $endPage     = min($totalPages, $currentPage + 2);

        $pageUrl = static function (int $p) use ($rating) : string {
            $qs = [];
            if ($rating > 0) {
                $qs['rating'] = $rating;
            }
            $qs['page'] = $p;
            return '?' . http_build_query($qs);
        };
        ?>

        <nav class="mt-5" aria-label="Feedback pagination">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
            <div class="small text-secondary">
              Showing
              <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $totalFeedback) ?></strong>
              of <strong><?= number_format($totalFeedback) ?></strong> reviews
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

    <?php else: ?>

      <div class="empty-state bg-white rounded-4 border">
        <i class="bi bi-chat-square-quote"></i>
        <!-- Demoted from H2 to H3 -->
        <h3 class="h5 mt-3">No feedback found</h3>
        <p class="text-secondary mb-0">
          <?php if ($rating > 0): ?>
            No approved reviews with <?= $rating ?> star<?= $rating === 1 ? '' : 's' ?> yet.
          <?php else: ?>
            No approved reviews yet.
          <?php endif; ?>
        </p>
      </div>

    <?php endif; ?>

  </div>
</section>

<!-- ==========================================================================
     PAGE STYLES
     ========================================================================== -->
<style>
/* 4-column grid for feedback cards */
.feedback-all-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1.25rem;
}

@media (max-width: 1199.98px) {
  .feedback-all-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
  .feedback-all-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575.98px) {
  .feedback-all-grid { grid-template-columns: 1fr; }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>