<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   FILTERS
   ========================================================================== */
$topicSlug       = getq('topic_slug');
$countrySlug     = getq('country_slug');
$cityCountrySlug = getq('city_country_slug');
$citySlug        = getq('city_slug');
$legacyCityKey   = getq('city_key');
$searchTerm      = trim(getq('search'));

/* ==========================================================================
   COMPACT PUBLIC SEO ROUTES
   ========================================================================== */
$seo1 = strtolower(trim(getq('seo1')));
$seo2 = strtolower(trim(getq('seo2')));
$seo3 = strtolower(trim(getq('seo3')));
$compactRouteInvalid = false;

if ($seo1 !== '') {
    if ($seo3 !== '') {
        $topicSlug       = $seo1;
        $countrySlug     = $seo2;
        $cityCountrySlug = $seo2;
        $citySlug        = $seo3;
    } elseif ($seo2 !== '') {
        $topicCheck = db()->prepare(
            "SELECT id FROM categories WHERE slug = ? AND status = 'active' LIMIT 1"
        );
        $topicCheck->execute([$seo1]);
        $topicExists = (bool) $topicCheck->fetchColumn();

        $countryCheck = db()->prepare(
            "SELECT id FROM countries WHERE slug = ? AND status = 'active' LIMIT 1"
        );
        $countryCheck->execute([$seo2]);
        $secondIsCountry = (bool) $countryCheck->fetchColumn();

        if ($topicExists && $secondIsCountry) {
            $topicSlug   = $seo1;
            $countrySlug = $seo2;
        } else {
            $firstCountryCheck = db()->prepare(
                "SELECT id FROM countries WHERE slug = ? AND status = 'active' LIMIT 1"
            );
            $firstCountryCheck->execute([$seo1]);
            $firstCountryExists = (bool) $firstCountryCheck->fetchColumn();

            $cityCheck = db()->prepare(
                "SELECT ci.id
                 FROM cities ci
                 JOIN countries co ON co.id = ci.country_id
                 WHERE co.slug = ?
                   AND ci.slug = ?
                   AND co.status = 'active'
                   AND ci.status = 'active'
                 LIMIT 1"
            );
            $cityCheck->execute([$seo1, $seo2]);
            $cityExists = (bool) $cityCheck->fetchColumn();

            if ($firstCountryExists && $cityExists) {
                $cityCountrySlug = $seo1;
                $citySlug        = $seo2;
            } else {
                $compactRouteInvalid = true;
            }
        }
    } else {
        $topicCheck = db()->prepare(
            "SELECT id FROM categories WHERE slug = ? AND status = 'active' LIMIT 1"
        );
        $topicCheck->execute([$seo1]);

        if ($topicCheck->fetchColumn()) {
            $topicSlug = $seo1;
        } else {
            $countryCheck = db()->prepare(
                "SELECT id FROM countries WHERE slug = ? AND status = 'active' LIMIT 1"
            );
            $countryCheck->execute([$seo1]);

            if ($countryCheck->fetchColumn()) {
                $countrySlug = $seo1;
            } else {
                $compactRouteInvalid = true;
            }
        }
    }
}

$topicFromSlug   = $topicSlug !== '';
$countryFromSlug = $countrySlug !== '';
$cityFromSlug    = ($cityCountrySlug !== '' && $citySlug !== '') || $legacyCityKey !== '';

$topicId    = (int) getq('topic');
$countryId  = (int) getq('country');
$cityId     = (int) getq('city');
$attendance = getq('attendance');
$status     = strtolower(getq('status', getq('live')));
$sort       = getq('sort', 'nearest');

if (!in_array($attendance, ['', 'Offline', 'Online', 'Hybrid'], true)) $attendance = '';
if (!in_array($status, ['', 'upcoming', 'ongoing'], true)) $status = '';
if (!in_array($sort, ['nearest', 'latest', 'title-asc', 'title-desc'], true)) $sort = 'nearest';

$topicRow   = null;
$countryRow = null;
$cityRow    = null;

/* ==========================================================================
   SMART SEARCH
   ========================================================================== */
$searchMatchedAs = '';

