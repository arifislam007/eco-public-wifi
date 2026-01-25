FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    freeradius-utils \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install PECL radius extension (optional)
RUN pecl install radius \
    && docker-php-ext-enable radius || echo "Radius extension not available, will use radclient fallback"

# Enable Apache modules
RUN a2enmod rewrite ssl

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY portal/ /var/www/html/portal/
COPY admin/ /var/www/html/admin/
COPY scripts/ /var/www/html/scripts/

# Create logs directory
RUN mkdir -p /var/www/html/logs && chmod 777 /var/www/html/logs

# Configure Apache
COPY docker/php/apache-config.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Expose ports
EXPOSE 80 443

# Start Apache
CMD ["apache2-foreground"]
