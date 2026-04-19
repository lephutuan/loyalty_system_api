# Loyalty System API (Symfony + API Platform + MySQL)

Tài liệu này hướng dẫn chạy dự án theo đúng yêu cầu bài tập cuối tuần Hệ thống Ví Điểm (Loyalty System), gồm:

- Thiết kế CSDL quan hệ có log biến động điểm
- 3 API bắt buộc: tích điểm, đổi quà, truy vấn ví
- Đảm bảo ACID transaction và xử lý race condition khi đổi quà

## 1. Công nghệ sử dụng

- PHP 8.3
- Symfony 7.2
- API Platform 3
- Doctrine ORM + Doctrine Migrations
- MySQL 8
- Docker Compose
- phpMyAdmin

## 2. Cấu trúc dữ liệu theo yêu cầu đề bài

Các bảng đã được định nghĩa theo đúng naming plural:

- members
- wallets
- transactions
- points
- gifts
- redemptions

Ý nghĩa chính:

- wallets.balance là số dư hiện tại
- points là bảng ledger/audit trail của mọi biến động điểm (+/-)
- Tính nhất quán cần luôn đúng:
  - wallets.balance = SUM(points.point_amount) theo từng ví

## 3. API bắt buộc đã thiết kế

Base URL:

- http://localhost:8000/api/v1

### 3.1. API nhận giao dịch và cộng điểm

- Method: POST
- Endpoint: /transactions
- Request body:

```json
{
  "member_id": 1,
  "amount": "100000.00"
}
```

Logic:

1. Validate amount > 0
2. Tạo transactions
3. Tính điểm theo công thức mặc định 1%
4. Ghi log points với point_amount dương
5. Cập nhật wallets.balance
6. Toàn bộ chạy trong 1 database transaction

### 3.2. API đổi quà

- Method: POST
- Endpoint: /redemptions
- Request body:

```json
{
  "member_id": 1,
  "gift_id": 1
}
```

Logic:

1. Kiểm tra quà tồn tại, active, stock > 0
2. Kiểm tra ví đủ điểm
3. Lock pessimistic trên wallet và gift
4. Giảm stock quà
5. Tạo redemptions
6. Ghi points âm
7. Cập nhật wallets.balance
8. Toàn bộ chạy trong 1 database transaction

### 3.3. API truy vấn ví

- Method: GET
- Endpoint: /members/{member_id}/wallet
- Trả về:
  - Thông tin member
  - balance hiện tại
  - 10 bản ghi points gần nhất

## 4. Hướng dẫn chạy bằng Docker

Yêu cầu:

- Docker Desktop đang chạy
- Port trống: 8000, 3306, 8080

Bước 0: Tạo file env local từ mẫu

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Bạn có thể chỉnh các giá trị `MYSQL_*`, `DATABASE_URL` trong `.env` theo môi trường local trước khi chạy container.

Lưu ý: container app được cấu hình chạy ở `prod` để giảm đáng kể thời gian phản hồi API. Nếu cần debug/dev mode, hãy override `APP_ENV=dev` khi khởi chạy compose.

`vendor` và `var` được tách sang Docker volumes riêng để tránh chậm do bind mount trên Windows.

Bước 1: Build và khởi động container

```bash
docker compose up -d --build
```

Bước 2: Cài dependency PHP trong container app

```bash
docker compose exec app composer install
```

Bước 3: Chạy migration tạo schema

```bash
docker compose exec app php bin/console doctrine:migrations:migrate -n
```

Bước 3.1: Seed dữ liệu mẫu đầy đủ cho tất cả bảng

```bash
docker compose exec app php bin/console app:seed:loyalty --reset
```

Lệnh này sẽ nạp dữ liệu mẫu cho `members`, `wallets`, `transactions`, `points`, `gifts`, `redemptions` để test API ngay, đặc biệt endpoint đổi quà.

Bước 4: Kiểm tra API docs

