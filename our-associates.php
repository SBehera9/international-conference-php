<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   LOGO URL HELPER
   ========================================================================== */
function public_associate_logo_url(?string $logo): string
{
    $logo = trim((string) $logo);
    if ($logo === '') return '';
    if (preg_match('#^https?://#i', $logo)) return $logo;
    return url('/' . ltrim($logo, '/'));
}

/* ==========================================================================
   FORM STATE
   ========================================================================== */
$formError = '';
$openAssociateModal = false;

$formValues = [
    'name'        => '',
    'website'     => '',
    'description' => '',
];

/* ==========================================================================
   SUBMISSION
   ========================================================================== */
if (is_post()) {
    verify_csrf();
    $openAssociateModal = true;

    /* --------------------------------------------------------------
       Anti-spam honeypot
       -------------------------------------------------------------- */
    if (public_form_honeypot_triggered()) {
        flash(
            'success',
            'Associate application submitted successfully. It will appear after Admin approval.'
        );

        redirect('/our-associates');
    }

    /* --------------------------------------------------------------
       Rate limit:
       Maximum 3 associate applications per 60 minutes
       from the same IP address.
       -------------------------------------------------------------- */
    if (public_form_rate_limited('associate', 3, 60)) {
        flash(
            'danger',
            'Too many associate applications. Please wait about 1 hour and try again.'
        );

        redirect('/our-associates');
    }

    /* Record this submission attempt */
    record_public_form_attempt('associate');

    $formValues = [
        'name'        => post('name'),
        'website'     => post('website'),
        'description' => post('description'),
    ];

    try {
        $name        = trim($formValues['name']);
        $websiteRaw  = trim($formValues['website']);
        $website     = normalize_url($websiteRaw);
        $description = trim($formValues['description']);

        if ($name === '') {
            throw new RuntimeException('Company name is required.');
        }

        if ($websiteRaw === '' || $website === '') {
            throw new RuntimeException('Please enter a valid website link.');
        }

        if ($description === '') {
            throw new RuntimeException('Description is required.');
        }

if (mb_strlen($description) > 200) {
    throw new RuntimeException('Description must be 200 characters or less.');
}

        if (
            empty($_FILES['logo_file']) ||
            !is_array($_FILES['logo_file']) ||
            ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            throw new RuntimeException('Please upload a company logo.');
        }

        $logo = upload_image('logo_file', 'partners');
        if (!$logo) {
            throw new RuntimeException('Please upload a valid company logo.');
        }

        $stmt = db()->prepare(
            "INSERT INTO associates (
                name, description, logo, website,
                status, is_verified, is_deactivated, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'pending', 0, 0, NOW(), NOW())"
        );
        $stmt->execute([$name, $description, $logo, $website]);

        flash('success', 'Associate application submitted successfully. It will appear after Admin approval.');
        redirect('/our-associates');

    } catch (Throwable $e) {
        $formError = $e->getMessage();
    }
}

/* ==========================================================================
   APPROVED ASSOCIATES + PAGINATION
   ========================================================================== */
$page    = max(1, (int) getq('page', '1'));
$perPage = 40;

$totalApproved = (int) db()->query(
    "SELECT COUNT(*) FROM associates WHERE status = 'approved' AND is_deactivated = 0"
)->fetchColumn();

