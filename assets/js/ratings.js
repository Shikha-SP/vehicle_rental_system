// -------------
const labels = ['', 'Terrible', 'Poor', 'Average', 'Good', 'Excellent'];
const reviewModal = document.getElementById('reviewModal');
let selectedRating = reviewModal && reviewModal.dataset.rating ? parseInt(reviewModal.dataset.rating) : 0;

// rating - main page stars click opens the review modal with preselected stars
const stars = document.querySelectorAll(".stars i");
if (stars.length > 0) {
    stars.forEach((star, index1) => {
        star.addEventListener("click", () => {
            const ratingValue = index1 + 1;
            selectedRating = ratingValue;

            // Visual update for modal stars
            const modalStars = document.querySelectorAll('.stars-modal i');
            modalStars.forEach((ms, index2) => {
                ms.classList.toggle("fa-solid", index2 < ratingValue);
                ms.classList.toggle("fa-regular", index2 >= ratingValue);
                ms.classList.toggle("active", index2 < ratingValue);
            });

            // Update modal rating label
            const modalLabel = document.getElementById('modalRatingLabel');
            if (modalLabel) {
                modalLabel.textContent = labels[ratingValue];
            }

            // Open the modal
            const reviewModalEl = document.getElementById('reviewModal');
            if (reviewModalEl) {
                reviewModalEl.classList.add('active');
            }
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

                    const deleteBtn = document.getElementById('deleteRatingBtn');
                    if (deleteBtn) deleteBtn.style.display = 'inline-flex';

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

// Delete review
document.getElementById('deleteRatingBtn')
    ?.addEventListener('click', () => {
        if (!confirm('Are you sure you want to delete your rating and review?')) {
            return;
        }

        const ratingBox = document.querySelector('.rating-box');
        const vehicleId = ratingBox.dataset.vehicleId;
        const bookingId = ratingBox.dataset.bookingId;

        fetch('delete_rating.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                vehicle_id: vehicleId,
                booking_id: bookingId
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('⭐ Rating deleted!');

                    // Reset main page stars visually
                    const mainStars = document.querySelectorAll(".stars i");
                    mainStars.forEach(s => {
                        s.classList.remove('fa-solid', 'active');
                        s.classList.add('fa-regular');
                    });

                    // Hide the delete button and reset text
                    const deleteBtn = document.getElementById('deleteRatingBtn');
                    if (deleteBtn) deleteBtn.style.display = 'none';

                    const openBtn = document.getElementById('openReviewModal');
                    if (openBtn) openBtn.textContent = 'Write a review';

                    // Reload after a short delay to update the UI
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Delete rating error:', err);
                alert('An error occurred while deleting your rating.');
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

// Dynamic button text based on textarea content
const reviewTextEl = document.getElementById('reviewText');
const submitReviewEl = document.getElementById('submitReview');
if (reviewTextEl && submitReviewEl) {
    const isUpdate = submitReviewEl.textContent.includes('Update');
    const updateButtonText = () => {
        const hasText = reviewTextEl.value.trim().length > 0;
        if (isUpdate) {
            submitReviewEl.textContent = hasText ? 'Update Review' : 'Update Rating';
        } else {
            submitReviewEl.textContent = hasText ? 'Post Review' : 'Post Rating';
        }
    };

    // Run on init
    updateButtonText();

    // Run on typing
    reviewTextEl.addEventListener('input', updateButtonText);
    reviewTextEl.addEventListener('change', updateButtonText);

    // Run when the main star ratings are clicked (which updates rating selection)
    document.querySelectorAll(".stars i").forEach(star => {
        star.addEventListener('click', updateButtonText);
    });
}