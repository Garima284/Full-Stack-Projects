<?php
require_once '../config/database.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$user = requireAuth();

switch ($method) {
    case 'GET':
        getUsers($user);
        break;
    case 'POST':
        updateOnlineStatus($user);
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getUsers($currentUser) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get all users except current user with their last message info
        $query = "SELECT 
                    u.id, 
                    u.username, 
                    u.email, 
                    u.is_online, 
                    u.last_seen,
                    (SELECT COUNT(*) FROM messages m 
                     WHERE m.sender_id = u.id AND m.receiver_id = :current_user_id AND m.is_read = FALSE
                    ) as unread_count,
                    (SELECT m.message FROM messages m 
                     WHERE (m.sender_id = u.id AND m.receiver_id = :current_user_id2) 
                        OR (m.sender_id = :current_user_id3 AND m.receiver_id = u.id)
                     ORDER BY m.created_at DESC LIMIT 1
                    ) as last_message,
                    (SELECT m.created_at FROM messages m 
                     WHERE (m.sender_id = u.id AND m.receiver_id = :current_user_id4) 
                        OR (m.sender_id = :current_user_id5 AND m.receiver_id = u.id)
                     ORDER BY m.created_at DESC LIMIT 1
                    ) as last_message_time
                  FROM users u 
                  WHERE u.id != :current_user_id6 
                  ORDER BY u.is_online DESC, u.last_seen DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':current_user_id', $currentUser['id']);
        $stmt->bindParam(':current_user_id2', $currentUser['id']);
        $stmt->bindParam(':current_user_id3', $currentUser['id']);
        $stmt->bindParam(':current_user_id4', $currentUser['id']);
        $stmt->bindParam(':current_user_id5', $currentUser['id']);
        $stmt->bindParam(':current_user_id6', $currentUser['id']);
        $stmt->execute();
        
        $users = $stmt->fetchAll();
        
        // Format the response
        foreach ($users as &$user) {
            $user['is_online'] = (bool) $user['is_online'];
            $user['unread_count'] = (int) $user['unread_count'];
            
            // Format last seen time
            if ($user['last_seen']) {
                $lastSeen = new DateTime($user['last_seen']);
                $now = new DateTime();
                $diff = $now->diff($lastSeen);
                
                if ($user['is_online']) {
                    $user['status'] = 'Online';
                } elseif ($diff->days > 0) {
                    $user['status'] = 'Last seen ' . $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                } elseif ($diff->h > 0) {
                    $user['status'] = 'Last seen ' . $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                } elseif ($diff->i > 0) {
                    $user['status'] = 'Last seen ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                } else {
                    $user['status'] = 'Last seen recently';
                }
            } else {
                $user['status'] = 'Last seen recently';
            }
            
            // Remove sensitive data
            unset($user['email']);
        }
        
        jsonResponse([
            'success' => true,
            'users' => $users
        ]);
        
    } catch (Exception $e) {
        error_log("Get users error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch users'], 500);
    }
}

function updateOnlineStatus($user) {
    $input = getJsonInput();
    $isOnline = $input['is_online'] ?? true;
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $query = "UPDATE users SET is_online = :is_online, last_seen = NOW() WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':is_online', $isOnline, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $user['id']);
        $stmt->execute();
        
        jsonResponse([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Update status error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update status'], 500);
    }
}
?>
