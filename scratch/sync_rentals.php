<?php
require_once 'config/db.php';

echo "Syncing completed rentals...\n";

// 1. Get all users
$users_res = $conn->query("SELECT id, medal, completed_rentals FROM users");

while ($user = $users_res->fetch_assoc()) {
    $user_id = $user['id'];
    
    // 2. Count confirmed bookings for this user
    $count_res = $conn->query("SELECT COUNT(*) as total FROM bookings WHERE user_id = $user_id AND (status = 'confirmed' OR status = 'completed')");
    $count_row = $count_res->fetch_assoc();
    $actual_count = (int)$count_row['total'];
    
    echo "User #$user_id: Current Count={$user['completed_rentals']}, Actual Count=$actual_count\n";
    
    // 3. Update the count if different
    if ($actual_count != $user['completed_rentals']) {
        $conn->query("UPDATE users SET completed_rentals = $actual_count WHERE id = $user_id");
    }
    
    // 4. Recalculate Medal based on the new count
    $new_medal = 'NONE';
    if ($actual_count >= 15) {
        $new_medal = 'GOLD';
    } elseif ($actual_count >= 7) {
        $new_medal = 'SILVER';
    } elseif ($actual_count >= 3) {
        $new_medal = 'BRONZE';
    }
    
    if ($new_medal !== $user['medal']) {
        echo "Updating medal for User #$user_id: {$user['medal']} -> $new_medal\n";
        $conn->query("UPDATE users SET medal = '$new_medal' WHERE id = $user_id");
    }

    // 5. Award missing milestone codes
    $milestones = [
        'BRONZE' => ['prefix' => 'BRONZE5', 'percent' => 5],
        'SILVER' => ['prefix' => 'SILVER10', 'percent' => 10],
        'GOLD'   => ['prefix' => 'GOLD20', 'percent' => 20],
    ];

    $medal_rank = ['NONE' => 0, 'BRONZE' => 1, 'SILVER' => 2, 'GOLD' => 3];
    $current_rank = $medal_rank[$new_medal];

    foreach ($milestones as $m_medal => $m_data) {
        if ($current_rank >= $medal_rank[$m_medal]) {
            $prefix = $m_data['prefix'];
            $percent = $m_data['percent'];
            
            // Check if user already has a code starting with this prefix
            $code_check = $conn->query("SELECT id FROM discount_codes WHERE owner_user_id = $user_id AND code LIKE '$prefix%'");
            if ($code_check->num_rows === 0) {
                $unique_suffix = substr(md5(uniqid($user_id, true)), 0, 4);
                $personal_code = $prefix . "-U" . $user_id . "-" . $unique_suffix;
                
                echo "Awarding $prefix code to User #$user_id: $personal_code\n";
                $conn->query("
                    INSERT INTO discount_codes (code, type, discount_percent, discount_flat, max_uses, owner_user_id) 
                    VALUES ('$personal_code', 'percent', $percent, 0, 1, $user_id)
                ");
            }
        }
    }
}

echo "Sync complete!\n";
