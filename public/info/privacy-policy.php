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
    
    <h1>Privacy Policy</h1>
    <!-- Main content paragraphs explaining the privacy policy -->
    <p>
        At TD Rentals, we value your privacy. All personal information collected is used solely for
        providing our services and will never be sold to third parties. We use secure methods to
        store and protect your data.
    </p>
    <p>
        By using our website, you agree to our privacy practices. For more details, please contact
        us through the Contact page.
    </p>
</main>

<?php 
// Include the global footer for the website
include '../../includes/footer.php'; 
?>