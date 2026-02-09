<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

// 1. Get user's stats from database
$stats = [
    'total_plans' => 0,
    'active_plans' => 0,
    'budget_used' => 0,
    'pantry_items' => 0,
    'plans_generated' => 0, // NEW: Track plan generations
    'generated_this_week' => 0, // NEW: Plans generated this week
    'generated_this_month' => 0 // NEW: Plans generated this month
];

// Total meal plans (SAVED plans)
$plans_query = "SELECT COUNT(*) as total FROM meal_plans WHERE user_id = $user_id";
$result = mysqli_query($conn, $plans_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['total_plans'] = mysqli_fetch_assoc($result)['total'];
}

// Active meal plans (this week)
$active_query = "SELECT COUNT(*) as active FROM meal_plans 
                 WHERE user_id = $user_id 
                 AND start_date <= CURDATE() 
                 AND end_date >= CURDATE()";
$result = mysqli_query($conn, $active_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['active_plans'] = mysqli_fetch_assoc($result)['active'];
}

// Budget used (this week) - FIXED: Using amount instead of spent
$budget_query = "SELECT COALESCE(SUM(amount), 0) as budget_amount FROM budgets 
                 WHERE user_id = $user_id 
                 AND start_date <= CURDATE() 
                 AND end_date >= CURDATE()";
$result = mysqli_query($conn, $budget_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['budget_used'] = mysqli_fetch_assoc($result)['budget_amount'];
}

// NEW: Get total plans generated (from user_activity table)
$generated_query = "SELECT COUNT(*) as generated_count FROM user_activity 
                    WHERE user_id = $user_id 
                    AND activity_type = 'plan_generated'";
$result = mysqli_query($conn, $generated_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['plans_generated'] = mysqli_fetch_assoc($result)['generated_count'];
}

// NEW: Get plans generated this week
$week_generated_query = "SELECT COUNT(*) as week_count FROM user_activity 
                         WHERE user_id = $user_id 
                         AND activity_type = 'plan_generated' 
                         AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
$result = mysqli_query($conn, $week_generated_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['generated_this_week'] = mysqli_fetch_assoc($result)['week_count'];
}

// NEW: Get plans generated this month
$month_generated_query = "SELECT COUNT(*) as month_count FROM user_activity 
                          WHERE user_id = $user_id 
                          AND activity_type = 'plan_generated' 
                          AND YEAR(created_at) = YEAR(CURDATE()) 
                          AND MONTH(created_at) = MONTH(CURDATE())";
$result = mysqli_query($conn, $month_generated_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['generated_this_month'] = mysqli_fetch_assoc($result)['month_count'];
}

// NEW: Get recent plan generation activity
$recent_activity_query = "SELECT activity_details, created_at FROM user_activity 
                          WHERE user_id = $user_id 
                          AND activity_type = 'plan_generated' 
                          ORDER BY created_at DESC 
                          LIMIT 5";
$recent_activity_result = mysqli_query($conn, $recent_activity_query);
$recent_activities = [];
if ($recent_activity_result) {
    while ($activity = mysqli_fetch_assoc($recent_activity_result)) {
        $recent_activities[] = $activity;
    }
}

// Today's meals from database
$today_query = "SELECT m.*, r.recipe_name, r.calories, r.estimated_cost, r.meal_type 
                FROM meals m
                JOIN recipes r ON m.recipe_id = r.recipe_id
                JOIN meal_plans mp ON m.mealplan_id = mp.mealplan_id
                WHERE mp.user_id = $user_id 
                AND m.scheduled_date = CURDATE()
                ORDER BY FIELD(r.meal_type, 'Breakfast', 'Lunch', 'Dinner', 'Snack')";
$today_result = mysqli_query($conn, $today_query);

// Group today's meals by type
$todays_meals = [
    'Breakfast' => [],
    'Lunch' => [],
    'Dinner' => [],
    'Snack' => []
];

if ($today_result) {
    while ($meal = mysqli_fetch_assoc($today_result)) {
        $todays_meals[$meal['meal_type']][] = $meal;
    }
}

// 2. Get recommended recipes (Kenyan dishes) from database
$recommended_query = "SELECT * FROM recipes 
                      WHERE meal_type IN ('Breakfast', 'Lunch', 'Dinner') 
                      ORDER BY RAND() 
                      LIMIT 3";
$recommended_result = mysqli_query($conn, $recommended_query);

