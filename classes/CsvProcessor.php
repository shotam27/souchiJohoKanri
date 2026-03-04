<?php
/**
 * CSVファイル処理クラス
 */
class CsvProcessor {
    private $filePath;
    private $headers;
    private $data;
    private $errors;
    private $delimiter;
    
    public function __construct($filePath = null) {
        $this->filePath = $filePath;
        $this->headers = [];
        $this->data = [];
        $this->errors = [];
        $this->delimiter = ',';
    }

    /**
     * CSVの区切り文字を自動検出する（カンマ or タブ）
     * @param string $content ファイル内容
     * @return string 検出された区切り文字
     */
    private function detectDelimiter($content) {
        $firstLine = strtok($content, "\n");
        $tabCount   = substr_count($firstLine, "\t");
        $commaCount = substr_count($firstLine, ',');
        return ($tabCount > $commaCount) ? "\t" : ',';
    }

    /**
     * 使用中の区切り文字を返す
     * @return string
     */
    public function getDelimiter() {
        return $this->delimiter;
    }
    
    /**
     * CSVファイルを読み込む
     * @param string $filePath
     * @return bool
     */
    public function loadFile($filePath) {
        $this->filePath = $filePath;
        $this->headers = [];
        $this->data = [];
        $this->errors = [];
        
        if (!file_exists($filePath)) {
            $this->errors[] = "ファイルが存在しません: " . $filePath;
            return false;
        }
        
        if (!is_readable($filePath)) {
            $this->errors[] = "ファイルを読み込めません: " . $filePath;
            return false;
        }
        
        // ファイルのエンコーディングを検出・変換
        $content = file_get_contents($filePath);
        $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS', 'EUC-JP', 'ASCII'], true);
        
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            file_put_contents($filePath, $content);
        }

        // 区切り文字を自動検出（カンマ or タブ）
        $this->delimiter = $this->detectDelimiter($content);

