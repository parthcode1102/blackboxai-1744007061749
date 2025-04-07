<?php
header('Content-Type: application/json');
require_once 'connect.php';

// Function to generate a unique order number
function generateOrderNumber() {
    return 'RNG-' . strtoupper(substr(md5(uniqid()), 0, 8));
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate input
    if (empty($data['name']) || empty($data['email']) || empty($data['phone']) || empty($data['address']) || empty($data['items'])) {
        throw new Exception('All fields are required');
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Insert order
    $orderNumber = generateOrderNumber();
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, customer_name, contact_number, total_amount, status)
        VALUES (:user_id, :name, :phone, :total, 'Confirmed')
    ");

    $user_id = null; // Will be implemented with user authentication
    $stmt->execute([
        ':user_id' => $user_id,
        ':name' => $data['name'],
        ':phone' => $data['phone'],
        ':total' => $data['total']
    ]);

    $order_id = $pdo->lastInsertId();

    // Insert order items
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, item_id, quantity, price)
        VALUES (:order_id, :item_id, :quantity, :price)
    ");

    foreach ($data['items'] as $item) {
        $stmt->execute([
            ':order_id' => $order_id,
            ':item_id' => $item['id'],
            ':quantity' => $item['quantity'],
            ':price' => $item['price']
        ]);
    }

    // Commit transaction
    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_number' => $orderNumber,
        'message' => 'Order placed successfully'
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