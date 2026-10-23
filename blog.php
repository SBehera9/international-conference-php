<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   PAGINATION
   ========================================================================== */
$page    = max(1, (int) getq('page', '1'));
$perPage = 10;

$total = (int) db()->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();

$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

/* ==========================================================================
   FETCH POSTS
   ========================================================================== */
$stmt = db()->prepare(
    "SELECT id, title, slug, content, featured_image, author, created_at
     FROM blog_posts
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

/* ==========================================================================
   SEO — per-page keywords + CollectionPage schema
   ========================================================================== */
$pageKeywords = page_keywords('blog');

$blogCanonical   = $page > 1 ? url('/blog?page=' . $page) : url('/blog');
$blogCollectionName = 'Blog';
$blogCollectionDesc = 'News, announcements and insights from ' . APP_NAME . '.';

$seo = page_seo([
    'title'       => $blogCollectionName,
    'description' => $blogCollectionDesc,
    'keywords'    => $pageKeywords,
    'canonical'   => $blogCanonical,
    'robots'      => 'index,follow',
    'json_ld'     => [
        collection_schema(
            $blogCollectionName,
            $blogCanonical,
            $blogCollectionDesc,
            $pageKeywords
        ),
        breadcrumb_schema([
            ['name' => 'Home', 'url' => url('/')],
            ['name' => 'Blog', 'url' => url('/blog')],
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
        <li class="breadcrumb-item active" aria-current="page">Blog</li>
      </ol>
    </nav>

    <div class="section-kicker">Insights & Updates</div>
    <h1 class="display-5 mt-2">Blog</h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      News, announcements and insights from the world of international conferences.
    </p>
  </div>
</section>

<!-- ==========================================================================
     BLOG LIST
     ========================================================================== -->
<section class="py-5">
  <div class="container">

    <!-- Visible H2 for this page -->
    <h2 class="section-title h3 fw-bold mb-4">Latest Blog Posts</h2>

    <?php if ($posts): ?>

      <div class="blog-list reveal-stagger">

        <?php foreach ($posts as $post): ?>
          <?php
          $postUrl  = url('/blog/' . $post['slug']);
          $image    = trim((string) ($post['featured_image'] ?? ''));
          $imageUrl = $image !== '' ? absolute_media_url($image) : '';
          ?>

          <article class="blog-list-item">

            <!-- Image -->
            <a href="<?= e($postUrl) ?>" class="blog-list-thumb">
              <?php if ($imageUrl !== ''): ?>
                <img src="<?= e($imageUrl) ?>"
                     alt="<?= e($post['title']) ?>"
                     loading="lazy">
              <?php else: ?>
                <div class="blog-list-thumb-placeholder">
                  <i class="bi bi-journal-text"></i>
                </div>
              <?php endif; ?>
            </a>

            <!-- Content -->
            <div class="blog-list-body">

              <div class="blog-list-meta">
                <span class="blog-list-date">
                  <i class="bi bi-calendar3 me-1"></i>
                  <?= e(date('j M Y', strtotime((string) $post['created_at']))) ?>
                </span>

                <?php if (!empty($post['author'])): ?>
                  <span class="blog-list-author">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= e($post['author']) ?>
                  </span>
                <?php endif; ?>
              </div>

              <!-- Demoted from H2 to H3 -->
              <h3 class="blog-list-title">
                <a href="<?= e($postUrl) ?>">
                  <?= e($post['title']) ?>
                </a>
              </h3>

              <p class="blog-list-excerpt">
                <?= e(rich_text_excerpt((string) $post['content'], 180)) ?>
              </p>

              <a href="<?= e($postUrl) ?>" class="blog-list-more">
                Read More <i class="bi bi-arrow-right ms-1"></i>
              </a>

            </div>

          </article>
        <?php endforeach; ?>

      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <?php
        $startPage = max(1, $page - 2);
        $endPage   = min($totalPages, $page + 2);
        ?>

        <nav class="mt-5" aria-label="Blog pagination">
          <ul class="pagination justify-content-center flex-wrap">

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link"
                 href="<?= $page > 1 ? e(url('/blog?page=' . ($page - 1))) : '#' ?>">
                <i class="bi bi-chevron-left"></i>
              </a>
            </li>

            <?php if ($startPage > 1): ?>
              <li class="page-item">
                <a class="page-link" href="<?= e(url('/blog?page=1')) ?>">1</a>
              </li>
              <?php if ($startPage > 2): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
              <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('/blog?page=' . $i)) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
              <?php if ($endPage < $totalPages - 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
              <?php endif; ?>
              <li class="page-item">
                <a class="page-link" href="<?= e(url('/blog?page=' . $totalPages)) ?>"><?= $totalPages ?></a>
              </li>
            <?php endif; ?>

            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link"
                 href="<?= $page < $totalPages ? e(url('/blog?page=' . ($page + 1))) : '#' ?>">
                <i class="bi bi-chevron-right"></i>
              </a>
            </li>

          </ul>
        </nav>
      <?php endif; ?>

    <?php else: ?>

      <div class="empty-state bg-soft rounded-4">
        <i class="bi bi-journal-text"></i>
        <!-- Demoted from H2 to H3 -->
        <h3 class="h5 mt-3">No posts yet</h3>
        <p class="text-secondary mb-0">
          Blog posts will appear here once published.
        </p>
      </div>

    <?php endif; ?>

  </div>
</section>

<!-- ==========================================================================
     PAGE STYLES
     ========================================================================== -->
<style>
.blog-list {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.blog-list-item {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 1.5rem;
  padding: 1.25rem;
  background: var(--color-white);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-xs);
  transition:
    transform   var(--dur-base) var(--ease-standard),
    box-shadow  var(--dur-base) var(--ease-standard),
    border-color var(--dur-base) var(--ease-standard);
}

.blog-list-item:hover {
  transform: translateY(-3px);
  border-color: var(--color-accent-light);
  box-shadow: var(--shadow-lg);
}

.blog-list-thumb {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 3;
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: linear-gradient(135deg, #EFF6FF 0%, #F8FAFC 100%);
  flex-shrink: 0;
  text-decoration: none;
}

.blog-list-thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: center;
  background: #F8FAFC;
  padding: 6px;
  transition: transform var(--dur-slow) var(--ease-out);
}

.blog-list-item:hover .blog-list-thumb img {
  transform: scale(1.05);
}

.blog-list-thumb-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--color-accent);
  font-size: 2.5rem;
  background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
}

.blog-list-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.blog-list-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  font-size: var(--fs-xs);
  color: var(--color-muted);
  margin-bottom: 0.75rem;
}

.blog-list-date,
.blog-list-author {
  display: inline-flex;
  align-items: center;
}

.blog-list-title {
  font-family: var(--font-heading);
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.3;
  letter-spacing: -0.02em;
  margin: 0 0 0.6rem;
  color: var(--color-text-strong);
  overflow-wrap: anywhere;
}

.blog-list-title a {
  color: inherit;
  text-decoration: none;
  transition: color var(--dur-fast) var(--ease-standard);
}

.blog-list-title a:hover {
  color: var(--color-accent);
}

.blog-list-excerpt {
  flex: 1;
  font-size: var(--fs-sm);
  line-height: 1.65;
  color: var(--color-muted);
  margin: 0 0 1rem;
}

.blog-list-more {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  color: var(--color-accent);
  font-family: var(--font-heading);
  font-weight: 700;
  font-size: var(--fs-sm);
  text-decoration: none;
}

.blog-list-more:hover {
  color: var(--color-action);
}

.blog-list-more i {
  transition: transform var(--dur-fast) var(--ease-standard);
}

.blog-list-more:hover i {
  transform: translateX(4px);
}

@media (max-width: 767.98px) {
  .blog-list-item {
    grid-template-columns: 1fr;
    gap: 1rem;
    padding: 1rem;
  }

  .blog-list-thumb {
    aspect-ratio: 16 / 9;
  }

  .blog-list-title {
    font-size: 1.15rem;
  }
}

@media (max-width: 575.98px) {
  .blog-list-item { padding: 0.85rem; }
  .blog-list-meta { gap: 0.5rem; }
  .blog-list-title { font-size: 1.05rem; }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>