<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   HELPER
   ========================================================================== */
function normalize_location_name(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    // Preserve short all-caps abbreviations (UK, USA, UAE, etc.)
    if (mb_strlen($value) <= 5 && $value === mb_strtoupper($value, 'UTF-8')) {
        return $value;
    }

    return mb_convert_case(
        mb_strtolower($value, 'UTF-8'),
        MB_CASE_TITLE,
        'UTF-8'
    );
}

/* ==========================================================================
   DOWNLOAD SAMPLE CSV
   ========================================================================== */
if (isset($_GET['sample']) && $_GET['sample'] === '1') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="country-city-sample.csv"');

    $output = fopen('php://output', 'w');

    if ($output !== false) {
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Country', 'City']);
        fputcsv($output, ['India', 'Bhubaneswar, Delhi, Mumbai, Kolkata']);
        fputcsv($output, ['United States', 'New York, Boston, Chicago, Miami']);
        fputcsv($output, ['United Kingdom', 'London, Manchester, Birmingham, Liverpool']);
        fclose($output);
    }

    exit;
}

/* ==========================================================================
   ACTIONS
   ========================================================================== */
if (is_post()) {
    verify_csrf();
    $action = post('action');

    /* ==================== ADD COUNTRY ==================== */
    if ($action === 'add_country') {
        $countryName = normalize_location_name(post('country_name'));

        if ($countryName === '') {
            flash('danger', 'Country name is required.');
            redirect('/admin/locations');
        }

        if (mb_strlen($countryName) > 120) {
            flash('danger', 'Country name is too long.');
            redirect('/admin/locations');
        }

        $countrySlug = slugify($countryName);

        $check = db()->prepare(
            'SELECT id FROM countries WHERE LOWER(name) = LOWER(?) OR slug = ? LIMIT 1'
        );
        $check->execute([$countryName, $countrySlug]);

        if ($check->fetch()) {
            flash('warning', 'This country already exists.');
            redirect('/admin/locations');
        }

        try {
            $stmt = db()->prepare(
                "INSERT INTO countries (name, slug, code, status, created_at, updated_at)
                 VALUES (?, ?, NULL, 'active', NOW(), NOW())"
            );
            $stmt->execute([$countryName, $countrySlug]);
            flash('success', 'Country added successfully.');
        } catch (Throwable $e) {
            flash('danger', 'Unable to add this country: ' . $e->getMessage());
        }

        redirect('/admin/locations');
    }

    /* ==================== ADD CITY ==================== */
    if ($action === 'add_city') {
        $countryId = (int) post('country_id');
        $cityName  = normalize_location_name(post('city_name'));

        if ($countryId <= 0 || $cityName === '') {
            flash('danger', 'Select a country and enter a city name.');
            redirect('/admin/locations');
        }

        if (mb_strlen($cityName) > 150) {
            flash('danger', 'City name is too long.');
            redirect('/admin/locations');
        }

        $countryCheck = db()->prepare('SELECT id FROM countries WHERE id = ? LIMIT 1');
        $countryCheck->execute([$countryId]);

        if (!$countryCheck->fetch()) {
            flash('danger', 'Selected country was not found.');
            redirect('/admin/locations');
        }

        $citySlug = slugify($cityName);

        $check = db()->prepare(
            'SELECT id FROM cities
             WHERE country_id = ?
               AND (LOWER(name) = LOWER(?) OR slug = ?)
             LIMIT 1'
        );
        $check->execute([$countryId, $cityName, $citySlug]);

        if ($check->fetch()) {
            flash('warning', 'This city already exists under the selected country.');
            redirect('/admin/locations');
        }

        try {
            $stmt = db()->prepare(
                "INSERT INTO cities (country_id, name, slug, time_zone, status, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, 'active', NOW(), NOW())"
            );
            $stmt->execute([$countryId, $cityName, $citySlug]);
            flash('success', 'City added successfully.');
        } catch (Throwable $e) {
            flash('danger', 'Unable to add this city: ' . $e->getMessage());
        }

        redirect('/admin/locations');
    }

    /* ==================== BULK UPLOAD ==================== */
    if ($action === 'bulk_upload') {
        if (!isset($_FILES['location_file']) || !is_array($_FILES['location_file'])) {
            flash('danger', 'Choose a CSV file first.');
            redirect('/admin/locations');
        }

        $file = $_FILES['location_file'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('danger', 'CSV upload failed.');
            redirect('/admin/locations');
        }

        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            flash('danger', 'CSV file must be smaller than 2 MB.');
            redirect('/admin/locations');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            flash('danger', 'Only CSV files are allowed.');
            redirect('/admin/locations');
        }

        $temporaryFile = (string) ($file['tmp_name'] ?? '');

        if ($temporaryFile === '' || !is_uploaded_file($temporaryFile)) {
            flash('danger', 'Invalid uploaded CSV file.');
            redirect('/admin/locations');
        }

        $handle = fopen($temporaryFile, 'r');

        if ($handle === false) {
            flash('danger', 'Unable to read the uploaded CSV file.');
            redirect('/admin/locations');
        }

        $countriesAdded  = 0;
        $countriesReused = 0;
        $citiesAdded     = 0;
        $citiesSkipped   = 0;

        try {
            db()->beginTransaction();

            $findCountry = db()->prepare(
                'SELECT id FROM countries WHERE LOWER(name) = LOWER(?) OR slug = ? LIMIT 1'
            );
            $insertCountry = db()->prepare(
                "INSERT INTO countries (name, slug, code, status, created_at, updated_at)
                 VALUES (?, ?, NULL, 'active', NOW(), NOW())"
            );
            $findCity = db()->prepare(
                'SELECT id FROM cities
                 WHERE country_id = ?
                   AND (LOWER(name) = LOWER(?) OR slug = ?)
                 LIMIT 1'
            );
            $insertCity = db()->prepare(
                "INSERT INTO cities (country_id, name, slug, time_zone, status, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, 'active', NOW(), NOW())"
            );

            while (($row = fgetcsv($handle)) !== false) {
                $rawCountry = trim((string) ($row[0] ?? ''));
                $rawCountry = preg_replace('/^\xEF\xBB\xBF/', '', $rawCountry) ?? $rawCountry;

                if ($rawCountry === '' || mb_strtolower($rawCountry, 'UTF-8') === 'country') {
                    continue;
                }

                $rawCities = trim((string) ($row[1] ?? ''));

                $countryName = normalize_location_name($rawCountry);
                $countrySlug = slugify($countryName);

                if ($countryName === '' || $countrySlug === '') continue;

                $findCountry->execute([$countryName, $countrySlug]);
                $existingCountry = $findCountry->fetch();

                if ($existingCountry) {
                    $countryId = (int) $existingCountry['id'];
                    $countriesReused++;
                } else {
                    $insertCountry->execute([$countryName, $countrySlug]);
                    $countryId = (int) db()->lastInsertId();
                    $countriesAdded++;
                }

                if ($rawCities === '') continue;

                $citiesSeenInRow = [];
                $cityNames       = explode(',', $rawCities);

                foreach ($cityNames as $rawCityName) {
                    $cityName = normalize_location_name((string) $rawCityName);

                    if ($cityName === '') continue;

                    $cityKey = mb_strtolower($cityName, 'UTF-8');

                    if (isset($citiesSeenInRow[$cityKey])) {
                        $citiesSkipped++;
                        continue;
                    }

                    $citiesSeenInRow[$cityKey] = true;
                    $citySlug = slugify($cityName);

                    if ($citySlug === '') {
                        $citiesSkipped++;
                        continue;
                    }

                    $findCity->execute([$countryId, $cityName, $citySlug]);

                    if ($findCity->fetch()) {
                        $citiesSkipped++;
                        continue;
                    }

                    $insertCity->execute([$countryId, $cityName, $citySlug]);
                    $citiesAdded++;
                }
            }

            fclose($handle);
            $handle = null;

            db()->commit();

            flash(
                'success',
                $countriesAdded . ' new country(s) added, ' .
                $citiesAdded . ' new city/cities added. ' .
                $countriesReused . ' existing country row(s) reused and ' .
                $citiesSkipped . ' duplicate city/cities skipped.'
            );
        } catch (Throwable $e) {
            if (is_resource($handle)) fclose($handle);
            if (db()->inTransaction()) db()->rollBack();
            flash('danger', 'Bulk upload error: ' . $e->getMessage());
        }

        redirect('/admin/locations');
    }

    /* ==================== DELETE CITY ==================== */
    if ($action === 'delete_city') {
        $cityId = (int) post('city_id');

        if ($cityId <= 0) {
            flash('danger', 'Invalid city.');
            redirect('/admin/locations');
        }

        $check = db()->prepare('SELECT COUNT(*) FROM conferences WHERE city_id = ?');
        $check->execute([$cityId]);
        $conferenceCount = (int) $check->fetchColumn();

        if ($conferenceCount > 0) {
            flash('danger', 'This city is used by ' . $conferenceCount . ' conference(s) and cannot be deleted.');
            redirect('/admin/locations');
        }

        try {
            db()->prepare('DELETE FROM cities WHERE id = ?')->execute([$cityId]);
            flash('success', 'City deleted successfully.');
        } catch (Throwable $e) {
            flash('danger', 'Unable to delete this city.');
        }

        redirect('/admin/locations');
    }

    /* ==================== DELETE COUNTRY ==================== */
    if ($action === 'delete_country') {
        $countryId = (int) post('country_id');

        if ($countryId <= 0) {
            flash('danger', 'Invalid country.');
            redirect('/admin/locations');
        }

        $check = db()->prepare(
            'SELECT COUNT(DISTINCT conf.id)
             FROM conferences conf
             LEFT JOIN cities ci ON ci.id = conf.city_id
             WHERE conf.country_id = ? OR ci.country_id = ?'
        );
        $check->execute([$countryId, $countryId]);
        $conferenceCount = (int) $check->fetchColumn();

        if ($conferenceCount > 0) {
            flash('danger', 'This country is used by ' . $conferenceCount . ' conference(s) and cannot be deleted.');
            redirect('/admin/locations');
        }

        try {
            db()->prepare('DELETE FROM countries WHERE id = ?')->execute([$countryId]);
            flash('success', 'Country and its unused cities deleted successfully.');
        } catch (Throwable $e) {
            flash('danger', 'Unable to delete this country.');
        }

        redirect('/admin/locations');
    }
}

