# Renderデプロイ用簡易セットアップスクリプト

このスクリプトは、Renderへのデプロイ準備を支援します。

## クイックスタート

### 1. GitHubリポジトリの準備

```bash
# まだGitリポジトリでない場合
git init
git add .
git commit -m "Initial commit for Render deployment"

# GitHubにプッシュ
git remote add origin <your-github-repo-url>
git push -u origin main
```

### 2. Renderアカウント作成

https://render.com にアクセスしてアカウントを作成

### 3. デプロイ方法を選択

#### 🚀 簡単な方法: Blueprint を使用

1. Renderダッシュボードで「New +」→「Blueprint」
2. GitHubリポジトリを接続
3. `render.yaml` が検出される
4. 「Apply」をクリック
5. 数分待つとデプロイ完了！

#### 🔧 手動方法: ステップバイステップ

詳細は `docs/RENDER_DEPLOYMENT.md` を参照

### 4. データベース初期化

デプロイ後、データベースにスキーマを適用：

```bash
# Renderダッシュボード→MySQL→Connect から接続情報を取得
mysql -h <HOST> -P <PORT> -u admin -p device_management < database/schema.sql
```

### 5. アクセス！

Renderが提供するURL（例: `https://fusion-device-manager.onrender.com`）にアクセス

## ファイル構成

- `render.yaml` - Render Blueprint設定（自動デプロイ用）
- `Dockerfile.render` - 本番環境用Docker設定
- `config.render.php` - 本番環境用PHP設定
- `docs/RENDER_DEPLOYMENT.md` - 詳細なデプロイ手順

## コスト

- **Free**: $0/月（スリープあり、制限あり）
- **Starter**: 約$14/月（常時稼働）
- **Standard**: 約$40/月（高性能）

## トラブルシューティング

### デプロイが失敗する

- Dockerfileが正しいか確認
- ビルドログを確認
- 環境変数が設定されているか確認

### データベースに接続できない

- DB_HOST が内部ホスト名（例: `fusion-mysql`）になっているか確認
- WebサービスとDBが同じリージョンにあるか確認

### アップロードが動作しない

- ディスクがマウントされているか確認
- `/var/www/html/uploads` にマウントされているか確認

## サポート

詳細なドキュメント: `docs/RENDER_DEPLOYMENT.md`