        // CSVファイルを開く
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->errors[] = "CSVファイルを開けません";
            return false;
        }
        
        $rowNumber = 0;
        while (($row = fgetcsv($handle, 0, $this->delimiter)) !== false) {
            $rowNumber++;
            
            if ($rowNumber === 1) {
                // ヘッダー行
                $this->headers = array_map('trim', $row);
                
                // 必須カラムの存在確認（4つのみ）
                $requiredColumns = ['サービス名', '装置種別', '装置名称', 'ユーザ名1'];
                foreach ($requiredColumns as $required) {
                    if (!in_array($required, $this->headers)) {
                        $this->errors[] = "必須カラムが不足しています: " . $required;
                    }
                }
                
                if (!empty($this->errors)) {
                    fclose($handle);
                    return false;
                }
            } else {
                // データ行
                if (count($row) !== count($this->headers)) {
                    $this->errors[] = "行{$rowNumber}: カラム数が一致しません（期待: " . count($this->headers) . ", 実際: " . count($row) . "）";
                    continue;
                }
                
                $rowData = [];
                for ($i = 0; $i < count($this->headers); $i++) {
                    $rowData[$this->headers[$i]] = isset($row[$i]) ? trim($row[$i]) : '';
                }
                
                // 必須項目の検証
                if (empty($rowData['サービス名'])) {
                    $this->errors[] = "行{$rowNumber}: サービス名が空です";
                    continue;
                }
                if (empty($rowData['装置種別'])) {
                    $this->errors[] = "行{$rowNumber}: 装置種別が空です";
                    continue;
                }
                if (empty($rowData['装置名称'])) {
                    $this->errors[] = "行{$rowNumber}: 装置名称が空です";
                    continue;
                }
                if (empty($rowData['ユーザ名1'])) {
                    $this->errors[] = "行{$rowNumber}: ユーザ名1が空です";
                    continue;
                }
                
                $this->data[] = $rowData;
            }
        }
        
        fclose($handle);
        
        if (empty($this->data)) {
            $this->errors[] = "有効なデータが見つかりません";
            return false;
        }
        
        return empty($this->errors);
    }
    
    /**
     * ヘッダー情報を取得
     * @return array
     */
    public function getHeaders() {
        return $this->headers;
    }
    
    /**
     * データを取得
     * @return array
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * エラー情報を取得
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * device_infoテーブルのカラム一覧を取得
     * @return array
     */
    public function getDeviceInfoColumns() {
        return [
            'サービス名',
            '装置種別', 
            '装置名称',
            'ログインIP',
            'ユーザ名1',
            'パスワード1',
            'ユーザ名2',
            'パスワード2',
            'ユーザ名3',
            'パスワード3',
            'ユーザ名4',
            'パスワード4',
            'ユーザ名5',
            'パスワード5',
            'ユーザ名6',
            'パスワード6',
            'ユーザ名7',
            'パスワード7',
            'ユーザ名8',
            'パスワード8',
            'ユーザ名9',
            'パスワード9',
            'ユーザ名10',
            'パスワード10'
        ];
    }
    
    /**
     * 基本カラム（device_infoに登録されるカラム）を取得
     * @return array
     */
    public function getBasicColumns() {
        return $this->getDeviceInfoColumns();
    }
    
    /**
     * 拡張カラム（device_info以外のカラム）を取得
     * @return array
     */
    public function getExtendedColumns() {
        $deviceInfoColumns = $this->getDeviceInfoColumns();
        $extendedColumns = [];
        
        foreach ($this->headers as $header) {
            if (!in_array($header, $deviceInfoColumns)) {
                $extendedColumns[] = $header;
            }
        }
        
        return $extendedColumns;
    }
    
    /**
     * 主キー値を生成（データ用）
     * @param array $rowData
     * @return string
     */
    public function generatePrimaryKey($rowData) {
        return $rowData['サービス名'] . '_' . 
               $rowData['装置種別'] . '_' . 
               $rowData['装置名称'] . '_' . 
               $rowData['ユーザ名1'];
    }
    
    /**
     * 主キーカラム名を生成（テーブル定義用）
     * @param array $rowData
     * @return string
     */
    public function generatePrimaryKeyColumnName($rowData) {
        // 主キーカラム名を完全に固定
        return 'primary_key';
    }
    
    /**
     * 動的テーブル名を生成
     * @param array $rowData
     * @return string
     */
    public function generateTableName($rowData) {
        return sanitizeTableName($rowData['サービス名'] . '_' . $rowData['装置種別']);
    }
    
    /**
     * データを装置情報テーブル用に変換
     * @param array $rowData
     * @return array
     */
    public function convertToDeviceInfo($rowData) {
        $result = [
            'primary_key' => $this->generatePrimaryKey($rowData),
            'service_name' => $rowData['サービス名'],
            'device_type' => $rowData['装置種別'],
            'device_name' => $rowData['装置名称'],
            'login_ip' => isset($rowData['ログインIP']) ? $rowData['ログインIP'] : null,
            'username1' => $rowData['ユーザ名1'],
            'password1' => isset($rowData['パスワード1']) ? $rowData['パスワード1'] : null
        ];
        
        // ユーザ名2-10、パスワード2-10を追加
        for ($i = 2; $i <= 10; $i++) {
            $result["username{$i}"] = isset($rowData["ユーザ名{$i}"]) ? $rowData["ユーザ名{$i}"] : null;
            $result["password{$i}"] = isset($rowData["パスワード{$i}"]) ? $rowData["パスワード{$i}"] : null;
        }
        
        return $result;
    }
    
    /**
     * データを動的テーブル用に変換
     * @param array $rowData
     * @return array
     */
    public function convertToExtendedData($rowData) {
        $extendedColumns = $this->getExtendedColumns();
        
        // 主キーカラム名は日本語のまま使用
        $primaryKeyColumnName = $this->generatePrimaryKeyColumnName($rowData);
        $primaryKeyValue = $this->generatePrimaryKey($rowData);
        
        $result = [
            $primaryKeyColumnName => $primaryKeyValue
        ];
        
        foreach ($extendedColumns as $column) {
            // 拡張カラム名も日本語のまま使用
            $result[$column] = isset($rowData[$column]) ? $rowData[$column] : null;
        }
        
        return $result;
    }
    
    /**
     * CSVファイルの統計情報を取得
     * @return array
     */
    public function getStatistics() {
        if (empty($this->data)) {
            return [
                'total_rows' => 0,
                'services' => [],
                'device_types' => [],
                'unique_combinations' => 0
            ];
        }
        
        $services = [];
        $deviceTypes = [];
        $combinations = [];
        
        foreach ($this->data as $row) {
            $services[] = $row['サービス名'];
            $deviceTypes[] = $row['装置種別'];
            $combinations[] = $row['サービス名'] . '_' . $row['装置種別'];
        }
        
        return [
            'total_rows' => count($this->data),
            'services' => array_unique($services),
            'device_types' => array_unique($deviceTypes),
            'unique_combinations' => array_unique($combinations)
        ];
    }
    
    /**
     * バリデーションの実行
     * @return bool
     */
    public function validate() {
        $this->errors = [];
        
        if (empty($this->headers)) {
            $this->errors[] = "CSVファイルが読み込まれていません";
            return false;
        }
        
        if (empty($this->data)) {
            $this->errors[] = "データが存在しません";
            return false;
        }
        
        // 主キーの重複チェック
        $primaryKeys = [];
        foreach ($this->data as $index => $row) {
            $primaryKey = $this->generatePrimaryKey($row);
            if (in_array($primaryKey, $primaryKeys)) {
                $this->errors[] = "行" . ($index + 2) . ": 主キーが重複しています - " . $primaryKey;
            } else {
                $primaryKeys[] = $primaryKey;
            }
        }
        
        // IPアドレス形式のチェック（存在する場合のみ）
        foreach ($this->data as $index => $row) {
            if (!empty($row['ログインIP']) && !filter_var($row['ログインIP'], FILTER_VALIDATE_IP)) {
                $this->errors[] = "行" . ($index + 2) . ": ログインIPの形式が不正です - " . $row['ログインIP'];
            }
        }
        
        return empty($this->errors);
    }
}
?>