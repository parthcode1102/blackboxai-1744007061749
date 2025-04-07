<?php
header('Content-Type: application/json');
require_once 'connect.php';

try {
    // Verify customer authentication
    if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Authentication required');
    }

    // Get customer ID from token (simplified for this example)
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    $customerId = 1; // In real app, would decode token to get customer ID

    $method = $_SERVER['REQUEST_METHOD'];
    $response = [];

    switch ($method) {
        case 'GET':
            // Get favorite items
            $stmt = $pdo->prepare("
                SELECT i.item_id, i.name, i.price, i.image_url
                FROM favorites f
                JOIN items i ON f.item_id = i.item_id
                WHERE f.user_id = ?
            ");
            $stmt->execute([$customerId]);
            $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'data' => $favorites
            ];
            break;

        case 'POST':
            // Add item to favorites
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['item_id'])) {
                throw new Exception('Item ID is required');
            }

            // Check if item already exists in favorites
            $stmt = $pdo->prepare("SELECT * FROM favorites WHERE user_id = ? AND item_id = ?");
            $stmt->execute([$customerId, $data['item_id']]);
            if ($stmt->fetch()) {
                throw new Exception('Item is already in favorites');
            }

            // Insert new favorite
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, item_id) VALUES (?, ?)");
            $stmt->execute([$customerId, $data['item_id']]);

            $response = [
                'success' => true,
                'message' => 'Item added to favorites'
            ];
            break;

        case 'DELETE':
            // Remove item from favorites
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['item_id'])) {
                throw new Exception('Item ID is required');
            }

            $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND item_id = ?");
            $stmt->execute([$customerId, $data['item_id']]);

            $response = [
                'success' => true,
                'message' => 'Item removed from favorites'
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