<?php
// Start session and include database connection
session_start();
require_once 'db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user ID
$user_id = $_SESSION['user_id'];

// Check if clear filters was clicked
if (isset($_GET['clear_filters'])) {
    header("Location: transactions.php");
    exit();
}

// Initialize filter variables with proper sanitization
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Pagination variables
$transactions_per_page = 5; // Changed from 10 to 5
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $transactions_per_page;

// Build base SQL query with prepared statement approach
$sql = "SELECT * FROM transactions WHERE user_id = ?";

// Initialize parameters array
$params = array($user_id);
$types = "i"; // i for integer

// Add filters to query
if (!empty($type_filter)) {
    $sql .= " AND transaction_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND transaction_date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= "ss";
}

if (!empty($search_query)) {
    $sql .= " AND (transaction_id LIKE CONCAT('%', ?, '%') 
              OR item_details LIKE CONCAT('%', ?, '%'))";
    $params[] = $search_query;
    $params[] = $search_query;
    $types .= "ss";
}

// Order by most recent first
$sql .= " ORDER BY transaction_date DESC, transaction_time DESC";

// Get total count for pagination
$count_sql = $sql;
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($count_stmt === false) {
    die("Error preparing count statement: " . mysqli_error($conn));
}

// Bind parameters for count query
mysqli_stmt_bind_param($count_stmt, $types, ...$params);

// Execute count query
if (!mysqli_stmt_execute($count_stmt)) {
    die("Error executing count statement: " . mysqli_stmt_error($count_stmt));
}

$count_result = mysqli_stmt_get_result($count_stmt);
$total_transactions = mysqli_num_rows($count_result);
$total_pages = ceil($total_transactions / $transactions_per_page);

// Add LIMIT to main query for pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $transactions_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute main query with proper parameter binding
$stmt = mysqli_prepare($conn, $sql);
if ($stmt === false) {
    die("Error preparing statement: " . mysqli_error($conn));
}

// Bind parameters for main query
mysqli_stmt_bind_param($stmt, $types, ...$params);

// Execute query
if (!mysqli_stmt_execute($stmt)) {
    die("Error executing statement: " . mysqli_stmt_error($stmt));
}

// Get result
$result = mysqli_stmt_get_result($stmt);

