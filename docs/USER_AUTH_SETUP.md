# ユーザー認証機能のセットアップ

このアプリケーションにユーザー認証機能が追加されました。

## 📋 実装内容

### 1. セキュリティ機能
- **パスワードのハッシュ化**: bcryptアルゴリズム（コスト10）でパスワードを安全に保管
- **SQLインジェクション対策**: プリペアドステートメントを使用
- **CSRF対策**: トークンによる検証
- **セッションハイジャック対策**: ログイン時にセッションIDを再生成

### 2. 作成されたファイル

#### データベース
- `database/users_schema.sql` - ユーザーテーブルのスキーマ
- `admin/init_users_table.php` - テーブル初期化スクリプト

#### クラス
- `classes/User.php` - ユーザー管理クラス（登録、ログイン、認証機能）

#### ページ
- `register.php` - ユーザー登録画面
- `login.php` - ログイン画面
- `logout.php` - ログアウト処理

#### ヘルパー
- `includes/auth_helper.php` - 認証関連のヘルパー関数

### 3. 修正されたファイル
- `includes/header.php` - ナビゲーションバーにログイン/ログアウトボタンを追加
- `css/styles.css` - 認証関連のスタイルを追加

## 🚀 セットアップ手順

### 1. データベーステーブルを作成

コマンドラインから以下を実行:

```bash
php admin/init_users_table.php
```

または、直接SQLを実行:

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ユーザーID',
    username VARCHAR(100) NOT NULL UNIQUE COMMENT 'ユーザー名',
    password_hash VARCHAR(255) NOT NULL COMMENT 'パスワードハッシュ（bcrypt）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    last_login TIMESTAMP NULL COMMENT '最終ログイン日時',
    is_active TINYINT(1) DEFAULT 1 COMMENT '有効フラグ(1:有効, 0:無効)',
    INDEX idx_username (username),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. ユーザー登録

ブラウザで `register.php` にアクセスし、最初のユーザーを登録します。

- ユーザー名: 3文字以上
- パスワード: 6文字以上

### 3. ログイン

`login.php` からログインできます。

## 💡 使い方

### 認証が必要なページを作成する場合

ページの先頭に以下を追加:

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_helper.php';

// ログインを必須にする
requireLogin();

// 現在のユーザー情報を取得
$username = getLoggedInUsername();
$userId = getLoggedInUserId();
?>
```

### ヘルパー関数

```php
// ログイン状態を確認
if (isLoggedIn()) {
    // ログイン済みの処理
}

// ユーザー名を取得
$username = getLoggedInUsername();

// ユーザーIDを取得
$userId = getLoggedInUserId();

// ログインを必須にする（未ログインの場合はログインページにリダイレクト）
requireLogin();
```

### Userクラスの使用例

```php
$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_TYPE, DB_PORT);
$user = new User($database);

// ユーザー登録
$result = $user->register('username', 'password');

// ログイン
$result = $user->login('username', 'password');

// ログアウト
$user->logout();

// パスワード変更
$result = $user->changePassword($userId, 'currentPassword', 'newPassword');

// 現在のユーザー情報取得
$currentUser = $user->getCurrentUser();

// ユーザーIDから情報取得
$userData = $user->getUserById($userId);
```

## 🔒 セキュリティの特徴

1. **パスワードハッシュ化**
   - bcryptアルゴリズムを使用
   - パスワードは平文で保存されません
   - password_verify()で安全に検証

2. **パスワード要件**
   - 最低6文字以上
   - ユーザー名は3文字以上

3. **セッション管理**
   - ログイン時にセッションIDを再生成
   - ログアウト時に完全にセッションを破棄

4. **CSRF対策**
   - フォームにCSRFトークンを含む
   - サーバーサイドで検証

5. **SQLインジェクション対策**
   - プリペアドステートメントを使用
   - 全てのユーザー入力を安全に処理

## 📝 データベーステーブル構造

### users テーブル

| カラム名 | 型 | 説明 |
|---------|-----|------|
| id | INT | ユーザーID（主キー） |
| username | VARCHAR(100) | ユーザー名（ユニーク） |
| password_hash | VARCHAR(255) | パスワードハッシュ |
| created_at | TIMESTAMP | 作成日時 |
| updated_at | TIMESTAMP | 更新日時 |
| last_login | TIMESTAMP | 最終ログイン日時 |
| is_active | TINYINT(1) | 有効フラグ |

## 🎨 UI機能

- ナビゲーションバーに「ログイン」「新規登録」ボタンを表示（未ログイン時）
- ナビゲーションバーにユーザー名と「ログアウト」ボタンを表示（ログイン時）
- レスポンシブデザイン対応
- エラーメッセージと成功メッセージの表示

## 🔧 今後の拡張案

- パスワードリセット機能
- メール認証
- 二要素認証
- ユーザープロフィール管理
- 管理者権限の実装
- ユーザー一覧表示（管理者用）
