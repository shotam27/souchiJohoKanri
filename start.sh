#!/bin/bash
set -e

# ServerName警告を抑制
echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Renderの環境変数PORTを使用してApacheを設定
if [ -n "$PORT" ]; then
    echo "Configuring Apache to listen on port $PORT"
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/:80>/:$PORT>/g" /etc/apache2/sites-available/000-default.conf
else
    echo "PORT environment variable not set, using default port 80"
fi

# Apache設定を表示（デバッグ用）
echo "Apache will listen on:"
grep "Listen" /etc/apache2/ports.conf

# Apacheを起動
exec apache2-foreground
