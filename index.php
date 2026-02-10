<?php
require_once 'config.php';
require_once __DIR__ . '/includes/auth_helper.php';

$pageTitle = '装置情報管理システム';

// ログインしている場合は upload.php にリダイレクト
if (isLoggedIn()) {
    header('Location: upload.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .landing-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .landing-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 50px;
            max-width: 700px;
            width: 100%;
            text-align: center;
        }
        
        .landing-logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .landing-logo svg {
            width: 60px;
            height: 60px;
            fill: white;
        }
        
        .landing-title {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        .landing-subtitle {
            font-size: 1.2em;
            color: #666;
            margin-bottom: 40px;
        }
        
        .info-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .info-section h2 {
            color: #667eea;
            font-size: 1.5em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-section h2 svg {
            width: 24px;
            height: 24px;
            fill: #667eea;
        }
        
        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .info-list li {
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05em;
            color: #444;
        }
        
        .info-list li:last-child {
            border-bottom: none;
        }
        
        .info-list li svg {
            width: 20px;
            height: 20px;
            fill: #667eea;
            flex-shrink: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1em;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            padding: 15px 40px;
            border: 2px solid #667eea;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1em;
            font-weight: bold;
            transition: all 0.2s;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .update-date {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #999;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .landing-container {
                padding: 30px 20px;
            }
            
            .landing-title {
                font-size: 2em;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary, .btn-secondary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="landing-page">
        <div class="landing-container">
            <div class="landing-logo">
                <svg viewBox="0 0 24 24">
                    <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M11,7H13V9H11V7M11,11H13V17H11V11Z"/>
                </svg>
            </div>
            
            <h1 class="landing-title">装置情報管理システム</h1>
            <p class="landing-subtitle">Device Information Management System</p>
            
            <div class="info-section">
                <h2>
                    <svg viewBox="0 0 24 24">
                        <path d="M13,9H11V7H13M13,17H11V11H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/>
                    </svg>
                    ご利用について
                </h2>
                <ul class="info-list">
                    <li>
                        <svg viewBox="0 0 24 24">
                            <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                        </svg>
                        <strong>ログインが必要です：</strong> すべての機能を使用するにはログインが必要です
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24">
                            <path d="M12,17A2,2 0 0,0 14,15C14,13.89 13.1,13 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10C4,8.89 4.9,8 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z"/>
                        </svg>
                        <strong>アカウント作成可能：</strong> どなたでも自由にアカウントを作成できます
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M10,19L12,15H9V10H13V12L11,16H14V19H10Z"/>
                        </svg>
                        <strong>CSVファイル管理：</strong> 装置情報をCSVで一括管理できます
                    </li>
                    <li>
                        <svg viewBox="0 0 24 24">
                            <path d="M19,20H4C2.89,20 2,19.1 2,18V6C2,4.89 2.89,4 4,4H10L12,6H19A2,2 0 0,1 21,8H21L4,8V18L6.14,10H23.21L20.93,18.5C20.7,19.37 19.92,20 19,20Z"/>
                        </svg>
                        <strong>Teratermマクロ生成：</strong> SSH接続用のマクロを自動生成します
                    </li>
                </ul>
            </div>
            
            <div class="action-buttons">
                <a href="login.php" class="btn-primary">ログイン</a>
                <a href="register.php" class="btn-secondary">新規登録</a>
            </div>
            
            <div class="update-date">
                最終更新日: 2026年2月10日
            </div>
        </div>
    </div>
</body>
</html>