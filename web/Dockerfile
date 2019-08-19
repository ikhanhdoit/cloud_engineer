FROM centos:centos7

RUN yum update -y
RUN yum install -y \
	httpd php php-mysqlnd
RUN systemctl enable httpd
RUN chkconfig httpd on
RUN yum install -y vim
RUN yum install -y mysql

COPY index.php /var/www/html/index.php
COPY httpd.conf /etc/httpd/conf/httpd.conf
COPY insert.php /var/www/html/insert.php
COPY query.php /var/www/html/query.php

EXPOSE 80 443 3306

CMD [ "/usr/sbin/httpd", "-DFOREGROUND" ]
