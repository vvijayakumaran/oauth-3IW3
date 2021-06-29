FROM php:7.4-fpm-alpine

WORKDIR /home/root

COPY . .

RUN chmod 755 /home/root

ENV PORT=80

COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint
ENTRYPOINT ["docker-entrypoint"]

COPY --from=caddy /usr/bin/caddy /usr/local/bin/caddy

RUN caddy validate --config docker/Caddyfile && \
    cp docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker.conf

EXPOSE 80

CMD ["caddy", "run", "--config", "docker/Caddyfile"]