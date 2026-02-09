<?php
// FIXED: Added session_start() at the beginning
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = mysqli_real_escape_string($conn, $_POST['user_type']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($user_type)) {
        $error = "All required fields must be filled!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        // Check if email exists
        $check_sql = "SELECT user_id FROM users WHERE email = '$email'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (!$check_result) {
            $error = "Database error: " . mysqli_error($conn);
        } elseif (mysqli_num_rows($check_result) > 0) {
            $error = "Email already registered!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $sql = "INSERT INTO users (full_name, email, password, user_type, phone_number, date_registered) 
                    VALUES ('$full_name', '$email', '$hashed_password', '$user_type', '$phone', CURDATE())";
            
            if (mysqli_query($conn, $sql)) {
                $success = "Account created successfully! You can now login.";
                
                // Optional: Auto-login after registration
                // $user_id = mysqli_insert_id($conn);
                // $_SESSION['user_id'] = $user_id;
                // $_SESSION['full_name'] = $full_name;
                // header("Location: dashboard.php");
                // exit();
            } else {
                $error = "Registration failed: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Meal Plan System</title>
    <style>
        :root {
            --primary: #27ae60;
            --primary-dark: #219653;
            --secondary: #2c3e50;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border: #dee2e6;
            --facebook: #3b5998;
            --google: #db4437;
            --twitter: #1da1f2;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Roboto', sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            min-height: 600px;
        }
        
        /* Left Side - Sign Up Form */
        .signup-form-side {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .signup-form-side::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 80%;
            background: var(--border);
        }
        
        .auth-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 40px;
            text-align: center;
        }
        
        .auth-subtitle {
            font-size: 18px;
            color: var(--gray);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--secondary);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: var(--light);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
            padding-right: 50px;
        }
        
        .auth-btn {
            width: 100%;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .auth-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .switch-auth {
            text-align: center;
            color: var(--gray);
            font-size: 15px;
            margin-top: 30px;
        }
        
        .switch-auth a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .switch-auth a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* Right Side - Login Preview */
        .login-preview-side {
            flex: 1;
            padding: 50px;
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-preview {
            text-align: center;
        }
        
        .login-icon {
            font-size: 80px;
            margin-bottom: 30px;
        }
        
        .login-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .login-description {
            font-size: 18px;
            margin-bottom: 40px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .benefits-list {
            list-style: none;
            margin-bottom: 40px;
        }
        
        .benefits-list li {
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .benefits-list i {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .login-btn {
            display: inline-block;
            padding: 15px 40px;
            background: white;
            color: var(--primary);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .login-btn:hover {
            background: transparent;
            color: white;
            border-color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Messages */
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }
        
        .success-message {
            background: #efe;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .success-message a {
            color: #27ae60;
            font-weight: 600;
            text-decoration: none;
        }
        
        .success-message a:hover {
            text-decoration: underline;
        }
        
        /* Password strength indicator */
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: var(--gray);
        }
        
        .strength-bar {
            height: 4px;
            background: var(--gray-light);
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            background: #e74c3c;
            transition: all 0.3s;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
                max-width: 450px;
            }
            
            .signup-form-side::after {
                display: none;
            }
            
            .signup-form-side,
            .login-preview-side {
                padding: 30px;
            }
            
            .login-preview-side {
                order: -1;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <!-- Left Side: Sign Up Form -->
        <div class="signup-form-side">
            <h1 class="auth-title">Sign Up</h1>
            <p class="auth-subtitle">Create your Meal Planner account</p>
            
            <?php if ($error): ?>
                <div class="message error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success-message">
                    <?php echo htmlspecialchars($success); ?>
                    <br>
                    <a href="login.php">Go to Login</a>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="signupForm">
                <!-- FIXED: Use direct full_name input instead of combining -->
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="John Doe" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="johndoe@xyz.com" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="**************" required>
                        <div class="password-strength">
                            <span id="strengthText">Password strength: </span>
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="**************" required>
                        <div class="password-strength">
                            <span id="matchText">Passwords match: </span>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="user_type">Account Type *</label>
                        <select id="user_type" name="user_type" class="form-control" required>
                            <option value="">Select account type</option>
                            <option value="Individual">Individual</option>
                            <option value="Family">Family</option>
                            <option value="Nutritionist">Nutritionist</option>
                            <option value="Organization">Organization</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="0712 345 678">
                    </div>
                </div>
                
                <button type="submit" class="auth-btn">Sign Up</button>
            </form>
            
            <div class="switch-auth">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
        
        <!-- Right Side: Login Preview -->
        <div class="login-preview-side">
            <div class="login-preview">
                <div class="login-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <h2 class="login-title">Log In</h2>
                <p class="login-description">
                    Already have an account? Sign in to access your personalized meal plans, pantry, and shopping lists.
                </p>
                
                <ul class="benefits-list">
                    <li><i class="fas fa-chart-line"></i> Track your nutrition goals</li>
                    <li><i class="fas fa-wallet"></i> Manage your food budget</li>
                    <li><i class="fas fa-list-check"></i> Access your shopping lists</li>
                    <li><i class="fas fa-heart"></i> Save your favorite recipes</li>
                </ul>
                
                <a href="login.php" class="login-btn">Sign In Now</a>
            </div>
        </div>
    </div>
    
    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const matchText = document.getElementById('matchText');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Check password strength
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update strength bar
            const width = strength * 25;
            strengthFill.style.width = width + '%';
            
            // Update colors and text
            const strengths = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#27ae60'];
            
            strengthText.textContent = 'Password strength: ' + strengths[strength];
            strengthFill.style.background = colors[strength];
            
            // Check password match
            checkPasswordMatch();
        });
        
        confirmInput.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm === '') {
                matchText.textContent = 'Passwords match: ';
                matchText.style.color = '';
            } else if (password === confirm) {
                matchText.textContent = 'Passwords match: ✓';
                matchText.style.color = '#27ae60';
            } else {
                matchText.textContent = 'Passwords match: ✗';
                matchText.style.color = '#e74c3c';
            }
        }
        
        // Form validation
        const form = document.getElementById('signupForm');
        form.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            const userType = document.getElementById('user_type').value;
            const fullName = document.getElementById('full_name').value;
            const email = document.getElementById('email').value;
            
            // Basic validation
            if (!fullName.trim()) {
                e.preventDefault();
                alert('Please enter your full name!');
                return;
            }
            
            if (!email.trim()) {
                e.preventDefault();
                alert('Please enter your email!');
                return;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return;
            }
            
            if (!userType) {
                e.preventDefault();
                alert('Please select an account type!');
                return;
            }
        });
        
        // Auto-format phone number
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = value.match(/.{1,4}/g).join(' ');
            }
            e.target.value = value;
        });
    </script>
</body>
</html>