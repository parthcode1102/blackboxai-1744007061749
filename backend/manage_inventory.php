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
            // Get all inventory items
            $stmt = $pdo->query("
                SELECT 
                    item_id, name, description, category, 
                    price, stock_quantity, is_available, image_url
                FROM items
                ORDER BY category, name
            ");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'data' => $items
            ];
            break;

        case 'POST':
            // Add new inventory item
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['name']) || empty($data['category']) || !isset($data['price'])) {
                throw new Exception('Name, category and price are required');
            }

            $stmt = $pdo->prepare("
                INSERT INTO items 
                (name, description, category, price, stock_quantity, is_available, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['category'],
                $data['price'],
                $data['stock_quantity'] ?? 0,
                $data['is_available'] ?? true,
                $data['image_url'] ?? null
            ]);

            $itemId = $pdo->lastInsertId();

            $response = [
                'success' => true,
                'message' => 'Item added successfully',
                'item_id' => $itemId
            ];
            break;

        case 'PUT':
            // Update inventory item
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['item_id'])) {
                throw new Exception('Item ID is required');
            }

            $fields = [];
            $params = [];
            
            $updatableFields = [
                'name', 'description', 'category', 
                'price', 'stock_quantity', 'is_available', 'image_url'
            ];

            foreach ($updatableFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($fields)) {
                throw new Exception('No fields to update');
            }

            $params[] = $data['item_id'];
            $query = "UPDATE items SET " . implode(', ', $fields) . " WHERE item_id = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            $response = [
                'success' => true,
                'message' => 'Item updated successfully'
            ];
            break;

        case 'DELETE':
            // Delete inventory item
            $itemId = $_GET['item_id'] ?? null;
            
            if (empty($itemId)) {
                throw new Exception('Item ID is required');
            }

            $stmt = $pdo->prepare("DELETE FROM items WHERE item_id = ?");
            $stmt->execute([$itemId]);

            $response = [
                'success' => true,
                'message' => 'Item deleted successfully'
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