# MySQL バックアップ設定ガイド

このガイドでは、MySQLデータベースの自動バックアップを設定する方法を説明します。

## 📁 ファイル構成

- `backup_mysql.sh` - バックアップスクリプト
- `restore_mysql.sh` - リストアスクリプト
- `backups/` - バックアップファイルの保存先（自動作成）
  - `daily/` - 日次バックアップ（7日分保持）
  - `weekly/` - 週次バックアップ（4週分保持）
  - `monthly/` - 月次バックアップ（12ヶ月分保持）

## 🔧 初期設定

### 1. スクリプトに実行権限を付与

```bash
chmod +x backup_mysql.sh
chmod +x restore_mysql.sh
```

### 2. 環境変数の設定（オプション）

デフォルト設定を変更する場合は、環境変数を設定します：

```bash
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=device_management
export DB_USER=root
export DB_PASS=your_password
```

または、`.env`ファイルを作成して管理することもできます。

## 📝 手動バックアップ

### 日次バックアップ
```bash
./backup_mysql.sh daily
```

### 週次バックアップ
```bash
./backup_mysql.sh weekly
```

### 月次バックアップ
```bash
./backup_mysql.sh monthly
```

## ⏰ Cron設定（自動バックアップ）

### Cronの編集

```bash
crontab -e
```

### Cron設定例

以下の設定を追加します：

```cron
# MySQL バックアップ設定
# ※重要: 環境変数をcronで使用する場合は、先頭で定義してください

# 環境変数の設定（必要に応じて変更）
DB_HOST=localhost
DB_PORT=3306
DB_NAME=device_management
DB_USER=root
DB_PASS=

# スクリプトのパス（プロジェクトの絶対パスに変更してください）
SCRIPT_DIR=/path/to/1031_Fusion

# 日次バックアップ: 毎日午前3時に実行
0 3 * * * cd $SCRIPT_DIR && ./backup_mysql.sh daily >> logs/backup.log 2>&1

# 週次バックアップ: 毎週日曜日の午前4時に実行
0 4 * * 0 cd $SCRIPT_DIR && ./backup_mysql.sh weekly >> logs/backup.log 2>&1

# 月次バックアップ: 毎月1日の午前5時に実行
0 5 1 * * cd $SCRIPT_DIR && ./backup_mysql.sh monthly >> logs/backup.log 2>&1
```

### Cronの時間設定の説明

```
分 時 日 月 曜日
│ │ │ │ │
│ │ │ │ └─ 曜日 (0-7, 0と7は日曜日)
│ │ │ └─── 月 (1-12)
│ │ └───── 日 (1-31)
│ └─────── 時 (0-23)
└───────── 分 (0-59)
```

### 実際の設定例（絶対パスを使用）

```cron
# 環境変数
DB_HOST=localhost
DB_NAME=device_management
DB_USER=root
DB_PASS=your_secure_password

# パス設定（実際のパスに変更してください）
PATH=/usr/local/bin:/usr/bin:/bin

# 日次バックアップ: 毎日午前3時
0 3 * * * /path/to/1031_Fusion/backup_mysql.sh daily >> /path/to/1031_Fusion/logs/backup.log 2>&1

# 週次バックアップ: 毎週日曜日午前4時
0 4 * * 0 /path/to/1031_Fusion/backup_mysql.sh weekly >> /path/to/1031_Fusion/logs/backup.log 2>&1

# 月次バックアップ: 毎月1日午前5時
0 5 1 * * /path/to/1031_Fusion/backup_mysql.sh monthly >> /path/to/1031_Fusion/logs/backup.log 2>&1
```

### Cronの設定確認

```bash
# 現在のcron設定を確認
crontab -l

# cronのログを確認（システムによって異なる）
grep CRON /var/log/syslog
# または
tail -f /var/log/cron
```

## 🔄 リストア（復元）

### バックアップファイルからリストア

```bash
./restore_mysql.sh backups/daily/backup_device_management_20260217_030000.sql.gz
```

### 利用可能なバックアップファイルの確認

```bash
# 日次バックアップ
ls -lh backups/daily/

# 週次バックアップ
ls -lh backups/weekly/

# 月次バックアップ
ls -lh backups/monthly/
```

### 最新のバックアップをリストア

```bash
# 最新の日次バックアップ
LATEST=$(ls -t backups/daily/backup_*.sql.gz | head -1)
./restore_mysql.sh "$LATEST"
```

## 📊 バックアップの保持期間

| バックアップタイプ | 実行頻度 | 保持期間 | 保存場所 |
|------------------|---------|---------|---------|
| 日次 (daily) | 毎日 | 7日間 | backups/daily/ |
| 週次 (weekly) | 毎週日曜日 | 4週間 | backups/weekly/ |
| 月次 (monthly) | 毎月1日 | 12ヶ月 | backups/monthly/ |

## 🔍 ログの確認

バックアップのログは以下のファイルに記録されます：

```bash
# ログファイルの確認
tail -f logs/backup.log

# 最近のバックアップ履歴
tail -n 50 logs/backup.log
```

## ⚠️ 注意事項

1. **ディスク容量**: バックアップファイルは圧縮されますが、データベースが大きい場合は十分なディスク容量を確保してください。

2. **セキュリティ**: 
   - バックアップファイルには機密情報が含まれる可能性があります。
   - パスワードをスクリプトに直接書かず、環境変数を使用してください。
   - バックアップディレクトリのパーミッションを適切に設定してください。

3. **テスト**: 
   - 本番環境で使用する前に、テスト環境でリストアのテストを行ってください。

4. **バックアップの検証**: 
   - 定期的にバックアップファイルからのリストアをテストして、バックアップが正常に機能していることを確認してください。

## 🛠️ トラブルシューティング

### mysql/mysqldumpコマンドが見つからない

```bash
# MySQLクライアントのインストール（Ubuntu/Debian）
sudo apt-get install mysql-client

# MySQLクライアントのインストール（CentOS/RHEL）
sudo yum install mysql
```

### パーミッションエラー

```bash
# スクリプトに実行権限を付与
chmod +x backup_mysql.sh restore_mysql.sh

# バックアップディレクトリの権限を設定
chmod 700 backups/
```

### Cronが実行されない

1. Cronサービスの確認
```bash
sudo systemctl status cron
# または
sudo service cron status
```

2. Cronログの確認
```bash
grep CRON /var/log/syslog
```

3. 絶対パスを使用しているか確認
```bash
which mysqldump
# 出力されたパスをスクリプト内で使用
```

## 📦 外部ストレージへのバックアップ（推奨）

本番環境では、バックアップを外部ストレージにも保存することを推奨します：

```bash
# 例: AWS S3へのアップロード
aws s3 cp backups/ s3://your-bucket/mysql-backups/ --recursive

# 例: rsyncで別サーバーへコピー
rsync -avz backups/ user@backup-server:/backup/mysql/
```

## 🔗 関連ファイル

- [config.php](config.php) - データベース設定
- [docker-compose.yml](docker-compose.yml) - Docker環境の設定
- [README.md](README.md) - プロジェクトのメインドキュメント
