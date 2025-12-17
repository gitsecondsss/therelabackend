# Use official PHP + Apache image
FROM php:8.2-apache

# Install extensions/tools (curl optional, but handy for debugging)
RUN apt-get update && apt-get install -y \
    curl \
 && rm -rf /var/lib/apt/lists/*

# Enable needed PHP extensions (openssl is already in the base image)
RUN docker-php-ext-install opcache

# Copy your app into the web root
# Make sure your repo has: index.php, verify-human.php, etc. in the root
COPY . /var/www/html/

# Tighten permissions
RUN chown -R www-data:www-data /var/www/html

# Optional: enable .htaccess / rewrites if you need them later
RUN a2enmod rewrite

# Optional: harden Apache a bit (remove version leaks)
RUN sed -i 's/ServerTokens OS/ServerTokens Prod/i' /etc/apache2/conf-available/security.conf && \
    sed -i 's/ServerSignature On/ServerSignature Off/i' /etc/apache2/conf-available/security.conf && \
    a2enconf security

# Railway will map its $PORT to container port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