$totalPages = max(1, (int) ceil($totalApproved / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;

$sql = "
    SELECT id, name, description, logo, website, updated_at
    FROM associates
    WHERE status = 'approved' AND is_deactivated = 0
    ORDER BY updated_at DESC, id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$rows = db()->query($sql)->fetchAll();

/* ==========================================================================
   SEO — per-page keywords + CollectionPage schema
   ========================================================================== */
$pageKeywords = page_keywords('our_associates');

$associateCollectionName = 'Our Associates';
$associateCollectionDesc = 'Explore approved academic and professional associates and apply to become an associate of International Conference.';

$seo = page_seo([
    'title'       => $associateCollectionName,
    'description' => $associateCollectionDesc,
    'keywords'    => $pageKeywords,
    'canonical'   => url('/our-associates'),
    'json_ld'     => [
        collection_schema(
            $associateCollectionName,
            url('/our-associates'),
            $associateCollectionDesc,
            $pageKeywords
        ),
        breadcrumb_schema([
            ['name' => 'Home',           'url' => url('/')],
            ['name' => 'Our Associates', 'url' => url('/our-associates')],
        ]),
    ],
]);

require __DIR__ . '/includes/header.php';
?>

<style>
.partner-public-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1.25rem;
}

@media (min-width: 576px) {
    .partner-public-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 992px) {
    .partner-public-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 1400px) {
    .partner-public-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

.partner-public-card {
    min-width: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    border: 1px solid #e3e8ef;
    border-radius: 18px;
    padding: 1.25rem;
    background: #fff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}

.partner-public-card:hover {
    transform: translateY(-3px);
    border-color: #d4dbe5;
    box-shadow: 0 14px 34px rgba(15, 23, 42, .08);
}

.partner-logo-wrap {
    width: 82px;
    height: 82px;
    margin: 0 0 1rem 0;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 9px;
    flex-shrink: 0;
}

.partner-logo-wrap img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: contain;
}

.partner-card-title {
    width: 100%;
    margin: 0 0 .55rem 0;
    color: #111827;
    font-size: 1.05rem;
    line-height: 1.35;
    font-weight: 700;
    text-align: left;
    overflow-wrap: anywhere;
}

.partner-card-description {
    width: 100%;
    margin: 0 0 1rem 0;
    color: #64748b;
    font-size: .92rem;
    line-height: 1.6;
    font-weight: 400;
    text-align: left;
    overflow-wrap: anywhere;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 5;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.partner-public-card .btn {
    width: auto !important;
    align-self: flex-start;
    margin-top: auto !important;
    padding: .5rem .85rem;
    font-weight: 600;
    border-radius: 9px;
}

.partner-apply-box {
    border: 1px solid #dbe3ea;
    border-radius: 20px;
    background: #fff;
    padding: 2rem;
}

@media (max-width: 575.98px) {
    .partner-public-card {
        padding: 1.1rem;
    }

    .partner-logo-wrap {
        width: 74px;
        height: 74px;
    }

    .partner-card-title {
        font-size: 1rem;
    }

    .partner-card-description {
        font-size: .9rem;
    }

    .partner-public-card .btn {
        width: 100% !important;
    }
}
</style>

<!-- ==========================================================================
     PAGE HERO — the single H1
     ========================================================================== -->
<section class="page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Our Associates</li>
      </ol>
    </nav>

    <div class="section-kicker">Academic & professional network</div>
    <h1 class="display-5 mt-2">Our Associates</h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      Explore approved organizations collaborating with International Conference
      and become part of our professional network.
    </p>
  </div>
</section>

<!-- ==========================================================================
     BECOME AN ASSOCIATE — CTA BOX (demoted to H3)
     ========================================================================== -->
<section class="py-5">
  <div class="container">
    <div class="partner-apply-box d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 reveal">

      <div>
        <div class="section-kicker mb-2">Join our network</div>
        <h3 class="h3 fw-bold mb-2">Become an Associate</h3>
        <p class="text-secondary mb-0">
          Submit your organization for review. Approved associates are published on this page.
        </p>
      </div>

      <button type="button"
              class="btn btn-action btn-lg px-4 flex-shrink-0"
              data-bs-toggle="modal"
              data-bs-target="#associateModal">
        <i class="bi bi-plus-circle me-1"></i>
        Become an Associate
      </button>

    </div>
  </div>
</section>

<!-- ==========================================================================
     APPROVED ASSOCIATES — the single H2
     ========================================================================== -->
<section class="pb-5">
  <div class="container">

    <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4 reveal">
      <div>
        <div class="section-kicker">Approved Network</div>
        <h2 class="h3 fw-bold mb-1">Our Approved Associates</h2>
        <p class="text-secondary mb-0">
          Most recently approved associates appear first.
        </p>
      </div>

      <span class="badge text-bg-light border px-3 py-2">
        <i class="bi bi-diagram-3 me-1"></i>
        <?= number_format($totalApproved) ?> Associate<?= $totalApproved === 1 ? '' : 's' ?>
      </span>
    </div>

    <?php if ($rows): ?>
      <div class="partner-public-grid reveal-stagger">
        <?php foreach ($rows as $partner): ?>
          <?php $logoUrl = public_associate_logo_url($partner['logo'] ?? null); ?>

          <article class="partner-public-card">

            <div class="partner-logo-wrap">
              <?php if ($logoUrl !== ''): ?>
                <img src="<?= e($logoUrl) ?>"
                     alt="<?= e($partner['name']) ?> logo"
                     loading="lazy">
              <?php else: ?>
                <i class="bi bi-image fs-1 text-secondary"></i>
              <?php endif; ?>
            </div>

            <h3 class="partner-card-title"><?= e($partner['name']) ?></h3>

            <p class="partner-card-description">
              <?= e($partner['description']) ?>
            </p>

            <a href="<?= e($partner['website']) ?>"
               target="_blank"
               rel="noopener noreferrer nofollow"
               class="btn btn-outline-dark btn-sm w-100 mt-2">
              Visit Website
              <i class="bi bi-box-arrow-up-right ms-1"></i>
            </a>

          </article>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <?php
        $startPage = max(1, $page - 2);
        $endPage   = min($totalPages, $page + 2);
        ?>

        <nav class="mt-5" aria-label="Associate pagination">
          <ul class="pagination justify-content-center flex-wrap">

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link"
                 href="<?= $page > 1 ? e(url('/our-associates?page=' . ($page - 1))) : '#' ?>">
                <i class="bi bi-chevron-left"></i>
              </a>
            </li>

            <?php if ($startPage > 1): ?>
              <li class="page-item">
                <a class="page-link" href="<?= e(url('/our-associates?page=1')) ?>">1</a>
              </li>
              <?php if ($startPage > 2): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
              <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('/our-associates?page=' . $i)) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
              <?php if ($endPage < $totalPages - 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
              <?php endif; ?>
              <li class="page-item">
                <a class="page-link" href="<?= e(url('/our-associates?page=' . $totalPages)) ?>"><?= $totalPages ?></a>
              </li>
            <?php endif; ?>

            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link"
                 href="<?= $page < $totalPages ? e(url('/our-associates?page=' . ($page + 1))) : '#' ?>">
                <i class="bi bi-chevron-right"></i>
              </a>
            </li>

          </ul>
        </nav>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty-state bg-soft rounded-4">
        <i class="bi bi-diagram-3"></i>
        <h3 class="h5 mt-3">No associates yet</h3>
        <p class="text-secondary mb-0">
          Approved associates will appear here.
        </p>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- ==========================================================================
     SUBMISSION MODAL
     ========================================================================== -->
<div class="modal fade" id="associateModal" tabindex="-1"
     aria-labelledby="associateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h3 class="modal-title h4 fw-bold mb-1" id="associateModalLabel">
            Become an Associate
          </h3>
          <p class="small text-secondary mb-0">
            Complete the form below. Admin approval is required before your company is published.
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

<form method="post" enctype="multipart/form-data">

    <?= csrf_field() ?>

    <?= public_form_honeypot_field() ?>

        <div class="modal-body">

          <?php if ($formError !== ''): ?>
            <div class="alert alert-danger">
              <i class="bi bi-exclamation-circle me-2"></i>
              <?= e($formError) ?>
            </div>
          <?php endif; ?>

          <div class="row g-4">

            <div class="col-md-6">
              <label class="form-label">Company Name *</label>
              <input name="name" class="form-control"
                     value="<?= e($formValues['name']) ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Website Link *</label>
              <input name="website" type="text" class="form-control"
                     value="<?= e($formValues['website']) ?>"
                     placeholder="https://example.org" required>
            </div>

            <div class="col-12">
              <label class="form-label">Logo Upload *</label>
              <input name="logo_file" type="file"
                     accept="image/jpeg,image/png,image/webp,image/gif"
                     class="form-control" required>
              <div class="form-text">
                JPG, PNG, WEBP or GIF. Maximum 500 KB.
              </div>
            </div>

            <div class="col-12">
              <div class="d-flex justify-content-between gap-3">
                <label class="form-label">Description *</label>
                <small class="text-secondary" data-char-count="#associateDescription"></small>
              </div>
              <textarea id="associateDescription"
                        name="description"
                        class="form-control"
                        rows="5"
                        maxlength="200"
                        required><?= e($formValues['description']) ?></textarea>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit" class="btn btn-action px-4">
            <i class="bi bi-send me-1"></i> Submit
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<?php if ($openAssociateModal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var m = document.getElementById('associateModal');
  if (m && window.bootstrap) bootstrap.Modal.getOrCreateInstance(m).show();
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>