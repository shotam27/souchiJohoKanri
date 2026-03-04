<?php
/**
 * ユーザー操作ログ記録クラス
 * upload / download / manage (update_device, delete_device, teraterm) の操作を
 * audit_logs テーブルに記録する。
 */
class ActivityLogger {

    private $db;

    /** ログのアクション定数 */
    const ACTION_UPLOAD         = 'upload';
    const ACTION_DOWNLOAD       = 'download';
    const ACTION_UPDATE_DEVICE  = 'update_device';
    const ACTION_DELETE_DEVICE  = 'delete_device';
    const ACTION_TERATERM       = 'teraterm_download';

    public function __construct(Database $database) {
        $this->db = $database;
    }

    /**
     * 操作ログを記録する
     *
     * @param string      $username  操作ユーザー名
     * @param string      $action    アクション種別（ACTION_* 定数を推奨）
     * @param string|null $detail    補足情報（ファイル名・対象装置 primary_key など）
     * @param string|null $ip        クライアントIPアドレス（省略時は自動取得）
     * @return void
     */
    public function log(string $username, string $action, ?string $detail = null, ?string $ip = null): void {
        try {
            $resolvedIp = $ip ?? $this->getClientIp();

            $this->db->execute(
                "INSERT INTO audit_logs (username, action, detail, ip_address, created_at)
                 VALUES (:username, :action, :detail, :ip, NOW())",
                [
                    ':username' => $username,
                    ':action'   => $action,
                    ':detail'   => $detail,
                    ':ip'       => $resolvedIp,
                ]
            );
        } catch (Exception $e) {
            // ログ失敗はアプリの処理を止めない（エラーログのみ）
            error_log("ActivityLogger::log error: " . $e->getMessage());
        }
    }

    /**
     * クライアントIPを取得（リバースプロキシも考慮）
     */
    private function getClientIp(): string {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return 'unknown';
    }

    /**
     * ログ一覧を取得（管理画面用）
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLogs(int $limit = 100, int $offset = 0): array {
        return $this->db->query(
            "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
            [':limit' => $limit, ':offset' => $offset]
        );
    }
}
