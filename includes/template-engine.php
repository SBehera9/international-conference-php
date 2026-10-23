<?php
declare(strict_types=1);

/**
 * Template Engine — renders admin-editable templates with placeholders.
 *
 * Supported placeholders:
 *   {COUNTRY}        → Link: country name
 *   {CITY}           → Link: city name
 *   {TOPIC}          → Link: topic name
 *   {SEARCH}         → Bold text (search term)
 *   {YEAR}           → Bold text (current year)
 *   {NEXT_YEAR}      → Bold text (next year)
 *   {COUNT}          → Bold text (results count)
 *   {TOP_TOPICS}     → Links: top 10 topics in current context
 *   {TOP_CITIES}     → Links: top 10 cities in current context
 *   {TOP_COUNTRIES}  → Links: top 10 countries in current context
 */

/* ==========================================================================
   LINK HELPERS — build safe HTML
   ========================================================================== */

/**
 * Render a link (opens in new tab).
 */
function template_link(string $label, string $url): string
{
    $label = trim($label);
    if ($label === '' || $url === '') return '';

    return '<a href="' . e($url) . '" '
         . 'target="_blank" rel="noopener" '
         . 'class="template-link">'
         . e($label)
         . '</a>';
}

/**
 * Render bold text (no link).
 */
function template_bold(string $text): string
{
    return '<strong class="template-bold">' . e($text) . '</strong>';
}

/**
 * Render a comma-separated list of links.
 */
function template_link_list(array $items): string
{
    if (!$items) return '';

    $parts = array_map(
        static fn($item) => template_link($item['label'], $item['url']),
        $items
    );

    return implode(', ', array_filter($parts));
}

/* ==========================================================================
   TEMPLATE RENDERER
   ========================================================================== */

/**
 * Render template with placeholders (HTML-safe).
 */
function render_template(string $template, array $htmlPlaceholders = []): string
{
    if (!isset($htmlPlaceholders['YEAR'])) {
        $htmlPlaceholders['YEAR'] = template_bold((string) date('Y'));
    }
    if (!isset($htmlPlaceholders['NEXT_YEAR'])) {
        $htmlPlaceholders['NEXT_YEAR'] = template_bold((string) (date('Y') + 1));
    }

    $search  = [];
    $replace = [];

    foreach ($htmlPlaceholders as $key => $html) {
        $search[]  = '{' . strtoupper($key) . '}';
        $replace[] = (string) $html;
    }

    return str_replace($search, $replace, $template);
}

/**
 * Get a template from site_content, with a default fallback.
 */
function get_template(string $key, string $fallback = ''): string
{
    return content($key, $fallback);
}

/* ==========================================================================
   CONTEXT HELPERS — new
   ========================================================================== */

/**
 * Look up a slug from a whitelisted table by ID.
 */
