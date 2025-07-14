FROM php:8.1.29-apache

# Instalar extensões necessárias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    libcurl4-openssl-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    libmariadb-dev \
    nano \
    && docker-php-ext-install pdo pdo_mysql gd curl mbstring sockets

# Ativar SSL e rewrite do Apache
RUN a2enmod rewrite ssl

# Copiar código para o Apache
COPY . /var/www/html

# Permissões
RUN chown -R www-data:www-data /var/www/html

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Rodar dependências
WORKDIR /var/www/html
RUN composer install --no-interaction