/* ==========================================================================
   STATS
   ========================================================================== */
$totalCountries = (int) db()->query('SELECT COUNT(*) FROM countries')->fetchColumn();
$totalCities    = (int) db()->query('SELECT COUNT(*) FROM cities')->fetchColumn();

/* ==========================================================================
   COUNTRIES WITH CONFERENCE COUNT
   ========================================================================== */
$countries = db()->query(
    "SELECT
        co.id,
        co.name,
        co.slug,
        co.status,
        (
            SELECT COUNT(DISTINCT conf.id)
            FROM conferences conf
            LEFT JOIN cities ci2 ON ci2.id = conf.city_id
            WHERE conf.country_id = co.id OR ci2.country_id = co.id
        ) AS conference_count
     FROM countries co
     ORDER BY co.name ASC"
)->fetchAll();

/* ==========================================================================
   CITIES GROUPED BY COUNTRY
   ========================================================================== */
$cities = db()->query(
    "SELECT id, country_id, name, slug, status
     FROM cities
     ORDER BY name ASC"
)->fetchAll();

$citiesByCountry = [];
foreach ($cities as $city) {
    $countryId = (int) $city['country_id'];
    $citiesByCountry[$countryId][] = $city;
}

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Countries & Cities';
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
        Location Directory
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Countries & Cities
        <span class="badge text-bg-light ms-2" style="font-size:.75rem;vertical-align:middle;color:var(--color-text-strong);">
          <?= number_format($totalCountries) ?> countries · <?= number_format($totalCities) ?> cities
        </span>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Add and manage countries and cities for conference listings.
      </p>
    </div>
  </div>
