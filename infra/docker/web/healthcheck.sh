#!/bin/sh
# Pide /health directamente a PHP-FPM: sano solo si Laravel responde y llega a la base.
SCRIPT_NAME=/index.php \
SCRIPT_FILENAME=/var/www/html/public/index.php \
REQUEST_URI=/health \
REQUEST_METHOD=GET \
cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q '"status":"ok"'
