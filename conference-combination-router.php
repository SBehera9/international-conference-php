<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$r1 = strtolower(trim((string) getq('r1')));
$r2 = strtolower(trim((string) getq('r2')));
$r3 = strtolower(trim((string) getq('r3')));
$r4 = strtolower(trim((string) getq('r4')));

$segments = array_values(array_filter(
    [$r1, $r2, $r3, $r4],
    static fn ($value) => $value !== ''
));

$segmentCount = count($segments);

/*
|--------------------------------------------------------------------------
| Only 2, 3 and 4 segment routes come through this resolver
|--------------------------------------------------------------------------
*/

if ($segmentCount < 2 || $segmentCount > 4) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| Last segment must be conference slug
|--------------------------------------------------------------------------
*/

$conferenceSlug = $segments[$segmentCount - 1];

/*
|--------------------------------------------------------------------------
| Find approved public conference + its REAL relationships
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare(
    "SELECT
        c.id,
        c.slug,
        c.category_id,
        c.country_id,
        c.city_id,

        ca.name AS topic_name,
        ca.slug AS topic_slug,

        co.name AS country_name,
        co.slug AS country_slug,

        ci.name AS city_name,
        ci.slug AS city_slug

     FROM conferences c

     LEFT JOIN categories ca
        ON ca.id = c.category_id

     LEFT JOIN countries co
        ON co.id = c.country_id

     LEFT JOIN cities ci
        ON ci.id = c.city_id
       AND ci.country_id = c.country_id

     WHERE c.slug = ?
       AND c.status = 'approved'
       AND c.is_deactivated = 0

     LIMIT 1"
);

$stmt->execute([$conferenceSlug]);

$conference = $stmt->fetch() ?: null;

/*
|--------------------------------------------------------------------------
| Conference-combination information
|--------------------------------------------------------------------------
*/

$isConferenceCombination = false;
$combinationType = '';
$canonicalPath = '';

if ($conference) {

    $topicSlug = strtolower(trim(
        (string) ($conference['topic_slug'] ?? '')
    ));

    $countrySlug = strtolower(trim(
        (string) ($conference['country_slug'] ?? '')
    ));

    $citySlug = strtolower(trim(
        (string) ($conference['city_slug'] ?? '')
    ));

    /*
    |--------------------------------------------------------------------------
    | 2 SEGMENTS
    |--------------------------------------------------------------------------
    |
    | country + conference
    | topic + conference
    |
    */

    if ($segmentCount === 2) {

        /*
        | Country + Conference
        */

        if (
            $countrySlug !== '' &&
            $r1 === $countrySlug
        ) {

            $isConferenceCombination = true;

            $combinationType =
                'country_conference';

            $canonicalPath =
                '/conferences/' .
                $countrySlug . '/' .
                $conferenceSlug;
        }

        /*
        | Topic + Conference
        */

        elseif (
            $topicSlug !== '' &&
            $r1 === $topicSlug
        ) {

            $isConferenceCombination = true;

            $combinationType =
                'topic_conference';

            $canonicalPath =
                '/conferences/' .
                $topicSlug . '/' .
                $conferenceSlug;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3 SEGMENTS
    |--------------------------------------------------------------------------
    |
    | country + topic + conference
    | country + city + conference
    |
    */

    elseif ($segmentCount === 3) {

        /*
        | Country + Topic + Conference
        */

        if (
            $countrySlug !== '' &&
            $topicSlug !== '' &&
            $r1 === $countrySlug &&
            $r2 === $topicSlug
        ) {

            $isConferenceCombination = true;

            $combinationType =
                'country_topic_conference';

            $canonicalPath =
                '/conferences/' .
                $countrySlug . '/' .
                $topicSlug . '/' .
                $conferenceSlug;
        }

        /*
        | Country + City + Conference
        */

        elseif (
            $countrySlug !== '' &&
            $citySlug !== '' &&
            $r1 === $countrySlug &&
            $r2 === $citySlug
        ) {

            $isConferenceCombination = true;

            $combinationType =
                'country_city_conference';

            $canonicalPath =
                '/conferences/' .
                $countrySlug . '/' .
                $citySlug . '/' .
                $conferenceSlug;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 4 SEGMENTS
    |--------------------------------------------------------------------------
    |
    | country + city + topic + conference
    |
    */

    elseif ($segmentCount === 4) {

        if (
            $countrySlug !== '' &&
            $citySlug !== '' &&
            $topicSlug !== '' &&
            $r1 === $countrySlug &&
            $r2 === $citySlug &&
            $r3 === $topicSlug
        ) {

            $isConferenceCombination = true;

            $combinationType =
                'country_city_topic_conference';

            $canonicalPath =
                '/conferences/' .
                $countrySlug . '/' .
                $citySlug . '/' .
                $topicSlug . '/' .
                $conferenceSlug;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Valid conference combination
|--------------------------------------------------------------------------
|
| Pass route information to conference.php.
|--------------------------------------------------------------------------
*/

if (
    $isConferenceCombination &&
    $conference &&
    $canonicalPath !== ''
) {

    $_GET['slug'] =
        (string) $conference['slug'];

    /*
    |--------------------------------------------------------------------------
    | Internal route information
    |--------------------------------------------------------------------------
    */

    $_GET['_conference_combination'] = '1';

    $_GET['_conference_combination_type'] =
        $combinationType;

    $_GET['_conference_combination_path'] =
        $canonicalPath;

    require __DIR__ . '/conference.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| Not a conference combination
|--------------------------------------------------------------------------
|
| Preserve existing landing pages:
|
| topic + country
| country + city
| topic + country + city
|--------------------------------------------------------------------------
*/

if ($segmentCount <= 3) {

    $_GET['seo1'] = $r1;
    $_GET['seo2'] = $r2;

    if ($segmentCount === 3) {
        $_GET['seo3'] = $r3;
    }

    require __DIR__ . '/conferences.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| Invalid 4-segment route
|--------------------------------------------------------------------------
*/

http_response_code(404);

require __DIR__ . '/404.php';

exit;