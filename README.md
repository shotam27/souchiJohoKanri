# 装置情報管理システム

Docker + PHP + MySQL で構築された装置情報管理システムです。CSVファイルのアップロード・ダウンロード、装置情報の検索機能を提供します。

## 機能

- **CSVアップロード**: サービス名・装置種別ごとにCSVデータを登録
- **装置検索**: サービス名・装置種別・装置名での検索、統計情報表示
- **CSVダウンロード**: データのプレビュー表示、項目選択してCSVエクスポート

## 必要要件

- Docker Desktop
- Docker Compose

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

## 注意事項

- `config.php` には機密情報が含まれるため、Gitには含まれません
- `uploads/` と `logs/` ディレクトリはGitで管理されません
- 本番環境では `config.php` のエラー表示設定を無効にしてください

## ライセンス

MIT License
