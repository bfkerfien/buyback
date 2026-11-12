# Initial application container contract; application implementation will supply ./bin/server and ./bin/worker.
FROM php:8.3-cli-alpine
WORKDIR /app
RUN apk add --no-cache bash curl git icu-dev libzip-dev oniguruma-dev     && docker-php-ext-install intl opcache pdo_mysql zip
COPY . .
EXPOSE 8080
CMD ["./bin/server"]
