// rating
const stars = document.querySelectorAll(".stars i");
if (stars.length > 0) {
    stars.forEach((star, index1) => {
        star.addEventListener("click", () => {
            const ratingValue = index1 + 1;

            // Visual update
            stars.forEach((star, index2) => {
                if (index1 >= index2) {
                    star.classList.add("active", "fa-solid");
                    star.classList.remove("fa-regular");
                } else {
                    star.classList.remove("active", "fa-solid");
                    star.classList.add("fa-regular");
                }
            });

            // Get IDs from data attributes
            const ratingBox = document.querySelector(".rating-box");
            const vehicleId = ratingBox ? ratingBox.dataset.vehicleId : null;
            const bookingId = ratingBox ? ratingBox.dataset.bookingId : null;

            // Send rating via fetch (no page reload)
            fetch('submit_ratings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    rating: ratingValue,
                    vehicle_id: vehicleId,
                    booking_id: bookingId
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.updated ? '⭐ Rating updated!' : '⭐ Rating saved!');
                    }
                })
                .catch(err => console.error('Rating error:', err));
        });
    });
}

function showToast(message) {
    // Remove any existing toast first
    const existing = document.querySelector('.rating-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'rating-toast';
    toast.textContent = message;

    document.querySelector('.rating-box').appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => toast.classList.add('show'));

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}
// -------------
const labels = ['', 'Terrible', 'Poor', 'Average', 'Good', 'Excellent'];

// Open / Close
document.getElementById('openReviewModal')
    ?.addEventListener('click', () => {
        document.getElementById('reviewModal').classList.add('active');
    });

document.getElementById('closeModal')
    ?.addEventListener('click', () => {
        document.getElementById('reviewModal').classList.remove('active');
    });

// Close on overlay click
document.getElementById('reviewModal')
    ?.addEventListener('click', (e) => {
        if (e.target.id === 'reviewModal')
            document.getElementById('reviewModal').classList.remove('active');
    });

// Stars inside modal
const modalStars = document.querySelectorAll('.stars-modal i');
const reviewModal = document.getElementById('reviewModal');
let selectedRating = reviewModal && reviewModal.dataset.rating ? parseInt(reviewModal.dataset.rating) : 0;

modalStars.forEach((star, i) => {
    // Hover preview
    star.addEventListener('mouseenter', () => {
        modalStars.forEach((s, j) => {
            s.classList.toggle('fa-solid', j <= i);
            s.classList.toggle('fa-regular', j > i);
        });
        document.getElementById('modalRatingLabel').textContent = labels[i + 1];
    });

    // Reset to selected on mouse leave
    star.addEventListener('mouseleave', () => {
        modalStars.forEach((s, j) => {
            s.classList.toggle('fa-solid', j < selectedRating);
            s.classList.toggle('fa-regular', j >= selectedRating);
        });
        document.getElementById('modalRatingLabel').textContent = labels[selectedRating] || 'Select a rating';
    });

    star.addEventListener('click', () => {
        selectedRating = i + 1;
        modalStars.forEach((s, j) => {
            s.classList.toggle('fa-solid', j < selectedRating);
            s.classList.toggle('fa-regular', j >= selectedRating);
        });
        document.getElementById('modalRatingLabel').textContent = labels[selectedRating] || 'Select a rating';
    });
});

// Submit review
document.getElementById('submitReview')
    ?.addEventListener('click', () => {
        const reviewText = document.getElementById('reviewText').value.trim();
        const ratingBox = document.querySelector('.rating-box');
        const vehicleId = ratingBox.dataset.vehicleId;
        const bookingId = ratingBox.dataset.bookingId;

        if (!selectedRating) {
            alert('Please select a star rating.');
            return;
        }

        fetch('submit_ratings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                rating: selectedRating,
                review: reviewText,
                vehicle_id: vehicleId,
                booking_id: bookingId
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('reviewModal').classList.remove('active');
                    showToast(data.updated ? '⭐ Review updated!' : '⭐ Review posted!');

                    const trigger = document.getElementById('openReviewModal');
                    if (trigger) trigger.textContent = 'Edit your review';

                    // Update main stars to match
                    const mainStars = document.querySelectorAll(".stars i");
                    mainStars.forEach((s, j) => {
                        s.classList.toggle('fa-solid', j < selectedRating);
                        s.classList.toggle('fa-regular', j >= selectedRating);
                        s.classList.toggle('active', j < selectedRating);
                    });

                    // Reload after a short delay to show the review in the list and update average
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Review submission error:', err);
                alert('An error occurred while submitting your review.');
            });
    });

// Reply toggle
document.querySelectorAll('.btn-toggle-reply').forEach(btn => {
    btn.addEventListener('click', () => {
        const reviewId = btn.dataset.reviewId;
        const box = document.getElementById('reply-input-' + reviewId);
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
    });
});

// Reply submit
document.querySelectorAll('.btn-submit-reply').forEach(btn => {
    btn.addEventListener('click', () => {
        const reviewId = btn.dataset.reviewId;
        const text = document.getElementById('reply-text-' + reviewId).value.trim();
        const msgEl = document.getElementById('reply-msg-' + reviewId);

        if (!text) {
            msgEl.textContent = 'Reply cannot be empty.';
            msgEl.style.color = 'red';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Posting…';

        const fd = new FormData();
        fd.append('review_id', reviewId);
        fd.append('reply_text', text);

        fetch('../api/reply_review.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msgEl.textContent = '✅ Reply posted!';
                    msgEl.style.color = 'green';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msgEl.textContent = '❌ ' + data.message;
                    msgEl.style.color = 'red';
                    btn.disabled = false;
                    btn.textContent = 'Post Reply';
                }
            })
            .catch(() => {
                msgEl.textContent = '❌ Network error.';
                msgEl.style.color = 'red';
                btn.disabled = false;
                btn.textContent = 'Post Reply';
            });
    });
});