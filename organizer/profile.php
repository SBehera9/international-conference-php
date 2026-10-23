<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user    = require_role('organizer');
$profile = require_organizer_profile(true);

$countries = fetch_countries();
$cities    = fetch_cities();

/* ==========================================================================
   FIND SAVED COUNTRY ID
   ========================================================================== */
$selectedCountryId = 0;

foreach ($countries as $countryRow) {
    if (
        strcasecmp(
            (string) $countryRow['name'],
            (string) ($profile['country'] ?? '')
        ) === 0
    ) {
        $selectedCountryId = (int) $countryRow['id'];
        break;
    }
}

/* ==========================================================================
   FIND SAVED CITY ID
   ========================================================================== */
$selectedCityId = 0;

if ($selectedCountryId > 0) {
    foreach ($cities as $cityRow) {
        if (
            (int) $cityRow['country_id'] === $selectedCountryId &&
            strcasecmp(
                (string) $cityRow['name'],
                (string) ($profile['city'] ?? '')
            ) === 0
        ) {
            $selectedCityId = (int) $cityRow['id'];
            break;
        }
    }
}

/* ==========================================================================
   WHATSAPP PARSE
   ========================================================================== */
$whatsappCountryCode = '';
$whatsappPhone       = '';

$savedWhatsapp = trim((string) ($profile['whatsapp'] ?? ''));

if ($savedWhatsapp !== '') {
    if (preg_match('/^\+?(\d{1,4})[\s-]+(.+)$/', $savedWhatsapp, $matches)) {
        $whatsappCountryCode = $matches[1];
        $whatsappPhone       = trim($matches[2]);
    } else {
        $whatsappPhone = $savedWhatsapp;
    }
}

/* ==========================================================================
   FORM VALUES
   ========================================================================== */
$form = [
    'organization_name' => (string) ($profile['organization_name'] ?? ''),
    'website'           => (string) ($profile['website'] ?? ''),
    'country_id'        => $selectedCountryId,
    'city_id'           => $selectedCityId,
    'about_organization'=> (string) ($profile['about_organization'] ?? ''),

    'logo_url' => str_starts_with((string) ($profile['logo'] ?? ''), 'http')
        ? (string) $profile['logo']
        : '',

    'facebook'  => (string) ($profile['facebook']  ?? ''),
    'instagram' => (string) ($profile['instagram'] ?? ''),
    'linkedin'  => (string) ($profile['linkedin']  ?? ''),
    'twitter'   => (string) ($profile['twitter']   ?? ''),
    'youtube'   => (string) ($profile['youtube']   ?? ''),
    'telegram'  => (string) ($profile['telegram']  ?? ''),

    'whatsapp_country_code' => $whatsappCountryCode,
    'whatsapp_phone'        => $whatsappPhone,
];

