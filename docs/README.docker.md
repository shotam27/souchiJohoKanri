# 🐳 Docker環境での装置情報管理システム

このドキュメントではDockerを使用したセットアップ方法を説明します。

## 📋 必要な環境

- **Docker Desktop** (Windows/Mac/Linux)
- **Git** (オプション: ソースコード取得用)

## 🚀 クイックスタート

### 1. Docker Desktopのインストール
- [Docker公式サイト](https://www.docker.com/products/docker-desktop/) からダウンロード
- インストール後、Docker Desktopを起動

### 2. アプリケーションの起動
```bash
# Windows
docker-start.bat

# または手動で実行
docker compose up -d --build
```

### 3. アクセス
- **アプリケーション**: http://localhost:8000
- **phpMyAdmin**: http://localhost:8080
  - ユーザー: `root`
  - パスワード: `rootpassword`

## 📊 Docker構成

### サービス構成
```yaml
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Server    │    │     MySQL       │    │   phpMyAdmin    │
│  (PHP + Apache) │◄──►│   Database      │◄──►│  (DB管理用)     │
│   Port: 8000    │    │   Port: 3306    │    │   Port: 8080    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### ディレクトリ構成
```
1031_Fusion/
├── 📄 docker-compose.yml      # Docker Compose設定
├── 📄 Dockerfile              # PHP/Apache コンテナ設定
├── 📄 docker-start.bat        # 起動スクリプト (Windows)
├── 📄 docker-stop.bat         # 停止スクリプト (Windows)
├── 📄 config.docker.php       # Docker用設定
├── 📁 uploads/                # ファイルアップロード先
└── 📁 database/
    └── schema.sql             # 初期データベーススキーマ
```

## 🛠️ Docker コマンド

### 基本操作
```bash
# 起動 (バックグラウンド)
docker compose up -d

# ビルドして起動
docker compose up -d --build

# 停止
docker compose down

# 完全削除 (データも削除)
docker compose down -v

# ログ表示
docker compose logs -f

# コンテナ状態確認
docker compose ps
```

### 開発用コマンド
```bash
# Webコンテナに入る
docker compose exec web bash

# MySQLに直接接続
docker compose exec mysql mysql -u root -p

# データベース初期化
docker compose exec web php init_database.php

# アプリケーションファイルの同期確認
docker compose exec web ls -la /var/www/html
```

## 🔧 設定のカスタマイズ

### データベース設定変更
`docker-compose.yml` の環境変数を編集:

```yaml
environment:
  MYSQL_ROOT_PASSWORD: your_password  # パスワード変更
  MYSQL_DATABASE: your_db_name        # DB名変更
```

### ポート番号変更
```yaml
services:
  web:
    ports:
      - "8080:80"  # 8080ポートに変更

  mysql:
    ports:
      - "3307:3306"  # 3307ポートに変更
```

### PHP設定のカスタマイズ
`Dockerfile` にPHP設定を追加:

```dockerfile
# アップロード制限変更
RUN echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini
RUN echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini
```

## 🐛 トラブルシューティング

### よくある問題と解決方法

#### 1. ポートが使用されています
```bash
# 使用中のプロセスを確認
netstat -ano | findstr :8000
netstat -ano | findstr :3306

# 設定でポートを変更するか、プロセスを終了
```

#### 2. データベースに接続できません
```bash
# MySQLコンテナの状態確認
docker compose logs mysql

# MySQLコンテナが起動するまで待機
docker compose up mysql
# 別のターミナルで
docker compose up web
```

#### 3. ファイルのアップロードができません
```bash
# アップロードディレクトリの権限確認
docker compose exec web ls -la uploads/

# 権限修正
docker compose exec web chmod 755 uploads/
docker compose exec web chown www-data:www-data uploads/
```

#### 4. 初期化スクリプトがエラー
```bash
# 手動でデータベース初期化
docker compose exec web php init_database.php

# MySQLに直接接続して確認
docker compose exec mysql mysql -u root -prootpassword -e "SHOW DATABASES;"
```

### ログの確認方法
```bash
# 全サービスのログ
docker compose logs

# 特定のサービスのログ
docker compose logs web
docker compose logs mysql
docker compose logs phpmyadmin

# リアルタイムでログ監視
docker compose logs -f web
```

## 📈 パフォーマンス最適化

### 本番環境向け設定
```yaml
# docker-compose.prod.yml
version: '3.8'
services:
  web:
    restart: always
    environment:
      - PHP_OPCACHE_ENABLE=1
  
  mysql:
    restart: always
    command: --innodb-buffer-pool-size=1G
```

## 🔒 セキュリティ設定

### 本番環境での注意点
1. **パスワードの変更**: デフォルトパスワードは必ず変更
2. **ポートの制限**: 必要なポートのみ公開
3. **データのバックアップ**: 定期的なバックアップ設定

```bash
# データのバックアップ
docker compose exec mysql mysqldump -u root -prootpassword device_management > backup.sql

# リストア
docker compose exec -T mysql mysql -u root -prootpassword device_management < backup.sql
```

## 📚 参考リンク

- [Docker公式ドキュメント](https://docs.docker.com/)
- [Docker Compose リファレンス](https://docs.docker.com/compose/)
- [MySQL Docker イメージ](https://hub.docker.com/_/mysql)
- [PHP Docker イメージ](https://hub.docker.com/_/php)