// 3. Get meal categories with Kenyan recipes from database
$categories = [];
$meal_types = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];

foreach ($meal_types as $type) {
    $cat_query = "SELECT * FROM recipes 
                  WHERE meal_type = '$type' 
                  ORDER BY RAND() 
                  LIMIT 2";
    $cat_result = mysqli_query($conn, $cat_query);
    $categories[$type] = [];
    if ($cat_result) {
        while ($recipe = mysqli_fetch_assoc($cat_result)) {
            $categories[$type][] = $recipe;
        }
    }
}

// 4. Calculate nutrition for today from database - FIXED QUERY
$nutrition = [
    'total_calories' => 0,
    'total_protein' => 0,
    'total_carbs' => 0,
    'total_fats' => 0,
    'total_cost' => 0
];

// First check if nutrition columns exist
$check_cols_query = "SHOW COLUMNS FROM recipes WHERE Field IN ('protein', 'carbs', 'fats')";
$check_result = mysqli_query($conn, $check_cols_query);
$has_nutrition = (mysqli_num_rows($check_result) > 0);

if ($has_nutrition) {
    // Use actual nutrition columns if they exist
    $nutrition_query = "SELECT 
        COALESCE(SUM(r.calories), 0) as total_calories,
        COALESCE(SUM(r.protein), 0) as total_protein,
        COALESCE(SUM(r.carbs), 0) as total_carbs,
        COALESCE(SUM(r.fats), 0) as total_fats,
        COALESCE(SUM(r.estimated_cost), 0) as total_cost
        FROM meals m
        JOIN recipes r ON m.recipe_id = r.recipe_id
        JOIN meal_plans mp ON m.mealplan_id = mp.mealplan_id
        WHERE mp.user_id = $user_id 
        AND m.scheduled_date = CURDATE()";
} else {
    // Fallback if nutrition columns don't exist
    $nutrition_query = "SELECT 
        COALESCE(SUM(300), 0) as total_calories,
        COALESCE(SUM(15), 0) as total_protein,
        COALESCE(SUM(50), 0) as total_carbs,
        COALESCE(SUM(10), 0) as total_fats,
        COALESCE(SUM(r.estimated_cost), 0) as total_cost
        FROM meals m
        JOIN recipes r ON m.recipe_id = r.recipe_id
        JOIN meal_plans mp ON m.mealplan_id = mp.mealplan_id
        WHERE mp.user_id = $user_id 
        AND m.scheduled_date = CURDATE()";
}

$nutrition_result = mysqli_query($conn, $nutrition_query);
if ($nutrition_result && mysqli_num_rows($nutrition_result) > 0) {
    $nutrition = mysqli_fetch_assoc($nutrition_result);
}

// 5. Get user's weekly budget from database
$weekly_budget_query = "SELECT amount FROM budgets 
                        WHERE user_id = $user_id 
                        AND start_date <= CURDATE() 
                        AND end_date >= CURDATE() 
                        LIMIT 1";
$weekly_budget_result = mysqli_query($conn, $weekly_budget_query);
$weekly_budget = 5000; // Default if not set
if ($weekly_budget_result && mysqli_num_rows($weekly_budget_result) > 0) {
    $budget_row = mysqli_fetch_assoc($weekly_budget_result);
    $weekly_budget = $budget_row['amount'];
}

// 6. Get pantry items count - Check if pantry table exists first
$pantry_count = 0;
$check_pantry = "SHOW TABLES LIKE 'pantry'";
$pantry_exists = mysqli_query($conn, $check_pantry);

