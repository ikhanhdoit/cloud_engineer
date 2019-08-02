#!/bin/bash
sudo yum update -y
sudo yum install -y httpd php php-mysqlnd
sudo systemctl start httpd
sudo chkconfig httpd on
sudo yum install -y vim
sudo yum install -y mysql
echo "Fortune-Of-The-Day Coming Soon!" > /var/www/html/index.html
