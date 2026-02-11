<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

// Check if user is admin
$admin_check = "SELECT is_admin FROM users WHERE user_id = $user_id";
$admin_result = mysqli_query($conn, $admin_check);
$user_data = mysqli_fetch_assoc($admin_result);

if (!$user_data || $user_data['is_admin'] != 1) {
    header("Location: dashboard.php");
    exit();
}

// Handle recipe actions
$message = '';
$message_type = '';

// Add new recipe
if (isset($_POST['add_recipe'])) {
    $recipe_name = mysqli_real_escape_string($conn, $_POST['recipe_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $instructions = mysqli_real_escape_string($conn, $_POST['instructions']);
    $prep_time = mysqli_real_escape_string($conn, $_POST['prep_time']);
    $serving_size = intval($_POST['serving_size']);
    $meal_type = mysqli_real_escape_string($conn, $_POST['meal_type']);
    $estimated_cost = floatval($_POST['estimated_cost']);
    $calories = intval($_POST['calories']);
    $protein = floatval($_POST['protein']);
    $carbs = floatval($_POST['carbs']);
    $fats = floatval($_POST['fats']);
    
    $sql = "INSERT INTO recipes (recipe_name, description, instructions, prep_time, serving_size, 
            meal_type, estimated_cost, calories, protein, carbs, fats) 
            VALUES ('$recipe_name', '$description', '$instructions', '$prep_time', $serving_size, 
            '$meal_type', $estimated_cost, $calories, $protein, $carbs, $fats)";
    
    if (mysqli_query($conn, $sql)) {
        $message = "Recipe added successfully!";
        $message_type = 'success';
    } else {
        $message = "Error adding recipe: " . mysqli_error($conn);
        $message_type = 'error';
    }
}

// Update recipe
if (isset($_POST['update_recipe'])) {
    $recipe_id = intval($_POST['recipe_id']);
    $recipe_name = mysqli_real_escape_string($conn, $_POST['recipe_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $instructions = mysqli_real_escape_string($conn, $_POST['instructions']);
    $prep_time = mysqli_real_escape_string($conn, $_POST['prep_time']);
    $serving_size = intval($_POST['serving_size']);
    $meal_type = mysqli_real_escape_string($conn, $_POST['meal_type']);
    $estimated_cost = floatval($_POST['estimated_cost']);
    $calories = intval($_POST['calories']);
    $protein = floatval($_POST['protein']);
    $carbs = floatval($_POST['carbs']);
    $fats = floatval($_POST['fats']);
    
    $sql = "UPDATE recipes SET 
            recipe_name = '$recipe_name',
            description = '$description',
            instructions = '$instructions',
            prep_time = '$prep_time',
            serving_size = $serving_size,
            meal_type = '$meal_type',
            estimated_cost = $estimated_cost,
            calories = $calories,
            protein = $protein,
            carbs = $carbs,
            fats = $fats
            WHERE recipe_id = $recipe_id";
    
    if (mysqli_query($conn, $sql)) {
        $message = "Recipe updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating recipe: " . mysqli_error($conn);
        $message_type = 'error';
    }
}

// Delete recipe
if (isset($_GET['delete_recipe'])) {
    $recipe_id = intval($_GET['delete_recipe']);
    
    // First delete recipe ingredients
    $delete_ingredients = "DELETE FROM recipe_ingredients WHERE recipe_id = $recipe_id";
    mysqli_query($conn, $delete_ingredients);
    
    // Then delete recipe
    $delete_recipe = "DELETE FROM recipes WHERE recipe_id = $recipe_id";
    
    if (mysqli_query($conn, $delete_recipe)) {
        $message = "Recipe deleted successfully!";
        $message_type = 'success';
    } else {
        $message = "Error deleting recipe: " . mysqli_error($conn);
        $message_type = 'error';
    }
}

// Get all recipes for display
$recipes_query = "SELECT * FROM recipes ORDER BY recipe_name";
$recipes_result = mysqli_query($conn, $recipes_query);

// Get statistics for dashboard
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'];
$total_recipes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM recipes"))['count'];
$total_meal_plans = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM meal_plans"))['count'];
$total_activity = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM user_activity"))['count'];

// Get recent activity
$recent_activity_query = "SELECT ua.*, u.full_name 
                          FROM user_activity ua 
                          JOIN users u ON ua.user_id = u.user_id 
                          ORDER BY ua.created_at DESC LIMIT 10";
$recent_activity_result = mysqli_query($conn, $recent_activity_query);

// Get user growth data for chart
$user_growth_query = "SELECT DATE(date_registered) as date, COUNT(*) as count 
                      FROM users 
                      WHERE date_registered >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      GROUP BY DATE(date_registered)
                      ORDER BY date";
$user_growth_result = mysqli_query($conn, $user_growth_query);
$user_growth_data = [];
while ($row = mysqli_fetch_assoc($user_growth_result)) {
    $user_growth_data[] = $row;
}

// Get recipe categories data
$recipe_categories_query = "SELECT meal_type, COUNT(*) as count 
                            FROM recipes 
                            GROUP BY meal_type";
$recipe_categories_result = mysqli_query($conn, $recipe_categories_query);
$recipe_categories_data = [];
while ($row = mysqli_fetch_assoc($recipe_categories_result)) {
    $recipe_categories_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Meal Plan System</title>
    <style>
        :root {
            --primary-green: #27ae60;
            --secondary-green: #2ecc71;
            --accent-orange: #e67e22;
            --accent-red: #e74c3c;
            --accent-blue: #3498db;
            --accent-purple: #9b59b6;
            --dark-green: #2e8b57;
            --light-green: #d5f4e6;
            --light-bg: #f9fdf7;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --border-color: #e1f5e1;
            --warning-yellow: #f39c12;
            --success-blue: #3498db;
            --admin-purple: #8e44ad;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Roboto', sans-serif;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #2c3e50, #34495e);
            color: white;
            padding: 25px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        }
        
        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--admin-purple), #9b59b6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-right: 15px;
        }
        
        .logo-text h2 {
            color: white;
            font-size: 22px;
        }
        
        .logo-text p {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
        }
        
        .admin-welcome {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .admin-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--admin-purple), #8e44ad);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        
        .nav-menu {
            list-style: none;
            margin-bottom: 30px;
        }
        
        .nav-menu li {
            margin-bottom: 8px;
        }
        
        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .nav-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-menu a.active {
            background: var(--admin-purple);
            color: white;
        }
        
        .nav-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 18px;
        }
        
        .admin-badge {
            background: var(--admin-purple);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }
        
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .welcome-message h1 {
            color: var(--admin-purple);
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .welcome-message p {
            color: var(--text-light);
            font-size: 16px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--admin-purple);
            color: white;
        }
        
        .btn-primary:hover {
            background: #7d3c98;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(142, 68, 173, 0.3);
        }
        
        .btn-outline {
            background: white;
            color: var(--admin-purple);
            border: 2px solid var(--admin-purple);
        }
        
        .btn-outline:hover {
            background: #f8f0ff;
        }
        
        .btn-success {
            background: var(--primary-green);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-yellow);
            color: white;
        }
        
        .btn-danger {
            background: var(--accent-red);
            color: white;
        }
        
        .btn-info {
            background: var(--accent-blue);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .content-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .section-header h2 {
            color: var(--admin-purple);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }
        
        .stat-card.total-users::before { background: linear-gradient(90deg, var(--admin-purple), #9b59b6); }
        .stat-card.total-recipes::before { background: linear-gradient(90deg, var(--primary-green), var(--secondary-green)); }
        .stat-card.total-plans::before { background: linear-gradient(90deg, var(--accent-blue), #2980b9); }
        .stat-card.total-activity::before { background: linear-gradient(90deg, var(--accent-orange), #e67e22); }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 24px;
        }
        
        .stat-icon.users { background: linear-gradient(135deg, var(--admin-purple), #9b59b6); }
        .stat-icon.recipes { background: linear-gradient(135deg, var(--primary-green), var(--secondary-green)); }
        .stat-icon.plans { background: linear-gradient(135deg, var(--accent-blue), #2980b9); }
        .stat-icon.activity { background: linear-gradient(135deg, var(--accent-orange), #e67e22); }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
        }
        
        /* Charts Container */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border: 2px solid var(--border-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            color: var(--text-dark);
            font-size: 18px;
        }
        
        /* Tables */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .admin-table th {
            background: var(--light-bg);
            color: var(--admin-purple);
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
        }
        
        .admin-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .admin-table tr:hover {
            background: var(--light-bg);
        }
        
        .table-actions {
            display: flex;
            gap: 8px;
        }
        
        /* Recipe Form */
        .recipe-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--admin-purple);
            box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.1);
        }
        
        .form-full {
            grid-column: 1 / -1;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .modal-header h3 {
            color: var(--admin-purple);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        /* Activity Feed */
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin-purple);
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-text {
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .activity-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: var(--text-light);
        }
        
        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-primary { background: #d1c4e9; color: #4527a0; }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .dashboard-container {
                flex-direction: column;
            }
            
            .top-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-table {
                display: block;
                overflow-x: auto;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="logo-text">
                    <h2>NutriPlan KE</h2>
                    <p>Admin Dashboard</p>
                </div>
            </div>
            
            <div class="admin-welcome">
                <div class="admin-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h3>Welcome, Admin!</h3>
                <p>System Administrator</p>
                <span class="admin-badge">ADMIN</span>
            </div>
            
            <ul class="nav-menu">
                <li><a href="admin-dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#recipes" onclick="showSection('recipes')"><i class="fas fa-utensils"></i> Manage Recipes</a></li>
                <li><a href="#nutrition" onclick="showSection('nutrition')"><i class="fas fa-chart-line"></i> Nutritional Reports</a></li>
                <li><a href="#users" onclick="showSection('users')"><i class="fas fa-users"></i> User Management</a></li>
                <li><a href="#activity" onclick="showSection('activity')"><i class="fas fa-history"></i> System Activity</a></li>
                <li><a href="dashboard.php"><i class="fas fa-exchange-alt"></i> Switch to User View</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="welcome-message">
                    <h1>Admin Dashboard</h1>
                    <p>Manage recipes, view reports, and monitor system activity</p>
                </div>
                <div class="header-actions">
                    <span class="badge badge-primary">Last login: <?php echo date('h:i A'); ?></span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard Overview -->
            <div id="dashboard" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-tachometer-alt"></i> System Overview</h2>
                    <div class="header-actions">
                        <span class="badge badge-info">Last updated: <?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card total-users">
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_users; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    
                    <div class="stat-card total-recipes">
                        <div class="stat-icon recipes">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_recipes; ?></div>
                        <div class="stat-label">Recipes</div>
                    </div>
                    
                    <div class="stat-card total-plans">
                        <div class="stat-icon plans">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_meal_plans; ?></div>
                        <div class="stat-label">Meal Plans</div>
                    </div>
                    
                    <div class="stat-card total-activity">
                        <div class="stat-icon activity">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_activity; ?></div>
                        <div class="stat-label">Activities</div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="charts-grid">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>User Growth (Last 30 Days)</h3>
                            <span class="badge badge-success">+<?php echo count($user_growth_data); ?> days</span>
                        </div>
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Recipe Categories</h3>
                            <span class="badge badge-primary"><?php echo count($recipe_categories_data); ?> categories</span>
                        </div>
                        <canvas id="recipeCategoriesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Manage Recipes Section -->
            <div id="recipes" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-utensils"></i> Manage Recipes</h2>
                    <button class="btn btn-primary" onclick="showAddRecipeModal()">
                        <i class="fas fa-plus"></i> Add New Recipe
                    </button>
                </div>
                
                <!-- Recipes Table -->
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="5%">ID</th>
                            <th width="20%">Recipe Name</th>
                            <th width="15%">Meal Type</th>
                            <th width="10%">Calories</th>
                            <th width="15%">Nutrition (P/C/F)</th>
                            <th width="15%">Cost</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($recipe = mysqli_fetch_assoc($recipes_result)): ?>
                            <tr>
                                <td><?php echo $recipe['recipe_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($recipe['recipe_name']); ?></strong>
                                    <div style="font-size: 12px; color: var(--text-light);">
                                        <?php echo strlen($recipe['description']) > 50 ? substr(htmlspecialchars($recipe['description']), 0, 50) . '...' : htmlspecialchars($recipe['description']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php echo $recipe['meal_type'] == 'Breakfast' ? 'badge-warning' : 
                                               ($recipe['meal_type'] == 'Lunch' ? 'badge-success' : 
                                               ($recipe['meal_type'] == 'Dinner' ? 'badge-primary' : 'badge-info')); ?>">
                                        <?php echo $recipe['meal_type']; ?>
                                    </span>
                                </td>
                                <td><?php echo $recipe['calories'] ?? 'N/A'; ?></td>
                                <td>
                                    <?php if ($recipe['protein'] && $recipe['carbs'] && $recipe['fats']): ?>
                                        <?php echo $recipe['protein']; ?>g / <?php echo $recipe['carbs']; ?>g / <?php echo $recipe['fats']; ?>g
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>KES <?php echo number_format($recipe['estimated_cost'], 0); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn btn-sm btn-info" onclick="editRecipe(<?php echo $recipe['recipe_id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="viewRecipe(<?php echo $recipe['recipe_id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <a href="?delete_recipe=<?php echo $recipe['recipe_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this recipe?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Nutritional Reports Section -->
            <div id="nutrition" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-chart-line"></i> Nutritional Reports</h2>
                    <div class="header-actions">
                        <button class="btn btn-outline" onclick="exportNutritionData()">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                    </div>
                </div>
                
                <div class="charts-grid">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Average Nutritional Values</h3>
                            <span class="badge badge-info">All Recipes</span>
                        </div>
                        <canvas id="nutritionChart"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Cost vs Calories</h3>
                            <span class="badge badge-warning">KES per 100 calories</span>
                        </div>
                        <canvas id="costCaloriesChart"></canvas>
                    </div>
                </div>
                
                <!-- Nutritional Summary -->
                <div style="margin-top: 30px;">
                    <h3 style="color: var(--admin-purple); margin-bottom: 20px;">Nutritional Summary</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div style="background: var(--light-bg); padding: 20px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: var(--primary-green);">
                                <?php 
                                $avg_calories = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(calories) as avg FROM recipes WHERE calories > 0"))['avg'];
                                echo number_format($avg_calories, 0);
                                ?>
                            </div>
                            <div style="color: var(--text-light); font-size: 14px;">Avg Calories</div>
                        </div>
                        
                        <div style="background: var(--light-bg); padding: 20px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: var(--accent-blue);">
                                <?php 
                                $avg_protein = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(protein) as avg FROM recipes WHERE protein > 0"))['avg'];
                                echo number_format($avg_protein, 1); ?>g
                            </div>
                            <div style="color: var(--text-light); font-size: 14px;">Avg Protein</div>
                        </div>
                        
                        <div style="background: var(--light-bg); padding: 20px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: var(--accent-orange);">
                                <?php 
                                $avg_cost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(estimated_cost) as avg FROM recipes WHERE estimated_cost > 0"))['avg'];
                                echo 'KES ' . number_format($avg_cost, 0);
                                ?>
                            </div>
                            <div style="color: var(--text-light); font-size: 14px;">Avg Cost</div>
                        </div>
                        
                        <div style="background: var(--light-bg); padding: 20px; border-radius: 10px; text-align: center;">
                            <div style="font-size: 24px; font-weight: bold; color: var(--admin-purple);">
                                <?php 
                                $recipe_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM recipes WHERE calories > 0 AND protein > 0"))['count'];
                                echo $recipe_count;
                                ?>
                            </div>
                            <div style="color: var(--text-light); font-size: 14px;">Complete Nutrition Data</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Feed Section -->
            <div id="activity" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent System Activity</h2>
                    <button class="btn btn-outline" onclick="refreshActivity()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                
                <div class="activity-feed">
                    <?php while ($activity = mysqli_fetch_assoc($recent_activity_result)): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?php 
                                    echo $activity['activity_type'] == 'plan_generated' ? 'magic' : 
                                           ($activity['activity_type'] == 'plan_saved' ? 'save' : 
                                           ($activity['activity_type'] == 'login' ? 'sign-in-alt' : 'history')); 
                                ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong> 
                                    <?php echo htmlspecialchars($activity['activity_type']); ?>
                                    <?php if ($activity['activity_details']): ?>
                                        - <?php echo htmlspecialchars($activity['activity_details']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-meta">
                                    <span><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($activity['created_at'])); ?></span>
                                    <span><i class="far fa-calendar"></i> <?php echo date('M j, Y', strtotime($activity['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <!-- Add/Edit Recipe Modal -->
            <div id="recipeModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="modalTitle">Add New Recipe</h3>
                        <button class="close-modal" onclick="closeModal()">&times;</button>
                    </div>
                    <form id="recipeForm" method="POST" action="">
                        <input type="hidden" id="recipe_id" name="recipe_id">
                        
                        <div class="recipe-form">
                            <div class="form-group">
                                <label for="recipe_name">Recipe Name *</label>
                                <input type="text" id="recipe_name" name="recipe_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="meal_type">Meal Type *</label>
                                <select id="meal_type" name="meal_type" class="form-control" required>
                                    <option value="">Select Meal Type</option>
                                    <option value="Breakfast">Breakfast</option>
                                    <option value="Lunch">Lunch</option>
                                    <option value="Dinner">Dinner</option>
                                    <option value="Snack">Snack</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="prep_time">Prep Time</label>
                                <input type="text" id="prep_time" name="prep_time" class="form-control" placeholder="e.g., 30 minutes">
                            </div>
                            
                            <div class="form-group">
                                <label for="serving_size">Serving Size</label>
                                <input type="number" id="serving_size" name="serving_size" class="form-control" min="1" value="4">
                            </div>
                            
                            <div class="form-group form-full">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group form-full">
                                <label for="instructions">Instructions *</label>
                                <textarea id="instructions" name="instructions" class="form-control" rows="5" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="estimated_cost">Estimated Cost (KES) *</label>
                                <input type="number" id="estimated_cost" name="estimated_cost" class="form-control" min="0" step="10" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="calories">Calories</label>
                                <input type="number" id="calories" name="calories" class="form-control" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="protein">Protein (g)</label>
                                <input type="number" id="protein" name="protein" class="form-control" min="0" step="0.1">
                            </div>
                            
                            <div class="form-group">
                                <label for="carbs">Carbs (g)</label>
                                <input type="number" id="carbs" name="carbs" class="form-control" min="0" step="0.1">
                            </div>
                            
                            <div class="form-group">
                                <label for="fats">Fats (g)</label>
                                <input type="number" id="fats" name="fats" class="form-control" min="0" step="0.1">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" id="submitBtn" name="add_recipe" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Recipe
                            </button>
                            <button type="button" class="btn btn-outline" onclick="closeModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Section navigation
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(sectionId).style.display = 'block';
            
            // Update active nav
            document.querySelectorAll('.nav-menu a').forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        // Modal functions
        function showAddRecipeModal() {
            document.getElementById('modalTitle').textContent = 'Add New Recipe';
            document.getElementById('recipeForm').reset();
            document.getElementById('recipe_id').value = '';
            document.getElementById('submitBtn').name = 'add_recipe';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Save Recipe';
            document.getElementById('recipeModal').style.display = 'flex';
        }
        
        function editRecipe(recipeId) {
            // In a real implementation, you would fetch recipe data via AJAX
            // For now, we'll redirect to an edit page
            window.location.href = `edit-recipe.php?id=${recipeId}`;
        }
        
        function viewRecipe(recipeId) {
            window.open(`recipe-details.php?id=${recipeId}`, '_blank');
        }
        
        function closeModal() {
            document.getElementById('recipeModal').style.display = 'none';
        }
        
        function refreshActivity() {
            location.reload();
        }
        
        function exportNutritionData() {
            // In a real implementation, this would generate and download a CSV/PDF
            alert('Export feature would generate a report file. To be implemented.');
        }
        
        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            // User Growth Chart
            const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
            const userGrowthChart = new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($user_growth_data, 'date')); ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode(array_column($user_growth_data, 'count')); ?>,
                        borderColor: '#8e44ad',
                        backgroundColor: 'rgba(142, 68, 173, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            // Recipe Categories Chart
            const recipeCategoriesCtx = document.getElementById('recipeCategoriesChart').getContext('2d');
            const recipeCategoriesChart = new Chart(recipeCategoriesCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($recipe_categories_data, 'meal_type')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($recipe_categories_data, 'count')); ?>,
                        backgroundColor: [
                            '#f39c12', // Breakfast
                            '#27ae60', // Lunch
                            '#3498db', // Dinner
                            '#9b59b6', // Snack
                            '#e74c3c', // Other
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Nutrition Chart (sample data)
            const nutritionCtx = document.getElementById('nutritionChart').getContext('2d');
            const nutritionChart = new Chart(nutritionCtx, {
                type: 'bar',
                data: {
                    labels: ['Protein', 'Carbs', 'Fats'],
                    datasets: [{
                        label: 'Average grams per recipe',
                        data: [<?php echo $avg_protein; ?>, 65, <?php echo $avg_protein * 0.8; ?>],
                        backgroundColor: [
                            '#3498db',
                            '#2ecc71',
                            '#f39c12'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Cost vs Calories Chart (sample data)
            const costCaloriesCtx = document.getElementById('costCaloriesChart').getContext('2d');
            const costCaloriesChart = new Chart(costCaloriesCtx, {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Recipes',
                        data: [
                            {x: 250, y: 120},
                            {x: 420, y: 150},
                            {x: 680, y: 220},
                            {x: 320, y: 80},
                            {x: 580, y: 180},
                            {x: 750, y: 450},
                            {x: 180, y: 60}
                        ],
                        backgroundColor: '#8e44ad'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Calories'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Cost (KES)'
                            }
                        }
                    }
                }
            });
        });
        
        // Initialize with dashboard visible
        document.addEventListener('DOMContentLoaded', function() {
            showSection('dashboard');
        });
    </script>
</body>
</html>