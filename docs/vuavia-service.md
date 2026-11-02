# Dịch vụ Tài khoản & Nick (Vuavia) — Hướng dẫn cấu hình

## 1. Tổng quan

Dịch vụ bán tài khoản / nick / clone tài khoản mạng xã hội, giao hàng tự động.
Dữ liệu đồng bộ từ API đối tác qua header `X-Api-Key`, khách mua trong hệ thống của bạn.

> **Bảo mật:** toàn bộ thông tin đối tác (base URL, API key, tên nhà cung cấp) chỉ hiển thị
> trong khu vực quản trị. Phía khách chỉ thấy tên dịch vụ + giá bán.

## 2. Lấy API key

1. Đăng nhập tài khoản tại `vuavia.io`
2. Vào **Tài khoản → Thông tin cá nhân**, nhấn **Tạo API Key**
3. Key có dạng `vav_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx` (36 ký tự)
4. Lưu lại key — chỉ hiển thị 1 lần. Nếu bị lộ → tạo lại key ngay (key cũ tự vô hiệu hóa)

## 3. Cấu hình trong hệ thống

Vào **Quản trị → Tài khoản & Nick (Vuavia)**:

| Trường | Ý nghĩa |
|---|---|
| Base URL | Mặc định `https://api.vuavia.io/api/v2` (bắt buộc HTTPS) |
| API Key | Key `vav_...` — được mã hoá AES-256-GCM trước khi lưu DB |
| Biên lợi nhuận (%) | Giá bán = giá đối tác × (1 + biên lợi nhuận). `0` = bán ngang giá |
| Bật dịch vụ | Tắt → khách không thấy & không mua được |
| Tự đồng bộ | Tự kéo danh mục sản phẩm mỗi 5 phút |

Sau khi lưu → bấm **Đồng bộ** để tải danh mục về.

## 4. Luồng mua hàng

```
Khách bấm Mua
  → Kiểm tra tồn kho (hết hàng → chặn, KHÔNG trừ tiền)
  → Kiểm tra số dư ví nội bộ
  → Trừ tiền (giá đối tác + markup) — ghi ledger
  → Gọi POST /orders của đối tác
      ├─ AUTO   → đối tác giao ngay → lưu hàng → khách thấy trong đơn
      └─ MANUAL → đối tác chờ giao → Admin giao hàng thủ công
  └─ Nếu API lỗi → HOÀN TIỀN 100% + ghi đơn FAILED
```

Mật khẩu trong đơn được mã hoá AES-256-GCM, chỉ hiện khi khách mở đơn.

## 5. Giao hàng thủ công (đơn MANUAL)

**Quản trị → Tài khoản & Nick → Giao hàng thủ công**:

- Mã đơn: `id` của đơn trong bảng `vuavia_orders`
- Danh sách hàng: mỗi dòng 1 hàng, định dạng `tai_khoan|mat_khau`

```
acc_vi_01|pass_xyz
acc_vi_02|pass_abc
```

## 6. API của hệ thống (dành cho đối tác bên ngoài)

Xác thực: header `X-API-Key: pk_live_...` (key do hệ thống cấp ở `/api-keys`).

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/accounts/products` | Danh sách sản phẩm (`q`, `categoryId`, `page`, `size`, `sort`) |
| POST | `/api/accounts/order` | Mua hàng `{ productId, quantity }` |
| GET | `/api/accounts/orders` | Lịch sử đơn (`limit`) |
| GET | `/api/accounts/detail?id=` | Chi tiết đơn + hàng đã giao |

```bash
curl -s "http://your-site/api/accounts/products?size=10" \
  -H "X-API-Key: pk_live_xxx_yyy"

curl -s -X POST http://your-site/api/accounts/order \
  -H "X-API-Key: pk_live_xxx_yyy" \
  -H "Content-Type: application/json" \
  -d '{"productId": 8, "quantity": 1}'
```

Mã lỗi: `INSUFFICIENT_BALANCE` (402), `OUT_OF_STOCK` (409), `PRODUCT_NOT_FOUND` (404),
`INVALID_PARAM` (422), `AUTH_INVALID` (401).

## 7. Bảng dữ liệu

| Bảng | Mô tả |
|---|---|
| `vuavia_products` | Danh mục sản phẩm đồng bộ (product_id, name, price, stock_count, delivery_type) |
| `vuavia_categories` | Danh mục để lọc |
| `vuavia_orders` | Đơn hàng (user_id, product_id, quantity, total_amount, status) |
| `vuavia_deliveries` | Hàng đã giao (account, password_enc đã mã hoá) |

## 8. Chạy migration

```bash
php database/migrate_v17.php
```

## 9. Kiểm thử

```bash
php -S 127.0.0.1:8921 tests/mock_vuavia.php   # mock API đối tác
php -S 127.0.0.1:8912 -t public                # web server

php tests/test_vuavia_logic.php       # 33 test - logic + mã hoá
php tests/test_vuavia_http.php        # 25 test - luồng web đầy đủ
php tests/test_api_accounts_http.php  # 27 test - public API
```
