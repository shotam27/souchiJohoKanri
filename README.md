# 装置情報管理システム

Docker + PHP + MySQL で構築された装置情報管理システムです。CSVファイルのアップロード・ダウンロード、装置情報の検索機能を提供します。

## 機能

- **CSVアップロード**: サービス名・装置種別ごとにCSVデータを登録
- **装置検索**: サービス名・装置種別・装置名での検索、統計情報表示
- **CSVダウンロード**: データのプレビュー表示、項目選択してCSVエクスポート

## 必要要件

### ローカル開発環境
- Docker Desktop
- Docker Compose

### 本番環境（Render）
- Renderアカウント（[https://render.com](https://render.com)）
- GitHubアカウント
- MySQLクライアント（データベース初期化用）

## セットアップ

1. リポジトリをクローン
```bash
git clone <repository-url>
cd 1031_Fusion
```

2. 設定ファイルを作成
```bash
cp config.php.example config.php
```

3. Dockerコンテナを起動
```bash
docker-compose up -d --build
```

または、Windows の場合はバッチファイルを使用：
```bash
docker-start.bat
```

4. ブラウザでアクセス
- アプリケーション: http://localhost:8000
- phpMyAdmin: http://localhost:8080

## 停止方法

```bash
docker-compose down
```

または、Windows の場合：
```bash
docker-stop.bat
```

## 🚀 Renderへのデプロイ（本番環境）

このアプリケーションをRender（クラウドプラットフォーム）で公開できます。

### クイックスタート

1. **GitHubにプッシュ**
   ```bash
   git add .
   git commit -m "Render deployment"
   git push origin main
   ```

2. **Renderでデプロイ**
   - [Render Dashboard](https://dashboard.render.com/)にログイン
   - 「New +」→「Blueprint」を選択
   - GitHubリポジトリを接続
   - `render.yaml`が自動検出される
   - 「Apply」をクリック

3. **データベース初期化**
   ```bash
   # Windows
   .\init-render-db.ps1
   
   # Mac/Linux
   ./init-render-db.sh
   ```

4. **アクセス**
   - Renderが提供するURL（例: `https://fusion-device-manager.onrender.com`）にアクセス

### 詳細な手順

詳しいデプロイ手順は以下のドキュメントを参照してください：
- 📖 [Renderデプロイ手順（詳細版）](docs/RENDER_DEPLOYMENT.md)
- 🚀 [クイックセットアップガイド](RENDER_SETUP.md)

### コスト

- **Free**: $0/月（15分でスリープ、月750時間）
- **Starter**: 約$14/月（常時稼働）
- **Standard**: 約$40/月（高性能）



## プロジェクト構造

```
1031_Fusion/
├── docker-compose.yml      # Docker設定
├── Dockerfile              # PHPコンテナ設定
├── config.php.example      # 設定ファイルサンプル
├── index.php               # CSVアップロード
├── search.php              # 装置検索
├── download.php            # CSVダウンロード
├── ajax_api.php            # AJAX API
├── classes/                # PHPクラス
│   ├── Database.php
│   ├── DeviceManager.php
│   └── CSVHandler.php
├── includes/               # 共通コンポーネント
│   ├── header.php
│   └── footer.php
├── svgs/                   # SVGアイコン
├── uploads/                # アップロードファイル保存
└── logs/                   # ログファイル
```

## データベース接続情報

- ホスト: `mysql` (Docker内) / `localhost` (ホストから)
- ポート: `3306`
- データベース名: `device_management`
- ユーザー名: `app_user`
- パスワード: `secure_password`

## データベース構造

### 1. device_info テーブル（装置基本情報）

装置の基本情報を格納するメインテーブル。

| カラム名 | データ型 | NULL | キー | 説明 |
|---------|---------|------|------|------|
| primary_key | VARCHAR(500) | NO | PK | サービス名_装置種別名_装置名_ユーザ名の複合キー |
| service_name | VARCHAR(100) | NO | INDEX | サービス名 |
| device_type | VARCHAR(100) | NO | INDEX | 装置種別 |
| device_name | VARCHAR(100) | NO | INDEX | 装置名称 |
| device_ip | VARCHAR(45) | YES | - | 装置IP |
| username | VARCHAR(100) | NO | INDEX | ユーザー名 |
| password | VARCHAR(255) | YES | - | パスワード |
| created_at | TIMESTAMP | NO | - | 作成日時 |
| updated_at | TIMESTAMP | NO | - | 更新日時 |

**インデックス:**
- `PRIMARY KEY (primary_key)`
- `INDEX idx_service_device_type (service_name, device_type)`
- `INDEX idx_device_info (service_name, device_type, device_name, username)`

### 2. service_device_type_relations テーブル（サービスと装置種別の関係）

サービス名と装置種別の組み合わせを管理。

| カラム名 | データ型 | NULL | キー | 説明 |
|---------|---------|------|------|------|
| id | INT | NO | PK | 自動採番ID |
| service_name | VARCHAR(100) | NO | INDEX | サービス名 |
| device_type | VARCHAR(100) | NO | INDEX | 装置種別 |
| description | TEXT | YES | - | 説明 |
| is_active | TINYINT(1) | NO | INDEX | 有効フラグ(1:有効, 0:無効) |
| created_at | TIMESTAMP | NO | - | 作成日時 |
| updated_at | TIMESTAMP | NO | - | 更新日時 |

**インデックス:**
- `PRIMARY KEY (id)`
- `UNIQUE KEY unique_service_device_type (service_name, device_type)`
- `INDEX idx_service_name (service_name)`
- `INDEX idx_device_type (device_type)`
- `INDEX idx_active (is_active)`

### 3. 動的テーブル（サービス名_装置種別名）

CSVアップロード時に自動生成される拡張情報用テーブル。

**命名規則:** `` `サービス名_装置種別名` ``

**構造例:**
```sql
CREATE TABLE `サービスA_装置種別A` (
    `サービスA_装置種別A_装置名_ユーザ名` VARCHAR(500) PRIMARY KEY,
    `関連装置名称` VARCHAR(255),
    `関連装置IP` VARCHAR(45),
    `ポート番号` VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

- 1列目: 複合主キー（`primary_key`と同じ値）
- 2列目以降: CSVファイルの2列目以降の項目が動的に追加される

### テーブル間の関係

```
device_info (1)
    ↓ service_name, device_type
service_device_type_relations (N)
    ↓ サービス名_装置種別名
動的テーブル (1:1)
```

### データフロー

1. **CSVアップロード時:**
   - `service_device_type_relations` にサービス名と装置種別を登録
   - `device_info` に基本情報を登録
   - 動的テーブルを作成し、拡張情報を登録

2. **検索時:**
   - `device_info` から基本情報を取得
   - 動的テーブルから拡張情報を取得（LEFT JOIN）

3. **ダウンロード時:**
   - `device_info` と動的テーブルを結合してデータを取得

### データ例

**device_info:**
```
primary_key: "WebサービスA_Webサーバ_server01_admin"
service_name: "WebサービスA"
device_type: "Webサーバ"
device_name: "server01"
device_ip: "192.168.1.100"
username: "admin"
password: "xxxxx"
```

**service_device_type_relations:**
```
id: 1
service_name: "WebサービスA"
device_type: "Webサーバ"
is_active: 1
```

**動的テーブル（WebサービスA_Webサーバ）:**
```
WebサービスA_Webサーバ_server01_admin: "WebサービスA_Webサーバ_server01_admin"
関連装置名称: "LB01"
関連装置IP: "192.168.1.200"
ポート番号: "8080"
```

## 注意事項

- `config.php` には機密情報が含まれるため、Gitには含まれません
- `uploads/` と `logs/` ディレクトリはGitで管理されません
- 本番環境では `config.php` のエラー表示設定を無効にしてください

## ライセンス

MIT License
