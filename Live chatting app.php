<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'chat_app';
    private $username = 'root';
    private $password = '';
    private $conn = null;

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch(PDOException $e) {
                error_log("Connection Error: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}

// CORS headers for API requests
function setCorsHeaders() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}

// JSON response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Get JSON input
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

// Session helper functions
function generateSessionToken() {
    return bin2hex(random_bytes(32));
}

function validateSession($token) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $query = "SELECT u.*, s.session_token FROM users u 
              JOIN user_sessions s ON u.id = s.user_id 
              WHERE s.session_token = :token AND s.expires_at > NOW()";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    return $stmt->fetch();
}

function requireAuth() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $_POST['token'] ?? $_GET['token'] ?? null;
    
    if (!$token) {
        jsonResponse(['error' => 'Authentication token required'], 401);
    }
    
    // Remove 'Bearer ' prefix if present
    $token = str_replace('Bearer ', '', $token);
    
    $user = validateSession($token);
    if (!$user) {
        jsonResponse(['error' => 'Invalid or expired session'], 401);
    }
    
    return $user;
}
?>
