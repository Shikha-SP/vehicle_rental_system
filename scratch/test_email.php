<?php
require_once 'includes/functions.php';

function testEmail($email) {
    echo "Testing $email: " . (is_email_genuine($email) ? "GENUINE" : "NOT GENUINE") . "\n";
}

testEmail('test@gmail.com');
testEmail('fake@thisdomaindoesnotexist123456789.com');
testEmail('invalid-email');
?>