/* ==========================================================================
   SAVE PROFILE
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    $form = [
        'organization_name' => post('organization_name'),
        'website'           => post('website'),
        'country_id'        => (int) post('country_id'),
        'city_id'           => (int) post('city_id'),
        'about_organization'=> post('about_organization'),
        'logo_url'          => post('logo_url'),

        'facebook'  => post('facebook'),
        'instagram' => post('instagram'),
        'linkedin'  => post('linkedin'),
        'twitter'   => post('twitter'),
        'youtube'   => post('youtube'),
        'telegram'  => post('telegram'),

        'whatsapp_country_code' => post('whatsapp_country_code'),
        'whatsapp_phone'        => post('whatsapp_phone'),
    ];

    try {
        $orgName   = trim((string) $form['organization_name']);
        $countryId = (int) $form['country_id'];
        $cityId    = (int) $form['city_id'];
        $about     = trim((string) $form['about_organization']);

        /* Required fields */
        if ($orgName === '' || $countryId <= 0 || $cityId <= 0 || $about === '') {
            throw new RuntimeException(
                'Organization name, country, city and about organization are required.'
            );
        }

        /* Validate country */
        $countryStmt = db()->prepare(
            "SELECT name FROM countries WHERE id = ? AND status = 'active'"
        );
        $countryStmt->execute([$countryId]);
        $country = $countryStmt->fetchColumn();

        /* Validate city belongs to country */
        $cityStmt = db()->prepare(
            "SELECT name FROM cities
             WHERE id = ? AND country_id = ? AND status = 'active'"
        );
        $cityStmt->execute([$cityId, $countryId]);
        $city = $cityStmt->fetchColumn();

        if (!$country || !$city) {
            throw new RuntimeException('Please select a valid country and city.');
        }

        /* URL fields */
        $urlFields = [
            'website'   => 'Website',
            'logo_url'  => 'Logo URL',
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin'  => 'LinkedIn',
            'twitter'   => 'Twitter / X',
            'youtube'   => 'YouTube',
            'telegram'  => 'Telegram',
        ];

        $urls = [];

        foreach ($urlFields as $field => $label) {
            $rawValue   = trim((string) $form[$field]);
            $normalized = normalize_url($rawValue);

            if ($rawValue !== '' && $normalized === '') {
                throw new RuntimeException($label . ' must be a valid URL.');
            }

            $urls[$field] = $normalized;
        }

        /* WhatsApp validation */
        $codeRaw  = trim((string) $form['whatsapp_country_code']);
        $phoneRaw = trim((string) $form['whatsapp_phone']);
        $whatsapp = null;

        if ($codeRaw !== '' || $phoneRaw !== '') {
            if ($codeRaw === '' || $phoneRaw === '') {
                throw new RuntimeException(
                    'For WhatsApp, enter both the country code and phone number.'
                );
            }

            $codeDigits = ltrim($codeRaw, '+');
            $codeDigits = preg_replace('/\s+/', '', $codeDigits) ?? '';

            if (!preg_match('/^[1-9]\d{0,3}$/', $codeDigits)) {
                throw new RuntimeException(
                    'WhatsApp country code must be 1–4 digits and start with 1–9 (no leading zero).'
                );
            }

            if (!preg_match('/^[0-9\s()\-]+$/', $phoneRaw)) {
                throw new RuntimeException('WhatsApp phone number contains invalid characters.');
            }

            $phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?? '';

            if (strlen($phoneDigits) < 6 || strlen($phoneDigits) > 15) {
                throw new RuntimeException('WhatsApp phone number must contain 6 to 15 digits.');
            }

            /* Strip leading zeros from phone (they're not valid after country code) */
            $phoneDigits = ltrim($phoneDigits, '0');

            if ($phoneDigits === '') {
                throw new RuntimeException('WhatsApp phone number is invalid.');
            }

            if (strlen($codeDigits . $phoneDigits) > 15) {
                throw new RuntimeException(
                    'WhatsApp country code and phone number together cannot exceed 15 digits.'
                );
            }

            $whatsapp = '+' . $codeDigits . ' ' . $phoneDigits;
        }

        /* Unique slug */
        $slug = unique_slug('organizers', $orgName, (int) $profile['id']);

        /* Update */
        $stmt = db()->prepare(
            'UPDATE organizers SET
                organization_name = ?,
                website = ?,
                about_organization = ?,
                logo = ?,
                country = ?,
                city = ?,
                slug = ?,
                facebook = ?,
                instagram = ?,
                linkedin = ?,
                twitter = ?,
                youtube = ?,
                telegram = ?,
                whatsapp = ?,
                is_profile_complete = 1,
                updated_at = NOW()
             WHERE id = ?
             AND user_id = ?'
        );

        $stmt->execute([
            $orgName,
            $urls['website'] ?: null,
            $about,
            $urls['logo_url'] ?: null,
            $country,
            $city,
            $slug,
            $urls['facebook'] ?: null,
            $urls['instagram'] ?: null,
            $urls['linkedin'] ?: null,
            $urls['twitter'] ?: null,
            $urls['youtube'] ?: null,
            $urls['telegram'] ?: null,
            $whatsapp,
            (int) $profile['id'],
            (int) $user['id'],
        ]);

        audit_log(
            'ORGANIZER_PROFILE_UPDATE',
            'Organizer profile updated',
            null,
            (int) $profile['id']
        );

        flash('success', 'Organizer profile saved successfully.');
        redirect('/organizer/');

    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
}

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = empty($profile['is_profile_complete'])
    ? 'Complete Organizer Profile'
    : 'Organizer Profile';

$dashboardRole = 'organizer';

require dirname(__DIR__) . '/includes/dashboard-header.php';
?>

<!-- ==========================================================================
     INFO BANNER
     ========================================================================== -->
<div class="organizer-welcome-banner mb-4 reveal">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <div class="section-kicker mb-1" style="color:var(--color-highlight);">
        <?= empty($profile['is_profile_complete']) ? 'Setup Required' : 'Account Settings' ?>
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        <?= empty($profile['is_profile_complete'])
            ? 'Complete Your Organizer Profile'
            : 'Organizer Profile' ?>
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        <?php if (empty($profile['is_profile_complete'])): ?>
          Complete the required fields below to unlock the Organizer Portal.
        <?php else: ?>
          Update your organization details and social links.
        <?php endif; ?>
      </p>
    </div>
  </div>
</div>

<!-- ==========================================================================
     FORM
     ========================================================================== -->
