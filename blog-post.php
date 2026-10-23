<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   LOOKUP POST BY SLUG
   ========================================================================== */
$slug = getq('slug');

if ($slug === '') {
    http_response_code(404);
    $seo = page_seo(['title' => 'Post Not Found', 'robots' => 'noindex,follow']);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="py-5">
      <div class="container empty-state">
        <i class="bi bi-journal-x"></i>
        <h1 class="h2 mt-3">Post not found</h1>
        <p class="text-secondary">This post may have been removed.</p>
        <a class="btn btn-action" href="<?= e(url('/blog')) ?>">
          <i class="bi bi-arrow-left me-1"></i> Back to Blog
        </a>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = db()->prepare(
    "SELECT id, title, slug, content, featured_image, author, created_at, updated_at
     FROM blog_posts
     WHERE slug = ?
     LIMIT 1"
);
$stmt->execute([$slug]);
$post = $stmt->fetch() ?: null;

/* ----------------------------- 404 ----------------------------- */
if (!$post) {
    http_response_code(404);
    $seo = page_seo(['title' => 'Post Not Found', 'robots' => 'noindex,follow']);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="py-5">
      <div class="container empty-state">
        <i class="bi bi-journal-x"></i>
        <h1 class="h2 mt-3">Post not found</h1>
        <p class="text-secondary">This post may have been removed.</p>
        <a class="btn btn-action" href="<?= e(url('/blog')) ?>">
          <i class="bi bi-arrow-left me-1"></i> Back to Blog
        </a>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ==========================================================================
   RELATED POSTS — most recent, excluding current (up to 6)
   ========================================================================== */
$stmt = db()->prepare(
    "SELECT id, title, slug, content, created_at
     FROM blog_posts
     WHERE id <> ?
     ORDER BY created_at DESC
     LIMIT 6"
);
$stmt->execute([(int) $post['id']]);
$related = $stmt->fetchAll();

/* ==========================================================================
   SEO
   ========================================================================== */
$postUrl     = url('/blog/' . $post['slug']);
$publishedAt = $post['created_at'];

$image = '';
if (!empty($post['featured_image'])) {
    $image = absolute_media_url((string) $post['featured_image']);
}
if ($image === '') {
    $image = url('/assets/images/company-logo.png');
}

$description = rich_text_excerpt((string) $post['content'], 158);

$articleSchema = [
    '@context'         => 'https://schema.org',
    '@type'            => 'BlogPosting',
    'headline'         => $post['title'],
    'description'      => $description,
    'image'            => $image,
    'datePublished'    => date('c', strtotime((string) $publishedAt)),
    'dateModified'     => date('c', strtotime((string) ($post['updated_at'] ?? $publishedAt))),
    'url'              => $postUrl,
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id'   => $postUrl,
    ],
    'author' => [
        '@type' => 'Person',
        'name'  => !empty($post['author']) ? $post['author'] : APP_NAME,
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name'  => APP_NAME,
        'logo'  => [
            '@type' => 'ImageObject',
            'url'   => url('/assets/images/company-logo.png'),
        ],
    ],
];

$seo = page_seo([
    'title'       => $post['title'],
    'description' => $description,
    'canonical'   => $postUrl,
    'image'       => $image,
    'type'        => 'article',
    'robots'      => 'index,follow,max-image-preview:large',
    'json_ld'     => [
        $articleSchema,
        breadcrumb_schema([
            ['name' => 'Home', 'url' => url('/')],
            ['name' => 'Blog', 'url' => url('/blog')],
            ['name' => $post['title'], 'url' => $postUrl],
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
        <li class="breadcrumb-item"><a href="<?= e(url('/blog')) ?>">Blog</a></li>
        <li class="breadcrumb-item active" aria-current="page">
          <?= e(excerpt((string) $post['title'], 60)) ?>
        </li>
      </ol>
    </nav>

    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
      <span class="text-secondary small">
        <i class="bi bi-calendar3 me-1"></i>
        <?= e(date('j F Y', strtotime((string) $publishedAt))) ?>
      </span>

      <?php if (!empty($post['author'])): ?>
        <span class="text-secondary small">
          <i class="bi bi-person-circle me-1"></i>
          <?= e($post['author']) ?>
        </span>
      <?php endif; ?>
    </div>

    <h1 class="display-5 mt-2 blog-post-title"><?= e($post['title']) ?></h1>
  </div>
</section>

<!-- ==========================================================================
     POST CONTENT
     ========================================================================== -->
<section class="blog-post-wrap py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9">

        <!-- Visible H2 for this page -->
        <h2 class="section-title h3 fw-bold mb-4">About This Article</h2>

		<?php if (!empty($post['featured_image'])): ?>
		  <div class="blog-post-image reveal">
		    <img src="<?= e(absolute_media_url((string) $post['featured_image'])) ?>"
		         alt="<?= e($post['title']) ?>"
		         class="w-100">
		  </div>
		<?php endif; ?>

        <article class="blog-post-content reveal">
          <?= rich_text_render((string) $post['content']) ?>
        </article>

        <!-- Author + Meta -->
        <div class="blog-post-footer reveal">
          <div class="blog-post-footer-info">
            <?php if (!empty($post['author'])): ?>
              <div class="blog-post-author">
                <div class="blog-post-author-avatar">
                  <i class="bi bi-person-fill"></i>
                </div>
                <div>
                  <div class="blog-post-author-name"><?= e($post['author']) ?></div>
                  <div class="small text-secondary">Author</div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <a href="<?= e(url('/blog')) ?>" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-arrow-left me-1"></i>
            Back to Blog
          </a>
        </div>

      </div>
    </div>

    <!-- ============ MORE POSTS ============ -->
    <?php if ($related): ?>
      <div class="row justify-content-center mt-5">
        <div class="col-lg-9">
          <!-- Demoted from H2 to H3 -->
          <h3 class="h4 fw-bold mb-4 mt-5">
            <i class="bi bi-journal-text me-2 text-brand"></i>
            More Posts
          </h3>

          <div class="blog-related-grid reveal-stagger">
            <?php foreach ($related as $rp): ?>
              <?php
              $rpDate = $rp['created_at'];
              $rpDesc = excerpt(strip_tags((string) ($rp['content'] ?? '')), 130);
              ?>
              <a href="<?= e(url('/blog/' . $rp['slug'])) ?>" class="blog-related-card">
                <div class="blog-related-body">
                  <h4 class="blog-related-title">
                    <?= e($rp['title']) ?>
                  </h4>

                  <?php if ($rpDesc !== ''): ?>
                    <p class="blog-related-description">
                      <?= e($rpDesc) ?>
                    </p>
                  <?php endif; ?>

                  <div class="blog-related-date">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?= e(date('j M Y', strtotime((string) $rpDate))) ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- ==========================================================================
     PAGE STYLES
     ========================================================================== -->
<style>
.blog-post-title {
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.15;
  color: var(--color-text-strong);
}

.blog-post-wrap {
  background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%);
}

.blog-post-image {
  border-radius: var(--radius-xl);
  overflow: hidden;
  margin-bottom: 2rem;
  box-shadow: var(--shadow-lg);
}

.blog-post-image img {
  display: block;
  max-height: 520px;
  object-fit: cover;
}

.blog-post-content {
  font-size: 1.05rem;
  line-height: 1.8;
  color: var(--color-text);
}

.blog-post-content p {
  margin-bottom: 1.25rem;
}

.blog-post-content h2,
.blog-post-content h3,
.blog-post-content h4 {
  font-family: var(--font-heading);
  color: var(--color-text-strong);
  margin: 2rem 0 1rem;
}

.blog-post-content h2 { font-size: 1.65rem; font-weight: 800; }
.blog-post-content h3 { font-size: 1.35rem; font-weight: 700; }
.blog-post-content h4 { font-size: 1.15rem; font-weight: 700; }

.blog-post-content a {
  color: var(--color-accent);
  text-decoration: none;
  font-weight: 600;
  transition: color var(--dur-fast) var(--ease-standard);
}

.blog-post-content a:hover {
  color: var(--color-action);
  text-decoration: underline;
}

.blog-post-content ul,
.blog-post-content ol {
  padding-left: 1.5rem;
  margin-bottom: 1.25rem;
}

.blog-post-content li {
  margin-bottom: 0.4rem;
}

.blog-post-content blockquote {
  border-left: 4px solid var(--color-accent);
  background: var(--color-bg-soft);
  padding: 1rem 1.25rem;
  margin: 1.5rem 0;
  color: var(--color-text);
  font-style: italic;
  border-radius: 0 var(--radius-md) var(--radius-md) 0;
}

.blog-post-content code {
  background: var(--color-bg-muted);
  color: var(--color-error-dark);
  padding: 0.15em 0.4em;
  border-radius: 4px;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.9em;
}

.blog-post-content pre {
  background: var(--color-primary);
  color: #F8FAFC;
  padding: 1rem 1.25rem;
  border-radius: var(--radius-md);
  overflow-x: auto;
  margin-bottom: 1.25rem;
}

.blog-post-content pre code {
  background: transparent;
  color: inherit;
  padding: 0;
}

.blog-post-content img {
  max-width: 100%;
  height: auto;
  border-radius: var(--radius-md);
  margin: 1rem 0;
}

.blog-post-content hr {
  border: 0;
  border-top: 1px solid var(--color-border);
  margin: 2rem 0;
}

.blog-post-footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 3rem;
  padding-top: 2rem;
  border-top: 1px solid var(--color-border);
}

.blog-post-author {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.blog-post-author-avatar {
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-primary) 100%);
  color: #fff;
  border-radius: 50%;
  font-size: 1.3rem;
}

.blog-post-author-name {
  font-family: var(--font-heading);
  font-weight: 700;
  color: var(--color-text-strong);
}

.blog-related-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.25rem;
}

.blog-related-card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 1.25rem;
  text-decoration: none;
  color: inherit;
  transition:
    transform    var(--dur-base) var(--ease-standard),
    box-shadow   var(--dur-base) var(--ease-standard),
    border-color var(--dur-base) var(--ease-standard);
}

.blog-related-card:hover {
  transform: translateY(-3px);
  border-color: var(--color-accent-light);
  box-shadow: var(--shadow-md);
  color: inherit;
}

.blog-related-body {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.blog-related-title {
  font-family: var(--font-heading);
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--color-text-strong);
  margin: 0 0 0.5rem;
  overflow-wrap: anywhere;
  transition: color var(--dur-fast) var(--ease-standard);
}

.blog-related-card:hover .blog-related-title {
  color: var(--color-accent);
}

.blog-related-description {
  flex: 1;
  font-size: 0.875rem;
  line-height: 1.6;
  color: var(--color-muted);
  margin: 0 0 1rem;
}

.blog-related-date {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-muted);
  padding-top: 0.75rem;
  border-top: 1px solid var(--color-border);
  display: flex;
  align-items: center;
}

@media (max-width: 991.98px) {
  .blog-related-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 575.98px) {
  .blog-related-grid {
    grid-template-columns: 1fr;
  }

  .blog-post-title {
    font-size: 1.6rem;
  }

  .blog-post-content {
    font-size: 1rem;
  }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>