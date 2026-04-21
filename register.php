<?php
require_once __DIR__ . '/config.php';

// 既にログイン済みの場合はリダイレクト
$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_TYPE, DB_PORT);
$user = new User($database);

if ($user->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // CSRF対策
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = '不正なリクエストです';
    } elseif ($password !== $confirmPassword) {
        $error = 'パスワードが一致しません';
    } else {
        $result = $user->register($username, $password);
        
        if ($result['success']) {
            $success = $result['message'];
            // 自動ログイン
            $loginResult = $user->login($username, $password);
            if ($loginResult['success']) {
                header('Location: index.php');
                exit;
            }
        } else {
            $error = $result['error'];
        }
    }
}

// CSRFトークン生成
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

include __DIR__ . '/includes/header.php';
?>

<style>
.register-container {
    max-width: 450px;
    margin: 50px auto;
    padding: 30px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.register-container h2 {
    text-align: center;
    color: #333;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #555;
    font-weight: bold;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #4CAF50;
}

.btn-register {
    width: 100%;
    padding: 12px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-register:hover {
    background-color: #45a049;
}

.error-message {
    background-color: #f44336;
    color: white;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
    text-align: center;
}

.success-message {
    background-color: #4CAF50;
    color: white;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
    text-align: center;
}



.login-link a {
    color: #4CAF50;
    text-decoration: none;
}

.login-link a:hover {
    text-decoration: underline;
}

.password-requirements {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
</style>

<div class="register-container">
    <h2 style="display: flex; align-items: center; justify-content: center; gap: 10px;">
        <span style="width: 28px; height: 28px; display: inline-flex;"><?php include 'svgs/register.svg'; ?></span>
        ユーザー登録
    </h2>
    
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="register.php">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <div class="form-group">
            <label for="username">ユーザー名</label>
            <input type="text" id="username" name="username" required 
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                   minlength="3" maxlength="100">
            <div class="password-requirements">※ 3文字以上で入力してください</div>
        </div>
        
        <div class="form-group">
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password" required minlength="6">
            <div class="password-requirements">※ 6文字以上で入力してください</div>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">パスワード（確認）</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
        </div>
        
        <button type="submit" class="btn-register">登録する</button>
    </form>
    
    <div class="login-link">
        既にアカウントをお持ちの方は<a href="login.php">こちら</a>からログイン
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
