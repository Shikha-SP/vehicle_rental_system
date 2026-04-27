/**
 * TD RENTALS — Loading Indicators
 * Covers: page transitions, form submits, filter search, image lazy load
 */
(function () {
  'use strict';

  /* ── helpers ── */
  function el(id) { return document.getElementById(id); }
  function make(tag, cls) {
    var d = document.createElement(tag);
    if (cls) d.className = cls;
    return d;
  }

  /* ══════════════════════════════════════════
     1. INJECT persistent DOM elements once
  ══════════════════════════════════════════ */
  document.addEventListener('DOMContentLoaded', function () {

    // Top progress bar
    if (!el('td-progress-bar')) {
      var bar = make('div'); bar.id = 'td-progress-bar';
      document.body.prepend(bar);
    }

    // Full-page overlay
    if (!el('td-page-loader')) {
      var overlay = make('div'); overlay.id = 'td-page-loader';
      overlay.innerHTML =
        '<div class="loader-logo">TD <span>RENTALS</span></div>' +
        '<div class="loader-bar-track"><div class="loader-bar-fill"></div></div>' +
        '<div class="loader-text" id="td-loader-msg">Loading…</div>';
      document.body.appendChild(overlay);
    }

    /* ── 2. Page-transition progress bar on <a> clicks ── */
    document.addEventListener('click', function (e) {
      var link = e.target.closest('a[href]');
      if (!link) return;
      var href = link.getAttribute('href');
      // skip anchors, external, javascript:, target=_blank
      if (!href || href.startsWith('#') || href.startsWith('javascript') ||
          href.startsWith('http') || link.target === '_blank') return;

      startProgressBar();
    });

    /* ── 3. Full overlay on form submits ── */
    document.addEventListener('submit', function (e) {
      var form = e.target;
      // Don't show full overlay for GET forms — those use inline spinner below
      if (form.method && form.method.toUpperCase() === 'GET') return;

      // Find the submit button and add spinner to it
      var btn = form.querySelector('[type="submit"]');
      if (btn) addButtonSpinner(btn);

      // Show the full overlay with a contextual message
      var msg = getFormMessage(form);
      showOverlay(msg);
    });

    /* ── 4. Inline spinner + grid fade on search/filter GET forms ── */
    /* Uses event delegation so it catches ALL GET forms regardless of method case */
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.method || form.method.toUpperCase() !== 'GET') return;

      // Spinner on the submit button
      var btn = form.querySelector('[type="submit"]');
      if (btn) addButtonSpinner(btn);

      // Find the grid — target the container so fadeInUp on cards doesn't fight us
      var resultsGrid = document.querySelector(
        '.vehicles-grid, .car-grid, .vehicle-cards, .results-container'
      );

      if (resultsGrid) {
        // Prevent instant submit — fade first, THEN submit after animation is visible
        e.preventDefault();

        // Kill card animations so they don't override the fade
        var cards = resultsGrid.querySelectorAll('.vehicle-card, .car-card');
        cards.forEach(function(card) {
          card.style.animation = 'none';
        });

        // Fade the grid out
        resultsGrid.style.transition = 'opacity 0.8s ease';
        resultsGrid.style.opacity = '0.2';

        // Submit after fade is visible (800ms matches the transition)
        setTimeout(function() {
          form.submit();
        }, 850);
      }
    });

    /* ── 5. Image lazy loading with shimmer ── */
    initLazyImages();

    /* ── 6. Hide overlay when page is fully loaded ── */
    /* pageshow fires on normal load AND when browser restores page from back/forward cache */
    window.addEventListener('pageshow', function (e) {
      hideOverlay();
      completeProgressBar();

      // e.persisted = true means page came from bfcache (user pressed Back)
      // Reset all stuck spinners in that case
      if (e.persisted) {
        document.querySelectorAll('.td-btn-loading').forEach(function (btn) {
          btn.classList.remove('td-btn-loading');
          var inner = btn.querySelector('.td-btn-text');
          if (inner) {
            btn.innerHTML = inner.innerHTML;
          }
        });
      }
    });

    /* ── 7. Admin action buttons (approve/reject/delete) ── */
    document.querySelectorAll(
      '.approve-btn, .reject-btn, .delete-btn'
    ).forEach(function (btn) {
      btn.addEventListener('click', function () {
        setTimeout(function () { addButtonSpinner(btn); }, 0);
      });
    });

  }); // end DOMContentLoaded

  /* ══════════════════════════════════════════
     PROGRESS BAR
  ══════════════════════════════════════════ */
  var _progTimer = null;
  function startProgressBar() {
    var bar = el('td-progress-bar');
    if (!bar) return;
    bar.classList.remove('done');
    bar.style.opacity = '1';
    bar.style.width = '0%';
    // Animate to 85% — jumps to 100% on done
    var pct = 0;
    clearInterval(_progTimer);
    _progTimer = setInterval(function () {
      pct = pct < 70 ? pct + 8 : pct < 85 ? pct + 1 : pct;
      bar.style.width = pct + '%';
    }, 120);
  }

  function completeProgressBar() {
    var bar = el('td-progress-bar');
    if (!bar) return;
    clearInterval(_progTimer);
    bar.style.width = '100%';
    setTimeout(function () { bar.classList.add('done'); }, 300);
  }

  /* ══════════════════════════════════════════
     FULL-PAGE OVERLAY
  ══════════════════════════════════════════ */
  function showOverlay(msg) {
    var overlay = el('td-page-loader');
    var msgEl = el('td-loader-msg');
    if (!overlay) return;
    if (msgEl) msgEl.textContent = msg || 'Loading…';
    overlay.classList.add('active');
    startProgressBar();
  }

  function hideOverlay() {
    var overlay = el('td-page-loader');
    if (overlay) overlay.classList.remove('active');
    completeProgressBar();
    // Restore any dimmed grids
    document.querySelectorAll('.vehicles-grid, .car-grid').forEach(function (g) {
      g.style.opacity = '';
    });
  }

  /* ══════════════════════════════════════════
     BUTTON SPINNER
  ══════════════════════════════════════════ */
  function addButtonSpinner(btn) {
    if (btn.classList.contains('td-btn-loading')) return;
    // Wrap existing text so we can hide it
    if (!btn.querySelector('.td-btn-text')) {
      var span = make('span', 'td-btn-text');
      span.innerHTML = btn.innerHTML;
      btn.innerHTML = '';
      btn.appendChild(span);
    }
    btn.classList.add('td-btn-loading');
  }

  /* ══════════════════════════════════════════
     CONTEXTUAL MESSAGES
  ══════════════════════════════════════════ */
  function getFormMessage(form) {
    var id = form.id || '';
    var action = (form.action || '').toLowerCase();
    if (id === 'listCarForm' || action.includes('add.php'))       return 'Listing your vehicle…';
    if (action.includes('payment') || action.includes('pay'))     return 'Processing payment…';
    if (action.includes('login'))                                  return 'Signing you in…';
    if (action.includes('signup') || action.includes('register')) return 'Creating your account…';
    if (action.includes('edit') || action.includes('update'))     return 'Saving changes…';
    if (action.includes('delete'))                                 return 'Deleting…';
    if (action.includes('booking') || action.includes('cancel'))  return 'Updating booking…';
    return 'Please wait…';
  }

  /* ══════════════════════════════════════════
     IMAGE LAZY LOAD WITH SHIMMER
  ══════════════════════════════════════════ */
  function initLazyImages() {
    // Find all td-img-real images (vehicle card images with shimmer)
    document.querySelectorAll('img.td-img-real').forEach(function (img) {

      // Always start image as invisible so shimmer shows
      img.style.opacity = '0';

      function revealImage() {
        setTimeout(function () {
          var shimmer = img.previousElementSibling;
          if (shimmer && shimmer.classList.contains('td-img-shimmer')) {
            shimmer.style.transition = 'opacity 0.4s ease';
            shimmer.style.opacity = '0';
            setTimeout(function() { shimmer.style.display = 'none'; }, 400);
          }
          img.style.transition = 'opacity 0.5s ease';
          img.style.opacity = '1';
        }, 1200); // shimmer shows for 3 seconds
      }

      // Whether image is cached or not — always delay the reveal
      if (img.complete) {
        revealImage();
      } else {
        img.addEventListener('load', revealImage);
        img.addEventListener('error', function () {
          var shimmer = img.previousElementSibling;
          if (shimmer) shimmer.style.display = 'none';
        });
      }
    });

    // For non-shimmer images — standard lazy class
    var imgs = document.querySelectorAll('img[src]:not(.td-img-real)');
    imgs.forEach(function (img) {
      if (img.complete && img.naturalWidth > 0) return;
      img.classList.add('td-lazy');
      img.addEventListener('load', function () {
        img.classList.remove('td-lazy');
        img.classList.add('loaded');
      });
      img.addEventListener('error', function () {
        img.classList.remove('td-lazy');
      });
    });
  }

  /* ══════════════════════════════════════════
     EXPOSE for manual use in PHP pages
     Usage: TDLoader.show('Searching…') / TDLoader.hide()
  ══════════════════════════════════════════ */
  window.TDLoader = {
    show: showOverlay,
    hide: hideOverlay,
    progress: { start: startProgressBar, complete: completeProgressBar },
    buttonSpinner: addButtonSpinner
  };

})();