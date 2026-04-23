# Loyalty System API - Hướng dẫn đọc source cho người mới

Tài liệu này dành cho người chưa biết gì về Symfony và API Platform. Mục tiêu là giúp bạn hiểu project đang làm gì, request đi qua những lớp nào, và nên đọc source theo thứ tự nào để đỡ bị rối.

## 1. Project này là gì

Đây là một backend API quản lý hệ thống tích điểm và đổi quà cho thành viên.

Người dùng của hệ thống có một ví điểm. Mỗi lần phát sinh giao dịch mua hàng, hệ thống cộng điểm vào ví. Khi đổi quà, hệ thống trừ điểm, giảm stock quà, và ghi lại lịch sử vào bảng điểm để có thể audit lại sau này.

Điểm quan trọng nhất của project này là:

- Dữ liệu chính được lưu bằng Doctrine ORM.
- API được dựng bằng API Platform.
- Không đi theo kiểu controller truyền thống cho 3 API chính, mà đi qua DTO + Processor + Provider.
- Các thao tác cộng/trừ điểm đều chạy trong transaction để tránh lệch dữ liệu.

## 2. Cách hiểu nhanh kiến trúc

Nếu nhìn theo luồng xử lý, project này đi như sau:

1. Request đi vào Symfony qua `public/index.php`.
2. `src/Kernel.php` nạp cấu hình từ thư mục `config/`.
3. API Platform đọc metadata từ các class DTO có gắn `ApiResource`.
4. Request POST sẽ đi vào Processor.
5. Request GET đặc biệt sẽ đi vào Provider.
6. Processor/Provider dùng Repository và Doctrine Entity để đọc/ghi dữ liệu.
7. Kết quả được trả về bằng DTO output, không trả thẳng entity ra ngoài.

Nói ngắn gọn: API Platform là lớp routing + serialization, còn business logic nằm ở Processor/Provider.

## 3. Các file nên đọc trước

| Thứ tự | File / thư mục                                       | Vai trò                                             |
| ------ | ---------------------------------------------------- | --------------------------------------------------- |
| 1      | `README.md`                                          | Xem nhanh mục tiêu project và cách chạy bằng Docker |
| 2      | `config/routes.yaml`                                 | Biết API Platform đang được mount dưới prefix nào   |
| 3      | `src/Dto/CreateTransactionInput.php`                 | Hiểu API tạo giao dịch bắt đầu từ đâu               |
| 4      | `src/State/Processor/CreateTransactionProcessor.php` | Hiểu logic cộng điểm                                |
| 5      | `src/Dto/CreateRedemptionInput.php`                  | Hiểu API đổi quà bắt đầu từ đâu                     |
| 6      | `src/State/Processor/CreateRedemptionProcessor.php`  | Hiểu logic trừ điểm và giảm stock                   |
| 7      | `src/Dto/WalletInquiryOutput.php`                    | Hiểu API truy vấn ví                                |
| 8      | `src/State/Provider/WalletInquiryProvider.php`       | Hiểu cách lấy balance và lịch sử điểm               |
| 9      | `src/Entity/`                                        | Hiểu cấu trúc dữ liệu thật trong database           |
| 10     | `src/Repository/`                                    | Hiểu cách query dữ liệu                             |
| 11     | `src/Command/SeedLoyaltyDataCommand.php`             | Hiểu cách nạp dữ liệu mẫu                           |
| 12     | `tests/Integration/LoyaltyFlowTest.php`              | Xem các test nhỏ đã có                              |

## 4. Bản đồ thư mục chính

| Thư mục                | Ý nghĩa                                          |
| ---------------------- | ------------------------------------------------ |
| `public/`              | Điểm vào web server, có `index.php`              |
| `src/Entity/`          | Các entity Doctrine, tương ứng với bảng database |
| `src/Dto/`             | Data transfer object dùng cho input/output API   |
| `src/State/Processor/` | Xử lý request POST hoặc các thao tác ghi dữ liệu |
| `src/State/Provider/`  | Xử lý request GET hoặc truy vấn dữ liệu tùy biến |
| `src/Repository/`      | Câu query Doctrine cho từng entity               |
| `src/Service/`         | Logic nghiệp vụ nhỏ, ví dụ tính điểm             |
| `src/Command/`         | Lệnh console, ở đây có lệnh seed dữ liệu         |
| `config/`              | Cấu hình Symfony, Doctrine, API Platform         |
| `migrations/`          | Migrations tạo và cập nhật schema                |
| `tests/`               | Test tự động                                     |
| `postman/`             | Bộ sưu tập để test API bằng Postman              |

