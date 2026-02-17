#!/bin/bash

# MySQL バックアップスクリプト
# 使用方法: ./backup_mysql.sh [daily|weekly|monthly]

# 設定
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_ROOT="${SCRIPT_DIR}/backups"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-device_management}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

# バックアップタイプ（引数から取得、デフォルトはdaily）
BACKUP_TYPE="${1:-daily}"

# バックアップディレクトリ
BACKUP_DIR="${BACKUP_ROOT}/${BACKUP_TYPE}"
mkdir -p "${BACKUP_DIR}"

# タイムスタンプ
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/backup_${DB_NAME}_${TIMESTAMP}.sql.gz"

# ログファイル
LOG_DIR="${SCRIPT_DIR}/logs"
mkdir -p "${LOG_DIR}"
LOG_FILE="${LOG_DIR}/backup.log"

# ログ関数
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "${LOG_FILE}"
}

log "=========================================="
log "バックアップ開始: ${BACKUP_TYPE}"
log "データベース: ${DB_NAME}"

# mysqldumpの実行
if [ -z "${DB_PASS}" ]; then
    # パスワードなし
    mysqldump -h "${DB_HOST}" \
              -P "${DB_PORT}" \
              -u "${DB_USER}" \
              --single-transaction \
              --routines \
              --triggers \
              --events \
              "${DB_NAME}" | gzip > "${BACKUP_FILE}"
else
    # パスワードあり
    mysqldump -h "${DB_HOST}" \
              -P "${DB_PORT}" \
              -u "${DB_USER}" \
              -p"${DB_PASS}" \
              --single-transaction \
              --routines \
              --triggers \
              --events \
              "${DB_NAME}" | gzip > "${BACKUP_FILE}"
fi

# バックアップの成否を確認
if [ $? -eq 0 ] && [ -f "${BACKUP_FILE}" ]; then
    BACKUP_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
    log "バックアップ成功: ${BACKUP_FILE} (${BACKUP_SIZE})"
else
    log "エラー: バックアップに失敗しました"
    exit 1
fi

# 古いバックアップファイルの削除
case "${BACKUP_TYPE}" in
    daily)
        # 7日以上前のファイルを削除
        KEEP_DAYS=7
        log "日次バックアップ: ${KEEP_DAYS}日より古いファイルを削除"
        find "${BACKUP_DIR}" -name "backup_*.sql.gz" -type f -mtime +${KEEP_DAYS} -delete
        ;;
    weekly)
        # 4週間（28日）以上前のファイルを削除
        KEEP_DAYS=28
        log "週次バックアップ: ${KEEP_DAYS}日より古いファイルを削除"
        find "${BACKUP_DIR}" -name "backup_*.sql.gz" -type f -mtime +${KEEP_DAYS} -delete
        ;;
    monthly)
        # 12ヶ月（365日）以上前のファイルを削除
        KEEP_DAYS=365
        log "月次バックアップ: ${KEEP_DAYS}日より古いファイルを削除"
        find "${BACKUP_DIR}" -name "backup_*.sql.gz" -type f -mtime +${KEEP_DAYS} -delete
        ;;
esac

# 残っているバックアップファイルの数を表示
BACKUP_COUNT=$(ls -1 "${BACKUP_DIR}"/backup_*.sql.gz 2>/dev/null | wc -l)
log "保存されているバックアップファイル数: ${BACKUP_COUNT}"

log "バックアップ完了"
log "=========================================="

exit 0
