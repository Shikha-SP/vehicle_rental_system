<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

/**
 * TD RENTALS — AI CHATBOT BACKEND
 * Using Groq API (Free Tier) + Live Vehicle Database for RAG recommendations
 */

// 1. Get user input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['message']) || empty(trim($data['message']))) {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

$userMessage = trim($data['message']);

// 2. Configuration
$apiKey = '';
$apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
$model  = 'llama-3.1-8b-instant'; // 5x higher rate limit, still free, works great with strong prompting

// 3. Helper: Convert hex color code to a readable color name
function hexToColorName(string $hex): string {
    $hex = strtolower(trim($hex, '#'));
    if (strlen($hex) !== 6) return 'Unknown';

    // Parse RGB components
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Named color map (hex => name)
    $namedColors = [
        'ff0000' => 'Red',         'cc0000' => 'Dark Red',
        'e03030' => 'Red',         'ff4444' => 'Light Red',
        '00ff00' => 'Green',       '008000' => 'Dark Green',
        '00cc00' => 'Green',       '0000ff' => 'Blue',
        '0000cc' => 'Dark Blue',   '1e90ff' => 'Dodger Blue',
        '000000' => 'Black',       'ffffff' => 'White',
        'c0c0c0' => 'Silver',      '808080' => 'Grey',
        'ffd700' => 'Gold',        'ffff00' => 'Yellow',
        'ff8c00' => 'Orange',      'ff4500' => 'Orange Red',
        '8b4513' => 'Brown',       'a52a2a' => 'Brown',
        '800080' => 'Purple',      'ee82ee' => 'Violet',
        'ffc0cb' => 'Pink',        'ff69b4' => 'Hot Pink',
        '40e0d0' => 'Turquoise',   '00ced1' => 'Dark Turquoise',
        '4b0082' => 'Indigo',      'f5f5dc' => 'Beige',
        '2f4f4f' => 'Dark Slate',  '191970' => 'Midnight Blue',
        '708090' => 'Slate Grey',  'b8860b' => 'Dark Goldenrod',
        'd2691e' => 'Chocolate',   '228b22' => 'Forest Green',
        '006400' => 'Dark Green',  '20b2aa' => 'Light Sea Green',
    ];

    if (isset($namedColors[$hex])) {
        return $namedColors[$hex];
    }

    // Fallback: determine dominant hue by RGB analysis
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $lightness = ($max + $min) / 510; // 0–1

    if ($lightness > 0.85) return 'White';
    if ($lightness < 0.15) return 'Black';
    if ($max - $min < 30)  return 'Grey';

    if ($r >= $g && $r >= $b) {
        return ($g > 150) ? 'Orange' : 'Red';
    } elseif ($g >= $r && $g >= $b) {
        return ($r > 150) ? 'Yellow-Green' : 'Green';
    } else {
        return ($r > 100) ? 'Purple' : 'Blue';
    }
}

// 4. Connect to DB and fetch live vehicle data
require_once __DIR__ . '/../../config/db.php';

// Intent Routing: Only load heavy DB context if the user's message likely needs it.
$dataKeywords = ['book', 'recommend', 'car', 'vehicle', 'cheap', 'fast', 'wishlist', 'history', 'rent', 'price', 'speed', 'color', 'red', 'blue', 'black', 'white', 'find', 'looking', 'automatic', 'manual', 'petrol', 'electric', 'diesel', 'cancel', 'refund', 'listing', 'my ', 'i want', 'what do', 'how do', 'help', 'fleet', 'options', 'drive', 'trip'];

$needsData = false;
foreach ($dataKeywords as $word) {
    if (stripos($userMessage, $word) !== false) {
        $needsData = true;
        break;
    }
}

$vehicleCatalog = "";
$comingSoonCatalog = "";
$userContext = "";