if ($searchTerm !== '') {

    $canAutoSelect = ($topicId <= 0 && $countryId <= 0 && $cityId <= 0 && $topicSlug === '' && $countrySlug === '');

    if ($canAutoSelect) {

        /* ---------- 1. Try country ---------- */
        $stmt = db()->prepare(
            "SELECT * FROM countries
             WHERE status = 'active'
               AND (LOWER(name) = LOWER(?) OR LOWER(name) LIKE LOWER(?))
             ORDER BY
               CASE WHEN LOWER(name) = LOWER(?) THEN 0 ELSE 1 END,
               name ASC
             LIMIT 1"
        );
        $stmt->execute([$searchTerm, $searchTerm . '%', $searchTerm]);
        $matchedCountry = $stmt->fetch() ?: null;

        if ($matchedCountry) {
            $countryRow      = $matchedCountry;
            $countryId       = (int) $matchedCountry['id'];
            $searchMatchedAs = 'country';
        }

        /* ---------- 2. Try city ---------- */
        if (!$countryRow) {
            $stmt = db()->prepare(
                "SELECT ci.*, co.name AS country_name, co.slug AS country_slug,
                        co.id AS country_id
                 FROM cities ci
                 JOIN countries co ON co.id = ci.country_id
                 WHERE ci.status = 'active'
                   AND co.status = 'active'
                   AND (LOWER(ci.name) = LOWER(?) OR LOWER(ci.name) LIKE LOWER(?))
                 ORDER BY
                   CASE WHEN LOWER(ci.name) = LOWER(?) THEN 0 ELSE 1 END,
                   ci.name ASC
                 LIMIT 1"
            );
            $stmt->execute([$searchTerm, $searchTerm . '%', $searchTerm]);
            $matchedCity = $stmt->fetch() ?: null;

            if ($matchedCity) {
                $cityRow    = $matchedCity;
                $cityId     = (int) $matchedCity['id'];
                $countryId  = (int) $matchedCity['country_id'];

                $stmt = db()->prepare(
                    "SELECT * FROM countries WHERE id = ? AND status = 'active' LIMIT 1"
                );
                $stmt->execute([$countryId]);
                $countryRow = $stmt->fetch() ?: null;

                $searchMatchedAs = 'city';
            }
        }

        /* ---------- 3. Try topic ---------- */
        if (!$countryRow && !$cityRow) {
            $stmt = db()->prepare(
                "SELECT * FROM categories
                 WHERE status = 'active'
                   AND (LOWER(name) = LOWER(?) OR LOWER(name) LIKE LOWER(?))
                 ORDER BY
                   CASE WHEN LOWER(name) = LOWER(?) THEN 0 ELSE 1 END,
                   name ASC
                 LIMIT 1"
            );
            $stmt->execute([$searchTerm, $searchTerm . '%', $searchTerm]);
            $matchedTopic = $stmt->fetch() ?: null;

            if ($matchedTopic) {
                $topicRow        = $matchedTopic;
                $topicId         = (int) $matchedTopic['id'];
                $searchMatchedAs = 'topic';
            }
        }

        /* ---------- 4. Fallback ---------- */
        if ($searchMatchedAs === '') {
            $searchMatchedAs = 'text';
        }
    } else {
        $searchMatchedAs = 'text';
    }
}

/* ==========================================================================
   RESOLVE SLUGS → IDs
   ========================================================================== */
if ($topicSlug !== '' && !$topicRow) {
    $stmt = db()->prepare(
        "SELECT * FROM categories WHERE slug = ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$topicSlug]);
    $topicRow = $stmt->fetch() ?: null;
    if ($topicRow) $topicId = (int) $topicRow['id'];
}

if ($countrySlug !== '' && !$countryRow) {
    $stmt = db()->prepare(
        "SELECT * FROM countries WHERE slug = ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$countrySlug]);
    $countryRow = $stmt->fetch() ?: null;
    if ($countryRow) $countryId = (int) $countryRow['id'];
}

if ($cityCountrySlug !== '' && $citySlug !== '' && !$cityRow) {
    $stmt = db()->prepare(
        "SELECT ci.*, co.name AS country_name, co.slug AS country_slug
         FROM cities ci
         JOIN countries co ON co.id = ci.country_id
         WHERE ci.slug = ?
           AND co.slug = ?
           AND ci.status = 'active'
           AND co.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$citySlug, $cityCountrySlug]);
    $cityRow = $stmt->fetch() ?: null;

    if ($cityRow) {
        $cityId    = (int) $cityRow['id'];
        $countryId = (int) $cityRow['country_id'];

        if (!$countryRow) {
            $stmt = db()->prepare(
                "SELECT * FROM countries WHERE id = ? AND status = 'active' LIMIT 1"
            );
            $stmt->execute([$countryId]);
            $countryRow = $stmt->fetch() ?: null;
        }
    }
}

/* Legacy city URL support */
if ($legacyCityKey !== '' && preg_match('/^(.+)-(\d+)$/', $legacyCityKey, $matches)) {
    $legacyCitySlug = strtolower(trim((string) $matches[1]));
    $legacyCityId   = (int) $matches[2];

    $stmt = db()->prepare(
        "SELECT ci.*, co.name AS country_name, co.slug AS country_slug
         FROM cities ci
         JOIN countries co ON co.id = ci.country_id
         WHERE ci.id = ?
           AND ci.slug = ?
           AND ci.status = 'active'
           AND co.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$legacyCityId, $legacyCitySlug]);
    $legacyCityRow = $stmt->fetch() ?: null;

    if ($legacyCityRow) {
        $target = url('/conferences/' . $legacyCityRow['country_slug'] . '/' . $legacyCityRow['slug']);
        header('Location: ' . $target, true, 301);
        exit;
    }
}

