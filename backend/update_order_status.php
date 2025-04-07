<?php
header('Content-Type: application/json');
require_once 'connect.php';

try {
    // Verify admin authentication (simplified for this example)
    if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Authentication required');
    }

    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input
    if (empty($data['order_id']) || empty($data['status'])) {
        throw new Exception('Order ID and status are required');
    }

    // Validate status
    $validStatuses = ['Pending', 'Confirmed', 'Processing', 'Ready', 'Completed'];
    if (!in_array($data['status'], $validStatuses)) {
        throw new Exception('Invalid status value');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Update order status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = ?, updated_at = NOW() 
        WHERE order_id = ?
    ");
    $stmt->execute([$data['status'], $data['order_id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Order not found');
    }

    // Log status change
    $stmt = $pdo->prepare("
        INSERT INTO order_status_history 
        (order_id, status, changed_by, changed_at)
        VALUES (?, ?, 'admin', NOW())
    ");
    $stmt->execute([$data['order_id'], $data['status']]);

    // Commit transaction
    $pdo->commit();

    // Get updated order details
    $stmt = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
        FROM orders o
        WHERE o.order_id = ?
    ");
    $stmt->execute([$data['order_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format response
    $response = [
        'success' => true,
        'message' => 'Order status updated',
        'order' => [
            'id' => $order['order_id'],
            'customer' => $order['customer_name'],
            'phone' => $order['contact_number'],
            'total' => (float)$order['total_amount'],
            'status' => $order['status'],
            'date' => $order['created_at'],
            'item_count' => (int)$order['item_count']
        ]
    ];

    echo json_encode($response);

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