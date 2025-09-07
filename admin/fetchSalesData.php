<?php
session_start();
include_once "config/dbconnect.php";

class SalesDataFetcher {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getFilteredSales($startDate, $endDate) {
        $sql = "
            SELECT placed_on, SUM(CAST(total_price AS DECIMAL(10, 2))) AS total_amount
            FROM orders
            WHERE payment_status = 'completed'
            AND STR_TO_DATE(placed_on, '%d-%b-%Y') BETWEEN STR_TO_DATE('$startDate', '%Y-%m-%d') 
            AND STR_TO_DATE('$endDate', '%Y-%m-%d')
            GROUP BY placed_on 
            ORDER BY STR_TO_DATE(placed_on, '%d-%b-%Y')
        ";
        
        $result = $this->conn->query($sql);
        
        $purchaseDates = [];
        $totalAmounts = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $purchaseDates[] = $row['placed_on'];
                $totalAmounts[] = (float)$row['total_amount'];
            }
        }
        
        return [
            'dates' => $purchaseDates,
            'amounts' => $totalAmounts
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['startDate']) && isset($_POST['endDate'])) {
    $salesFetcher = new SalesDataFetcher($conn);
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    
    $data = $salesFetcher->getFilteredSales($startDate, $endDate);
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}