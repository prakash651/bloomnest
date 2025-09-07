<?php
session_start();

// Check if the user is already logged in
if (!isset($_SESSION['adminname'])) {
    header("location: index.php");
    exit;
}

include_once "config/dbconnect.php";

// Dashboard class to handle data operations
class DashboardManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getCategoryDistribution() {
        $sql = "
            SELECT c.name AS category_name, COUNT(p.id) AS flower_count
            FROM products p
            JOIN categories c ON p.category = c.id
            GROUP BY c.name
        ";
        $result = $this->conn->query($sql);
        
        $categories = [];
        $flowerCounts = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row['category_name'];
                $flowerCounts[] = $row['flower_count'];
            }
        }
        
        return [
            'categories' => $categories,
            'flowerCounts' => $flowerCounts
        ];
    }
    
    public function getSalesData($startDate = null, $endDate = null) {
        $sql = "
            SELECT placed_on, SUM(CAST(total_price AS DECIMAL(10, 2))) AS total_amount
            FROM orders
            WHERE payment_status = 'completed'
        ";
        
        // Add date filtering if provided
        if ($startDate && $endDate) {
            $sql .= " AND STR_TO_DATE(placed_on, '%d-%b-%Y') BETWEEN STR_TO_DATE('$startDate', '%Y-%m-%d') 
                     AND STR_TO_DATE('$endDate', '%Y-%m-%d')";
        }
        
        $sql .= " GROUP BY placed_on ORDER BY STR_TO_DATE(placed_on, '%d-%b-%Y')";
        
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
    
    public function getTotalCount($table) {
        $validTables = ['products', 'categories', 'user_details', 'orders'];
        
        if (!in_array($table, $validTables)) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as total FROM $table";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['total'];
        }
        
        return 0;
    }
}

// Initialize DashboardManager
$dashboardManager = new DashboardManager($conn);