if (mysqli_num_rows($pantry_exists) > 0) {
    $pantry_query = "SELECT COUNT(*) as count FROM pantry WHERE user_id = $user_id";
    $pantry_result = mysqli_query($conn, $pantry_query);
    if ($pantry_result) {
        $pantry_data = mysqli_fetch_assoc($pantry_result);
        $pantry_count = $pantry_data['count'];
    }
}
$stats['pantry_items'] = $pantry_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Meal Plan System</title>
    <style>
        :root {
            --primary-green: #27ae60;
            --secondary-green: #2ecc71;
            --accent-orange: #e67e22;
            --accent-red: #e74c3c;
            --accent-blue: #3498db;
            --dark-green: #2e8b57;
            --light-green: #d5f4e6;
            --light-bg: #f9fdf7;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --border-color: #e1f5e1;
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
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid var(--border-color);
            padding: 25px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(46, 204, 113, 0.1);
        }
        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-right: 15px;
        }
        .logo-text h2 {
            color: var(--dark-green);
            font-size: 22px;
        }
        .logo-text p {
            color: var(--text-light);
            font-size: 12px;
        }
        .user-welcome {
            background: linear-gradient(135deg, var(--light-green), #e8f8f1);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        .user-welcome h3 {
            color: var(--dark-green);
            margin-bottom: 5px;
        }
        .user-welcome p {
            color: var(--text-light);
            font-size: 14px;
            font-style: italic;
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
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .nav-menu a:hover {
            background-color: var(--light-green);
            color: var(--primary-green);
            transform: translateX(5px);
        }
        .nav-menu a.active {
            background-color: var(--primary-green);
            color: white;
        }
        .nav-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 18px;
        }
        .todays-plan {
            background: white;
            border: 2px solid var(--primary-green);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        .todays-plan h4 {
            color: var(--dark-green);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .todays-plan h4 i {
            margin-right: 10px;
        }
        .meal-time {
            margin-bottom: 15px;
        }
        .meal-time:last-child {
            margin-bottom: 0;
        }
        .meal-time-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .meal-time-title {
            color: var(--dark-green);
            font-weight: 600;
        }
        .meal-time-time {
            color: var(--accent-orange);
            font-size: 14px;
            font-weight: 500;
        }
        .meal-item {
            background: var(--light-bg);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 5px;
            border-left: 3px solid var(--primary-green);
        }
        .meal-item:last-child {
            margin-bottom: 0;
        }
        .meal-item h5 {
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        .meal-item p {
            color: var(--text-light);
            font-size: 13px;
        }
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }
        /* Top Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .welcome-message h1 {
            color: var(--dark-green);
            font-size: 28px;
            margin-bottom: 5px;
        }
        .welcome-message p {
            color: var(--accent-orange);
            font-size: 16px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .search-bar {
            position: relative;
            width: 300px;
        }
        .search-bar input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            background-color: white;
            font-size: 14px;
            transition: all 0.3s;
        }
        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
        }
        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        .notification-btn, .profile-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            cursor: pointer;
            transition: all 0.3s;
        }
        .notification-btn:hover, .profile-btn:hover {
            background: var(--light-green);
            color: var(--primary-green);
        }
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
            color: white;
        }
        .stat-icon.plans {
            background: linear-gradient(135deg, var(--primary-green), #2980b9);
        }
        .stat-icon.pantry {
            background: linear-gradient(135deg, var(--secondary-green), #27ae60);
        }
        .stat-icon.budget {
            background: linear-gradient(135deg, var(--accent-orange), #e67e22);
        }
        .stat-icon.recipes {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        .stat-icon.generated {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        .stat-icon.week {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        .stat-icon.month {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        .stat-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .stat-info p {
            color: var(--text-light);
            font-size: 14px;
        }
        /* Main Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        /* Meal Categories Section */
        .meal-categories {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .section-header h2 {
            color: var(--dark-green);
            font-size: 22px;
        }
        .view-all {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }
        .view-all:hover {
            text-decoration: underline;
        }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .category-card {
            background: linear-gradient(135deg, #f9fdf7, #ffffff);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(46, 204, 113, 0.15);
            border-color: var(--primary-green);
        }
        .category-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 18px;
        }
        .breakfast .category-icon { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .lunch .category-icon { background: linear-gradient(135deg, #3498db, #2980b9); }
        .dinner .category-icon { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .snack .category-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        .category-header h3 {
            color: var(--text-dark);
            font-size: 18px;
        }
        .meal-items {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .meal-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid var(--border-color);
        }
        .meal-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .meal-info h4 {
            color: var(--text-dark);
            font-size: 15px;
            font-weight: 600;
        }
        .meal-price {
            color: var(--primary-green);
            font-weight: bold;
            font-size: 16px;
        }
        .meal-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .meal-calories {
            color: var(--accent-orange);
            font-size: 13px;
            font-weight: 500;
        }
        .add-btn {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .add-btn:hover {
            background: var(--dark-green);
            transform: scale(1.05);
        }
        /* Recommended Section */
        .recommended-section {
            margin-top: 30px;
        }
        .recommended-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .recommended-card {
            background: linear-gradient(135deg, #f9fdf7, #ffffff);
            border-radius: 15px;
            padding: 20px;
            border: 2px solid var(--primary-green);
            text-align: center;
        }
        .food-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        .recommended-card h4 {
            color: var(--dark-green);
            margin-bottom: 10px;
        }
        .price-comparison {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .current-price {
            color: var(--primary-green);
            font-weight: bold;
            font-size: 18px;
        }
        .original-price {
            color: var(--text-light);
            text-decoration: line-through;
            font-size: 14px;
        }
        /* Right Sidebar - Your Meal Plan */
        .meal-plan-sidebar {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }
        .plan-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .plan-header h2 {
            color: var(--dark-green);
            margin-bottom: 10px;
        }
        .plan-date {
            color: var(--accent-orange);
            font-weight: 500;
        }
        .nutrition-summary {
            background: linear-gradient(135deg, var(--light-green), #e8f8f1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }
        .nutrition-summary h4 {
            color: var(--dark-green);
            margin-bottom: 15px;
        }
        .nutrition-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            color: var(--primary-green);
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            color: var(--text-light);
            font-size: 12px;
        }
        .plan-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 25px;
        }
        .plan-btn {
            padding: 12px;
            border-radius: 10px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .plan-btn.primary {
            background: var(--primary-green);
            color: white;
        }
        .plan-btn.secondary {
            background: white;
            color: var(--primary-green);
            border: 2px solid var(--primary-green);
        }
        .plan-btn:hover {
            transform: translateY(-2px);
        }
        /* Budget Summary */
        .budget-summary {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        .budget-summary h4 {
            margin-bottom: 15px;
            color: var(--dark-green);
        }
        /* Recent Activity Section */
        .recent-activity {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 25px;
            border: 1px solid var(--border-color);
        }
        .recent-activity h4 {
            color: var(--dark-green);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .activity-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .activity-icon {
            color: var(--primary-green);
        }
        .activity-time {
            color: var(--text-light);
            font-size: 13px;
        }
        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
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
            .search-bar {
                width: 100%;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="logo-text">
                    <h2>NutriPlan KE</h2>
                    <p>Smart • Healthy • Affordable</p>
                </div>
            </div>
            
            <div class="user-welcome">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <h3>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h3>
                <p>Don't forget to have breakfast today! 🍳</p>
            </div>
            
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="meal_plan.php"><i class="fas fa-calendar-alt"></i> My Meal Plans</a></li>
                <li><a href="pantry.php"><i class="fas fa-utensils"></i> My Pantry</a></li>
                <li><a href="recipes.php"><i class="fas fa-book"></i> Kenyan Recipes</a></li>
                <li><a href="shopping-list.php"><i class="fas fa-shopping-cart"></i> Shopping List</a></li>
                <li><a href="budget.php"><i class="fas fa-wallet"></i> Budget Tracker</a></li>
                <li><a href="preferences.php"><i class="fas fa-sliders-h"></i> My Preferences</a></li>
                <li><a href="create-plan.php"><i class="fas fa-magic"></i> Generate Plan</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
            
            <div class="todays-plan">
                <h4><i class="fas fa-calendar-day"></i> Today's Plan</h4>
                
                <?php 
                $meal_times = [
                    'Breakfast' => '06:30 AM',
                    'Lunch' => '01:00 PM', 
                    'Dinner' => '07:30 PM'
                ];
                
                $has_today_meals = false;
                foreach ($todays_meals as $type => $meals) {
                    if (!empty($meals)) {
                        $has_today_meals = true;
                        break;
                    }
                }
                
                if ($has_today_meals): 
                    foreach ($meal_times as $type => $time):
                        if (!empty($todays_meals[$type])): ?>
                            <div class="meal-time">
                                <div class="meal-time-header">
                                    <span class="meal-time-title"><?php echo $type; ?></span>
                                    <span class="meal-time-time"><?php echo $time; ?></span>
                                </div>
                                <?php foreach ($todays_meals[$type] as $meal): ?>
                                    <div class="meal-item">
                                        <h5><?php echo htmlspecialchars($meal['recipe_name']); ?></h5>
                                        <p><?php echo $meal['calories']; ?> cal • KES <?php echo $meal['estimated_cost']; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; 
                else: ?>
                    <div style="text-align: center; padding: 20px; color: var(--text-light);">
                        <i class="fas fa-utensils" style="font-size: 40px; margin-bottom: 10px;"></i>
                        <p>No meals planned for today</p>
                        <a href="create-plan.php" style="background: var(--primary-green); color: white; border: none; padding: 8px 15px; border-radius: 5px; margin-top: 10px; cursor: pointer; text-decoration: none; display: inline-block;">
                            Create Plan
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="welcome-message">
                    <h1>Find the menu you want</h1>
                    <p>Healthy eating made simple with local Kenyan foods</p>
                </div>
                <div class="header-actions">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search for recipes, ingredients...">
                    </div>
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                    </button>
                    <button class="profile-btn">
                        <i class="fas fa-user"></i>
                    </button>
                </div>
            </div>
            
            <!-- Plan Generation Stats Section -->
            <div class="content-section" style="margin-bottom: 30px;">
                <h3 style="color: var(--dark-green); margin-bottom: 20px;">
                    <i class="fas fa-chart-line"></i> Your Plan Generation Activity
                </h3>
                
                <div class="stats-grid">
                    <!-- Plans Generated Card -->
                    <div class="stat-card">
                        <div class="stat-icon generated">
                            <i class="fas fa-magic"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['plans_generated']; ?></h3>
                            <p>Total Plans Generated</p>
                            <small style="color: var(--text-light); font-size: 12px;">
                                <?php echo $stats['plans_generated'] == 0 ? 'Start generating plans!' : 'Great work!'; ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- This Week Card -->
                    <div class="stat-card">
                        <div class="stat-icon week">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['generated_this_week']; ?></h3>
                            <p>This Week</p>
                            <small style="color: var(--text-light); font-size: 12px;">
                                <?php echo date('M d') . ' - ' . date('M d', strtotime('+6 days')); ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- This Month Card -->
                    <div class="stat-card">
                        <div class="stat-icon month">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['generated_this_month']; ?></h3>
                            <p>This Month</p>
                            <small style="color: var(--text-light); font-size: 12px;">
                                <?php echo date('F Y'); ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- Next Plan Generation Card -->
                    <div class="stat-card">
                        <div class="stat-icon pantry">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <div class="stat-info">
                            <h3>
                                <?php 
                                $next_goal = 5;
                                if ($stats['plans_generated'] >= 20) {
                                    $next_goal = 25;
                                } elseif ($stats['plans_generated'] >= 10) {
                                    $next_goal = 15;
                                } elseif ($stats['plans_generated'] >= 5) {
                                    $next_goal = 10;
                                }
                                echo $next_goal;
                                ?>
                            </h3>
                            <p>Next Goal</p>
                            <small style="color: var(--text-light); font-size: 12px;">
                                <?php 
                                $remaining = $next_goal - $stats['plans_generated'];
                                echo $remaining > 0 ? $remaining . ' more to go!' : 'Goal achieved!';
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bar for Goals -->
                <?php if ($stats['plans_generated'] > 0): ?>
                <div style="background: var(--light-bg); padding: 15px; border-radius: 10px; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: var(--text-dark); font-weight: 500;">Plan Generation Progress</span>
                        <span style="color: var(--primary-green); font-weight: 600;">
                            <?php echo $stats['plans_generated']; ?> generated
                        </span>
                    </div>
                    <div style="height: 10px; background: var(--border-color); border-radius: 5px; overflow: hidden;">
                        <?php 
                        $progress = min(100, ($stats['plans_generated'] / 20) * 100);
                        ?>
                        <div style="height: 100%; width: <?php echo $progress; ?>%; 
                                    background: linear-gradient(90deg, var(--primary-green), var(--secondary-green));">
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 12px; color: var(--text-light);">
                        <span>Beginner</span>
                        <span>Intermediate (10)</span>
                        <span>Advanced (20)</span>
                        <span>Expert (30+)</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Main Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon plans">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_plans']; ?></h3>
                        <p>Total Meal Plans</p>
                        <small style="color: var(--text-light); font-size: 12px;">
                            <?php echo $stats['active_plans']; ?> active
                        </small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon pantry">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pantry_items']; ?></h3>
                        <p>Pantry Items</p>
                        <small style="color: var(--text-light); font-size: 12px;">
                            Ready to use
                        </small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon budget">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-info">
                        <h3>KES <?php echo number_format($stats['budget_used'], 0); ?></h3>
                        <p>Budget Used</p>
                        <small style="color: var(--text-light); font-size: 12px;">
                            This week
                        </small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon recipes">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php 
                        // Count total recipes
                        $recipe_count = 0;
                        foreach ($categories as $cat) {
                            $recipe_count += count($cat);
                        }
                        echo $recipe_count * 5; // Multiply for better display
                        ?></h3>
                        <p>Available Recipes</p>
                        <small style="color: var(--text-light); font-size: 12px;">
                            Kenyan dishes
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Section -->
            <?php if (!empty($recent_activities)): ?>
            <div class="recent-activity">
                <h4><i class="fas fa-history"></i> Recent Plan Generations</h4>
                <div class="activity-list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <i class="fas fa-check-circle activity-icon"></i>
                                <span><?php echo htmlspecialchars($activity['activity_details']); ?></span>
                            </div>
                            <span class="activity-time">
                                <?php 
                                // Format the date nicely
                                $date = new DateTime($activity['created_at']);
                                $now = new DateTime();
                                $interval = $date->diff($now);
                                
                                if ($interval->days == 0) {
                                    if ($interval->h < 1) {
                                        echo $interval->i . ' min ago';
                                    } else {
                                        echo 'Today, ' . $date->format('h:i A');
                                    }
                                } elseif ($interval->days == 1) {
                                    echo 'Yesterday, ' . $date->format('h:i A');
                                } elseif ($interval->days < 7) {
                                    echo $interval->days . ' days ago';
                                } else {
                                    echo $date->format('M d, h:i A');
                                }
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Left Column: Meal Categories -->
                <div class="meal-categories">
                    <div class="section-header">
                        <h2>Today's Meal Categories</h2>
                        <a href="recipes.php" class="view-all">View All →</a>
                    </div>
                    
                    <div class="category-grid">
                        <?php 
                        // Display only categories that have recipes
                        foreach ($categories as $category => $recipes): 
                            if (empty($recipes)) continue;
                            $icon_class = strtolower($category);
                        ?>
                            <div class="category-card <?php echo $icon_class; ?>">
                                <div class="category-header">
                                    <div class="category-icon">
                                        <i class="fas fa-<?php 
                                            echo $category == 'Breakfast' ? 'sun' : 
                                                 ($category == 'Lunch' ? 'sun' : 
                                                 ($category == 'Dinner' ? 'moon' : 'apple-alt')); 
                                        ?>"></i>
                                    </div>
                                    <h3><?php echo $category; ?> 
                                        <?php if ($category != 'Snack'): ?>
                                            <span class="meal-time-time" style="font-size: 12px; margin-left: 10px;">
                                                <?php echo $category == 'Breakfast' ? '06:30 AM' : 
                                                       ($category == 'Lunch' ? '01:00 PM' : '07:30 PM'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                </div>
                                <div class="meal-items">
                                    <?php foreach ($recipes as $recipe): ?>
                                        <div class="meal-card">
                                            <div class="meal-info">
                                                <h4><?php echo htmlspecialchars($recipe['recipe_name']); ?></h4>
                                                <span class="meal-price">KES <?php echo $recipe['estimated_cost']; ?></span>
                                            </div>
                                            <div class="meal-meta">
                                                <span class="meal-calories">
                                                    <?php 
                                                    echo isset($recipe['calories']) ? $recipe['calories'] . ' cal' : 'N/A';
                                                    ?>
                                                </span>
                                                <button class="add-btn" onclick="window.location.href='create-plan.php'">
                                                    Add to Plan
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php 
                        // If no recipes found, show message
                        $total_recipes = 0;
                        foreach ($categories as $cat) {
                            $total_recipes += count($cat);
                        }
                        if ($total_recipes == 0): ?>
                            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-light);">
                                <i class="fas fa-utensils" style="font-size: 50px; margin-bottom: 20px;"></i>
                                <h3>No recipes available</h3>
                                <p>Add some recipes to get started with meal planning</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recommended Section -->
                    <?php if ($recommended_result && mysqli_num_rows($recommended_result) > 0): ?>
                    <div class="recommended-section">
                        <div class="section-header">
                            <h2>Recommended For You</h2>
                            <a href="recipes.php" class="view-all">View All →</a>
                        </div>
                        <div class="recommended-grid">
                            <?php 
                            // Reset pointer and display recipes
                            mysqli_data_seek($recommended_result, 0);
                            while ($recipe = mysqli_fetch_assoc($recommended_result)): ?>
                                <div class="recommended-card">
                                    <div class="food-icon">
                                        <i class="fas fa-<?php 
                                            echo strpos(strtolower($recipe['recipe_name']), 'choma') !== false ? 'drumstick-bite' :
                                                 (strpos(strtolower($recipe['recipe_name']), 'fish') !== false ? 'fish' : 
                                                 (strpos(strtolower($recipe['recipe_name']), 'chapati') !== false ? 'bread-slice' : 'leaf'));
                                        ?>"></i>
                                    </div>
                                    <h4><?php echo htmlspecialchars($recipe['recipe_name']); ?></h4>
                                    <div class="price-comparison">
                                        <span class="current-price">KES <?php echo $recipe['estimated_cost']; ?></span>
                                        <span class="original-price">KES <?php echo round($recipe['estimated_cost'] * 1.1); ?></span>
                                    </div>
                                    <button class="add-btn" onclick="window.location.href='create-plan.php'">
                                        Add to Plan
                                    </button>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column: Your Meal Plan -->
                <div class="meal-plan-sidebar">
                    <div class="plan-header">
                        <h2>Your Meal Plan</h2>
                        <p class="plan-date">Today, <?php echo date('d M Y'); ?></p>
                    </div>
                    
                    <div class="nutrition-summary">
                        <h4>Nutrition Summary</h4>
                        <div class="nutrition-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo round($nutrition['total_calories']); ?></div>
                                <div class="stat-label">Calories</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo round($nutrition['total_protein']); ?>g</div>
                                <div class="stat-label">Protein</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo round($nutrition['total_carbs']); ?>g</div>
                                <div class="stat-label">Carbs</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo round($nutrition['total_fats']); ?>g</div>
                                <div class="stat-label">Fats</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="plan-actions">
                        <a href="create-plan.php" class="plan-btn primary">
                            <i class="fas fa-plus-circle"></i> Create New Plan
                        </a>
                        <a href="shopping-list.php" class="plan-btn secondary">
                            <i class="fas fa-shopping-cart"></i> Generate Shopping List
                        </a>
                    </div>
                    
                    <div class="budget-summary">
                        <h4>Weekly Budget</h4>
                        <div style="margin: 15px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Budget:</span>
                                <span style="font-weight: bold;">KES <?php echo number_format($weekly_budget, 0); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>Used:</span>
                                <span style="color: var(--accent-orange); font-weight: bold;">KES <?php echo number_format($stats['budget_used'], 0); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Remaining:</span>
                                <span style="color: var(--primary-green); font-weight: bold;">
                                    KES <?php echo number_format($weekly_budget - $stats['budget_used'], 0); ?>
                                </span>
                            </div>
                        </div>
                        <div style="background: #f0f0f0; height: 10px; border-radius: 5px; margin-top: 10px;">
                            <div style="background: var(--primary-green); height: 100%; width: <?php echo min(100, ($stats['budget_used'] / $weekly_budget) * 100); ?>%; border-radius: 5px;"></div>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                        <h4>Quick Stats</h4>
                        <div style="margin-top: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>Plans Generated:</span>
                                <span style="font-weight: bold; color: var(--primary-green);">
                                    <?php echo $stats['plans_generated']; ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>This Week:</span>
                                <span style="color: var(--accent-orange); font-weight: bold;">
                                    <?php echo $stats['generated_this_week']; ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Generation Rate:</span>
                                <span style="color: var(--accent-blue); font-weight: bold;">
                                    <?php 
                                    if ($stats['plans_generated'] > 0) {
                                        $days_used = 30; // Assume 30 days of usage
                                        $rate = $stats['plans_generated'] / $days_used;
                                        echo round($rate, 1) . '/day';
                                    } else {
                                        echo '0/day';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Simple JavaScript for interactivity
        document.querySelectorAll('.add-btn').forEach(button => {
            button.addEventListener('click', function() {
                window.location.href = 'create-plan.php';
            });
        });
        
        // Search functionality placeholder
        document.querySelector('.search-bar input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                alert('Search feature coming soon!');
            }
        });
        
        // Notification button
        document.querySelector('.notification-btn').addEventListener('click', function() {
            alert('No new notifications');
        });
        
        // Profile button
        document.querySelector('.profile-btn').addEventListener('click', function() {
            window.location.href = 'profile.php';
        });
        
        // Auto-refresh page every 60 seconds to update stats
        setTimeout(function() {
            //location.reload();
        }, 60000);
    </script>
</body>
</html>