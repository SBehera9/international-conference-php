<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$siteRoot = dirname(__DIR__);

$mainSitemapFile = 'sitemaps.xml';

$mainSitemapPath =
    $siteRoot . DIRECTORY_SEPARATOR . $mainSitemapFile;

$sessionKey = 'admin_generated_sitemap_package';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function sitemap_xml_escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_XML1,
        'UTF-8'
    );
}

function sitemap_date(?string $value): string
{
    if (!$value) {
        return date('Y-m-d');
    }

    $time = strtotime($value);

    return $time
        ? date('Y-m-d', $time)
        : date('Y-m-d');
}

function sitemap_add_url(
    array &$entries,
    string $path,
    string $lastmod,
    string $changefreq = 'weekly',
    string $priority = '0.6'
): void {

    $absoluteUrl = url($path);

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate URLs
    |--------------------------------------------------------------------------
    */

    $entries[$absoluteUrl] = [
        'loc'        => $absoluteUrl,
        'lastmod'    => $lastmod,
        'changefreq' => $changefreq,
        'priority'   => $priority,
    ];
}

function sitemap_build_urlset(array $entries): string
{
    $xml =
        '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

    $xml .=
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
        "\n";

    foreach ($entries as $entry) {

        $xml .= "  <url>\n";

        $xml .=
            '    <loc>' .
            sitemap_xml_escape((string) $entry['loc']) .
            "</loc>\n";

        $xml .=
            '    <lastmod>' .
            sitemap_xml_escape((string) $entry['lastmod']) .
            "</lastmod>\n";

        $xml .=
            '    <changefreq>' .
            sitemap_xml_escape((string) $entry['changefreq']) .
            "</changefreq>\n";

        /*
         * <priority> has been intentionally removed as requested.
         */

        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";

    return $xml;
}

function sitemap_build_index(array $files): string
{
    $xml =
        '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

    $xml .=
        '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' .
        "\n";

    foreach ($files as $fileName) {

        $xml .= "  <sitemap>\n";

        $xml .=
            '    <loc>' .
            sitemap_xml_escape(url('/' . $fileName)) .
            "</loc>\n";

        $xml .=
            '    <lastmod>' .
            date('Y-m-d') .
            "</lastmod>\n";

        $xml .= "  </sitemap>\n";
    }

    $xml .= "</sitemapindex>\n";

    return $xml;
}

/*
|--------------------------------------------------------------------------
| Refresh latest data
|--------------------------------------------------------------------------
|
| Refresh removes the previous generated package.
| The page then calculates everything again from the live database.
|--------------------------------------------------------------------------
*/

if (isset($_GET['refresh'])) {

    unset($_SESSION[$sessionKey]);

    redirect('/admin/sitemap-generator');
}

/*
|--------------------------------------------------------------------------
| Static public pages
|--------------------------------------------------------------------------
*/

$staticPages = [
    '/',
    '/conferences',
    '/organizers',
    '/blog',
    '/media-partner',
    '/our-associates',
    '/about-us',
    '/contact-us',
    '/feedback',
    '/feedback/all',
    '/privacy-policy',
    '/terms-of-service',
    '/faq',
];

/*
|--------------------------------------------------------------------------
| Fetch approved public conferences
|--------------------------------------------------------------------------
*/

$conferences = db()->query(
    "SELECT
        c.id,
        c.slug,
        c.updated_at,

        ca.slug AS topic_slug,

        co.slug AS country_slug,

        ci.slug AS city_slug

     FROM conferences c

     LEFT JOIN categories ca
        ON ca.id = c.category_id

     LEFT JOIN countries co
        ON co.id = c.country_id

     LEFT JOIN cities ci
        ON ci.id = c.city_id

     WHERE c.status = 'approved'
       AND c.is_deactivated = 0
       AND c.slug IS NOT NULL
       AND c.slug <> ''

     ORDER BY c.id ASC"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Topics
|--------------------------------------------------------------------------
*/

$topics = db()->query(
    "SELECT
        ca.id,
        ca.slug,
        ca.updated_at

     FROM categories ca

     WHERE ca.status = 'active'

       AND EXISTS (
           SELECT 1
           FROM conferences c
           WHERE c.category_id = ca.id
             AND c.status = 'approved'
             AND c.is_deactivated = 0
       )

     ORDER BY ca.name"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Countries
|--------------------------------------------------------------------------
*/

$countries = db()->query(
    "SELECT
        co.id,
        co.slug,
        co.updated_at

     FROM countries co

     WHERE co.status = 'active'

       AND EXISTS (
           SELECT 1
           FROM conferences c
           WHERE c.country_id = co.id
             AND c.status = 'approved'
             AND c.is_deactivated = 0
       )

     ORDER BY co.name"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Country + City
|--------------------------------------------------------------------------
*/

$countryCities = db()->query(
    "SELECT
        ci.id,
        ci.slug AS city_slug,
        co.slug AS country_slug,
        ci.updated_at

     FROM cities ci

     INNER JOIN countries co
        ON co.id = ci.country_id

     WHERE ci.status = 'active'
       AND co.status = 'active'

       AND EXISTS (
           SELECT 1
           FROM conferences c
           WHERE c.city_id = ci.id
             AND c.country_id = co.id
             AND c.status = 'approved'
             AND c.is_deactivated = 0
       )

     ORDER BY co.name, ci.name"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Topic + Country
|--------------------------------------------------------------------------
*/

$topicCountries = db()->query(
    "SELECT DISTINCT
        ca.slug AS topic_slug,
        co.slug AS country_slug,

        GREATEST(
            COALESCE(c.updated_at, '1970-01-01'),
            COALESCE(ca.updated_at, '1970-01-01'),
            COALESCE(co.updated_at, '1970-01-01')
        ) AS updated_at

     FROM conferences c

     INNER JOIN categories ca
        ON ca.id = c.category_id

     INNER JOIN countries co
        ON co.id = c.country_id

     WHERE c.status = 'approved'
       AND c.is_deactivated = 0
       AND ca.status = 'active'
       AND co.status = 'active'

     GROUP BY
        ca.slug,
        co.slug"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Topic + Country + City
|--------------------------------------------------------------------------
*/

$topicCountryCities = db()->query(
    "SELECT
        ca.slug AS topic_slug,
        co.slug AS country_slug,
        ci.slug AS city_slug,
        MAX(c.updated_at) AS updated_at

     FROM conferences c

     INNER JOIN categories ca
        ON ca.id = c.category_id

     INNER JOIN countries co
        ON co.id = c.country_id

     INNER JOIN cities ci
        ON ci.id = c.city_id

     WHERE c.status = 'approved'
       AND c.is_deactivated = 0
       AND ca.status = 'active'
       AND co.status = 'active'
       AND ci.status = 'active'

     GROUP BY
        ca.slug,
        co.slug,
        ci.slug"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Public organizers
|--------------------------------------------------------------------------
*/

$organizers = db()->query(
    "SELECT
        o.slug,
        o.updated_at

     FROM organizers o

     INNER JOIN users u
        ON u.id = o.user_id

     WHERE o.is_profile_complete = 1
       AND o.is_suspended = 0
       AND u.status = 'active'

       AND o.slug IS NOT NULL
       AND o.slug <> ''

       AND EXISTS (
           SELECT 1
           FROM conferences c
           WHERE c.organizer_id = o.id
             AND c.status = 'approved'
             AND c.is_deactivated = 0
       )

     ORDER BY o.organization_name"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Blog posts
|--------------------------------------------------------------------------
*/

$blogPosts = db()->query(
    "SELECT
        slug,
        updated_at

     FROM blog_posts

     WHERE slug IS NOT NULL
       AND slug <> ''

     ORDER BY created_at DESC"
)->fetchAll();

/*
|--------------------------------------------------------------------------
| Calculate card counts
|--------------------------------------------------------------------------
*/

$staticPagesCount = count($staticPages);

$conferenceCount = count($conferences);

$topicCount = count($topics);

$countryCount = count($countries);

$countryCityCount = count($countryCities);

$topicCountryCount = count($topicCountries);

$topicCountryCityCount = count($topicCountryCities);

$organizerCount = count($organizers);

$blogCount = count($blogPosts);

/*
|--------------------------------------------------------------------------
| Conference combination counts
|--------------------------------------------------------------------------
*/

$countryConferenceCount = 0;

$topicConferenceCount = 0;

$countryTopicConferenceCount = 0;

$countryCityConferenceCount = 0;

$countryCityTopicConferenceCount = 0;

foreach ($conferences as $conference) {

    $conferenceSlug =
        trim((string) ($conference['slug'] ?? ''));

    $topicSlug =
        trim((string) ($conference['topic_slug'] ?? ''));

    $countrySlug =
        trim((string) ($conference['country_slug'] ?? ''));

    $citySlug =
        trim((string) ($conference['city_slug'] ?? ''));

    if (
        $conferenceSlug !== '' &&
        $countrySlug !== ''
    ) {
        $countryConferenceCount++;
    }

    if (
        $conferenceSlug !== '' &&
        $topicSlug !== ''
    ) {
        $topicConferenceCount++;
    }

    if (
        $conferenceSlug !== '' &&
        $countrySlug !== '' &&
        $topicSlug !== ''
    ) {
        $countryTopicConferenceCount++;
    }

    if (
        $conferenceSlug !== '' &&
        $countrySlug !== '' &&
        $citySlug !== ''
    ) {
        $countryCityConferenceCount++;
    }

    if (
        $conferenceSlug !== '' &&
        $countrySlug !== '' &&
        $citySlug !== '' &&
        $topicSlug !== ''
    ) {
        $countryCityTopicConferenceCount++;
    }
}

/*
|--------------------------------------------------------------------------
| Cards
|--------------------------------------------------------------------------
*/

$countCards = [

    [
        'label' => 'Static Pages',
        'count' => $staticPagesCount,
        'icon'  => 'bi-file-earmark',
    ],

    [
        'label' => 'Conference Detail',
        'count' => $conferenceCount,
        'icon'  => 'bi-calendar-event',
    ],

    [
        'label' => 'Topic',
        'count' => $topicCount,
        'icon'  => 'bi-tags',
    ],

    [
        'label' => 'Country',
        'count' => $countryCount,
        'icon'  => 'bi-globe',
    ],

    [
        'label' => 'Country + City',
        'count' => $countryCityCount,
        'icon'  => 'bi-geo-alt',
    ],

    [
        'label' => 'Topic + Country',
        'count' => $topicCountryCount,
        'icon'  => 'bi-diagram-2',
    ],

    [
        'label' => 'Topic + Country + City',
        'count' => $topicCountryCityCount,
        'icon'  => 'bi-signpost-split',
    ],

    [
        'label' => 'Organizer',
        'count' => $organizerCount,
        'icon'  => 'bi-people',
    ],

    [
        'label' => 'Blog Posts',
        'count' => $blogCount,
        'icon'  => 'bi-journal-text',
    ],

    [
        'label' => 'Country + Conference',
        'count' => $countryConferenceCount,
        'icon'  => 'bi-globe-americas',
    ],

    [
        'label' => 'Topic + Conference',
        'count' => $topicConferenceCount,
        'icon'  => 'bi-tag',
    ],

    [
        'label' => 'Country + Topic + Conference',
        'count' => $countryTopicConferenceCount,
        'icon'  => 'bi-diagram-3',
    ],

    [
        'label' => 'Country + City + Conference',
        'count' => $countryCityConferenceCount,
        'icon'  => 'bi-geo',
    ],

    [
        'label' => 'Country + City + Topic + Conference',
        'count' => $countryCityTopicConferenceCount,
        'icon'  => 'bi-bezier2',
    ],
];

/*
|--------------------------------------------------------------------------
| Total URLs
|--------------------------------------------------------------------------
*/

$totalUrls = 0;

foreach ($countCards as $card) {
    $totalUrls += (int) $card['count'];
}

/*
|--------------------------------------------------------------------------
| Build sitemap package
|--------------------------------------------------------------------------
*/

function build_sitemap_package(
    array $staticPages,
    array $conferences,
    array $topics,
    array $countries,
    array $countryCities,
    array $topicCountries,
    array $topicCountryCities,
    array $organizers,
    array $blogPosts
): array {

    $entries = [];

    $today = date('Y-m-d');

    /*
    |--------------------------------------------------------------------------
    | Static
    |--------------------------------------------------------------------------
    */

    foreach ($staticPages as $path) {

        sitemap_add_url(
            $entries,
            $path,
            $today,
            'weekly',
            $path === '/' ? '1.0' : '0.6'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Conference detail + conference combinations
    |--------------------------------------------------------------------------
    */

    foreach ($conferences as $conference) {

        $conferenceSlug =
            trim((string) ($conference['slug'] ?? ''));

        if ($conferenceSlug === '') {
            continue;
        }

        $lastmod =
            sitemap_date(
                (string) ($conference['updated_at'] ?? '')
            );

        $topicSlug =
            trim((string) ($conference['topic_slug'] ?? ''));

        $countrySlug =
            trim((string) ($conference['country_slug'] ?? ''));

        $citySlug =
            trim((string) ($conference['city_slug'] ?? ''));

        /*
        | Normal conference URL
        */

        sitemap_add_url(
            $entries,
            '/conference/' . $conferenceSlug,
            $lastmod,
            'weekly',
            '0.9'
        );

        /*
        | Country + Conference
        */

        if ($countrySlug !== '') {

            sitemap_add_url(
                $entries,
                '/conferences/' .
                    $countrySlug . '/' .
                    $conferenceSlug,
                $lastmod,
                'weekly',
                '0.8'
            );
        }

        /*
        | Topic + Conference
        */

        if ($topicSlug !== '') {

            sitemap_add_url(
                $entries,
                '/conferences/' .
                    $topicSlug . '/' .
                    $conferenceSlug,
                $lastmod,
                'weekly',
                '0.8'
            );
        }

        /*
        | Country + Topic + Conference
        */

        if (
            $countrySlug !== '' &&
            $topicSlug !== ''
        ) {

            sitemap_add_url(
                $entries,
                '/conferences/' .
                    $countrySlug . '/' .
                    $topicSlug . '/' .
                    $conferenceSlug,
                $lastmod,
                'weekly',
                '0.8'
            );
        }

        /*
        | Country + City + Conference
        */

        if (
            $countrySlug !== '' &&
            $citySlug !== ''
        ) {

            sitemap_add_url(
                $entries,
                '/conferences/' .
                    $countrySlug . '/' .
                    $citySlug . '/' .
                    $conferenceSlug,
                $lastmod,
                'weekly',
                '0.8'
            );
        }

        /*
        | Country + City + Topic + Conference
        */

        if (
            $countrySlug !== '' &&
            $citySlug !== '' &&
            $topicSlug !== ''
        ) {

            sitemap_add_url(
                $entries,
                '/conferences/' .
                    $countrySlug . '/' .
                    $citySlug . '/' .
                    $topicSlug . '/' .
                    $conferenceSlug,
                $lastmod,
                'weekly',
                '0.8'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Topics
    |--------------------------------------------------------------------------
    */

    foreach ($topics as $row) {

        sitemap_add_url(
            $entries,
            '/conferences/' . $row['slug'],
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'weekly',
            '0.7'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Countries
    |--------------------------------------------------------------------------
    */

    foreach ($countries as $row) {

        sitemap_add_url(
            $entries,
            '/conferences/' . $row['slug'],
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'weekly',
            '0.7'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Country + City
    |--------------------------------------------------------------------------
    */

    foreach ($countryCities as $row) {

        sitemap_add_url(
            $entries,
            '/conferences/' .
                $row['country_slug'] . '/' .
                $row['city_slug'],
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'weekly',
            '0.7'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Topic + Country
    |--------------------------------------------------------------------------
    */

    foreach ($topicCountries as $row) {

        sitemap_add_url(
            $entries,
            '/conferences/' .
                $row['topic_slug'] . '/' .
                $row['country_slug'],
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'weekly',
            '0.7'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Topic + Country + City
    |--------------------------------------------------------------------------
    */

    foreach ($topicCountryCities as $row) {

        sitemap_add_url(
            $entries,
            '/conferences/' .
                $row['topic_slug'] . '/' .
                $row['country_slug'] . '/' .
                $row['city_slug'],
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'weekly',
            '0.7'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Organizers
    |--------------------------------------------------------------------------
    */

    foreach ($organizers as $row) {

        sitemap_add_url(
            $entries,
            '/organizers/' . $row['slug'],
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'weekly',
            '0.7'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Blog
    |--------------------------------------------------------------------------
    */

    foreach ($blogPosts as $row) {

        sitemap_add_url(
            $entries,
            '/blog/' . $row['slug'],
            sitemap_date(
                (string) ($row['updated_at'] ?? '')
            ),
            'monthly',
            '0.6'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Maximum 50,000 URLs per sitemap
    |--------------------------------------------------------------------------
    */

    $entries =
        array_values($entries);

    $chunks =
        array_chunk($entries, 50000);

    $files = [];

    /*
    |--------------------------------------------------------------------------
    | Single sitemap
    |--------------------------------------------------------------------------
    */

    if (count($chunks) <= 1) {

        $files['sitemaps.xml'] =
            sitemap_build_urlset($entries);

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_urls'   => count($entries),
            'split'        => false,
            'files'        => $files,
            'main_xml'     => $files['sitemaps.xml'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Multiple sitemap files
    |--------------------------------------------------------------------------
    */

    $childFileNames = [];

    foreach ($chunks as $index => $chunk) {

        $fileName =
            'sitemaps-' .
            ($index + 1) .
            '.xml';

        $files[$fileName] =
            sitemap_build_urlset($chunk);

        $childFileNames[] =
            $fileName;
    }

    $files['sitemaps.xml'] =
        sitemap_build_index($childFileNames);

    return [
        'generated_at' => date('Y-m-d H:i:s'),
        'total_urls'   => count($entries),
        'split'        => true,
        'files'        => $files,
        'main_xml'     => $files['sitemaps.xml'],
    ];
}

/*
|--------------------------------------------------------------------------
| POST actions
|--------------------------------------------------------------------------
*/

if (is_post()) {

    verify_csrf();

    $action =
        strtolower(trim(post('action')));

    /*
    |--------------------------------------------------------------------------
    | Generate
    |--------------------------------------------------------------------------
    */

    if ($action === 'generate') {

        $package = build_sitemap_package(
            $staticPages,
            $conferences,
            $topics,
            $countries,
            $countryCities,
            $topicCountries,
            $topicCountryCities,
            $organizers,
            $blogPosts
        );

        $_SESSION[$sessionKey] =
            $package;

        audit_log(
            'SITEMAP_GENERATED',
            'Generated sitemap with ' .
                number_format(
                    (int) $package['total_urls']
                ) .
                ' URLs.'
        );

        flash(
            'success',
            'Sitemap generated successfully. Total URLs: ' .
                number_format(
                    (int) $package['total_urls']
                )
        );

        redirect('/admin/sitemap-generator');
    }

    /*
    |--------------------------------------------------------------------------
    | Download
    |--------------------------------------------------------------------------
    */

    if ($action === 'download') {

        $package =
            $_SESSION[$sessionKey] ?? null;

        if (
            !is_array($package) ||
            empty($package['main_xml'])
        ) {
            flash(
                'danger',
                'Generate the sitemap first.'
            );

            redirect('/admin/sitemap-generator');
        }

        header(
            'Content-Type: application/xml; charset=UTF-8'
        );

        header(
            'Content-Disposition: attachment; filename="sitemaps.xml"'
        );

        header('Cache-Control: no-store');

        echo (string) $package['main_xml'];
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Save / Update live sitemap
    |--------------------------------------------------------------------------
    */

    if ($action === 'save') {

        $package =
            $_SESSION[$sessionKey] ?? null;

        if (
            !is_array($package) ||
            empty($package['files']) ||
            !is_array($package['files'])
        ) {

            flash(
                'danger',
                'Generate the sitemap first.'
            );

            redirect('/admin/sitemap-generator');
        }

        /*
        |--------------------------------------------------------------------------
        | Remove old split sitemap files only
        |--------------------------------------------------------------------------
        */

        foreach (
            glob(
                $siteRoot .
                DIRECTORY_SEPARATOR .
                'sitemaps-*.xml'
            ) ?: []
            as $oldFile
        ) {

            if (
                is_file($oldFile) &&
                preg_match(
                    '/sitemaps-\d+\.xml$/',
                    basename($oldFile)
                )
            ) {
                @unlink($oldFile);
            }
        }

        $savedFiles = 0;

        foreach (
            $package['files']
            as $fileName => $contents
        ) {

            $target =
                $siteRoot .
                DIRECTORY_SEPARATOR .
                basename((string) $fileName);

            $bytes =
                @file_put_contents(
                    $target,
                    (string) $contents,
                    LOCK_EX
                );

            if ($bytes === false) {

                flash(
                    'danger',
                    'Unable to write ' .
                        basename((string) $fileName) .
                        '. Check file/folder permissions.'
                );

                redirect('/admin/sitemap-generator');
            }

            $savedFiles++;
        }

        audit_log(
            'SITEMAP_SAVED',
            'Saved sitemap files: ' .
                $savedFiles
        );

        flash(
            'success',
            $savedFiles === 1
                ? 'sitemaps.xml updated successfully.'
                : $savedFiles .
                    ' sitemap files saved successfully.'
        );

        redirect('/admin/sitemap-generator');
    }
}

/*
|--------------------------------------------------------------------------
| Existing generated package
|--------------------------------------------------------------------------
*/

$generatedPackage =
    $_SESSION[$sessionKey] ?? null;

$hasGenerated =
    is_array($generatedPackage) &&
    !empty($generatedPackage['main_xml']);

$generatedXml =
    $hasGenerated
        ? (string) $generatedPackage['main_xml']
        : '';

$generatedAt =
    $hasGenerated
        ? (string) ($generatedPackage['generated_at'] ?? '')
        : '';

$generatedUrlCount =
    $hasGenerated
        ? (int) ($generatedPackage['total_urls'] ?? 0)
        : 0;

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

$dashboardTitle = 'Sitemap Generator';

$dashboardRole = 'admin';

require dirname(__DIR__) .
    '/includes/dashboard-header.php';
?>

<style>
.sitemap-summary-card {
    height: 100%;
    min-height: 135px;
    border: 1px solid #e7ebf0;
    border-radius: 16px;
    background: #fff;
    padding: 18px;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .035);
}

.sitemap-summary-icon {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #f1f6ff;
    color: #155dfc;
    font-size: 1.15rem;
}

.sitemap-summary-label {
    margin-top: 15px;
    color: #667085;
    font-size: .82rem;
    line-height: 1.35;
}

.sitemap-summary-count {
    margin-top: 3px;
    font-size: 1.7rem;
    line-height: 1;
    font-weight: 800;
    color: #101828;
}

.sitemap-total-card {
    border-radius: 18px;
    background: #0a2540;
    color: #fff;
    padding: 24px;
}

.sitemap-total-card .total-number {
    font-size: 2.6rem;
    line-height: 1;
    font-weight: 800;
}

.sitemap-xml-preview {
    width: 100%;
    min-height: 420px;
    max-height: 600px;
    overflow: auto;
    margin: 0;
    padding: 18px;
    border-radius: 12px;
    background: #0f172a;
    color: #e2e8f0;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: .78rem;
}
</style>


<div class="container-fluid px-0">

    <!-- =========================================================
         PAGE HEADER
         ========================================================= -->

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">

        <div>

            <h2 class="h4 fw-bold mb-1">
                Sitemap Generator
            </h2>

            <p class="text-secondary mb-0">
                Generate all public website URLs directly from the latest database records.
            </p>

        </div>


        <a
            href="<?= e(url('/admin/sitemap-generator?refresh=1')) ?>"
            class="btn btn-outline-primary"
        >
            <i class="bi bi-arrow-clockwise me-1"></i>
            Refresh Data
        </a>

    </div>


    <!-- =========================================================
         TOTAL
         ========================================================= -->

    <div class="sitemap-total-card mb-4">

        <div class="row align-items-center g-3">

            <div class="col-md">

                <div class="small opacity-75 mb-2">
                    Total URLs Currently Detected
                </div>

                <div class="total-number">
                    <?= number_format($totalUrls) ?>
                </div>

            </div>

            <div class="col-md-auto">

                <div class="small opacity-75">
                    Live database calculation
                </div>

                <div class="fw-semibold">
                    <?= e(date('d M Y, h:i A')) ?>
                </div>

            </div>

        </div>

    </div>


    <!-- =========================================================
         COUNT CARDS
         ========================================================= -->

    <div class="row g-3 mb-4">

        <?php foreach ($countCards as $card): ?>

            <div class="col-6 col-md-4 col-xl-3">

                <div class="sitemap-summary-card">

                    <div class="sitemap-summary-icon">

                        <i class="bi <?= e($card['icon']) ?>"></i>

                    </div>

                    <div class="sitemap-summary-label">
                        <?= e($card['label']) ?>
                    </div>

                    <div class="sitemap-summary-count">
                        <?= number_format((int) $card['count']) ?>
                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- =========================================================
         GENERATOR
         ========================================================= -->

    <div class="card border-0 shadow-sm mb-4">

        <div class="card-body p-4">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

                <div>

                    <h3 class="h5 fw-bold mb-1">
                        Generate XML Sitemap
                    </h3>

                    <p class="text-secondary mb-0">
                        Includes standard pages, conference combinations, organizers, topics, countries and cities.
                    </p>

                </div>


                <?php if (!$hasGenerated): ?>

                    <form
                        method="post"
                        id="generateSitemapForm"
                    >

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="generate"
                        >

                        <button
                            type="submit"
                            class="btn btn-primary px-4"
                            id="generateSitemapBtn"
                        >

                            <i class="bi bi-lightning-charge me-1"></i>

                            Generate Sitemap

                        </button>

                    </form>

                <?php else: ?>

                    <span class="badge text-bg-success fs-6 px-3 py-2">

                        <i class="bi bi-check-circle me-1"></i>

                        Generated

                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =========================================================
         GENERATED RESULT
         ========================================================= -->

    <?php if ($hasGenerated): ?>

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body p-4">

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">

                    <div>

                        <h3 class="h5 fw-bold mb-1">
                            Sitemap Generated Successfully
                        </h3>

                        <div class="text-secondary small">

                            Total URLs:
                            <strong>
                                <?= number_format($generatedUrlCount) ?>
                            </strong>

                            <?php if ($generatedAt !== ''): ?>

                                &nbsp;•&nbsp;

                                Generated:
                                <strong>
                                    <?= e(date(
                                        'd M Y, h:i A',
                                        strtotime($generatedAt)
                                    )) ?>
                                </strong>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <div class="d-flex flex-wrap gap-2 mb-4">

                    <button
                        type="button"
                        class="btn btn-outline-dark"
                        data-bs-toggle="collapse"
                        data-bs-target="#sitemapPreview"
                    >
                        <i class="bi bi-eye me-1"></i>
                        Preview XML
                    </button>


                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        id="copySitemapBtn"
                    >
                        <i class="bi bi-copy me-1"></i>
                        Copy XML
                    </button>


                    <form method="post">

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="download"
                        >

                        <button
                            class="btn btn-outline-secondary"
                            type="submit"
                        >
                            <i class="bi bi-download me-1"></i>
                            Download
                        </button>

                    </form>


                    <form method="post">

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="save"
                        >

                        <button
                            class="btn btn-success"
                            type="submit"
                            data-confirm="Save and update the live sitemap files now?"
                        >
                            <i class="bi bi-cloud-arrow-up me-1"></i>
                            Save / Update sitemaps.xml
                        </button>

                    </form>

                </div>


                <div
                    class="collapse"
                    id="sitemapPreview"
                >

                    <pre class="sitemap-xml-preview"><?= e($generatedXml) ?></pre>

                </div>


                <textarea
                    id="sitemapCopySource"
                    class="d-none"
                ><?= e($generatedXml) ?></textarea>

            </div>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         INFORMATION
         ========================================================= -->

    <div class="card border-0 shadow-sm">

        <div class="card-body p-4">

            <div class="row g-4">

                <div class="col-md-6">

                    <div class="small text-secondary mb-1">
                        Sitemap File
                    </div>

                    <code>
                        /public_html/sitemaps.xml
                    </code>

                </div>

                <div class="col-md-6">

                    <div class="small text-secondary mb-1">
                        Public Sitemap URL
                    </div>

                    <code class="text-break">
                        https://internationalconference.info/sitemaps.xml
                    </code>

                </div>

            </div>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Generate loading state
    |--------------------------------------------------------------------------
    */

    const generateForm =
        document.getElementById('generateSitemapForm');

    const generateButton =
        document.getElementById('generateSitemapBtn');

    if (generateForm && generateButton) {

        generateForm.addEventListener('submit', function () {

            generateButton.disabled = true;

            generateButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Generating...';

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Copy XML
    |--------------------------------------------------------------------------
    */

    const copyButton =
        document.getElementById('copySitemapBtn');

    const copySource =
        document.getElementById('sitemapCopySource');

    if (copyButton && copySource) {

        copyButton.addEventListener('click', async function () {

            try {

                await navigator.clipboard.writeText(
                    copySource.value
                );

                const oldHtml =
                    copyButton.innerHTML;

                copyButton.innerHTML =
                    '<i class="bi bi-check2 me-1"></i>Copied';

                copyButton.classList.remove(
                    'btn-outline-primary'
                );

                copyButton.classList.add(
                    'btn-success'
                );

                setTimeout(function () {

                    copyButton.innerHTML =
                        oldHtml;

                    copyButton.classList.remove(
                        'btn-success'
                    );

                    copyButton.classList.add(
                        'btn-outline-primary'
                    );

                }, 1800);

            } catch (error) {

                copySource.classList.remove('d-none');

                copySource.select();

                document.execCommand('copy');

                copySource.classList.add('d-none');

            }

        });

    }

});
</script>


<?php
require dirname(__DIR__) .
    '/includes/dashboard-footer.php';