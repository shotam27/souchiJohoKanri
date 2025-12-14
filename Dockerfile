# PHP 8.2 with Apache
FROM php:8.2-apache

# 必要な拡張機能をインストール
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Apacheのmod_rewriteを有効化
RUN a2enmod rewrite

# 作業ディレクトリを設定
WORKDIR /var/www/html

# アプリケーションファイルをコピー
COPY . /var/www/html/

# アップロードディレクトリの権限を設定
RUN chmod 755 /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads

# Apacheのポート80を公開
EXPOSE 80