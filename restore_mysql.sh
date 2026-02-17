#!/bin/bash

# MySQL リストアスクリプト
# 使用方法: ./restore_mysql.sh <バックアップファイルのパス>

# 設定
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-device_management}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

# 引数チェック
if [ $# -eq 0 ]; then
    echo "使用方法: $0 <バックアップファイルのパス>"
    echo ""
    echo "例:"
    echo "  $0 backups/daily/backup_device_management_20260217_120000.sql.gz"
    exit 1
fi

BACKUP_FILE="$1"

# ファイルの存在確認
if [ ! -f "${BACKUP_FILE}" ]; then
    echo "エラー: ファイルが見つかりません: ${BACKUP_FILE}"
    exit 1
fi

echo "=========================================="
echo "データベースリストア"
echo "バックアップファイル: ${BACKUP_FILE}"
echo "データベース: ${DB_NAME}"
echo "=========================================="
echo ""
read -p "このデータベースを復元しますか？ 現在のデータは上書きされます。(yes/no): " CONFIRM

if [ "${CONFIRM}" != "yes" ]; then
    echo "リストアをキャンセルしました。"
    exit 0
fi

echo ""
echo "リストアを開始します..."

# リストアの実行
if [ -z "${DB_PASS}" ]; then
    # パスワードなし
    gunzip < "${BACKUP_FILE}" | mysql -h "${DB_HOST}" \
                                      -P "${DB_PORT}" \
                                      -u "${DB_USER}" \
                                      "${DB_NAME}"
else
    # パスワードあり
    gunzip < "${BACKUP_FILE}" | mysql -h "${DB_HOST}" \
                                      -P "${DB_PORT}" \
                                      -u "${DB_USER}" \
                                      -p"${DB_PASS}" \
                                      "${DB_NAME}"
fi

# リストアの成否を確認
if [ $? -eq 0 ]; then
    echo ""
    echo "✓ リストアが完了しました"
    echo "=========================================="
else
    echo ""
    echo "✗ エラー: リストアに失敗しました"
    echo "=========================================="
    exit 1
fi

exit 0
