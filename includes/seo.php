<?php
declare(strict_types=1);

function page_seo(array $overrides = []): array
{
    $seo = array_merge([
        'title' => APP_NAME,
        'description' => DEFAULT_META_DESCRIPTION,
        'keywords' => DEFAULT_META_KEYWORDS,
        'canonical' => current_page_url(),
        'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
        'image' => url('/assets/images/company-logo.png'),
        'type' => 'website',
        'json_ld' => [],
    ], $overrides);
    $seo['title'] = trim($seo['title']);
    if (!str_contains($seo['title'], APP_NAME)) {
        $seo['title'] .= ' | ' . APP_NAME;
    }
    return $seo;
}

function organization_schema(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => url('/#organization'),
        'name' => APP_NAME,
        'url' => url('/'),
        'logo' => url('/assets/images/company-logo.png'),
        'sameAs' => array_values(array_filter([
            setting('facebook_url'), setting('instagram_url'), setting('linkedin_url'), setting('twitter_url'), setting('youtube_url')
        ])),
    ];
}

function website_schema(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => url('/#website'),
        'url' => url('/'),
        'name' => APP_NAME,
        'publisher' => ['@id' => url('/#organization')],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => url('/conferences?search={search_term_string}'),
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function breadcrumb_schema(array $items): array
{
    $list = [];
    foreach ($items as $i => $item) {
        $list[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['name'],
            'item' => $item['url'],
        ];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

function conference_schema(array $c, ?array $org = null): array
{
    $start = (string)($c['start_date'] ?? '');
    $end = (string)($c['end_date'] ?? '');
    $time = (string)($c['conference_time'] ?? '09:00:00');
    $timezone = (string)($c['time_zone'] ?? '');

    $toIso = static function (string $date, string $clock, string $tz): ?string {
        if ($date === '') return null;
        try {
            if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
                $dt = new DateTimeImmutable($date . ' ' . substr($clock, 0, 8), new DateTimeZone($tz));
                return $dt->format(DateTimeInterface::ATOM);
            }
        } catch (Throwable $e) {
            // Fall back to local date-time below if a stored timezone is invalid.
        }
        return $date . 'T' . substr($clock, 0, 8);
    };

    $startIso = $toIso($start, $time, $timezone);
    $endIso = $toIso($end, $time, $timezone);
    $isOnline = ($c['attendance_type'] ?? '') === 'Online';
    $isHybrid = ($c['attendance_type'] ?? '') === 'Hybrid';
    $cityName = (string)($c['city_name'] ?? '');
    $countryName = (string)($c['country_name'] ?? '');

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $c['title'],
        'description' => excerpt((string)$c['description'], 500),
        'startDate' => $startIso,
        'endDate' => $endIso,
        'eventStatus' => 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => $isOnline
            ? 'https://schema.org/OnlineEventAttendanceMode'
            : ($isHybrid ? 'https://schema.org/MixedEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode'),
        'url' => url('/conference/' . $c['slug']),
        'image' => array_values(array_filter([absolute_media_url($c['banner_image'] ?? ''), url('/assets/images/company-logo.png')])),
    ];

    if (!$isOnline) {
        $schema['location'] = [
            '@type' => 'Place',
            'name' => $c['venue'] ?: $cityName,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $c['venue'] ?: '',
                'addressLocality' => $cityName,
                'addressRegion' => (string)($c['state'] ?? ''),
                'addressCountry' => $countryName,
            ],
        ];
    } elseif (!empty($c['conference_website'])) {
        $schema['location'] = ['@type' => 'VirtualLocation', 'url' => $c['conference_website']];
    }

    if ($org) {
        $schema['organizer'] = [
            '@type' => 'Organization',
            'name' => $org['organization_name'] ?: $org['contact_person'],
            'url' => $org['website'] ?: url('/organizers/' . $org['slug']),
        ];
    }

    return array_filter($schema, static fn($value) => $value !== null && $value !== '');
}


/* ==========================================================================
   PER-PAGE KEYWORDS
   ==========================================================================
   Builds a keyword string based on the current page type, using the
   templates from the client-supplied keyword document.
   ========================================================================== */

