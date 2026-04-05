<?php include '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/info.css">

<main class="info-page">
    <button class="info-back-btn" onclick="history.back()">← Back</button>
    <h1>Contact Us</h1>
    <p>If you have questions, feedback, or need support, please use the form below to reach us.</p>

    <form action="../ajax/contact_submit.php" method="post" class="contact-form">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="message">Message:</label>
        <textarea id="message" name="message" required></textarea>

        <button type="submit">Send Message</button>
    </form>
</main>

<?php include '../../includes/footer.php'; ?>