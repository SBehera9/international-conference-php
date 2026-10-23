/* ==========================================================================
   INTERNATIONAL CONFERENCE — Main JavaScript
   ==========================================================================
   Features:
     1.  Sidebar toggle (dashboard, mobile)
     2.  Confirm dialogs
     3.  Country → City cascade
     4.  Character counters
     5.  Admin sidebar accordion (one open)
     6.  Country/City accordion (locations)
     7.  Scroll reveals (IntersectionObserver)
     8.  Back-to-top button
     9.  Navbar scroll effect
     10. Button ripple
     11. Mobile nav toggle (public)
     12. Smooth anchor links
     13. Star rating painter
     14. Lazy load images
     15. Auto-dismiss alerts
     16. Form submit guard
   ========================================================================== */

(function () {
  'use strict';

  /* ========================================================================
     HELPERS
     ======================================================================== */

  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function debounce(fn, wait = 150) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  function throttle(fn, wait = 100) {
    let last = 0;
    let timer = null;
    return function (...args) {
      const now = Date.now();
      const remaining = wait - (now - last);
      if (remaining <= 0) {
        clearTimeout(timer);
        timer = null;
        last = now;
        fn.apply(this, args);
      } else if (!timer) {
        timer = setTimeout(() => {
          last = Date.now();
          timer = null;
          fn.apply(this, args);
        }, remaining);
      }
    };
  }

  /* ========================================================================
     1. DASHBOARD SIDEBAR TOGGLE
     ======================================================================== */

  function initDashboardSidebar() {
    const sidebar = $('#dashboardSidebar');
    const toggles = $$('[data-dashboard-toggle]');

    if (!sidebar || !toggles.length) return;

    toggles.forEach((btn) => {
      btn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
      });
    });

    document.addEventListener('click', (e) => {
      if (window.innerWidth >= 992) return;
      if (!sidebar.classList.contains('open')) return;
      if (sidebar.contains(e.target)) return;
      if (e.target.closest('[data-dashboard-toggle]')) return;
      sidebar.classList.remove('open');
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') sidebar.classList.remove('open');
    });
  }

  /* ========================================================================
     2. CONFIRM DIALOGS
     ======================================================================== */

  function initConfirmDialogs() {
    $$('[data-confirm]').forEach((el) => {
      if (el.dataset.confirmBound === '1') return;
      el.dataset.confirmBound = '1';

      el.addEventListener('click', (e) => {
        const message = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(message)) {
          e.preventDefault();
          e.stopPropagation();
          return false;
        }
      });
    });
  }

  /* ========================================================================
     3. COUNTRY → CITY CASCADE
     ======================================================================== */

  function initCountryCityCascade() {
    function findCitySelect(countrySelect) {
      if (countrySelect.hasAttribute('data-feedback-country')) {
        return $('[data-feedback-city]');
      }
      if (countrySelect.hasAttribute('data-conference-country')) {
        return $('[data-conference-city]');
      }
      if (countrySelect.hasAttribute('data-country-select')) {
        return $('[data-city-select]');
      }
      return null;
    }

    const countrySelects = $$(
      '[data-country-select], [data-conference-country], [data-feedback-country]'
    );

    countrySelects.forEach((countrySelect) => {
      if (countrySelect.dataset.cascadeBound === '1') return;
      countrySelect.dataset.cascadeBound = '1';

      const citySelect = findCitySelect(countrySelect);
      if (!citySelect) return;

      const allCities = Array.from(
        citySelect.querySelectorAll('option[data-country]')
      ).map((opt) => ({
        value: String(opt.value),
        country: String(opt.dataset.country || ''),
        label: opt.textContent.trim(),
      }));

      const isConferenceFilter = !!citySelect.closest('[data-conference-city-wrap]');
      const isFeedbackModal    = !!citySelect.closest('[data-feedback-city-wrap]');

      const cityWrap =
        citySelect.closest('[data-conference-city-wrap]') ||
        citySelect.closest('[data-feedback-city-wrap]');

      function getPlaceholderText(hasCountry) {
        if (isConferenceFilter) {
          return hasCountry ? 'All Cities' : 'Select country first';
        }
        return hasCountry ? 'Select city' : 'Select country first';
      }

      function renderCities(keepSelection) {
        const countryId = String(countrySelect.value || '');
        const previousCity = keepSelection ? String(citySelect.value || '') : '';
        const hasCountry = countryId !== '';

        if (isConferenceFilter) {
          if (cityWrap) {
            cityWrap.classList.remove('d-none');
            cityWrap.hidden = false;
          }

          citySelect.innerHTML = '';

          const placeholder = document.createElement('option');
          placeholder.value = '';
          placeholder.textContent = getPlaceholderText(hasCountry);
          citySelect.appendChild(placeholder);

          if (!hasCountry) {
            citySelect.disabled = true;
            return;
          }

          const matches = allCities.filter((c) => c.country === countryId);

          matches.forEach((city) => {
            const opt = document.createElement('option');
            opt.value = city.value;
            opt.textContent = city.label;
            opt.dataset.country = city.country;
            if (city.value === previousCity) opt.selected = true;
            citySelect.appendChild(opt);
          });

          if (!matches.length) {
            placeholder.textContent = 'No cities available';
          }

          citySelect.disabled = false;

          if (previousCity && matches.some((c) => c.value === previousCity)) {
            citySelect.value = previousCity;
          } else {
            citySelect.value = '';
          }
          return;
        }

        if (isFeedbackModal) {
          citySelect.innerHTML = '';

          const placeholder = document.createElement('option');
          placeholder.value = '';
          placeholder.textContent = getPlaceholderText(hasCountry);
          citySelect.appendChild(placeholder);

          if (!hasCountry) {
            citySelect.disabled = true;
            if (cityWrap) cityWrap.hidden = true;
            return;
          }

          if (cityWrap) cityWrap.hidden = false;

          const matches = allCities.filter((c) => c.country === countryId);

          matches.forEach((city) => {
            const opt = document.createElement('option');
            opt.value = city.value;
            opt.textContent = city.label;
            opt.dataset.country = city.country;
            if (city.value === previousCity) opt.selected = true;
            citySelect.appendChild(opt);
          });

          if (!matches.length) {
            placeholder.textContent = 'No cities available';
          }

          citySelect.disabled = false;

          if (previousCity && matches.some((c) => c.value === previousCity)) {
            citySelect.value = previousCity;
          } else {
            citySelect.value = '';
          }
          return;
        }

        citySelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = getPlaceholderText(hasCountry);
        citySelect.appendChild(placeholder);

        if (!hasCountry) {
          citySelect.disabled = true;
          return;
        }

        const matches = allCities.filter((c) => c.country === countryId);

        matches.forEach((city) => {
          const opt = document.createElement('option');
          opt.value = city.value;
          opt.textContent = city.label;
          opt.dataset.country = city.country;
          if (city.value === previousCity) opt.selected = true;
          citySelect.appendChild(opt);
        });

        if (!matches.length) {
          placeholder.textContent = 'No cities available';
        }

        citySelect.disabled = false;

        if (previousCity && matches.some((c) => c.value === previousCity)) {
          citySelect.value = previousCity;
        } else {
          citySelect.value = '';
        }
      }

      countrySelect.addEventListener('change', () => renderCities(false));
      renderCities(true);
    });
  }

  /* ========================================================================
     4. CHARACTER COUNTERS
     ======================================================================== */

  function initCharCounters() {
    $$('[data-char-count]').forEach((counter) => {
      const targetSel = counter.dataset.charCount;
      if (!targetSel) return;

      const target = $(targetSel);
      if (!target) return;

      const max = target.getAttribute('maxlength') || '∞';

      function update() {
        counter.textContent = `${target.value.length} / ${max}`;
      }

      if (!target.dataset.charCountBound) {
        target.dataset.charCountBound = '1';
        target.addEventListener('input', update);
      }
      update();
    });
  }

  /* ========================================================================
     5. ADMIN SIDEBAR ACCORDION (only one open at a time)
     ======================================================================== */

