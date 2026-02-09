<?php
// login.php - ADD session_start() HERE!
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    $sql = "SELECT user_id, full_name, password FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Meal Plan System</title>
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
            max-width: 400px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
            min-height: 600px;
        }
        
        /* Left Side - Login */
        .login-side {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .login-side::after {
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
        
        .form-group {
            margin-bottom: 25px;
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
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
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
            margin-bottom: 30px;
        }
        
        .auth-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .divider {
            text-align: center;
            position: relative;
            margin: 25px 0;
            color: var(--gray);
            font-size: 14px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background: var(--border);
        }
        
        .divider::before {
            left: 0;
        }
        
        .divider::after {
            right: 0;
        }
        
        .social-login {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .social-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--border);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 20px;
        }
        
        .social-btn.facebook {
            color: var(--facebook);
        }
        
        .social-btn.google {
            color: var(--google);
        }
        
        .social-btn.twitter {
            color: var(--twitter);
        }
        
        .social-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-color: currentColor;
        }
        
        .switch-auth {
            text-align: center;
            color: var(--gray);
            font-size: 15px;
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
        
        /* Right Side - Sign Up Preview */
        .signup-side {
            flex: 1;
            padding: 50px;
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .signup-preview {
            text-align: center;
        }
        
        .signup-icon {
            font-size: 80px;
            margin-bottom: 30px;
        }
        
        .signup-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .signup-description {
            font-size: 18px;
            margin-bottom: 40px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .features-list {
            list-style: none;
            margin-bottom: 40px;
        }
        
        .features-list li {
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .features-list i {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .get-started-btn {
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
        
        .get-started-btn:hover {
            background: transparent;
            color: white;
            border-color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Error Message */
        .error-message {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c33;
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
                max-width: 450px;
            }
            
            .login-side::after {
                display: none;
            }
            
            .login-side,
            .signup-side {
                padding: 30px;
            }
            
            .signup-side {
                order: -1;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <!-- Left Side: Login Form -->
        <div class="login-side">
            <h1 class="auth-title">Log In</h1>
            <p class="auth-subtitle">Welcome back to NutriPlan</p>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="johndoe@xyz.com" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="**************" required>
                </div>
                
                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="auth-btn">Log In</button>
            </form>
            
            <div class="divider">Or Sign In with</div>
            
            <div class="social-login">
                <button type="button" class="social-btn facebook">
                    <i class="fab fa-facebook-f"></i>
                </button>
                <button type="button" class="social-btn google">
                    <i class="fab fa-google"></i>
                </button>
                <button type="button" class="social-btn twitter">
                    <i class="fab fa-twitter"></i>
                </button>
            </div>
            
            <div class="switch-auth">
                Don't have an account? <a href="signup.php">Sign Up</a>
            </div>
        </div>
            </body>
            </html>