<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/components.php';

/*
|--------------------------------------------------------------------------
| Home Feedback Form
|--------------------------------------------------------------------------
*/
$feedbackCountries = fetch_countries();
$feedbackCities = fetch_cities();

$feedbackFormError = '';
$openFeedbackModal = false;

$feedbackForm = [
    'name' => '',
    'country_id' => 0,
    'city_id' => 0,
    'rating' => 5,
    'message' => '',
];

if (is_post() && post('form_type') === 'home_feedback') {
    verify_csrf();

    $openFeedbackModal = true;

    $feedbackForm = [
        'name' => trim(post('name')),
        'country_id' => (int) post('country_id'),
        'city_id' => (int) post('city_id'),
        'rating' => (int) post('rating', '5'),
        'message' => trim(post('message')),
    ];

    try {
        if ($feedbackForm['name'] === '') {
            throw new RuntimeException('Name is required.');
        }
        if (mb_strlen($feedbackForm['name']) > 150) {
            throw new RuntimeException('Name is too long.');
        }
        if ($feedbackForm['country_id'] <= 0 || $feedbackForm['city_id'] <= 0) {
            throw new RuntimeException('Please select your country and city.');
        }
        if ($feedbackForm['rating'] < 1 || $feedbackForm['rating'] > 5) {
            throw new RuntimeException('Please select a valid star rating.');
        }
        if ($feedbackForm['message'] === '') {
            throw new RuntimeException('Please write your feedback.');
        }
if (mb_strlen($feedbackForm['message']) > 200) {
    throw new RuntimeException('Feedback must be 200 characters or less.');
}

        $countryStmt = db()->prepare(
            "SELECT name FROM countries WHERE id = ? AND status = 'active' LIMIT 1"
        );
        $countryStmt->execute([$feedbackForm['country_id']]);
        $countryName = $countryStmt->fetchColumn();

        $cityStmt = db()->prepare(
            "SELECT name FROM cities WHERE id = ? AND country_id = ? AND status = 'active' LIMIT 1"
        );
        $cityStmt->execute([$feedbackForm['city_id'], $feedbackForm['country_id']]);
        $cityName = $cityStmt->fetchColumn();

        if (!$countryName || !$cityName) {
            throw new RuntimeException('Please select a valid country and city.');
        }

        $stmt = db()->prepare(
            "INSERT INTO user_feedbacks (
                name, email, organization, country, image,
                message, rating, status, is_verified, created_at, updated_at
            ) VALUES (?, NULL, ?, ?, NULL, ?, ?, 'pending', 0, NOW(), NOW())"
        );

        $stmt->execute([
            $feedbackForm['name'],
            $cityName,
            $countryName,
            $feedbackForm['message'],
            $feedbackForm['rating'],
        ]);

        flash('success', 'Thank you. Your feedback was submitted for Admin review.');
        redirect('/#feedback');
    } catch (Throwable $e) {
        $feedbackFormError = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Public Home Page Data
|--------------------------------------------------------------------------
*/
$publicConferenceWhere = "
    c.status = 'approved'
    AND c.is_deactivated = 0
    AND c.end_date >= CURDATE()
";

/* Featured Conferences */
$featuredSql = "
    SELECT c.*, ca.name AS topic_name, co.name AS country_name, ci.name AS city_name,
           o.organization_name AS organizer_name
    FROM conferences c
    LEFT JOIN categories ca ON ca.id = c.category_id
    LEFT JOIN countries co ON co.id = c.country_id
    LEFT JOIN cities ci ON ci.id = c.city_id
    LEFT JOIN organizers o ON o.id = c.organizer_id
    WHERE {$publicConferenceWhere}
      AND c.is_featured = 1
    ORDER BY
        CASE WHEN CURDATE() BETWEEN c.start_date AND c.end_date THEN 0 ELSE 1 END ASC,
        c.start_date ASC,
        c.updated_at DESC,
        c.id DESC
    LIMIT 15
";
$featured = db()->query($featuredSql)->fetchAll();

/* Active Countries */
$countries = db()->query(
    "SELECT co.id, co.name, co.slug, COUNT(c.id) AS conference_count
     FROM countries co
     INNER JOIN conferences c ON c.country_id = co.id AND {$publicConferenceWhere}
     WHERE co.status = 'active'
     GROUP BY co.id, co.name, co.slug
     HAVING COUNT(c.id) > 0
     ORDER BY conference_count DESC, co.name ASC"
)->fetchAll();

/* Active Cities */
$cities = db()->query(
    "SELECT ci.id, ci.name, ci.slug, co.name AS country_name, co.slug AS country_slug,
            COUNT(c.id) AS conference_count
     FROM cities ci
     INNER JOIN countries co ON co.id = ci.country_id AND co.status = 'active'
     INNER JOIN conferences c ON c.city_id = ci.id AND {$publicConferenceWhere}
     WHERE ci.status = 'active'
     GROUP BY ci.id, ci.name, ci.slug, co.name, co.slug
     HAVING COUNT(c.id) > 0
     ORDER BY conference_count DESC, ci.name ASC"
)->fetchAll();

/* Active Topics */
$topics = db()->query(
    "SELECT ca.id, ca.name, ca.slug, COUNT(c.id) AS conference_count
     FROM categories ca
     INNER JOIN conferences c ON c.category_id = ca.id AND {$publicConferenceWhere}
     WHERE ca.status = 'active'
     GROUP BY ca.id, ca.name, ca.slug
     HAVING COUNT(c.id) > 0
     ORDER BY conference_count DESC, ca.name ASC"
)->fetchAll();

/* Organizer Profiles */
$organizers = db()->query(
    "SELECT o.*, COUNT(c.id) AS conference_count
     FROM organizers o
     INNER JOIN users u ON u.id = o.user_id AND u.status = 'active'
     INNER JOIN conferences c ON c.organizer_id = o.id AND {$publicConferenceWhere}
     WHERE o.is_profile_complete = 1 AND o.is_suspended = 0
     GROUP BY o.id
     HAVING COUNT(c.id) > 0
     ORDER BY o.is_verified DESC, conference_count DESC, o.updated_at DESC, o.id DESC
     LIMIT 15"
)->fetchAll();

/* Home content + feedback */
$feedback = fetch_approved_feedback(30);
$homeDescription = content('home_description', DEFAULT_META_DESCRIPTION);

$homeYearRange = date('Y') . '–' . (date('Y') + 1);
$homeKeywords  = page_keywords('conferences_all');

$seo = page_seo([
    'title'       => 'International Conferences ' . $homeYearRange . ' | Find Global Conferences',
    'description' => excerpt($homeDescription, 158),
    'keywords'    => $homeKeywords,
    'canonical'   => url('/'),
    'json_ld'     => [
        [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => APP_NAME,
            'url'      => url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => url('/conferences?search={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ],
        collection_schema(
            'International Conferences ' . $homeYearRange,
            url('/'),
            excerpt($homeDescription, 300),
            $homeKeywords
        ),
        breadcrumb_schema([['name' => 'Home', 'url' => url('/')]]),
    ],
]);

require __DIR__ . '/includes/header.php';

/* Display helper */
function home_public_status(array $conference): string
{
    $today = date('Y-m-d');
    $start = (string) ($conference['start_date'] ?? '');
    $end = (string) ($conference['end_date'] ?? '');
    if ($start !== '' && $end !== '' && $start <= $today && $end >= $today) {
        return 'Ongoing';
    }
    return 'Upcoming';
}
?>

<!-- ==========================================================================
     HERO
     ========================================================================== -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center g-5">

      <div class="col-lg-8">

        <span class="hero-eyebrow">
          <i class="bi bi-globe2 me-2"></i>
          Global Conference Discovery
        </span>

        <h1>
          Find the Best International Conferences in 2026-2027
        </h1>

        <p>
          Explore international conferences worldwide, discover events in your preferred subject and destination, and connect with a global community of researchers and professionals.
        </p>

        <form class="hero-search" action="<?= e(url('/conferences')) ?>" method="get" role="search">
          <div class="row g-2 align-items-center">
            <div class="col-md">
              <div class="input-group">
                <span class="input-group-text">
                  <i class="bi bi-search text-secondary"></i>
                </span>
                <input
                  class="form-control form-control-lg"
                  name="search"
                  placeholder="Search conference, topic or location"
                  aria-label="Search conferences">
              </div>
            </div>
            <div class="col-md-auto">
              <button class="btn btn-action btn-lg w-100 px-4">
                <i class="bi bi-search me-1"></i>
                Search
              </button>
            </div>
          </div>
        </form>

        <div class="d-flex flex-wrap gap-4 mt-4 small" style="color:rgba(255,255,255,0.72);">
          <span><i class="bi bi-shield-check text-warning me-1"></i> Admin-reviewed listings</span>
          <span><i class="bi bi-geo text-warning me-1"></i> Browse global locations</span>
          <span><i class="bi bi-building-check text-warning me-1"></i> Trusted organizers</span>
        </div>

      </div>

      <div class="col-lg-4 d-none d-lg-block">
        <div class="backdrop-card">
          <div class="small text-uppercase fw-bold mb-3" style="color:var(--color-highlight);letter-spacing:.12em;">
            Explore directory
          </div>

          <div class="d-grid">
            <a href="#featured" class="text-decoration-none d-flex justify-content-between align-items-center">
              Featured Conferences <i class="bi bi-arrow-down"></i>
            </a>
            <a href="#countries" class="text-decoration-none d-flex justify-content-between align-items-center">
              Countries <i class="bi bi-arrow-down"></i>
            </a>
            <a href="#cities" class="text-decoration-none d-flex justify-content-between align-items-center">
              Cities <i class="bi bi-arrow-down"></i>
            </a>
            <a href="#topics" class="text-decoration-none d-flex justify-content-between align-items-center">
              Topics <i class="bi bi-arrow-down"></i>
            </a>
            <a href="#organizers" class="text-decoration-none d-flex justify-content-between align-items-center">
              Organizers <i class="bi bi-arrow-down"></i>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ==========================================================================
     INTRO — the single H2 for this page
     ========================================================================== -->
<section class="py-5 bg-white">
  <div class="container py-3">
    <div class="reveal">
      <div class="section-kicker">International Conference Directory</div>
      <h2 class="section-title display-6 mt-2">Why Attend International Conferences?</h2>
      <p class="home-intro-copy mt-3 mb-0">
        <?= e($homeDescription) ?>
      </p>
    </div>
  </div>
</section>


<!-- ==========================================================================
     FEATURED CONFERENCES
     ========================================================================== -->
<section id="featured" class="py-5 bg-soft">
  <div class="container py-3">

    <div class="home-section-head reveal">
      <div>
        <div class="section-kicker">Admin selected</div>
        <h3 class="section-title h2 mb-1">Featured Conferences</h3>
        <p class="text-secondary mb-0">Active conferences selected as Featured by the administrator.</p>
      </div>
      <a href="<?= e(url('/conferences')) ?>" class="btn btn-outline-dark">
        View All <i class="bi bi-arrow-right ms-1"></i>
      </a>
    </div>

    <?php if ($featured): ?>
      <div class="home-featured-grid reveal-stagger">
        <?php foreach ($featured as $conference): ?>
          <?php
          $publicStatus = home_public_status($conference);
          $statusClass  = $publicStatus === 'Ongoing' ? 'text-bg-success' : 'text-bg-primary';

          $location = trim(
              (string) ($conference['city_name'] ?? '') . ', ' .
              (string) ($conference['country_name'] ?? ''),
              ', '
          );

          $topicLabel = $conference['topic_name'] ?: 'General';
          $attendance = $conference['attendance_type'] ?: 'Offline';
          ?>

          <article class="public-conference-card featured-card">

            <!-- Row 1: Featured + Status -->
            <div class="featured-card-row">
              <span class="badge badge-featured">
                <i class="bi bi-star-fill"></i> Featured
              </span>
              <span class="badge <?= e($statusClass) ?>"><?= e($publicStatus) ?></span>
            </div>

            <!-- Row 2: Topic + Attendance -->
            <div class="featured-card-row">
              <span class="topic-badge featured-card-topic"
                    title="<?= e($topicLabel) ?>">
                <?= e($topicLabel) ?>
              </span>
              <span class="badge text-bg-light border featured-card-attendance">
                <i class="bi bi-people me-1"></i> <?= e($attendance) ?>
              </span>
            </div>

            <!-- Title (H3 — not H2, to keep 1 H2 per page) -->
            <h3 class="public-conference-title"><?= e($conference['title']) ?></h3>

            <!-- Description -->
            <p class="public-conference-description">
              <?= e(excerpt((string) $conference['description'], 125)) ?>
            </p>

            <!-- Date + Location meta -->
            <div class="conference-card-meta mb-3">
              <div class="d-flex align-items-center gap-2 mb-1 featured-card-date">
                <i class="bi bi-calendar3 text-brand"></i>
                <span>
                  <?= e(date('j M Y', strtotime((string) $conference['start_date']))) ?>
                  <?php if (!empty($conference['end_date']) && $conference['end_date'] !== $conference['start_date']): ?>
                    <span class="text-secondary">→</span>
                    <?= e(date('j M Y', strtotime((string) $conference['end_date']))) ?>
                  <?php endif; ?>
                </span>
              </div>

              <?php if ($location !== ''): ?>
                <div class="d-flex align-items-center gap-2 featured-card-location">
                  <i class="bi bi-geo-alt text-brand"></i>
                  <span><?= e($location) ?></span>
                </div>
              <?php endif; ?>
            </div>

            <!-- View button -->
            <a href="<?= e(url('/conference/' . $conference['slug'])) ?>"
               class="btn btn-outline-dark btn-sm w-100 mt-auto"
               target="_blank"
               rel="noopener">
              View More <i class="bi bi-arrow-right ms-1"></i>
            </a>

          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state bg-white rounded-4 border">
        <i class="bi bi-stars"></i>
        <p class="mt-2 mb-0">Featured conferences will appear here after Admin selection.</p>
      </div>
    <?php endif; ?>

  </div>
</section>


<!-- ==========================================================================
     COUNTRIES
     ========================================================================== -->
<section id="countries" class="py-5 bg-white">
  <div class="container py-3">

    <div class="home-section-head reveal">
      <div>
        <div class="section-kicker">Explore locations</div>
        <h3 class="section-title h2 mb-1">Countries</h3>
        <p class="text-secondary mb-0">Only countries with active public conferences are displayed.</p>
      </div>

      <div class="home-mini-search">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="search" class="form-control" placeholder="Search country"
                 aria-label="Search country" data-home-search="countriesGrid">
        </div>
      </div>
    </div>

    <?php if ($countries): ?>
      <div class="directory-scroll-box reveal">
        <div class="directory-grid" id="countriesGrid">
          <?php foreach ($countries as $country): ?>
            <a class="directory-tile directory-tile-country"
               href="<?= e(url('/conferences/' . $country['slug'])) ?>"
               data-search-text="<?= e(strtolower((string) $country['name'])) ?>">

              <div class="directory-tile-icon">
                <i class="bi bi-globe2"></i>
              </div>

              <div class="directory-tile-name"><?= e($country['name']) ?></div>

              <div class="directory-tile-count">
                <?= (int) $country['conference_count'] ?>
                <?= (int) $country['conference_count'] === 1 ? 'Conference' : 'Conferences' ?>
              </div>

            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="home-no-results" data-no-results-for="countriesGrid">No matching country found.</div>
    <?php else: ?>
      <p class="text-secondary mb-0">Countries will appear when active conferences are available.</p>
    <?php endif; ?>

  </div>
</section>


<!-- ==========================================================================
     CITIES
     ========================================================================== -->
<section id="cities" class="py-5 bg-soft">
  <div class="container py-3">

    <div class="home-section-head reveal">
      <div>
        <div class="section-kicker">Popular hubs</div>
        <h3 class="section-title h2 mb-1">Cities</h3>
        <p class="text-secondary mb-0">Only cities with active public conferences are displayed.</p>
      </div>

      <div class="home-mini-search">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="search" class="form-control" placeholder="Search city"
                 aria-label="Search city" data-home-search="citiesGrid">
        </div>
      </div>
    </div>

    <?php if ($cities): ?>
      <div class="directory-scroll-box reveal">
        <div class="directory-grid" id="citiesGrid">
          <?php foreach ($cities as $city): ?>
            <a class="directory-tile directory-tile-city"
               href="<?= e(url('/conferences/' . $city['country_slug'] . '/' . $city['slug'])) ?>"
               data-search-text="<?= e(strtolower((string) ($city['name'] . ' ' . $city['country_name']))) ?>">

              <div class="directory-tile-icon">
                <i class="bi bi-geo-alt"></i>
              </div>

              <div class="directory-tile-name"><?= e($city['name']) ?></div>
              <div class="directory-tile-sub"><?= e($city['country_name']) ?></div>

              <div class="directory-tile-count">
                <?= (int) $city['conference_count'] ?>
                <?= (int) $city['conference_count'] === 1 ? 'Conference' : 'Conferences' ?>
              </div>

            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="home-no-results" data-no-results-for="citiesGrid">No matching city found.</div>
    <?php else: ?>
      <p class="text-secondary mb-0">Cities will appear when active conferences are available.</p>
    <?php endif; ?>

  </div>
</section>


<!-- ==========================================================================
     TOPICS
     ========================================================================== -->
<section id="topics" class="py-5 bg-white">
  <div class="container py-3">

    <div class="home-section-head reveal">
      <div>
        <div class="section-kicker">Explore by subject</div>
        <h3 class="section-title h2 mb-1">Topics</h3>
        <p class="text-secondary mb-0">Only topics used by active public conferences are displayed.</p>
      </div>

      <div class="home-mini-search">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input type="search" class="form-control" placeholder="Search topic"
                 aria-label="Search topic" data-home-search="topicsGrid">
        </div>
      </div>
    </div>

    <?php if ($topics): ?>
      <div class="directory-scroll-box reveal">
        <div class="directory-grid" id="topicsGrid">
          <?php foreach ($topics as $topic): ?>
            <a class="directory-tile directory-tile-topic"
               href="<?= e(url('/conferences/' . $topic['slug'])) ?>"
               data-search-text="<?= e(strtolower((string) $topic['name'])) ?>">

              <div class="directory-tile-icon">
                <i class="bi bi-tag"></i>
              </div>

              <div class="directory-tile-name"><?= e($topic['name']) ?></div>

              <div class="directory-tile-count">
                <?= (int) $topic['conference_count'] ?>
                <?= (int) $topic['conference_count'] === 1 ? 'Conference' : 'Conferences' ?>
              </div>

            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="home-no-results" data-no-results-for="topicsGrid">No matching topic found.</div>
    <?php else: ?>
      <p class="text-secondary mb-0">Topics will appear after active conferences are published.</p>
    <?php endif; ?>

  </div>
</section>


<!-- ==========================================================================
     ORGANIZERS
     ========================================================================== -->
<section id="organizers" class="py-5 bg-soft">
  <div class="container py-3">

    <div class="home-section-head reveal">
      <div>
        <div class="section-kicker">Conference hosts</div>
        <h3 class="section-title h2 mb-1">Organizer Profiles</h3>
        <p class="text-secondary mb-0">Organizers with active approved conferences are shown here.</p>
      </div>
    </div>

    <?php if ($organizers): ?>
      <div class="home-organizer-grid reveal-stagger">
        <?php foreach ($organizers as $organizer): ?>
          <?php
          $logoUrl = absolute_media_url($organizer['logo'] ?? null);
          $location = trim(
              (string) ($organizer['city'] ?? '') .
              (!empty($organizer['city']) && !empty($organizer['country']) ? ', ' : '') .
              (string) ($organizer['country'] ?? '')
          );
          ?>

          <article class="home-organizer-card">
            <?php if ($logoUrl !== ''): ?>
              <img src="<?= e($logoUrl) ?>"
                   alt="<?= e($organizer['organization_name']) ?> logo"
                   class="home-organizer-logo" loading="lazy">
            <?php else: ?>
              <div class="home-organizer-logo-placeholder">
                <i class="bi bi-building fs-2"></i>
              </div>
            <?php endif; ?>

            <h3 class="home-organizer-name"><?= e($organizer['organization_name']) ?></h3>
            <div class="home-organizer-location">
              <?= e($location ?: 'International Organizer') ?>
            </div>

            <p class="home-organizer-description">
              <?= e(excerpt((string) ($organizer['about_organization'] ?? ''), 115)) ?>
            </p>

            <div class="home-organizer-count">
              <?= (int) $organizer['conference_count'] ?> Active
              <?= (int) $organizer['conference_count'] === 1 ? 'Conference' : 'Conferences' ?>
            </div>

            <a href="<?= e(url('/organizers/' . $organizer['slug'])) ?>"
               class="btn btn-outline-dark btn-sm w-100">
              View Profile <i class="bi bi-arrow-right ms-1"></i>
            </a>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="text-center mt-4 reveal">
        <a href="<?= e(url('/organizers')) ?>" class="btn btn-action px-4">
          View All Organizers <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    <?php else: ?>
      <div class="empty-state bg-white rounded-4 border">
        <p class="mb-0">Organizer profiles will appear when active conferences are published.</p>
      </div>
    <?php endif; ?>

  </div>
</section>


<!-- ==========================================================================
     FEEDBACK
     ========================================================================== -->
<section id="feedback" class="py-5 bg-white">
  <div class="container py-3">

    <div class="home-section-head reveal">
      <div>
        <div class="section-kicker">Customer Feedback</div>
        <h3 class="section-title h2 mb-1">What Our Visitors Say</h3>
        <p class="text-secondary mb-0">Feedback shown here has been activated by the administrator.</p>
      </div>

      <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if (count($feedback) > 1): ?>
          <button type="button" class="btn btn-outline-secondary feedback-scroll-control"
                  data-feedback-scroll="prev" aria-label="Previous feedback">
            <i class="bi bi-arrow-left"></i>
          </button>
          <button type="button" class="btn btn-outline-secondary feedback-scroll-control"
                  data-feedback-scroll="next" aria-label="Next feedback">
            <i class="bi bi-arrow-right"></i>
          </button>
        <?php endif; ?>

        <a href="<?= e(url('/feedback/all')) ?>"
           class="btn btn-outline-dark">
          View All Feedback <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>

    <?php if ($feedback): ?>
      <div class="feedback-scroll-shell reveal">
        <div class="feedback-scroll-track" id="homeFeedbackTrack" tabindex="0" aria-label="Customer feedback">
          <?php foreach ($feedback as $item): ?>
            <article class="feedback-public-card">

              <?php if (!empty($item['image'])): ?>
                <div class="feedback-public-photo">
                  <img src="<?= e(absolute_media_url($item['image'])) ?>"
                       alt="<?= e($item['name']) ?>"
                       loading="lazy">
                </div>
              <?php else: ?>
                <div class="feedback-public-photo">
                  <div class="feedback-public-photo-placeholder">
                    <i class="bi bi-person-fill"></i>
                  </div>
                </div>
              <?php endif; ?>

              <div class="feedback-public-name">
                <?= e($item['name']) ?>
              </div>

              <div class="feedback-public-location">
                <?php
                $feedbackLocation = trim(
                    (string) ($item['organization'] ?? '') .
                    (!empty($item['organization']) && !empty($item['country']) ? ', ' : '') .
                    (string) ($item['country'] ?? '')
                );
                ?>
                <?= e($feedbackLocation ?: 'Conference Visitor') ?>
              </div>

              <div class="feedback-public-stars">
                <?php
                $rating = max(1, min(5, (int) $item['rating']));
                for ($star = 1; $star <= 5; $star++):
                ?>
                  <i class="bi <?= $star <= $rating ? 'bi-star-fill' : 'bi-star' ?>"></i>
                <?php endfor; ?>
              </div>

              <p class="feedback-public-message">
                "<?= e(excerpt((string) $item['message'], 220)) ?>"
              </p>

            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state bg-soft rounded-4">
        Activated customer feedback will appear here.
      </div>
    <?php endif; ?>

    <div class="feedback-add-box mt-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 reveal">
      <div>
        <h3 class="h5 fw-bold mb-1">Share Your Experience</h3>
        <p class="text-secondary mb-0">Add your feedback. It will be published after Admin activates it.</p>
      </div>

      <a href="<?= e(url('/feedback')) ?>"
        class="btn btn-action px-4 flex-shrink-0">
        <i class="bi bi-chat-square-heart me-1"></i> Add Feedback
      </a>
    </div>

  </div>
</section>

<!-- ==========================================================================
     Home-specific Styles
     ========================================================================== -->
<style>

/* ==========================================================================
   HOME INTRO — full-width description paragraph
   ========================================================================== */
.home-intro-copy {
  color: var(--color-muted);
  line-height: var(--lh-relaxed);
  font-size: var(--fs-lg);
  max-width: 100%;
  text-align: left;
}

/* ==========================================================================
   FEATURED CONFERENCES GRID
   ========================================================================== */
.home-featured-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 1.25rem;
}

@media (max-width: 1399.98px) {
  .home-featured-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (max-width: 1199.98px) {
  .home-featured-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
  .home-featured-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575.98px) {
  .home-featured-grid { grid-template-columns: 1fr; }
}

/* ==========================================================================
   FEATURED CARD — clean 2-row badge layout
   ========================================================================== */

.featured-card {
  min-width: 0;
  overflow: hidden;
}

.featured-card-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  min-width: 0;
}

.featured-card-row .badge-featured {
  flex: 0 0 auto;
}

.featured-card-row > .badge:not(.badge-featured) {
  flex: 0 0 auto;
  white-space: nowrap;
}

.featured-card-row .featured-card-topic {
  flex: 1 1 auto;
  min-width: 0;
  max-width: 100%;
  white-space: normal;
  overflow: visible;
  text-overflow: clip;
  font-size: 0.68rem;
  line-height: 1.2;
  padding: 0.3em 0.65em;
}

.featured-card-row .featured-card-attendance {
  flex: 0 0 auto;
  white-space: nowrap;
}

.featured-card-date,
.featured-card-location {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 0.8rem;
  min-width: 0;
}

.featured-card-date span,
.featured-card-location span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.public-conference-title {
  overflow-wrap: anywhere;
  word-break: break-word;
}

.public-conference-description {
  overflow-wrap: anywhere;
  word-break: break-word;
}

/* ==========================================================================
   DIRECTORY SCROLL BOX — Countries / Cities / Topics
   ========================================================================== */
.directory-scroll-box {
  position: relative;
  max-height: 640px;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 4px 10px 4px 4px;
  border-radius: var(--radius-lg);
  scrollbar-width: thin;
  scrollbar-color: var(--color-border-strong) transparent;
}

.directory-scroll-box::-webkit-scrollbar {
  width: 8px;
}

.directory-scroll-box::-webkit-scrollbar-track {
  background: transparent;
}

.directory-scroll-box::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 999px;
}

.directory-scroll-box::-webkit-scrollbar-thumb:hover {
  background: var(--color-muted);
}

.directory-grid {
  display: grid;
  grid-template-columns: repeat(8, minmax(0, 1fr));
  gap: 0.85rem;
}

@media (max-width: 1599.98px) {
  .directory-grid { grid-template-columns: repeat(7, minmax(0, 1fr)); }
}
@media (max-width: 1399.98px) {
  .directory-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
}
@media (max-width: 1199.98px) {
  .directory-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
  .directory-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 767.98px) {
  .directory-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .directory-scroll-box { max-height: 520px; }
}
@media (max-width: 420px) {
  .directory-grid { grid-template-columns: 1fr; }
  .directory-scroll-box { max-height: 460px; }
}

/* ==========================================================================
   DIRECTORY TILE — modern colorful card
   ========================================================================== */
.directory-tile {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.4rem;
  padding: 0.9rem 0.9rem 0.85rem;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  color: var(--color-text-strong);
  text-decoration: none;
  min-width: 0;
  overflow: hidden;
  transition:
    transform    var(--dur-base) var(--ease-standard),
    box-shadow   var(--dur-base) var(--ease-standard),
    border-color var(--dur-base) var(--ease-standard),
    background   var(--dur-base) var(--ease-standard);
}

.directory-tile::before {
  content: "";
  position: absolute;
  inset: 0 0 auto 0;
  height: 4px;
  background: linear-gradient(90deg, var(--tile-accent-1, var(--color-accent)), var(--tile-accent-2, var(--color-action)));
  opacity: 0.85;
  transition: opacity var(--dur-base) var(--ease-standard);
}

.directory-tile:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
  border-color: var(--color-accent-light);
  color: var(--color-text-strong);
}

