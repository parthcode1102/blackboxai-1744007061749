<?php
header('Content-Type: application/json');
require_once 'connect.php';

try {
    // Verify admin authentication
    if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Authentication required');
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $response = [];

    switch ($method) {
        case 'GET':
            // Get dashboard summary
            $currentDate = date('Y-m-d');
            
            // Get total sales
            $stmt = $pdo->query("SELECT SUM(total_amount) as total_sales FROM orders");
            $sales = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get new customers
            $stmt = $pdo->query("SELECT COUNT(*) as new_customers FROM users WHERE DATE(created_at) = '$currentDate'");
            $customers = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get pending orders
            $stmt = $pdo->query("SELECT COUNT(*) as pending_orders FROM orders WHERE status = 'Pending'");
            $orders = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get recent feedback
            $stmt = $pdo->query("SELECT f.*, u.name FROM feedback f JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC LIMIT 5");
            $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'data' => [
                    'total_sales' => (float)$sales['total_sales'] ?? 0,
                    'new_customers' => (int)$customers['new_customers'] ?? 0,
                    'pending_orders' => (int)$orders['pending_orders'] ?? 0,
                    'recent_feedback' => $feedback
                ]
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