// Get dashboard data
$categoryData = $dashboardManager->getCategoryDistribution();
$salesData = $dashboardManager->getSalesData();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flower Shop Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Montserrat', sans-serif;
        }
        .content {
            height: auto;
            background: url("assets/images/floral-bg.png") no-repeat center;
            background-size: contain;
            padding: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s;
        }
        #flowersByCategoryChart, #overallPurchasesChart {
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 90%;
        }
        .card-chart {
            width: 100%;
            margin-bottom: 30px;
        }
        .btn-filter {
            width: 100px;
            color: white;
            padding: 8px;
            border-radius: 5px;
            background-color: #ff7eb9;
            border: none;
            transition: all 0.3s;
        }
        .btn-filter:hover {
            background-color: #ff5fa3;
            transform: translateY(-2px);
        }
        .row {
            margin-top: 20px;
        }
        .row input {
            font-size: 15px;
            height: 40px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            padding: 0 10px;
        }
        h1 {
            color: #2d3e40;
            margin: 20px 0;
            font-family: 'Playfair Display', serif;
        }
        h3 {
            color: #7fc96b;
            margin-bottom: 15px;
        }
        .card {
            background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card i {
            margin-bottom: 15px;
        }
        .container.allContent-section {
            padding: 20px;
        }
        .form-control:focus {
            border-color: #ff7eb9;
            box-shadow: 0 0 0 0.2rem rgba(255, 126, 185, 0.25);
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .col-sm-3 {
                margin-bottom: 20px;
            }
            #flowersByCategoryChart, #overallPurchasesChart {
                padding: 10px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php include "./adminHeader.php"; ?>
    <?php include "./sidebar.php"; ?>
    
    <div id="main">
        <button class="openbtn" onclick="toggleNav()" style="width:85px; border-radius:10px; background: linear-gradient(135deg, #ff7eb9 0%, #7fc96b 100%);">
            <i class="fa fa-bars" style="font-size:30px; color:white;"></i>
        </button>
    </div>
    
    <div class="content">
        <div id="main-content" class="container allContent-section py-4">
            <div class="row">
                <div class="col-sm-3">
                    <div class="card">
                        <i class="fa fa-leaf mb-2" style="font-size: 50px; color:white;"></i>
                        <h3 style="color:white;">Flowers</h3>
                        <h5 style="color:white;"><?php echo $dashboardManager->getTotalCount('products'); ?></h5>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card">
                        <i class="fa fa-list mb-2" style="font-size: 50px; color:white;"></i>
                        <h3 style="color:white;">Categories</h3>
                        <h5 style="color:white;"><?php echo $dashboardManager->getTotalCount('categories'); ?></h5>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card">
                        <i class="fa fa-users mb-2" style="font-size: 50px; color:white;"></i>
                        <h3 style="color:white;">Customers</h3>
                        <h5 style="color:white;"><?php echo $dashboardManager->getTotalCount('user_details'); ?></h5>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="card">
                        <i class="fa fa-shopping-cart mb-2" style="font-size: 50px; color:white;"></i>
                        <h3 style="color:white;">Orders</h3>
                        <h5 style="color:white;"><?php echo $dashboardManager->getTotalCount('orders'); ?></h5>
                    </div>
                </div>
            </div>
        </div>
        
        <center>
            <h1>Flowers by Category</h1>
            <div class="col-sm-12">
                <div class="card-chart">
                    <canvas id="flowersByCategoryChart" style="max-height: 400px;"></canvas>
                </div>
            </div>
            
            <h1>Sales Overview</h1>
            <h3>Filter Sales by Date</h3>
            <div class="row justify-content-center">
                <div class="col-sm-3">
                   <input type="date" id="startDate" class="form-control" placeholder="Start Date">
                </div>
                <div class="col-sm-3">
                    <input type="date" id="endDate" class="form-control" placeholder="End Date">
                </div>
                <div class="col-sm-2">
                    <button class="btn-filter" onclick="filterSales()">Filter</button>
                </div>
            </div>
            <div class="col-sm-12">
                <div class="card-chart">
                    <canvas id="overallPurchasesChart" style="max-height: 400px;"></canvas>
                </div>
            </div>
        </center>
    </div>
    
    <?php include 'adminfooter.php' ?>
    
    <script>
        // Toggle sidebar navigation
        function toggleNav() {
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            if (sidebar.style.width === '250px') {
                sidebar.style.width = '0';
                content.style.marginLeft = '0';
            } else {
                sidebar.style.width = '250px';
                content.style.marginLeft = '250px';
            }
        }
        
        // Flowers by Category Chart
        var ctx = document.getElementById('flowersByCategoryChart').getContext('2d');
        var flowersByCategoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categoryData['categories']); ?>,
                datasets: [{
                    label: 'Number of Flowers',
                    data: <?php echo json_encode($categoryData['flowerCounts']); ?>,
                    backgroundColor: [
                        '#ff7eb9', '#7fc96b', '#ffd166', '#7eb6ff', '#c97fc9', 
                        '#ffb366', '#6bd0c9', '#ff6666', '#c9c97f', '#7f7fc9'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Flower Distribution by Category'
                    }
                }
            }
        });

        // Overall Purchases Chart
        var ctx2 = document.getElementById('overallPurchasesChart').getContext('2d');
        var purchasesLineChart = new Chart(ctx2, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($salesData['dates']); ?>,
                datasets: [{
                    label: 'Total Sales',
                    data: <?php echo json_encode($salesData['amounts']); ?>,
                    borderColor: '#ff7eb9',
                    backgroundColor: 'rgba(255, 126, 185, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Sales Over Time'
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Amount ($)'
                        }
                    }
                }
            }
        });

        function filterSales() {
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;

            if (!startDate || !endDate) {
                alert('Please select both start and end dates.');
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "fetchSalesData.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var response = JSON.parse(xhr.responseText);
                    purchasesLineChart.data.labels = response.dates;
                    purchasesLineChart.data.datasets[0].data = response.amounts;
                    purchasesLineChart.update();
                }
            };
            xhr.send("startDate=" + startDate + "&endDate=" + endDate);
        }
    </script>
</body>
</html>