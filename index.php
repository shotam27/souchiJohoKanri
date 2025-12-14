<?php
require_once 'config.php';

$pageTitle = '装置情報管理システム - CSVアップロード';
$errorMessage = getErrorMessage();
$successMessage = getSuccessMessage();

// 共通ヘッダーを読み込み
require_once 'includes/header.php';
?>

    <div class="main-content">
        <div class="page-container">
            <div class="page-header">
                <h1 class="page-title">
                    <div class="page-title-icon black-svg">
                        <?php include 'svgs/upload.svg'; ?>
                    </div>
                    CSVファイルアップロード
                </h1>
            </div>

        <?php if ($errorMessage): ?>
        <div class="alert alert-error">
            <svg class="alert-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12,2C17.53,2 22,6.47 22,12C22,17.53 17.53,22 12,22C6.47,22 2,17.53 2,12C2,6.47 6.47,2 12,2M15.59,7L12,10.59L8.41,7L7,8.41L10.59,12L7,15.59L8.41,17L12,13.41L15.59,17L17,15.59L13.41,12L17,8.41L15.59,7Z"/>
            </svg>
            <div>
                <strong>エラー:</strong> <?= h($errorMessage) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <svg class="alert-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22A10,10 0 0,1 2,12A10,10 0 0,1 12,2M11,16.5L18,9.5L16.59,8.09L11,13.67L7.91,10.59L6.5,12L11,16.5Z"/>
            </svg>
            <div>
                <strong>成功:</strong> <?= h($successMessage) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M13,9H11V7H13M13,17H11V11H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                </svg>
                CSVファイル形式について
            </h3>
            <p>以下の形式のCSVファイルをアップロードしてください：</p>
            <div class="csv-format">
サービス名,装置種別,装置名称,装置IP,ユーザー名,パスワード,関連装置名称,関連装置IP,ポート番号
サービスA,装置種別A,souchimei,198.1.1.1,admin,admin123,kanrensouchi,198.1.1.11,1
サービスA,装置種別A,souchimei2,198.1.1.2,admin,admin123,kanrensouchi2,198.1.1.12,1
            </div>
            <ul>
                <li><strong>必須項目:</strong> サービス名、装置種別、装置名称、ユーザー名</li>
                <li><strong>主キー:</strong> [サービス名]_[装置種別]_[装置名称]_[ユーザー名]</li>
                <li><strong>7行目以降のカラム:</strong> 動的テーブルに格納されます</li>
                <li><strong>文字エンコーディング:</strong> UTF-8</li>
                <li><strong>ファイルサイズ制限:</strong> 最大10MB</li>
            </ul>
        </div>
        
        <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(generateCsrfToken()) ?>">
            
            <div class="form-group flex-col">
                <label for="csvFile">CSVファイルを選択:</label>
                
                <!-- ドラッグ&ドロップエリア -->
                <div class="drag-drop-area" id="dragDropArea">
                    <div class="drag-drop-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                    </div>
                    <div class="drag-drop-text">
                        CSVファイルをここにドラッグ&ドロップ
                    </div>
                    <div class="drag-drop-subtext">
                        または<strong>クリック</strong>してファイルを選択
                    </div>
                </div>
                
                <!-- 隠されたファイル入力 -->
                <input type="file" 
                       id="csvFile" 
                       name="csv_file" 
                       accept=".csv,text/csv,application/csv" 
                       class="file-input-hidden"
                       required>
                
                <!-- 選択されたファイル情報 -->
                <div class="selected-file-info" id="selectedFileInfo">
                    <div class="file-details">
                        <div class="file-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                            </svg>
                        </div>
                        <div class="file-info-text">
                            <div class="file-name" id="fileName"></div>
                            <div class="file-size" id="fileSize"></div>
                        </div>
                    </div>
                </div>
                
                <div class="file-info" style="margin-top: 10px;">
                    ※ CSVファイル（.csv）のみアップロード可能です（最大10MB）
                </div>
            </div>
            
            <div class="upload-progress" id="uploadProgress">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn" id="uploadBtn">
                    CSVファイルをアップロード
                </button>
            </div>
        </form>
    </div>

    <script>
        // ドラッグ&ドロップ機能
        const dragDropArea = document.getElementById('dragDropArea');
        const fileInput = document.getElementById('csvFile');
        const selectedFileInfo = document.getElementById('selectedFileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        
        // ドラッグ&ドロップエリアのクリックでファイル選択ダイアログを開く
        dragDropArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        // ドラッグオーバー
        dragDropArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            dragDropArea.classList.add('drag-over');
        });
        
        // ドラッグリーブ
        dragDropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            dragDropArea.classList.remove('drag-over');
        });
        
        // ドロップ
        dragDropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            dragDropArea.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (validateFile(file)) {
                    fileInput.files = files;
                    displayFileInfo(file);
                }
            }
        });
        
        // ファイル選択（通常の方法）
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                displayFileInfo(file);
            }
        });
        
        // ファイル情報を表示
        function displayFileInfo(file) {
            const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
            fileName.textContent = file.name;
            fileSize.textContent = `${fileSizeMB} MB`;
            
            selectedFileInfo.classList.add('show');
            dragDropArea.classList.add('has-file');
            
            // ドラッグエリアのテキストを更新
            const dragDropText = dragDropArea.querySelector('.drag-drop-text');
            const dragDropSubtext = dragDropArea.querySelector('.drag-drop-subtext');
            dragDropText.textContent = 'ファイルが選択されました';
            dragDropSubtext.innerHTML = '<strong>クリック</strong>して別のファイルを選択';
        }
        
        // ファイル検証
        function validateFile(file) {
            // ファイルサイズチェック
            if (file.size > <?= UPLOAD_MAX_SIZE ?>) {
                alert('ファイルサイズが大きすぎます。10MB以下のファイルを選択してください。');
                return false;
            }
            
            // ファイル形式チェック
            const allowedTypes = ['text/csv', 'application/csv', 'text/plain'];
            if (!allowedTypes.includes(file.type) && !file.name.toLowerCase().endsWith('.csv')) {
                alert('CSVファイルのみアップロード可能です。');
                return false;
            }
            
            return true;
        }
        
        // フォーム送信
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadProgress = document.getElementById('uploadProgress');
            
            if (!fileInput.files.length) {
                alert('CSVファイルを選択してください。');
                e.preventDefault();
                return;
            }
            
            const file = fileInput.files[0];
            if (!validateFile(file)) {
                e.preventDefault();
                return;
            }
            
            // アップロード開始
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'アップロード中...';
            uploadProgress.style.display = 'block';
            
            // 擬似的なプログレスバー
            let progress = 0;
            const progressInterval = setInterval(function() {
                progress += 5;
                document.getElementById('progressBar').style.width = progress + '%';
                if (progress >= 90) {
                    clearInterval(progressInterval);
                }
            }, 100);
        });
    </script>

        </div> <!-- page-container -->
    </div> <!-- main-content -->

<?php require_once 'includes/footer.php'; ?>