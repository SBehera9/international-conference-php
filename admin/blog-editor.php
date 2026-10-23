<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   EDIT MODE
   ========================================================================== */
$id      = (int) getq('id');
$editing = null;

if ($id > 0) {
    $stmt = db()->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $editing = $stmt->fetch() ?: null;

    if (!$editing) {
        flash('danger', 'Blog post not found.');
        redirect('/admin/blog');
    }
}

$form = [
    'id'             => (int) ($editing['id'] ?? 0),
    'title'          => (string) ($editing['title'] ?? ''),
    'content'        => (string) ($editing['content'] ?? ''),
    'featured_image' => (string) ($editing['featured_image'] ?? ''),
    'author'         => (string) ($editing['author'] ?? ''),
];

$existingImage = $form['featured_image'];

/* ==========================================================================
   SUBMIT
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    $form = [
        'id'             => (int) post('id'),
        'title'          => trim(post('title')),
        'content'        => (string) ($_POST['content'] ?? ''), // DO NOT trim — HTML whitespace matters
        'featured_image' => trim(post('featured_image')),
        'author'         => trim(post('author')),
    ];

    try {
        /* ---------- Validate ---------- */
        if ($form['title'] === '') {
            throw new RuntimeException('Title is required.');
        }
        if (mb_strlen($form['title']) > 255) {
            throw new RuntimeException('Title must be 255 characters or less.');
        }
        if (trim(strip_tags($form['content'])) === '') {
            throw new RuntimeException('Description is required.');
        }
        if ($form['author'] === '') {
            throw new RuntimeException('Author is required.');
        }
        if (mb_strlen($form['author']) > 150) {
            throw new RuntimeException('Author must be 150 characters or less.');
        }

        /* ---------- SANITIZE HTML ---------- */
        $cleanContent = rich_text_purify($form['content']);

        /* ---------- Upload new image (optional) ---------- */
        $imagePath = $existingImage;

        $hasNewUpload =
            !empty($_FILES['image_file']) &&
            is_array($_FILES['image_file']) &&
            ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasNewUpload) {
            $uploaded = upload_image('image_file', 'blog');
            if ($uploaded === null) {
                throw new RuntimeException('Image upload failed.');
            }
            $imagePath = $uploaded;
        }

        if (post('remove_image') === '1') {
            $imagePath = '';
        }

        /* ---------- Slug ---------- */
        $slug = unique_slug('blog_posts', $form['title'], $form['id'] > 0 ? $form['id'] : null);

        /* ---------- Save ---------- */
        if ($form['id'] > 0) {
            $stmt = db()->prepare(
                "UPDATE blog_posts SET
                    title          = ?,
                    slug           = ?,
                    content        = ?,
                    featured_image = ?,
                    author         = ?,
                    updated_at     = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([
                $form['title'],
                $slug,
                $cleanContent,
                $imagePath !== '' ? $imagePath : null,
                $form['author'],
                $form['id'],
            ]);

            flash('success', 'Blog post updated successfully.');
        } else {
            $stmt = db()->prepare(
                "INSERT INTO blog_posts (
                    title, slug, content, featured_image, author,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([
                $form['title'],
                $slug,
                $cleanContent,
                $imagePath !== '' ? $imagePath : null,
                $form['author'],
            ]);

            flash('success', 'Blog post created successfully.');
        }

        redirect('/admin/blog');

    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
}

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = $editing ? 'Edit Blog Post' : 'New Blog Post';
$dashboardRole  = 'admin';

require dirname(__DIR__) . '/includes/dashboard-header.php';

$previewUrl = '';
if ($existingImage !== '') {
    $previewUrl = preg_match('#^https?://#i', $existingImage)
        ? $existingImage
        : url('/' . ltrim($existingImage, '/'));
}
?>

<!-- TinyMCE CDN -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.3.0/tinymce.min.js" referrerpolicy="origin"></script>

<!-- ==========================================================================
     PAGE HEADER
     ========================================================================== -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 reveal">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-<?= $editing ? 'pencil-square' : 'plus-circle' ?> me-2 text-brand"></i>
      <?= $editing ? 'Edit Blog Post' : 'New Blog Post' ?>
    </h2>
    <p class="small text-secondary mb-0">
      <?= $editing ? 'Update this blog post.' : 'Write a new blog post.' ?>
    </p>
  </div>

  <a href="<?= e(url('/admin/blog')) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i> Back to Blog
  </a>
</div>

<!-- ==========================================================================
     FORM
     ========================================================================== -->
<form method="post" enctype="multipart/form-data" class="row g-4 reveal" id="blogEditorForm">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
  <input type="hidden" name="featured_image" value="<?= e($existingImage) ?>">
  <input type="hidden" name="remove_image" id="removeImageFlag" value="0">

  <!-- ==================== MAIN COLUMN ==================== -->
  <div class="col-lg-8">

    <div class="form-section">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-file-text me-2 text-brand"></i>
        Post Content
      </h3>

      <div class="row g-3">

        <!-- Title -->
        <div class="col-12">
          <label class="form-label">
            Title <span class="text-danger">*</span>
          </label>
          <input type="text"
                 name="title"
                 class="form-control form-control-lg"
                 value="<?= e($form['title']) ?>"
                 maxlength="255"
                 placeholder="Enter blog title"
                 required>
          <div class="form-text">
            The URL is created automatically from the title.
          </div>
        </div>

        <!-- Author -->
        <div class="col-md-6">
          <label class="form-label">
            Author <span class="text-danger">*</span>
          </label>
          <input type="text"
                 name="author"
                 class="form-control"
                 value="<?= e($form['author']) ?>"
                 maxlength="150"
                 placeholder="Person or company name"
                 required>
        </div>

        <!-- Description (Rich Editor) -->
        <div class="col-12">
          <label class="form-label">
            Description <span class="text-danger">*</span>
          </label>
          <textarea name="content"
                    id="blogContent"
                    class="form-control"
                    rows="16"><?= e($form['content']) ?></textarea>
          <div class="form-text">
            Use the toolbar to add <strong>bold</strong>, headings, links and lists.
            Links open in a new tab automatically.
          </div>
        </div>

      </div>
    </div>

  </div>

  <!-- ==================== SIDEBAR ==================== -->
  <div class="col-lg-4">

    <!-- Image Upload -->
    <div class="form-section mb-4">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-image me-2 text-brand"></i>
        Featured Image
      </h3>

      <div id="currentImageWrap"
           class="mb-3"
           style="<?= $previewUrl === '' ? 'display:none;' : '' ?>">
        <img src="<?= e($previewUrl) ?>"
             id="currentImage"
             alt="Current image"
             style="width:100%;max-height:220px;object-fit:cover;border-radius:12px;border:1px solid var(--color-border);">
      </div>

      <div id="noImageWrap"
           class="mb-3"
           style="<?= $previewUrl !== '' ? 'display:none;' : '' ?>">
        <div style="padding:2rem;text-align:center;background:var(--color-bg-soft);border:2px dashed var(--color-border-strong);border-radius:12px;color:var(--color-muted);">
          <i class="bi bi-image" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
          <div class="small">No image uploaded yet</div>
        </div>
      </div>

      <label class="form-label">Upload Image</label>
      <input type="file"
             name="image_file"
             id="imageFile"
             class="form-control"
             accept="image/jpeg,image/png,image/webp,image/gif">

<div class="form-text">
    JPG, PNG, WEBP or GIF. Max 500 KB.
</div>

      <?php if ($previewUrl !== ''): ?>
        <button type="button"
                id="removeImageBtn"
                class="btn btn-outline-danger btn-sm w-100 mt-3">
          <i class="bi bi-trash me-1"></i>
          Remove Image
        </button>
      <?php endif; ?>

    </div>

    <!-- Save -->
    <div class="form-section">

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-action">
          <i class="bi bi-check-circle me-1"></i>
          <?= $editing ? 'Save Changes' : 'Publish Post' ?>
        </button>

        <a href="<?= e(url('/admin/blog')) ?>" class="btn btn-outline-secondary">
          Cancel
        </a>
      </div>

      <?php if ($editing && !empty($editing['slug'])): ?>
        <hr>
        <div class="small text-secondary">
          <div class="mb-2">
            <i class="bi bi-link-45deg me-1"></i>
            <strong>Public URL:</strong>
          </div>
          <a href="<?= e(url('/blog/' . $editing['slug'])) ?>"
             target="_blank"
             rel="noopener"
             class="text-decoration-none small text-break">
            <?= e(url('/blog/' . $editing['slug'])) ?>
            <i class="bi bi-box-arrow-up-right ms-1"></i>
          </a>
        </div>
      <?php endif; ?>

    </div>

  </div>
</form>

<!-- ==========================================================================
     TINYMCE INITIALIZATION
     ========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {

  if (typeof tinymce === 'undefined') {
    console.error('TinyMCE failed to load. Check your internet connection or CDN.');
    return;
  }

  tinymce.init({
    selector: '#blogContent',
    height: 560,
    menubar: false,
    branding: false,
    promotion: false,
    plugins: 'link lists code autolink paste',
    toolbar: [
      'undo redo',
      '|',
      'formatselect',
      '|',
      'bold italic underline strikethrough',
      '|',
      'link unlink',
      '|',
      'bullist numlist',
      '|',
      'blockquote hr',
      '|',
      'removeformat code'
    ],

    /* Heading styles */
    block_formats: 'Paragraph=p; Heading 3=h3; Heading 4=h4; Heading 5=h5',

    /* Links: force target="_blank" and safe rel */
    link_default_target: '_blank',
    link_assume_external_targets: true,
    link_rel_list: [
      { title: 'No Referrer', value: 'noopener noreferrer' },
      { title: 'No Follow',   value: 'nofollow noopener' }
    ],

    /* Paste — keep it simple */
    paste_as_text: false,
    paste_webkit_styles: 'none',
    paste_remove_styles_if_webkit: true,
    paste_data_images: false,

    /* Autolink — clicking on URL text auto-creates link */
    link_context_toolbar: true,

    /* Mobile-friendly */
    mobile: {
      toolbar: 'undo redo | bold italic | link | bullist numlist'
    },

    /* Save content back to textarea on submit */
    setup: function (editor) {
      editor.on('change keyup', function () {
        editor.save();
      });
    },

    /* Content CSS — matches public blog styling */
    content_style: `
      body {
        font-family: 'Inter', system-ui, sans-serif;
        font-size: 16px;
        line-height: 1.75;
        color: #1F2937;
        max-width: 100%;
        padding: 1rem;
      }
      h2 { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.6rem; margin: 1.5rem 0 0.75rem; color: #0F172A; }
      h3 { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.35rem; margin: 1.25rem 0 0.65rem; color: #0F172A; }
      h4 { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.15rem; margin: 1rem 0 0.5rem; color: #0F172A; }
      p { margin-bottom: 1rem; }
a { color: #2563EB; text-decoration: none; font-weight: 600; }
a:hover { color: #1D4ED8; text-decoration: underline; }
      blockquote {
        border-left: 4px solid #2563EB;
        background: #F8FAFC;
        padding: 0.75rem 1rem;
        margin: 1rem 0;
        color: #475569;
      }
      code {
        background: #F1F5F9;
        padding: 0.15em 0.4em;
        border-radius: 4px;
        font-family: monospace;
        font-size: 0.9em;
      }
      pre {
        background: #0F172A;
        color: #F8FAFC;
        padding: 1rem;
        border-radius: 8px;
        overflow-x: auto;
      }
      ul, ol { padding-left: 1.5rem; margin-bottom: 1rem; }
      li { margin-bottom: 0.35rem; }
      hr { border: none; border-top: 1px solid #E5E7EB; margin: 1.5rem 0; }
    `
  });

  /* =====================================================
     IMAGE PREVIEW + REMOVE
     ===================================================== */
  var fileInput    = document.getElementById('imageFile');
  var currentWrap  = document.getElementById('currentImageWrap');
  var currentImg   = document.getElementById('currentImage');
  var noImageWrap  = document.getElementById('noImageWrap');
  var removeBtn    = document.getElementById('removeImageBtn');
  var removeFlag   = document.getElementById('removeImageFlag');

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) return;

      var reader = new FileReader();
      reader.onload = function (e) {
        if (currentImg) {
          currentImg.src = e.target.result;
          currentWrap.style.display = 'block';
          noImageWrap.style.display = 'none';
        }
      };
      reader.readAsDataURL(file);

      if (removeFlag) removeFlag.value = '0';
    });
  }

  if (removeBtn) {
    removeBtn.addEventListener('click', function () {
      if (!confirm('Remove this image?')) return;

      if (removeFlag) removeFlag.value = '1';
      if (fileInput)   fileInput.value = '';
      if (currentWrap) currentWrap.style.display = 'none';
      if (noImageWrap) noImageWrap.style.display = 'block';
    });
  }

  /* =====================================================
     SAVE EDITOR CONTENT BEFORE SUBMIT
     ===================================================== */
  var form = document.getElementById('blogEditorForm');
  if (form && typeof tinymce !== 'undefined') {
    form.addEventListener('submit', function () {
      tinymce.triggerSave();
    });
  }

});
</script>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>