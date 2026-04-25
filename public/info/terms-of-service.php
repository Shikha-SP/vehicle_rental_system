<?php 
// Include the global header for the website (contains nav, common head tags)
include '../../includes/header.php'; 
?>
<!-- Link to the specific CSS file for information pages -->
<link rel="stylesheet" href="../../assets/css/info.css">

<!-- Main container for the info page content -->
<main class="info-page">
    <!-- Button to let the user navigate back to the previous page using browser history -->
    <button class="info-back-btn" onclick="history.back()">← Back</button>
    
    <h1>Terms of Service</h1>
    <!-- Main content paragraphs outlining the terms and conditions -->
    <p>
        Welcome to TD Rentals. By accessing or using our website, you agree to comply with our
        terms and conditions. These terms govern your use of our services, bookings, and website
        content.
    </p>
    <p>
        TD Rentals reserves the right to modify or terminate services at any time. Misuse of the
        website or fraudulent bookings may result in account suspension.
    </p>
    <p>
        Please read all terms carefully. For questions, contact us through the Contact page.
    </p>
</main>

<?php 
// Include the global footer for the website
include '../../includes/footer.php'; 
?>