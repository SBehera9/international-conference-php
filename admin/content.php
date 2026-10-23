<?php
declare(strict_types=1);

require __DIR__ . '/_guard.php';

/* ==========================================================================
   SAVE
   ========================================================================== */
if (is_post()) {
    verify_csrf();

    $fields = [
        'template_default_heading',
        'template_default_description',
        'template_list_heading',

        'template_country_heading',
        'template_country_description',
        'template_country_list_heading',

        'template_city_heading',
        'template_city_description',
        'template_city_list_heading',

        'template_country_city_heading',
        'template_country_city_description',
        'template_country_city_list_heading',

        'template_topic_heading',
        'template_topic_description',
        'template_topic_list_heading',

        'template_country_topic_heading',
        'template_country_topic_description',
        'template_country_topic_list_heading',

        'template_country_city_topic_heading',
        'template_country_city_topic_description',
        'template_country_city_topic_list_heading',

        'template_search_heading',
        'template_search_description',
        'template_search_list_heading',
    ];

    try {
        $stmt = db()->prepare(
            "INSERT INTO site_content (content_key, content_value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = NOW()"
        );

        foreach ($fields as $key) {
            $value = (string) ($_POST[$key] ?? '');
            $stmt->execute([$key, $value]);
        }

        flash('success', 'Templates saved successfully.');
        redirect('/admin/content');

    } catch (Throwable $e) {
        flash('danger', 'Unable to save templates: ' . $e->getMessage());
    }
}

/* ==========================================================================
   LOAD CURRENT VALUES
   ========================================================================== */
$defaults = [
    'template_default_heading'                => 'International Conferences {YEAR}-{NEXT_YEAR}',
    'template_default_description'            => 'Browse {COUNT} upcoming approved international conferences for {YEAR}-{NEXT_YEAR}.',
    'template_list_heading'                   => 'All Conferences',

    'template_country_heading'                => 'International Conferences in {COUNTRY} {YEAR}-{NEXT_YEAR}',
    'template_country_description'            => 'Discover {COUNT} upcoming conferences in {COUNTRY} for {YEAR}-{NEXT_YEAR}. Browse events across {TOP_TOPICS}.',
    'template_country_list_heading'           => 'Conferences in {COUNTRY}',

    'template_city_heading'                   => 'International Conferences in {CITY}, {COUNTRY} {YEAR}-{NEXT_YEAR}',
    'template_city_description'               => 'Find {COUNT} upcoming conferences in {CITY}, {COUNTRY} for {YEAR}-{NEXT_YEAR}.',
    'template_city_list_heading'              => 'Conferences in {CITY}',

    'template_country_city_heading'           => 'International Conferences in {CITY}, {COUNTRY} {YEAR}-{NEXT_YEAR}',
    'template_country_city_description'       => 'Find {COUNT} upcoming conferences in {CITY}, {COUNTRY} for {YEAR}-{NEXT_YEAR}.',
    'template_country_city_list_heading'      => 'Conferences in {CITY}',

    'template_topic_heading'                  => '{TOPIC} Conferences {YEAR}-{NEXT_YEAR}',
    'template_topic_description'              => 'Browse {COUNT} upcoming {TOPIC} conferences for {YEAR}-{NEXT_YEAR}.',
    'template_topic_list_heading'             => '{TOPIC} Conferences',

    'template_country_topic_heading'          => '{TOPIC} Conferences in {COUNTRY} {YEAR}-{NEXT_YEAR}',
    'template_country_topic_description'      => 'Browse {COUNT} upcoming {TOPIC} conferences in {COUNTRY} for {YEAR}-{NEXT_YEAR}.',
    'template_country_topic_list_heading'     => '{TOPIC} Conferences in {COUNTRY}',

    'template_country_city_topic_heading'     => '{TOPIC} Conferences in {CITY}, {COUNTRY} {YEAR}-{NEXT_YEAR}',
    'template_country_city_topic_description' => 'Find {COUNT} upcoming {TOPIC} conferences in {CITY}, {COUNTRY} for {YEAR}-{NEXT_YEAR}.',
    'template_country_city_topic_list_heading'=> '{TOPIC} Conferences in {CITY}',

    'template_search_heading'                 => 'Search results for "{SEARCH}"',
    'template_search_description'             => 'Found {COUNT} conferences matching "{SEARCH}".',
    'template_search_list_heading'            => 'Search Results',
];

$values = [];
foreach ($defaults as $key => $fallback) {
    $values[$key] = content($key, $fallback);
}

/* ==========================================================================
   PAGE
   ========================================================================== */
$dashboardTitle = 'Website Content';
$dashboardRole  = 'admin';

require dirname(__DIR__) . '/includes/dashboard-header.php';
?>

<div class="admin-welcome-banner mb-4 reveal">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <div class="section-kicker mb-1" style="color:var(--color-highlight);">
        Templates
      </div>
      <h2 class="h4 fw-bold mb-1 text-white">
        Page Heading &amp; Description Templates
      </h2>
      <p class="mb-0 small" style="color:rgba(255,255,255,0.80);">
        Write templates once. They auto-fill for every country, city, and topic.
      </p>
    </div>
  </div>