<form method="post" class="row g-4 reveal">
  <?= csrf_field() ?>

  <!-- ==================== SECTION 1: ORGANIZATION ==================== -->
  <div class="col-12">
    <div class="form-section">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-building me-2 text-brand"></i>
        Organization Information
      </h3>

      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">
            Organization Name <span class="text-danger">*</span>
          </label>
          <input name="organization_name"
                 class="form-control"
                 value="<?= e((string) $form['organization_name']) ?>"
                 maxlength="190"
                 required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Website</label>
          <input name="website"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['website']) ?>"
                 placeholder="https://example.org">
        </div>

        <div class="col-md-6">
          <label class="form-label">
            Country <span class="text-danger">*</span>
          </label>
          <select name="country_id"
                  class="form-select"
                  data-country-select
                  required>
            <option value="">Select country</option>
            <?php foreach ($countries as $countryRow): ?>
              <option value="<?= (int) $countryRow['id'] ?>"
                      <?= (int) $form['country_id'] === (int) $countryRow['id'] ? 'selected' : '' ?>>
                <?= e($countryRow['name']) ?>
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
                  data-city-select
                  required
                  <?= (int) $form['country_id'] > 0 ? '' : 'disabled' ?>>
            <option value="">
              <?= (int) $form['country_id'] > 0 ? 'Select city' : 'Select country first' ?>
            </option>
            <?php foreach ($cities as $cityRow): ?>
              <option value="<?= (int) $cityRow['id'] ?>"
                      data-country="<?= (int) $cityRow['country_id'] ?>"
                      <?= (int) $form['city_id'] === (int) $cityRow['id'] ? 'selected' : '' ?>>
                <?= e($cityRow['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">
            About Organization <span class="text-danger">*</span>
          </label>
          <textarea name="about_organization"
                    rows="5"
                    class="form-control"
                    required><?= e((string) $form['about_organization']) ?></textarea>
          <div class="form-text">
            This appears on your public organizer profile.
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Logo URL</label>
          <input name="logo_url"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['logo_url']) ?>"
                 placeholder="https://example.org/logo.png">
        </div>

      </div>
    </div>
  </div>

  <!-- ==================== SECTION 2: SOCIAL LINKS ==================== -->
  <div class="col-12">
    <div class="form-section">

      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-share me-2 text-brand"></i>
        Social Links
      </h3>

      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">Facebook</label>
          <input name="facebook"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['facebook']) ?>"
                 placeholder="https://facebook.com/...">
        </div>

        <div class="col-md-6">
          <label class="form-label">Instagram</label>
          <input name="instagram"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['instagram']) ?>"
                 placeholder="https://instagram.com/...">
        </div>

        <div class="col-md-6">
          <label class="form-label">LinkedIn</label>
          <input name="linkedin"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['linkedin']) ?>"
                 placeholder="https://linkedin.com/...">
        </div>

        <div class="col-md-6">
          <label class="form-label">Twitter / X</label>
          <input name="twitter"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['twitter']) ?>"
                 placeholder="https://x.com/...">
        </div>

        <div class="col-md-6">
          <label class="form-label">YouTube</label>
          <input name="youtube"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['youtube']) ?>"
                 placeholder="https://youtube.com/...">
        </div>

        <div class="col-md-6">
          <label class="form-label">Telegram</label>
          <input name="telegram"
                 type="url"
                 class="form-control"
                 value="<?= e((string) $form['telegram']) ?>"
                 placeholder="https://t.me/...">
        </div>

        <div class="col-md-6">
          <label class="form-label">WhatsApp</label>
          <div class="row g-2">
            <div class="col-4">
              <div class="input-group">
                <span class="input-group-text">+</span>
                <input name="whatsapp_country_code"
                       type="tel"
                       inputmode="numeric"
                       class="form-control"
                       value="<?= e((string) $form['whatsapp_country_code']) ?>"
                       placeholder="91"
                       maxlength="4">
              </div>
            </div>
            <div class="col-8">
              <input name="whatsapp_phone"
                     type="tel"
                     inputmode="tel"
                     class="form-control"
                     value="<?= e((string) $form['whatsapp_phone']) ?>"
                     placeholder="9876543210"
                     maxlength="20">
            </div>
          </div>
          <div class="form-text">Country code + phone number</div>
        </div>

      </div>
    </div>
  </div>

  <!-- ==================== SUBMIT ==================== -->
  <div class="col-12 d-flex justify-content-end border-top pt-4">
    <button type="submit" class="btn btn-action px-4">
      <i class="bi bi-check-circle me-1"></i>
      Save Profile
    </button>
  </div>

</form>

<!-- Organizer banner style -->
<style>
.organizer-welcome-banner {
  background:
    radial-gradient(circle at 90% 10%, rgba(249, 115, 22, 0.35), transparent 55%),
    radial-gradient(circle at 10% 90%, rgba(37, 99, 235, 0.20), transparent 55%),
    linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
  border-radius: var(--radius-xl);
  padding: 1.5rem 1.75rem;
  position: relative;
  overflow: hidden;
}

.organizer-welcome-banner::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.06) 1px, transparent 0);
  background-size: 28px 28px;
  pointer-events: none;
}

.organizer-welcome-banner > * {
  position: relative;
  z-index: 1;
}

@media (max-width: 767.98px) {
  .organizer-welcome-banner { padding: 1.25rem; }
}
</style>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>