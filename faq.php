<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/* ==========================================================================
   FAQ CONTENT — edit only this array to add/remove questions
   ==========================================================================
   Sections → Each section has a heading + a list of Q&A items.
   Every Q&A becomes both a visible accordion and a FAQPage schema entry.
   ========================================================================== */

$faqSections = [

    'About the Platform' => [
        [
            'question' => 'What is International Conference?',
            'answer'   => 'International Conference is a global directory that indexes and presents legitimate international conferences across academic, scientific, engineering, medical, business and professional fields. We help researchers, students, professionals and organizations discover upcoming conference opportunities from one structured directory.',
        ],

        [
            'question' => 'Is using this website free?',
            'answer'   => 'Yes. Browsing conferences, viewing organizer profiles, reading blog posts and using the platform is completely free for visitors. There are no subscriptions or hidden fees for researchers and attendees looking for conferences.',
        ],
        [
            'question' => 'How do I find a conference relevant to my field?',
            'answer'   => 'Use the search bar on the home page or the Conferences page. You can search by title, topic, country or city. Use the filters to narrow down by topic, country, city, attendance type (Offline, Online or Hybrid), and date. Each conference page shows dates, venue, organizer and registration details.',
        ],


    ],

    'For Attendees' => [
        [
            'question' => 'Are the conferences listed here verified?',
            'answer'   => 'Every conference on this site is submitted by an organizer and manually reviewed by our administrator before publication. We check that the submission is complete, meaningful and consistent with our platform standards. Organizers may also be marked as Verified once their profile is confirmed.',
        ],
        [
            'question' => 'How do I register for a conference?',
            'answer'   => 'Open any conference detail page and click the Conference Website button in the sidebar. That link takes you to the official conference website where registration is handled directly by the organizer. We do not process registrations on our platform.',
        ],
        [
            'question' => 'Can I attend conferences online?',
            'answer'   => 'Yes. Many conferences on the platform are available as Online or Hybrid events. Look for the Attendance Type badge on the conference card, or use the Attendance filter on the Conferences page to show only Online or Hybrid events.',
        ],
    ],

    'For Organizers' => [
        [
            'question' => 'How can I list my conference?',
            'answer'   => 'Create a free organizer account using the Organizer Signup link in the top navigation. After you complete your organization profile, you can submit a conference from your dashboard. Every submission is reviewed by our administrator before it appears publicly.',
        ],
        [
            'question' => 'How long does approval take?',
            'answer'   => 'Approval times vary depending on the queue, but most conferences are reviewed within 1 to 3 business days. You will see the status change from Pending to Approved or Rejected on your organizer dashboard.',
        ],
        [
            'question' => 'Can I edit my conference after submitting it?',
            'answer'   => 'You can edit a conference while it is still Pending or after it has been Rejected. Once it is Approved, the listing is locked to preserve accuracy and any further changes must be requested from the administrator.',
        ],
        [
            'question' => 'What happens to my conference after it ends?',
            'answer'   => 'Once a conference end date has passed, it is automatically moved to your Completed Conferences list. It no longer appears in the public directory, but the record is preserved in your dashboard for history and future reference.',
        ],
    ],

    'Account & Support' => [
        [
            'question' => 'How do I reset my password?',
            'answer'   => 'On the login page, click Forgot password and follow the instructions. Our administrator will verify your account and send you a secure reset link via email that is valid for 24 hours.',
        ],
        [
            'question' => 'How do I contact support?',
            'answer'   => 'Visit the Contact Us page for our email address and other contact details. You can also share your experience through the Feedback page. We usually respond within 1 to 2 business days.',
        ],
    ],

];

/* Flatten every Q&A into one list for the FAQPage schema */
$allFaqs = [];
foreach ($faqSections as $sectionItems) {
    foreach ($sectionItems as $faq) {
        $allFaqs[] = $faq;
    }
}

/* ==========================================================================
   SEO
   ========================================================================== */
