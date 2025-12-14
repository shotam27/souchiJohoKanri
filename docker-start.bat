@echo off
echo ========================================
echo  装置情報管理システム - Docker起動
echo ========================================
echo.

REM Dockerがインストールされているかチェック
docker --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Docker がインストールされていません
    echo    Docker Desktop をインストールしてください
    echo    URL: https://www.docker.com/products/docker-desktop/
    echo.
    pause
    exit /b 1
)

echo ✓ Docker が利用可能です
docker --version
echo.

REM Docker Composeがインストールされているかチェック
docker compose version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ❌ Docker Compose が利用できません
    pause
    exit /b 1
)

echo ✓ Docker Compose が利用可能です
docker compose version
echo.

echo [1] Dockerコンテナをビルド・起動中...
docker compose up -d --build

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo  🎉 起動完了！
    echo ========================================
    echo.
    echo 📱 アプリケーション: http://localhost:8000
    echo 🗄️  phpMyAdmin:     http://localhost:8080
    echo     ユーザー: root
    echo     パスワード: rootpassword
    echo.
    echo [2] データベースの初期化を実行中...
    timeout /t 10 /nobreak >nul
    docker compose exec web php init_database.php
    
    if %ERRORLEVEL% EQU 0 (
        echo.
        echo ✅ データベース初期化完了！
        echo.
        echo 🚀 準備完了！ブラウザで http://localhost:8000 にアクセスしてください
    ) else (
        echo.
        echo ⚠️  データベース初期化でエラーが発生しました
        echo    手動で以下を実行してください:
        echo    docker compose exec web php init_database.php
    )
    
    echo.
    echo ========================================
    echo  便利なコマンド
    echo ========================================
    echo  停止: docker compose down
    echo  ログ確認: docker compose logs -f
    echo  コンテナ一覧: docker compose ps
    echo  データベースリセット: docker compose down -v
    echo.
) else (
    echo.
    echo ❌ Docker起動でエラーが発生しました
    echo    以下を確認してください:
    echo    1. Docker Desktop が起動しているか
    echo    2. ポート8000, 8080, 3306が使用されていないか
    echo    3. docker-compose.yml が正しく配置されているか
)

pause