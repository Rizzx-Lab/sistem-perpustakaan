# Dockerfile

FROM php:8.2-cli

# Install extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 8080

# Start PHP server
CMD php -S 0.0.0.0:$PORT -t .