</div>

<!-- ==========================================================================
     STATS
     ========================================================================== -->
<div class="row row-cols-1 row-cols-md-2 g-4 mb-4 reveal-stagger">

  <div class="col">
    <div class="stat-card stat-accent-primary h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-globe2"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format($totalCountries) ?></div>
      <div class="small text-secondary mt-1">Total Countries</div>
    </div>
  </div>

  <div class="col">
    <div class="stat-card stat-accent-success h-100">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <i class="bi bi-buildings"></i>
      </div>
      <div class="display-6 fw-bold"><?= number_format($totalCities) ?></div>
      <div class="small text-secondary mt-1">Total Cities</div>
    </div>
  </div>

</div>

<!-- ==========================================================================
     ADD FORMS
     ========================================================================== -->
<div class="row g-4 mb-4">

  <!-- Add Manually -->
  <div class="col-xl-6">
    <div class="form-section h-100 reveal">

      <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
        <i class="bi bi-plus-circle me-2 text-brand"></i>
        Add Country & City Manually
      </h3>

      <!-- Add Country -->
      <form method="post" class="mb-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_country">

        <label class="form-label">Country Name <span class="text-danger">*</span></label>

        <div class="d-flex gap-2 dashboard-inline-form-row">
          <input type="text"
                 name="country_name"
                 class="form-control"
                 maxlength="120"
                 placeholder="Example: India"
                 required>

          <button type="submit" class="btn btn-action flex-shrink-0">
            <i class="bi bi-plus-lg me-1"></i>
            Add Country
          </button>
        </div>
      </form>

      <hr class="my-4">

      <!-- Add City -->
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_city">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Select Country <span class="text-danger">*</span></label>
            <select name="country_id" class="form-select" required>
              <option value="">Select Country</option>
              <?php foreach ($countries as $country): ?>
                <option value="<?= (int) $country['id'] ?>">
                  <?= e($country['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">City Name <span class="text-danger">*</span></label>
            <input type="text"
                   name="city_name"
                   class="form-control"
                   maxlength="150"
                   placeholder="Example: Bhubaneswar"
                   required>
          </div>

          <div class="col-12">
            <button type="submit" class="btn btn-dark">
              <i class="bi bi-geo-alt me-1"></i>
              Add City
            </button>
          </div>
        </div>
      </form>

    </div>
  </div>

  <!-- Bulk Upload -->
  <div class="col-xl-6">
    <div class="form-section h-100 reveal">

      <h3 class="h6 fw-bold mb-4 border-bottom pb-2">
        <i class="bi bi-cloud-arrow-up me-2 text-brand"></i>
        Bulk Country & City Upload
      </h3>

      <p class="small text-secondary mb-3">
        Upload countries and their cities using one CSV file.
      </p>

      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_upload">

        <div class="mb-3">
          <label class="form-label">CSV File <span class="text-danger">*</span></label>
          <input type="file"
                 name="location_file"
                 class="form-control"
                 accept=".csv,text/csv"
                 required>
        </div>

        <div class="border rounded-3 p-3 bg-light mb-3">
          <div class="fw-bold small mb-2">CSV Format</div>
          <div class="small text-secondary mb-2">
            Column A = Country. Column B = comma-separated cities.
            Extra columns are ignored.
          </div>
          <code class="d-block small">Country,City</code>
          <code class="d-block small">India,"Bhubaneswar, Delhi, Mumbai, Kolkata"</code>
          <code class="d-block small">United States,"New York, Boston, Chicago, Miami"</code>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-dark">
            <i class="bi bi-upload me-1"></i>
            Upload Locations
          </button>

          <a href="<?= e(url('/admin/locations?sample=1')) ?>"
             class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i>
            Download Sample CSV
          </a>
        </div>
      </form>

    </div>
  </div>

</div>

<!-- ==========================================================================
     COUNTRIES ACCORDION
     ========================================================================== -->
<div class="mb-3 reveal">
  <h2 class="h5 fw-bold mb-1">
    <i class="bi bi-globe2 me-2 text-brand"></i>
    All Countries & Cities
  </h2>
  <p class="small text-secondary mb-0">
    Click a country to expand and manage its cities.
  </p>
</div>

<!-- DEBUG: <?= count($countries) ?> countries loaded -->
<?php if (!$countries): ?>

  <div class="table-card">
    <div class="empty-state bg-white">
      <i class="bi bi-globe2"></i>
      <h2 class="h5 mt-3">No countries added yet</h2>
      <p class="text-secondary mb-0">
        Add your first country above.
      </p>
    </div>
  </div>

<?php else: ?>

  <div class="row g-3">
    <?php foreach ($countries as $country): ?>
      <?php
      $countryId     = (int) $country['id'];
      $countryCities = $citiesByCountry[$countryId] ?? [];
      ?>

      <div class="col-12">
        <div class="table-card country-accordion-item">

          <!-- Header -->
          <div class="country-accordion-header" data-country-toggle>

            <div class="country-accordion-main">
              <div class="country-toggle-icon">
                <i class="bi bi-chevron-right"></i>
              </div>

              <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <h3 class="h6 fw-bold mb-0">
                    <i class="bi bi-globe2 text-brand me-1"></i>
                    <?= e($country['name']) ?>
                  </h3>

                  <span class="badge text-bg-light border">
                    <?= (int) $country['conference_count'] ?>
                    <?= (int) $country['conference_count'] === 1 ? 'conference' : 'conferences' ?>
                  </span>

                  <span class="badge text-bg-light border">
                    <?= count($countryCities) ?>
                    <?= count($countryCities) === 1 ? 'city' : 'cities' ?>
                  </span>
                </div>
              </div>
            </div>

            <!-- Delete Country -->
            <form method="post" class="m-0 country-delete-form" onclick="event.stopPropagation();">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_country">
              <input type="hidden" name="country_id" value="<?= $countryId ?>">

              <button type="submit"
                      class="btn btn-sm btn-outline-danger"
                      data-confirm="Delete this country? All unused cities under it will also be deleted.">
                <i class="bi bi-trash me-1"></i>
                Delete Country
              </button>
            </form>

          </div>

          <!-- Cities panel -->
          <div class="country-cities-panel">
            <div class="p-4 border-top">

              <div class="small fw-bold text-secondary text-uppercase mb-3" style="letter-spacing:.08em;">
                Cities
              </div>

              <?php if (!$countryCities): ?>

                <div class="small text-secondary">
                  No cities added under this country yet.
                </div>

              <?php else: ?>

                <div class="country-city-chips">
                  <?php foreach ($countryCities as $city): ?>
                    <div class="city-chip">
                      <span>
                        <i class="bi bi-geo-alt text-brand me-1"></i>
                        <?= e($city['name']) ?>
                      </span>

                      <form method="post" class="m-0" onclick="event.stopPropagation();">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_city">
                        <input type="hidden" name="city_id" value="<?= (int) $city['id'] ?>">

                        <button type="submit"
                                class="city-delete-button"
                                title="Delete city"
                                aria-label="Delete <?= e($city['name']) ?>"
                                data-confirm="Delete this city?">
                          <i class="bi bi-x-circle"></i>
                        </button>
                      </form>
                    </div>
                  <?php endforeach; ?>
                </div>

              <?php endif; ?>

            </div>
          </div>

        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<!-- Admin banner + stat card accents -->
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

.stat-card.stat-accent-primary { border-left: 4px solid var(--color-accent); }
.stat-card.stat-accent-success { border-left: 4px solid var(--color-success); }
.stat-card.stat-accent-primary i { color: var(--color-accent); }
.stat-card.stat-accent-success i { color: var(--color-success); }
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>