<div align="center">

# 🎰 Casino Portal — Nền tảng tổng hợp sảnh game & dịch vụ số

### Kiến trúc PHP MVC tự thân · Tích hợp 12+ API đối tác · Quản trị 30+ module

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![GSAP](https://img.shields.io/badge/GSAP-3.12-0AE4F4?logo=greensock&logoColor=white)](https://gsap.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

[**Xem Demo trực tiếp →**](https://transangca.github.io/casino-portal/)
[**Tài liệu API →**](#-tài-liệu-api)
[**Hướng dẫn Deploy →**](docs/DEPLOY.md)

</div>

---

## 📌 Giới thiệu

**Casino Portal** là một **nền tảng tổng hợp** xây dựng trên kiến trúc **PHP MVC tự thân** (không dùng framework), được thiết kế để vận hành cùng lúc:

- **Sảnh game** từ nhiều nhà phát hành (PG SOFT, JILI, CQ9, Pragmatic Play, Deno Casino…) với **đồng bộ ví 2 chiều**
- **Chợ tài nguyên** & hệ thống **gói chơi / VIP 60 cấp**
- **39 dịch vụ số** (ngân hàng ảo, VPS, Auto MXH, SMM, CV, thiết kế AI, tạo website tự động…)
- **Trang quản trị** đầy đủ 30+ module: nạp/rút, game, user, CSO, chiến dịch, bảo mật…
- **API đối tác** chuẩn Seamless Wallet (Operator API) + public API có tài liệu OpenAPI

> 🎯 Dự án nhằm mục tiêu thể hiện năng lực **full-stack**: thiết kế kiến trúc, lập trình backend PHP, tích hợp API bên thứ ba, xây dựng UI/UX hiện đại, và triển khai hệ thống production.

---

## 🏗 Kiến trúc hệ thống

```
casino-portal/
├── app/
│   ├── Controllers/     # 40+ Controller (phân tách theo domain)
│   ├── Core/            # Router, Database (PDO singleton), View, Middleware
│   ├── Models/          # Model + truy vấn SQL
│   ├── Services/        # 56 Service — logic nghiệp vụ, tích hợp API
│   │   ├── Provider/    # Adapter sảnh game (PgSoft, DenoCasino, KingMidas…)
│   │   └── Ws/         # WebSocket client (đồng bộ sự kiện)
│   ├── Support/        # Helper: auth, CSRF, asset, brand_manifest, i18n
│   └── Views/          # 25 nhóm view template (layouts, partials, admin, auth…)
├── config/              # app.php, providers_registry.php, .htaccess
├── database/            # schema.sql + 18 migration + seed + cron jobs
├── public/              # DOCUMENT ROOT — index.php (front controller)
│   ├── assets/          # CSS/JS/IMG/FONTS (851 ảnh, 216 game, PG SOFT showcase)
│   └── uploads/         # file người dùng (avatar, CV, ảnh thiết kế)
├── storage/             # logs, cache, upload, backup, manifest provider
├── tests/               # 27 test script (unit + HTTP + E2E)
├── tools/               # script chụp ảnh demo tự động
└── docs/                # DEPLOY.md + screenshots/
```

### Nguyên tắc thiết kế

| Nguyên tắc | Thực hiện |
|---|---|
| **Separation of Concerns** | Controller → Service → Model, không lẫn logic vào View |
| **Single Source of Truth** | `Database::conn()` là PDO singleton duy nhất, có transaction wrapper |
| **Config qua `.env`** | 24 biến môi trường, `.env` không bao giờ commit (đã có `.gitignore`) |
| **Bảo mật mặc định** | CSRF token, prepared statement, hash password (`PASSWORD_DEFAULT`), role-based middleware |
| **Đa ngôn ngữ** | `I18n` service hỗ trợ 21 locale, cờ & font riêng từng locale |
| **Theme động** | Light/Dark/System, lưu bằng cookie + `settings` table |

---

## ✨ Tính năng nổi bật

### 🎮 Sảnh game & đồng bộ ví

- **Showcase PG SOFT 1:1** — 9 section dựng lại từ `pgsoft.com`: Slogan, Data (globe + 8 card), Features (stepper 5 bước + GSAP ScrollTrigger), Licensing, Back Office (Lottie), About (6 card tương tác), Partners (24 logo), Vision, Gallery
- **Đồng bộ ví 2 chiều** qua **Operator API** chuẩn Seamless Wallet: `verifySession`, `wallet/get`, `wallet/change`, `game/info`, `game/list` (+ alias `Cash/Get`, `Cash/Change`)
- **Adapter sảnh** (`app/Services/Provider/`) — thêm nhà phát hành mới chỉ cần 1 class implement `ProviderInterface`
- Đồng bộ game tự động theo lịch cron, cache manifest JSON, fallback sang API gốc

### 🏦 Dịch vụ tài chính

- **Ví điện tử** — số dư, lịch sử giao dịch, chuyển tiền nội bộ
- **Nạp tiền** — VietQR (QR ngân hàng) + thẻ cào, tự động cộng
- **Rút tiền** — duyệt nhanh 24/7, đối soát tự động
- **Bank ảo** — thuê tài khoản ngân hàng ảo, logo chính thức từng ngân hàng
- **Auto Bank** — quét giao dịch tự động qua `api.sieuthicode.net`, cron mỗi 2 phút
- **P2P** — sàn mua bán tiền & tài khoản, có hệ thống dispute

### 🤖 Tích hợp API đối tác (12+)

| Dịch vụ | API | Endpoint |
|---|---|---|
| HeyBank | Bank ảo v2 | `HEYBANK_BASE_URL` |
| GenzShop | Tài khoản AI | `GENZSHOP_BASE_URL` |
| ViOTP | Thuê số OTP | `VIOTP_BASE_URL` |
| AutoBank | Thanh toán tự động | `AUTOBANK_BASE_URL` |
| TrungTamMMO | Auto MXH (mở khoá) | `AUTOMXH_BASE_URL` |
| TrungTamMMO | SMM (like/view/comment) | `SMM_BASE_URL` |
| XVPS Agency | VPS VMware | `api.xvps.vn` |
| XVPS Virtualizor | VPS StoreVPS | `api.xvps.vn/api/virt/*` |
| TMDB | Kho phim | `media_items` |
| GenzShop / Vuavia | Tài khoản & nick | `/api/external/*` |

> 🔒 **Bảo mật API**: proxy route `/api/external/{smm,automxh}` chỉ chấp nhận `X-API-Key` riêng của từng user, **không lộ khóa đối tác** ra ngoài.

### 🛠 Công cụ & tiện ích

- **Bộ công cụ** — Hash, JSON formatter, Password generator, Convert, UUID, Base64, IP info, Whois, SEO check, Speed test
- **Tạo CV** — chọn mẫu, nhập thông tin, thêm avatar, xuất & xem trước
- **Thiết kế ảnh AI** — tạo poster/banner từ prompt
- **Tạo website A-Z** — tự động dựng site hoàn chỉnh từ mô tả
- **Copy 1-1 website** — quét thật + tải asset về ZIP
- **Đục UI/UX** — quét giao diện casino, trích xuất asset
- **Check SĐT** — tra tên/CCCD/địa chỉ/Facebook theo số điện thoại (single + batch)
- **Chiến dịch SMS** — gửi hàng loạt qua cron nền, giới hạn tốc độ
- **POS & Kế toán** — sổ sách, báo cáo, hóa đơn, sao lưu (fullscreen MISA-style)
- **Kho proxy** — đăng ký / mua proxy, mã ưu đãi voucher

### 🛡 Bảo mật & vận hành

- Role-based access control với **5 vai trò** (Super Admin, Quản trị, Hỗ trợ, Tài chính, Kỹ thuật) — mỗi role có danh sách permission riêng
- 2FA (TOTP), đăng nhập social (Google/Facebook/Telegram), blacklist IP, rate limit, audit log
- CSRF token, prepared statement, session hardening
- **131 bảng CSDL**, migration có kiểm tra idempotent

---

## 📸 Ảnh chụp màn hình

<details>
<summary><b>Xem tất cả 57 ảnh (trang khách + trang quản trị)</b></summary>

### Trang khách

| | |
|---|---|
| ![Trang chủ](docs/screenshots/01-trang-chu.png) | ![Kho game](docs/screenshots/02-kho-game.png) |
| **Trang chủ — Showcase PG SOFT 1:1** | **Kho game — 374 game từ 30+ sảnh** |
| ![Sảnh PG SOFT](docs/screenshots/05-sanh-pgsoft.png) | ![Deno Casino](docs/screenshots/06-sanh-deno-casino.png) |
| **Sảnh PG SOFT** | **Sảnh Deno Casino** |
| ![Diễn đàn](docs/screenshots/08-dien-dan.png) | ![Xem phim](docs/screenshots/09-xem-phim.png) |
| **Diễn đàn cộng đồng** | **Kho phim — TMDB API** |
| ![Tạo CV](docs/screenshots/10-tao-cv.png) | ![Thiết kế AI](docs/screenshots/11-thiet-ke-anh.png) |
| **Tạo CV online** | **Thiết kế ảnh bằng AI** |
| ![Tạo website](docs/screenshots/12-tao-website.png) | ![Kho proxy](docs/screenshots/13-kho-proxy.png) |
| **Tạo website A-Z** | **Kho proxy** |
| ![VPS VMware](docs/screenshots/14-vps-vmware.png) | ![VPS StoreVPS](docs/screenshots/15-vps-storevps.png) |
| **VPS VMware (XVPS)** | **VPS StoreVPS / Virtualizor** |
| ![Mua domain](docs/screenshots/16-mua-domain-hosting.png) | ![POS & Kế toán](docs/screenshots/17-pos-ke-toan.png) |
| **Domain / Hosting / VPS** | **POS & phần mềm kế toán** |
| ![Tra cứu ngân hàng](docs/screenshots/18-tra-cuu-ngan-hang.png) | ![Công cụ tiện ích](docs/screenshots/07-cong-cu-tien-ich.png) |
| **Tra cứu VietQR** | **Bộ công cụ tiện ích** |
| ![API docs](docs/screenshots/19-api-docs.png) | ![Đăng ký](docs/screenshots/22-dang-ky.png) |
| **Tài liệu API đối tác** | **Đăng ký có mã giới thiệu** |

### Trang quản trị

| | |
|---|---|
| ![Admin dashboard](docs/screenshots/26-admin-dashboard.png) | ![Admin users](docs/screenshots/27-admin-users.png) |
| **Dashboard** | **Quản lý người dùng** |
| ![Admin games](docs/screenshots/28-admin-games.png) | ![Admin deposits](docs/screenshots/29-admin-deposits.png) |
| **Quản lý game** | **Duyệt nạp tiền** |
| ![Admin banks](docs/screenshots/32-admin-banks.png) | ![Admin virtual bank](docs/screenshots/33-admin-virtual-bank.png) |
| **Ngân hàng VietQR** | **Bank ảo** |
| ![Admin AI accounts](docs/screenshots/34-admin-ai-accounts.png) | ![Admin OTP](docs/screenshots/36-admin-otp.png) |
| **Tài khoản AI** | **Thuê số OTP** |
| ![Admin AutoMXH](docs/screenshots/38-admin-automxh.png) | ![Admin SMM](docs/screenshots/39-admin-smm.png) |
| **Auto MXH** | **SMM** |
| ![Admin VPS](docs/screenshots/40-admin-vps.png) | ![Admin settings](docs/screenshots/47-admin-settings.png) |
| **VPS VMware** | **Cấu hình hệ thống** |

</details>

> 📸 Toàn bộ ảnh được tự động chụp bằng script [`tools/capture-screenshots.js`](tools/capture-screenshots.js) (Chrome headless + CDP).
> Chạy lại: `node tools/capture-screenshots.js`

---

## 🔌 Tài liệu API

### Operator API (chuẩn Seamless Wallet — game server gọi)

```http
POST /operator/api/verifySession
POST /operator/api/wallet/get
POST /operator/api/wallet/change
POST /operator/api/game/info
GET  /operator/api/game/list
```

Alias tương thích PG SOFT: `Cash/Get`, `Cash/Change`, `wallet/balance`, `balance`, `transaction`, `bet`, `win`.

### Public API cho đối tác

| Endpoint | Mô tả |
|---|---|
| `GET /api/docs` | Tài liệu API giao diện |
| `GET /api/spec` | OpenAPI specification (JSON) |
| `*  /api/{action}` | Xem số dư, giao dịch, đặt lệnh… (Header `X-API-Key`) |
| `*  /api/external/smm/*` | Proxy API SMM (key riêng user) |
| `*  /api/external/automxh/*` | Proxy API Auto MXH (key riêng user) |

### Ví dụ

```bash
curl -H "X-API-Key: YOUR_KEY" https://your-domain.com/api/balance
```

```json
{
  "success": true,
  "data": { "balance": 1250000, "currency": "VND" }
}
```

---

## 🧪 Testing

```bash
# Unit / logic
php tests/test_sale_logic.php
php tests/test_vps_logic.php
php tests/test_brand_lookup.php

# HTTP integration (cần server chạy ở :8000)
php tests/test_pages_http.php
php tests/test_api_accounts_http.php
php tests/test_vuavia_http.php

# E2E
php tests/test_vps_e2e.php
php tests/test_vps_vz_e2e.php
```

---

## 🛠 Cài đặt & chạy local

### Yêu cầu

- PHP **8.1+** (`pdo_mysql`, `mbstring`, `curl`, `gd`, `zip`, `intl`)
- MySQL **8.0+** / MariaDB 10.6+
- Node.js 18+ *(chỉ để chụy ảnh demo)*

### Các bước

```bash
# 1. Cấu hình
cp .env.example .env
# sửa DB_HOST, DB_NAME, DB_USER, DB_PASS, APP_URL

# 2. Tạo database
mysql -u root -p -e "CREATE DATABASE casino_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Migration + seed
php database/migrate.php
php database/seed.php
php database/admin_account.php create admin admin@localhost YourPassword123

# 4. Chạy server
php -S 127.0.0.1:8000 -t public public/router.php
```

Mở trình duyệt: **http://127.0.0.1:8000**

> 📖 Hướng dẫn deploy production đầy đủ (Nginx + PHP-FPM + HTTPS + cron + checklist bảo mật): **[docs/DEPLOY.md](docs/DEPLOY.md)**

---

## 🧰 Công nghệ sử dụng

**Backend**
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white) ![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white) ![PDO](https://img.shields.io/badge/PDO-singleton-777BB4)

**Frontend**
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white) ![GSAP](https://img.shields.io/badge/GSAP-3.12-0AE4F4?logo=greensock&logoColor=white) ![ScrollTrigger](https://img.shields.io/badge/ScrollTrigger-3.12-0AE4F4) ![Lottie](https://img.shields.io/badge/Lottie-5.12-2A9D8F) ![FontAwesome](https://img.shields.io/badge/FontAwesome-6.5-339AF0?logo=fontawesome&logoColor=white)

**DevOps**
![Nginx](https://img.shields.io/badge/Nginx-1.24-009639?logo=nginx&logoColor=white) ![Git](https://img.shields.io/badge/Git-F05032?logo=git&logoColor=white) ![GitHub Actions](https://img.shields.io/badge/GitHub_Actions-2088FF?logo=githubactions&logoColor=white) ![Chrome Headless](https://img.shields.io/badge/Chrome-Headless-4285F4?logo=googlechrome&logoColor=white)

---

## 📊 Quy mô dự án

| Hạng mục | Số lượng |
|---|---|
| PHP class / file | **276** file trong `app/` |
| Controllers | **40+** |
| Services | **56** |
| Database tables | **131** |
| Migration | **18** phiên bản |
| Test scripts | **27** |
| Hình ảnh | **851** (216 game, 212 PG SOFT, 187 brand) |
| Video | 5 (MP4) + 2 Lottie animation |
| Font | 10 file (DIN Condensed, PG Web Slogan, Kanit, Inter) |
| Tổng route | **190+** |
| Dịch vụ tích hợp | **12+ API đối tác** |

---

## 🔐 Bảo mật

- [x] `.env` **không** commit lên GitHub (`.gitignore` + `.env.example` mẫu)
- [x] Password hash bằng `PASSWORD_DEFAULT` (bcrypt)
- [x] CSRF token cho mọi form POST
- [x] Prepared statement — không nối chuỗi SQL
- [x] Role-based middleware (5 role, permission riêng)
- [x] 2FA TOTP, IP blacklist, rate limit, audit log
- [x] Khóa API đối tác **không** lộ ra client — proxy qua `/api/external/*`
- [x] Nginx chặn truy cập trực tiếp `app/ config/ database/ storage/`

---

## 📝 Giấy phép

MIT License — xem [LICENSE](LICENSE).

---

## 👤 Tác giả

**transangca** — Full-stack Developer
[![GitHub](https://img.shields.io/badge/GitHub-transangca-181717?logo=github&logoColor=white)](https://github.com/transangca)

> Nếu bạn thấy dự án này hữu ích, hãy ⭐ **Star** repo để ủng hộ! Nếu cần trao đổi về cơ hội việc, vui lòng liên hệ qua GitHub.
