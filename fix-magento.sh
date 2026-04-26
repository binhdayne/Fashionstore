#!/bin/bash
# Tự động tìm tên container chứa php
CONTAINER_NAME=$(docker ps --format "{{.Names}}" | grep php)

echo "--- Đang dọn dẹp cache trong container: $CONTAINER_NAME ---"
docker exec -it $CONTAINER_NAME rm -rf pub/static/* var/view_preprocessed/* generated/* var/cache/*

echo "--- Đang cấp quyền file ---"
docker exec -it $CONTAINER_NAME chmod -R 777 pub/static var generated

echo "--- Đang Deploy giao diện (vi_VN và en_US) ---"
docker exec -it $CONTAINER_NAME php bin/magento setup:static-content:deploy -f vi_VN en_US

echo "--- Xong! Hãy load lại trình duyệt ---"
