<?php
header('Content-Type: application/json');
require_once 'connect.php';

try {
    // Get category filter from query parameter
    $category = isset($_GET['category']) ? $_GET['category'] : 'all';
    
    // Prepare SQL query based on category filter
    if ($category === 'all') {
        $query = "SELECT * FROM items WHERE is_available = TRUE";
        $stmt = $pdo->query($query);
    } else {
        $query = "SELECT * FROM items WHERE category = :category AND is_available = TRUE";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->execute();
    }

    // Fetch results and format response
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add image URLs if not already set (fallback to Pexels images)
    foreach ($items as &$item) {
        if (empty($item['image_url'])) {
            switch ($item['category']) {
                case 'Ice Cream':
                    $item['image_url'] = 'https://images.pexels.com/photos/1625235/pexels-photo-1625235.jpeg';
                    break;
                case 'Shake':
                    $item['image_url'] = 'https://images.pexels.com/photos/918581/pexels-photo-918581.jpeg';
                    break;
                case 'Snack':
                    $item['image_url'] = 'https://images.pexels.com/photos/5638593/pexels-photo-5638593.jpeg';
                    break;
                default:
                    $item['image_url'] = 'https://images.pexels.com/photos/1192043/pexels-photo-1192043.jpeg';
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>