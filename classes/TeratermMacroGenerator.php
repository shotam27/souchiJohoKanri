<?php
/**
 * Teraterm マクロ (.ttl) ファイル生成クラス
 * 
 * SSH接続用のTeratermマクロファイルを生成します
 */
class TeratermMacroGenerator
{
    private string $hostAddr;
    private string $username;
    private string $password;
    private int $port;

    /**
     * コンストラクタ
     * 
     * @param string $hostAddr ホストアドレス (IPアドレスまたはホスト名)
     * @param string $username ユーザー名
     * @param string $password パスワード
     * @param int $port SSHポート番号 (デフォルト: 22)
     */
    public function __construct(
        string $hostAddr,
        string $username,
        string $password,
        int $port = 22
    ) {
        $this->hostAddr = $hostAddr;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }

    /**
     * Teratermマクロの内容を生成
     * 
     * @return string マクロファイルの内容
     */
    public function generate(): string
    {
        $macro = "; --- Configuration ---\n";
        $macro .= "HOSTADDR = '{$this->hostAddr}'\n";
        $macro .= "USERNAME = '{$this->username}'\n";
        $macro .= "PASSWORD = '{$this->password}'\n";
        $macro .= "\n";
        $macro .= "; --- Setup Connection String ---\n";
        $macro .= "COMMAND = HOSTADDR\n";
        $macro .= "strconcat COMMAND ':{$this->port} /ssh /2 /auth=password /user='\n";
        $macro .= "strconcat COMMAND USERNAME\n";
        $macro .= "strconcat COMMAND ' /passwd='\n";
        $macro .= "strconcat COMMAND PASSWORD\n";
        $macro .= "\n";
        $macro .= "; --- Execution ---\n";
        $macro .= "connect COMMAND\n";

        return $macro;
    }

    /**
     * マクロファイルを指定パスに保存
     * 
     * @param string $filePath 保存先ファイルパス
     * @return bool 成功した場合true
     */
    public function saveToFile(string $filePath): bool
    {
        $content = $this->generate();
        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * マクロファイルをダウンロード用に出力
     * 
     * @param string $filename ダウンロードファイル名 (デフォルト: connection.ttl)
     */
    public function download(string $filename = 'connection.ttl'): void
    {
        $content = $this->generate();

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $content;
        exit;
    }

    /**
     * 複数の接続情報から一括でマクロを生成
     * 
     * @param array $connections 接続情報の配列
     *                          [['host' => '...', 'user' => '...', 'pass' => '...', 'port' => 22], ...]
     * @param string $outputDir 出力ディレクトリ
     * @return array 生成されたファイルパスの配列
     */
    public static function generateBatch(array $connections, string $outputDir): array
    {
        $generatedFiles = [];

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        foreach ($connections as $index => $conn) {
            $hostAddr = $conn['host'] ?? '';
            $username = $conn['user'] ?? '';
            $password = $conn['pass'] ?? '';
            $port = $conn['port'] ?? 22;

            if (empty($hostAddr) || empty($username)) {
                continue;
            }

            $generator = new self($hostAddr, $username, $password, $port);
            
            // ファイル名を生成 (ホスト名_ユーザー名.ttl)
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $hostAddr);
            $filename = "{$safeName}_{$username}.ttl";
            $filePath = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

            if ($generator->saveToFile($filePath)) {
                $generatedFiles[] = $filePath;
            }
        }

        return $generatedFiles;
    }

    /**
     * CSVファイルから接続情報を読み込んでマクロを生成
     * 
     * @param string $csvPath CSVファイルパス
     * @param string $outputDir 出力ディレクトリ
     * @param array $columnMapping カラムマッピング ['host' => 'IP', 'user' => 'User', 'pass' => 'Password']
     * @return array 生成されたファイルパスの配列
     */
    public static function generateFromCsv(
        string $csvPath,
        string $outputDir,
        array $columnMapping = ['host' => 'IP', 'user' => 'User', 'pass' => 'Password', 'port' => 'Port']
    ): array {
        if (!file_exists($csvPath)) {
            throw new Exception("CSVファイルが見つかりません: {$csvPath}");
        }

        $connections = [];
        $handle = fopen($csvPath, 'r');
        
        if ($handle === false) {
            throw new Exception("CSVファイルを開けません: {$csvPath}");
        }

        // ヘッダー行を取得
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new Exception("CSVファイルが空です");
        }

        // カラムインデックスを取得
        $hostIndex = array_search($columnMapping['host'], $headers);
        $userIndex = array_search($columnMapping['user'], $headers);
        $passIndex = array_search($columnMapping['pass'], $headers);
        $portIndex = isset($columnMapping['port']) ? array_search($columnMapping['port'], $headers) : false;

        // データ行を読み込み
        while (($row = fgetcsv($handle)) !== false) {
            $conn = [
                'host' => $hostIndex !== false ? ($row[$hostIndex] ?? '') : '',
                'user' => $userIndex !== false ? ($row[$userIndex] ?? '') : '',
                'pass' => $passIndex !== false ? ($row[$passIndex] ?? '') : '',
                'port' => $portIndex !== false && isset($row[$portIndex]) ? (int)$row[$portIndex] : 22
            ];

            if (!empty($conn['host']) && !empty($conn['user'])) {
                $connections[] = $conn;
            }
        }

        fclose($handle);

        return self::generateBatch($connections, $outputDir);
    }

    // Getter メソッド
    public function getHostAddr(): string
    {
        return $this->hostAddr;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
