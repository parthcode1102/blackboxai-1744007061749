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

    $method = $_SERVER['REQUEST_METHOD'];
    $response = [];

    switch ($method) {
        case 'GET':
            // Get user settings
            $stmt = $pdo->prepare("
                SELECT 
                    notification_preferences,
                    theme,
                    language,
                    dietary_restrictions
                FROM user_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$settings) {
                // Return default settings if none exist
                $settings = [
                    'notification_preferences' => ['email' => true, 'sms' => false],
                    'theme' => 'light',
                    'language' => 'en',
                    'dietary_restrictions' => []
                ];
            } else {
                // Decode JSON fields
                $settings['notification_preferences'] = json_decode($settings['notification_preferences'], true);
                $settings['dietary_restrictions'] = json_decode($settings['dietary_restrictions'], true);
            }

            $response = [
                'success' => true,
                'data' => $settings
            ];
            break;

        case 'PUT':
            // Update user settings
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Prepare fields to update
            $fields = [
                'notification_preferences' => json_encode($data['notification_preferences'] ?? []),
                'theme' => $data['theme'] ?? 'light',
                'language' => $data['language'] ?? 'en',
                'dietary_restrictions' => json_encode($data['dietary_restrictions'] ?? [])
            ];

            // Check if settings exist
            $stmt = $pdo->prepare("SELECT 1 FROM user_settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $exists = $stmt->fetch();

            if ($exists) {
                // Update existing settings
                $stmt = $pdo->prepare("
                    UPDATE user_settings SET
                        notification_preferences = ?,
                        theme = ?,
                        language = ?,
                        dietary_restrictions = ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");
                $params = [
                    $fields['notification_preferences'],
                    $fields['theme'],
                    $fields['language'],
                    $fields['dietary_restrictions'],
                    $userId
                ];
            } else {
                // Insert new settings
                $stmt = $pdo->prepare("
                    INSERT INTO user_settings 
                    (user_id, notification_preferences, theme, language, dietary_restrictions)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $params = [
                    $userId,
                    $fields['notification_preferences'],
                    $fields['theme'],
                    $fields['language'],
                    $fields['dietary_restrictions']
                ];
            }

            $stmt->execute($params);

            $response = [
                'success' => true,
                'message' => 'Settings updated successfully'
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