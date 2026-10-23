<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   ACTIONS
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    $id     = (int) post('id');
    $action = post('action');

    if ($action === 'delete' && $id > 0) {
        $stmt = db()->prepare("SELECT id, title FROM blog_posts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if (!$post) {
            flash('danger', 'Post not found.');
            redirect('/admin/blog');
        }

        db()->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);

        flash('success', 'Post deleted successfully.');
        redirect('/admin/blog');
    }
}

/* ==========================================================================
   LIST (PAGINATED — 50 per page)
   ========================================================================== */
$perPage = 50;

/* Total rows */
$totalRows = (int) db()->query(
    "SELECT COUNT(*) FROM blog_posts"
)->fetchColumn();

$pager = paginate($totalRows, $perPage);

/* Fetch this page */
$stmt = db()->prepare(
    "SELECT id, title, slug, featured_image, author, created_at
     FROM blog_posts
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
);

$stmt->bindValue(1, $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue(2, $pager['offset'],   PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Blog Posts';
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
        Content Management
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Blog Posts
        <span class="badge text-bg-light ms-2" style="font-size:.75rem;vertical-align:middle;color:var(--color-text-strong);">
          <?= number_format($totalRows) ?> total
        </span>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Create, edit, or delete blog posts.
      </p>
    </div>

    <a href="<?= e(url('/admin/blog-editor')) ?>"
       class="btn btn-light btn-sm flex-shrink-0">
      <i class="bi bi-plus-circle me-1"></i>
      Add New Post
    </a>
  </div>
</div>

<!-- ==========================================================================
     TABLE
     ========================================================================== -->
<div class="table-card reveal">

  <?php if ($rows): ?>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">Image</th>
            <th>Title</th>
            <th>Author</th>
            <th>Posted On</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>

          <?php foreach ($rows as $post): ?>
            <?php
            $postId = (int) $post['id'];
            $img    = trim((string) ($post['featured_image'] ?? ''));
            $imgUrl = $img !== '' ? absolute_media_url($img) : '';
            ?>

            <tr>
              <td>
                <?php if ($imgUrl !== ''): ?>
                    <img src="<?= e($imgUrl) ?>"
                         alt="<?= e($post['title']) ?> thumbnail"
                         style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border);">
                <?php else: ?>
                  <div class="avatar-circle" style="width:56px;height:56px;font-size:1.2rem;">
                    <i class="bi bi-journal-text"></i>
                  </div>
                <?php endif; ?>
              </td>

              <td>
                <strong><?= e($post['title']) ?></strong>
                <div class="small text-secondary mt-1">
                  <code>/blog/<?= e($post['slug']) ?></code>
                </div>
              </td>

              <td>
                <span class="small"><?= e($post['author']) ?></span>
              </td>

              <td class="small text-nowrap">
                <i class="bi bi-calendar3 me-1 text-secondary"></i>
                <?= e(date('j M Y, g:i A', strtotime((string) $post['created_at']))) ?>
              </td>

              <td class="text-end text-nowrap">
                <div class="d-inline-flex gap-1 flex-wrap justify-content-end">

                  <a href="<?= e(url('/admin/blog-editor?id=' . $postId)) ?>"
                     class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil-square me-1"></i>Edit
                  </a>

                  <a href="<?= e(url('/blog/' . $post['slug'])) ?>"
                     target="_blank"
                     rel="noopener"
                     class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-eye me-1"></i>View
                  </a>

                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $postId ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit"
                            class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this post permanently? This cannot be undone.">
                      <i class="bi bi-trash me-1"></i>Delete
                    </button>
                  </form>

                </div>
              </td>
            </tr>

          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

  <?php else: ?>

    <div class="empty-state bg-white">
      <i class="bi bi-journal-text"></i>
      <h2 class="h5 mt-3">No blog posts yet</h2>
      <p class="text-secondary mb-3">Start by creating your first blog post.</p>
      <a href="<?= e(url('/admin/blog-editor')) ?>" class="btn btn-action">
        <i class="bi bi-plus-circle me-1"></i>
        Add New Post
      </a>
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

  <nav class="mt-4" aria-label="Blog posts pagination">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
      <div class="small text-secondary">
        Showing
        <strong><?= (int) $pager['offset'] + 1 ?></strong>–<strong><?= min((int) $pager['offset'] + (int) $pager['per_page'], $totalRows) ?></strong>
        of <strong><?= number_format($totalRows) ?></strong> blog posts
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

<!-- Admin banner style -->
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
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>