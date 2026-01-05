#!/bin/bash

# Renderデータベース初期化スクリプト
# 
# 使用方法:
# 1. Renderダッシュボードでデータベースの接続情報を取得
# 2. 以下の変数を設定
# 3. ./init-render-db.sh を実行

# データベース接続情報（Renderダッシュボードから取得）
DB_HOST="your-database-host.render.com"
DB_PORT="3306"
DB_NAME="device_management"
DB_USER="admin"
DB_PASS="your-database-password"

echo "Renderデータベースにスキーマを適用します..."
echo "接続先: $DB_HOST:$DB_PORT"
echo "データベース: $DB_NAME"
echo ""

# スキーマファイルの存在確認
if [ ! -f "database/schema.sql" ]; then
    echo "エラー: database/schema.sql が見つかりません"
    exit 1
fi

# MySQLクライアントの確認
if ! command -v mysql &> /dev/null; then
    echo "エラー: MySQLクライアントがインストールされていません"
    echo "インストール方法:"
    echo "  macOS: brew install mysql-client"
    echo "  Ubuntu: sudo apt-get install mysql-client"
    echo "  Windows: MySQL公式サイトからダウンロード"
    exit 1
fi

# データベースに接続してスキーマを適用
echo "スキーマを適用中..."
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/schema.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ スキーマの適用が完了しました！"
    echo ""
    echo "次のステップ:"
    echo "1. Renderのwebサービスが起動していることを確認"
    echo "2. 提供されたURLにアクセス"
    echo "3. CSVファイルをアップロードしてテスト"
else
    echo ""
    echo "❌ エラーが発生しました"
    echo "接続情報を確認してください"
    exit 1
fi
