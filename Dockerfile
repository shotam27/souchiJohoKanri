# PHP 8.2 with Apache
FROM php:8.2-apache

# 必要なシステムパッケージをインストール（Composer依存含む）
RUN apt-get update && apt-get install -y unzip git && rm -rf /var/lib/apt/lists/*

# 必要な拡張機能をインストール
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHP設定（アップロード制限を緩和）
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Apacheのmod_rewriteを有効化
RUN a2enmod rewrite

# 作業ディレクトリを設定
WORKDIR /var/www/html

# アプリケーションファイルをコピー
COPY . /var/www/html/

# Composer依存関係をインストール
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

# 必要なディレクトリを作成して権限を設定
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chmod 777 /var/www/html/uploads \
    && chmod 777 /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs

# DB初期化 + Apache起動スクリプトを設定
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Apacheのポート80を公開
EXPOSE 80

CMD ["/docker-entrypoint.sh"]