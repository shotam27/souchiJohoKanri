# 装置情報管理システム - ファイル構成

## 📁 プロジェクト構成

```
1031_Fusion/
├── 📄 メインファイル
│   ├── index.php          # CSVアップロードページ
│   ├── search.php         # 装置検索ページ
│   ├── download.php       # CSVダウンロードページ
│   ├── upload.php         # ファイルアップロード処理
│   └── ajax_api.php       # Ajax API エンドポイント
│
├── ⚙️ 設定ファイル
│   ├── config.php         # 本番環境設定
│   └── config.docker.php  # Docker環境設定
│
├── 📂 classes/            # PHPクラス
│   ├── Database.php       # データベース接続クラス
│   ├── DeviceManager.php  # デバイス管理クラス
│   └── CsvProcessor.php   # CSV処理クラス
│
├── 📂 includes/           # 共通テンプレート
│   ├── header.php         # 共通ヘッダー
│   └── footer.php         # 共通フッター
│
├── 📂 svgs/              # SVGアイコン
│   ├── brand.svg         # ブランドアイコン
│   ├── upload.svg        # アップロードアイコン
│   ├── search.svg        # 検索アイコン
│   └── download.svg      # ダウンロードアイコン
│
├── 📂 admin/             # 管理者用ファイル
│   ├── admin_tables.php  # テーブル管理
│   └── init_database.php # データベース初期化
│
├── 📂 docs/              # ドキュメント
│   ├── README.md         # プロジェクト概要
│   ├── README.docker.md  # Docker環境構築手順
│   └── SETUP.md          # セットアップ手順
│
├── 📂 uploads/           # アップロードファイル
├── 📂 logs/              # ログファイル
├── 📂 database/          # データベース関連
│
├── 🐳 Docker関連
│   ├── docker-compose.yml # Docker構成
│   ├── Dockerfile         # Webサーバー設定
│   ├── docker-start.bat   # Docker開始スクリプト
│   ├── docker-stop.bat    # Docker停止スクリプト
│   └── .dockerignore      # Docker除外設定
│
└── 📊 サンプルデータ
    ├── sample.csv         # サンプルCSVファイル
    └── test_devices.csv   # テスト用デバイスデータ
```

## 🚀 主要機能

### 1. CSVアップロード (index.php)
- CSVファイルのアップロード
- 動的テーブル作成
- データバリデーション
- リレーション自動登録

### 2. 装置検索 (search.php)
- 高速検索機能
- フィルタリング
- ページネーション
- 統計情報表示

### 3. CSVダウンロード (download.php)
- データエクスポート
- プレビュー機能
- フィルタリング
- Excel対応形式

## 🛠️ 技術スタック

- **Backend**: PHP 8.2, MySQL 8.0
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Infrastructure**: Docker, Docker Compose
- **Webserver**: Apache 2.4
- **Database Admin**: phpMyAdmin

## 📋 ファイルの役割

### コアファイル
- `index.php` - メインのアップロードインターフェース
- `search.php` - 検索・表示インターフェース
- `download.php` - ダウンロードインターフェース
- `upload.php` - バックエンド処理（ファイル処理）
- `ajax_api.php` - フロントエンド用API

### クラス設計
- `Database.php` - PDO接続管理、トランザクション制御
- `DeviceManager.php` - ビジネスロジック、CRUD操作
- `CsvProcessor.php` - CSV解析、変換処理

### 管理・運用
- `admin/admin_tables.php` - データベーステーブル管理
- `admin/init_database.php` - 初期セットアップ
- `docker-start.bat` / `docker-stop.bat` - 簡単起動・停止

## 🔧 開発・保守

### 環境起動
```bash
./docker-start.bat
```

### 管理機能
```
http://localhost:8000/admin/admin_tables.php
```

### ログ確認
```
logs/ フォルダ内のログファイル
```

---
*Last updated: October 31, 2025*