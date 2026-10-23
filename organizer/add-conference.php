<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   AJAX — LIVE DUPLICATE TITLE CHECK
   ========================================================================== */
if (isset($_GET['check_title'])) {
    header('Content-Type: application/json; charset=utf-8');

    $checkTitle = trim((string) ($_GET['title'] ?? ''));
    $excludeId  = max(0, (int) ($_GET['exclude_id'] ?? 0));

    if ($checkTitle === '') {
        echo json_encode(['duplicate' => false]);
        exit;
    }

    $sql = "SELECT id, title
            FROM conferences
            WHERE LOWER(TRIM(title)) = LOWER(TRIM(?))"
            . ($excludeId > 0 ? " AND id <> ?" : "")
            . " LIMIT 1";

    $params = [$checkTitle];
    if ($excludeId > 0) $params[] = $excludeId;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $duplicate = $stmt->fetch();

    echo json_encode([
        'duplicate' => (bool) $duplicate,
        'title'     => $duplicate ? (string) $duplicate['title'] : '',
    ]);
    exit;
}

/* ==========================================================================
   LOAD TAXONOMY
   ========================================================================== */
$categories = fetch_categories();
$countries  = fetch_countries();
$cities     = fetch_cities();

/* ==========================================================================
   EDIT MODE
   ========================================================================== */
$id      = (int) getq('id');
$editing = null;

if ($id > 0) {
    $stmt = db()->prepare(
        "SELECT * FROM conferences
         WHERE id = ?
           AND organizer_id = ?
         LIMIT 1"
    );
    $stmt->execute([$id, (int) $profile['id']]);
    $editing = $stmt->fetch() ?: null;

    if (!$editing || !in_array((string) $editing['status'], ['draft', 'rejected', 'pending'], true)) {
        flash('warning', 'Only pending, rejected or old draft conferences can be edited.');
        redirect('/organizer/manage-conferences');
    }
}

$today = date('Y-m-d');

/* ==========================================================================
   FORM VALUES
   ========================================================================== */
$form = [
    'id'                 => (int) ($editing['id'] ?? 0),
    'title'              => (string) ($editing['title'] ?? ''),
    'category_id'        => (int) ($editing['category_id'] ?? 0),
    'start_date'         => (string) ($editing['start_date'] ?? ''),
    'end_date'           => (string) ($editing['end_date'] ?? ''),
    'country_id'         => (int) ($editing['country_id'] ?? 0),
    'city_id'            => (int) ($editing['city_id'] ?? 0),
    'venue'              => (string) ($editing['venue'] ?? ''),
    'attendance_type'    => (string) ($editing['attendance_type'] ?? 'Offline'),
    'contact_email'      => (string) ($editing['contact_email'] ?? ($profile['email'] ?? $user['email'] ?? '')),
    'conference_website' => (string) ($editing['conference_website'] ?? ''),
    'call_for_papers'    => (string) ($editing['description'] ?? ''),
];

