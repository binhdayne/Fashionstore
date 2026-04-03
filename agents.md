# Agents

## Available Agents

### Explore
- **Description**: Fast read-only codebase exploration and Q&A subagent
- **Use Case**: Searching and exploring code, asking questions about specific parts of the codebase
- **Thoroughness**: quick, medium, or thorough

## Usage

To use an agent, specify its name when needed for code exploration and analysis tasks.
 

# Kiến trúc project, code convention, quy tắc và hướng dẫn sử dụng thư mục src

## 1. Kiến trúc thư mục `src`
Thư mục `src` là nơi chứa mã nguồn chính của hệ thống Magento 2, bao gồm:
- `app/`: Chứa code ứng dụng, module, cấu hình, autoload...
- `bin/`: Các script CLI hỗ trợ quản trị hệ thống.
- `dev/`: Công cụ phát triển, test, debug.
- `lib/`: Thư viện nội bộ.
- `phpserver/`: Cấu hình PHP server.
- `pub/`: Thư mục public, entrypoint cho web, chứa static, media...
- `setup/`: Script cài đặt, migration, performance toolkit.
- `var/`: Thư mục runtime, cache, log, session...
- `vendor/`: Thư viện bên thứ 3 (cài qua composer).

## 2. Code convention
- Tuân thủ chuẩn PSR-2, PSR-12 cho PHP (đã cấu hình sẵn qua `.php-cs-fixer.dist.php`).
- Sử dụng array syntax ngắn (`[]`).
- Không để dòng trắng thừa, không import không dùng.
- Tên class, file, namespace tuân thủ chuẩn Magento: PascalCase, phân tách module theo `Vendor_Module`.
- Đặt tên biến, hàm rõ nghĩa, tiếng Anh.
- Mỗi module nằm trong `app/code/Vendor/ModuleName`.

## 3. Quy tắc quan trọng
- Không sửa trực tiếp vào core Magento (trong `vendor/magento/`).
- Mọi custom code phải nằm trong `app/code` hoặc qua extension/module riêng.
- Không commit các file trong `var/`, `pub/static/`, `generated/` lên git.
- Kiểm tra kỹ permission file/folder khi deploy.
- Đảm bảo PHP >= 8.1.0.

## 4. Hướng dẫn sử dụng/thêm module vào `src`
1. Tạo module mới trong `app/code/Vendor/ModuleName`.
2. Tạo file `registration.php` và `etc/module.xml` cho module.
3. Chạy lệnh `php bin/magento setup:upgrade` để đăng ký module.
4. Code logic vào các thư mục Block, Controller, Model, View... theo chuẩn Magento.
5. Kiểm tra code bằng PHP CS Fixer: `php vendor/bin/php-cs-fixer fix`.
6. Đọc tài liệu Magento DevDocs để tuân thủ chuẩn phát triển.

## 5. Tài liệu tham khảo
- [Magento DevDocs](https://developer.adobe.com/commerce)
- [Magento Coding Standard](https://github.com/magento/magento-coding-standard)