function initAdminAccordion() {
  // Prevent binding more than once
  if (document.body.dataset.adminAccordionBound === '1') return;
  document.body.dataset.adminAccordionBound = '1';

  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-admin-menu-toggle]');
    if (!toggle) return;

    e.preventDefault();
    e.stopPropagation();

    var group = toggle.closest('.admin-menu-group');
    if (!group) return;

    var wasOpen = group.classList.contains('open');

    // Close every dropdown first
    document.querySelectorAll('.admin-menu-group').forEach(function (item) {
      item.classList.remove('open');
    });

    // If clicked dropdown was closed, open it
    // If it was already open, leave everything closed
    if (!wasOpen) {
      group.classList.add('open');
    }
  }, true);

  // Safety:
  // If more than one dropdown is open when page loads,
  // keep only one open.
  var openGroups = document.querySelectorAll('.admin-menu-group.open');

  if (openGroups.length > 1) {
    openGroups.forEach(function (item, index) {
      if (index < openGroups.length - 1) {
        item.classList.remove('open');
      }
    });
  }
}

  /* ========================================================================
     6. COUNTRY / CITY ACCORDION (Locations page)
     ======================================================================== */

  function initCountryAccordion() {
    const items = $$('.country-accordion-item');
    if (!items.length) return;

    items.forEach((item) => {
      const header = item.querySelector('[data-country-toggle]');
      if (!header) return;
      if (header.dataset.countryBound === '1') return;
      header.dataset.countryBound = '1';

      header.addEventListener('click', (event) => {
        if (event.target.closest('button') || event.target.closest('form')) return;

        const isOpen = item.classList.contains('open');
        items.forEach((other) => other.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
      });
    });
  }

  /* ========================================================================
     7. SCROLL REVEALS
     ======================================================================== */

  function initScrollReveals() {
    const elements = $$('.reveal, .reveal-stagger');
    if (!elements.length) return;

    if (!('IntersectionObserver' in window)) {
      elements.forEach((el) => el.classList.add('in-view'));
      return;
    }

    if (prefersReducedMotion()) {
      elements.forEach((el) => el.classList.add('in-view'));
      return;
    }

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        observer.unobserve(entry.target);
      }
    });
  },
  {
    threshold: 0,                    // ← NEW: trigger as soon as ANY pixel is visible
    rootMargin: '0px 0px -60px 0px',
  }
);

    elements.forEach((el) => observer.observe(el));
  }

  /* ========================================================================
     8. BACK TO TOP BUTTON
     ======================================================================== */

  function initBackToTop() {
    let btn = $('.back-to-top');

    if (!btn && document.body.classList.contains('dashboard-body') === false) {
      btn = document.createElement('button');
      btn.className = 'back-to-top';
      btn.type = 'button';
      btn.setAttribute('aria-label', 'Back to top');
      btn.innerHTML = '<i class="bi bi-arrow-up"></i>';
      document.body.appendChild(btn);
    }

    if (!btn) return;

    const toggle = () => {
      if (window.scrollY > 400) {
        btn.classList.add('visible');
      } else {
        btn.classList.remove('visible');
      }
    };

    window.addEventListener('scroll', throttle(toggle, 100), { passive: true });

    btn.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
      });
    });

    toggle();
  }

  /* ========================================================================
     9. NAVBAR SCROLL EFFECT
     ======================================================================== */

  function initNavbarScroll() {
    const navbar = $('.site-navbar');
    if (!navbar) return;

    const toggle = () => {
      if (window.scrollY > 8) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    };

    window.addEventListener('scroll', throttle(toggle, 100), { passive: true });
    toggle();
  }

  /* ========================================================================
     10. BUTTON RIPPLE
     ======================================================================== */

  function initButtonRipple() {
    if (prefersReducedMotion()) return;

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn');
      if (!btn) return;
      if (btn.disabled) return;

      const rect = btn.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;

      btn.style.setProperty('--ripple-x', x + '%');
      btn.style.setProperty('--ripple-y', y + '%');
    });
  }

  /* ========================================================================
     11. MOBILE NAV TOGGLE (public navbar)
     ======================================================================== */

  function initMobileNav() {
    const toggler = $('[data-navbar-toggle]') || $('.navbar-toggler');
    const nav = $('.navbar-nav');

    if (!toggler || !nav) return;
    if (toggler.dataset.navBound === '1') return;
    toggler.dataset.navBound = '1';

    function syncBurgerIcon() {
      const icon = toggler.querySelector('i');
      if (!icon) return;

      if (nav.classList.contains('open')) {
        icon.classList.remove('bi-list');
        icon.classList.add('bi-x-lg');
      } else {
        icon.classList.remove('bi-x-lg');
        icon.classList.add('bi-list');
      }
    }

    toggler.addEventListener('click', () => {
      nav.classList.toggle('open');
      toggler.setAttribute(
        'aria-expanded',
        nav.classList.contains('open') ? 'true' : 'false'
      );
      syncBurgerIcon();
    });

    nav.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        if (window.innerWidth < 1024) {
          nav.classList.remove('open');
          syncBurgerIcon();
        }
      });
    });

    document.addEventListener('click', (e) => {
      if (window.innerWidth >= 1024) return;
      if (!nav.classList.contains('open')) return;
      if (nav.contains(e.target)) return;
      if (toggler.contains(e.target)) return;
      nav.classList.remove('open');
      syncBurgerIcon();
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth >= 1024) {
        nav.classList.remove('open');
        syncBurgerIcon();
      }
    });
  }

  /* ========================================================================
     12. SMOOTH ANCHOR LINKS
     ======================================================================== */

  function initSmoothAnchors() {
    $$('a[href^="#"]').forEach((link) => {
      if (link.dataset.smoothBound === '1') return;
      link.dataset.smoothBound = '1';

      link.addEventListener('click', (e) => {
        const href = link.getAttribute('href');
        if (!href || href === '#' || href.length < 2) return;

        const target = document.getElementById(href.slice(1));
        if (!target) return;

        e.preventDefault();

        const navbar = $('.site-navbar');
        const offset = navbar ? navbar.offsetHeight + 12 : 12;
        const top = target.getBoundingClientRect().top + window.scrollY - offset;

        window.scrollTo({
          top,
          behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        });

        if (history.replaceState) {
          history.replaceState(null, '', href);
        }
      });
    });
  }

  /* ========================================================================
     13. STAR RATING PAINTER
     ======================================================================== */

  function initStarPicker() {
    const pickers = $$('[data-star-picker]');
    if (!pickers.length) return;

    pickers.forEach((picker) => {
      if (picker.dataset.starBound === '1') return;
      picker.dataset.starBound = '1';

      const inputs = Array.from(picker.querySelectorAll('input[type="radio"]'));
      const labels = Array.from(picker.querySelectorAll('[data-star-value]'));

      function paint() {
        const checked = inputs.find((i) => i.checked);
        const value = checked ? Number(checked.value) : 0;

        labels.forEach((label) => {
          const starValue = Number(label.getAttribute('data-star-value'));
          label.classList.toggle('active', starValue <= value);
        });
      }

      inputs.forEach((input) => input.addEventListener('change', paint));
      paint();
    });
  }

  /* ========================================================================
     14. LAZY LOAD IMAGES (native + fallback)
     ======================================================================== */

  function initLazyLoad() {
    if ('loading' in HTMLImageElement.prototype) return;

    const images = $$('img[loading="lazy"]');
    if (!images.length) return;

    if (!('IntersectionObserver' in window)) {
      images.forEach((img) => {
        if (img.dataset.src) img.src = img.dataset.src;
      });
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) img.src = img.dataset.src;
            observer.unobserve(img);
          }
        });
      },
      { rootMargin: '100px' }
    );

    images.forEach((img) => observer.observe(img));
  }

  /* ========================================================================
     15. AUTO-DISMISS ALERTS
     ======================================================================== */

  function initAutoDismissAlerts() {
    $$('.alert-success, .alert-warning').forEach((alert) => {
      if (alert.dataset.autoDismiss === '1') return;
      alert.dataset.autoDismiss = '1';

      setTimeout(() => {
        alert.style.transition = 'opacity 300ms ease, transform 300ms ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-8px)';

        setTimeout(() => {
          alert.remove();
        }, 320);
      }, 5000);
    });
  }

  /* ========================================================================
     16. FORM SUBMIT GUARD (prevent double-submit)
     ======================================================================== */

  function initFormGuard() {
    $$('form').forEach((form) => {
      if (form.dataset.guardBound === '1') return;
      form.dataset.guardBound = '1';

      form.addEventListener('submit', (e) => {
        if (form.hasAttribute('data-no-guard')) return;

        setTimeout(() => {
          const submitter = form.querySelector('button[type="submit"], input[type="submit"]');

          if (submitter && !submitter.disabled) {
            submitter.disabled = true;
            submitter.dataset.originalText = submitter.innerHTML;

            setTimeout(() => {
              if (submitter && submitter.disabled) {
                submitter.disabled = false;
                if (submitter.dataset.originalText) {
                  submitter.innerHTML = submitter.dataset.originalText;
                }
              }
            }, 8000);
          }
        }, 0);
      });
    });
  }

  /* ========================================================================
     INITIALIZATION
     ======================================================================== */

  function init() {
    initDashboardSidebar();
    initConfirmDialogs();
    initCountryCityCascade();
    initCharCounters();
    initAdminAccordion();
    initCountryAccordion();
    initScrollReveals();
    initBackToTop();
    initNavbarScroll();
    initButtonRipple();
    initMobileNav();
    initSmoothAnchors();
    initStarPicker();
    initLazyLoad();
    initAutoDismissAlerts();
    initFormGuard();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.ICApp = { init };

})();