$faqKeywords     = page_keywords('faq');
$faqTitle        = 'Frequently Asked Questions';
$faqDescription  = 'Find answers to common questions about finding conferences, listing events as an organizer, account management and support.';

$seo = page_seo([
    'title'       => $faqTitle,
    'description' => $faqDescription,
    'keywords'    => $faqKeywords,
    'canonical'   => url('/faq'),
    'json_ld'     => [
        faq_schema($allFaqs),
        collection_schema(
            $faqTitle,
            url('/faq'),
            $faqDescription,
            $faqKeywords
        ),
        breadcrumb_schema([
            ['name' => 'Home', 'url' => url('/')],
            ['name' => 'FAQ',  'url' => url('/faq')],
        ]),
    ],
]);

require __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     PAGE HERO — single H1
     ========================================================================== -->
<section class="page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">FAQ</li>
      </ol>
    </nav>

    <div class="section-kicker">Help Center</div>
    <h1 class="display-5 mt-2">Frequently Asked Questions</h1>
    <p class="lead text-secondary col-lg-9 mb-0">
      Quick answers to the most common questions about using the platform —
      for attendees, organizers and partners.
    </p>
  </div>
</section>

<!-- ==========================================================================
     FAQ SECTIONS
     ========================================================================== -->
<section class="py-5 faq-accordion-column">
  <div class="container">
        <div class="row g-5">

      <!-- Main FAQ column -->
      <div class="col-lg-8">

        <?php $faqCounter = 0; ?>

        <?php foreach ($faqSections as $sectionTitle => $items): ?>

          <div class="mb-5 reveal">

            <!-- Visible H2 for each section -->
            <h2 class="h4 fw-bold mb-4">
              <i class="bi bi-question-circle me-2 text-brand"></i>
              <?= e($sectionTitle) ?>
            </h2>

            <div class="accordion faq-accordion" id="faqAccordion-<?= e(slugify($sectionTitle)) ?>">

              <?php foreach ($items as $faq): ?>
                <?php
                $faqCounter++;
                $faqId = 'faq-item-' . $faqCounter;
                ?>

                <div class="accordion-item">

                  <!-- Question — H3 for accessibility hierarchy -->
                  <h3 class="accordion-header" id="<?= e($faqId) ?>-heading">
                    <button
                      class="accordion-button collapsed"
                      type="button"
                      data-bs-toggle="collapse"
                      data-bs-target="#<?= e($faqId) ?>-body"
                      aria-expanded="false"
                      aria-controls="<?= e($faqId) ?>-body">

                      <?= e($faq['question']) ?>

                    </button>
                  </h3>

                  <div
                    id="<?= e($faqId) ?>-body"
                    class="accordion-collapse collapse"
                    aria-labelledby="<?= e($faqId) ?>-heading"
                    data-bs-parent="#faqAccordion-<?= e(slugify($sectionTitle)) ?>">

                    <div class="accordion-body">
                      <?= e($faq['answer']) ?>
                    </div>

                  </div>

                </div>

              <?php endforeach; ?>

            </div>

          </div>

        <?php endforeach; ?>

      </div>

      <!-- Sidebar -->
      <div class="col-lg-4 faq-sidebar-column">
       <div class="form-section faq-sidebar-sticky">

          <h2 class="h5 fw-bold mb-4">
            <i class="bi bi-compass me-2 text-brand"></i>
            Still need help?
          </h2>

          <p class="small text-secondary mb-4">
            Couldn't find what you were looking for? We're happy to help.
          </p>

          <div class="d-grid gap-2 mb-4">
            <a href="<?= e(url('/contact-us')) ?>" class="btn btn-action">
              <i class="bi bi-envelope me-1"></i>
              Contact Us
            </a>

            <a href="<?= e(url('/feedback')) ?>" class="btn btn-outline-dark">
              <i class="bi bi-chat-square-heart me-1"></i>
              Share Feedback
            </a>
          </div>

          <hr>

          <h3 class="h6 fw-bold mb-3">Quick Links</h3>

          <ul class="list-unstyled mb-0 faq-quick-links">
            <li class="mb-2">
              <a href="<?= e(url('/conferences')) ?>">
                <i class="bi bi-calendar2-event me-1"></i>
                Browse Conferences
              </a>
            </li>
            <li class="mb-2">
              <a href="<?= e(url('/organizer-signup')) ?>">
                <i class="bi bi-person-plus me-1"></i>
                Organizer Signup
              </a>
            </li>
            <li class="mb-2">
              <a href="<?= e(url('/organizers')) ?>">
                <i class="bi bi-people me-1"></i>
                Meet the Organizers
              </a>
            </li>
            <li class="mb-2">
              <a href="<?= e(url('/blog')) ?>">
                <i class="bi bi-journal-text me-1"></i>
                Read the Blog
              </a>
            </li>
            <li>
              <a href="<?= e(url('/about-us')) ?>">
                <i class="bi bi-info-circle me-1"></i>
                About Us
              </a>
            </li>
          </ul>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- ==========================================================================
     PAGE-SPECIFIC STYLES
     ========================================================================== -->
