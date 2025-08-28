<?php
require_once '../config/database.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$user = requireAuth();

switch ($method) {
    case 'GET':
        if (isset($_GET['chat_with'])) {
            getMessages($user, $_GET['chat_with']);
        } else {
            jsonResponse(['error' => 'chat_with parameter required'], 400);
        }
        break;
    case 'POST':
        $action = $_GET['action'] ?? '';
        if ($action === 'send') {
            sendMessage($user);
        } elseif ($action === 'mark_read') {
            markMessagesAsRead($user);
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getMessages($user, $chatWithId) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Validate chat_with user exists
        $userCheck = "SELECT id FROM users WHERE id = :chat_with_id";
        $userStmt = $conn->prepare($userCheck);
        $userStmt->bindParam(':chat_with_id', $chatWithId);
        $userStmt->execute();
        
        if (!$userStmt->fetch()) {
            jsonResponse(['error' => 'Invalid user ID'], 404);
        }
        
        // Get messages between current user and specified user
        $query = "SELECT 
                    m.*,
                    s.username as sender_username,
                    r.username as receiver_username
                  FROM messages m
                  JOIN users s ON m.sender_id = s.id
                  JOIN users r ON m.receiver_id = r.id
                  WHERE (m.sender_id = :user_id AND m.receiver_id = :chat_with_id)
                     OR (m.sender_id = :chat_with_id2 AND m.receiver_id = :user_id2)
                  ORDER BY m.created_at ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':chat_with_id', $chatWithId);
        $stmt->bindParam(':chat_with_id2', $chatWithId);
        $stmt->bindParam(':user_id2', $user['id']);
        $stmt->execute();
        
        $messages = $stmt->fetchAll();
        
        // Format messages
        foreach ($messages as &$message) {
            $message['is_own'] = ($message['sender_id'] == $user['id']);
            $message['timestamp'] = strtotime($message['created_at']) * 1000; // JavaScript timestamp
            $message['is_read'] = (bool) $message['is_read'];
        }
        
        jsonResponse([
            'success' => true,
            'messages' => $messages
        ]);
        
    } catch (Exception $e) {
        error_log("Get messages error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch messages'], 500);
    }
}

function sendMessage($user) {
    $input = getJsonInput();
    $receiverId = $input['receiver_id'] ?? null;
    $message = trim($input['message'] ?? '');
    
    if (!$receiverId || empty($message)) {
        jsonResponse(['error' => 'Receiver ID and message are required'], 400);
    }
    
    if (strlen($message) > 1000) {
        jsonResponse(['error' => 'Message too long'], 400);
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Validate receiver exists
        $userCheck = "SELECT id FROM users WHERE id = :receiver_id";
        $userStmt = $conn->prepare($userCheck);
        $userStmt->bindParam(':receiver_id', $receiverId);
        $userStmt->execute();
        
        if (!$userStmt->fetch()) {
            jsonResponse(['error' => 'Invalid receiver ID'], 404);
        }
        
        // Insert message
        $query = "INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at) 
                  VALUES (:sender_id, :receiver_id, :message, FALSE, NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':sender_id', $user['id']);
        $stmt->bindParam(':receiver_id', $receiverId);
        $stmt->bindParam(':message', $message);
        $stmt->execute();
        
        $messageId = $conn->lastInsertId();
        
        // Get the created message with user info
        $getQuery = "SELECT 
                       m.*,
                       s.username as sender_username,
                       r.username as receiver_username
                     FROM messages m
                     JOIN users s ON m.sender_id = s.id
                     JOIN users r ON m.receiver_id = r.id
                     WHERE m.id = :message_id";
        
        $getStmt = $conn->prepare($getQuery);
        $getStmt->bindParam(':message_id', $messageId);
        $getStmt->execute();
        
        $newMessage = $getStmt->fetch();
        $newMessage['is_own'] = true;
        $newMessage['timestamp'] = strtotime($newMessage['created_at']) * 1000;
        $newMessage['is_read'] = false;
        
        jsonResponse([
            'success' => true,
            'message' => $newMessage
        ]);
        
    } catch (Exception $e) {
        error_log("Send message error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to send message'], 500);
    }
}

function markMessagesAsRead($user) {
    $input = getJsonInput();
    $senderId = $input['sender_id'] ?? null;
    
    if (!$senderId) {
        jsonResponse(['error' => 'Sender ID is required'], 400);
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Mark messages as read
        $query = "UPDATE messages 
                  SET is_read = TRUE 
                  WHERE sender_id = :sender_id 
                    AND receiver_id = :receiver_id 
                    AND is_read = FALSE";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':sender_id', $senderId);
        $stmt->bindParam(':receiver_id', $user['id']);
        $stmt->execute();
        
        $affectedRows = $stmt->rowCount();
        
        jsonResponse([
            'success' => true,
            'marked_count' => $affectedRows,
            'message' => 'Messages marked as read'
        ]);
        
    } catch (Exception $e) {
        error_log("Mark read error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to mark messages as read'], 500);
    }
}
?>
