<?php
header('Content-Type: application/json');
require_once 'connect.php';

try {
    // Verify authentication
    if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Authentication required');
    }

    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    $userId = 1; // In real app, would decode token to get user ID

    $method = $_SERVER['REQUEST_METHOD'];
    $response = [];

    switch ($method) {
        case 'GET':
            // Get loyalty points for user
            $stmt = $pdo->prepare("SELECT points FROM loyalty WHERE user_id = ?");
            $stmt->execute([$userId]);
            $loyalty = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$loyalty) {
                $loyalty = ['points' => 0];
            }

            $response = [
                'success' => true,
                'data' => $loyalty
            ];
            break;

        case 'POST':
            // Add points to user
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['points'])) {
                throw new Exception('Points are required');
            }

            $stmt = $pdo->prepare("
                INSERT INTO loyalty (user_id, points) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE points = points + ?
            ");
            $stmt->execute([$userId, $data['points'], $data['points']]);

            $response = [
                'success' => true,
                'message' => 'Points added successfully'
            ];
            break;

        case 'DELETE':
            // Redeem points
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['points'])) {
                throw new Exception('Points are required to redeem');
            }

            // Check current points
            $stmt = $pdo->prepare("SELECT points FROM loyalty WHERE user_id = ?");
            $stmt->execute([$userId]);
            $loyalty = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($loyalty['points'] < $data['points']) {
                throw new Exception('Insufficient points');
            }

            $stmt = $pdo->prepare("
                UPDATE loyalty SET points = points - ? WHERE user_id = ?
            ");
            $stmt->execute([$data['points'], $userId]);

            $response = [
                'success' => true,
                'message' => 'Points redeemed successfully'
            ];
            break;

        default:
            http_response_code(405);
            throw new Exception('Method not allowed');
    }

    echo json_encode($response);

} catch (PDOException $e) {
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