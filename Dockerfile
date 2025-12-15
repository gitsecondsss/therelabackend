# Use official PHP image with Apache
FROM php:8.2-apache

# Enable required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring json

# Copy backend files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/data

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
