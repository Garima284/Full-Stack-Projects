<?php
require_once '../config/database.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'login') {
            handleLogin();
        } elseif ($action === 'register') {
            handleRegister();
        } elseif ($action === 'logout') {
            handleLogout();
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function handleLogin() {
    $input = getJsonInput();
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'Username and password are required'], 400);
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get user by username
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        // Generate session token
        $sessionToken = generateSessionToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert session
        $sessionQuery = "INSERT INTO user_sessions (user_id, session_token, expires_at) 
                        VALUES (:user_id, :token, :expires_at)";
        $sessionStmt = $conn->prepare($sessionQuery);
        $sessionStmt->bindParam(':user_id', $user['id']);
        $sessionStmt->bindParam(':token', $sessionToken);
        $sessionStmt->bindParam(':expires_at', $expiresAt);
        $sessionStmt->execute();
        
        // Update user online status
        $updateQuery = "UPDATE users SET is_online = TRUE, last_seen = NOW() WHERE id = :id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();
        
        // Remove password from response
        unset($user['password']);
        $user['is_online'] = true;
        
        jsonResponse([
            'success' => true,
            'user' => $user,
            'token' => $sessionToken,
            'message' => 'Login successful'
        ]);
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        jsonResponse(['error' => 'Login failed'], 500);
    }
}

function handleRegister() {
    $input = getJsonInput();
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    if (strlen($password) < 6) {
        jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if username or email already exists
        $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bindParam(':username', $username);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->fetch()) {
            jsonResponse(['error' => 'Username or email already exists'], 409);
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $insertQuery = "INSERT INTO users (username, email, password, is_online) 
                       VALUES (:username, :email, :password, TRUE)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bindParam(':username', $username);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':password', $hashedPassword);
        $insertStmt->execute();
        
        $userId = $conn->lastInsertId();
        
        // Generate session token
        $sessionToken = generateSessionToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert session
        $sessionQuery = "INSERT INTO user_sessions (user_id, session_token, expires_at) 
                        VALUES (:user_id, :token, :expires_at)";
        $sessionStmt = $conn->prepare($sessionQuery);
        $sessionStmt->bindParam(':user_id', $userId);
        $sessionStmt->bindParam(':token', $sessionToken);
        $sessionStmt->bindParam(':expires_at', $expiresAt);
        $sessionStmt->execute();
        
        // Get the created user
        $userQuery = "SELECT id, username, email, is_online, created_at FROM users WHERE id = :id";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bindParam(':id', $userId);
        $userStmt->execute();
        $user = $userStmt->fetch();
        
        jsonResponse([
            'success' => true,
            'user' => $user,
            'token' => $sessionToken,
            'message' => 'Registration successful'
        ]);
        
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        jsonResponse(['error' => 'Registration failed'], 500);
    }
}

function handleLogout() {
    $user = requireAuth();
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get token from header
        $headers = getallheaders();
        $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
        
        // Delete session
        $deleteQuery = "DELETE FROM user_sessions WHERE session_token = :token";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bindParam(':token', $token);
        $deleteStmt->execute();
        
        // Update user offline status
        $updateQuery = "UPDATE users SET is_online = FALSE, last_seen = NOW() WHERE id = :id";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();
        
        jsonResponse([
            'success' => true,
            'message' => 'Logout successful'
        ]);
        
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        jsonResponse(['error' => 'Logout failed'], 500);
    }
}
?>
