/* ============================================================
   TD RENTALS — Loading Indicators (loading.js)
   Handles: progress bar, overlay, button spinners,
            inline spinner, skeleton cards, image shimmer
   ============================================================ */

(function () {
  'use strict';

  /* ── 1. Top progress bar ──────────────────────────────────── */
  const bar = document.getElementById('td-progress-bar');

  function progressStart() {
    if (!bar) return;
    bar.style.transition = 'none';
    bar.style.opacity    = '1';
    bar.style.width      = '0%';
    requestAnimationFrame(() => {
      bar.style.transition = 'width 0.4s ease';
      bar.style.width      = '70%';
    });
  }

  function progressDone() {
    if (!bar) return;
    bar.style.width = '100%';
    setTimeout(() => { bar.classList.add('done'); }, 300);
    setTimeout(() => {
      bar.classList.remove('done');
      bar.style.width = '0%';
    }, 750);
  }

  /* Start bar on navigation links (not hash, not target=_blank) */
  document.querySelectorAll('a[href]').forEach(a => {
    const href = a.getAttribute('href') || '';
    if (
      href.startsWith('#') ||
      href.startsWith('javascript') ||
      a.target === '_blank' ||
      href === '' ||
      a.classList.contains('no-progress')
    ) return;
    a.addEventListener('click', () => progressStart());
  });

  /* Stop bar when page finishes loading */
  window.addEventListener('load', () => progressDone());
  /* Also hide on DOMContentLoaded for fast pages */
  document.addEventListener('DOMContentLoaded', () => progressDone());

  /* ── 2. Full-page overlay helpers ────────────────────────── */
  const overlay    = document.getElementById('td-overlay');
  const overlayMsg = document.getElementById('td-overlay-msg');

  window.TDLoading = {
    show: function (msg) {
      if (!overlay) return;
      if (overlayMsg) overlayMsg.textContent = msg || 'Loading…';
      overlay.classList.add('active');
    },
    hide: function () {
      if (!overlay) return;
      overlay.classList.remove('active');
    }
  };

  /* ── 3. Form submissions → overlay + button spinner ──────── */
  document.querySelectorAll('form').forEach(form => {
    /* Skip forms that opt out */
    if (form.dataset.noLoading) return;

    form.addEventListener('submit', function (e) {
      /* Don't show overlay if HTML5 validation will stop submission */
      if (!form.checkValidity()) return;

      const submitBtn  = form.querySelector('[type="submit"]');
      const btnVal     = submitBtn ? (submitBtn.value || submitBtn.textContent.trim()) : '';
      const customMsg  = form.dataset.loadingMsg || inferMsg(btnVal);

      /* Button spinner + disable */
      if (submitBtn) {
        const spinner = document.createElement('span');
        spinner.className = 'td-btn-spinner';
        submitBtn.prepend(spinner);
        submitBtn.disabled = true;
      }

      /* Full-page overlay */
      window.TDLoading.show(customMsg);
    });
  });

  function inferMsg(btnText) {
    const t = btnText.toLowerCase();
    if (t.includes('pay'))      return 'Processing payment…';
    if (t.includes('book'))     return 'Confirming booking…';
    if (t.includes('login') || t.includes('log in')) return 'Signing you in…';
    if (t.includes('register') || t.includes('sign up')) return 'Creating your account…';
    if (t.includes('submit'))   return 'Submitting…';
    if (t.includes('save'))     return 'Saving changes…';
    if (t.includes('upload'))   return 'Uploading…';
    if (t.includes('list'))     return 'Listing your vehicle…';
    if (t.includes('approve'))  return 'Approving vehicle…';
    if (t.includes('reject'))   return 'Rejecting vehicle…';
    if (t.includes('delete'))   return 'Deleting…';
    if (t.includes('update'))   return 'Updating…';
    if (t.includes('search') || t.includes('filter') || t.includes('apply')) return 'Searching…';
    return 'Please wait…';
  }

  /* ── 4. Inline spinner for search/filter forms ───────────── */
  document.querySelectorAll('form.search-filters-form, form[data-inline-loading]').forEach(form => {
    const resultArea = document.querySelector(
      form.dataset.resultsTarget || '.vehicles-content, .results-area'
    );

    form.addEventListener('submit', function () {
      if (!form.checkValidity()) return;

      /* If a results area exists, show inline spinner there */
      if (resultArea) {
        const spinner = document.createElement('div');
        spinner.className = 'td-inline-spinner';
        spinner.id = 'td-search-spinner';
        spinner.textContent = 'Finding vehicles…';
        resultArea.prepend(spinner);
      }
      /* No full-page overlay for filter forms (already handled above) */
    });
  });

  /* ── 5. Skeleton cards for vehicle listings ──────────────── */
  const vehiclesGrid = document.querySelector('.vehicles-grid');
  if (vehiclesGrid && vehiclesGrid.children.length === 0) {
    renderSkeletons(vehiclesGrid, 6);
  }

  function renderSkeletons(container, count) {
    for (let i = 0; i < count; i++) {
      const card = document.createElement('div');
      card.className = 'td-skeleton-card';
      card.innerHTML = `
        <div class="td-skeleton-img"></div>
        <div class="td-skeleton-body">
          <div class="td-skeleton-line"></div>
          <div class="td-skeleton-line short"></div>
          <div class="td-skeleton-line xshort"></div>
          <div class="td-skeleton-line short"></div>
        </div>`;
      container.appendChild(card);
    }
  }

  /* ── 6. Image lazy-load shimmer ──────────────────────────── */
  function applyImageShimmer() {
    document.querySelectorAll('.vehicle-image-wrapper img, .tbl-img img').forEach(img => {
      const wrapper = img.parentElement;
      if (!wrapper.classList.contains('td-img-shimmer')) {
        wrapper.classList.add('td-img-shimmer');
      }
      if (img.complete && img.naturalWidth > 0) {
        img.classList.add('loaded');
        wrapper.classList.add('done');
      } else {
        img.addEventListener('load', () => {
          img.classList.add('loaded');
          wrapper.classList.add('done');
        });
        img.addEventListener('error', () => {
          wrapper.classList.add('done');
        });
      }
    });
  }
  applyImageShimmer();

  /* ── 7. Admin approve/reject action spinners ─────────────── */
  document.querySelectorAll('button[name="action"]').forEach(btn => {
    btn.addEventListener('click', function () {
      const isApprove = this.value === 'approve';
      /* Show overlay with contextual message */
      window.TDLoading.show(isApprove ? 'Approving vehicle…' : 'Rejecting vehicle…');

      /* Spinner on the clicked button */
      const spinner = document.createElement('span');
      spinner.className = 'td-btn-spinner';
      this.prepend(spinner);
      this.disabled = true;

      /* Disable the sibling action button too */
      const siblingBtns = this.closest('td')?.querySelectorAll('button[name="action"]');
      if (siblingBtns) siblingBtns.forEach(b => { b.disabled = true; });
    });
  });

  /* ── 8. Auto-hide overlay on page load ───────────────────── */
  window.addEventListener('load', () => {
    window.TDLoading.hide();
  });

})();
