FROM mysql:5.7

ENV MYSQL_ROOT_PASSWORD=www
ENV MYSQL_USER=www
ENV MYSQL_PASSWORD=www

COPY fortune_script.sql /docker-entrypoint-initdb.d/

EXPOSE 3306
