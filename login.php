<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (empty($user['email_verified_at'])) {
            $_SESSION['verify_user_id'] = $user['id'];
            header('Location: verify_otp.php');
            exit;
        }

        if (($user['approval_status'] ?? 'pending') !== 'approved') {
            $error = 'Your account is still pending approval.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'];
            header('Location: dashboard.php');
            exit;
        }
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Login - ERMS</title>
        <link href="css/styles.css?v=20260515-auth-sync" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="bg-primary">
        <div id="layoutAuthentication" class="auth-screen">
            <div id="layoutAuthentication_content">
                <main>
                    <div class="container">
                        <div class="row justify-content-center align-items-center min-vh-100 py-4">
                            <div class="col-xl-4 col-lg-5 col-md-7">
                                <div class="auth-brand text-center mb-3">
                                    <div class="auth-logo"><i class="fas fa-shield-halved"></i></div>
                                    <h1>ERMS</h1>
                                    <p>Employee Information Portal</p>
                                </div>
                                <div class="card auth-card shadow-lg border-0 rounded-lg">
                                    <div class="card-header auth-card-header">
                                        <h3>Welcome Back</h3>
                                        <p>Sign in to continue to your dashboard.</p>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($_GET['message'])): ?><div class="alert alert-success"><?= e($_GET['message']) ?></div><?php endif; ?>
                                        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                                        <form method="post">
                                            <div class="auth-input form-floating mb-3">
                                                <i class="fas fa-user"></i>
                                                <input class="form-control" id="username" name="username" type="text" required />
                                                <label for="username">Username</label>
                                            </div>
                                            <div class="auth-input form-floating mb-3">
                                                <i class="fas fa-lock"></i>
                                                <input class="form-control" id="password" name="password" type="password" required />
                                                <label for="password">Password</label>
                                            </div>
                                            <div class="d-grid"><button class="btn btn-primary btn-action" type="submit"><i class="fas fa-right-to-bracket"></i><span>Login</span></button></div>
                                        </form>
                                    </div>
                                    <div class="card-footer auth-footer text-center py-3">
                                        <a class="btn btn-outline-primary btn-action w-100" href="register.php"><i class="fas fa-user-plus"></i><span>Register as manager</span></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
