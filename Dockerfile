FROM php:8.2-cli

# Kerakli PHP extensionlar
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    && docker-php-ext-install sockets

# Ishchi papka
WORKDIR /app

# Reponi konteynerga yuklaymiz
COPY . /app

# Botni ishga tushirish
CMD ["php", "bot.php"]
