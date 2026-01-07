# 装置情報管理システム - PHPファイル一覧

全16ファイルの完全なソースコードを含むドキュメントページです。

## 🌐 GitHub Pages

このリポジトリはGitHub Pagesで公開されています。

**閲覧URL**: [https://YOUR_USERNAME.github.io/YOUR_REPO_NAME/php_files_list.html](https://YOUR_USERNAME.github.io/YOUR_REPO_NAME/php_files_list.html)

##  収録ファイル

### ROOT (9ファイル)
- ajax_api.php - Ajax APIエンドポイント
- config.docker.php - Docker環境用設定
- config.render.php - Render本番環境用設定
- debug.php - デバッグページ
- download.php - CSVダウンロード機能
- index.php - CSVアップロードページ
- search.php - 装置情報検索ページ
- test-static.php - 静的ファイルテスト
- upload.php - CSVアップロード処理

### includes (2ファイル)
- header.php - 共通ヘッダー
- footer.php - 共通フッター

### admin (2ファイル)
- admin_tables.php - テーブル管理
- init_database.php - DB初期化

### classes (3ファイル)
- CsvProcessor.php - CSV処理クラス
- Database.php - DB接続管理クラス
- DeviceManager.php - 装置情報管理クラス

##  GitHub Pagesへのデプロイ方法

1. GitHubで新しいリポジトリを作成
2. ローカルでコミット:
\\\ash
git add .
git commit -m "Initial commit: PHP files documentation"
\\\

3. GitHubにプッシュ:
\\\ash
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git
git branch -M main
git push -u origin main
\\\

4. GitHubリポジトリ設定:
   - Settings > Pages
   - Source: Deploy from a branch
   - Branch: main / (root)
   - Save

5. 数分後、以下のURLでアクセス可能:
   \https://YOUR_USERNAME.github.io/YOUR_REPO_NAME/php_files_list.html\

##  機能

-  全16ファイルの完全なソースコード
-  折りたたみ式表示（デフォルト非表示）
-  ワンクリックコピー機能
-  美しいグラデーションデザイン
-  レスポンシブ対応

##  統計

- 総行数: 約4,000行
- ファイルサイズ: 約165KB
- 対応言語: PHP, HTML, CSS, JavaScript

---

Generated: 2026-01-07 22:49:31
