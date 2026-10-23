<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$slug = getq('slug');
$conference = find_conference_by_slug($slug);

/* ----------------------------- 404 ----------------------------- */
if (!$conference) {
    http_response_code(404);
    $seo = page_seo(['title' => 'Conference Not Found', 'robots' => 'noindex,follow']);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="py-5">
      <div class="container empty-state">
        <i class="bi bi-calendar-x"></i>
        <h1 class="h2 mt-3">Conference not found</h1>
        <p class="text-secondary">
          The conference may have been removed, deactivated or is not approved.
        </p>
        <a class="btn btn-action" href="<?= e(url('/conferences')) ?>">
          <i class="bi bi-arrow-left me-1"></i> Browse Conferences
        </a>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ----------------------- Increment views ----------------------- */
$conferenceId = (int) $conference['id'];
$viewedKey    = 'viewed_conferences';

if (!isset($_SESSION[$viewedKey]) || !is_array($_SESSION[$viewedKey])) {
    $_SESSION[$viewedKey] = [];
}

$userAgent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
$isBot = $userAgent === ''
      || preg_match('/(bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegram|skypeuripreview|linkedinbot|twitterbot|pinterest|google-inspectiontool)/i', $userAgent);

if (!$isBot && !isset($_SESSION[$viewedKey][$conferenceId])) {
    try {
        db()->prepare('UPDATE conferences SET views_count = views_count + 1 WHERE id = ?')
            ->execute([$conferenceId]);
        $_SESSION[$viewedKey][$conferenceId] = true;
    } catch (Throwable $e) {
        /* Ignore view-count failures */
    }
}

$org = find_public_organizer_by_slug((string) $conference['organizer_slug']);

/* ==========================================================================
   RELATED CONFERENCES
   ========================================================================== */
$related = [];

try {
    $stmt = db()->prepare(
        "SELECT c.*,
                ca.name AS topic_name,
                ca.slug AS topic_slug,
                co.name AS country_name,
                ci.name AS city_name,
                o.organization_name AS organization_name,
                CASE
                    WHEN c.category_id IS NOT NULL AND c.category_id = ? THEN 3
                    WHEN c.country_id   IS NOT NULL AND c.country_id   = ? THEN 2
                    WHEN c.city_id      IS NOT NULL AND c.city_id      = ? THEN 1
                    ELSE 0
                END AS relevance
         FROM conferences c
         LEFT JOIN categories ca ON ca.id = c.category_id
         LEFT JOIN countries  co ON co.id = c.country_id
         LEFT JOIN cities     ci ON ci.id = c.city_id
         LEFT JOIN organizers o  ON o.id = c.organizer_id
         WHERE c.id <> ?
           AND c.status = 'approved'
           AND c.is_deactivated = 0
           AND c.end_date >= CURDATE()
           AND (
                (? > 0 AND c.category_id = ?)
             OR (? > 0 AND c.country_id = ?)
             OR (? > 0 AND c.city_id = ?)
           )
         ORDER BY relevance DESC, c.start_date ASC, c.id DESC
         LIMIT 60"
    );

    $catId = (int) ($conference['category_id'] ?? 0);
    $coId  = (int) ($conference['country_id']  ?? 0);
    $ciId  = (int) ($conference['city_id']     ?? 0);

    $stmt->execute([
        $catId, $coId, $ciId,
        $conferenceId,
        $catId, $catId,
        $coId,  $coId,
        $ciId,  $ciId,
    ]);

    $related = $stmt->fetchAll();
} catch (Throwable $e) {
    $related = [];
}

/* ==========================================================================
   SEO + CONFERENCE COMBINATION ROUTES
   ========================================================================== */

$topicName = trim(
    (string) ($conference['topic_name'] ?? '')
);

$topicSlug = trim(
    (string) ($conference['topic_slug'] ?? '')
);

$countryName = trim(
    (string) ($conference['country_name'] ?? '')
);

$countrySlug = trim(
    (string) ($conference['country_slug'] ?? '')
);

$cityName = trim(
    (string) ($conference['city_name'] ?? '')
);

$citySlug = trim(
    (string) ($conference['city_slug'] ?? '')
);

$conferenceSlug = trim(
    (string) $conference['slug']
);


/*
|--------------------------------------------------------------------------
| Detect whether this request came from combination router
|--------------------------------------------------------------------------
*/

$isCombinationPage =
    getq('_conference_combination') === '1';

$combinationType =
    trim(getq('_conference_combination_type'));

$combinationPath =
    trim(getq('_conference_combination_path'));


/*
|--------------------------------------------------------------------------
| Default conference SEO
|--------------------------------------------------------------------------
*/

$title =
    $conference['seo_title']
        ?: $conference['title'];

$desc =
    $conference['seo_description']
        ?: excerpt(
            (string) $conference['description'],
            158
        );

$canonical =
    url(
        '/conference/' .
        $conferenceSlug
    );


/*
|--------------------------------------------------------------------------
| Combination-specific canonical + metadata
|--------------------------------------------------------------------------
*/

if (
    $isCombinationPage &&
    $combinationPath !== ''
) {

    /*
    |--------------------------------------------------------------------------
    | Self-referencing canonical
    |--------------------------------------------------------------------------
    */

    $canonical =
        url($combinationPath);


    /*
    |--------------------------------------------------------------------------
    | Country + Conference
    |--------------------------------------------------------------------------
    */

    if (
        $combinationType ===
        'country_conference'
    ) {

        $title =
            $conference['title'] .
            ' in ' .
            $countryName;

        $desc =
            'View details for ' .
            $conference['title'] .
            ' in ' .
            $countryName .
            ', including dates, venue, topic, organizer and registration information.';
    }


    /*
    |--------------------------------------------------------------------------
    | Topic + Conference
    |--------------------------------------------------------------------------
    */

    elseif (
        $combinationType ===
        'topic_conference'
    ) {

        $title =
            $conference['title'] .
            ' | ' .
            $topicName .
            ' Conference';

        $desc =
            'Explore ' .
            $conference['title'] .
            ', an international ' .
            $topicName .
            ' conference with event dates, venue, organizer and registration details.';
    }


    /*
    |--------------------------------------------------------------------------
    | Country + Topic + Conference
    |--------------------------------------------------------------------------
    */

    elseif (
        $combinationType ===
        'country_topic_conference'
    ) {

        $title =
            $conference['title'] .
            ' | ' .
            $topicName .
            ' Conference in ' .
            $countryName;

        $desc =
            'Discover ' .
            $conference['title'] .
            ', a ' .
            $topicName .
            ' conference in ' .
            $countryName .
            ', with dates, location, organizer and registration information.';
    }


    /*
    |--------------------------------------------------------------------------
    | Country + City + Conference
    |--------------------------------------------------------------------------
    */

    elseif (
        $combinationType ===
        'country_city_conference'
    ) {

        $title =
            $conference['title'] .
            ' in ' .
            $cityName .
            ', ' .
            $countryName;

        $desc =
            'View ' .
            $conference['title'] .
            ' in ' .
            $cityName .
            ', ' .
            $countryName .
            ', including conference dates, venue, organizer and registration details.';
    }


    /*
    |--------------------------------------------------------------------------
    | Country + City + Topic + Conference
    |--------------------------------------------------------------------------
    */

    elseif (
        $combinationType ===
        'country_city_topic_conference'
    ) {

        $title =
            $conference['title'] .
            ' | ' .
            $topicName .
            ' in ' .
            $cityName .
            ', ' .
            $countryName;

        $desc =
            'Explore ' .
            $conference['title'] .
            ', a ' .
            $topicName .
            ' conference in ' .
            $cityName .
            ', ' .
            $countryName .
            ', with full event and registration information.';
    }
}


/*
|--------------------------------------------------------------------------
| SEO image
|--------------------------------------------------------------------------
*/

$img =
    absolute_media_url(
        $conference['banner_image'] ?? ''
    )
    ?: url(
        '/assets/images/company-logo.png'
    );


/*
|--------------------------------------------------------------------------
| Structured data / breadcrumb
|--------------------------------------------------------------------------
*/

$jsonLd = [
    breadcrumb_schema([
        [
            'name' => 'Home',
            'url'  => url('/'),
        ],
        [
            'name' => 'Conferences',
            'url'  => url('/conferences'),
        ],
        [
            'name' => $conference['title'],
            'url'  => $canonical,
        ],
    ]),
];

if (
    ($conference['attendance_type'] ?? '')
    !== 'Online'
) {

    array_unshift(
        $jsonLd,
        conference_schema(
            $conference,
            $org
        )
    );
}


/*
|--------------------------------------------------------------------------
| Final SEO
|--------------------------------------------------------------------------
*/

$seo = page_seo([
    'title'       => $title,

    'description' => $desc,

    'keywords'    =>
        $conference['seo_keywords']
        ?: trim(
            $topicName .
            ', international conference, ' .
            $cityName .
            ', ' .
            $countryName,
            ', '
        ),

    'canonical'   => $canonical,

    'robots'      =>
        'index,follow,max-image-preview:large',

    'image'       => $img,

    'type'        => 'article',

    'json_ld'     => $jsonLd,
]);

require __DIR__ . '/includes/header.php';

$location = trim(($conference['city_name'] ?? '') . ', ' . ($conference['country_name'] ?? ''), ', ');

/*
|--------------------------------------------------------------------------
| Crawlable conference combination URLs
|--------------------------------------------------------------------------
*/

$conferenceSeoLinks = [];

/*
| Normal conference
*/

$conferenceSeoLinks[] = [
    'label' => 'Main Conference Page',
    'url'   => url(
        '/conference/' .
        $conferenceSlug
    ),
];


/*
| Topic
*/

if ($topicSlug !== '') {

    $conferenceSeoLinks[] = [
        'label' =>
            $topicName .
            ' Conferences',

        'url' =>
            url(
                '/conferences/' .
                $topicSlug
            ),
    ];
}


/*
| Country
*/

if ($countrySlug !== '') {

    $conferenceSeoLinks[] = [
        'label' =>
            'Conferences in ' .
            $countryName,

        'url' =>
            url(
                '/conferences/' .
                $countrySlug
            ),
    ];
}


/*
| Country + City
*/

if (
    $countrySlug !== '' &&
    $citySlug !== ''
) {

    $conferenceSeoLinks[] = [
        'label' =>
            'Conferences in ' .
            $cityName .
            ', ' .
            $countryName,

        'url' =>
            url(
                '/conferences/' .
                $countrySlug . '/' .
                $citySlug
            ),
    ];
}


/*
| Topic + Country
*/

if (
    $topicSlug !== '' &&
    $countrySlug !== ''
) {

    $conferenceSeoLinks[] = [
        'label' =>
            $topicName .
            ' Conferences in ' .
            $countryName,

        'url' =>
            url(
                '/conferences/' .
                $topicSlug . '/' .
                $countrySlug
            ),
    ];
}


/*
| Topic + Country + City
*/

if (
    $topicSlug !== '' &&
    $countrySlug !== '' &&
    $citySlug !== ''
) {

    $conferenceSeoLinks[] = [
        'label' =>
            $topicName .
            ' Conferences in ' .
            $cityName .
            ', ' .
            $countryName,

        'url' =>
            url(
                '/conferences/' .
                $topicSlug . '/' .
                $countrySlug . '/' .
                $citySlug
            ),
    ];
}


/*
|--------------------------------------------------------------------------
| Conference-specific combinations
|--------------------------------------------------------------------------
*/

/*
| Country + Conference
*/

if ($countrySlug !== '') {

    $conferenceSeoLinks[] = [
        'label' =>
            (
                $conference['short_title']
                ?:
                $conference['title']
            )
            . ' in ' .
            $countryName,

        'url' =>
            url(
                '/conferences/' .
                $countrySlug . '/' .
                $conferenceSlug
            ),
    ];
}


/*
| Topic + Conference
*/

if ($topicSlug !== '') {

    $conferenceSeoLinks[] = [
        'label' =>
            $topicName .
            ' – ' .
            (
                $conference['short_title']
                ?:
                $conference['title']
            ),

        'url' =>
            url(
                '/conferences/' .
                $topicSlug . '/' .
                $conferenceSlug
            ),
    ];
}


/*
| Country + Topic + Conference
*/

if (
    $countrySlug !== '' &&
    $topicSlug !== ''
) {

    $conferenceSeoLinks[] = [
        'label' =>
            $topicName .
            ' Conference in ' .
            $countryName,

        'url' =>
            url(
                '/conferences/' .
                $countrySlug . '/' .
                $topicSlug . '/' .
                $conferenceSlug
            ),
    ];
}

/*
| Country + City + Conference
*/

if (
    $countrySlug !== '' &&
    $citySlug !== ''
) {

    $conferenceSeoLinks[] = [
        'label' =>
            (
                $conference['short_title']
                ?:
                $conference['title']
            )
            . ' in ' .
            $cityName,

        'url' =>
            url(
                '/conferences/' .
                $countrySlug . '/' .
                $citySlug . '/' .
                $conferenceSlug
            ),
    ];
}

/*
| Country + City + Topic + Conference
*/

if (
    $countrySlug !== '' &&
    $citySlug !== '' &&
    $topicSlug !== ''
) {

    $conferenceSeoLinks[] = [
        'label' =>
            $topicName .
            ' Conference in ' .
            $cityName .
            ', ' .
            $countryName,

        'url' =>
            url(
                '/conferences/' .
                $countrySlug . '/' .
                $citySlug . '/' .
                $topicSlug . '/' .
                $conferenceSlug
            ),
    ];
}

/* ==========================================================================
   STATIC EVENT AGENDA
   ========================================================================== */
$agendaOffline = [
    ['time' => '09:00 – 09:30', 'title' => 'Registration & Tea Coffee',                          'tag' => ''],
    ['time' => '09:30 – 10:00', 'title' => 'Inaugural Speech & Conference Theme Presentation',   'tag' => ''],
    ['time' => '10:00 – 11:45', 'title' => 'Poster and Physical Presentation',                    'tag' => ''],
    ['time' => '11:45 – 12:30', 'title' => 'Panel Discussion',                                    'tag' => ''],
    ['time' => '12:30 – 01:30', 'title' => 'Lunch Break',                                          'tag' => 'BREAK'],
    ['time' => '01:30 – 02:15', 'title' => 'Award Function and Closing Ceremony',                  'tag' => 'CLOSING'],
];

$agendaOnline = [
    ['time' => '09:00 – 09:30', 'title' => 'Welcome & Virtual Check-in',                           'tag' => ''],
    ['time' => '09:30 – 10:00', 'title' => 'Keynote Session (Live Stream)',                        'tag' => ''],
    ['time' => '10:00 – 11:45', 'title' => 'Virtual Presentations',                                'tag' => ''],
    ['time' => '11:45 – 12:30', 'title' => 'Q&A Session',                                          'tag' => ''],
    ['time' => '12:30 – 01:30', 'title' => 'Virtual Networking Break',                              'tag' => 'BREAK'],
    ['time' => '01:30 – 02:15', 'title' => 'Closing Ceremony & Awards',                             'tag' => 'CLOSING'],
];

$attendanceType = (string) ($conference['attendance_type'] ?? 'Offline');

if (!in_array($attendanceType, ['Offline', 'Online', 'Hybrid'], true)) {
    $attendanceType = 'Offline';
}

if ($attendanceType === 'Hybrid') {
    $agendaHybrid = array_merge($agendaOffline, $agendaOnline);
    usort($agendaHybrid, static function ($a, $b) {
        return strcmp((string) $a['time'], (string) $b['time']);
    });
}

/* ==========================================================================
   RELATED — resolve link target for "View More" button
   ========================================================================== */
$relatedTopicSlug = trim((string) ($conference['topic_slug'] ?? ''));

$relatedViewMoreUrl = $relatedTopicSlug !== ''
    ? url('/conferences/' . $relatedTopicSlug)
    : url('/conferences');
?>

<!-- ==========================================================================
     BREADCRUMB
     ========================================================================== -->
<section class="pt-5 pb-3 bg-soft border-bottom">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/conferences')) ?>">Conferences</a></li>
        <li class="breadcrumb-item active" aria-current="page">
          <?= e($conference['title']) ?>
        </li>
      </ol>
    </nav>
  </div>
</section>

<!-- ==========================================================================
     CONFERENCE DETAIL
     ========================================================================== -->
<section class="conference-detail-wrap py-5">
  <div class="container">
    <div class="row g-5">

      <!-- ============== LEFT: MAIN CONTENT ============== -->
      <div class="col-lg-8">
        <!-- Badges -->
        <div class="d-flex flex-wrap gap-2 mb-3 reveal">
          <span class="topic-badge"><?= e($conference['topic_name'] ?: 'General') ?></span>
          <span class="badge text-bg-light border">
            <i class="bi bi-people me-1"></i> <?= e($conference['attendance_type']) ?>
          </span>
          <?php if (!empty($conference['is_verified'])): ?>
            <span class="badge text-bg-success">
              <i class="bi bi-patch-check-fill me-1"></i> Verified
            </span>
          <?php endif; ?>
        </div>

        <!-- Title — the single H1 for this page -->
        <h1 class="conference-detail-title reveal">
          <?= e($conference['title']) ?>
        </h1>

        <?php
        $showShortTitle = !empty($conference['short_title'])
            && mb_strtolower(trim($conference['short_title'])) !== mb_strtolower(trim($conference['title']));
        ?>
        <?php if ($showShortTitle): ?>
          <p class="text-secondary fw-semibold reveal">
            <?= e($conference['short_title']) ?>
          </p>
        <?php endif; ?>

        <!-- ============ COLORED INFO CARDS ============ -->
        <div class="row g-3 my-4 reveal">

          <!-- Date (Blue) -->
          <div class="col-md-4">
            <div class="conference-info-card conference-info-blue h-100">
              <div class="conference-info-icon">
                <i class="bi bi-calendar3"></i>
              </div>
              <div class="conference-info-label">Date</div>
              <div class="conference-info-value">
                <?= e(format_date($conference['start_date'])) ?>
                <?php if ($conference['end_date'] !== $conference['start_date']): ?>
                  <br><span class="conference-info-secondary">to <?= e(format_date($conference['end_date'])) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Location (Green) -->
          <div class="col-md-4">
            <div class="conference-info-card conference-info-green h-100">
              <div class="conference-info-icon">
                <i class="bi bi-geo-alt"></i>
              </div>
              <div class="conference-info-label">Location</div>
              <div class="conference-info-value">
                <?= e($location ?: '—') ?>
              </div>
            </div>
          </div>

          <!-- Status (Orange) -->
          <div class="col-md-4">
            <div class="conference-info-card conference-info-orange h-100">
              <div class="conference-info-icon">
                <i class="bi bi-activity"></i>
              </div>
              <div class="conference-info-label">Status</div>
              <div class="conference-info-value">
                <?= e(conference_live_status($conference)) ?>
                <div class="conference-info-secondary"><?= e($conference['attendance_type']) ?></div>
              </div>
            </div>
          </div>

        </div>

        <!-- ============ DESCRIPTION — the single H2 for this page ============ -->
        <article class="prose reveal">
          <h2 class="conference-overview-heading mb-4">
            <i class="bi bi-journal-text"></i>
            Conference Overview & Call for Papers
          </h2>

          <?php foreach (preg_split('/\R{2,}/', trim($conference['description'])) as $p): ?>
            <p><?= nl2br(e($p)) ?></p>
          <?php endforeach; ?>
        </article>

        <!-- ============ EVENT AGENDA ============ -->
        <section class="event-agenda-section reveal mt-5">

          <!-- Demoted from H2 to H3 -->
          <h3 class="conference-overview-heading mb-4">
            <i class="bi bi-calendar2-week"></i>
            Event Agenda
          </h3>

          <div class="event-agenda-tabs" role="tablist">
            <button type="button"
                    class="event-agenda-tab active"
                    role="tab"
                    aria-selected="true">

              <?php if ($attendanceType === 'Offline'): ?>
                <i class="bi bi-geo-alt-fill"></i>
              <?php elseif ($attendanceType === 'Online'): ?>
                <i class="bi bi-display"></i>
              <?php else: ?>
                <i class="bi bi-broadcast"></i>
              <?php endif; ?>

              <span><?= e($attendanceType) ?></span>
            </button>
          </div>

          <div class="event-agenda-panel active">
            <?php
            if ($attendanceType === 'Offline') {
                $activeAgenda = $agendaOffline;
            } elseif ($attendanceType === 'Online') {
                $activeAgenda = $agendaOnline;
            } else {
                $activeAgenda = $agendaHybrid;
            }
            ?>

            <?php foreach ($activeAgenda as $row): ?>
              <div class="event-agenda-row">
                <div class="event-agenda-time">
                  <div class="event-agenda-time-main"><?= e($row['time']) ?></div>
                </div>
                <div class="event-agenda-title">
                  <?= e($row['title']) ?>
                </div>
                <?php if (!empty($row['tag'])): ?>
                  <div class="event-agenda-tag event-agenda-tag-<?= strtolower($row['tag']) ?>">
                    <?= e($row['tag']) ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

        </section>
      </div>

      <!-- ============== RIGHT: STICKY SIDEBAR ============== -->
      <div class="col-lg-4">
        <div class="conference-sidebar conference-sidebar-sticky">

          <div class="conference-sidebar-header">
            <i class="bi bi-info-circle-fill"></i>
            <span>Conference Details</span>
          </div>

          <div class="conference-sidebar-body">

            <dl class="row small mb-4">
              <dt class="col-5 text-secondary">Topic</dt>
              <dd class="col-7 text-end fw-semibold"><?= e($conference['topic_name'] ?? '—') ?></dd>

              <dt class="col-5 text-secondary">Starts</dt>
              <dd class="col-7 text-end fw-semibold"><?= e(format_date($conference['start_date'])) ?></dd>

              <dt class="col-5 text-secondary">Ends</dt>
              <dd class="col-7 text-end fw-semibold"><?= e(format_date($conference['end_date'])) ?></dd>

              <dt class="col-5 text-secondary">Timezone</dt>
              <dd class="col-7 text-end fw-semibold"><?= e($conference['time_zone'] ?: '—') ?></dd>

              <dt class="col-5 text-secondary">Type</dt>
              <dd class="col-7 text-end fw-semibold"><?= e($conference['attendance_type']) ?></dd>

              <?php if (!empty($conference['venue'])): ?>
                <dt class="col-5 text-secondary">Venue</dt>
                <dd class="col-7 text-end fw-semibold"><?= e($conference['venue']) ?></dd>
              <?php endif; ?>

              <?php if (!empty($conference['contact_email'])): ?>
                <dt class="col-5 text-secondary">Email</dt>
                <dd class="col-7 text-end fw-semibold">
                  <a href="mailto:<?= e($conference['contact_email']) ?>"
                     class="text-decoration-none text-truncate d-inline-block"
                     style="max-width:100%;">
                    <?= e($conference['contact_email']) ?>
                  </a>
                </dd>
              <?php endif; ?>
            </dl>

            <?php if (!empty($conference['conference_website'])): ?>
              <a class="btn btn-action-outline w-100"
                 href="<?= e($conference['conference_website']) ?>"
                 rel="nofollow noopener"
                 target="_blank">
                <i class="bi bi-globe me-1"></i>
                Conference Website
              </a>
            <?php endif; ?>

            <hr>

            <div class="small text-secondary mb-2">Organizer</div>

            <?php if ($org): ?>
              <a class="conference-organizer-box"
                 href="<?= e(url('/organizers/' . $org['slug'])) ?>"
                 target="_blank"
                 rel="noopener">

                <?php if (!empty($org['logo'])): ?>
                  <img src="<?= e($org['logo']) ?>"
                       alt=""
                       width="44"
                       height="44"
                       class="organizer-logo">
                <?php else: ?>
                  <span class="avatar-circle">
                    <i class="bi bi-building"></i>
                  </span>
                <?php endif; ?>

                <span class="conference-organizer-info">
                  <strong class="d-block"><?= e($org['organization_name']) ?></strong>
                  <?php if (!empty($org['is_verified'])): ?>
                    <span class="small text-brand">
                      <i class="bi bi-patch-check-fill me-1"></i> Verified
                    </span>
                  <?php endif; ?>
                </span>

              </a>
            <?php else: ?>
              <strong><?= e($conference['organizer_name'] ?? 'Conference Organizer') ?></strong>
            <?php endif; ?>

          </div>
        </div>
      </div>

    </div>
  </div>
</section>




<!-- ==========================================================================


<?php if (!empty($conferenceSeoLinks)): ?>

<section class="py-4 border-top bg-white conference-seo-links">

    <div class="container">

        <div class="mb-3">

            <div class="section-kicker">
                Explore Conference Pages
            </div>

            <h3 class="h5 fw-bold mt-1 mb-1">
                Related Conference Links
            </h3>

            <p class="text-secondary small mb-0">
                Explore this conference by topic and location.
            </p>

        </div>


        <div class="d-flex flex-wrap gap-2">

            <?php foreach ($conferenceSeoLinks as $seoLink): ?>

                <a
                    href="<?= e($seoLink['url']) ?>"
                    class="btn btn-sm btn-outline-secondary"
                >
                    <?= e($seoLink['label']) ?>
                </a>

            <?php endforeach; ?>

        </div>

    </div>

</section>

<?php endif; ?>
========================================================================== -->



<!-- ==========================================================================
     RELATED CONFERENCES
     ========================================================================== -->
<?php if ($related): ?>
<?php
  $relatedHasOverflow = count($related) > 20;
?>
<section class="related-conferences-section py-5 bg-soft">
  <div class="container">

    <div class="related-section-head reveal">
      <div>
        <div class="section-kicker">You may also like</div>
        <!-- Demoted from H2 to H3 -->
        <h3 class="section-title h3 mt-1 mb-0">
          <i class="bi bi-link-45deg me-1 text-brand"></i>
          Related Conferences
        </h3>
      </div>

      <?php if ($relatedHasOverflow): ?>
        <a href="<?= e($relatedViewMoreUrl) ?>"
           class="btn btn-outline-dark btn-sm flex-shrink-0"
           target="_blank"
           rel="noopener">
          View More <i class="bi bi-arrow-right ms-1"></i>
        </a>
      <?php endif; ?>
    </div>

    <div class="related-scroll-box reveal">
      <div class="related-grid">
        <?php foreach ($related as $rc): ?>
          <?php
          $rcLocation = trim(
              (string) ($rc['city_name'] ?? '') . ', ' .
              (string) ($rc['country_name'] ?? ''),
              ', '
          );
          ?>

          <a class="related-card"
             href="<?= e(url('/conference/' . $rc['slug'])) ?>"
             target="_blank"
             rel="noopener">

            <h3 class="related-card-title">
              <?= e($rc['title']) ?>
            </h3>

            <p class="related-card-description">
              <?= e(excerpt((string) $rc['description'], 130)) ?>
            </p>

            <!-- Date + Location -->
            <div class="related-card-meta">
              <div class="related-card-meta-row">
                <i class="bi bi-calendar3"></i>
                <span>
                  <?= e(date('j M Y', strtotime((string) $rc['start_date']))) ?>
                  <?php if (!empty($rc['end_date']) && $rc['end_date'] !== $rc['start_date']): ?>
                    <span class="text-secondary">→</span>
                    <?= e(date('j M Y', strtotime((string) $rc['end_date']))) ?>
                  <?php endif; ?>
                </span>
              </div>

              <?php if ($rcLocation !== ''): ?>
                <div class="related-card-meta-row">
                  <i class="bi bi-geo-alt"></i>
                  <span><?= e($rcLocation) ?></span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Organizer -->
            <div class="related-card-organizer">
              <i class="bi bi-building"></i>
              <span><?= e($rc['organization_name'] ?: 'Conference Organizer') ?></span>
            </div>

          </a>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>
<?php endif; ?>

<!-- ==========================================================================
     PAGE-SPECIFIC STYLES
     ========================================================================== -->
<style>
.conference-detail-wrap {
  background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%);
}

