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

    // Get pagination parameters
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // Get status filter
    $statusFilter = $_GET['status'] ?? 'all';
    $validStatuses = ['all', 'pending', 'confirmed', 'processing', 'ready', 'completed'];
    if (!in_array($statusFilter, $validStatuses)) {
        throw new Exception('Invalid status filter');
    }

    // Base query
    $query = "SELECT 
                o.order_id, 
                o.total_amount, 
                o.status, 
                o.created_at,
                COUNT(oi.id) as item_count
              FROM orders o
              JOIN order_items oi ON o.order_id = oi.order_id
              WHERE o.user_id = ?";

    $params = [$customerId];

    // Apply status filter
    if ($statusFilter !== 'all') {
        $query .= " AND o.status = ?";
        $params[] = ucfirst($statusFilter);
    }

    // Complete query
    $query .= " GROUP BY o.order_id
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Get orders
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM orders WHERE user_id = ?";
    $countParams = [$customerId];
    if ($statusFilter !== 'all') {
        $countQuery .= " AND status = ?";
        $countParams[] = ucfirst($statusFilter);
    }
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $total = $stmt->fetch()['total'];

    // Format response
    $response = [
        'success' => true,
        'data' => array_map(function($order) {
            return [
                'id' => $order['order_id'],
                'total' => (float)$order['total_amount'],
                'status' => $order['status'],
                'date' => $order['created_at'],
                'item_count' => (int)$order['item_count']
            ];
        }, $orders),
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ];

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