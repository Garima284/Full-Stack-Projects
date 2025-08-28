<?php
require_once '../config/database.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$user = requireAuth();

switch ($method) {
    case 'POST':
        updateTypingStatus($user);
        break;
    case 'GET':
        getTypingStatus($user);
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function updateTypingStatus($user) {
    $input = getJsonInput();
    $chatWithId = $input['chat_with_id'] ?? null;
    $isTyping = $input['is_typing'] ?? false;
    
    if (!$chatWithId) {
        jsonResponse(['error' => 'chat_with_id is required'], 400);
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Update or insert typing status
        $query = "INSERT INTO typing_status (user_id, chat_with_id, is_typing, updated_at) 
                  VALUES (:user_id, :chat_with_id, :is_typing, NOW())
                  ON DUPLICATE KEY UPDATE 
                  is_typing = :is_typing2, updated_at = NOW()";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user['id']);
        $stmt->bindParam(':chat_with_id', $chatWithId);
        $stmt->bindParam(':is_typing', $isTyping, PDO::PARAM_BOOL);
        $stmt->bindParam(':is_typing2', $isTyping, PDO::PARAM_BOOL);
        $stmt->execute();
        
        // Auto-clear typing status after 5 seconds
        if ($isTyping) {
            // This would typically be handled by a background job or WebSocket
            // For now, we'll just rely on the client to send false after timeout
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Typing status updated'
        ]);
        
    } catch (Exception $e) {
        error_log("Update typing status error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update typing status'], 500);
    }
}

function getTypingStatus($user) {
    $chatWithId = $_GET['chat_with_id'] ?? null;
    
    if (!$chatWithId) {
        jsonResponse(['error' => 'chat_with_id parameter required'], 400);
    }
    
    try {
        $db = new Database();
