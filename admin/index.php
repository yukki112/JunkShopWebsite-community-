<?php
// admin/admin_dashboard.php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Customer-portal/Login/Login.php");
    exit();
}

// Check if user is admin (assuming you have is_admin column in users table)
$user_id = $_SESSION['user_id'];
$sql = "SELECT first_name, last_name, email, profile_image, is_admin FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user || !$user['is_admin']) {
    session_destroy();
    header("Location: ../Customer-portal/Login/Login.php");
    exit();
}

$admin_name = $user['first_name'] . ' ' . $user['last_name'];
$admin_initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));

// Get stats for dashboard
$stats = [];
$queries = [
    'today_sales' => "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE DATE(transaction_date) = CURDATE()",
    'today_transactions' => "SELECT COUNT(*) as total FROM transactions WHERE DATE(transaction_date) = CURDATE()",
    'today_profit' => "SELECT COALESCE(SUM(amount * 0.3), 0) as total FROM transactions WHERE DATE(transaction_date) = CURDATE()", // Assuming 30% profit
    'total_users' => "SELECT COUNT(*) as total FROM users" // Changed from active_users to total_users
];

foreach ($queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    if ($result) {
        $stats[$key] = mysqli_fetch_assoc($result)['total'];
    } else {
        $stats[$key] = 0;
        error_log("Query failed: " . mysqli_error($conn));
    }
}

// Get recent transactions
$transaction_query = "SELECT 
                        t.transaction_id,
                        t.transaction_type,
                        t.transaction_date,
                        t.transaction_time,
                        t.Item_details,
                        t.status,
                        t.amount,
                        t.points_earned,
                        t.points_redeemed,
                        t.created_at,
                        u.first_name,
                        u.last_name
                     FROM transactions t
                     JOIN users u ON t.user_id = u.id
                     ORDER BY t.transaction_date DESC, t.transaction_time DESC 
                     LIMIT 5";
$transactions_result = mysqli_query($conn, $transaction_query);
if (!$transactions_result) {
    error_log("Transactions query failed: " . mysqli_error($conn));
    $transactions = [];
} else {
    $transactions = mysqli_fetch_all($transactions_result, MYSQLI_ASSOC);
}

// Get sales data for charts
$sales_data = ['labels' => [], 'sales' => [], 'count' => []];
$sales_query = "SELECT 
    DATE_FORMAT(transaction_date, '%b') as month,
    SUM(amount) as total_sales,
    COUNT(*) as transaction_count
    FROM transactions
    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), DATE_FORMAT(transaction_date, '%b')
    ORDER BY DATE_FORMAT(transaction_date, '%Y-%m')";
$sales_result = mysqli_query($conn, $sales_query);

if ($sales_result) {
    while ($row = mysqli_fetch_assoc($sales_result)) {
        $sales_data['labels'][] = $row['month'];
        $sales_data['sales'][] = $row['total_sales'];
        $sales_data['count'][] = $row['transaction_count'];
    }
} else {
    error_log("Sales query failed: " . mysqli_error($conn));
}

// Get sales by category
$categories = [];
$category_sales = [];
$category_query = "SELECT 
    transaction_type as category,
    SUM(amount) as total_sales
    FROM transactions
    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
    GROUP BY transaction_type
    ORDER BY total_sales DESC";
$category_result = mysqli_query($conn, $category_query);

if ($category_result) {
    while ($row = mysqli_fetch_assoc($category_result)) {
        $categories[] = $row['category'];
        $category_sales[] = $row['total_sales'];
    }
} else {
    error_log("Category query failed: " . mysqli_error($conn));
}

