# Hướng dẫn Deploy Casino Portal lên GitHub & Server Production

## 1. Đưa code lên GitHub

### 1.1. Chuẩn bị (đã làm sẵn)

Repo đã được khởi tạo với:
- `.gitignore` — loại trừ `.env`, log, cache, upload người dùng, `vendor/`, `node_modules/`
- `.gitattributes` — ép LF cho file text, nhận dạng binary
- `.env.example` — mẫu biến môi trường (không chứa khóa thật)

### 1.2. Tạo repository trên GitHub

1. Vào <https://github.com/new>
2. **Owner**: chọn tài khoản của bạn
3. **Repository name**: `casino-portal` (chỉ chữ thường, `-`, `_`)
4. **Public** (miễn phí) hoặc **Private**
5. ⚠️ **KHÔNG** tick "Add a README file", "Add .gitignore", "Choose a license" → để tránh xung đột
6. Bấm **Create repository**

### 1.3. Kết nối và push

```bash
cd "C:\Users\PC\AppData\Local\Cursor\AgentStores\cursor_agent_stores\8b2d6d19-86f5-4de6-8355-7662f67d0e3b\files"

# thêm remote
git remote add origin https://github.com/<TENDANG>/casino-portal.git

# đặt tên nhánh chính
git branch -M main

# push lần đầu
git push -u origin main
```

Khi hỏi mật khẩu → dùng **Personal Access Token** (không dùng mật khẩu GitHub):
- GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic) → Generate new token
- Quyền: `repo` (đọc/ghi private repo) + `workflow`
- Copy token, dán vào ô password khi push

### 1.4. Cài GitHub CLI (tuỳ chọn, tiện hơn)

```powershell
winget install --id GitHub.cli
gh auth login
gh repo create casino-portal --public --source=. --remote=origin --push
```

### 1.5. Commit thay đổi về sau

```bash
git add .
git commit -m "mo ta thay doi"
git push
```

---

## 2. Deploy lên server Production

### 2.1. Yêu cầu server

| Thành phần | Yêu cầu |
|---|---|
| OS | Ubuntu 22.04 / Debian 12 |
| PHP | 8.1+ (khuyến nghị 8.2) với ext: pdo_mysql, mbstring, curl, gd, zip, intl |
| Web server | Nginx + PHP-FPM (khuyến nghị) hoặc Apache |
| Database | MySQL 8.0 / MariaDB 10.6+ |
| Tool | git, composer (nếu dùng vendor) |

### 2.2. Cài đặt trên server

```bash
# 1. Cập nhật & cài deps
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-mbstring \
  php8.2-curl php8.2-gd php8.2-zip php8.2-intl unzip git

# 2. Clone code
sudo mkdir -p /var/www/casino-portal
sudo chown -R $USER:$USER /var/www/casino-portal
cd /var/www/casino-portal
git clone https://github.com/<TENDANG>/casino-portal.git .
```

### 2.3. Tạo file `.env` trên server

```bash
cp .env.example .env
nano .env
```

Điền giá trị thật:

```ini
APP_NAME=Casino Portal
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ten-domain-cua-ban.com
APP_KEY=                       # sinh bằng: php -r "echo bin2hex(random_bytes(32));"

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=casino_platform
DB_USER=casino_user
DB_PASS=mat-khau-cu-ban

HEYBANK_BASE_URL=https://api.heybank.vn
HEYBANK_ACCESS_KEY=...
HEYBANK_SERVICE_ON=1

GENZSHOP_BASE_URL=...
GENZSHOP_API_KEY=...
GENZSHOP_SERVICE_ON=1

VIOTP_BASE_URL=...
VIOTP_TOKEN=...
VIOTP_SERVICE_ON=1

AUTOBANK_BASE_URL=https://api.sieuthicode.net
AUTOBANK_SERVICE_ON=1
AUTOBANK_CRON_TOKEN=token-ngau-nhien-dai

AUTOMXH_BASE_URL=https://trungtammmo.vn/api/external/automxh
AUTOMXH_API_KEY=...
AUTOMXH_SERVICE_ON=1

SMM_BASE_URL=...
SMM_API_KEY=...
SMM_SERVICE_ON=1
```

```bash
# phân quyền file nhạy cảm
chmod 600 .env
```

### 2.4. Tạo database & import schema

```bash
mysql -u root -p <<'SQL'
CREATE DATABASE casino_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'casino_user'@'localhost' IDENTIFIED BY 'mat-khau-cu-ban';
GRANT ALL PRIVILEGES ON casino_platform.* TO 'casino_user'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql -u casino_user -p casino_platform < database/schema.sql

# chạy các migration còn lại theo thứ tự
for v in 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18; do
  php database/migrate_v${v}.php
done

# nạp dữ liệu mẫu (game, provider, cấu hình)
php database/seed.php
php database/admin_account.php
```

### 2.5. Phân quyền thư mục

```bash
cd /var/www/casino-portal
chmod -R 775 storage
chown -R www-data:www-data storage
```

### 2.6. Cấu hình Nginx

```nginx
server {
    listen 80;
    server_name ten-domain-cua-ban.com;
    root /var/www/casino-portal/public;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_read_timeout 300;
    }

    # chặn truy cập trực tiếp app/, config/, database/
    location ~ ^/(app|config|database|storage|tests)/ {
        deny all;
        return 404;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/casino-portal /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 2.7. Cài HTTPS (khuyến nghị)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ten-domain-cua-ban.com
```

---

## 3. Cron job cho các dịch vụ tự động

```bash
crontab -e
```

```cron
# AutoBank - quet giao dich ngan hang
*/2 * * * * cd /var/www/casino-portal && php database/cron_autobank.php >> storage/logs/cron_autobank.log 2>&1

# AutoMXH - dong bo don tu dong
*/5 * * * * cd /var/www/casino-portal && php database/cron_automxh.php >> storage/logs/cron_automxh.log 2>&1

# SMM - don hang MXH
*/3 * * * * cd /var/www/casino-portal && php database/cron_smm.php >> storage/logs/cron_smm.log 2>&1

# SMS - chien dich tin nhan
*/10 * * * * cd /var/www/casino-portal && php database/cron_sms.php >> storage/logs/cron_sms.log 2>&1
```

> Các file `cron_*.php` trong `database/` cần được giữ lại khi cập nhật. Nếu đã xóa, tạo lại từ script tương ứng trong `app/Services/`.

---

## 4. Cập nhật code trên server (sau khi push)

```bash
cd /var/www/casino-portal
git pull origin main
php database/migrate.php
chown -R www-data:www-data storage
```

---

## 5. Bảo mật cần làm trước khi lên production

- [ ] `APP_DEBUG=false`, `APP_ENV=production` trong `.env`
- [ ] `APP_KEY` đã sinh ngẫu nhiên (không để trống)
- [ ] Đổi mật khẩu admin mặc định
- [ ] Xóa `public/install.php` sau khi cài xong
- [ ] `.env` có quyền `600`, thuộc user web server
- [ ] Đã chặn truy cập trực tiếp `app/ config/ database/ storage/`
- [ ] Đã bật HTTPS
- [ ] Không push `.env` lên GitHub (đã chặn trong `.gitignore`)
- [ ] Các API key trong `settings` của DB cũng cần đổi nếu đã lộ

---

## 6. Kiểm tra sau khi deploy

```bash
curl -I https://ten-domain-cua-ban.com/
curl -I https://ten-domain-cua-ban.com/admin
php -l app/Controllers/HomeController.php   # lint từng file nếu nghi lỗi
```