.directory-tile:hover::before {
  opacity: 1;
}

.directory-tile-icon {
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  background: var(--tile-icon-bg, rgba(37, 99, 235, 0.10));
  color: var(--tile-accent-1, var(--color-accent));
  font-size: 1rem;
  flex-shrink: 0;
  transition: transform var(--dur-base) var(--ease-standard);
}

.directory-tile:hover .directory-tile-icon {
  transform: scale(1.08);
}

.directory-tile-name {
  font-family: var(--font-heading);
  font-weight: 700;
  font-size: 0.88rem;
  line-height: 1.25;
  color: var(--color-text-strong);
  overflow-wrap: anywhere;
  width: 100%;
}

.directory-tile-sub {
  font-size: 0.7rem;
  color: var(--color-muted);
  margin-top: -2px;
  width: 100%;
  overflow-wrap: anywhere;
}

.directory-tile-count {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--color-muted);
  padding: 0.2rem 0.55rem;
  background: var(--tile-count-bg, rgba(15, 23, 42, 0.05));
  border-radius: var(--radius-full);
  margin-top: 0.15rem;
}

.directory-tile-country {
  --tile-accent-1: #2563EB;
  --tile-accent-2: #1D4ED8;
  --tile-icon-bg: rgba(37, 99, 235, 0.12);
  --tile-count-bg: rgba(37, 99, 235, 0.08);
}
.directory-tile-country .directory-tile-icon { color: #2563EB; }
.directory-tile-country .directory-tile-count { color: #1E40AF; }

.directory-tile-city {
  --tile-accent-1: #10B981;
  --tile-accent-2: #059669;
  --tile-icon-bg: rgba(16, 185, 129, 0.12);
  --tile-count-bg: rgba(16, 185, 129, 0.08);
}
.directory-tile-city .directory-tile-icon { color: #059669; }
.directory-tile-city .directory-tile-count { color: #065F46; }

.directory-tile-topic {
  --tile-accent-1: #F97316;
  --tile-accent-2: #EA580C;
  --tile-icon-bg: rgba(249, 115, 22, 0.12);
  --tile-count-bg: rgba(249, 115, 22, 0.08);
}
.directory-tile-topic .directory-tile-icon { color: #EA580C; }
.directory-tile-topic .directory-tile-count { color: #9A3412; }
</style>

<!-- ==========================================================================
     Home-specific Scripts
     ========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {

  document.querySelectorAll('[data-home-search]').forEach(function (input) {
    var gridId = input.getAttribute('data-home-search');
    var grid = document.getElementById(gridId);
    if (!grid) return;

    var items = Array.from(grid.querySelectorAll('[data-search-text]'));
    var noResults = document.querySelector('[data-no-results-for="' + gridId + '"]');

    function run() {
      var term = input.value.trim().toLowerCase();
      var visible = 0;

      items.forEach(function (item) {
        var text = (item.getAttribute('data-search-text') || '').toLowerCase();
        var matched = term === '' || text.indexOf(term) !== -1;
        item.style.display = matched ? '' : 'none';
        if (matched) visible++;
      });

      if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
    }

    input.addEventListener('input', run);
  });

  var track = document.getElementById('homeFeedbackTrack');
  if (track) {

    document.querySelectorAll('[data-feedback-scroll]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var dir = btn.getAttribute('data-feedback-scroll');
        var first = track.querySelector('.feedback-public-card');
        var amount = first ? first.getBoundingClientRect().width + 16 : 320;
        track.scrollBy({
          left: dir === 'next' ? amount : -amount,
          behavior: 'smooth'
        });
        pauseAutoScroll();
      });
    });

    var autoScrollTimer = null;
    var autoScrollSpeed = 1500;
    var autoScrollPaused = false;

    function getScrollStep() {
      var first = track.querySelector('.feedback-public-card');
      return first ? first.getBoundingClientRect().width + 16 : 320;
    }

    function autoScrollNext() {
      if (autoScrollPaused) return;

      var step = getScrollStep();
      var maxScroll = track.scrollWidth - track.clientWidth;

      if (track.scrollLeft >= maxScroll - 5) {
        setTimeout(function () {
          track.scrollTo({ left: 0, behavior: 'smooth' });
        }, 800);
      } else {
        track.scrollBy({ left: step, behavior: 'smooth' });
      }
    }

    function startAutoScroll() {
      stopAutoScroll();
      autoScrollTimer = setInterval(autoScrollNext, autoScrollSpeed);
    }

    function stopAutoScroll() {
      if (autoScrollTimer) {
        clearInterval(autoScrollTimer);
        autoScrollTimer = null;
      }
    }

    function pauseAutoScroll() {
      autoScrollPaused = true;
      stopAutoScroll();

      clearTimeout(window.__feedbackResumeTimer);
      window.__feedbackResumeTimer = setTimeout(function () {
        autoScrollPaused = false;
        startAutoScroll();
      }, 8000);
    }

    startAutoScroll();

    track.addEventListener('mouseenter', function () {
      autoScrollPaused = true;
    });
    track.addEventListener('mouseleave', function () {
      autoScrollPaused = false;
    });

    track.addEventListener('touchstart', function () {
      pauseAutoScroll();
    }, { passive: true });

    var scrollPauseTimer = null;
    track.addEventListener('scroll', function () {
      if (autoScrollPaused) return;

      clearTimeout(scrollPauseTimer);
      scrollPauseTimer = setTimeout(function () {
        autoScrollPaused = true;
        stopAutoScroll();

        setTimeout(function () {
          autoScrollPaused = false;
          startAutoScroll();
        }, 8000);
      }, 150);
    }, { passive: true });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        stopAutoScroll();
      } else if (!autoScrollPaused) {
        startAutoScroll();
      }
    });

    var slideCount = track.querySelectorAll('.feedback-public-card').length;
    if (slideCount <= 1) {
      stopAutoScroll();
    }
  }

});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>