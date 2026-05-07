<?php
http_response_code(404);
include('../../includes/header.php');
?>

<!-- Dedicated 404 styles -->
<link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/404.css">

<div class="error-page-container">

    <!-- Large Background Text -->
    <div class="bg-404">404</div>

    <!-- Top Left Logo -->
    <a href="/vehicle_rental_collab_project/public/user/home_page.php" class="top-logo">TD RENTALS</a>

    <div class="error-content">

        <div class="status-label">
            <div class="status-line"></div>
            STATUS: 404
        </div>

        <h1 class="error-heading">
            LOST THE <span class="text-red">LINE</span>
        </h1>

        <p class="error-desc">
            Even the best drivers take a wrong turn sometimes.<br>
            The apex you're looking for has shifted or no longer exists. Let's get you back on track.
        </p>

        <div class="btn-group">
            <a href="/vehicle_rental_collab_project/public/user/home_page.php" class="btn btn-primary">
                <svg viewBox="0 0 24 24">
                    <path
                        d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z" />
                </svg>
                RETURN TO GARAGE
            </a>
            <a href="/vehicle_rental_collab_project/public/vehicle/vehicles.php" class="btn btn-secondary">
                <svg viewBox="0 0 24 24">
                    <path
                        d="M12 10.9c-.61 0-1.1.49-1.1 1.1s.49 1.1 1.1 1.1c.61 0 1.1-.49 1.1-1.1s-.49-1.1-1.1-1.1zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm2.19 12.19L6 18l3.81-8.19L18 6l-3.81 8.19z" />
                </svg>
                EXPLORE VEHICLES
            </a>
        </div>

    </div>
</div>

<?php include('../../includes/footer.php'); ?>