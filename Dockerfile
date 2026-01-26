# We use 8.2 or 8.3 for stability. 
# (Official PHP 8.4 images are not always available on all platforms yet)
FROM php:8.2-apache

# 1. Install dependencies
# We include git, zip, and unzip for Composer
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo_mysql zip

# 2. Enable Apache mod_rewrite
RUN a2enmod rewrite

# 3. Set working directory
WORKDIR /var/www/html

# 4. Copy application files
COPY . .

# 5. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 6. Set permissions
# We give ownership of the whole HTML folder to www-data.
# This prevents errors if 'storage' or 'bootstrap/cache' are missing from git.
RUN chown -R www-data:www-data /var/www/html

# 7. Configure Apache DocumentRoot to point to public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# --- FIX IS HERE (Removed the .0 from the end) ---
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 8. Expose Port
EXPOSE 80