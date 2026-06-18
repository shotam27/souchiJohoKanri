<?php
/**
 * ユーザー管理クラス
 * パスワードはbcryptハッシュで安全に保管
 */
class User {
    private $db;
    private $dbType;
    
    public function __construct(Database $database) {
        $this->db = $database;
        $this->dbType = $database->getDbType();
    }
    
    /**
     * ユーザーを登録
     * @param string $username ユーザー名
     * @param string $password パスワード（平文）
     * @return array 成功時: ['success' => true, 'user_id' => id], 失敗時: ['success' => false, 'error' => message]
     */
    public function register($username, $password) {
        try {
            // 入力検証
            if (empty($username) || empty($password)) {
                return ['success' => false, 'error' => 'ユーザー名とパスワードは必須です'];
            }
            
            if (strlen($username) < 3) {
                return ['success' => false, 'error' => 'ユーザー名は3文字以上で入力してください'];
            }
            
            if (strlen($password) < 6) {
                return ['success' => false, 'error' => 'パスワードは6文字以上で入力してください'];
            }
            
            // ユーザー名の重複チェック
            if ($this->usernameExists($username)) {
                return ['success' => false, 'error' => 'このユーザー名は既に使用されています'];
            }
            
            // パスワードをbcryptでハッシュ化（コスト10）
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            
            if ($passwordHash === false) {
                return ['success' => false, 'error' => 'パスワードのハッシュ化に失敗しました'];
            }
            
            // データベースに登録
            $conn = $this->db->connect();
            $sql = "INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => $passwordHash
            ]);
            
            $userId = $conn->lastInsertId();
            
            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'ユーザー登録が完了しました'
            ];
            
        } catch (PDOException $e) {
            error_log("User registration error: " . $e->getMessage());
            return ['success' => false, 'error' => 'ユーザー登録に失敗しました'];
        }
    }
    
    /**
     * ユーザー名の存在確認
     * @param string $username
     * @return bool
     */
    private function usernameExists($username) {
        try {
            $conn = $this->db->connect();
            $sql = "SELECT COUNT(*) FROM users WHERE username = :username";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':username' => $username]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Username check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ログイン認証
     * @param string $username ユーザー名
     * @param string $password パスワード（平文）
     * @return array 成功時: ['success' => true, 'user' => [user data]], 失敗時: ['success' => false, 'error' => message]
     */
    public function login($username, $password) {
        try {
            // 入力検証
            if (empty($username) || empty($password)) {
                return ['success' => false, 'error' => 'ユーザー名とパスワードを入力してください'];
            }
            
            // ユーザー情報を取得
            $conn = $this->db->connect();
            $sql = "SELECT id, username, password_hash, is_active FROM users WHERE username = :username";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'error' => 'ユーザー名またはパスワードが正しくありません'];
            }
            
            // アカウントの有効性チェック
            if (!$user['is_active']) {
                return ['success' => false, 'error' => 'このアカウントは無効です'];
            }
            
            // パスワード検証
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'error' => 'ユーザー名またはパスワードが正しくありません'];
            }
            
            // パスワードの再ハッシュが必要かチェック（bcryptのコストが変更された場合）
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 10])) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                $updateSql = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->execute([
                    ':password_hash' => $newHash,
                    ':id' => $user['id']
                ]);
            }
            
            // 最終ログイン日時を更新
            $updateLoginSql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
            $updateLoginStmt = $conn->prepare($updateLoginSql);
            $updateLoginStmt->execute([':id' => $user['id']]);
            
            // セッションに保存
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in'] = true;
            
            // セッションハイジャック対策
            session_regenerate_id(true);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username']
                ],
                'message' => 'ログインしました'
            ];
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'ログインに失敗しました'];
        }
    }
    
    /**
     * ログアウト
     */
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * ログイン状態の確認
     * @return bool
     */
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * 現在のユーザー情報を取得
     * @return array|null
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null
        ];
    }
    
    /**
     * ユーザーIDからユーザー情報を取得
     * @param int $userId
     * @return array|null
     */
    public function getUserById($userId) {
        try {
            $conn = $this->db->connect();
            $sql = "SELECT id, username, created_at, last_login, is_active FROM users WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * パスワード変更
     * @param int $userId
     * @param string $currentPassword 現在のパスワード
     * @param string $newPassword 新しいパスワード
     * @return array
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // 現在のパスワードを検証
            $conn = $this->db->connect();
            $sql = "SELECT password_hash FROM users WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'error' => 'ユーザーが見つかりません'];
            }
            
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => '現在のパスワードが正しくありません'];
            }
            
            // 新しいパスワードの検証
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'error' => '新しいパスワードは6文字以上で入力してください'];
            }
            
            // 新しいパスワードをハッシュ化
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
            
            // パスワードを更新
            $updateSql = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([
                ':password_hash' => $newHash,
                ':id' => $userId
            ]);
            
            return ['success' => true, 'message' => 'パスワードを変更しました'];
            
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'error' => 'パスワード変更に失敗しました'];
        }
    }
}