/* ==========================================================================
   SUBMIT
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    $form = [
        'id'                 => (int) post('id'),
        'title'              => trim(post('title')),
        'category_id'        => (int) post('category_id'),
        'start_date'         => post('start_date'),
        'end_date'           => post('end_date'),
        'country_id'         => (int) post('country_id'),
        'city_id'            => (int) post('city_id'),
        'venue'              => trim(post('venue')),
        'attendance_type'    => post('attendance_type', 'Offline'),
        'contact_email'      => trim(post('contact_email')),
        'conference_website' => trim(post('conference_website')),
        'call_for_papers'    => trim(post('call_for_papers')),
    ];

    try {
        $conferenceId   = (int) $form['id'];
        $title          = (string) $form['title'];
        $categoryId     = (int) $form['category_id'];
        $startDate      = (string) $form['start_date'];
        $endDate        = (string) $form['end_date'];
        $countryId      = (int) $form['country_id'];
        $cityId         = (int) $form['city_id'];
        $venue          = (string) $form['venue'];
        $attendanceType = (string) $form['attendance_type'];
        $contactEmail   = (string) $form['contact_email'];
        $callForPapers  = (string) $form['call_for_papers'];

        /* Required fields */
        if (
            $title === '' || $categoryId <= 0 ||
            $startDate === '' || $endDate === '' ||
            $countryId <= 0 || $cityId <= 0 ||
            $venue === '' || $contactEmail === '' ||
            $form['conference_website'] === '' || $callForPapers === ''
        ) {
            throw new RuntimeException('Please complete every required conference field.');
        }

        /* Duplicate check */
        $duplicateSql = "SELECT id, title
                         FROM conferences
                         WHERE LOWER(TRIM(title)) = LOWER(TRIM(?))"
                         . ($conferenceId > 0 ? " AND id <> ?" : "")
                         . " LIMIT 1";

        $duplicateParams = [$title];
        if ($conferenceId > 0) $duplicateParams[] = $conferenceId;

        $duplicateStmt = db()->prepare($duplicateSql);
        $duplicateStmt->execute($duplicateParams);
        $duplicate = $duplicateStmt->fetch();
        if ($duplicate) {
            throw new RuntimeException(
                'Duplicate: "' . (string) $duplicate['title'] . '" already exists. '
                . 'Try adding city, country, or event date to make the title unique.'
            );
        }

        /* Validate category */
        $categoryStmt = db()->prepare(
            "SELECT name FROM categories WHERE id = ? AND status = 'active' LIMIT 1"
        );
        $categoryStmt->execute([$categoryId]);
        $categoryName = $categoryStmt->fetchColumn();

        if ($categoryName === false) {
            throw new RuntimeException('Please select a valid topic.');
        }

        if (!in_array($attendanceType, ['Offline', 'Online', 'Hybrid'], true)) {
            throw new RuntimeException('Please select a valid attendance type.');
        }

        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid contact email address.');
        }

        $conferenceWebsite = normalize_url((string) $form['conference_website']);
        if ($conferenceWebsite === '') {
            throw new RuntimeException('Please enter a valid official conference website URL.');
        }

        if ($startDate < $today) {
            throw new RuntimeException('Start date cannot be before today.');
        }

        if ($endDate < $startDate) {
            throw new RuntimeException('End date cannot be before the start date.');
        }

        /* Validate country */
        $countryStmt = db()->prepare(
            "SELECT name FROM countries WHERE id = ? AND status = 'active' LIMIT 1"
        );
        $countryStmt->execute([$countryId]);
        $countryName = $countryStmt->fetchColumn();

        if ($countryName === false) {
            throw new RuntimeException('Please select a valid country.');
        }

        /* Validate city belongs to country */
        $cityStmt = db()->prepare(
            "SELECT name, time_zone FROM cities
             WHERE id = ? AND country_id = ? AND status = 'active'
             LIMIT 1"
        );
        $cityStmt->execute([$cityId, $countryId]);
        $cityRow = $cityStmt->fetch();

        if (!$cityRow) {
            throw new RuntimeException('Please select a valid city for the selected country.');
        }

        /* Ownership check on update */
        if ($conferenceId > 0) {
            $ownerStmt = db()->prepare(
                "SELECT id FROM conferences
                 WHERE id = ? AND organizer_id = ?
                   AND status IN ('draft', 'rejected', 'pending')
                 LIMIT 1"
            );
            $ownerStmt->execute([$conferenceId, (int) $profile['id']]);

            if (!$ownerStmt->fetchColumn()) {
                throw new RuntimeException('This conference cannot be edited or submitted.');
            }
        }

        $slug           = unique_slug('conferences', $title, $conferenceId > 0 ? $conferenceId : null);
        $shortTitle     = mb_substr($title, 0, 120);
        $seoTitle       = mb_substr($title, 0, 250);
        $seoDescription = excerpt($callForPapers, 158);
        $seoKeywords    = implode(', ', array_filter([
            (string) $categoryName,
            (string) $cityRow['name'],
            (string) $countryName,
            'international conference',
        ]));

        /* Always submit as Pending */
        if ($conferenceId > 0) {
            $stmt = db()->prepare(
                "UPDATE conferences SET
                    category_id = ?, country_id = ?, city_id = ?,
                    title = ?, short_title = ?, slug = ?,
                    banner_image = NULL, description = ?, state = NULL,
                    venue = ?, start_date = ?, end_date = ?,
                    conference_time = NULL, time_zone = ?,
                    attendance_type = ?, organizer_website = ?, conference_website = ?,
                    registration_link = NULL, contact_email = ?,
                    seo_title = ?, seo_description = ?, seo_keywords = ?,
                    status = 'pending', rejection_reason = NULL,
                    approved_by = NULL, approved_at = NULL,
                    updated_at = NOW()
                 WHERE id = ?
                   AND organizer_id = ?
                   AND status IN ('draft', 'rejected', 'pending')"
            );

            $stmt->execute([
                $categoryId, $countryId, $cityId,
                $title, $shortTitle, $slug,
                $callForPapers, $venue, $startDate, $endDate,
                $cityRow['time_zone'] ?: null,
                $attendanceType,
                $profile['website'] ?: null,
                $conferenceWebsite,
                $contactEmail,
                $seoTitle, $seoDescription, $seoKeywords,
                $conferenceId,
                (int) $profile['id'],
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Conference could not be submitted. Please try again.');
            }
        } else {
            $stmt = db()->prepare(
                "INSERT INTO conferences (
                    organizer_id, category_id, country_id, city_id,
                    title, short_title, slug, banner_image, description, state,
                    venue, start_date, end_date, conference_time, time_zone,
                    attendance_type, organizer_website, conference_website,
                    registration_link, contact_email,
                    seo_title, seo_description, seo_keywords,
                    status, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, NULL, ?, NULL, ?, ?, ?, NULL, ?,
                    ?, ?, ?, NULL, ?, ?, ?, ?, 'pending', NOW(), NOW()
                )"
            );

            $stmt->execute([
                (int) $profile['id'],
                $categoryId, $countryId, $cityId,
                $title, $shortTitle, $slug,
                $callForPapers, $venue, $startDate, $endDate,
                $cityRow['time_zone'] ?: null,
                $attendanceType,
                $profile['website'] ?: null,
                $conferenceWebsite,
                $contactEmail,
                $seoTitle, $seoDescription, $seoKeywords,
            ]);

            $conferenceId = (int) db()->lastInsertId();
        }

        audit_log('CONFERENCE_SUBMITTED', $title, $conferenceId, (int) $profile['id']);

        flash('success', 'Conference submitted successfully and sent for admin review.');
        redirect('/organizer/pending-conferences');

    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
}

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = $editing ? 'Edit Conference' : 'Add Conference';
$dashboardRole  = 'organizer';

