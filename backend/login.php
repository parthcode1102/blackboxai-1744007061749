<?php
header('Content-Type: application/json');
require_once 'connect.php';

// Function to verify password
function verifyPassword($inputPassword, $hashedPassword) {
    return password_verify($inputPassword, $hashedPassword);
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input
    if (empty($data['email']) || empty($data['password'])) {
        throw new Exception('Email and password are required');
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Invalid email or password');
    }

    // Verify password
    if (!verifyPassword($data['password'], $user['password'])) {
        throw new Exception('Invalid email or password');
    }

    // Generate session token (simplified for this example)
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));

    // Update user token in database
    $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expires_at = ? WHERE id = ?");
    $stmt->execute([$token, $expiresAt, $user['id']]);

    // Return user data (without sensitive information)
    $response = [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'is_admin' => (bool)$user['is_admin'],
            'token' => $token
        ]
    ];

    // Set cookie if "remember me" is checked
    if (!empty($data['remember_me']) && $data['remember_me']) {
        $cookieValue = json_encode([
            'user_id' => $user['id'],
            'token' => $token
        ]);
        setcookie('auth_token', $cookieValue, time() + (86400 * 30), "/"); // 30 days
    }

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>