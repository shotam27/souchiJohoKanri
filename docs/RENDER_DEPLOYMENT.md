# Renderへのデプロイ手順

このドキュメントでは、装置情報管理システムをRenderにデプロイする手順を説明します。

## 前提条件

- Renderアカウント（https://render.com でサインアップ）
- GitHubアカウント
- プロジェクトがGitHubリポジトリにプッシュされていること

## デプロイ手順

### 1. GitHubにプッシュ

```bash
git add .
git commit -m "Render deployment setup"
git push origin main
```

### 2. Renderダッシュボードで設定

#### 方法A: render.yaml を使用（推奨）

1. [Render Dashboard](https://dashboard.render.com/) にログイン
2. 「New +」→「Blueprint」を選択
3. GitHubリポジトリを接続
4. リポジトリを選択
5. `render.yaml` が自動検出される
6. 「Apply」をクリック

これで、WebサービスとMySQLデータベースが自動的に作成されます。

#### 方法B: 手動設定

##### データベースの作成

1. [Render Dashboard](https://dashboard.render.com/) にログイン
2. 「New +」→「MySQL」を選択
3. 以下を設定：
   - **Name**: `fusion-mysql`
   - **Database**: `device_management`
   - **User**: `admin`
   - **Region**: お好みのリージョン（Singapore推奨）
   - **Plan**: Free（または有料プラン）
4. 「Create Database」をクリック
5. データベースの接続情報をメモ（後で使用）

##### Webサービスの作成

1. 「New +」→「Web Service」を選択
2. GitHubリポジトリを接続して選択
3. 以下を設定：
   - **Name**: `fusion-device-manager`
   - **Region**: データベースと同じリージョン
   - **Branch**: `main`
   - **Runtime**: `Docker`
   - **Plan**: Free（または有料プラン）

4. 環境変数を設定（Environment Variables セクション）：
   ```
   DB_HOST=<データベースの内部ホスト名>
   DB_NAME=device_management
   DB_USER=admin
   DB_PASS=<データベースのパスワード>
   ```

5. ディスクを追加（Disks セクション）：
   - **Name**: `fusion-uploads`
   - **Mount Path**: `/var/www/html/uploads`
   - **Size**: 1 GB

6. 「Create Web Service」をクリック

### 3. データベースの初期化

デプロイが完了したら、データベースにスキーマを適用する必要があります。

#### オプションA: Renderシェルから実行

1. Renderダッシュボードでデータベースを開く
2. 「Connect」→「External Connection」の情報を取得
3. ローカルのMySQLクライアントで接続：
   ```bash
   mysql -h <EXTERNAL_HOST> -P <PORT> -u admin -p device_management
   ```
4. `database/schema.sql` の内容を実行

#### オプションB: phpMyAdminを使用

別途phpMyAdminサービスを作成するか、ローカルツールを使用してスキーマをインポートします。

### 4. アクセス確認

1. Renderダッシュボードでwebサービスを開く
2. 表示されるURLにアクセス（例: `https://fusion-device-manager.onrender.com`）
3. アプリケーションが正常に動作することを確認

## 重要な注意事項

### 無料プランの制限

- **スリープ**: 15分間アクセスがないとスリープ状態になり、次回アクセス時に起動（30秒〜1分）
- **稼働時間**: 月750時間まで無料
- **データベース**: 1GBまで、90日後に削除される可能性
- **ディスク**: 1GBまで無料

### セキュリティ設定

本番環境では以下の設定を変更してください：

1. **エラー表示を無効化**
   
   `config.php` で：
   ```php
   ini_set('display_errors', 0);
   error_reporting(E_ERROR | E_WARNING | E_PARSE);
   ```

2. **強力なデータベースパスワード**
   
   Renderで自動生成されたパスワードを使用

3. **HTTPS強制**
   
   Renderは自動的にHTTPSを提供しますが、リダイレクトを追加することを推奨

### パフォーマンス最適化

1. **PHPのキャッシュ**
   
   `Dockerfile` にOPcacheを追加：
   ```dockerfile
   RUN docker-php-ext-install opcache
   ```

2. **Apache設定の最適化**
   
   `.htaccess` ファイルでキャッシュヘッダーを設定

## トラブルシューティング

### データベース接続エラー

- 環境変数が正しく設定されているか確認
- データベースとWebサービスが同じリージョンにあるか確認
- データベースの内部ホスト名を使用しているか確認

### アップロードディレクトリのエラー

- ディスクが正しくマウントされているか確認
- パスが `/var/www/html/uploads` であることを確認

### スリープからの復帰が遅い

- 有料プランにアップグレード
- または、定期的にpingを送るサービスを使用（uptimerobotなど）

## 更新のデプロイ

コードを更新してGitHubにプッシュすると、Renderが自動的に再デプロイします：

```bash
git add .
git commit -m "Update feature"
git push origin main
```

## コスト見積もり

- **Free Plan**: $0/月（制限あり）
- **Starter Plan**: Web Service $7/月 + MySQL $7/月 = $14/月
- **Standard Plan**: Web Service $25/月 + MySQL $15/月 = $40/月

## サポートとドキュメント

- [Render公式ドキュメント](https://render.com/docs)
- [Renderコミュニティフォーラム](https://community.render.com/)
