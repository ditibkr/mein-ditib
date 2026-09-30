#!/bin/bash
# Cron starten (Alpine verwendet crond, nicht service cron)
crond
# PHP built-in Server
exec php -S 0.0.0.0:3232 -t /var/www/html/public /var/www/html/public/index.php