require dirname(__DIR__) . '/includes/dashboard-header.php';
?>

<!-- ==========================================================================
     PAGE HEADER
     ========================================================================== -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 reveal">
  <div>
    <h2 class="h4 fw-bold mb-1">
      <i class="bi bi-<?= $editing ? 'pencil-square' : 'plus-circle' ?> me-2 text-brand"></i>
      <?= $editing ? 'Edit Conference' : 'Add Conference' ?>
    </h2>
    <p class="small text-secondary mb-0">
      Submit the conference for administrator review. After submission it will appear in Pending Conferences.
    </p>
  </div>

  <a href="<?= e(url('/organizer/pending-conferences')) ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>
    Back to Pending
  </a>
</div>

<!-- ==========================================================================
     FORM
     ========================================================================== -->
<form method="post" class="row g-4 reveal" id="conferenceForm">
  <?= csrf_field() ?>
  <input type="hidden" name="id" id="conferenceId" value="<?= (int) $form['id'] ?>">

  <!-- ==================== SECTION 1: BASIC INFO ==================== -->
  <div class="col-12">
    <div class="form-section">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-card-text me-2 text-brand"></i>
        Conference Information
      </h3>

      <div class="row g-3">

        <div class="col-12">
          <label class="form-label">
            Conference Title <span class="text-danger">*</span>
          </label>
          <input name="title"
                 id="conferenceTitle"
                 class="form-control"
                 value="<?= e((string) $form['title']) ?>"
                 autocomplete="off"
                 required>
          <div id="titleDuplicateFeedback" class="form-text"></div>
        </div>

        <div class="col-12">
          <label class="form-label">
            Topic <span class="text-danger">*</span>
          </label>
          <select name="category_id" class="form-select" required>
            <option value="">Select topic</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['id'] ?>"
                      <?= (int) $form['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                <?= e($category['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
    </div>
  </div>

  <!-- ==================== SECTION 2: DATE ==================== -->
  <div class="col-12">
    <div class="form-section">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-calendar-event me-2 text-brand"></i>
        Date
      </h3>

      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">
            Start Date <span class="text-danger">*</span>
          </label>
          <input name="start_date"
                 id="conferenceStartDate"
                 type="date"
                 min="<?= e($today) ?>"
                 class="form-control"
                 value="<?= e((string) $form['start_date']) ?>"
                 required>
        </div>

        <div class="col-md-6">
          <label class="form-label">
            End Date <span class="text-danger">*</span>
          </label>
          <input name="end_date"
                 id="conferenceEndDate"
                 type="date"
                 min="<?= e(
                     ((string) $form['start_date'] !== '' && (string) $form['start_date'] >= $today)
                         ? (string) $form['start_date']
                         : $today
                 ) ?>"
                 class="form-control"
                 value="<?= e((string) $form['end_date']) ?>"
                 required>
        </div>

      </div>
    </div>
  </div>

  <!-- ==================== SECTION 3: LOCATION ==================== -->
  <div class="col-12">
    <div class="form-section">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-geo-alt me-2 text-brand"></i>
        Location
      </h3>

      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">
            Country <span class="text-danger">*</span>
          </label>
          <select name="country_id"
                  class="form-select"
                  data-conference-country
                  required>
            <option value="">Select country</option>
            <?php foreach ($countries as $country): ?>
              <option value="<?= (int) $country['id'] ?>"
                      <?= (int) $form['country_id'] === (int) $country['id'] ? 'selected' : '' ?>>
                <?= e($country['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">
            City <span class="text-danger">*</span>
          </label>
          <select name="city_id"
                  class="form-select"
                  data-conference-city
                  required
                  <?= (int) $form['country_id'] > 0 ? '' : 'disabled' ?>>
            <option value="">
              <?= (int) $form['country_id'] > 0 ? 'Select city' : 'Select country first' ?>
            </option>
            <?php foreach ($cities as $city): ?>
              <option value="<?= (int) $city['id'] ?>"
                      data-country="<?= (int) $city['country_id'] ?>"
                      <?= (int) $form['city_id'] === (int) $city['id'] ? 'selected' : '' ?>>
                <?= e($city['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">
            Venue Address <span class="text-danger">*</span>
          </label>
          <input name="venue"
                 class="form-control"
                 value="<?= e((string) $form['venue']) ?>"
                 placeholder="Enter conference venue address"
                 required>
        </div>

        <div class="col-md-6">
          <label class="form-label">
            Attendance Type <span class="text-danger">*</span>
          </label>
          <select name="attendance_type" class="form-select" required>
            <?php foreach (['Offline', 'Online', 'Hybrid'] as $attendance): ?>
              <option value="<?= e($attendance) ?>"
                      <?= (string) $form['attendance_type'] === $attendance ? 'selected' : '' ?>>
                <?= e($attendance) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
    </div>
  </div>

  <!-- ==================== SECTION 4: LINKS & CONTACT ==================== -->
  <div class="col-12">
    <div class="form-section">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-link-45deg me-2 text-brand"></i>
        Links & Contact
      </h3>

      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">
            Contact Email <span class="text-danger">*</span>
          </label>
          <input name="contact_email"
                 type="email"
                 class="form-control"
                 value="<?= e((string) $form['contact_email']) ?>"
                 required>
        </div>

        <div class="col-md-6">
          <label class="form-label">
            Official Conference Website <span class="text-danger">*</span>
          </label>
          <input name="conference_website"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['conference_website']) ?>"
                 placeholder="https://example.com"
                 required>
        </div>

      </div>
    </div>
  </div>

  <!-- ==================== SECTION 5: CONTENT ==================== -->
  <div class="col-12">
    <div class="form-section">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-file-text me-2 text-brand"></i>
        Call for Papers
      </h3>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">
            Conference Details & Call for Papers <span class="text-danger">*</span>
          </label>
          <textarea name="call_for_papers"
                    rows="8"
                    class="form-control"
                    placeholder="Enter conference details and call for papers"
                    required><?= e((string) $form['call_for_papers']) ?></textarea>
          <div class="form-text">
            Provide a detailed description. Admin will review before publishing.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== SUBMIT ==================== -->
  <div class="col-12 d-flex justify-content-between align-items-center border-top pt-4 flex-wrap gap-2">
    <a href="<?= e(url('/organizer/pending-conferences')) ?>"
       class="btn btn-outline-secondary px-4">
      Cancel
    </a>

    <button type="submit"
            class="btn btn-action px-4"
            id="submitConferenceButton">
      <i class="bi bi-send me-1"></i>
      <?= $editing ? 'Update Conference' : 'Submit Conference' ?>
    </button>
  </div>

</form>

<!-- ==========================================================================
     Duplicate-title AJAX check (page-specific)
     ========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var titleInput       = document.getElementById('conferenceTitle');
  var titleFeedback    = document.getElementById('titleDuplicateFeedback');
  var conferenceId     = document.getElementById('conferenceId');
  var startDate        = document.getElementById('conferenceStartDate');
  var endDate          = document.getElementById('conferenceEndDate');
  var today            = <?= json_encode($today) ?>;

  /* ---------- Duplicate title check ---------- */
  if (titleInput && titleFeedback) {
    var duplicateTimer   = null;
    var duplicateRequest = 0;

    function clearDuplicateState() {
      titleInput.classList.remove('is-invalid');
      titleInput.setCustomValidity('');
      titleFeedback.className = 'form-text';
      titleFeedback.textContent = '';
    }

    function renderDuplicateMessage(duplicateTitle) {
      titleFeedback.className = 'text-danger small mt-1';
      titleFeedback.textContent = '';

      var line1 = document.createElement('span');
      line1.appendChild(document.createTextNode('Duplicate: "'));

      var strong = document.createElement('strong');
      strong.textContent = String(duplicateTitle || '');

      line1.appendChild(strong);
      line1.appendChild(document.createTextNode('" already exists.'));

      var line2 = document.createElement('span');
      line2.className = 'text-secondary d-block';
      line2.textContent = 'Try adding city, country, or event date to make the title unique.';

      titleFeedback.appendChild(line1);
      titleFeedback.appendChild(document.createElement('br'));
      titleFeedback.appendChild(line2);
    }

    function checkDuplicateTitle() {
      var title = titleInput.value.trim();
      if (title === '') {
        clearDuplicateState();
        return;
      }

      var requestNumber = ++duplicateRequest;
      var params = new URLSearchParams({
        check_title: '1',
        title: title,
        exclude_id: conferenceId ? conferenceId.value : '0'
      });

      fetch(window.location.pathname + '?' + params.toString(), {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (data) {
          if (requestNumber !== duplicateRequest) return;

          if (data.duplicate) {
            titleInput.classList.add('is-invalid');
            titleInput.setCustomValidity('Duplicate conference title');
            renderDuplicateMessage(data.title);
          } else {
            clearDuplicateState();
          }
        })
        .catch(function () {
          clearDuplicateState();
        });
    }

    titleInput.addEventListener('input', function () {
      clearTimeout(duplicateTimer);
      clearDuplicateState();
      duplicateTimer = setTimeout(checkDuplicateTitle, 450);
    });

    titleInput.addEventListener('blur', function () {
      clearTimeout(duplicateTimer);
      checkDuplicateTitle();
    });

    if (titleInput.value.trim() !== '') {
      checkDuplicateTitle();
    }
  }

  /* ---------- Start date → end date ---------- */
  if (startDate && endDate) {
    function syncEndDate() {
      var minEndDate = startDate.value || today;
      endDate.min = minEndDate;

      if (endDate.value && endDate.value < minEndDate) {
        endDate.value = '';
      }
    }

    startDate.min = today;
    startDate.addEventListener('change', syncEndDate);
    startDate.addEventListener('input',  syncEndDate);
    syncEndDate();
  }
});
</script>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>