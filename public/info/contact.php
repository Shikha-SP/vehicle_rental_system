<?php 
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

// Pre-fill user data if logged in
$name = "";
$email = "";
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
        $name = $user['first_name'] . ' ' . $user['last_name'];
        $email = $user['email'];
    }
}

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

include '../../includes/header.php'; 
?>
<link rel="stylesheet" href="../../assets/css/login.css">
<style>
    .contact-container {
        padding: 80px 24px;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 200px);
    }
    .contact-card {
        width: 100%;
        max-width: 600px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        padding: 3rem;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    .contact-card h1 {
        font-family: 'Inter', sans-serif;
        font-weight: 800;
        font-size: 2.5rem;

        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }
    .contact-card p {
        color: var(--text-secondary);
        margin-bottom: 2rem;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        color: var(--text-secondary);
    }
    .form-group input, .form-group textarea {
        width: 100%;
        padding: 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        font-family: inherit;
    }
    .form-group textarea {
        min-height: 150px;
        resize: vertical;
    }
    .form-group input:focus, .form-group textarea:focus {
        outline: none;
        border-color: var(--red);
    }
    .btn-submit {
        width: 100%;
        padding: 1rem;
        background: var(--red);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        font-size: 1.1rem;
    }
    .btn-submit:hover {
        background: #b02020;
        transform: translateY(-2px);
    }
</style>

<div class="contact-container">
    <div class="contact-card">
        <h1>HELP US IMPROVE</h1>
        <p>Got a recommendation for our app or a feature request? We value your feedback! Tell us how we can make your rental experience even better.</p>
        
        <form action="../api/submit_contact.php" method="POST">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>" placeholder="John Doe" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="john@example.com" required>
            </div>
            
            <div class="form-group">
                <label for="message">Your Recommendation / Feedback</label>
                <textarea id="message" name="message" placeholder="What features would you like to see? How can we improve our service?" required></textarea>
            </div>
            
            <button type="submit" class="btn-submit">Send Message</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>