## 5. Symfony và API Platform trong project này hoạt động thế nào

### Symfony làm gì

Symfony là framework nền. Nó lo việc boot application, nạp service, routing, container, validator, serializer, và Doctrine integration.

Trong project này, Symfony được cấu hình rất gọn:

- `src/Kernel.php` import các file trong `config/`.
- `config/services.yaml` bật autowire và autoconfigure cho toàn bộ class trong `src/`.
- `config/packages/doctrine.yaml` map entity trong `src/Entity/`.

### API Platform làm gì

API Platform là lớp dựng API trên đầu Symfony. Nó đọc metadata trên class để tự sinh endpoint, serialize/deserialize JSON, validate input, và kết nối request với Processor hoặc Provider.

Điểm khác so với controller truyền thống là:

- Bạn không viết `Controller::createTransaction()` cho từng API chính.
- Bạn khai báo một DTO input có `ApiResource`.
- Bạn gắn `Post` hoặc `Get` vào DTO đó.
- API Platform tự route request đến đúng Processor hoặc Provider.

## 6. Luồng request của từng API

### 6.1. Tạo giao dịch và cộng điểm

File bắt đầu đọc:

- `src/Dto/CreateTransactionInput.php`
- `src/State/Processor/CreateTransactionProcessor.php`

Luồng xử lý:

1. Client gửi `POST /api/v1/transactions`.
2. API Platform map JSON vào `CreateTransactionInput`.
3. Symfony Validator kiểm tra `member_id` và `amount`.
4. `CreateTransactionProcessor` lấy member và wallet.
5. Hệ thống khóa wallet để tránh race condition khi ghi đồng thời.
6. Tạo `Transaction` mới với trạng thái hoàn tất.
7. Tính điểm bằng `LoyaltyPointCalculator`.
8. Cộng balance cho wallet.
9. Tạo record trong bảng `points` để lưu lịch sử.
10. Trả về `TransactionOutput`.

Ý nghĩa nghiệp vụ:

- `transactions` là log giao dịch mua hàng.
- `points` là ledger audit trail.
- `wallets.balance` là số dư hiện tại.

### 6.2. Đổi quà

File bắt đầu đọc:

- `src/Dto/CreateRedemptionInput.php`
- `src/State/Processor/CreateRedemptionProcessor.php`

Luồng xử lý:

1. Client gửi `POST /api/v1/redemptions`.
2. API Platform map JSON vào `CreateRedemptionInput`.
3. Processor tìm member và gift.
4. Hệ thống kiểm tra gift còn active và còn stock.
5. Hệ thống khóa wallet và gift trong transaction.
6. Nếu số dư không đủ, request bị từ chối.
7. Giảm stock quà.
8. Trừ điểm trong wallet.
9. Tạo `Redemption`.
10. Tạo record âm trong bảng `points`.
11. Trả về `RedemptionOutput`.

Ý nghĩa nghiệp vụ:

- `gifts.stock` phải không âm.
- `wallets.balance` không được âm.
- Mỗi lần đổi quà phải ghi lịch sử điểm âm để đối soát.

### 6.3. Truy vấn ví

File bắt đầu đọc:

- `src/Dto/WalletInquiryOutput.php`
- `src/State/Provider/WalletInquiryProvider.php`

Luồng xử lý:

1. Client gọi `GET /api/v1/members/{member_id}/wallet`.
2. Provider tìm member và wallet.
3. Query lấy 10 bản ghi điểm gần nhất của wallet đó.
4. Convert các dòng query thành `PointHistoryItem`.
5. Trả về `WalletInquiryOutput` gồm thông tin member, balance, và lịch sử điểm.

## 7. Các entity chính và ý nghĩa của chúng

### `Member`

Đại diện cho thành viên. Mỗi member có một wallet riêng.

### `Wallet`

Lưu số dư điểm hiện tại. Đây là số cần đọc nhanh khi check ví.

### `Transaction`

Lưu giao dịch phát sinh điểm. Mỗi transaction gắn với một member.

### `Gift`

Lưu quà có thể đổi. Quan trọng nhất là `pointCost`, `stock`, và `status`.

### `Redemption`

Lưu lịch sử đổi quà. Mỗi redemption gắn với member và gift.

### `Point`

Đây là bảng quan trọng nhất để audit. Nó lưu mọi biến động điểm, cả cộng và trừ.

Điểm cần nhớ:

- `pointAmount` dương nghĩa là được cộng điểm.
- `pointAmount` âm nghĩa là bị trừ điểm.
- `transaction_id` hoặc `redemption_id` cho biết điểm này sinh ra từ đâu.

## 8. Repository và service dùng để làm gì

### Repository

Repository là nơi đặt các query Doctrine rõ ràng hơn thay vì viết query trực tiếp trong Processor.

Trong project này:

- `MemberRepository::findOneWithWallet()` lấy member kèm wallet.
- `PointRepository::findLatestHistoryForWallet()` lấy lịch sử điểm gần nhất.

### Service

`src/Service/LoyaltyPointCalculator.php` là logic tính điểm.

Hiện tại công thức là 1% giá trị giao dịch.

## 9. Vì sao có transaction và lock

Phần này rất quan trọng nếu bạn mới học backend.

Khi nhiều request cùng lúc cố đổi quà hoặc cộng/trừ điểm, dữ liệu có thể bị lệch nếu không khóa bản ghi.

Project này xử lý bằng cách:

- chạy một thao tác ghi trong một database transaction,
- lock wallet bằng `PESSIMISTIC_WRITE`,
- lock gift khi redeem,
- chỉ flush sau khi dữ liệu đã hợp lệ.

Mục tiêu là tránh:

- stock âm,
- balance âm,
- ghi trùng điểm,
- dữ liệu wallet khác với tổng ledger trong `points`.

## 10. Database và migration

Schema được tạo bằng migrations trong `migrations/`.

Hai migration hiện có:

- `Version20260417000100.php` tạo toàn bộ bảng chính.
- `Version20260419000100.php` thêm index cho truy vấn lịch sử điểm theo ví và thời gian.

Nếu muốn hiểu data model, hãy đọc migration trước vì nó cho bạn bức tranh thật của database, sau đó mới quay lại entity để xem cách Doctrine map sang code.

## 11. Cấu hình quan trọng cần biết

### `config/routes.yaml`

API Platform được mount dưới prefix `/api/v1`. Điều này có nghĩa endpoint thực tế sẽ là:

- `/api/v1/transactions`
- `/api/v1/redemptions`
- `/api/v1/members/{member_id}/wallet`

### `config/packages/api_platform.yaml`

API trả về JSON và JSON-LD, đồng thời bật stateless mode.

### `config/services.yaml`

Symfony tự autowire mọi class trong `src/`, nên bạn không cần khai báo service thủ công cho từng class nghiệp vụ.

## 12. Dữ liệu mẫu và cách test nhanh

Nếu chưa có dữ liệu, dùng lệnh seed:

```bash
docker compose exec app php bin/console app:seed:loyalty --reset
```

Lệnh này tạo sẵn:

- member mẫu,
- wallet mẫu,
- transaction mẫu,
- point mẫu,
- gift mẫu,
- redemption mẫu.

Sau đó bạn có thể thử:

```bash
curl -X POST "http://localhost:8000/api/v1/transactions" \
	-H "Content-Type: application/json" \
	-d '{"member_id":1,"amount":"100000.00"}'
```

```bash
curl -X POST "http://localhost:8000/api/v1/redemptions" \
	-H "Content-Type: application/json" \
	-d '{"member_id":1,"gift_id":1}'
```

```bash
curl "http://localhost:8000/api/v1/members/1/wallet"
```

## 13. Cách đọc source nếu bạn là người mới hoàn toàn

Nếu bạn chưa biết Symfony hay API Platform, hãy đọc theo thứ tự này:

1. `README.md` để biết project làm gì và chạy thế nào.
2. `migrations/` để hiểu database trước.
3. `src/Entity/` để hiểu object nào map với bảng nào.
4. `src/Dto/` để biết API nhận và trả dữ liệu gì.
5. `src/State/Processor/` để hiểu business logic của POST.
6. `src/State/Provider/` để hiểu business logic của GET.
7. `src/Repository/` để xem các query phụ trợ.
8. `src/Service/` để xem logic dùng lại.
9. `tests/Integration/` để xem hệ thống được kiểm tra như thế nào.

Nếu bạn đọc theo thứ tự này, bạn sẽ không bị mắc kẹt ở chỗ Symfony magic quá sớm.

## 14. Tóm tắt một câu

Đây là một project Symfony + API Platform cho hệ thống ví điểm, trong đó DTO định nghĩa API, Processor/Provider xử lý nghiệp vụ, Entity/Repository xử lý dữ liệu, và transaction lock được dùng để bảo đảm cộng trừ điểm không bị lỗi khi có nhiều request đồng thời.