- Swagger/OpenAPI UI: http://localhost:8000/api/v1/docs
- JSON OpenAPI: http://localhost:8000/api/v1/docs.jsonopenapi

Bước 5: Truy cập phpMyAdmin

- URL: http://localhost:8080
- Host: theo biến `PMA_HOST` trong `.env` (mặc định `mysql`)
- User: theo `MYSQL_USER` trong `.env`
- Password: theo `MYSQL_PASSWORD` trong `.env`
- DB: theo `MYSQL_DATABASE` trong `.env`

## 5. Lệnh kiểm thử nhanh API

### 5.1. Tạo giao dịch tích điểm

```bash
curl -X POST "http://localhost:8000/api/v1/transactions" \
  -H "Content-Type: application/json" \
  -d '{"member_id":1,"amount":"100000.00"}'
```

### 5.2. Đổi quà

```bash
curl -X POST "http://localhost:8000/api/v1/redemptions" \
  -H "Content-Type: application/json" \
  -d '{"member_id":1,"gift_id":1}'
```

### 5.3. Truy vấn ví

```bash
curl "http://localhost:8000/api/v1/members/1/wallet"
```

## 6. Kiểm tra tính nhất quán dữ liệu

Chạy SQL sau trong MySQL để đối soát balance và tổng ledger:

```sql
SELECT
  w.id AS wallet_id,
  w.balance AS wallet_balance,
  COALESCE(SUM(p.point_amount), 0) AS ledger_balance
FROM wallets w
LEFT JOIN points p ON p.wallet_id = w.id
GROUP BY w.id, w.balance
HAVING w.balance <> COALESCE(SUM(p.point_amount), 0);
```

Kết quả đúng mong đợi: không có dòng nào trả về.

## 7. Quality gates theo checklist

### 7.1. Unit/Integration test

```bash
docker compose exec app vendor/bin/phpunit
```

### 7.2. PHPStan level 9

```bash
docker compose exec app vendor/bin/phpstan analyse src tests --level=9
```

### 7.3. Security audit dependency

```bash
docker compose exec app composer audit
```

### 7.4. PSR-12 coding style

Dự án đang tuân thủ style theo convention Symfony. Nếu muốn enforce tự động PSR-12, có thể thêm PHP-CS-Fixer hoặc PHP_CodeSniffer.

Ví dụ với PHP-CS-Fixer:

```bash
docker compose exec app composer require --dev friendsofphp/php-cs-fixer
```

```bash
docker compose exec app vendor/bin/php-cs-fixer fix src tests --dry-run --diff
```

### 7.5. Coverage > 80%

```bash
docker compose exec app vendor/bin/phpunit --coverage-text
```

Lưu ý: cần bật Xdebug hoặc PCOV trong image PHP để đo coverage.

## 8. Kiểm thử race condition đổi quà

Mục tiêu: với món quà còn stock = 1, gửi 2 request redeem gần như đồng thời, chỉ 1 request thành công.

Bạn có thể mở 2 terminal và chạy cùng lúc lệnh redeem ở mục 5.2, hoặc dùng công cụ load test.

Kỳ vọng:

- 1 request thành công
- request còn lại lỗi do out of stock hoặc insufficient points (tùy thứ tự lock)
- Không xuất hiện stock âm
- Không xuất hiện balance âm

## 9. Cấu trúc thư mục chính

- src/Entity: mô hình domain
- src/State/Processor: xử lý nghiệp vụ write (transaction/redeem)
- src/State/Provider: xử lý read wallet inquiry
- src/Dto: input/output API
- migrations: database migration
- tests/Integration: integration test

## 10. Ghi chú triển khai

- Công thức tính điểm mặc định: Point = Amount x 1%
- Sử dụng decimal string + BCMath để tránh sai số float
- Redeem dùng pessimistic lock để giảm race condition
- Mọi write quan trọng chạy trong transaction để đảm bảo all-or-nothing
