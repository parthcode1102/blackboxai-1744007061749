<?php
header('Content-Type: application/json');
require_once 'connect.php';

try {
    // Verify admin authentication
    if (empty($_SERVER['HTTP_AUTHORIZATION'])) {
        throw new Exception('Authentication required');
    }

    // Get time period (default: last 30 days)
    $period = $_GET['period'] ?? '30days';
    $validPeriods = ['7days', '30days', '90days', 'month', 'year'];
    if (!in_array($period, $validPeriods)) {
        throw new Exception('Invalid time period');
    }

    // Calculate date ranges
    $endDate = date('Y-m-d');
    switch ($period) {
        case '7days':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30days':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90days':
            $startDate = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'month':
            $startDate = date('Y-m-01');
            break;
        case 'year':
            $startDate = date('Y-01-01');
            break;
    }

    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as order_count,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value
        FROM orders
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get daily sales data
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as order_count,
            SUM(total_amount) as total_revenue
        FROM orders
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at)
    ");
    $stmt->execute([$startDate, $endDate]);
    $dailySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top selling items
    $stmt = $pdo->prepare("
        SELECT 
            i.name,
            i.category,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN items i ON oi.item_id = i.item_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY i.item_id
        ORDER BY total_quantity DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get sales by category
    $stmt = $pdo->prepare("
        SELECT 
            i.category,
            COUNT(DISTINCT o.order_id) as order_count,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN items i ON oi.item_id = i.item_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.created_at BETWEEN ? AND ?
        GROUP BY i.category
        ORDER BY total_revenue DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $salesByCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    $response = [
        'success' => true,
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'label' => $period
        ],
        'summary' => [
            'order_count' => (int)$summary['order_count'],
            'total_revenue' => (float)$summary['total_revenue'],
            'avg_order_value' => (float)$summary['avg_order_value']
        ],
        'daily_sales' => array_map(function($day) {
            return [
                'date' => $day['date'],
                'order_count' => (int)$day['order_count'],
                'total_revenue' => (float)$day['total_revenue']
            ];
        }, $dailySales),
        'top_items' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'category' => $item['category'],
                'total_quantity' => (int)$item['total_quantity'],
                'total_revenue' => (float)$item['total_revenue']
            ];
        }, $topItems),
        'sales_by_category' => array_map(function($category) {
            return [
                'category' => $category['category'],
                'order_count' => (int)$category['order_count'],
                'total_quantity' => (int)$category['total_quantity'],
                'total_revenue' => (float)$category['total_revenue']
            ];
        }, $salesByCategory)
    ];

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