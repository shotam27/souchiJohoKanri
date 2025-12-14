@echo off
echo ========================================
echo  装置情報管理システム - Docker停止
echo ========================================
echo.

echo [1] Dockerコンテナを停止中...
docker compose down

if %ERRORLEVEL% EQU 0 (
    echo ✓ コンテナが正常に停止されました
) else (
    echo ❌ 停止処理でエラーが発生しました
)

echo.
echo 利用可能なコマンド:
echo   起動: docker-start.bat または docker compose up -d
echo   完全削除: docker compose down -v  (データも削除)
echo   ログ確認: docker compose logs
echo.
pause