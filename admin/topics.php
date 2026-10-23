<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   ACTIONS
   ========================================================================== */
if (is_post()) {
    verify_csrf();
    $action = post('action');

    /* ==================== ADD SINGLE TOPIC ==================== */
    if ($action === 'add') {
        $name = trim(post('name'));

        if ($name === '') {
            flash('danger', 'Topic name is required.');
            redirect('/admin/topics');
        }

        if (strlen($name) > 150) {
            flash('danger', 'Topic name is too long.');
            redirect('/admin/topics');
        }

        $slug = slugify($name);

        if ($slug === '') {
            flash('danger', 'Enter a valid topic name.');
            redirect('/admin/topics');
        }

        /* Duplicate check */
        $check = db()->prepare('SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1');
        $check->execute([$name, $slug]);

        if ($check->fetch()) {
            flash('warning', 'This topic already exists.');
            redirect('/admin/topics');
        }

        try {
            $stmt = db()->prepare(
                "INSERT INTO categories (name, slug, description, status, created_at, updated_at)
                 VALUES (?, ?, NULL, 'active', NOW(), NOW())"
            );
            $stmt->execute([$name, $slug]);

            flash('success', 'Topic added successfully.');
        } catch (Throwable $e) {
            flash('danger', 'Unable to add the topic.');
        }

        redirect('/admin/topics');
    }

    /* ==================== BULK UPLOAD ==================== */
    if ($action === 'bulk_upload') {
        if (!isset($_FILES['topic_file']) || !is_array($_FILES['topic_file'])) {
            flash('danger', 'Please choose a topic file.');
            redirect('/admin/topics');
        }

        $file = $_FILES['topic_file'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('danger', 'Topic file upload failed.');
            redirect('/admin/topics');
        }

        if (($file['size'] ?? 0) > 1024 * 1024) {
            flash('danger', 'Topic file must be smaller than 1 MB.');
            redirect('/admin/topics');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'txt'], true)) {
            flash('danger', 'Only CSV and TXT files are allowed.');
            redirect('/admin/topics');
        }

        $temporaryFile = (string) ($file['tmp_name'] ?? '');

        if ($temporaryFile === '' || !is_uploaded_file($temporaryFile)) {
            flash('danger', 'Invalid uploaded file.');
            redirect('/admin/topics');
        }

        $topicNames = [];

        /* ---------- CSV ---------- */
        if ($extension === 'csv') {
            $handle = fopen($temporaryFile, 'r');

            if ($handle === false) {
                flash('danger', 'Unable to read the uploaded CSV file.');
                redirect('/admin/topics');
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (!isset($row[0])) continue;

                $topicName = trim((string) $row[0]);
                if ($topicName === '') continue;

                $headerCheck = strtolower($topicName);

                if (in_array($headerCheck, ['topic', 'topic name', 'topic_name', 'name'], true)) {
                    continue;
                }

                $topicNames[] = $topicName;
            }

            fclose($handle);
        } else {
            /* ---------- TXT ---------- */
            $lines = file($temporaryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($lines === false) {
                flash('danger', 'Unable to read the uploaded TXT file.');
                redirect('/admin/topics');
            }

            foreach ($lines as $line) {
                $topicName = trim((string) $line);
                if ($topicName !== '') {
                    $topicNames[] = $topicName;
                }
            }
        }

        if (!$topicNames) {
            flash('danger', 'No topic names were found in the uploaded file.');
            redirect('/admin/topics');
        }

        /* Dedupe within file */
        $uniqueTopics = [];
        foreach ($topicNames as $topicName) {
            $key = strtolower(trim($topicName));
            if ($key !== '' && !isset($uniqueTopics[$key])) {
                $uniqueTopics[$key] = trim($topicName);
            }
        }

        $added   = 0;
        $skipped = 0;

        $check  = db()->prepare('SELECT id FROM categories WHERE name = ? OR slug = ? LIMIT 1');
        $insert = db()->prepare(
            "INSERT INTO categories (name, slug, description, status, created_at, updated_at)
             VALUES (?, ?, NULL, 'active', NOW(), NOW())"
        );

        try {
            db()->beginTransaction();

            foreach ($uniqueTopics as $topicName) {
                if ($topicName === '' || strlen($topicName) > 150) {
                    $skipped++;
                    continue;
                }

                $slug = slugify($topicName);

                if ($slug === '') {
                    $skipped++;
                    continue;
                }

                $check->execute([$topicName, $slug]);

                if ($check->fetch()) {
                    $skipped++;
                    continue;
                }

                $insert->execute([$topicName, $slug]);
                $added++;
            }

            db()->commit();

            flash(
                'success',
                $added . ' topic(s) added successfully. ' .
                $skipped . ' duplicate/invalid topic(s) skipped.'
            );

        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('danger', 'Bulk topic upload failed.');
        }

        redirect('/admin/topics');
    }

    /* ==================== DELETE ==================== */
    if ($action === 'delete') {
        $id = (int) post('id');

        if ($id <= 0) {
            flash('danger', 'Invalid topic.');
            redirect('/admin/topics');
        }

        try {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('success', 'Topic deleted successfully.');
        } catch (Throwable $e) {
            flash('danger', 'Unable to delete this topic.');
        }

        redirect('/admin/topics');
    }
}

