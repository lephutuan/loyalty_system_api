# Race Condition & Hybrid Locking - Tổng Hợp Nâng Cấp

## 1) Mục tiêu nâng cấp

- Ngăn hiện tượng oversell khi redeem quà dưới tải đồng thời.
- Bảo toàn tính nhất quán dữ liệu: không âm `stock`, không âm `balance`, và `wallets.balance = SUM(points.point_amount)`.
- Áp dụng Hybrid Locking để vừa khóa chặt luồng ghi quan trọng, vừa phát hiện lost update ngoài ý muốn.

## 2) Các phần tạo mới

| Hạng mục                  | Tệp                                      | Mục đích                                                                           |
| ------------------------- | ---------------------------------------- | ---------------------------------------------------------------------------------- |
| Migration versioning      | `migrations/Version20260422000100.php`   | Thêm cột `version` cho `wallets`, `gifts` để hỗ trợ optimistic locking             |
| NodeJS race/load script   | `scripts/race-load-test.js`              | Giả lập request đồng thời (`redeem-race`, `redeem-race-loop`, `transaction-burst`) |
| PowerShell race suite     | `scripts/run-race-suite.ps1`             | Chạy full suite: migrate + race loop + burst + SQL invariant checks                |
| Seed dữ liệu race fixture | `src/Command/SeedLoyaltyDataCommand.php` | Tạo quà `Race Gift - Single Stock` (stock=1) để tái lập race condition             |

## 3) Các phần thay đổi chính

| Thành phần                | Tệp                                                  | Thay đổi                                                                                                                             |
| ------------------------- | ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Optimistic locking entity | `src/Entity/Wallet.php`                              | Thêm `#[ORM\Version]` với field `version`                                                                                            |
| Optimistic locking entity | `src/Entity/Gift.php`                                | Thêm `#[ORM\Version]` với field `version`                                                                                            |
| Processor redeem          | `src/State/Processor/CreateRedemptionProcessor.php`  | Refetch bên trong transaction bằng `PESSIMISTIC_WRITE`, kiểm tra/ghi trên locked instance, map `OptimisticLockException` -> HTTP 409 |
| Processor transaction     | `src/State/Processor/CreateTransactionProcessor.php` | Refetch wallet bên trong transaction bằng `PESSIMISTIC_WRITE`, map `OptimisticLockException` -> HTTP 409                             |
| Postman assertion         | `postman/Loyalty-System-API.postman_collection.json` | Cho phép kết quả race hợp lệ `201` hoặc `409`                                                                                        |
| Tài liệu vận hành         | `README.md`                                          | Bổ sung hướng dẫn chạy race/load tests và race suite                                                                                 |
| Composer scripts          | `composer.json`                                      | Thêm `race:redeem`, `race:redeem:loop`, `race:transactions`                                                                          |

## 4) Mô hình Hybrid Locking đang áp dụng

### 4.1 Pessimistic Locking (khóa bi quan)

- Dùng `LockMode::PESSIMISTIC_WRITE` để khóa row `wallet` và `gift` trong transaction.
- Thực hiện check `stock`/`balance` và update ngay trên bản ghi đã lock.

### 4.2 Optimistic Locking (khóa lạc quan)

- Dùng cột `version` ở mức ORM.
- Nếu có cập nhật cạnh tranh ngoài luồng dự kiến, Doctrine ném `OptimisticLockException`.
- API map về `409 Conflict` với thông điệp retry-friendly.

=> Kết hợp hai cơ chế giúp giảm race ở runtime chính và tăng độ an toàn khi có code path khác vô tình cập nhật cùng dữ liệu.

## 5) Cách kiểm tra nâng cấp đang hoạt động

## Bước 1: Build và chạy dịch vụ

```bash
docker compose up -d --build
```

## Bước 2: Migrate schema mới

```bash
docker compose exec app php bin/console doctrine:migrations:migrate -n
```

Kỳ vọng:

- Có apply migration `Version20260422000100` thành công.

## Bước 3: Seed dữ liệu race fixture

```bash
docker compose exec app php bin/console app:seed:loyalty --reset
```

Kỳ vọng:

- Có dòng `Race Gift - Single Stock (stock: 1)`.

## Bước 4: Chạy race test nhanh (2 request đồng thời)

```bash
node scripts/race-load-test.js redeem-race
```

Kỳ vọng pass:

- Status summary có đúng `201: 1` và `409: 1`.

## Bước 5: Chạy race suite chuyên sâu

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run-race-suite.ps1 -Rounds 3
```

Kỳ vọng pass:

- Race loop pass đủ số vòng (ví dụ `3/3`).
- Transaction burst không có request lỗi.
- Invariant check:
  - `ledger_mismatch_count=0`
  - `negative_stock_count=0`
  - `negative_balance_count=0`

## 6) Câu lệnh SQL hậu kiểm thủ công

```sql
SELECT COUNT(*) AS ledger_mismatch_count
FROM (
	SELECT w.id
	FROM wallets w
	LEFT JOIN points p ON p.wallet_id = w.id
	GROUP BY w.id, w.balance
	HAVING w.balance <> COALESCE(SUM(p.point_amount), 0)
) t;

SELECT COUNT(*) AS negative_stock_count
FROM gifts
WHERE stock < 0;

SELECT COUNT(*) AS negative_balance_count
FROM wallets
WHERE balance < 0;
```

Kỳ vọng: tất cả đều bằng `0`.

## 7) Kết quả thực thi gần nhất

- `redeem-race-loop`: pass nhiều vòng với mẫu đúng `1x201 + 1x409` mỗi vòng.
- `transaction-burst`: toàn bộ request thành công (không lỗi bất thường).
- Invariant dữ liệu: không phát hiện lệch ledger/balance, không phát hiện số âm.

## 8) Ghi chú vận hành

- Nếu test `redeem-race` ra `2 x 201`, cần kiểm tra:
  - Đã dùng đúng branch/code mới chưa.
  - Đã restart app container sau khi sửa processor chưa.
  - Đã reseed dữ liệu để `Race Gift` về stock=1 chưa.
  - `BASE_URL`, `GIFT_ID`, `MEMBER_ID` có đúng với dữ liệu seed không.