function _lookup_slug(string $table, int $id): string
{
    if ($id <= 0) return '';

    $allowed = ['countries', 'cities', 'categories'];
    if (!in_array($table, $allowed, true)) return '';

    try {
        $stmt = db()->prepare("SELECT slug FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $slug = $stmt->fetchColumn();
        return $slug !== false ? (string) $slug : '';
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Build a context-aware compact SEO URL.
 *
 * Patterns:
 *   /conferences
 *   /conferences/{topic}
 *   /conferences/{country}
 *   /conferences/{country}/{city}
 *   /conferences/{topic}/{country}
 *   /conferences/{topic}/{country}/{city}
 *
 * IMPORTANT: Cities only make sense with a country. Topic must come first
 * when present, per the router rules in .htaccess.
 */
function _build_context_url(
    ?string $topicSlug   = null,
    ?string $countrySlug = null,
    ?string $citySlug    = null
): string {
    $parts = [];

    if ($topicSlug !== null && $topicSlug !== '') {
        $parts[] = $topicSlug;

        if ($countrySlug !== null && $countrySlug !== '') {
            $parts[] = $countrySlug;

            if ($citySlug !== null && $citySlug !== '') {
                $parts[] = $citySlug;
            }
        }
    } elseif ($countrySlug !== null && $countrySlug !== '') {
        $parts[] = $countrySlug;

        if ($citySlug !== null && $citySlug !== '') {
            $parts[] = $citySlug;
        }
    }

    if (empty($parts)) {
        return url('/conferences');
    }

    return url('/conferences/' . implode('/', $parts));
}

/* ==========================================================================
   CONTEXT-AWARE FETCHERS
   ========================================================================== */

/**
 * Build WHERE + params based on the current context.
 */
function _context_where(
    ?int $topicId   = null,
    ?int $countryId = null,
    ?int $cityId    = null,
    ?string $excludeDimension = null
): array {
    $where  = [
        "c.status = 'approved'",
        "c.is_deactivated = 0",
        "c.end_date >= CURDATE()",
    ];
    $params = [];

    if ($excludeDimension !== 'topic' && $topicId !== null && $topicId > 0) {
        $where[]  = 'c.category_id = ?';
        $params[] = $topicId;
    }
    if ($excludeDimension !== 'country' && $countryId !== null && $countryId > 0) {
        $where[]  = 'c.country_id = ?';
        $params[] = $countryId;
    }
    if ($excludeDimension !== 'city' && $cityId !== null && $cityId > 0) {
        $where[]  = 'c.city_id = ?';
        $params[] = $cityId;
    }

    return [implode(' AND ', $where), $params];
}

/**
 * Fetch top N topics in the current context.
 * URLs preserve the current country + city context.
 */
function fetch_top_topics(
    ?int $topicId   = null,
    ?int $countryId = null,
    ?int $cityId    = null,
    int  $limit     = 10
): array {
    $countrySlug = _lookup_slug('countries', (int) ($countryId ?? 0));
    $citySlug    = _lookup_slug('cities',    (int) ($cityId    ?? 0));

    /* If already filtered by a single topic, only that topic is relevant */
    if ($topicId !== null && $topicId > 0) {
        try {
            $stmt = db()->prepare(
                "SELECT name, slug FROM categories WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$topicId]);
            $row = $stmt->fetch();
            if ($row) {
                return [[
                    'label' => (string) $row['name'],
                    'url'   => _build_context_url($row['slug'], $countrySlug, $citySlug),
                ]];
            }
        } catch (Throwable $e) {
            return [];
        }
        return [];
    }

    try {
        [$whereSql, $params] = _context_where($topicId, $countryId, $cityId);

        $stmt = db()->prepare(
            "SELECT ca.name, ca.slug, COUNT(c.id) AS cnt
             FROM conferences c
             INNER JOIN categories ca ON ca.id = c.category_id
             WHERE {$whereSql}
               AND c.category_id IS NOT NULL
             GROUP BY ca.id, ca.name, ca.slug
             ORDER BY cnt DESC, ca.name ASC
             LIMIT " . (int) $limit
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(static fn($r) => [
            'label' => (string) $r['name'],
            'url'   => _build_context_url($r['slug'], $countrySlug, $citySlug),
        ], $rows);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Fetch top N cities in the current context.
 * URLs preserve the current topic + country context.
 */
function fetch_top_cities(
    ?int $topicId   = null,
    ?int $countryId = null,
    ?int $cityId    = null,
    int  $limit     = 10
): array {
    $topicSlug = _lookup_slug('categories', (int) ($topicId ?? 0));

    /* If already filtered by a single city, only that city is relevant */
    if ($cityId !== null && $cityId > 0) {
        try {
            $stmt = db()->prepare(
                "SELECT ci.name, ci.slug, co.slug AS country_slug
                 FROM cities ci
                 INNER JOIN countries co ON co.id = ci.country_id
                 WHERE ci.id = ? LIMIT 1"
            );
            $stmt->execute([$cityId]);
            $row = $stmt->fetch();
            if ($row) {
                return [[
                    'label' => (string) $row['name'],
                    'url'   => _build_context_url($topicSlug, $row['country_slug'], $row['slug']),
                ]];
            }
        } catch (Throwable $e) {
            return [];
        }
        return [];
    }

    try {
        [$whereSql, $params] = _context_where($topicId, $countryId, $cityId);

        $stmt = db()->prepare(
            "SELECT ci.name, ci.slug, co.slug AS country_slug, COUNT(c.id) AS cnt
             FROM conferences c
             INNER JOIN cities ci ON ci.id = c.city_id
             INNER JOIN countries co ON co.id = ci.country_id
             WHERE {$whereSql}
               AND c.city_id IS NOT NULL
             GROUP BY ci.id, ci.name, ci.slug, co.slug
             ORDER BY cnt DESC, ci.name ASC
             LIMIT " . (int) $limit
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(static fn($r) => [
            'label' => (string) $r['name'],
            'url'   => _build_context_url($topicSlug, $r['country_slug'], $r['slug']),
        ], $rows);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Fetch top N countries in the current context.
 * URLs preserve the current topic context.
 */
function fetch_top_countries(
    ?int $topicId   = null,
    ?int $countryId = null,
    ?int $cityId    = null,
    int  $limit     = 10
): array {
    $topicSlug = _lookup_slug('categories', (int) ($topicId ?? 0));

    /* If already filtered by a single country, only that country is relevant */
    if ($countryId !== null && $countryId > 0) {
        try {
            $stmt = db()->prepare(
                "SELECT name, slug FROM countries WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$countryId]);
            $row = $stmt->fetch();
            if ($row) {
                return [[
                    'label' => (string) $row['name'],
                    'url'   => _build_context_url($topicSlug, $row['slug'], null),
                ]];
            }
        } catch (Throwable $e) {
            return [];
        }
        return [];
    }

    try {
        [$whereSql, $params] = _context_where($topicId, $countryId, $cityId);

        $stmt = db()->prepare(
            "SELECT co.name, co.slug, COUNT(c.id) AS cnt
             FROM conferences c
             INNER JOIN countries co ON co.id = c.country_id
             WHERE {$whereSql}
               AND c.country_id IS NOT NULL
             GROUP BY co.id, co.name, co.slug
             ORDER BY cnt DESC, co.name ASC
             LIMIT " . (int) $limit
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(static fn($r) => [
            'label' => (string) $r['name'],
            'url'   => _build_context_url($topicSlug, $r['slug'], null),
        ], $rows);
    } catch (Throwable $e) {
        return [];
    }
}

/* ==========================================================================
   TEMPLATE RESOLVER
   ========================================================================== */
function resolve_template_keys(
    string $searchTerm,
    string $topicName,
    string $countryName,
    string $cityName
): array {
    if ($searchTerm !== '') {
        return [
            'heading'      => 'template_search_heading',
            'description'  => 'template_search_description',
            'list_heading' => 'template_search_list_heading',
        ];
    }

    $hasTopic   = $topicName   !== '';
    $hasCountry = $countryName !== '';
    $hasCity    = $cityName    !== '';

    if ($hasTopic && $hasCity && $hasCountry) {
        return [
            'heading'      => 'template_country_city_topic_heading',
            'description'  => 'template_country_city_topic_description',
            'list_heading' => 'template_country_city_topic_list_heading',
        ];
    }

    if ($hasTopic && $hasCountry) {
        return [
            'heading'      => 'template_country_topic_heading',
            'description'  => 'template_country_topic_description',
            'list_heading' => 'template_country_topic_list_heading',
        ];
    }

    if ($hasTopic) {
        return [
            'heading'      => 'template_topic_heading',
            'description'  => 'template_topic_description',
            'list_heading' => 'template_topic_list_heading',
        ];
    }

    if ($hasCity && $hasCountry) {
        return [
            'heading'      => 'template_country_city_heading',
            'description'  => 'template_country_city_description',
            'list_heading' => 'template_country_city_list_heading',
        ];
    }

    if ($hasCity) {
        return [
            'heading'      => 'template_city_heading',
            'description'  => 'template_city_description',
            'list_heading' => 'template_city_list_heading',
        ];
    }

    if ($hasCountry) {
        return [
            'heading'      => 'template_country_heading',
            'description'  => 'template_country_description',
            'list_heading' => 'template_country_list_heading',
        ];
    }

    return [
        'heading'      => 'template_default_heading',
        'description'  => 'template_default_description',
        'list_heading' => 'template_list_heading',
    ];
}