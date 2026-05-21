FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader

# Copy project files
COPY . .

# Generate autoloader
RUN composer dump-autoload

# Default command
CMD ["vendor/bin/phpunit"]
