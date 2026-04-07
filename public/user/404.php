<?php include('../../includes/header.php'); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Lost The Line</title>
    <!-- Include identical font to Homepage.css -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <!-- Include the dedicated 404.css -->
    <link rel="stylesheet" href="../../assets/css/404.css">
</head>

<body>

    <div class="error-page-container">

        <!-- Large Background Text -->
        <div class="bg-404">404</div>

        <!-- Top Left Logo -->
        <a href="Home.php" class="top-logo">TD RENTALS</a>
        <br><br>
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
                <a href="Home.php" class="btn btn-primary">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z" />
                    </svg>
                    RETURN TO GARAGE
                </a>
                <a href="Home.php" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M12 10.9c-.61 0-1.1.49-1.1 1.1s.49 1.1 1.1 1.1c.61 0 1.1-.49 1.1-1.1s-.49-1.1-1.1-1.1zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm2.19 12.19L6 18l3.81-8.19L18 6l-3.81 8.19z" />
                    </svg>
                    EXPLORE FLEET
                </a>
            </div>

            <div class="error-badges">
                <div class="badge-item">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    ERROR TRACE: OÙ00404
                </div>
                <div class="badge-item">
                    <svg viewBox="0 0 24 24">
                        <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                        </path>
                        <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <line x1="2" y1="2" x2="22" y2="22"></line>
                    </svg>
                    LOCATION: UNKNOWN GRID
                </div>
            </div>

        </div>
    </div>

</body>

</html>

<?php include('../../includes/footer.php'); ?>