/* Query-string city filter */
if (!$cityRow && $cityId > 0) {
    $stmt = db()->prepare(
        "SELECT ci.*, co.name AS country_name, co.slug AS country_slug
         FROM cities ci
         JOIN countries co ON co.id = ci.country_id
         WHERE ci.id = ?
           AND ci.status = 'active'
           AND co.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([$cityId]);
    $cityRow = $stmt->fetch() ?: null;

    if ($cityRow) {
        if ($countryId <= 0) $countryId = (int) $cityRow['country_id'];

        if (!$countryRow) {
            $stmt = db()->prepare(
                "SELECT * FROM countries WHERE id = ? AND status = 'active' LIMIT 1"
            );
            $stmt->execute([(int) $cityRow['country_id']]);
            $countryRow = $stmt->fetch() ?: null;
        }
    }
}

if (
    $compactRouteInvalid ||
    ($topicFromSlug && !$topicRow) ||
    ($countryFromSlug && !$countryRow) ||
    ($cityFromSlug && !$cityRow)
) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

if ($topicId > 0 && !$topicRow) {
    $stmt = db()->prepare(
        "SELECT * FROM categories WHERE id = ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$topicId]);
    $topicRow = $stmt->fetch() ?: null;
    if (!$topicRow) $topicId = 0;
}

if ($countryId > 0 && !$countryRow) {
    $stmt = db()->prepare(
        "SELECT * FROM countries WHERE id = ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$countryId]);
    $countryRow = $stmt->fetch() ?: null;
    if (!$countryRow) {
        $countryId = 0;
        $cityId    = 0;
        $cityRow   = null;
    }
}

if ($cityRow && $countryId > 0 && (int) $cityRow['country_id'] !== $countryId) {
    $cityId  = 0;
    $cityRow = null;
}

/* Legacy SEO route redirects */
if (getq('legacy_seo') === '1') {
    $legacyPath = '/conferences';

    if ($topicRow && $cityRow) {
        $legacyPath = '/conferences/' . $topicRow['slug'] . '/' . $cityRow['country_slug'] . '/' . $cityRow['slug'];
    } elseif ($topicRow && $countryRow) {
        $legacyPath = '/conferences/' . $topicRow['slug'] . '/' . $countryRow['slug'];
    } elseif ($topicRow) {
        $legacyPath = '/conferences/' . $topicRow['slug'];
    } elseif ($cityRow) {
        $legacyPath = '/conferences/' . $cityRow['country_slug'] . '/' . $cityRow['slug'];
    } elseif ($countryRow) {
        $legacyPath = '/conferences/' . $countryRow['slug'];
    }

    header('Location: ' . url($legacyPath), true, 301);
    exit;
}

/* Numeric filter search redirects to clean SEO URL */
$isNumericLocationSearch =
    $searchTerm === '' &&
    !$topicFromSlug && !$countryFromSlug && !$cityFromSlug &&
    ($topicId > 0 || $countryId > 0 || $cityId > 0);

if ($isNumericLocationSearch) {
    $cleanPath = '/conferences';

    if ($topicRow && $cityRow) {
        $cleanPath = '/conferences/' . $topicRow['slug'] . '/' . $cityRow['country_slug'] . '/' . $cityRow['slug'];
    } elseif ($topicRow && $countryRow) {
        $cleanPath = '/conferences/' . $topicRow['slug'] . '/' . $countryRow['slug'];
    } elseif ($topicRow) {
        $cleanPath = '/conferences/' . $topicRow['slug'];
    } elseif ($cityRow) {
        $cleanPath = '/conferences/' . $cityRow['country_slug'] . '/' . $cityRow['slug'];
    } elseif ($countryRow) {
        $cleanPath = '/conferences/' . $countryRow['slug'];
    }

    $extraQuery = [];
    if ($attendance !== '') $extraQuery['attendance'] = $attendance;
    if ($status !== '')     $extraQuery['status']     = $status;
    if ($sort !== 'nearest') $extraQuery['sort']      = $sort;
    if ((int) getq('page', '1') > 1) $extraQuery['page'] = (int) getq('page', '1');

    $target = url($cleanPath);
    if ($extraQuery) $target .= '?' . http_build_query($extraQuery);

    header('Location: ' . $target, true, 302);
    exit;
}

/* ==========================================================================
   PUBLIC CONFERENCE QUERY
   ========================================================================== */
$where = [
    "c.status = 'approved'",
    'c.is_deactivated = 0',
    'c.end_date >= CURDATE()',
];
$params = [];

if ($topicId > 0)   { $where[] = 'c.category_id = ?'; $params[] = $topicId; }
if ($countryId > 0) { $where[] = 'c.country_id = ?';  $params[] = $countryId; }
if ($cityId > 0)    { $where[] = 'c.city_id = ?';     $params[] = $cityId; }
if ($attendance !== '') { $where[] = 'c.attendance_type = ?'; $params[] = $attendance; }

if ($status === 'upcoming') {
    $where[] = 'c.start_date > CURDATE()';
} elseif ($status === 'ongoing') {
    $where[] = 'c.start_date <= CURDATE() AND c.end_date >= CURDATE()';
}

if ($searchMatchedAs === 'text' && $searchTerm !== '') {
    $like = '%' . $searchTerm . '%';
    $where[] = '(
        c.title LIKE ?
        OR c.description LIKE ?
        OR c.venue LIKE ?
    )';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);

$stmt = db()->prepare(
    "SELECT COUNT(*)
     FROM conferences c
     LEFT JOIN categories ca ON ca.id = c.category_id
     LEFT JOIN countries  co ON co.id = c.country_id
     LEFT JOIN cities     ci ON ci.id = c.city_id
     WHERE {$whereSql}"
);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$pager = paginate($total, 50);

$orderSql = match ($sort) {
    'latest'     => 'c.start_date DESC, c.id DESC',
    'title-asc'  => 'c.title ASC, c.start_date ASC',
    'title-desc' => 'c.title DESC, c.start_date ASC',
    default => "
        CASE
            WHEN c.start_date = CURDATE() THEN 0
            WHEN CURDATE() BETWEEN c.start_date AND c.end_date THEN 1
            ELSE 2
        END ASC,
        CASE WHEN c.start_date >= CURDATE() THEN c.start_date ELSE c.end_date END ASC,
        c.start_date ASC,
        c.id DESC
    ",
};

$sql =
    "SELECT
        c.*,
        ca.name AS topic_name,
        co.name AS country_name,
        ci.name AS city_name,
        CASE
            WHEN CURDATE() > c.end_date THEN 'Completed'
            WHEN CURDATE() BETWEEN c.start_date AND c.end_date THEN 'Ongoing'
            ELSE 'Upcoming'
        END AS public_status
    FROM conferences c
    LEFT JOIN categories ca ON ca.id = c.category_id
    LEFT JOIN countries co ON co.id = c.country_id
    LEFT JOIN cities ci ON ci.id = c.city_id
    WHERE {$whereSql}
    ORDER BY {$orderSql}
    LIMIT ? OFFSET ?
";

$stmt = db()->prepare($sql);
$index = 1;
foreach ($params as $value) {
    $stmt->bindValue($index++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue($index++, $pager['per_page'], PDO::PARAM_INT);
$stmt->bindValue($index,   $pager['offset'],   PDO::PARAM_INT);
$stmt->execute();
$conferences = $stmt->fetchAll();

/* Filter options */
$categories = fetch_categories();
$countries  = fetch_countries();
$allCities  = fetch_cities();

/* ==========================================================================
   HEADING + SEO — powered by admin-editable templates
   ========================================================================== */
$canonical = url('/conferences');

$topicName        = $topicRow   ? trim((string) $topicRow['name'])         : '';
$countryName      = $countryRow ? trim((string) $countryRow['name'])       : '';
$cityName         = $cityRow    ? trim((string) $cityRow['name'])          : '';
$cityCountryName  = $cityRow    ? trim((string) $cityRow['country_name'])  : $countryName;

/* Resolve which template keys to use based on current filters */
$templateKeys = resolve_template_keys(
    $searchTerm,
    $topicName,
    $countryName,
    $cityName
);

/* Fetch the raw templates from site_content */
$headingTemplate     = get_template($templateKeys['heading']);
$descriptionTemplate = get_template($templateKeys['description']);
$listHeadingTemplate = get_template($templateKeys['list_heading'], 'All Conferences');

/* Context-aware URLs for {COUNTRY}, {CITY}, {TOPIC} placeholders */
$ctxTopicSlug   = $topicRow   ? (string) $topicRow['slug']   : '';
$ctxCountrySlug = $countryRow ? (string) $countryRow['slug'] : '';
$ctxCitySlug    = $cityRow    ? (string) $cityRow['slug']    : '';

$countryUrl = $ctxCountrySlug !== ''
    ? _build_context_url($ctxTopicSlug ?: null, $ctxCountrySlug, null)
    : '';

$cityUrl = ($ctxCitySlug !== '' && $ctxCountrySlug !== '')
    ? _build_context_url($ctxTopicSlug ?: null, $ctxCountrySlug, $ctxCitySlug)
    : '';

$topicUrl = $ctxTopicSlug !== ''
    ? _build_context_url($ctxTopicSlug, $ctxCountrySlug ?: null, null)
    : '';

/* ==========================================================================
   PLACEHOLDERS
   ==========================================================================
   Two versions:

     • Heading placeholders     → plain text, NO clickable links.
       Used for H1 (page heading) and H2 (list section heading).

     • Description placeholders → clickable links + bold.
       Used for the SEO description paragraph below the H1.
   ========================================================================== */

/* ---------- Heading placeholders (no links) ---------- */
$headingPlaceholders = [
    'COUNTRY'       => $countryName !== '' ? e($countryName) : '',
    'CITY'          => $cityName    !== '' ? e($cityName)    : '',
    'TOPIC'         => $topicName   !== '' ? e($topicName)   : '',
    'SEARCH'        => $searchTerm  !== '' ? e($searchTerm)  : '',
    'COUNT'         => e((string) $total),
    'TOP_TOPICS'    => '',
    'TOP_CITIES'    => '',
    'TOP_COUNTRIES' => '',
];

/* ---------- Description placeholders (links + bold) ---------- */
$descriptionPlaceholders = [
    'COUNTRY'       => $countryName !== ''
        ? template_link($countryName, $countryUrl)
        : '',

    'CITY'          => $cityName !== ''
        ? template_link($cityName, $cityUrl)
        : '',

    'TOPIC'         => $topicName !== ''
        ? template_link($topicName, $topicUrl)
        : '',

    'SEARCH'        => $searchTerm !== ''
        ? template_bold($searchTerm)
        : '',

    'COUNT'         => template_bold((string) $total),

    'TOP_TOPICS'    => template_link_list(fetch_top_topics($topicId, $countryId, $cityId, 10)),
    'TOP_CITIES'    => template_link_list(fetch_top_cities($topicId, $countryId, $cityId, 10)),
    'TOP_COUNTRIES' => template_link_list(fetch_top_countries($topicId, $countryId, $cityId, 10)),
];

/* Render */
$heading     = trim(render_template($headingTemplate,     $headingPlaceholders));
$description = trim(render_template($descriptionTemplate, $descriptionPlaceholders));
$listHeading = trim(render_template($listHeadingTemplate, $headingPlaceholders));

/* Safety fallbacks */
if ($heading === '') {
    $heading = 'International Conferences';
}
if ($description === '') {
    $description = 'Browse upcoming approved international conferences.';
}
if ($listHeading === '') {
    $listHeading = 'All Conferences';
}

/* Strip HTML for SEO meta description */
$descriptionText = trim(strip_tags($description));

/* Used by the breadcrumb + JSON-LD comparisons */
$DEFAULT_HEADING = trim(render_template(
    get_template('template_default_heading', 'International Conferences {YEAR}-{NEXT_YEAR}'),
    $descriptionPlaceholders
));

/* Canonical */
if ($topicFromSlug && $topicRow && $cityFromSlug && $cityRow) {
    $canonical = url('/conferences/' . $topicRow['slug'] . '/' . $cityRow['country_slug'] . '/' . $cityRow['slug']);
} elseif ($topicFromSlug && $topicRow && $countryFromSlug && $countryRow) {
    $canonical = url('/conferences/' . $topicRow['slug'] . '/' . $countryRow['slug']);
} elseif ($topicFromSlug && $topicRow) {
    $canonical = url('/conferences/' . $topicRow['slug']);
} elseif ($cityFromSlug && $cityRow) {
    $canonical = url('/conferences/' . $cityRow['country_slug'] . '/' . $cityRow['slug']);
} elseif ($countryFromSlug && $countryRow) {
    $canonical = url('/conferences/' . $countryRow['slug']);
}

$hasQueryFilter =
    $searchTerm !== '' ||
    (!$topicFromSlug && $topicId > 0) ||
    (!$countryFromSlug && !$cityFromSlug && $countryId > 0) ||
    (!$cityFromSlug && $cityId > 0) ||
    $attendance !== '' ||
    $status !== '' ||
    $sort !== 'nearest';

$robots = $hasQueryFilter ? 'noindex,follow' : 'index,follow,max-image-preview:large';

if ($pager['page'] > 1 && !$hasQueryFilter) {
    $canonical .= '?page=' . $pager['page'];
}

/* ==========================================================================
   PER-PAGE KEYWORDS — detect listing variant
   ========================================================================== */
$pageType = 'conferences_all';
$keywordVars = [];

if ($searchTerm !== '') {
    $pageType = 'conferences_all';
} elseif ($topicRow && $cityRow) {
    $pageType = 'topic_country_city';
    $keywordVars = [
        'topic'   => $topicName,
        'country' => $cityCountryName,
        'city'    => $cityName,
    ];
} elseif ($topicRow && $countryRow) {
    $pageType = 'topic_country';
    $keywordVars = [
        'topic'   => $topicName,
        'country' => $countryName,
    ];
} elseif ($topicRow) {
    $pageType = 'topic';
    $keywordVars = ['topic' => $topicName];
} elseif ($cityRow) {
    $pageType = 'city';
    $keywordVars = [
        'city'    => $cityName,
        'country' => $cityCountryName,
    ];
} elseif ($countryRow) {
    $pageType = 'country';
    $keywordVars = ['country' => $countryName];
}

$pageKeywords = page_keywords($pageType, $keywordVars);

/* ==========================================================================
   PER-PAGE COLLECTION PAGE SCHEMA
   ========================================================================== */
$collectionName = strip_tags($heading);
$collectionDesc = excerpt($descriptionText, 300);

$collectionLd = collection_schema(
    $collectionName,
    $canonical,
    $collectionDesc,
    $pageKeywords
);

$seo = page_seo([
    'title'       => strip_tags($heading),
    'description' => excerpt($descriptionText, 158),
    'keywords'    => $pageKeywords,
    'canonical'   => $canonical,
    'robots'      => $robots,
    'json_ld'     => [
        $collectionLd,
        breadcrumb_schema([
            ['name' => 'Home',        'url' => url('/')],
            ['name' => 'Conferences', 'url' => url('/conferences')],
            ...(strip_tags($heading) !== strip_tags($DEFAULT_HEADING)
                ? [['name' => strip_tags($heading), 'url' => $canonical]]
                : []),
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
        <li class="breadcrumb-item"><a href="<?= e(url('/conferences')) ?>">Conferences</a></li>
    <?php if (strip_tags($heading) !== strip_tags($DEFAULT_HEADING)): ?>
      <li class="breadcrumb-item active" aria-current="page"><?= strip_tags($heading) ?></li>
    <?php endif; ?>
      </ol>
    </nav>

    <div class="section-kicker">Conference Directory</div>
    <h1 class="display-5 mt-2"><?= $heading ?></h1>
    <p class="lead text-secondary mb-0"><?= $description ?></p>
  </div>
</section>

<!-- ==========================================================================
     CONFERENCE LIST
     ========================================================================== -->
<section class="py-5">
  <div class="container">

    <!-- Visible H2 for this page — editable per section from Admin → Website Content -->
    <h2 class="section-title h3 fw-bold mb-3">
      <?= $listHeading ?>
    </h2>

    <!-- Toggle + count -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <button type="button"
              class="btn btn-outline-dark conference-filter-toggle"
              data-bs-toggle="collapse"
              data-bs-target="#conferenceFilters"
              aria-expanded="true"
              aria-controls="conferenceFilters">
        <i class="bi bi-funnel me-1"></i> Filter
      </button>

      <div class="d-flex align-items-center gap-3">

        <p class="text-secondary mb-0 small">
          <strong class="text-dark"><?= number_format($total) ?></strong>
          conference<?= $total === 1 ? '' : 's' ?> found
        </p>

        <div class="view-toggle" role="group" aria-label="View mode">
          <button type="button"
                  class="view-toggle-btn"
                  data-view="list"
                  title="List view"
                  aria-label="List view">
            <i class="bi bi-list-ul"></i>
          </button>
          <button type="button"
                  class="view-toggle-btn"
                  data-view="grid"
                  title="Grid view"
                  aria-label="Grid view">
            <i class="bi bi-grid-3x3-gap-fill"></i>
          </button>
        </div>

      </div>
    </div>

    <!-- Filters -->
    <div id="conferenceFilters" class="collapse show mb-4">
      <form class="conference-filter-panel"
            action="<?= e(url('/conferences')) ?>"
            method="get">

        <div class="conference-filter-line">

          <div class="conference-filter-item">
            <label class="form-label" for="conferenceSearch">Search</label>
            <input type="search"
                   id="conferenceSearch"
                   class="form-control form-control-sm"
                   name="search"
                   placeholder="Title, topic, city…"
                   value="<?= e($searchTerm) ?>">
          </div>

          <div class="conference-filter-item">
            <label class="form-label" for="conferenceTopic">Topic</label>
            <select id="conferenceTopic" class="form-select form-select-sm" name="topic">
              <option value="">All Topics</option>
              <?php foreach ($categories as $row): ?>
                <option value="<?= (int) $row['id'] ?>" <?= $topicId === (int) $row['id'] ? 'selected' : '' ?>>
                  <?= e($row['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="conference-filter-item">
            <label class="form-label" for="conferenceCountry">Country</label>
            <select id="conferenceCountry"
                    class="form-select form-select-sm"
                    name="country"
                    data-conference-country>
              <option value="">All Countries</option>
              <?php foreach ($countries as $row): ?>
                <option value="<?= (int) $row['id'] ?>" <?= $countryId === (int) $row['id'] ? 'selected' : '' ?>>
                  <?= e($row['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="conference-filter-item" data-conference-city-wrap>
            <label class="form-label" for="conferenceCity">City</label>
            <select id="conferenceCity"
                    class="form-select form-select-sm"
                    name="city"
                    data-conference-city
                    <?= $countryId > 0 ? '' : 'disabled' ?>>
              <option value=""><?= $countryId > 0 ? 'All Cities' : 'Select country first' ?></option>
              <?php foreach ($allCities as $row): ?>
                <option value="<?= (int) $row['id'] ?>"
                        data-country="<?= (int) $row['country_id'] ?>"
                        <?= $cityId === (int) $row['id'] ? 'selected' : '' ?>>
                  <?= e($row['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="conference-filter-item">
            <label class="form-label" for="conferenceAttendance">Attendance</label>
            <select id="conferenceAttendance" class="form-select form-select-sm" name="attendance">
              <option value="">All Types</option>
              <?php foreach (['Offline', 'Online', 'Hybrid'] as $value): ?>
                <option value="<?= e($value) ?>" <?= $attendance === $value ? 'selected' : '' ?>>
                  <?= e($value) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="conference-filter-item">
            <label class="form-label" for="conferenceStatus">Status</label>
            <select id="conferenceStatus" class="form-select form-select-sm" name="status">
              <option value="">All Status</option>
              <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
              <option value="ongoing"  <?= $status === 'ongoing'  ? 'selected' : '' ?>>Ongoing</option>
            </select>
          </div>

          <div class="conference-filter-item">
            <label class="form-label" for="conferenceSort">Sort</label>
            <select id="conferenceSort" class="form-select form-select-sm" name="sort">
              <option value="nearest"    <?= $sort === 'nearest'    ? 'selected' : '' ?>>Nearest Date</option>
              <option value="latest"     <?= $sort === 'latest'     ? 'selected' : '' ?>>Latest Date</option>
              <option value="title-asc"  <?= $sort === 'title-asc'  ? 'selected' : '' ?>>Title A–Z</option>
              <option value="title-desc" <?= $sort === 'title-desc' ? 'selected' : '' ?>>Title Z–A</option>
            </select>
          </div>

          <div class="conference-filter-action">
            <button type="submit" class="btn btn-action btn-sm px-3">
              <i class="bi bi-check-lg me-1"></i> Apply
            </button>
          </div>

          <div class="conference-filter-action">
            <a href="<?= e(url('/conferences')) ?>" class="btn btn-outline-secondary btn-sm px-3">
              Reset
            </a>
          </div>

        </div>
      </form>
    </div>

    <!-- ==================== RESULTS ==================== -->
    <?php if ($conferences): ?>

      <div id="conferenceResults" class="conference-results">

        <!-- ============ LIST VIEW ============ -->
        <div class="conference-view conference-view-list" data-view-panel="list">
          <div class="conference-list-wrap reveal-stagger">

            <div class="conference-list-header">
              <div class="cl-col cl-col-conference">Conference</div>
              <div class="cl-col cl-col-topic">Topic</div>
              <div class="cl-col cl-col-date">Date</div>
              <div class="cl-col cl-col-location">Location</div>
              <div class="cl-col cl-col-status">Status</div>
              <div class="cl-col cl-col-action"></div>
            </div>

            <?php foreach ($conferences as $conference): ?>
              <?php
              $publicStatus = (string) $conference['public_status'];
              $statusClass  = $publicStatus === 'Ongoing' ? 'text-bg-success' : 'text-bg-primary';
              $location = trim(
                  (string) ($conference['city_name'] ?? '') . ', ' .
                  (string) ($conference['country_name'] ?? ''),
                  ', '
              );
              ?>

              <a class="conference-list-row"
                 href="<?= e(url('/conference/' . $conference['slug'])) ?>"
                 target="_blank"
                 rel="noopener">

                <div class="cl-col cl-col-conference">
                  <div class="cl-title"><?= e($conference['title']) ?></div>
                </div>

                <div class="cl-col cl-col-topic">
                  <span class="topic-badge cl-topic-badge"><?= e($conference['topic_name'] ?: 'General') ?></span>
                </div>

                <div class="cl-col cl-col-date">
                  <i class="bi bi-calendar3 cl-icon"></i>
                  <?= e(format_date($conference['start_date'])) ?>
                </div>

                <div class="cl-col cl-col-location">
                  <i class="bi bi-geo-alt cl-icon"></i>
                  <?= e($location ?: '—') ?>
                </div>

                <div class="cl-col cl-col-status">
                  <span class="badge <?= e($statusClass) ?>"><?= e($publicStatus) ?></span>
                </div>

                <div class="cl-col cl-col-action">
                  <i class="bi bi-arrow-right"></i>
                </div>

              </a>
            <?php endforeach; ?>

          </div>
        </div>

        <!-- ============ GRID VIEW ============ -->
        <div class="conference-view conference-view-grid" data-view-panel="grid">
          <div class="public-conference-grid reveal-stagger">

            <?php foreach ($conferences as $conference): ?>
              <?php
              $publicStatus = (string) $conference['public_status'];
              $statusClass  = $publicStatus === 'Ongoing' ? 'text-bg-success' : 'text-bg-primary';
              ?>

              <article class="public-conference-card">

                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <span class="topic-badge public-conference-topic"
                        title="<?= e($conference['topic_name'] ?: 'General') ?>">
                    <?= e($conference['topic_name'] ?: 'General') ?>
                  </span>
                  <span class="badge <?= e($statusClass) ?>"><?= e($publicStatus) ?></span>
                </div>

                <div class="mb-2">
                  <span class="badge text-bg-light border">
                    <i class="bi bi-people me-1"></i> <?= e($conference['attendance_type']) ?>
                  </span>
                </div>

                <!-- Card title — H3 to keep 1 H1 + 0 H2 on this page -->
                <h3 class="public-conference-title"><?= e($conference['title']) ?></h3>

                <p class="public-conference-description">
                  <?= e(excerpt((string) $conference['description'], 125)) ?>
                </p>

                <div class="conference-card-meta mb-3">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-calendar3 text-brand"></i>
                    <span>
                      <?= e(format_date($conference['start_date'])) ?>
                      <?php if (!empty($conference['end_date']) && $conference['end_date'] !== $conference['start_date']): ?>
                        <span class="text-secondary">→</span>
                        <?= e(format_date($conference['end_date'])) ?>
                      <?php endif; ?>
                    </span>
                  </div>

                  <?php
                  $location = trim(
                      (string) ($conference['city_name'] ?? '') . ', ' .
                      (string) ($conference['country_name'] ?? ''),
                      ', '
                  );
                  ?>
                  <?php if ($location !== ''): ?>
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-geo-alt text-brand"></i>
                      <span><?= e($location) ?></span>
                    </div>
                  <?php endif; ?>
                </div>

                <a href="<?= e(url('/conference/' . $conference['slug'])) ?>"
                   class="btn btn-outline-dark btn-sm w-100 mt-auto"
                   target="_blank"
                   rel="noopener">
                  View More <i class="bi bi-arrow-right ms-1"></i>
                </a>

              </article>
            <?php endforeach; ?>

          </div>
        </div>

      </div>

    <?php else: ?>

      <div class="empty-state bg-soft rounded-4">
        <i class="bi bi-search"></i>
        <!-- Empty state title — H3 to keep 1 H1 + 0 H2 on this page -->
        <h3 class="h5 mt-3">No conferences found</h3>
        <p class="mb-3">
          <?php if ($searchTerm !== ''): ?>
            No results match "<strong><?= e($searchTerm) ?></strong>". Try a different search term.
          <?php else: ?>
            Try changing or resetting the filters.
          <?php endif; ?>
        </p>
        <a href="<?= e(url('/conferences')) ?>" class="btn btn-action">Reset Filters</a>
      </div>

    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($pager['pages'] > 1): ?>
      <?php
      $paginationQuery = [];
      if ($searchTerm !== '')                  $paginationQuery['search']     = $searchTerm;
      if ($attendance !== '')                  $paginationQuery['attendance'] = $attendance;
      if ($status !== '')                      $paginationQuery['status']     = $status;
      if ($sort !== 'nearest')                 $paginationQuery['sort']       = $sort;

      $pageUrl = static function (int $pageNumber) use ($paginationQuery): string {
          $query = $paginationQuery;
          $query['page'] = $pageNumber;
          return '?' . http_build_query($query);
      };

      $currentPage = (int) $pager['page'];
      $totalPages  = (int) $pager['pages'];
      $startPage   = max(1, $currentPage - 2);
      $endPage     = min($totalPages, $currentPage + 2);
      ?>

      <nav class="mt-5" aria-label="Conference pagination">
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

          <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
            <li class="page-item <?= $pageNumber === $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="<?= e($pageUrl($pageNumber)) ?>"><?= $pageNumber ?></a>
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

  </div>
</section>

<!-- ==========================================================================
     PAGE-SPECIFIC STYLES
     ========================================================================== -->
<style>
.view-toggle {
  display: inline-flex;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  background: var(--color-white);
  overflow: hidden;
}

.view-toggle-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 36px;
  background: transparent;
  border: 0;
  color: var(--color-muted);
  font-size: 1.1rem;
  cursor: pointer;
  transition:
    background var(--dur-fast) var(--ease-standard),
    color var(--dur-fast) var(--ease-standard);
}

.view-toggle-btn:hover {
  background: var(--color-bg-soft);
  color: var(--color-text-strong);
}

.view-toggle-btn.active {
  background: var(--color-accent);
  color: #fff;
}

.conference-view { display: none; }
.conference-view.active { display: block; }

.conference-list-wrap {
  background: var(--color-white);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-xs);
  overflow: hidden;
}

.conference-list-header {
  display: grid;
  grid-template-columns: 2.5fr 1.2fr 1.2fr 1.5fr 1fr 44px;
  gap: 1rem;
  padding: 0.9rem 1.25rem;
  background: var(--color-bg-soft);
  border-bottom: 1px solid var(--color-border);
  font-family: var(--font-heading);
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-strong);
  line-height: 1;
}

.conference-list-header .cl-col {
  font-family: var(--font-heading);
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1;
  color: inherit;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  display: flex;
  align-items: center;
}

.conference-list-row {
  display: grid;
  grid-template-columns: 2.5fr 1.2fr 1.2fr 1.5fr 1fr 44px;
  gap: 1rem;
  align-items: center;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--color-border);
  color: inherit;
  text-decoration: none;
  transition:
    background var(--dur-fast) var(--ease-standard),
    border-color var(--dur-fast) var(--ease-standard);
}

.conference-list-row:last-child { border-bottom: 0; }

.conference-list-row:hover {
  background: rgba(37, 99, 235, 0.04);
  color: inherit;
}

.conference-list-row:hover .cl-col-action i {
  color: var(--color-accent);
  transform: translateX(4px);
}

.cl-col { min-width: 0; overflow-wrap: anywhere; }

.cl-title {
  font-family: var(--font-heading);
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--color-text-strong);
  line-height: 1.35;
}

.cl-topic-badge {
  font-size: 0.65rem;
  padding: 0.3em 0.7em;
}

.cl-col-date,
.cl-col-location {
  font-size: 0.875rem;
  color: var(--color-text);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.cl-icon {
  color: var(--color-accent);
  font-size: 0.85rem;
  flex-shrink: 0;
}

.cl-col-action {
  text-align: right;
  color: var(--color-muted);
}

.cl-col-action i {
  font-size: 1rem;
  transition: transform var(--dur-fast) var(--ease-standard), color var(--dur-fast) var(--ease-standard);
}

@media (max-width: 991.98px) {
  .conference-list-header,
  .conference-list-row {
    grid-template-columns: 2fr 1fr 1.2fr 1fr 40px;
  }
  .cl-col-topic { display: none; }
}

@media (max-width: 767.98px) {
  .conference-view-list { display: none !important; }
  .conference-view-grid { display: block !important; }
  .view-toggle { display: none; }
}

/* ============================================
   FIX: Let topic badges on conference grid
   cards wrap to 2 lines instead of being cut off
   ============================================ */
.public-conference-card .topic-badge.public-conference-topic {
  max-width: 100%;
  white-space: normal;
  overflow: visible;
  text-overflow: clip;
  font-size: 0.65rem;
  line-height: 1.2;
  padding: 0.28em 0.6em;
  text-align: center;
  word-break: break-word;
}

</style>

<script>
(function () {
  'use strict';

  var STORAGE_KEY = 'conference_view_preference';
  var MOBILE_BREAKPOINT = 768;

  var toggleBtns = document.querySelectorAll('.view-toggle-btn');
  var panels     = document.querySelectorAll('.conference-view');

  if (!toggleBtns.length || !panels.length) return;

  function getSavedView() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }

  function saveView(view) {
    try { localStorage.setItem(STORAGE_KEY, view); } catch (e) { }
  }

  function getDefaultView() {
    return window.innerWidth >= 992 ? 'list' : 'grid';
  }

  function applyView(view) {
    if (window.innerWidth < MOBILE_BREAKPOINT) view = 'grid';

    panels.forEach(function (panel) {
      panel.classList.toggle('active', panel.getAttribute('data-view-panel') === view);
    });

    toggleBtns.forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-view') === view);
    });
  }

  var saved = getSavedView();
  var initialView = saved || getDefaultView();
  applyView(initialView);

  toggleBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var view = btn.getAttribute('data-view');
      applyView(view);
      saveView(view);
    });
  });

  var resizeTimer = null;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      var savedView = getSavedView();
      var view = savedView || getDefaultView();
      applyView(view);
    }, 150);
  });

})();

</script>

<?php require __DIR__ . '/includes/footer.php'; ?>