<?php
require_once 'config.php'; // Contains database connection

class OrderManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function deleteOrder($orderId) {
        $orderId = intval($orderId);
        $stmt = $this->conn->prepare("DELETE FROM `orders` WHERE id = ?");
        $stmt->bind_param("i", $orderId);
        return $stmt->execute();
    }
    
    public function deleteUserOrders($userId) {
        $userId = intval($userId);
        $stmt = $this->conn->prepare("DELETE FROM `orders` WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        return $stmt->execute();
    }
    
    public function handleOrderDeletion() {
        // Handle POST request
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
            if (isset($_POST['order_id'])) {
                $orderId = intval($_POST['order_id']);
                if ($this->deleteOrder($orderId)) {
                    header("Location: orders.php");
                    exit();
                } else {
                    throw new Exception("Error deleting order: " . $this->conn->error);
                }
            }
        }
        
        // Handle GET requests
        if (isset($_GET['delete'])) {
            $deleteId = intval($_GET['delete']);
            if ($this->deleteUserOrders($deleteId)) {
                header('Location: orders.php');
                exit();
            } else {
                throw new Exception("Error deleting user orders: " . $this->conn->error);
            }
        }
        
        if (isset($_GET['delete_all'])) {
            $userId = intval($_GET['delete_all']);
            if ($this->deleteUserOrders($userId)) {
                header('Location: orders.php');
                exit();
            } else {
                throw new Exception("Error deleting all orders: " . $this->conn->error);
            }
        }
    }
}

// Usage
try {
    // Assuming $conn is already defined in config.php and connected
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection not available");
    }
    
    $orderManager = new OrderManager($conn);
    $orderManager->handleOrderDeletion();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    // Log the error for production: error_log($e->getMessage());
}
?>