// Get user info for header
$user_query = "SELECT first_name, last_name, profile_image FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
$user_name = $user['first_name'] . ' ' . $user['last_name'];
$user_initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JunkValue - Transaction History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background-color: var(--topbar-brown);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--panel-cream);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo::before {
            content: "♻";
            font-size: 28px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            font-weight: 500;
            color: var(--panel-cream);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--panel-cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--topbar-brown);
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
            padding: 30px 0;
        }

        .sidebar {
            background-color: var(--panel-cream);
            border-radius: 15px;
            padding: 25px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 80px;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li {
            margin-bottom: 5px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 25px;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .nav-menu a:hover {
            background-color: rgba(106, 127, 70, 0.1);
            border-left-color: var(--icon-green);
        }

        .nav-menu a.active {
            background-color: rgba(106, 127, 70, 0.15);
            border-left-color: var(--icon-green);
            color: var(--icon-green);
        }

        .nav-menu i {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            animation: fadeIn 0.5s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--icon-green);
        }

        /* Transaction History Specific Styles */
        .transaction-filters {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        select, .date-input, .search-bar input {
            padding: 10px 15px;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            background-color: white;
            font-size: 14px;
            transition: all 0.3s;
            width: 100%;
        }
        
        select:focus, .date-input:focus, .search-bar input:focus {
            outline: none;
            border-color: var(--icon-green);
            box-shadow: 0 0 0 3px rgba(106, 127, 70, 0.1);
        }
        
        .search-bar {
            position: relative;
            grid-column: 1 / -1;
        }
        
        .search-bar input {
            padding-left: 40px;
        }
        
        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            grid-column: 1 / -1;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--icon-green) 0%, var(--stock-green) 100%);
            color: white;
            box-shadow: 0 3px 10px rgba(106, 127, 70, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 127, 70, 0.4);
        }
        
        .btn-secondary {
            background-color: white;
            color: var(--text-dark);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .btn-secondary:hover {
            background-color: #f8f9fa;
            border-color: rgba(0,0,0,0.2);
        }
        
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .transaction-table th {
            background-color: rgba(106, 127, 70, 0.05);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--icon-green);
            font-size: 14px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
        }
        
        .transaction-table td {
            padding: 16px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            vertical-align: middle;
            font-size: 14px;
        }
        
        .transaction-table tr:not(:last-child) {
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .transaction-table tr:hover {
            background-color: rgba(106, 127, 70, 0.03);
        }
        
        .transaction-id {
            color: var(--icon-green);
            font-weight: 500;
            font-family: 'Courier New', monospace;
        }
        
        .transaction-items {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .transaction-status {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            gap: 5px;
        }
        
        .transaction-status i {
            font-size: 10px;
        }
        
        .status-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #d39e00;
        }
        
        .status-cancelled {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .transaction-amount {
            font-weight: 600;
            white-space: nowrap;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 8px;
            align-items: center;
        }
        
        .page-btn {
            min-width: 40px;
            height: 40px;
            border: 1px solid rgba(0,0,0,0.1);
            background-color: white;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            color: var(--text-dark);
            transition: all 0.2s ease;
            text-decoration: none;
            padding: 0 12px;
        }
        
        .page-btn:hover:not(.active, .disabled) {
            background-color: rgba(106, 127, 70, 0.1);
            border-color: var(--icon-green);
            color: var(--icon-green);
        }
        
        .page-btn.active {
            background-color: var(--icon-green);
            color: white;
            border-color: var(--icon-green);
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(106, 127, 70, 0.2);
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-btn i {
            font-size: 14px;
        }
        
        .page-dots {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
        }
        
        .no-transactions {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }
        
        .no-transactions i {
            font-size: 40px;
            margin-bottom: 15px;
            color: rgba(106, 127, 70, 0.3);
        }
        
        .no-transactions p {
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .export-btn {
            background: linear-gradient(90deg, var(--sales-orange) 0%, #e67a41 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(217, 122, 65, 0.3);
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(217, 122, 65, 0.4);
        }
        
        /* Responsive styles */
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .transaction-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .transaction-filters {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .transaction-filters {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .page-btn {
                min-width: 36px;
                height: 36px;
                font-size: 14px;
            }
            
            .page-dots {
                width: 36px;
                height: 36px;
            }
            
            .card {
                padding: 20px 15px;
            }
            
            .transaction-table td, 
            .transaction-table th {
                padding: 12px 10px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">JunkValue</div>
            <div class="user-info">
                <span>Hello, <?php echo htmlspecialchars($user_name); ?>!</span>
                <div class="user-avatar">
                    <?php if (!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $user_initials; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="dashboard">
            <!-- Sidebar -->
            <div class="sidebar">
                <ul class="nav-menu">
                    <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="#" class="active"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> Schedule Pickup</a></li>
                    <li><a href="prices.php"><i class="fas fa-coins"></i> Current Prices</a></li>
                    <li><a href="rewards.php"><i class="fas fa-award"></i> Loyalty Rewards</a></li>
                    <li><a href="settings.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                    <li><a href="login/login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history"></i> Transaction History</h3>
                        <button class="export-btn">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <form method="GET" action="">
                        <input type="hidden" name="page" value="1"> <!-- Reset to page 1 when filters change -->
                        <div class="transaction-filters">
                            <div class="filter-group">
                                <label>Transaction Type</label>
                                <select name="type">
                                    <option value="">All Types</option>
                                    <option value="Pickup" <?php echo ($type_filter == 'Pickup') ? 'selected' : ''; ?>>Pickups</option>
                                    <option value="Walk-in" <?php echo ($type_filter == 'Walk-in') ? 'selected' : ''; ?>>Walk-in Sales</option>
                                    <option value="Loyalty" <?php echo ($type_filter == 'Loyalty') ? 'selected' : ''; ?>>Loyalty Rewards</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Completed" <?php echo ($status_filter == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Pending" <?php echo ($status_filter == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Cancelled" <?php echo ($status_filter == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Date From</label>
                                <input type="date" class="date-input" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label>Date To</label>
                                <input type="date" class="date-input" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            
                            <div class="search-bar">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search transactions..." value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                            
                            <div class="filter-buttons">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                
                                <a href="transaction.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Transaction Table -->
                    <div style="overflow-x: auto;">
                        <table class="transaction-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date of Transacation</th>
                                    <th>Type</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($transaction = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td class="transaction-id">#<?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                            
                                            </td>
                                            <td>
                                                <?php 
                                                $type_icon = '';
                                                switch($transaction['transaction_type']) {
                                                    case 'Pickup': $type_icon = 'fa-truck'; break;
                                                    case 'Walk-in': $type_icon = 'fa-walking'; break;
                                                    case 'Loyalty': $type_icon = 'fa-award'; break;
                                                    default: $type_icon = 'fa-exchange-alt';
                                                }
                                                ?>
                                                <i class="fas <?php echo $type_icon; ?>" style="margin-right: 8px; color: var(--icon-green);"></i>
                                                <?php echo htmlspecialchars($transaction['transaction_type']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($transaction['item_details']); ?>
                                                <?php if (!empty($transaction['additional_info'])): ?>
                                                    <div class="transaction-items">
                                                        <?php echo htmlspecialchars($transaction['additional_info']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = 'status-' . strtolower($transaction['status']);
                                                $status_icon = '';
                                                switch($transaction['status']) {
                                                    case 'Completed': $status_icon = 'fa-check-circle'; break;
                                                    case 'Pending': $status_icon = 'fa-clock'; break;
                                                    case 'Cancelled': $status_icon = 'fa-times-circle'; break;
                                                }
                                                echo '<span class="transaction-status ' . $status_class . '">' . 
                                                     '<i class="fas ' . $status_icon . '"></i>' . 
                                                     htmlspecialchars($transaction['status']) . '</span>';
                                                ?>
                                            </td>
                                            <td class="transaction-amount" style="color: <?php 
                                                echo ($transaction['transaction_type'] == 'Loyalty') ? 'var(--sales-orange)' : 'var(--icon-green)';
                                            ?>;">
                                                +₱<?php echo number_format($transaction['amount'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-transactions">
                                            <i class="fas fa-info-circle"></i>
                                            <p>No transactions found</p>
                                            <?php if (!empty($type_filter) || !empty($status_filter) || !empty($date_from) || !empty($search_query)): ?>
                                                <p>Try adjusting your filters or <a href="transaction.php" style="color: var(--icon-green);">clear all filters</a></p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn" title="First Page">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" class="page-btn" title="Previous Page">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // Show page numbers
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1) {
                                echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="page-btn">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="page-dots">...</span>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = ($i == $current_page) ? 'active' : '';
                                echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="page-btn ' . $active . '">' . $i . '</a>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="page-dots">...</span>';
                                }
                                echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '" class="page-btn">' . $total_pages . '</a>';
                            }
                            ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" class="page-btn" title="Next Page">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn" title="Last Page">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// Close prepared statements
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
if (isset($count_stmt)) {
    mysqli_stmt_close($count_stmt);
}
if (isset($user_stmt)) {
    mysqli_stmt_close($user_stmt);
}
// Close connection
mysqli_close($conn);
?>