</div>

<!-- Placeholder help -->
<div class="form-section mb-4 reveal">
  <h3 class="h6 fw-bold mb-3">
    <i class="bi bi-info-circle me-2 text-brand"></i>
    Placeholders you can use
  </h3>
  <div class="row g-3">
    <div class="col-md-6 col-lg-3"><code>{COUNTRY}</code> — Country name <span class="text-secondary small">(link)</span></div>
    <div class="col-md-6 col-lg-3"><code>{CITY}</code> — City name <span class="text-secondary small">(link)</span></div>
    <div class="col-md-6 col-lg-3"><code>{TOPIC}</code> — Topic name <span class="text-secondary small">(link)</span></div>
    <div class="col-md-6 col-lg-3"><code>{SEARCH}</code> — Search term <span class="text-secondary small">(bold)</span></div>
    <div class="col-md-6 col-lg-3"><code>{YEAR}</code> — Current year <span class="text-secondary small">(bold)</span></div>
    <div class="col-md-6 col-lg-3"><code>{NEXT_YEAR}</code> — Next year <span class="text-secondary small">(bold)</span></div>
    <div class="col-md-6 col-lg-3"><code>{COUNT}</code> — Results count <span class="text-secondary small">(bold)</span></div>
    <div class="col-md-6 col-lg-3"><code>{TOP_TOPICS}</code> — Top 10 topics in context <span class="text-secondary small">(links)</span></div>
    <div class="col-md-6 col-lg-3"><code>{TOP_CITIES}</code> — Top 10 cities in context <span class="text-secondary small">(links)</span></div>
    <div class="col-md-6 col-lg-3"><code>{TOP_COUNTRIES}</code> — Top 10 countries in context <span class="text-secondary small">(links)</span></div>
  </div>
  <p class="small text-secondary mb-0 mt-3">
    <strong>Links</strong> open in a new tab and point to the filtered conference page.
    <strong>Bold</strong> values are plain text (no link).
  </p>

<!-- Template form -->
<form method="post" class="reveal">
  <?= csrf_field() ?>

  <?php
  $sections = [
      'Default Page (no filter)' => [
          'default_heading'     => 'Page Heading',
          'default_description' => 'Page Description',
          'list_heading'        => 'List Section Heading (H2 above the list)',
      ],
      'Country Page' => [
          'country_heading'      => 'Heading',
          'country_description'  => 'Description',
          'country_list_heading' => 'List Section Heading (H2 above the list)',
      ],
      'City Page' => [
          'city_heading'      => 'Heading',
          'city_description'  => 'Description',
          'city_list_heading' => 'List Section Heading (H2 above the list)',
      ],
      'Country + City Page' => [
          'country_city_heading'      => 'Heading',
          'country_city_description'  => 'Description',
          'country_city_list_heading' => 'List Section Heading (H2 above the list)',
      ],
      'Topic Page' => [
          'topic_heading'      => 'Heading',
          'topic_description'  => 'Description',
          'topic_list_heading' => 'List Section Heading (H2 above the list)',
      ],
      'Country + Topic Page' => [
          'country_topic_heading'      => 'Heading',
          'country_topic_description'  => 'Description',
          'country_topic_list_heading' => 'List Section Heading (H2 above the list)',
      ],
      'Country + City + Topic Page' => [
          'country_city_topic_heading'      => 'Heading',
          'country_city_topic_description'  => 'Description',
          'country_city_topic_list_heading' => 'List Section Heading (H2 above the list)',
      ],
      'Search Results Page' => [
          'search_heading'      => 'Heading',
          'search_description'  => 'Description',
          'search_list_heading' => 'List Section Heading (H2 above the list)',
      ],
  ];
  ?>

  <?php foreach ($sections as $sectionTitle => $fields): ?>
    <div class="form-section mb-4">
      <h3 class="h6 fw-bold border-bottom pb-2 mb-4">
        <i class="bi bi-pencil-square me-2 text-brand"></i>
        <?= e($sectionTitle) ?>
      </h3>

      <?php foreach ($fields as $shortKey => $label): ?>
        <?php
        $fullKey = 'template_' . $shortKey;
        $value   = $values[$fullKey] ?? '';
        $isLong  = str_contains($shortKey, 'description');
        ?>
        <div class="mb-3">
          <label class="form-label"><?= e($label) ?></label>
          <?php if ($isLong): ?>
            <textarea name="<?= e($fullKey) ?>"
                      class="form-control"
                      rows="3"><?= e($value) ?></textarea>
          <?php else: ?>
            <input type="text"
                   name="<?= e($fullKey) ?>"
                   class="form-control"
                   value="<?= e($value) ?>">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="d-flex justify-content-end border-top pt-4 mb-4">
    <button type="submit" class="btn btn-action px-4">
      <i class="bi bi-check-circle me-1"></i>
      Save All Templates
    </button>
  </div>
</form>

<?php require dirname(__DIR__) . '/includes/dashboard-footer.php'; ?>