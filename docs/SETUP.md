# 開発環境セットアップガイド

## 現在の状況
- PHP: インストールされていません
- MySQL: インストールされていません

## 推奨セットアップ方法

### オプション1: XAMPP（推奨）- 簡単セットアップ

1. **XAMPPダウンロード**
   - https://www.apachefriends.org/jp/index.html からダウンロード
   - Windows版を選択

2. **XAMPPインストール**
   - ダウンロードしたファイルを実行
   - Apache, MySQL, PHPを選択してインストール

3. **XAMPPコントロールパネル起動**
   - Apache と MySQL を Start

4. **パス設定**
   ```powershell
   # 環境変数PATHにXAMPPのPHPパスを追加
   # 通常は: C:\xampp\php
   ```

### オプション2: 個別インストール

#### PHP インストール
1. https://windows.php.net/download/ から PHP をダウンロード
2. 解凍して適当な場所に配置（例: C:\php）
3. 環境変数PATHに追加

#### MySQL インストール
1. https://dev.mysql.com/downloads/mysql/ から MySQL をダウンロード
2. インストーラーを実行
3. 環境変数PATHに追加（通常: C:\Program Files\MySQL\MySQL Server 8.0\bin）

## セットアップ後の確認

### 1. コマンドプロンプト/PowerShellで確認
```powershell
php --version
mysql --version
```

### 2. MySQLサービス確認
```powershell
# XAMPPの場合
C:\xampp\mysql\bin\mysql.exe -u root -p

# 個別インストールの場合
mysql -u root -p
```

### 3. データベース作成
```sql
CREATE DATABASE device_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE device_management;
```

### 4. アプリケーション初期化
```powershell
cd C:\Users\User\Desktop\myWorks\1031_Fusion
php init_database.php
```

### 5. Webサーバー起動
```powershell
php -S localhost:8000
```

## XAMPPを使用する場合の設定

### config.php の設定変更
```php
// XAMPP のデフォルト設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'device_management');
define('DB_USER', 'root');
define('DB_PASS', '');  // XAMPPのデフォルトは空パスワード
```

## トラブルシューティング

### MySQLに接続できない場合
1. XAMPPコントロールパネルでMySQLが起動しているか確認
2. ポート3306が使用されているか確認
3. ファイアウォールの設定を確認

### PHPが認識されない場合
1. 環境変数PATHにPHPのパスが追加されているか確認
2. PowerShellを再起動
3. php.iniの設定を確認

## 次のステップ

環境が整ったら以下を実行：

1. **データベース作成**
   ```sql
   CREATE DATABASE device_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **アプリケーション初期化**
   ```powershell
   php init_database.php
   ```

3. **Webサーバー起動**
   ```powershell
   php -S localhost:8000
   ```

4. **ブラウザでアクセス**
   http://localhost:8000

## 参考リンク

- [XAMPP公式サイト](https://www.apachefriends.org/jp/index.html)
- [PHP公式ダウンロード](https://windows.php.net/download/)
- [MySQL公式ダウンロード](https://dev.mysql.com/downloads/mysql/)