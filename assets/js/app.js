/* ============================================================
   TD RENTALS — Main JavaScript
   ============================================================ */

(function () {
  'use strict';

  /* ── Navbar scroll effect ──────────────────────────────── */
  const navbar = document.getElementById('navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      navbar.style.background = window.scrollY > 10
        ? 'hsla(0,0%,7%,0.97)'
        : 'hsla(0,0%,7%,0.85)';
    }, { passive: true });
  }

  /* ── Mobile hamburger ──────────────────────────────────── */
  const hamburger = document.getElementById('hamburger');
  const navLinks  = document.getElementById('navLinks');
  if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => {
      navLinks.classList.toggle('open');
    });
    document.addEventListener('click', (e) => {
      if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
        navLinks.classList.remove('open');
      }
    });
  }

  /* ── Booking price calculator + availability check ─────── */
  const pickupInput  = document.getElementById('pickupDate');
  const dropoffInput = document.getElementById('dropoffDate');
  const rentalEl     = document.getElementById('rentalTotal');
  const insEl        = document.getElementById('insuranceTotal');
  const grandEl      = document.getElementById('grandTotal');
  const summaryEl    = document.getElementById('bookingSummary');
  const submitBtn    = document.querySelector('#bookingForm button[type="submit"]');

  // If a booking was just confirmed successfully, skip ALL availability checks
  // so we never show "not available" after a successful booking
  const bookingJustConfirmed = !!document.querySelector('.alert-success');

  // Availability banner (injected dynamically)
  let availBanner = null;
  function getAvailBanner() {
    if (!availBanner) {
      availBanner = document.createElement('div');
      availBanner.id = 'availBanner';
      availBanner.style.cssText = 'display:none;padding:.65rem 1rem;border-radius:4px;font-size:.8rem;font-weight:600;margin-bottom:.75rem;';
      if (summaryEl) summaryEl.parentNode.insertBefore(availBanner, summaryEl);
    }
    return availBanner;
  }

  function showAvailBanner(msg, type) {
    const b = getAvailBanner();
    b.textContent = msg;
    b.style.display = 'block';
    if (type === 'error') {
      b.style.background = 'hsl(0,50%,18%)';
      b.style.color = 'hsl(0,70%,72%)';
      b.style.border = '1px solid hsl(0,50%,30%)';
    } else {
      b.style.background = 'hsl(140,50%,14%)';
      b.style.color = 'hsl(140,60%,70%)';
      b.style.border = '1px solid hsl(140,40%,28%)';
    }
  }

  function hideAvailBanner() {
    if (availBanner) availBanner.style.display = 'none';
  }

  // Extract car ID from URL
  const carIdMatch = window.location.search.match(/[?&]id=(\d+)/);
  const carId = carIdMatch ? carIdMatch[1] : null;

  // Extract daily rate from the booking price display
  const priceEl = document.querySelector('.booking-price');
  let pricePerDay = 0;
  if (priceEl) {
    const match = priceEl.textContent.match(/[\d,]+/);
    if (match) pricePerDay = parseInt(match[0].replace(/,/g, ''), 10);
  }

  function updateSummaryUI(days, rental, insurance, grand) {
    if (!rentalEl || !insEl || !grandEl) return;
    const rows = summaryEl ? summaryEl.querySelectorAll('.summary-row') : [];
    if (rows[0]) rows[0].querySelector('span').textContent = `${days} Day${days !== 1 ? 's' : ''} Rental`;
    rentalEl.textContent = 'रू' + rental.toLocaleString();
    insEl.textContent    = 'रू' + insurance.toLocaleString();
    grandEl.textContent  = 'रू' + grand.toLocaleString();
  }

  let availCheckTimer = null;

  function recalc() {
    if (!pickupInput || !dropoffInput) return;
    const pickup  = pickupInput.value;
    const dropoff = dropoffInput.value;

    if (!pickup || !dropoff) return;

    const p = new Date(pickup);
    const d = new Date(dropoff);
    if (d <= p) return;

    const days      = Math.round((d - p) / 86400000);
    const rental    = days * pricePerDay;
    const insurance = days * 150;
    const grand     = rental + insurance;
    updateSummaryUI(days, rental, insurance, grand);

    // Skip availability check entirely if booking was just confirmed —
    // the car is booked for these dates now, we don't want to show "not available"
    if (bookingJustConfirmed) return;

    // Skip availability check if no carId (index.php featured car has no ?id= param)
    if (!carId) return;

    // Debounced availability check via API
    clearTimeout(availCheckTimer);
    availCheckTimer = setTimeout(() => {
      hideAvailBanner();
      if (submitBtn) submitBtn.disabled = true;

      fetch(`../../ajax/calculator.php?car_id=${carId}&pickup_date=${pickup}&dropoff_date=${dropoff}`)
        .then(r => r.json())
        .then(data => {
          if (submitBtn) submitBtn.disabled = false;
          if (data.error) {
            showAvailBanner('⚠ ' + data.error, 'error');
            if (submitBtn) submitBtn.disabled = true;
            return;
          }
          if (data.available === false) {
            showAvailBanner('✗ Vehicle not available for the selected dates.', 'error');
            if (submitBtn) submitBtn.disabled = true;
          } else if (data.available === true) {
            showAvailBanner('✓ Vehicle is available for these dates!', 'success');
          }
          // Update totals from server if returned
          if (data.grand_total) {
            updateSummaryUI(data.days, data.rental_total, data.insurance_fee, data.grand_total);
          }
        })
        .catch(() => {
          if (submitBtn) submitBtn.disabled = false;
          hideAvailBanner();
        });
    }, 500);
  }

  // Enforce dropoff min = day after pickup
  function enforceDropoffMin() {
    if (!pickupInput || !dropoffInput) return;
    const p = pickupInput.value;
    if (!p) return;
    const nextDay = new Date(p);
    nextDay.setDate(nextDay.getDate() + 1);
    const nextDayStr = nextDay.toISOString().split('T')[0];
    dropoffInput.min = nextDayStr;
    if (dropoffInput.value && dropoffInput.value <= p) {
      dropoffInput.value = nextDayStr;
    }
  }

  if (pickupInput) {
    const todayStr = new Date().toISOString().split('T')[0];
    pickupInput.min = todayStr;
    enforceDropoffMin();

    pickupInput.addEventListener('change', () => {
      enforceDropoffMin();
      hideAvailBanner();
      recalc();
    });
  }

  if (dropoffInput) {
    dropoffInput.addEventListener('change', recalc);
  }

  // Run once on load if dates already filled
  if (pickupInput && pickupInput.value && dropoffInput && dropoffInput.value) {
    recalc();
  }

  /* ── Smooth scroll for anchor links ────────────────────── */
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  /* ── Fleet card hover: subtle glow border ──────────────── */
  document.querySelectorAll('.fleet-card').forEach(card => {
    card.addEventListener('mouseenter', () => { card.style.borderColor = 'var(--primary)'; });
    card.addEventListener('mouseleave', () => { card.style.borderColor = ''; });
  });

})();

/* ── Navbar active link ─────────────────────────────────── */
document.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', function () {
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    this.classList.add('active');
  });
});
