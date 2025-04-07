<?php
header('Content-Type: application/json');
require_once 'connect.php';

// Function to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input
    if (empty($data['firstName']) || empty($data['lastName']) || empty($data['email']) || empty($data['password'])) {
        throw new Exception('All fields are required');
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        throw new Exception('Email already registered');
    }

    // Validate password length
    if (strlen($data['password']) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Create new user
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, phone, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $name = $data['firstName'] . ' ' . $data['lastName'];
    $hashedPassword = hashPassword($data['password']);
    $phone = $data['phone'] ?? null;

    $stmt->execute([$name, $data['email'], $hashedPassword, $phone]);
    $userId = $pdo->lastInsertId();

    // Generate welcome token (for email verification in a real app)
    $welcomeToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE users SET welcome_token = ? WHERE id = ?");
    $stmt->execute([$welcomeToken, $userId]);

    // Commit transaction
    $pdo->commit();

    // Return success response (without sensitive data)
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $data['email']
        ]
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