.conference-detail-title {
  font-family: var(--font-heading);
  font-size: clamp(1.75rem, 3vw + 1rem, 2.5rem);
  font-weight: var(--fw-extrabold);
  letter-spacing: -0.03em;
  line-height: 1.15;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-accent) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: var(--sp-3);
}

.conference-info-card {
  position: relative;
  padding: 1.25rem;
  border-radius: var(--radius-xl);
  border: 1px solid transparent;
  transition:
    transform var(--dur-base) var(--ease-standard),
    box-shadow var(--dur-base) var(--ease-standard);
  overflow: hidden;
}

.conference-info-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
}

.conference-info-blue {
  background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
  border-color: #BFDBFE;
}
.conference-info-blue .conference-info-icon {
  background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
  color: #fff;
}
.conference-info-blue .conference-info-label { color: #1E40AF; }

.conference-info-green {
  background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
  border-color: #A7F3D0;
}
.conference-info-green .conference-info-icon {
  background: linear-gradient(135deg, #10B981 0%, #059669 100%);
  color: #fff;
}
.conference-info-green .conference-info-label { color: #065F46; }

.conference-info-orange {
  background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
  border-color: #FED7AA;
}
.conference-info-orange .conference-info-icon {
  background: linear-gradient(135deg, #F97316 0%, #EA580C 100%);
  color: #fff;
}
.conference-info-orange .conference-info-label { color: #9A3412; }

.conference-info-icon {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border-radius: var(--radius-lg);
  font-size: 1.15rem;
  margin-bottom: 0.75rem;
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.10);
}

.conference-info-label {
  font-family: var(--font-heading);
  font-size: 0.72rem;
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.10em;
  margin-bottom: 0.35rem;
}

.conference-info-value {
  font-family: var(--font-heading);
  font-size: 0.95rem;
  font-weight: var(--fw-bold);
  color: var(--color-text-strong);
  line-height: 1.35;
}

.conference-info-secondary {
  font-size: 0.78rem;
  font-weight: var(--fw-medium);
  color: var(--color-muted);
  margin-top: 0.15rem;
}

.conference-overview-heading {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-family: var(--font-heading);
  font-size: 1.25rem;
  font-weight: var(--fw-bold);
  color: var(--color-text-strong);
}

.conference-overview-heading i {
  display: inline-grid;
  place-items: center;
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-primary) 100%);
  color: #fff;
  border-radius: var(--radius-lg);
  font-size: 1rem;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.conference-sidebar {
  background: #fff;
  border: 1px solid var(--color-border);
  border-top: 4px solid var(--color-accent);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

.conference-sidebar-header {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 1rem 1.25rem;
  background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
  border-bottom: 1px solid #BFDBFE;
  font-family: var(--font-heading);
  font-weight: var(--fw-bold);
  font-size: 1rem;
  color: var(--color-primary);
}

.conference-sidebar-header i {
  color: var(--color-accent);
  font-size: 1.15rem;
}

.conference-sidebar-body {
  padding: 1.25rem;
}

.btn-action-outline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.65rem 1.25rem;
  font-family: var(--font-heading);
  font-size: 0.875rem;
  font-weight: var(--fw-semibold);
  color: var(--color-action);
  background: transparent;
  border: 1.5px solid var(--color-action);
  border-radius: var(--radius-md);
  text-decoration: none;
  transition:
    background var(--dur-base) var(--ease-standard),
    color var(--dur-base) var(--ease-standard),
    transform var(--dur-fast) var(--ease-standard),
    box-shadow var(--dur-base) var(--ease-standard);
}

.btn-action-outline:hover {
  background: var(--color-action);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(249, 115, 22, 0.28);
}

.conference-organizer-box {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem;
  background: linear-gradient(135deg, #F8FAFC 0%, #EFF6FF 100%);
  border: 1px solid #E0E7FF;
  border-radius: var(--radius-lg);
  text-decoration: none;
  color: var(--color-text);
  transition:
    transform var(--dur-fast) var(--ease-standard),
    border-color var(--dur-fast) var(--ease-standard),
    box-shadow var(--dur-fast) var(--ease-standard);
}

.conference-organizer-box:hover {
  transform: translateY(-2px);
  border-color: var(--color-accent);
  box-shadow: 0 6px 18px rgba(37, 99, 235, 0.12);
  color: var(--color-text);
}

.conference-organizer-info {
  min-width: 0;
}

.event-agenda-section {
  margin-top: 2.5rem;
}

.event-agenda-tabs {
  display: inline-flex;
  gap: 0.25rem;
  padding: 0.35rem;
  background: #F1F5F9;
  border-radius: var(--radius-lg);
  margin-bottom: 1.5rem;
}

.event-agenda-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.1rem;
  background: transparent;
  border: 0;
  border-radius: var(--radius-md);
  font-family: var(--font-heading);
  font-size: 0.875rem;
  font-weight: var(--fw-semibold);
  color: var(--color-muted);
  cursor: pointer;
  transition:
    background var(--dur-fast) var(--ease-standard),
    color var(--dur-fast) var(--ease-standard),
    box-shadow var(--dur-fast) var(--ease-standard);
}

.event-agenda-tab i {
  font-size: 1rem;
}

.event-agenda-tab:hover {
  color: var(--color-text-strong);
}

.event-agenda-tab.active {
  background: #fff;
  color: var(--color-action);
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.event-agenda-tab.active i {
  color: var(--color-action);
}

.event-agenda-panel { display: none; }
.event-agenda-panel.active { display: block; }

.event-agenda-row {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1.1rem 1.25rem;
  background: #F8FAFC;
  border-left: 4px solid var(--color-accent);
  border-radius: var(--radius-lg);
  margin-bottom: 0.75rem;
  transition:
    background var(--dur-fast) var(--ease-standard),
    transform var(--dur-fast) var(--ease-standard),
    box-shadow var(--dur-fast) var(--ease-standard);
}

.event-agenda-row:hover {
  background: #fff;
  transform: translateX(4px);
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}

.event-agenda-row:has(.event-agenda-tag-break) {
  border-left-color: #94A3B8;
}

.event-agenda-row:has(.event-agenda-tag-closing) {
  border-left-color: var(--color-action);
}

.event-agenda-time {
  min-width: 130px;
  flex-shrink: 0;
}

.event-agenda-time-main {
  font-family: var(--font-heading);
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--color-text-strong);
  white-space: nowrap;
}

.event-agenda-title {
  flex: 1;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--color-text-strong);
  min-width: 0;
}

.event-agenda-tag {
  flex-shrink: 0;
  padding: 0.25rem 0.7rem;
  font-family: var(--font-heading);
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  border-radius: var(--radius-full);
}

.event-agenda-tag-break {
  background: #E2E8F0;
  color: #475569;
}

.event-agenda-tag-closing {
  background: #FEF3C7;
  color: #92400E;
}

@media (max-width: 767.98px) {
  .event-agenda-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .event-agenda-time {
    min-width: 0;
  }

  .event-agenda-time-main {
    font-size: 0.85rem;
  }

  .event-agenda-title {
    font-size: 0.9rem;
  }
}

.related-conferences-section {
  border-top: 1px solid var(--color-border);
}

.related-section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.related-scroll-box {
  position: relative;
  max-height: 700px;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 4px 10px 4px 4px;
  border-radius: var(--radius-lg);
  scrollbar-width: thin;
  scrollbar-color: var(--color-border-strong) transparent;
}

.related-scroll-box::-webkit-scrollbar { width: 8px; }
.related-scroll-box::-webkit-scrollbar-track { background: transparent; }
.related-scroll-box::-webkit-scrollbar-thumb {
  background: var(--color-border-strong);
  border-radius: 999px;
}
.related-scroll-box::-webkit-scrollbar-thumb:hover {
  background: var(--color-muted);
}

.related-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
}

@media (max-width: 1199.98px) {
  .related-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 991.98px) {
  .related-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .related-scroll-box { max-height: 600px; }
}
@media (max-width: 575.98px) {
  .related-grid { grid-template-columns: 1fr; }
  .related-scroll-box { max-height: 520px; }
}

.related-card {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  padding: 1rem 1.1rem 1.1rem;
  background: #fff;
  border: 1px solid var(--color-border);
  border-left: 4px solid var(--color-accent);
  border-radius: var(--radius-lg);
  color: inherit;
  text-decoration: none;
  min-width: 0;
  overflow: hidden;
  transition:
    transform    var(--dur-base) var(--ease-standard),
    box-shadow   var(--dur-base) var(--ease-standard),
    border-color var(--dur-base) var(--ease-standard),
    background   var(--dur-base) var(--ease-standard);
}

.related-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
  border-color: var(--color-border);
  border-left-color: var(--color-action);
  background: #FBFDFF;
  color: inherit;
}

