-- データベース作成（必要に応じて）
-- CREATE DATABASE device_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE device_management;

-- 装置情報テーブル（基本情報を格納）
CREATE TABLE IF NOT EXISTS device_info (
    primary_key VARCHAR(500) NOT NULL PRIMARY KEY COMMENT 'サービス名_装置種別名_装置名_ユーザ名の複合キー',
    service_name VARCHAR(100) NOT NULL COMMENT 'サービス名',
    device_type VARCHAR(100) NOT NULL COMMENT '装置種別',
    device_name VARCHAR(100) NOT NULL COMMENT '装置名称',
    device_ip VARCHAR(45) COMMENT '装置IP',
    username VARCHAR(100) NOT NULL COMMENT 'ユーザー名',
    password VARCHAR(255) COMMENT 'パスワード',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    INDEX idx_service_device_type (service_name, device_type),
    INDEX idx_device_info (service_name, device_type, device_name, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='装置基本情報テーブル';

-- サービス名と装置種別のリレーションテーブル
CREATE TABLE IF NOT EXISTS service_device_type_relations (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
    service_name VARCHAR(100) NOT NULL COMMENT 'サービス名',
    device_type VARCHAR(100) NOT NULL COMMENT '装置種別',
    description TEXT COMMENT '説明',
    is_active TINYINT(1) DEFAULT 1 COMMENT '有効フラグ(1:有効, 0:無効)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    UNIQUE KEY unique_service_device_type (service_name, device_type),
    INDEX idx_service_name (service_name),
    INDEX idx_device_type (device_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='サービス名と装置種別のリレーションテーブル';

-- 動的テーブル作成用のサンプル（実際は PHP 側で動的に作成）
-- CREATE TABLE IF NOT EXISTS `サービスA_装置種別A` (
--     `サービスA_装置種別A_装置名_ユーザ名` VARCHAR(500) NOT NULL PRIMARY KEY,
--     `関連装置名称` VARCHAR(255),
--     `関連装置IP` VARCHAR(45),
--     `ポート番号` VARCHAR(10),
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;