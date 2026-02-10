<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

// Get all Kenyan recipes from database
$recipes_query = "SELECT * FROM recipes ORDER BY meal_type, recipe_name";
$recipes_result = mysqli_query($conn, $recipes_query);

// Get recipe categories
$categories_query = "SELECT DISTINCT meal_type FROM recipes";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($cat = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $cat['meal_type'];
}

// Handle search
$search = '';
if (isset($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $recipes_query = "SELECT * FROM recipes WHERE recipe_name LIKE '%$search%' OR description LIKE '%$search%' ORDER BY meal_type, recipe_name";
    $recipes_result = mysqli_query($conn, $recipes_query);
}

// Handle category filter
$category_filter = '';
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category = mysqli_real_escape_string($conn, $_GET['category']);
    $recipes_query = "SELECT * FROM recipes WHERE meal_type = '$category' ORDER BY recipe_name";
    $recipes_result = mysqli_query($conn, $recipes_query);
    $category_filter = $category;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kenyan Recipes - Meal Plan System</title>
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
            gap: 15px;
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
            background: var(--primary-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--dark-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-outline {
            background: white;
            color: var(--primary-green);
            border: 2px solid var(--primary-green);
        }
        
        .btn-outline:hover {
            background: var(--light-green);
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
            color: var(--dark-green);
            font-size: 22px;
        }
        
        /* Search and Filter */
        .search-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid var(--border-color);
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        .category-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .category-btn {
            padding: 8px 16px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            color: var(--text-dark);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .category-btn:hover {
            background: var(--light-green);
            border-color: var(--primary-green);
        }
        
        .category-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        /* Recipes Grid */
        .recipes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .recipe-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .recipe-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--primary-green);
        }
        
        .recipe-image {
            height: 180px;
            background: linear-gradient(135deg, var(--light-green), #e8f8f1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-green);
            font-size: 60px;
        }
        
        .recipe-content {
            padding: 20px;
        }
        
        .recipe-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .recipe-header h3 {
            color: var(--dark-green);
            font-size: 18px;
            flex: 1;
        }
        
        .recipe-category {
            background: var(--light-green);
            color: var(--primary-green);
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .recipe-description {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .recipe-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .recipe-nutrition {
            display: flex;
            gap: 15px;
        }
        
        .nutrition-item {
            text-align: center;
        }
        
        .nutrition-value {
            color: var(--primary-green);
            font-weight: bold;
            font-size: 16px;
            display: block;
        }
        
        .nutrition-label {
            color: var(--text-light);
            font-size: 11px;
            display: block;
        }
        
        .recipe-cost {
            color: var(--dark-green);
            font-weight: bold;
            font-size: 18px;
        }
        
        .recipe-actions {
            display: flex;
            gap: 10px;
        }
        
        .recipe-actions .btn {
            flex: 1;
            justify-content: center;
        }
        
        /* Category Sections */
        .category-section {
            margin-bottom: 40px;
        }
        
        .category-section h3 {
            color: var(--dark-green);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-section h3 i {
            color: var(--accent-orange);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-light);
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 60px;
            color: var(--border-color);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            margin-bottom: 30px;
        }
        
        /* Recipe Icons */
        .recipe-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin: 0 auto 15px;
        }
        
        .breakfast-icon { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .lunch-icon { background: linear-gradient(135deg, #3498db, #2980b9); }
        .dinner-icon { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .snack-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
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
            
            .recipes-grid {
                grid-template-columns: 1fr;
            }
            
            .search-box {
                min-width: 100%;
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
                <p>Discover Kenyan recipes</p>
            </div>
            
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="meal_plan.php"><i class="fas fa-calendar-alt"></i> My Meal Plans</a></li>
                <li><a href="pantry.php"><i class="fas fa-utensils"></i> My Pantry</a></li>
                <li><a href="recipes.php" class="active"><i class="fas fa-book"></i> Kenyan Recipes</a></li>
                <li><a href="shopping-list.php"><i class="fas fa-shopping-cart"></i> Shopping List</a></li>
                <li><a href="budget.php"><i class="fas fa-wallet"></i> Budget Tracker</a></li>
                <li><a href="preferences.php"><i class="fas fa-sliders-h"></i> My Preferences</a></li>
                <li><a href="create-plan.php"><i class="fas fa-magic"></i> Generate Plan</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <div class="welcome-message">
                    <h1>Kenyan Recipes</h1>
                    <p>Traditional and modern Kenyan dishes for every meal</p>
                </div>
                <div class="header-actions">
                    <a href="create-plan.php" class="btn btn-primary">
                        <i class="fas fa-magic"></i> Generate Plan
                    </a>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="content-section">
                <form method="GET" action="" class="search-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search recipes..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        <a href="recipes.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </form>
                
                <!-- Category Filter -->
                <div class="category-filter">
                    <a href="recipes.php" class="category-btn <?php echo empty($category_filter) ? 'active' : ''; ?>">
                        All Recipes
                    </a>
                    <?php foreach ($categories as $category): ?>
                        <a href="?category=<?php echo urlencode($category); ?>" 
                           class="category-btn <?php echo $category_filter == $category ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($category); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recipes Grid -->
            <div class="content-section">
                <?php if (mysqli_num_rows($recipes_result) > 0): ?>
                    <?php if (empty($category_filter) && empty($search)): ?>
                        <!-- Show recipes by category -->
                        <?php 
                        // Reset pointer and organize by category
                        mysqli_data_seek($recipes_result, 0);
                        $recipes_by_category = [];
                        while ($recipe = mysqli_fetch_assoc($recipes_result)) {
                            $recipes_by_category[$recipe['meal_type']][] = $recipe;
                        }
                        
                        foreach ($recipes_by_category as $category => $category_recipes): 
                            $icon_class = strtolower($category) . '-icon';
                            $category_icon = '';
                            switch($category) {
                                case 'Breakfast': $category_icon = 'fas fa-sun'; break;
                                case 'Lunch': $category_icon = 'fas fa-sun'; break;
                                case 'Dinner': $category_icon = 'fas fa-moon'; break;
                                case 'Snack': $category_icon = 'fas fa-apple-alt'; break;
                                default: $category_icon = 'fas fa-utensils';
                            }
                        ?>
                            <div class="category-section">
                                <h3><i class="<?php echo $category_icon; ?>"></i> <?php echo htmlspecialchars($category); ?> Recipes</h3>
                                <div class="recipes-grid">
                                    <?php foreach ($category_recipes as $recipe): ?>
                                        <div class="recipe-card">
                                            <div class="recipe-image">
                                                <div class="recipe-icon <?php echo $icon_class; ?>">
                                                    <i class="<?php echo $category_icon; ?>"></i>
                                                </div>
                                            </div>
                                            <div class="recipe-content">
                                                <div class="recipe-header">
                                                    <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
                                                    <span class="recipe-category"><?php echo htmlspecialchars($category); ?></span>
                                                </div>
                                                
                                                <p class="recipe-description">
                                                    <?php echo htmlspecialchars($recipe['description'] ?? 'Traditional Kenyan dish'); ?>
                                                </p>
                                                
                                                <div class="recipe-details">
                                                    <div class="recipe-nutrition">
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-value"><?php echo $recipe['calories'] ?? '300'; ?></span>
                                                            <span class="nutrition-label">Calories</span>
                                                        </div>
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-value"><?php echo $recipe['estimated_cost']; ?></span>
                                                            <span class="nutrition-label">KES</span>
                                                        </div>
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-value"><?php echo $recipe['prep_time'] ?? '30'; ?></span>
                                                            <span class="nutrition-label">Mins</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="recipe-actions">
                                                    <button class="btn btn-sm btn-outline" onclick="viewRecipe(<?php echo $recipe['recipe_id']; ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="addToPlan(<?php echo $recipe['recipe_id']; ?>)">
                                                        <i class="fas fa-plus"></i> Add to Plan
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Show all filtered recipes in one grid -->
                        <div class="recipes-grid">
                            <?php while ($recipe = mysqli_fetch_assoc($recipes_result)): 
                                $icon_class = strtolower($recipe['meal_type']) . '-icon';
                                $category_icon = '';
                                switch($recipe['meal_type']) {
                                    case 'Breakfast': $category_icon = 'fas fa-sun'; break;
                                    case 'Lunch': $category_icon = 'fas fa-sun'; break;
                                    case 'Dinner': $category_icon = 'fas fa-moon'; break;
                                    case 'Snack': $category_icon = 'fas fa-apple-alt'; break;
                                    default: $category_icon = 'fas fa-utensils';
                                }
                            ?>
                                <div class="recipe-card">
                                    <div class="recipe-image">
                                        <div class="recipe-icon <?php echo $icon_class; ?>">
                                            <i class="<?php echo $category_icon; ?>"></i>
                                        </div>
                                    </div>
                                    <div class="recipe-content">
                                        <div class="recipe-header">
                                            <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
                                            <span class="recipe-category"><?php echo htmlspecialchars($recipe['meal_type']); ?></span>
                                        </div>
                                        
                                        <p class="recipe-description">
                                            <?php echo htmlspecialchars($recipe['description'] ?? 'Traditional Kenyan dish'); ?>
                                        </p>
                                        
                                        <div class="recipe-details">
                                            <div class="recipe-nutrition">
                                                <div class="nutrition-item">
                                                    <span class="nutrition-value"><?php echo $recipe['calories'] ?? '300'; ?></span>
                                                    <span class="nutrition-label">Calories</span>
                                                </div>
                                                <div class="nutrition-item">
                                                    <span class="nutrition-value"><?php echo $recipe['estimated_cost']; ?></span>
                                                    <span class="nutrition-label">KES</span>
                                                </div>
                                                <div class="nutrition-item">
                                                    <span class="nutrition-value"><?php echo $recipe['prep_time'] ?? '30'; ?></span>
                                                    <span class="nutrition-label">Mins</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="recipe-actions">
                                            <button class="btn btn-sm btn-outline" onclick="viewRecipe(<?php echo $recipe['recipe_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="addToPlan(<?php echo $recipe['recipe_id']; ?>)">
                                                <i class="fas fa-plus"></i> Add to Plan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <h3>No recipes found</h3>
                        <p><?php echo !empty($search) ? "No recipes match your search for '" . htmlspecialchars($search) . "'" : "No recipes available"; ?></p>
                        <a href="recipes.php" class="btn btn-primary">
                            <i class="fas fa-redo"></i> View All Recipes
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Popular Kenyan Recipes Info -->
            <div class="content-section">
                <h3 style="color: var(--dark-green); margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> About Kenyan Cuisine
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div style="background: var(--light-bg); padding: 20px; border-radius: 10px;">
                        <i class="fas fa-drumstick-bite" style="color: var(--primary-green); font-size: 24px; margin-bottom: 10px;"></i>
                        <h4>Staple Foods</h4>
                        <p style="color: var(--text-light); font-size: 14px;">
                            Ugali, Sukuma Wiki, Githeri, Chapati, and Nyama Choma are Kenyan staples
                        </p>
                    </div>
                    <div style="background: var(--light-bg); padding: 20px; border-radius: 10px;">
                        <i class="fas fa-leaf" style="color: var(--secondary-green); font-size: 24px; margin-bottom: 10px;"></i>
                        <h4>Healthy Options</h4>
                        <p style="color: var(--text-light); font-size: 14px;">
                            Many Kenyan dishes are vegetable-based and packed with nutrients
                        </p>
                    </div>
                    <div style="background: var(--light-bg); padding: 20px; border-radius: 10px;">
                        <i class="fas fa-seedling" style="color: var(--accent-orange); font-size: 24px; margin-bottom: 10px;"></i>
                        <h4>Local Ingredients</h4>
                        <p style="color: var(--text-light); font-size: 14px;">
                            Recipes use locally available, fresh ingredients from Kenyan markets
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function viewRecipe(recipeId) {
            alert('Viewing recipe ' + recipeId + ' (feature coming soon)');
            // window.location.href = 'view-recipe.php?id=' + recipeId;
        }
        
        function addToPlan(recipeId) {
            alert('Adding recipe ' + recipeId + ' to meal plan (feature coming soon)');
            // window.location.href = 'create-plan.php?add_recipe=' + recipeId;
        }
        
        // Quick search
        document.querySelector('.search-box input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
        
        // Recipe card click
        document.addEventListener('DOMContentLoaded', function() {
            const recipeCards = document.querySelectorAll('.recipe-card');
            recipeCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('.recipe-actions')) {
                        const recipeId = this.querySelector('.recipe-actions button').getAttribute('onclick').match(/\d+/)[0];
                        viewRecipe(recipeId);
                    }
                });
            });
        });
    </script>
</body>
</html>