if ($needsData) {
    $stmt = $conn->prepare("
        SELECT v.id, v.model, v.license_type, v.transmission, v.fuel_type,
               v.price_per_day, v.top_speed, v.color
        FROM vehicles v
        WHERE v.status = 'approved'
        AND v.id NOT IN (
            SELECT vehicle_id FROM bookings
            WHERE status != 'cancelled' AND end_date >= CURDATE()
        )
        ORDER BY v.price_per_day ASC
        LIMIT 12
    ");

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $vehicles = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!empty($vehicles)) {
            $vehicleCatalog = "\n=== CURRENTLY AVAILABLE VEHICLES ===\n";
            $vehicleCatalog .= "Use this live data to answer vehicle questions and make recommendations.\n";
            $vehicleCatalog .= "Always include the detail link when recommending a vehicle.\n\n";

            foreach ($vehicles as $v) {
                $detailUrl  = "http://localhost/vehicle_rental_collab_project/public/vehicle/vehicle_detail.php?id={$v['id']}";
                $colorName  = hexToColorName($v['color'] ?? '');
                $vehicleCatalog .= "- [{$v['model']}] | Category: {$v['license_type']} | Transmission: {$v['transmission']} | Fuel: {$v['fuel_type']} | Color: {$colorName} | Price: NPR {$v['price_per_day']}/day | Top Speed: {$v['top_speed']} km/h | Link: {$detailUrl}\n";
            }
        } else {
            $vehicleCatalog = "\n=== CURRENTLY AVAILABLE VEHICLES ===\nNo vehicles are currently available for booking.\n";
        }
    }

    // Fetch currently-booked vehicles with their end dates (coming soon)
    $stmt2 = $conn->prepare("
        SELECT v.id, v.model, v.license_type, v.transmission, v.fuel_type,
               v.price_per_day, v.top_speed, v.color,
               MAX(b.end_date) AS available_from
        FROM vehicles v
        JOIN bookings b ON b.vehicle_id = v.id
        WHERE v.status = 'approved'
          AND b.status != 'cancelled'
          AND b.end_date >= CURDATE()
        GROUP BY v.id
        ORDER BY available_from ASC
        LIMIT 5
    ");

    if ($stmt2) {
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $bookedVehicles = $result2->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        if (!empty($bookedVehicles)) {
            $comingSoonCatalog = "\n=== COMING SOON (Currently Booked) ===\n";
            $comingSoonCatalog .= "These vehicles are booked right now but will be free after the shown date. Mention them if they match the user's needs.\n\n";
            foreach ($bookedVehicles as $bv) {
                $detailUrl = "http://localhost/vehicle_rental_collab_project/public/vehicle/vehicle_detail.php?id={$bv['id']}";
                $colorName = hexToColorName($bv['color'] ?? '');
                $freeDate  = date('M j, Y', strtotime($bv['available_from']));
                $comingSoonCatalog .= "- [{$bv['model']}] | Transmission: {$bv['transmission']} | Fuel: {$bv['fuel_type']} | Color: {$colorName} | Price: NPR {$bv['price_per_day']}/day | Available from: {$freeDate} | Link: {$detailUrl}\n";
            }
        }
    }

    // 5. Fetch user-specific data
    $userId   = $_SESSION['user_id'] ?? null;
    $userName = $_SESSION['username'] ?? 'Guest';
    $today    = date('Y-m-d');

    if ($userId) {
        // Active / Upcoming Bookings
        $stmt = $conn->prepare("
            SELECT v.model, b.start_date, b.end_date, b.status, b.total_price
            FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id
            WHERE b.user_id = ? AND b.status = 'confirmed' AND b.end_date >= ?
            ORDER BY b.start_date ASC LIMIT 5
        ");
        $stmt->bind_param('is', $userId, $today);
        $stmt->execute();
        $activeBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Past Completed Bookings
        $stmt = $conn->prepare("
            SELECT v.model, b.start_date, b.end_date, b.total_price
            FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id
            WHERE b.user_id = ? AND b.status = 'completed'
            ORDER BY b.end_date DESC LIMIT 5
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $pastBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Cancelled Bookings
        $stmt = $conn->prepare("
            SELECT v.model, b.start_date, b.end_date, b.total_price
            FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id
            WHERE b.user_id = ? AND b.status = 'cancelled'
            ORDER BY b.start_date DESC LIMIT 5
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $cancelledBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Total Spending
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(total_price),0) as total_spent,
                   COUNT(*) as total_bookings
            FROM bookings WHERE user_id = ? AND status != 'cancelled'
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $spendingRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Wishlist
        $stmt = $conn->prepare("
            SELECT v.id, v.model, v.price_per_day, v.fuel_type, v.transmission
            FROM wishlist w JOIN vehicles v ON w.vehicle_id = v.id
            WHERE w.user_id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $userWishlist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Own Vehicle Listings
        $stmt = $conn->prepare("
            SELECT id, model, status, price_per_day
            FROM vehicles WHERE user_id = ?
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $userListings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Build User Context
        $userContext  = "\n=== CURRENT USER: {$userName} ===\n";
        $userContext .= "Only share THIS user's data. Never reveal other users' information.\n\n";

        $totalSpent    = number_format($spendingRow['total_spent'], 2);
        $totalBookings = $spendingRow['total_bookings'];
        $userContext  .= "ACCOUNT SUMMARY: {$totalBookings} total booking(s), NPR {$totalSpent} total spent.\n\n";

        if (!empty($activeBookings)) {
            $userContext .= "ACTIVE & UPCOMING BOOKINGS (" . count($activeBookings) . "):\n";
            foreach ($activeBookings as $b) {
                $daysLeft = (strtotime($b['start_date']) > strtotime($today))
                    ? ceil((strtotime($b['start_date']) - strtotime($today)) / 86400) . " day(s) until pickup"
                    : "Currently active";
                $userContext .= "- {$b['model']} | {$b['start_date']} → {$b['end_date']} | NPR {$b['total_price']} | {$daysLeft}\n";
            }
        } else {
            $userContext .= "ACTIVE & UPCOMING BOOKINGS: None.\n";
        }

        $userContext .= "\nPAST COMPLETED BOOKINGS (recent " . count($pastBookings) . "):\n";
        if (!empty($pastBookings)) {
            foreach ($pastBookings as $b) {
                $userContext .= "- {$b['model']} | {$b['start_date']} → {$b['end_date']} | NPR {$b['total_price']}\n";
            }
        } else {
            $userContext .= "- None yet.\n";
        }

        $userContext .= "\nCANCELLED BOOKINGS (recent " . count($cancelledBookings) . "):\n";
        if (!empty($cancelledBookings)) {
            foreach ($cancelledBookings as $b) {
                $userContext .= "- {$b['model']} | {$b['start_date']} → {$b['end_date']} | NPR {$b['total_price']} (non-refundable)\n";
            }
        } else {
            $userContext .= "- None.\n";
        }

        $userContext .= "\nWISHLIST (" . count($userWishlist) . " vehicle(s)):\n";
        if (!empty($userWishlist)) {
            foreach ($userWishlist as $w) {
                $link = "http://localhost/vehicle_rental_collab_project/public/vehicle/vehicle_detail.php?id={$w['id']}";
                $userContext .= "- {$w['model']} | {$w['transmission']}, {$w['fuel_type']} | NPR {$w['price_per_day']}/day → {$link}\n";
            }
        } else {
            $userContext .= "- Wishlist is empty.\n";
        }

        $userContext .= "\nMY VEHICLE LISTINGS (" . count($userListings) . "):\n";
        if (!empty($userListings)) {
            foreach ($userListings as $l) {
                $statusNote = ($l['status'] === 'pending') ? " (awaiting admin approval)" : "";
                $userContext .= "- {$l['model']} | {$l['status']}{$statusNote} | NPR {$l['price_per_day']}/day\n";
            }
        } else {
            $userContext .= "- No vehicles listed yet. Use 'List Your Vehicle' in the top nav to add one.\n";
        }

    } else {
        $userContext = "\n=== USER SESSION ===\nThe user is NOT logged in. Politely remind them to log in to access bookings, wishlist, and listing features.\n";
    }
} else {
    // If no keywords triggered, we skip DB calls completely to save tokens and time.
    $userContext = "\n[Notice to AI: Live database context omitted to save tokens, because the user's message appears conversational. Respond naturally based on previous context or general knowledge.]\n";
}

$conn->close();
// 6. System Context — lean & effective for llama-3.1-8b-instant
$systemContext = <<<PROMPT
You are **TDBot**, the AI assistant for TD Rentals — a vehicle rental platform.

## ROLE & RULES
- Help users with: bookings, wishlist, vehicle listings, recommendations, and platform navigation.
- Use ONLY data provided below. NEVER invent vehicle names, specs, prices, or URLs.
- NEVER share another user's data. Only talk about the current user's own account.
- Keep replies concise (under 150 words). Vary tone: brief for simple questions, detailed for complex ones.
- Only suggest vehicles when the user is actually asking about finding/booking one.

## PERSONALITY & SMALL TALK
You're like a friendly colleague who happens to know everything about TD Rentals.
- Small talk, jokes, casual vibes? **Totally fine.** Respond naturally and briefly.
- Never say "I can only help with TD Rentals" or "I'm busy with TD Rentals tasks" — that's rude and robotic.
- If someone goes off-topic: **just reply like a human would** in 1 sentence. You don't have to steer every reply back to vehicles.
- Only gently redirect if the conversation has gone fully off-topic for **3+ exchanges** — and even then, keep it light: "Ha, I could chat all day — let me know if you need anything from TD Rentals! 😄"
- Never refuse, lecture, or make the user feel bad for casual conversation.

## VEHICLE CARD FORMAT (CRITICAL: You MUST use exactly 3 separate lines. Do NOT use bullet points or dashes)
🚗 **[Model]** — [Color], [Transmission], [Fuel]
💰 NPR [price]/day | ⚡ [speed] km/h
👉 [View & Book →]([link])
Max 3 vehicles. Only from AVAILABLE VEHICLES list. If none match, say so honestly.



## KEY EXAMPLES
Booking query → list their actual bookings with dates, amounts, status. Include summary (total spend, cancelled count).
Recommendation → match EXACTLY what they asked (electric = only electric, red = only red). Use the card format.
Refund ask → "TD Rentals doesn't offer refunds. Confirmed bookings are non-refundable. Contact admin via Settings for disputes."
No vehicles match → link to: http://localhost/vehicle_rental_collab_project/public/vehicle/vehicles.php

## PLATFORM QUICK GUIDE
- Book: Vehicle Detail page → pick dates → Book Now
- Wishlist: ❤️ on any vehicle card → Profile → My Wishlist
- List vehicle: "List Your Vehicle" in nav → admin reviews before publishing
- My Bookings: top navigation
- Support/disputes: Settings page → contact admin

## POLICIES
- No refunds. All confirmed bookings are non-refundable.
- Cancellations are recorded but not refunded.
- Insurance included in all bookings.
$userContext
$vehicleCatalog
$comingSoonCatalog
PROMPT;

// 7. Load conversation history from session (free, no DB needed)
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Append the new user message to history
$_SESSION['chat_history'][] = ['role' => 'user', 'content' => $userMessage];

// Build the full messages array: system prompt + last 10 turns of history
$MAX_HISTORY = 5; // Keep last 5 exchanges (10 messages) — enough context, fewer tokens
$trimmedHistory = array_slice($_SESSION['chat_history'], -($MAX_HISTORY * 2));

$messages = array_merge(
    [['role' => 'system', 'content' => $systemContext]],
    $trimmedHistory
);

// 8. Build request body
$postData = [
    'model'       => $model,
    'messages'    => $messages,
    'max_tokens'  => 300,
    'temperature' => 0.5,
    'stream'      => false
];

// 9. Call Groq API
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$info     = curl_getinfo($ch);
$httpCode = $info['http_code'];
$err      = curl_error($ch);
curl_close($ch);

// 10. Parse response and save assistant reply to history
if ($err) {
    echo json_encode(['reply' => "Connection error: $err"]);
    exit;
}

$result = json_decode($response, true);

if (isset($result['choices'][0]['message']['content'])) {
    $reply = trim($result['choices'][0]['message']['content']);

    // Save assistant reply to history so next message has full context
    $_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $reply];

    // Trim history to prevent session bloat (keep last MAX_HISTORY*2 messages)
    if (count($_SESSION['chat_history']) > $MAX_HISTORY * 2) {
        $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -($MAX_HISTORY * 2));
    }

    echo json_encode(['reply' => $reply]);

} elseif (isset($result['error'])) {
    $errMsg = $result['error']['message'] ?? 'Unknown error';
    if ($httpCode === 429) {
        // Distinguish between per-minute rate limit and daily limit
        if (stripos($errMsg, 'day') !== false || stripos($errMsg, 'daily') !== false) {
            echo json_encode(['reply' => "⚠️ I've hit my daily usage limit for today. The free tier resets every 24 hours. Try again tomorrow — I'll be back at full speed! 😊"]);
        } elseif (stripos($errMsg, 'minute') !== false || stripos($errMsg, 'per min') !== false) {
            echo json_encode(['reply' => "⏱️ I'm getting too many requests per minute. Wait about 60 seconds and try again — I'll be right here!"]);
        } else {
            // Show the raw limit message so user knows exactly what happened
            echo json_encode(['reply' => "⚠️ Rate limit hit: $errMsg"]);
        }
    } elseif ($httpCode === 401) {
        echo json_encode(['reply' => "🔑 API key issue — please check the Groq key in chat.php."]);
    } else {
        echo json_encode(['reply' => "AI Error ($httpCode): $errMsg"]);
    }
} else {
    $preview = substr(strip_tags($response), 0, 150);
    echo json_encode(['reply' => "Unexpected Response ($httpCode): $preview"]);
}
