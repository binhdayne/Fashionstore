#!/bin/bash
chmod -R 777 /var/www/html/var/ /var/www/html/pub/ /var/www/html/generated/ 2>/dev/null
exec "$@"