<style>
/* ==========================================================================
   FAQ accordion styling — matches the site's design language
   ========================================================================== */
.faq-accordion .accordion-item {
  background: var(--color-white);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  margin-bottom: 0.75rem;
  overflow: hidden;
  transition:
    border-color var(--dur-base) var(--ease-standard),
    box-shadow   var(--dur-base) var(--ease-standard);
}

.faq-accordion .accordion-item:hover {
  border-color: var(--color-accent-light);
  box-shadow: var(--shadow-sm);
}

.faq-accordion .accordion-button {
  background: var(--color-white);
  color: var(--color-text-strong);
  font-family: var(--font-heading);
  font-size: var(--fs-base);
  font-weight: var(--fw-semibold);
  line-height: 1.45;
  padding: 1.15rem 1.35rem;
  box-shadow: none;
  transition:
    background var(--dur-fast) var(--ease-standard),
    color      var(--dur-fast) var(--ease-standard);
}

.faq-accordion .accordion-button:hover {
  color: var(--color-accent);
}

.faq-accordion .accordion-button:not(.collapsed) {
  background: var(--color-bg-soft);
  color: var(--color-accent);
  box-shadow: none;
}

.faq-accordion .accordion-button:focus {
  border-color: transparent;
  box-shadow: none;
}

.faq-accordion .accordion-button::after {
  background-size: 1rem;
  width: 1rem;
  height: 1rem;
  transition: transform var(--dur-base) var(--ease-standard);
}

.faq-accordion .accordion-body {
  padding: 0 1.35rem 1.35rem;
  color: var(--color-muted);
  font-size: var(--fs-sm);
  line-height: 1.7;
}

/* ==========================================================================
   FAQ sidebar
   ========================================================================== */
.faq-quick-links a {
  display: inline-flex;
  align-items: center;
  color: var(--color-text);
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  text-decoration: none;
  transition: color var(--dur-fast) var(--ease-standard);
}

.faq-quick-links a:hover {
  color: var(--color-accent);
}

/* ==========================================================================
   FAQ sidebar — sticky on desktop (matches conference detail page)
   ========================================================================== */
@media (min-width: 992px) {

  /* Let sticky escape the parent grid/flex box */
  .faq-accordion-column,
  .faq-accordion-column > .container,
  .faq-accordion-column .row {
    overflow: visible !important;
  }

  .faq-sidebar-sticky {
    position: -webkit-sticky !important;
    position: sticky !important;
    top: 110px !important;
    z-index: 20;
  }

}

@media (max-width: 991.98px) {
  .faq-sidebar-sticky {
    position: static !important;
    top: auto !important;
  }
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>