/* ==========================================================================
   STATS
   ========================================================================== */
$totalTopics = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();

/* ==========================================================================
   LIST
   ========================================================================== */
$rows = db()->query(
    "SELECT
        ca.id,
        ca.name,
        ca.status,
        (
            SELECT COUNT(*)
            FROM conferences c
            WHERE c.category_id = ca.id
        ) AS conference_count
     FROM categories ca
     ORDER BY ca.name ASC"
)->fetchAll();

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Topics';
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
        Conference Taxonomy
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Topics
        <span class="badge text-bg-light ms-2" style="font-size:.75rem;vertical-align:middle;color:var(--color-text-strong);">
          <?= number_format($totalTopics) ?> total
        </span>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Manage topics used to categorize conferences across the platform.
      </p>
    </div>
  </div>
</div>

<!-- ==========================================================================
     TOP ACTION AREA (Add + Bulk Upload)
     ========================================================================== -->
<div class="row g-4 mb-4">

  <!-- Manual Add -->
  <div class="col-lg-6">
    <div class="form-section h-100 reveal">

      <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
        <i class="bi bi-plus-circle me-2 text-brand"></i>
        Add Topic Manually
      </h3>

      <form method="post" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">

        <div class="col-12">
          <label class="form-label">Topic Name <span class="text-danger">*</span></label>
          <input type="text"
                 name="name"
                 class="form-control"
                 maxlength="150"
                 placeholder="Example: Artificial Intelligence"
                 required>
          <div class="form-text">
            Name must be unique. Slug is generated automatically.
          </div>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-action">
            <i class="bi bi-plus-lg me-1"></i>
            Add Topic
          </button>
        </div>
      </form>

    </div>
  </div>

  <!-- Bulk Upload -->
  <div class="col-lg-6">
    <div class="form-section h-100 reveal">

      <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
        <i class="bi bi-cloud-arrow-up me-2 text-brand"></i>
        Bulk Topic Upload
      </h3>

      <form method="post" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_upload">

        <div class="col-12">
          <label class="form-label">Topic File <span class="text-danger">*</span></label>
          <input type="file"
                 name="topic_file"
                 class="form-control"
                 accept=".csv,.txt"
                 required>
          <div class="form-text">
            Upload CSV (first column = names) or TXT (one topic per line).
          </div>
        </div>

        <div class="col-12">
          <div class="border rounded-3 p-3 bg-light">
            <div class="fw-bold small mb-2">Supported Formats</div>
            <div class="small text-secondary mb-2">CSV:</div>
            <code class="d-block small">Topic Name</code>
            <code class="d-block small">Medicine &amp; Health</code>
            <code class="d-block small mb-3">Engineering</code>
            <div class="small text-secondary mb-2">TXT (one per line):</div>
            <code class="d-block small">Medicine &amp; Health</code>
            <code class="d-block small">Engineering</code>
          </div>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-dark">
            <i class="bi bi-cloud-arrow-up me-1"></i>
            Upload Topics
          </button>
        </div>
      </form>

    </div>
  </div>

</div>

<!-- ==========================================================================
     TOPIC LIST
     ========================================================================== -->
<div class="table-card reveal">

  <div class="p-4 border-bottom">
    <h2 class="h5 fw-bold mb-1">All Topics</h2>
    <p class="small text-secondary mb-0">
      Manage conference topics available on the platform.
    </p>
  </div>

  <?php if ($rows): ?>

    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Topic Name</th>
            <th>Conferences Using This Topic</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>

          <?php foreach ($rows as $row): ?>
            <tr>

              <td>
                <strong><?= e($row['name']) ?></strong>
              </td>

              <td>
                <span class="badge text-bg-light border">
                  <i class="bi bi-calendar2-event me-1"></i>
                  <?= (int) $row['conference_count'] ?>
                  <?= (int) $row['conference_count'] === 1 ? 'conference' : 'conferences' ?>
                </span>
              </td>

              <td>
                <?php if ($row['status'] === 'active'): ?>
                  <span class="badge text-bg-success">
                    <i class="bi bi-check-circle-fill me-1"></i>Active
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Inactive</span>
                <?php endif; ?>
              </td>

              <td class="text-end">
                <form method="post" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                  <button type="submit"
                          class="btn btn-sm btn-outline-danger"
                          data-confirm="Delete this topic? Conferences using this topic will no longer have a topic assigned.">
                    <i class="bi bi-trash me-1"></i>Delete
                  </button>
                </form>
              </td>

            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

  <?php else: ?>

    <div class="empty-state bg-white">
      <i class="bi bi-tags"></i>
      <h2 class="h5 mt-3">No topics added yet</h2>
      <p class="text-secondary mb-0">
        Add your first topic above.
      </p>
    </div>

  <?php endif; ?>

</div>

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