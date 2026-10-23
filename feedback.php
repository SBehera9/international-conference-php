<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   LOAD COUNTRIES + CITIES
   ========================================================================== */
$feedbackCountries = fetch_countries();
$feedbackCities    = fetch_cities();

/* ==========================================================================
   FORM STATE
   ========================================================================== */
$form = [
    'name'       => '',
    'country_id' => 0,
    'city_id'    => 0,
    'rating'     => 5,
    'message'    => '',
];

/* ==========================================================================
   FORM SUBMISSION
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    /* --------------------------------------------------------------
       Anti-spam honeypot
       -------------------------------------------------------------- */
    if (public_form_honeypot_triggered()) {
        flash('success', 'Thank you. Your feedback was submitted for review.');
        redirect('/feedback');
    }

    /* --------------------------------------------------------------
       Rate limit: maximum 5 feedback submissions per 30 minutes
       from the same IP address.
       -------------------------------------------------------------- */
    if (public_form_rate_limited('feedback', 5, 30)) {
        flash(
            'danger',
            'Too many feedback submissions. Please wait about 30 minutes and try again.'
        );

        redirect('/feedback');
    }

    /* Count this submission attempt */
    record_public_form_attempt('feedback');

    $form = [
        'name'       => trim(post('name')),
        'country_id' => (int) post('country_id'),
        'city_id'    => (int) post('city_id'),
        'rating'     => max(1, min(5, (int) post('rating', '5'))),
        'message'    => trim(post('message')),
    ];

    try {
        if ($form['name'] === '') {
            throw new RuntimeException('Name is required.');
        }
        if (mb_strlen($form['name']) > 150) {
            throw new RuntimeException('Name is too long.');
        }
        if ($form['country_id'] <= 0 || $form['city_id'] <= 0) {
            throw new RuntimeException('Please select your country and city.');
        }
        if ($form['message'] === '') {
            throw new RuntimeException('Please write your feedback.');
        }
        if (mb_strlen($form['message']) > 200) {
            throw new RuntimeException('Feedback must be 200 characters or less.');
        }

        /* Validate country */
        $countryStmt = db()->prepare(
            "SELECT name FROM countries WHERE id = ? AND status = 'active' LIMIT 1"
        );
        $countryStmt->execute([$form['country_id']]);
        $countryName = $countryStmt->fetchColumn();

        /* Validate city belongs to country */
        $cityStmt = db()->prepare(
            "SELECT name FROM cities WHERE id = ? AND country_id = ? AND status = 'active' LIMIT 1"
        );
        $cityStmt->execute([$form['city_id'], $form['country_id']]);
        $cityName = $cityStmt->fetchColumn();

        if (!$countryName || !$cityName) {
            throw new RuntimeException('Please select a valid country and city.');
        }

        /* Handle optional photo upload */
        $photoPath = null;

        if (
            !empty($_FILES['photo_file']) &&
            is_array($_FILES['photo_file']) &&
            ($_FILES['photo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ) {
            $photoPath = upload_image('photo_file', 'feedback');
        }

        $stmt = db()->prepare(
            "INSERT INTO user_feedbacks (
                name, email, organization, country,
                image, message, rating, status, is_verified, created_at, updated_at
            ) VALUES (?, NULL, ?, ?, ?, ?, ?, 'pending', 0, NOW(), NOW())"
        );
        $stmt->execute([
            $form['name'],
            $cityName,
            $countryName,
            $photoPath,
            $form['message'],
            $form['rating'],
        ]);

        flash('success', 'Thank you. Your feedback was submitted for review.');
        redirect('/feedback');

    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
}

/* ==========================================================================
   SEO — per-page keywords + CollectionPage schema
   ========================================================================== */
$pageKeywords = page_keywords('feedback');

$feedbackCollectionName = 'Share Feedback';
$feedbackCollectionDesc = 'Share your experience with International Conference. Approved feedback may appear on the website.';

$seo = page_seo([
    'title'       => $feedbackCollectionName,
    'description' => $feedbackCollectionDesc,
    'keywords'    => $pageKeywords,
    'canonical'   => url('/feedback'),
    'json_ld'     => [
        collection_schema(
            $feedbackCollectionName,
            url('/feedback'),
            $feedbackCollectionDesc,
            $pageKeywords
        ),
        breadcrumb_schema([
            ['name' => 'Home',     'url' => url('/')],
            ['name' => 'Feedback', 'url' => url('/feedback')],
        ]),
    ],
]);

require __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     PAGE HERO
     ========================================================================== -->
<section class="page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Feedback</li>
      </ol>
    </nav>

    <div class="section-kicker">Community voice</div>
    <h1 class="display-5 mt-2">Share Feedback</h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      Tell us about your experience using the conference directory.
    </p>
  </div>
</section>

<!-- ==========================================================================
     FORM + INFO
     ========================================================================== -->
<section class="py-5">
  <div class="container">
    <div class="row g-5 justify-content-center">

      <!-- Form -->
      <div class="col-lg-7">
        <div class="form-section reveal">

          <div class="mb-4">
            <h2 class="h4 fw-bold mb-2">
              <i class="bi bi-chat-square-heart me-2 text-brand"></i>
              Your Feedback
            </h2>
            <p class="small text-secondary mb-0">
              Fields marked with <span class="text-danger">*</span> are required.
              Your feedback will be reviewed before publishing.
            </p>
          </div>

<form method="post" enctype="multipart/form-data" class="row g-4">

    <?= csrf_field() ?>

    <?= public_form_honeypot_field() ?>

            <!-- Name -->
            <div class="col-12">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input name="name" class="form-control"
                     maxlength="150"
                     placeholder="Your full name"
                     value="<?= e($form['name']) ?>"
                     required>
            </div>

            <!-- Photo Upload (Optional) -->
            <div class="col-12">
              <label class="form-label">Your Photo (Optional)</label>
              <input name="photo_file"
                     type="file"
                     accept="image/jpeg,image/png,image/webp,image/gif"
                     class="form-control">
<div class="form-text">
    JPG, PNG, WEBP or GIF. Max 500 KB. Your photo appears with your feedback once approved.
</div>
            </div>

            <!-- Country -->
            <div class="col-md-6">
              <label class="form-label">Country <span class="text-danger">*</span></label>
              <select name="country_id"
                      class="form-select"
                      data-feedback-country
                      required>
                <option value="">Select country</option>
                <?php foreach ($feedbackCountries as $country): ?>
                  <option value="<?= (int) $country['id'] ?>"
                          <?= (int) $form['country_id'] === (int) $country['id'] ? 'selected' : '' ?>>
                    <?= e($country['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- City -->
            <div class="col-md-6"
                 data-feedback-city-wrap
                 <?= (int) $form['country_id'] > 0 ? '' : 'hidden' ?>>
              <label class="form-label">City <span class="text-danger">*</span></label>
              <select name="city_id"
                      class="form-select"
                      data-feedback-city
                      <?= (int) $form['country_id'] > 0 ? '' : 'disabled' ?>
                      required>
                <option value="">Select city</option>
                <?php foreach ($feedbackCities as $city): ?>
                  <option value="<?= (int) $city['id'] ?>"
                          data-country="<?= (int) $city['country_id'] ?>"
                          <?= (int) $form['city_id'] === (int) $city['id'] ? 'selected' : '' ?>>
                    <?= e($city['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Rating -->
            <div class="col-12">
              <label class="form-label d-block mb-2">
                Rating <span class="text-danger">*</span>
              </label>

              <div class="feedback-star-picker"
                   data-star-picker
                   aria-label="Select star rating">
                <?php for ($star = 1; $star <= 5; $star++): ?>
                  <input id="feedbackStar<?= $star ?>"
                         type="radio"
                         name="rating"
                         value="<?= $star ?>"
                         <?= (int) $form['rating'] === $star ? 'checked' : '' ?>
                         <?= $star === 5 ? 'required' : '' ?>>
                  <label for="feedbackStar<?= $star ?>"
                         data-star-value="<?= $star ?>"
                         aria-label="<?= $star ?> star<?= $star === 1 ? '' : 's' ?>">
                    <i class="bi bi-star-fill"></i>
                  </label>
                <?php endfor; ?>
              </div>
            </div>

            <!-- Message -->
            <div class="col-12">
              <div class="d-flex justify-content-between gap-3">
                <label class="form-label">
                  Your Feedback <span class="text-danger">*</span>
                </label>
                <small class="text-secondary" id="feedbackMessageCount">0 / 200</small>
              </div>
<textarea name="message"
          rows="6"
          class="form-control"
          maxlength="200"
          data-feedback-message
          placeholder="Write something about your experience..."
          required><?= e($form['message']) ?></textarea>
            </div>

            <!-- Submit -->
            <div class="col-12 d-flex justify-content-between align-items-center border-top pt-4 flex-wrap gap-2">
              <a href="<?= e(url('/#feedback')) ?>"
                 class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>
                View Public Feedback
              </a>

              <button type="submit" class="btn btn-action px-4">
                <i class="bi bi-send me-1"></i>
                Submit Feedback
              </button>
            </div>

          </form>
        </div>
      </div>

      <!-- Info -->
      <div class="col-lg-4">
        <div class="form-section reveal" style="position:sticky;top:110px;">

          <h3 class="h5 fw-bold mb-4">
            <i class="bi bi-info-circle me-2 text-brand"></i>
            How It Works
          </h3>

          <div class="d-flex gap-3 mb-4">
            <span class="avatar-circle flex-shrink-0" style="width:36px;height:36px;font-size:.85rem;">1</span>
            <div>
              <div class="fw-semibold text-dark small">Submit</div>
              <div class="small text-secondary">
                Fill the form and submit your feedback.
              </div>
            </div>
          </div>

          <div class="d-flex gap-3 mb-4">
            <span class="avatar-circle flex-shrink-0" style="width:36px;height:36px;font-size:.85rem;">2</span>
            <div>
              <div class="fw-semibold text-dark small">Review</div>
              <div class="small text-secondary">
                An administrator reviews your feedback.
              </div>
            </div>
          </div>

          <div class="d-flex gap-3 mb-4">
            <span class="avatar-circle flex-shrink-0" style="width:36px;height:36px;font-size:.85rem;">3</span>
            <div>
              <div class="fw-semibold text-dark small">Publish</div>
              <div class="small text-secondary">
                Approved feedback may appear on the home page.
              </div>
            </div>
          </div>

          <hr>

          <div class="small text-secondary">
            <i class="bi bi-shield-lock me-1 text-brand"></i>
            Your details are never shown publicly without your consent.
          </div>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- Live character counter -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var msg = document.querySelector('[data-feedback-message]');
  var count = document.getElementById('feedbackMessageCount');
  if (msg && count) {
    function update() { count.textContent = msg.value.length + ' / 200'; }
    msg.addEventListener('input', update);
    update();
  }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>