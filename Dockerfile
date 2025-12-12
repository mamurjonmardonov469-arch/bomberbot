FROM php:8.2-cli

WORKDIR /app

COPY . /app

RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl

CMD ["php", "bot.php"]
