/* ============================================================
   TD RENTALS — Loading Indicators 
   ============================================================ */

(function () {
  'use strict';

  const MIN_DELAY_MS = 300;
  const MIN_SHIMMER_MS = 300;

  /* ── 1. Top progress bar ──────────────────────────────────── */
  function startProgressBar() {
    const bar = document.getElementById('td-progress-bar');
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.opacity = '1';
    bar.style.width = '0%';
    requestAnimationFrame(() => {
      bar.style.transition = 'width 0.4s ease';
      bar.style.width = '70%';
    });
  }

  function finishProgressBar() {
    const bar = document.getElementById('td-progress-bar');
    if (!bar) return;
    bar.style.width = '100%';
    setTimeout(() => { bar.classList.add('done'); }, 300);
    setTimeout(() => {
      bar.classList.remove('done');
      bar.style.width = '0%';
    }, 750);
  }

  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href');
    if (
      href.startsWith('#') ||
      href.startsWith('javascript') ||
      a.target === '_blank' ||
      a.hostname !== window.location.hostname ||
      a.classList.contains('no-progress')
    ) return;
    startProgressBar();
  });

  /* ── 2. Full-page overlay helpers ────────────────────────── */
  function showOverlay(submitter) {
    const overlay = document.getElementById('td-overlay');
    const msgEl = document.getElementById('td-overlay-msg');
    if (!overlay) return;

    let msg = getFormMessage();
    if (submitter && submitter.classList.contains('reject-btn')) {
      msg = 'Rejecting vehicle…';
    }

    if (msgEl) msgEl.textContent = msg;

    // Ensure smooth CSS transition
    overlay.style.transition = 'opacity 0.4s ease, visibility 0.4s ease';
    overlay.classList.add('active');
  }

  function hideOverlay() {
    const overlay = document.getElementById('td-overlay');
    if (!overlay || !overlay.classList.contains('active')) return;
    overlay.classList.remove('active'); // CSS handles the 0.4s fade-out
  }

  /* ── 3. Contextual Messages ──────────────────────────────── */
  function getFormMessage() {
    const path = window.location.pathname.toLowerCase();
    if (path.includes('payment')) return 'Processing payment…';
    if (path.includes('login')) return 'Signing you in…';
    if (path.includes('signup')) return 'Creating your account…';
    if (path.includes('list_car')) return 'Listing your vehicle…';
    if (path.includes('edit')) return 'Saving changes…';
    if (path.includes('review')) return 'Approving vehicle…';
    return 'Please wait…';
  }

  /* ── 4. Button spinner ───────────────────────────────────── */
  function addButtonSpinner(btn) {
    if (!btn) return;
    btn.classList.add('td-btn-loading');
  }

  function removeButtonSpinners() {
    document.querySelectorAll('.td-btn-loading').forEach(btn => {
      btn.classList.remove('td-btn-loading');
    });
  }

  /* ── 5. Form Submissions ─────────────────────────────────── */
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return; // Respect other scripts that blocked submission
    const form = e.target;
    if (form.dataset.noLoading) return;
    if (form.dataset.isSubmitting === 'true') return; // Prevent double-submit loops

    // If the form relies on native validation (no 'novalidate' attribute), let the browser handle errors
    const hasNoValidate = form.hasAttribute('novalidate');
    if (!hasNoValidate && !form.checkValidity()) return;

    // Intercept form submission to enforce artificial delay
    e.preventDefault();
    form.dataset.isSubmitting = 'true';

    let clickedBtn = form.querySelector('[type="submit"]');
    if (e.submitter && e.submitter.tagName === 'BUTTON') {
      clickedBtn = e.submitter;
    }

    if (form.method.toUpperCase() === 'GET') {
      const resultArea = document.querySelector(
        form.dataset.resultsTarget || '.vehicles-content, .results-area'
      );

      if (clickedBtn) addButtonSpinner(clickedBtn);

      if (resultArea) {
        let spinner = document.getElementById('td-search-spinner');
        if (!spinner) {
          spinner = document.createElement('div');
          spinner.className = 'td-inline-spinner';
          spinner.id = 'td-search-spinner';
          spinner.textContent = 'Finding vehicles…';
          resultArea.prepend(spinner);
        }

        const grid = document.querySelector('.vehicles-grid');
        if (grid) {
          const realCards = grid.querySelectorAll('.vehicle-card');
          realCards.forEach(card => card.style.display = 'none');
          for (let i = 0; i < 6; i++) {
            const card = document.createElement('div');
            card.className = 'td-skeleton td-search-skeleton';
            card.innerHTML = `<div class="td-skeleton-img"></div><div class="td-skeleton-body"><div class="td-skeleton-line"></div><div class="td-skeleton-line short"></div><div class="td-skeleton-line xshort"></div><div class="td-skeleton-line short"></div></div>`;
            grid.appendChild(card);
          }
        }
      }

      // Wait exactly MIN_DELAY_MS before executing the request
      setTimeout(() => {
        if (clickedBtn && clickedBtn.name) {
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = clickedBtn.name;
          hidden.value = clickedBtn.value;
          form.appendChild(hidden);
        }
        form.submit();

        // Restore state cleanly in case the submission doesn't navigate (e.g. file download)
        removeButtonSpinners();
        const inlineSpinner = document.getElementById('td-search-spinner');
        if (inlineSpinner) inlineSpinner.remove();
        const grid = document.querySelector('.vehicles-grid');
        if (grid) {
          document.querySelectorAll('.td-search-skeleton').forEach(sk => sk.remove());
          grid.querySelectorAll('.vehicle-card').forEach(c => c.style.display = '');
        }
        form.dataset.isSubmitting = 'false';
      }, MIN_DELAY_MS);

      return;
    }

    // Handle POST forms (Login, Signup, Admin Approvals)
    if (clickedBtn) addButtonSpinner(clickedBtn);
    showOverlay(clickedBtn);

    // Enforce synchronous 1.5s delay before backend execution
    setTimeout(() => {
      if (clickedBtn && clickedBtn.name) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = clickedBtn.name;
        hidden.value = clickedBtn.value;
        form.appendChild(hidden);
      }
      form.submit();

      // Ensure UI restores perfectly if navigation doesn't occur immediately
      removeButtonSpinners();
      hideOverlay();
      form.dataset.isSubmitting = 'false';
    }, MIN_DELAY_MS);
  });

  /* ── 6. Skeleton shimmer cards ───────────────────────────── */
  function insertSkeletons() {
    const vehiclesGrid = document.querySelector('.vehicles-grid');
    if (!vehiclesGrid) return;

    // If the page already has real vehicle cards (from PHP), hide them temporarily
    const realCards = vehiclesGrid.querySelectorAll('.vehicle-card');
    let hasRealCards = realCards.length > 0;

    if (hasRealCards) {
      realCards.forEach(card => card.style.display = 'none');
    }

    // Insert 6 skeleton cards
    for (let i = 0; i < 6; i++) {
      const card = document.createElement('div');
      card.className = 'td-skeleton';
      card.innerHTML = `
        <div class="td-skeleton-img"></div>
        <div class="td-skeleton-body">
          <div class="td-skeleton-line"></div>
          <div class="td-skeleton-line short"></div>
          <div class="td-skeleton-line xshort"></div>
          <div class="td-skeleton-line short"></div>
        </div>`;
      vehiclesGrid.appendChild(card);
    }

    // If we hid real cards, restore them after the MIN_SHIMMER_MS delay
    if (hasRealCards) {
      setTimeout(() => {
        document.querySelectorAll('.td-skeleton').forEach(sk => sk.remove());
        realCards.forEach(card => {
          card.style.display = '';
          card.style.animation = 'td-fadeIn 0.4s ease forwards';
        });
      }, MIN_SHIMMER_MS);
    }
  }

  /* ── 7. Image lazy-load shimmer ──────────────────────────── */
  function initLazyImages() {
    document.querySelectorAll('.vehicle-image-wrapper img, .tbl-img img').forEach(img => {
      const wrapper = img.parentElement;
      if (!wrapper.classList.contains('td-img-shimmer')) {
        wrapper.classList.add('td-img-shimmer');
      }

      const imgLoadTime = Date.now();

      function resolveImage() {
        const elapsed = Date.now() - imgLoadTime;
        const remaining = Math.max(0, MIN_SHIMMER_MS - elapsed);
        setTimeout(() => {
          img.classList.add('loaded');
          wrapper.classList.add('done');

          const skeleton = document.querySelector('.td-skeleton');
          if (skeleton) {
            document.querySelectorAll('.td-skeleton').forEach(sk => {
              sk.style.opacity = '0';
              setTimeout(() => sk.remove(), 300);
            });
          }
        }, remaining);
      }

      if (img.complete && img.naturalWidth > 0) {
        resolveImage();
      } else {
        img.addEventListener('load', resolveImage);
        img.addEventListener('error', () => wrapper.classList.add('done'));
      }
    });
  }

  /* ── 8. Page Events ──────────────────────────────────────── */
  window.addEventListener('load', () => {
    finishProgressBar();
  });

  document.addEventListener('DOMContentLoaded', () => {
    initLazyImages();
    insertSkeletons();
  });

  // Clean up if user uses the browser back button (bfcache)
  window.addEventListener('pageshow', (e) => {
    if (e.persisted) {
      hideOverlay();
      removeButtonSpinners();

      const grid = document.querySelector('.vehicles-grid');
      if (grid) {
        document.querySelectorAll('.td-search-skeleton').forEach(sk => sk.remove());
        grid.querySelectorAll('.vehicle-card').forEach(c => c.style.display = '');
      }

      const inlineSpinner = document.getElementById('td-search-spinner');
      if (inlineSpinner) inlineSpinner.remove();

      document.querySelectorAll('form').forEach(f => f.dataset.isSubmitting = 'false');
    }
  });

})();
