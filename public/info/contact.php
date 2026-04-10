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
    
    <h1>Contact Us</h1>
    <p>If you have questions, feedback, or need support, please use the form below to reach us.</p>

    <!-- Contact form that submits data using POST to the contact_submit.php handler -->
    <form action="../ajax/contact_submit.php" method="post" class="contact-form">
        <!-- Input field for the user's name (required) -->
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>

        <!-- Input field for the user's email address (required) -->
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <!-- Text area for the user's message (required) -->
        <label for="message">Message:</label>
        <textarea id="message" name="message" required></textarea>

        <!-- Submit button to trigger the form action -->
        <button type="submit">Send Message</button>
    </form>
</main>

<?php 
// Include the global footer for the website
include '../../includes/footer.php'; 
?>