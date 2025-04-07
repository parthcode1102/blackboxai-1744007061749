<?php
header('Content-Type: application/json');
require_once 'connect.php';

try {
    // Verify admin authentication (simplified for this example)
    if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Authentication required');
    }

    // Get filter parameters
    $statusFilter = $_GET['status'] ?? 'all';
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // Base query
    $query = "SELECT 
                o.order_id, 
                o.customer_name, 
                o.contact_number, 
                o.total_amount, 
                o.status, 
                o.created_at,
                COUNT(oi.id) as item_count
              FROM orders o
              LEFT JOIN order_items oi ON o.order_id = oi.order_id";

    // Apply status filter
    $params = [];
    if ($statusFilter !== 'all') {
        $query .= " WHERE o.status = ?";
        $params[] = $statusFilter;
    }

    // Group and pagination
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
    $countQuery = "SELECT COUNT(*) as total FROM orders";
    if ($statusFilter !== 'all') {
        $countQuery .= " WHERE status = ?";
    }
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($statusFilter !== 'all' ? [$statusFilter] : []);
    $total = $stmt->fetch()['total'];

    // Format response
    $response = [
        'success' => true,
        'data' => array_map(function($order) {
            return [
                'id' => $order['order_id'],
                'customer' => $order['customer_name'],
                'phone' => $order['contact_number'],
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