function page_keywords(string $pageType, array $vars = []): string
{
    $topic   = trim((string) ($vars['topic']   ?? ''));
    $country = trim((string) ($vars['country'] ?? ''));
    $city    = trim((string) ($vars['city']    ?? ''));

    $topicLower = mb_strtolower($topic);

    $keywords = [];

    switch ($pageType) {

        case 'country':
            $keywords = [
                "international conferences in {$country}",
                "international conferences in {$country} 2026",
                "international conferences in {$country} 2027",
                "conferences in {$country} 2026-2027",
                "upcoming conferences in {$country}",
                "academic conferences in {$country}",
                "scientific conferences in {$country}",
                "research conferences in {$country}",
                "medical conferences in {$country}",
                "healthcare conferences {$country} 2026",
                "hybrid conferences in {$country}",
                "international events in {$country} for researchers",
                "{$country} conference list 2027",
            ];
            break;

        case 'city':
            $keywords = [
                "international conferences in {$city}",
                "international conferences in {$city} 2026",
                "international conferences in {$city} 2027",
                "conferences in {$city}",
                "upcoming conferences in {$city}",
                "academic conferences in {$city}",
                "scientific conferences in {$city}",
                "research conferences {$city} 2027",
                "medical conferences in {$city}",
                "engineering conferences in {$city}",
                "hybrid conferences {$city}",
                "{$city} conference list 2026-2027",
                "international summits in {$city} 2027",
            ];
            break;

        case 'topic':
            $keywords = [
                "{$topicLower} conferences 2026",
                "{$topicLower} conferences 2027",
                "{$topic} conferences 2026-2027",
                "international {$topic} conferences",
                "upcoming {$topicLower} conferences",
                "{$topic} research conferences",
                "academic {$topic} conferences 2027",
                "global {$topic} summit 2026",
                "{$topic} and robotics conferences",
                "online {$topic} conferences 2026",
                "{$topic} events for researchers and professionals",
                "{$topicLower} summit list",
            ];
            break;

        case 'topic_country':
            $keywords = [
                "{$topicLower} conferences in {$country} 2026",
                "{$topicLower} conferences in {$country} 2027",
                "{$topic} conferences in {$country} 2026-2027",
                "international {$topic} conferences in {$country}",
                "upcoming {$topicLower} conferences in {$country}",
                "{$topic} research conferences in {$country}",
                "academic {$topic} conferences in {$country} 2027",
                "global {$topic} summit in {$country} 2026",
                "{$topic} conferences in {$country}",
                "online {$topic} conferences in {$country} 2026",
                "{$topic} events for researchers and professionals in {$country}",
                "{$topicLower} summit list in {$country}",
            ];
            break;

        case 'topic_country_city':
            $keywords = [
                "{$topicLower} conferences in {$city} 2026",
                "{$topicLower} conferences in {$city} 2027",
                "{$topic} conferences in {$city} 2026-2027",
                "international {$topic} conferences in {$city}",
                "upcoming {$topicLower} conferences in {$city}",
                "{$topic} research conferences in {$city}",
                "academic {$topic} conferences in {$city} 2027",
                "global {$topic} summit in {$city} 2026",
                "{$topic} conferences in {$city}",
                "online {$topic} conferences in {$city} 2026",
                "{$topic} events for researchers and professionals in {$city}",
                "{$topicLower} summit list in {$city}",
            ];
            break;

        case 'conferences_all':
            $keywords = [
                'international conferences 2026',
                'international conferences 2027',
                'upcoming international conferences',
                'global conferences 2026-2027',
                'academic conferences worldwide',
                'scientific conferences 2026',
                'research conferences 2027',
                'medical conferences worldwide',
                'engineering conferences 2026',
                'hybrid conferences 2026-2027',
                'international events for researchers',
                'conference directory 2026-2027',
            ];
            break;

        case 'organizers':
            $keywords = [
                'conference organizers',
                'international conference organizers',
                'academic conference organizers',
                'scientific conference organizers',
                'conference organizer directory',
                'trusted conference organizers',
                'verified conference organizers',
                'list of international conference organizers',
                'research conference organizers',
                'medical conference organizers',
                'conference organizing companies',
                'global conference organizers 2026-2027',
            ];
            break;

        case 'media_partner':
            $keywords = [
                'media partners',
                'conference media partners',
                'international conference media partners',
                'become a media partner',
                'media partnership for conferences',
                'conference media partnership',
                'academic conference media partner',
                'scientific conference media partner',
                'event media partner',
                'media partner program',
                'apply for media partnership',
                'conference promotion partners',
                'conference outreach partners',
                'event promotion network',
                'global outreach network',
                'publication partners for conferences',
            ];
            break;

        case 'our_associates':
            $keywords = [
                'conference associates',
                'international conference associates',
                'academic associates',
                'professional associates',
                'conference partners',
                'academic partners',
                'become an associate',
                'join our network',
                'conference partnership program',
                'academic collaboration',
                'research collaboration partners',
                'scientific conference partners',
                'conference organizer partners',
                'partner with international conference',
                'become a conference partner',
                'apply for conference partnership',
                'academic institution partners',
                'university conference partners',
                'research organization partners',
                'professional association partners',
            ];
            break;

        case 'blog':
            $keywords = [
                'international conference blog',
                'academic conference blog',
                'conference news and updates',
                'international conference guide',
                'how to find international conferences',
                'how to choose the right conference',
                'conference tips for researchers',
                'academic conference tips',
                'conference tips for students',
                'how to submit a conference abstract',
                'how to write a conference paper',
                'call for papers guide',
                'how to present at a conference',
                'conference presentation tips',
                'poster presentation tips',
                'how to get a conference travel grant',
                'conference registration guide',
                'conference visa tips',
                'how to identify predatory conferences',
                'legitimate international conferences',
                'hybrid vs in-person conferences',
                'virtual conference tips',
                'networking at academic conferences',
                'benefits of attending international conferences',
                'upcoming conferences 2026-2027',
                'research conference announcements',
                'conference organizer tips',
                'how to promote a conference',
                'conference planning guide',
            ];
            break;

        case 'about_us':
            $keywords = [
                'about International Conference',
                'about internationalconference.info',
                'international conference directory',
                'global conference directory',
                'trusted conference directory',
                'verified conference listings',
                'manually reviewed conferences',
                'legitimate international conferences',
                'academic conference directory',
                'scientific conference directory',
                'research conference platform',
                'conference discovery platform',
                'find international conferences',
                'search conferences worldwide',
                'conferences for researchers',
                'conferences for students',
                'conferences for professionals',
                'trusted conference organizers',
                'conference listing platform',
                'submit conference for review',
                'conference approval process',
                'conference archive',
            ];
            break;

        case 'contact_us':
            $keywords = [
                'contact international conference',
                'internationalconference.info contact',
                'international conference contact us',
                'conference directory contact',
                'conference listing support',
                'conference listing inquiry',
                'contact conference organizers',
                'international conference support email',
                'conference directory customer support',
                'list your conference contact',
                'organizer signup help',
                'conference submission support',
                'advertise conference contact',
                'media partner inquiry',
                'conference partnership inquiry',
                'international conference Bengaluru',
                'conference directory India',
                'conference listing help desk',
                'academic conference directory support',
                'research conference listing contact',
            ];
            break;

        case 'feedback':
            $keywords = [
                'international conference feedback',
                'conference directory feedback',
                'share feedback',
                'submit feedback',
                'user feedback',
                'website feedback',
                'conference directory reviews',
                'international conference reviews',
                'user reviews conference directory',
                'rate conference directory',
                'conference listing feedback',
                'researcher feedback',
                'attendee feedback',
                'organizer feedback',
                'share your experience',
                'customer feedback form',
                'feedback form',
                'testimonials',
                'user testimonials',
                'conference website reviews',
                'internationalconference.info reviews',
                'internationalconference.info feedback',
            ];
            break;

        case 'privacy_policy':
            $keywords = [
                'privacy policy',
                'International Conference privacy policy',
                'internationalconference.info privacy policy',
                'conference directory privacy policy',
                'data protection policy',
                'personal data usage',
                'organizer account data',
                'conference submission data',
                'contact form data',
                'feedback form data',
                'partnership application data',
                'data security safeguards',
                'user information protection',
                'how we use your information',
                'privacy questions contact',
            ];
            break;

        case 'terms_of_service':
            $keywords = [
                'terms of service',
                'international conference terms of service',
                'internationalconference.info terms',
                'terms and conditions',
                'website terms of use',
                'conference listing terms',
                'conference directory terms and conditions',
                'organizer account terms',
                'conference organizer terms and conditions',
                'conference listing policy',
                'user agreement',
                'organizer signup terms',
                'listing approval policy',
                'acceptable use policy',
                'account registration terms',
                'content submission guidelines',
                'conference listing rules',
                'user responsibilities',
                'limitation of liability',
                'intellectual property policy',
                'account termination policy',
                'disclaimer',
                'legal terms',
                'governing law',
            ];
            break;

        case 'faq':
            $keywords = [
                'international conference FAQ',
                'conference directory FAQ',
                'frequently asked questions',
                'how to find international conferences',
                'how to submit a conference',
                'how to list a conference',
                'conference listing FAQ',
                'conference organizer questions',
                'conference approval process',
                'how to register for a conference',
                'are listed conferences verified',
                'attend conferences online',
                'conference directory help',
                'international conference support',
                'conference submission guidelines',
            ];
            break;

        default:
            return DEFAULT_META_KEYWORDS;
    }

    $keywords = array_filter(array_map('trim', $keywords), static fn ($v) => $v !== '');

    return implode(', ', $keywords);
}


/* ==========================================================================
   COLLECTION PAGE SCHEMA (per page)
   ========================================================================== */

function collection_schema(
    string $name,
    string $url,
    string $description,
    string $keywords
): array {
    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        'name'        => $name,
        'headline'    => $name,
        'url'         => $url,
        'description' => $description,
        'keywords'    => $keywords,
        'isPartOf'    => [
            '@type' => 'WebSite',
            'name'  => APP_NAME,
            'url'   => url('/'),
        ],
        'dateModified' => date('c'),
    ];
}


/* ==========================================================================
   FAQ PAGE SCHEMA
   ==========================================================================
   Emits a FAQPage JSON-LD block. Google may display FAQ items as an
   expandable rich result in search.
   ========================================================================== */

function faq_schema(array $faqs): array
{
    $items = [];

    foreach ($faqs as $faq) {
        $question = trim((string) ($faq['question'] ?? ''));
        $answer   = trim((string) ($faq['answer']   ?? ''));

        if ($question === '' || $answer === '') {
            continue;
        }

        $items[] = [
            '@type' => 'Question',
            'name'  => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $answer,
            ],
        ];
    }

    return [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $items,
    ];
}