.related-card-title {
  font-family: var(--font-heading);
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.3;
  color: var(--color-text-strong);
  margin: 0;
  overflow-wrap: anywhere;
  transition: color var(--dur-fast) var(--ease-standard);
}

.related-card:hover .related-card-title {
  color: var(--color-accent);
}

.related-card-description {
  flex: 1;
  font-size: 0.82rem;
  line-height: 1.55;
  color: var(--color-muted);
  margin: 0;
  overflow-wrap: anywhere;
}

.related-card-organizer {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding-top: 0.55rem;
  border-top: 1px dashed var(--color-border);
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--color-text);
  min-width: 0;
}

.related-card-organizer i {
  color: var(--color-accent);
  font-size: 0.85rem;
  flex-shrink: 0;
}

.related-card-organizer span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.related-card-meta {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding-top: 0.55rem;
  margin-top: 0.35rem;
  border-top: 1px dashed var(--color-border);
}

.related-card-meta-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--color-text);
  min-width: 0;
}

.related-card-meta-row i {
  color: var(--color-accent);
  font-size: 0.82rem;
  flex-shrink: 0;
}

.related-card-meta-row span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

/* ==========================================================================
   STICKY CONFERENCE DETAILS SIDEBAR
   ========================================================================== */

@media (min-width: 992px) {

  .conference-detail-wrap,
  .conference-detail-wrap > .container,
  .conference-detail-wrap .row {
    overflow: visible !important;
  }

  .conference-sidebar-sticky {
    position: -webkit-sticky !important;
    position: sticky !important;
    top: 110px !important;
    z-index: 20;
  }

}

@media (max-width: 991.98px) {

  .conference-sidebar-sticky {
    position: static !important;
    top: auto !important;
  }

}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>