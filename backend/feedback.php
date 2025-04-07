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
            // Get feedback for user
            $stmt = $pdo->prepare("SELECT * FROM feedback WHERE user_id = ?");
            $stmt->execute([$userId]);
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'data' => $feedbacks
            ];
            break;

        case 'POST':
            // Submit new feedback
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['message'])) {
                throw new Exception('Feedback message is required');
            }

            $stmt = $pdo->prepare("
                INSERT INTO feedback (user_id, message, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$userId, $data['message']]);

            $response = [
                'success' => true,
                'message' => 'Feedback submitted successfully'
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