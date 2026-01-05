# Renderデータベース初期化スクリプト（Windows PowerShell用）
# 
# 使用方法:
# 1. Renderダッシュボードでデータベースの接続情報を取得
# 2. 以下の変数を設定
# 3. .\init-render-db.ps1 を実行

# データベース接続情報（Renderダッシュボードから取得）
$DB_HOST = "your-database-host.render.com"
$DB_PORT = "3306"
$DB_NAME = "device_management"
$DB_USER = "admin"
$DB_PASS = "your-database-password"

Write-Host "Renderデータベースにスキーマを適用します..." -ForegroundColor Cyan
Write-Host "接続先: $DB_HOST:$DB_PORT"
Write-Host "データベース: $DB_NAME"
Write-Host ""

# スキーマファイルの存在確認
if (-not (Test-Path "database/schema.sql")) {
    Write-Host "エラー: database/schema.sql が見つかりません" -ForegroundColor Red
    exit 1
}

# MySQLクライアントの確認
$mysqlPath = Get-Command mysql -ErrorAction SilentlyContinue
if (-not $mysqlPath) {
    Write-Host "エラー: MySQLクライアントがインストールされていません" -ForegroundColor Red
    Write-Host "MySQL公式サイトからダウンロード: https://dev.mysql.com/downloads/mysql/" -ForegroundColor Yellow
    exit 1
}

# データベースに接続してスキーマを適用
Write-Host "スキーマを適用中..." -ForegroundColor Yellow
$schemaContent = Get-Content "database/schema.sql" -Raw
$schemaContent | & mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASS $DB_NAME

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ スキーマの適用が完了しました！" -ForegroundColor Green
    Write-Host ""
    Write-Host "次のステップ:" -ForegroundColor Cyan
    Write-Host "1. Renderのwebサービスが起動していることを確認"
    Write-Host "2. 提供されたURLにアクセス"
    Write-Host "3. CSVファイルをアップロードしてテスト"
} else {
    Write-Host ""
    Write-Host "❌ エラーが発生しました" -ForegroundColor Red
    Write-Host "接続情報を確認してください" -ForegroundColor Yellow
    exit 1
}
