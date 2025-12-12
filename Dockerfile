FROM php:8.2-cli

# Kerakli PHP extensionlar
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    && docker-php-ext-install sockets

# Ishchi papka
WORKDIR /app

# Barcha fayllarni konteynerga yuklaymiz
COPY . /app

# 24/7 ishlashi uchun
CMD ["php", "bot.php"]
