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
            // Get active promotions
            $currentDate = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                SELECT 
                    promotion_id, 
                    name, 
                    description, 
                    discount_type, 
                    discount_value, 
                    min_order_amount,
                    start_date,
                    end_date,
                    applicable_items
                FROM promotions
                WHERE start_date <= ? AND end_date >= ?
                ORDER BY end_date ASC
            ");
            $stmt->execute([$currentDate, $currentDate]);
            $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'data' => array_map(function($promo) {
                    return [
                        'id' => $promo['promotion_id'],
                        'name' => $promo['name'],
                        'description' => $promo['description'],
                        'discount_type' => $promo['discount_type'],
                        'discount_value' => (float)$promo['discount_value'],
                        'min_order_amount' => (float)$promo['min_order_amount'],
                        'start_date' => $promo['start_date'],
                        'end_date' => $promo['end_date'],
                        'applicable_items' => json_decode($promo['applicable_items'], true)
                    ];
                }, $promotions)
            ];
            break;

        case 'POST':
            // Create new promotion
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $required = ['name', 'discount_type', 'discount_value', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            // Validate discount type
            $validTypes = ['percentage', 'fixed_amount'];
            if (!in_array($data['discount_type'], $validTypes)) {
                throw new Exception('Invalid discount type');
            }

            // Validate dates
            if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
                throw new Exception('End date must be after start date');
            }

            $stmt = $pdo->prepare("
                INSERT INTO promotions 
                (name, description, discount_type, discount_value, 
                 min_order_amount, start_date, end_date, applicable_items)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['discount_type'],
                $data['discount_value'],
                $data['min_order_amount'] ?? 0,
                $data['start_date'],
                $data['end_date'],
                json_encode($data['applicable_items'] ?? [])
            ]);

            $promoId = $pdo->lastInsertId();

            $response = [
                'success' => true,
                'message' => 'Promotion created successfully',
                'promotion_id' => $promoId
            ];
            break;

        case 'PUT':
            // Update existing promotion
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['promotion_id'])) {
                throw new Exception('Promotion ID is required');
            }

            // Get existing promotion
            $stmt = $pdo->prepare("SELECT * FROM promotions WHERE promotion_id = ?");
            $stmt->execute([$data['promotion_id']]);
            $existing = $stmt->fetch();

            if (!$existing) {
                throw new Exception('Promotion not found');
            }

            // Prepare update fields
            $fields = [
                'name' => $data['name'] ?? $existing['name'],
                'description' => $data['description'] ?? $existing['description'],
                'discount_type' => $data['discount_type'] ?? $existing['discount_type'],
                'discount_value' => $data['discount_value'] ?? $existing['discount_value'],
                'min_order_amount' => $data['min_order_amount'] ?? $existing['min_order_amount'],
                'start_date' => $data['start_date'] ?? $existing['start_date'],
                'end_date' => $data['end_date'] ?? $existing['end_date'],
                'applicable_items' => isset($data['applicable_items']) 
                    ? json_encode($data['applicable_items']) 
                    : $existing['applicable_items']
            ];

            // Validate discount type if being updated
            if (isset($data['discount_type'])) {
                $validTypes = ['percentage', 'fixed_amount'];
                if (!in_array($data['discount_type'], $validTypes)) {
                    throw new Exception('Invalid discount type');
                }
            }

            // Validate dates if being updated
            if (isset($data['start_date']) || isset($data['end_date'])) {
                $startDate = $data['start_date'] ?? $existing['start_date'];
                $endDate = $data['end_date'] ?? $existing['end_date'];
                if (strtotime($startDate) > strtotime($endDate)) {
                    throw new Exception('End date must be after start date');
                }
            }

            $stmt = $pdo->prepare("
                UPDATE promotions SET
                    name = ?,
                    description = ?,
                    discount_type = ?,
                    discount_value = ?,
                    min_order_amount = ?,
                    start_date = ?,
                    end_date = ?,
                    applicable_items = ?
                WHERE promotion_id = ?
            ");

            $stmt->execute([
                $fields['name'],
                $fields['description'],
                $fields['discount_type'],
                $fields['discount_value'],
                $fields['min_order_amount'],
                $fields['start_date'],
                $fields['end_date'],
                $fields['applicable_items'],
                $data['promotion_id']
            ]);

            $response = [
                'success' => true,
                'message' => 'Promotion updated successfully'
            ];
            break;

        case 'DELETE':
            // Delete promotion
            $promoId = $_GET['id'] ?? null;
            if (empty($promoId)) {
                throw new Exception('Promotion ID is required');
            }

            $stmt = $pdo->prepare("DELETE FROM promotions WHERE promotion_id = ?");
            $stmt->execute([$promoId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Promotion not found');
            }

            $response = [
                'success' => true,
                'message' => 'Promotion deleted successfully'
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