// Close database connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-beige: #E6D8C3;
            --sales-orange: #D97A41;
            --stock-green: #708B4C;
            --panel-cream: #F2EAD3;
            --topbar-brown: #3C342C;
            --text-dark: #2E2B29;
            --icon-green: #6A7F46;
            --icon-orange: #D97A41;
            
            --primary-color: var(--stock-green);
            --secondary-color: var(--sales-orange);
            --accent-color: var(--icon-green);
            --dark-color: var(--topbar-brown);
            --light-color: var(--panel-cream);
            --text-color: var(--text-dark);
            --text-light: #777;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-beige);
            color: var(--text-dark);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: var(--topbar-brown);
            color: white;
            transition: all 0.3s;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .sidebar-header h2 {
            font-size: 18px;
            font-weight: 600;
        }

        .menu-group {
            margin: 25px 0;
            padding: 0 20px;
        }

        .menu-group h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }

        .menu-item:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .menu-item.active {
            background-color: var(--icon-green);
            color: white;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-info h4 {
            font-size: 14px;
            margin-bottom: 3px;
        }

        .user-info span {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
        }

        .main-container {
            flex: 1;
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background-color: white;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 18px;
            color: var(--text-dark);
            cursor: pointer;
            display: none;
        }

        .header-search {
            position: relative;
            width: 300px;
        }

        .header-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .header-search input {
            width: 100%;
            padding: 10px 15px 10px 35px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .header-search input:focus {
            outline: none;
            border-color: var(--icon-green);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-btn {
            background: none;
            border: none;
            font-size: 18px;
            color: var(--text-dark);
            position: relative;
            cursor: pointer;
        }

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--sales-orange);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        .profile-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .profile-dropdown img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-dropdown span {
            font-weight: 500;
        }

        .profile-dropdown i {
            font-size: 12px;
            color: var(--text-light);
        }

        .secondary-nav {
            padding: 15px 30px;
            background-color: var(--panel-cream);
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .secondary-nav h1 {
            font-size: 24px;
            color: var(--text-dark);
        }

        .main-content {
            flex: 1;
            padding: 30px;
            background-color: var(--bg-beige);
        }

        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .stat-card {
            text-align: center;
            padding: 25px 20px;
        }

        .stat-card .label {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .sales-card {
            border-top: 4px solid var(--sales-orange);
        }

        .stock-card {
            border-top: 4px solid var(--stock-green);
        }

        .panel-card {
            border-top: 4px solid var(--topbar-brown);
        }

        .trend-card {
            grid-column: span 2;
        }

        .trend-card h2 {
            margin-bottom: 20px;
            font-size: 18px;
            color: var(--text-dark);
        }

        .chart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            height: 300px;
        }

        .main-chart, .secondary-chart {
            position: relative;
            height: 100%;
        }

        .action-card {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background-color: var(--panel-cream);
            border: none;
            border-radius: 8px;
            color: var(--text-dark);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-align: left;
        }

        .action-btn i {
            font-size: 18px;
            color: var(--icon-green);
        }

        .action-btn:hover {
            background-color: var(--icon-green);
            color: white;
        }

        .action-btn:hover i {
            color: white;
        }

        .transactions-card {
            grid-column: span 3;
        }

        .transactions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .transactions-header h2 {
            font-size: 18px;
            color: var(--text-dark);
        }

        .transactions-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .search-box input {
            padding: 8px 15px 8px 30px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .sort-dropdown select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background-color: white;
        }

        .transactions-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .transaction {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            background-color: var(--panel-cream);
        }

        .transaction-info {
            display: flex;
            flex-direction: column;
        }

        .transaction-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .transaction-date {
            font-size: 13px;
            color: var(--text-light);
        }

        .transaction-status {
            font-size: 13px;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .status-sold {
            background-color: rgba(112, 139, 76, 0.1);
            color: var(--stock-green);
        }

        .status-purchased {
            background-color: rgba(217, 122, 65, 0.1);
            color: var(--sales-orange);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }

        .pagination-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background-color: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-info {
            font-size: 14px;
            color: var(--text-light);
        }

        .footer {
            padding: 15px 30px;
            background-color: white;
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-logo img {
            border-radius: 50%;
        }

        .footer-logo span {
            font-size: 14px;
            color: var(--text-light);
        }

        .footer-social {
            display: flex;
            gap: 15px;
        }

        .footer-social a {
            color: var(--text-light);
            font-size: 16px;
            transition: all 0.3s;
        }

        .footer-social a:hover {
            color: var(--icon-green);
        }

        /* Responsive styles */
        @media (max-width: 1200px) {
            .trend-card, .transactions-card {
                grid-column: span 2;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 100;
                height: 100vh;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-container {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .chart-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .trend-card, .transactions-card {
                grid-column: span 1;
            }

            .header-search {
                width: 200px;
            }

            .transactions-controls {
                flex-direction: column;
                align-items: flex-end;
                gap: 10px;
            }
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .header-search {
                width: 100%;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .secondary-nav {
                padding: 15px;
            }

            .main-content {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="img/rei.jpg" alt="Logo">
            <h2>JunkValue</h2>
        </div>
        <div class="sidebar-menu">
            <div class="menu-group">
                <h3>MAIN</h3>
                <a href="admin_dashboard.php" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="inventory.php" class="menu-item">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
                <a href="users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </div>
            <div class="menu-group">
                <h3>MANAGEMENT</h3>
                <a href="pricing.php" class="menu-item">
                    <i class="fas fa-tags"></i>
                    <span>Pricing Control</span>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports & Analytics</span>
                </a>
                <a href="transactions.php" class="menu-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Transactions</span>
                </a>
            </div>
            <div class="menu-group">
                <h3>ACCOUNT</h3>
                <a href="profile.php" class="menu-item">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile</span>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="../customer-portal/Login/login.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        <div class="sidebar-footer">
            <div class="user-profile">
                <?php if (!empty($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Admin">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background-color:#6A7F46;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;">
                        <?php echo $admin_initials; ?>
                    </div>
                <?php endif; ?>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($user['first_name']); ?></h4>
                    <span>Administrator</span>
                </div>
            </div>
        </div>
    </div>

    <div class="main-container">
        <header class="header">
            <button class="sidebar-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
            <div class="header-actions">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="badge">3</span>
                </button>
                <div class="profile-dropdown">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                        <div style="width:35px;height:35px;border-radius:50%;background-color:#6A7F46;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;">
                            <?php echo $admin_initials; ?>
                        </div>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($user['first_name']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </header>

        <nav class="secondary-nav">
            <h1>Dashboard Overview</h1>
        </nav>

        <main class="main-content">
            <div class="dashboard">
                <div class="card stat-card sales-card">
                    <div class="label">SALES TODAY</div>
                    <div class="value">₱<?php echo number_format($stats['today_sales'], 2); ?></div>
                </div>

                <div class="card stat-card stock-card">
                    <div class="label">TRANSACTIONS TODAY</div>
                    <div class="value"><?php echo $stats['today_transactions']; ?></div>
                </div>

                <div class="card stat-card panel-card">
                    <div class="label">ESTIMATED PROFIT</div>
                    <div class="value">₱<?php echo number_format($stats['today_profit'], 2); ?></div>
                </div>

                <div class="card stat-card panel-card">
                    <div class="label">TOTAL USERS</div>
                    <div class="value"><?php echo $stats['total_users']; ?></div>
                </div>

                <div class="card trend-card">
                    <h2>SALES TREND</h2>
                    <div class="chart-container">
                        <div class="main-chart">
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                        <div class="secondary-chart">
                            <canvas id="salesCategoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card action-card">
                    <button class="action-btn" onclick="window.location.href='inventory.php'">
                        <i class="fas fa-boxes"></i>
                        VIEW INVENTORY
                    </button>
                    <button class="action-btn" onclick="window.location.href='users.php'">
                        <i class="fas fa-user-cog"></i>
                        MANAGE USERS
                    </button>
                    <button class="action-btn" onclick="window.location.href='reports.php'">
                        <i class="fas fa-chart-line"></i>
                        REPORTS & ANALYTICS
                    </button>
                </div>

                <div class="card transactions-card">
                    <div class="transactions-header">
                        <h2>RECENT TRANSACTIONS</h2>
                        <div class="transactions-controls">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Search transactions...">
                            </div>
                            <div class="sort-dropdown">
                                <select>
                                    <option value="all">All Transactions</option>
                                    <option value="sold">Sold Only</option>
                                    <option value="purchased">Purchased Only</option>
                                    <option value="recent">Most Recent</option>
                                    <option value="oldest">Oldest First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="transactions-list">
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <div class="transaction">
                                    <div class="transaction-info">
                                        <span class="transaction-name">
                                            <?php echo htmlspecialchars($transaction['transaction_type']); ?> - 
                                            ₱<?php echo number_format($transaction['amount'], 2); ?>
                                            (<?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>)
                                        </span>
                                        <span class="transaction-date">
                                            <?php echo date('M j, Y g:i A', strtotime($transaction['transaction_date'] . ' ' . $transaction['transaction_time'])); ?>
                                        </span>
                                    </div>
                                    <span class="transaction-status status-<?php echo strtolower($transaction['transaction_type']); ?>">
                                        <?php echo htmlspecialchars($transaction['transaction_type']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: var(--text-light);">
                                <i class="fas fa-info-circle" style="font-size: 30px; margin-bottom: 15px; color: var(--icon-green);"></i>
                                <p>No recent transactions found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="pagination">
                        <button class="pagination-btn prev-btn" disabled>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <span class="page-info">Page 1 of 1</span>
                        <button class="pagination-btn next-btn" disabled>
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="img/rei.jpg" alt="JunkValue Logo" width="40">
                    <span>JunkValue &copy; <?php echo date('Y'); ?></span>
                </div>
                <div class="footer-social">
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-github"></i></a>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Sales Trend Chart (Line Chart)
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendChart = new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($sales_data['labels'] ?? []); ?>,
                datasets: [{
                    label: 'Total Sales',
                    data: <?php echo json_encode($sales_data['sales'] ?? []); ?>,
                    borderColor: '#D97A41',
                    backgroundColor: 'rgba(217, 122, 65, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Sales by Category Chart (Bar Chart)
        const salesCategoryCtx = document.getElementById('salesCategoryChart').getContext('2d');
        const salesCategoryChart = new Chart(salesCategoryCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($categories ?? []); ?>,
                datasets: [{
                    label: 'Sales by Category',
                    data: <?php echo json_encode($category_sales ?? []); ?>,
                    backgroundColor: [
                        'rgba(112, 139, 76, 0.7)',
                        'rgba(217, 122, 65, 0.7)',
                        'rgba(106, 127, 70, 0.7)',
                        'rgba(60, 52, 44, 0.7)',
                        'rgba(230, 216, 195, 0.7)'
                    ],
                    borderColor: [
                        'rgba(112, 139, 76, 1)',
                        'rgba(217, 122, 65, 1)',
                        'rgba(106, 127, 70, 1)',
                        'rgba(60, 52, 44, 1)',
                        'rgba(230, 216, 195, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>