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
    $isAdmin = false; // In real app, would check user role

    $method = $_SERVER['REQUEST_METHOD'];
    $response = [];

    switch ($method) {
        case 'GET':
            // Get notifications for user
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
            $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';

            $query = "SELECT * FROM notifications WHERE user_id = ?";
            $params = [$userId];

            if ($unreadOnly) {
                $query .= " AND is_read = FALSE";
            }

            $query .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mark as read if requested
            if (isset($_GET['mark_read']) && $_GET['mark_read'] === 'true') {
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = TRUE, read_at = NOW() 
                    WHERE user_id = ? AND is_read = FALSE
                ");
                $stmt->execute([$userId]);
            }

            $response = [
                'success' => true,
                'data' => array_map(function($notification) {
                    return [
                        'id' => $notification['notification_id'],
                        'title' => $notification['title'],
                        'message' => $notification['message'],
                        'type' => $notification['type'],
                        'is_read' => (bool)$notification['is_read'],
                        'created_at' => $notification['created_at'],
                        'metadata' => !empty($notification['metadata']) ? json_decode($notification['metadata'], true) : []
                    ];
                }, $notifications)
            ];
            break;

        case 'POST':
            // Create new notification (admin only)
            if (!$isAdmin) {
                throw new Exception('Admin access required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['user_id']) || empty($data['title']) || empty($data['message'])) {
                throw new Exception('User ID, title and message are required');
            }

            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, metadata)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['user_id'],
                $data['title'],
                $data['message'],
                $data['type'] ?? 'info',
                json_encode($data['metadata'] ?? [])
            ]);

            $notificationId = $pdo->lastInsertId();

            $response = [
                'success' => true,
                'message' => 'Notification created',
                'notification_id' => $notificationId
            ];
            break;

        case 'PATCH':
            // Mark notification as read
            $notificationId = $_GET['id'] ?? null;
            if (empty($notificationId)) {
                throw new Exception('Notification ID is required');
            }

            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = TRUE, read_at = NOW() 
                WHERE notification_id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Notification not found');
            }

            $response = [
                'success' => true,
                'message' => 'Notification marked as read'
            ];
            break;

        case 'DELETE':
            // Delete notification
            $notificationId = $_GET['id'] ?? null;
            if (empty($notificationId)) {
                throw new Exception('Notification ID is required');
            }

            $stmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE notification_id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Notification not found');
            }

            $response = [
                'success' => true,
                'message' => 'Notification deleted'
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
