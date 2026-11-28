# KẾT QUẢ KIỂM TRA & ĐÁNH GIÁ BÁO CÁO CUỐI KỲ (`Bao_cao_SOA_ck.docx`)

- **Tệp được kiểm tra:** `Bao_cao_SOA_ck.docx` (dung lượng: `3.905.285 bytes` ≈ `3,72 MB`)
- **Phạm vi đối chiếu:** Cấu trúc XML/Style/Section Word, toàn bộ `629` đoạn văn bản, `27` bảng biểu, `26` ảnh nhúng (`27` vị trí chèn ảnh), đối chiếu chéo với mã nguồn Cuối kỳ (`gateway/final/`, `services/*/final/`, `docker-compose.final.yml`, `.github/workflows/ci.yml`, `docs/diagrams/final-*.md`).

---

## 1. Thống kê tổng quan & Hình thức trình bày

| Hạng mục kiểm tra | Kết quả đo đạc thực tế | Đánh giá |
|---|---|---|
| Khổ giấy & Lề trang (5 Section) | A4 (`21,0 × 29,7 cm`); Lề Trên `3,5 cm`, Dưới `3,0 cm`, Trái `3,5 cm`, Phải `2,0 cm` (vùng in `15,5 × 23,2 cm`) | Đạt chuẩn TDTU |
| Kiểu chữ & Giãn dòng | `Times New Roman`, `Nidungvnbn` `13 pt`, giãn dòng `1,5` (`line=360`), căn đều hai bên (`both`) | Đạt chuẩn TDTU |
| Đánh số đề mục tự động (`numId=26`) | `CHƯƠNG %1.`, `%1.%2`, `%1.%2.%3`, tắt đánh số bằng `numId=0` cho Danh mục, Phụ lục, TLTK | Đạt |
| Đối soát Mục lục (TOC) | `37/37` tiêu đề khớp về cấu trúc *(còn sót backtick `` ` `` và `MỤC LỤC` dùng style `Nidungvnbn`)* | Cần rà soát |
| Đối soát Danh mục Hình vẽ (LOF) | `25/25` mục khớp chú thích *(nhưng thiếu `Hình 3-9` và `Hình 3-13`, `P#509` sai style, lẫn dấu `:` và `.`)* | **Cần sửa** |
| Đối soát Danh mục Bảng biểu (LOT) | `23/23` tiêu đề khớp *(còn backtick `` ` `` và lỗi `services//final`)* | **Cần sửa** |
| Placeholder bản nháp (`TODO`, `📸`) | `0` khung nhắc `📸 [VỊ TRÍ CHÈN HÌNH...]` / `0` từ khóa `TODO` | Đạt |
| Cú pháp Markdown thô còn sót | **`126` vị trí backtick `` ` ``** + **`4` vị trí `*...*`** *(ở TOC, LOF, LOT, Heading, Bảng, Văn xuôi, TLTK)* | **Cần sửa ngay** |
| Độ đồng nhất Ảnh chụp ↔ Văn bản | `Hình 3-5` chụp `DEGRADED` nhưng ghi `ALL_SERVICES_UP`; nhảy số mất `Hình 3-9` & `Hình 3-13`; thiếu ảnh PHPUnit/Go/k6/Newman; lệch cổng `:18080` | **Lỗi nghiêm trọng** |
| Trích dẫn Tài liệu tham khảo | **`0/11` tài liệu** (`[1]`–`[11]`) được trích dẫn trong Chương 1–4 | **Cần bổ sung** |
| Khung viền trang bìa (`pgBorders`) | `False` (chưa đóng khung 2 trang bìa) | Kiểm tra theo quy định khoa |
| Tự động cập nhật Field (`updateFields`) | Chưa bật trong `settings.xml` | Nhớ `Ctrl+A` → `F9` trước khi xuất PDF |

### Điểm mạnh nổi bật
1. **Nội dung kỹ thuật sâu & bám sát mã nguồn 7 Frameworks:** Diễn giải chính xác kiến trúc `Express 4.22.3` (`gateway/final`), `Spring Boot 3.5.16`, `Laravel 12 (v12.73.0)`, `ASP.NET Core 8 + EF Core 8`, `FastAPI 0.110.0 + Pydantic v2`, `Go 1.22 Gin v1.10.0 + GORM v1.25.12`, `Express 4 + Socket.io 4.7.5 + ioredis 5.3.2` cùng 5 MySQL 8.0 (`transaction_final_db` tách biệt hoàn toàn với giữa kỳ) và Redis 7.
2. **Làm rõ đúng trọng tâm Rubric 10.0:** Phân tích đầy đủ quy trình OTP 6 chữ số (Single-use, TTL `300s`, khóa `3` lần sai, phát kép Socket.io + SSE), xử lý đồng thời & lũy đẳng đa tầng (`KeyedLocker` + `FOR UPDATE`, `BCMath` + `lockForUpdate()`, `SHA-256` Refresh Token + `findForUpdate`, `RepeatableRead` + `IsConcurrencyToken`), `X-Request-Id` Tracing, `service_healthy`, non-root container (`USER 10001:10001`, `USER app`), CI/CD và ma trận Rubric 10.0 (`Bảng 4.1`).
3. **Đánh giá trung thực các giới hạn kỹ thuật:** Mục `2.2.6`, `3.3` và `4.2.2` thẳng thắn ghi nhận `otpStore`/RAM, hiệu ứng lặp thông báo khi có Redis loopback, `otpController.js` đọc `userId` từ `req.body`, Web PHP `:3000` chưa có UI OTP/Socket.io, 5/7 Dockerfile dùng `root`, và `gateway/final/src/openapi/asset.json` chưa liệt kê 5 API mới của Asset Final.

---

## 2. Danh sách lỗi NGHIÊM TRỌNG cần sửa ngay (Critical)

### [C1] `Hình 3-5` (`image20.png`) MÂU THUẪN TRỰC TIẾP với chú thích & lời khẳng định
- **Vị trí:** Chương 3, Mục `3.3`, `Hình 3-5` (`P#546`–`P#548`, `word/media/image20.png`).
- **Chú thích hiện tại:** ``Hình 3-5. Kết quả `GET /health/services` (`ALL_SERVICES_UP`)]`` *(còn cả backtick và dấu `]` thừa)*.
- **Nội dung ảnh thực tế:** URL gọi tới `http://localhost:18080/health/services` trả về JSON:
  - `"success": false`, `"overallStatus": "DEGRADED"`
  - `auth`: `"status": "DOWN"`, `"error": "fetch failed"`
  - `integration`: `"status": "DOWN"`, `"error": "fetch failed"`, `"latencyMs": 2636`
  - Chỉ có `transaction`, `asset`, `analytics`, `notification` ở trạng thái `"UP"`.
- **Cách khắc phục:** Khởi chạy đầy đủ cả 6 microservices ở trạng thái khỏe mạnh, gọi `GET http://localhost:8080/health/services` và chụp lại ảnh mới hiển thị `"overallStatus": "ALL_SERVICES_UP"` cho cả 6 dịch vụ.

### [C2] Nhảy số mất `Hình 3-9` và trích dẫn khống `Hình 3-13`
- **Mất `Hình 3-9`:** Số thứ tự hình trong Chương 3 và Danh mục Hình vẽ nhảy thẳng từ `Hình 3-8` (`P#556`: `Asset-Final-History-200`) sang `Hình 3-10` (`P#561`: `Giao diện Web iBanking – Dashboard`).
- **Mất `Hình 3-13`:** Đoạn tổng kết `P#566` viết:
  > *"Bốn nhóm ảnh chụp thực nghiệm Hình 3-10 → Hình 3-13 khép lại chu trình kiểm chứng từ giao diện người dùng cuối, tầng lưu trữ vật lý trên 5 MySQL 8.0 đến khả năng vận hành bền vững ở tầng log và tắt container an toàn."*
  Thực tế chỉ có **3 ảnh** (`Hình 3-10`, `3-11`, `3-12`); **không có `Hình 3-13`** và **thiếu ảnh chụp log JSON `X-Request-Id` + Graceful Shutdown (`docker compose down`)**.
- **Cách khắc phục:** Bổ sung đầy đủ các ảnh còn thiếu hoặc đánh số lại liên tục từ `Hình 3-8` → `Hình 3-9`..`Hình 3-12` (và cập nhật khớp câu `P#566` + Danh mục Hình vẽ).

### [C3] Thiếu minh chứng thực nghiệm cho PHPUnit, Go `-race`, Go Loadtest, `k6` và Newman
- **Vị trí:** Chương 3, Mục `3.3`, `Hình 3-4` (`image19.png`, `P#544`–`P#545`).
- **Hiện trạng:** Chú thích `Hình 3-4` ghi chung `Kết quả kiểm thử đơn vị & tích hợp tầng 1` và đoạn `P#545` viết *"xác nhận chất lượng logic nội bộ của cả ba dịch vụ xương sống (xác thực, giao dịch tiền tệ và đồng thời ngân hàng)..."*, nhưng ảnh chụp `image19.png` **chỉ hiển thị kết quả `mvn test` của Auth Service** (`AuthApiTest: 16`, `ApiErrorsTest: 2`, tổng `18/18 PASS`).
- **Thiếu sót:** Không có bất kỳ ảnh chụp thực nghiệm nào trong toàn bộ Chương 3 cho:
  1. Laravel PHPUnit (`php artisan test` — `CashflowTest.php`);
  2. Go Unit & Race test (`go test -race ./...` — `integration_test.go`);
  3. Go Concurrency Loadtest (`tools/loadtest` — `600` webhook, sai lệch `0` đồng);
  4. `k6` (`scripts/concurrency-test-k6.js`) hoặc Newman CLI.
- **Cách khắc phục:** Chạy và chèn bổ sung ảnh kết quả PHPUnit, Go `-race`, Go Loadtest / `k6` để khớp với cam kết ở `P#545` và `Bảng 4.1` Rubric 10.0.

### [C4] Lệch cổng triển khai giữa ảnh chụp (`Hình 3-2`, `3-5`, `3-6`, `3-7`) và `docker-compose.final.yml`
- **Trong ảnh `Hình 3-2` (`image17.png` — `docker ps`):**
  - `cashflow-gateway-final` chỉ có `8080/tcp` (không publish host `8080:8080`).
  - `cashflow-auth-db-final` chỉ có `3306/tcp` (không publish host `3307:3306`).
  - `cashflow-integration-service` chỉ có `8085/tcp` (không publish host `8085:8085`).
  - Mâu thuẫn trực tiếp với `Bảng 3.1` và cấu hình chuẩn trong `docker-compose.final.yml`.
- **Trong ảnh `Hình 3-5`, `3-6`, `3-7` (`image20`, `21`, `22`):** Cổng test thực tế hiển thị trên trình duyệt/Postman là **`http://localhost:18080/...`** thay vì `:8080` được mô tả xuyên suốt tài liệu.
- **Cách khắc phục:** Chạy cụm container chuẩn từ `docker-compose.final.yml` (`8080:8080`, `3307:3306`, `8085:8085`) và chụp lại `Hình 3-2`, `3-5`, `3-6`, `3-7` trên đúng cổng `:8080`.

### [C5] Rò rỉ cú pháp Markdown thô (`126` vị trí backtick `` ` `` và `4` vị trí `*...*`)
Dấu `` ` `` và `*...*` chưa được xóa khi chuyển từ Markdown sang Word:
- **Mục lục:** `P#95` (``(`gateway/final/`)``), `P#102` (``& `ioredis` ``), `P#109` (``7 `Dockerfile` ``), `P#110` (``(`docker-compose.final.yml`)``), `P#113` (``(`:3000`)``).
- **Danh mục Hình & Bảng:** `P#142` (``Hình 3-5. Kết quả `GET /health/services` (`ALL_SERVICES_UP`)]``), `P#155`–`P#172` (gồm cả `P#172`: ``(`gateway/final` & `services//final`)*`` — bị rụng dấu `*` giữa `services/*/final` thành `services//final` và còn dấu `*` ở cuối).
- **Headings, Bảng & Đoạn văn:** `P#215`, `P#412`, `P#515`, `P#521`, `P#558`, `Bảng 1.1`, `2.1`, `2.15`, `3.1`, `3.3`, `4.1` (còn cả `*(Penalty Caps: ... `7.0` ...)*`), và nhiều đoạn văn trong Chương 1–4 + Phụ lục (`MSSV: `52400010``).
- **Tài liệu tham khảo:** Mục `[1]` và `[7]` còn nguyên `*Express 4.x...*`, `*Socket.IO v4...*`.
- **Cách khắc phục:** Tìm và xóa toàn bộ `` ` ``, chuyển các cụm `*...*` thành chữ *in nghiêng* thực sự trong Word, đồng thời sửa `services//final` thành `services/*/final`.

### [C6] Nhúng nhầm 2 ảnh sơ đồ lớp vào bên trong dòng `Heading 3` (`P#253`, `P#287`)
- `P#253` (`Heading3`: `Auth & Profile Service — Java 17 + Spring Boot 3.5.16`) chứa luôn `image6.png` (`Hình 2-3`).
- `P#287` (`Heading3`: `Transaction & Budget Service — PHP 8.2 + Laravel 12`) chứa luôn `image7.png` (`Hình 2-4`).
- **Cách khắc phục:** Tách 2 ảnh này xuống thành một đoạn văn riêng (style `Normal` hoặc căn giữa) ngay dưới tiêu đề `Heading 3`.

### [C7] 5 sơ đồ bị nén quá nhỏ/vỡ chữ khi in trên khổ A4 (`Hình 1-2`, `2-3`, `2-5`, `2-9`, `2-11`)
| Hình | Tệp ảnh | Độ phân giải & Kích thước Word | Vấn đề trực quan khi đọc/in |
|---|---|---|---|
| `Hình 1-2` (Use Case) | `image3.png` | `1343×1292 px` (`15,5 × 14,92 cm`) | Không dùng hình người/hình oval chuẩn UML; 20 đường nối chụm thành vệt đen đặc ở góc `USER`; nhãn `<<extend>>` đè nét; chữ rất nhỏ. Nên xuất từ `final-02-usecase-and-otp.puml`. |
| `Hình 2-3` (Class Auth) | `image6.png` | `1343×759 px` (`15,5 × 8,76 cm`) | 14 lớp dàn ngang → tên phương thức/thuộc tính **mờ nhòe không đọc được**. |
| `Hình 2-5` (Class Asset) | `image8.png` | `1343×698 px` (`15,5 × 8,06 cm`) | 13 lớp dàn ngang → **chữ mờ vỡ không đọc được**. |
| `Hình 2-9` (ERD 15 thực thể) | `image12.png` | `1343×832 px` (`15,5 × 9,61 cm`) | Toàn bộ 15 bảng + cột dồn vào khung thấp → **tên cột & kiểu SQL không thể đọc**. |
| `Hình 2-11` (Seq Ngân hàng) | `image14.png` | `1343×569 px` (`15,5 × 6,56 cm`) | 7 lifeline nén cao `6,56 cm` → **chữ li ti không đọc được**. |

- **Cách khắc phục:** Xuất lại với độ phân giải cao (SVG hoặc PNG chiều rộng `2500–3500 px`), bố cục dọc hoặc tách nhỏ các nhóm lớp/CSDL hợp lý để đảm bảo chữ rõ ràng trên giấy A4.

---

## 3. Lỗi kỹ thuật, cấu trúc & đối chiếu mã nguồn (Medium)

### [M1] Chưa có bất kỳ trích dẫn `[1]`–`[11]` nào trong thân bài
- Bảng Tài liệu tham khảo liệt kê `[1]` đến `[11]`, nhưng toàn bộ Chương 1–4 **không chứa một lần trích dẫn `[n]` nào**. Cần gán trích dẫn `[1]`–`[11]` vào đúng các mục kỹ thuật tương ứng trong Chương 1–3.

### [M2] Chưa đồng nhất style & dấu câu ở chú thích Hình
- **Sai style ở `Hình 2-12` (`P#509`):** Mang nhầm style `Nidungvnbn` (thụt đầu dòng, chữ thường) thay vì style `pics` (căn giữa, in nghiêng) như 24 hình còn lại.
- **Lẫn dấu `:` và `.`:** Từ `Hình 1-1` đến `Hình 3-1` dùng định dạng `Hình X-Y: ...`; từ `Hình 3-2` đến `Hình 3-12` đổi sang `Hình 3-X. ...`. Cần thống nhất toàn bộ theo `Hình X-Y: ...`.

### [M3] Sai khác chi tiết trong `Bảng 3.3` so với mã nguồn
1. **Dòng `AuthApiTest.java` (`Bảng 3.3` R1):** Bảng ghi `AuthApiTest.java (10 test methods / 14 cases)`, trong khi mã nguồn `services/auth-service/final/src/test/java/com/cashflow/auth/AuthApiTest.java` thực tế có **`11` hàm `@Test` + `1` hàm `@ParameterizedTest` (`5` giá trị `@ValueSource`) = `16` lượt chạy test** (cộng `2` ở `ApiErrorsTest.java` = `18` lượt chạy, khớp đúng ảnh `Hình 3-4`). Cần sửa mô tả thành `11 @Test + 1 @ParameterizedTest (5 cases) = 16 lượt chạy`.
2. **Dòng `Postman_Collection_All_Services.json` (`Bảng 3.3` R4):** Bảng nêu collection này (`33 requests`) dùng kiểm thử `/health/services` và `quy trình OTP 6 số`, nhưng kiểm tra trực tiếp `docs/api-docs/Postman_Collection_All_Services.json` cho thấy file này có `33` requests nhưng **`0` request OTP** và **không có `/health/services`** (là collection dùng chung từ giữa kỳ). Cần ghi rõ phần OTP/Health test bằng request Postman bổ sung (hoặc export/cập nhật collection mới cho bản Cuối kỳ).

### [M4] Đại từ xưng hô ở Lời cam đoan (`P#64`–`P#66`)
- Lời cam đoan dùng đại từ số ít (*"Tôi xin cam đoan đây là công trình nghiên cứu của riêng tôi... do tôi gây ra"*) dù ngay bên dưới là bảng ký tên của **6 thành viên** (`TBL#2`). Nên sửa thành *"Nhóm thực hiện xin cam đoan..."* / *"của nhóm chúng tôi"*.

### [M5] Cấu trúc Section, Header đánh số trang & đoạn rỗng thừa
- Có **13 đoạn văn rỗng liên tiếp mang style `tab`** (`P#583`–`P#595`) nằm giữa `Bảng 4.2` và `PHỤ LỤC`.
- `P#84` (`MỤC LỤC`) đang dùng style `Nidungvnbn` thay vì style tiêu đề riêng.
- Tài liệu tách `5` Section trong khi `Section 5` (chứa Chương 1–4 từ sau `P#179`) không khai báo `headerReference` trực tiếp (`hfs=[]`), còn `header3.xml` gắn ở `Section 4` (vốn đặt `lowerRoman`). Cần mở **Print Preview** trong Word để kiểm tra chắc chắn số trang Header `1..100` hiển thị đúng liên tục.

### [M6] Định dạng đoạn mã nguồn trích dẫn
- Các khối code (`P#219`–`239`, `261`–`284`, `294`–`324`, `336`–`352`, `362`–`381`, `390`–`410`, `422`–`447`) dùng style `Normal` tô nền thủ công, chưa dùng style code riêng; một vài dòng đầu/lồng khối bị thụt lề chưa đều (như `applyWebhook` tại `P#391`, `Get` tại `P#337`).

---

## 4. Chi tiết nhỏ cần chỉnh & Lưu ý khi bảo vệ (Minor / Polish)

1. **[L1] Thống nhất gạch nối ở Trang bìa (`P#14`, `P#40`):** Dòng `LÊ TIẾN ĐẠT - 52400105` dùng dấu `-` ngắn, trong khi 5 thành viên trên dùng en-dash `–`.
2. **[L2] Sửa lỗi chính tả tên `Bảng 3.3` (`P#172`, `P#542`):** Sửa ``(`gateway/final` & `services//final`)*`` thành `(gateway/final & services/*/final)`.
3. **[L3] Kiểm tra ký tên trang Lời cảm ơn, Cam đoan (`TBL#1`, `TBL#2`) & Phụ lục:** Nếu nộp bản in có chữ ký thật, bổ sung chữ ký vào bảng `TBL#1`, `TBL#2` và mục Phụ lục; xác nhận lại ngày nộp (`ngày 28 tháng 11 năm 2026`).
4. **[L4] Giải thích chủ động khi bảo vệ về `Hình 3-11` (`image25.png`):** Trong ảnh hộp thư `notifications.php`, các thông báo (`Biến động số dư lớn`, `Mã xác thực OTP giao dịch`) hiển thị **lặp 2 lần** — nhóm cần lưu ý đây là hệ quả thực tế của cơ chế `createOtp()` / Redis Pub/Sub loopback đã được phân tích trung thực tại Mục `2.2.6` (`P#451`) và Mục `4.2.2` (`P#577`) (hoặc khắc phục bằng flag tránh nhân bản rồi chụp lại).
5. **[L5] Cập nhật lại Mục lục / Danh mục Hình / Danh mục Bảng:** Sau khi chỉnh sửa, nhấn **`Ctrl + A` → `F9` → `Update entire table`** trong Word trước khi xuất PDF.

---

## 5. Bảng kiểm tra chi tiết 25 Hình (`26` tệp ảnh nhúng)

| Hình | Tệp media | Kích thước Word | Độ phân giải (px) | Đánh giá chi tiết |
|---|---|---|---|---|
| Logo TDTU (×2) | `image1.png` | `4,13 × 2,29 cm` | `302 × 167` | Đạt |
| `Hình 1-1`: Kiến trúc tổng thể | `image2.png` | `12,77 × 22,13 cm` | `1111 × 1925` | Bố cục dọc rõ ràng, đầy đủ thông tin |
| `Hình 1-2`: Use Case tổng quát | `image3.png` | `15,50 × 14,92 cm` | `1343 × 1292` | **Mũi tên chụm đen ở gốc `USER`, nhãn cắt nét, không đúng oval UML** |
| `Hình 2-1`: Tuần tự Bảo mật JWT | `image4.png` | `15,50 × 9,78 cm` | `1343 × 847` | Hơi nhỏ khi in A4, chữ số bước nhỏ |
| `Hình 2-2`: Thành phần Express Gateway | `image5.png` | `15,50 × 14,36 cm` | `1343 × 1244` | Đạt (rõ nét, đọc tốt) |
| `Hình 2-3`: Class Auth Service | `image6.png` | `15,50 × 8,76 cm` | `1343 × 759` | **Nhúng trong `Heading 3` (`P#253`); 14 lớp nén ngang bị mờ tịt** |
| `Hình 2-4`: Class Transaction Service | `image7.png` | `15,50 × 19,10 cm` | `1346 × 1658` | **Nhúng trong `Heading 3` (`P#287`)** *(nội dung ảnh khá rõ)* |
| `Hình 2-5`: Class Asset Service | `image8.png` | `15,50 × 8,06 cm` | `1343 × 698` | **13 lớp nén ngang bị mờ chữ** |
| `Hình 2-6`: Thành phần Analytics | `image9.png` | `15,50 × 17,41 cm` | `1342 × 1508` | Đạt (rõ nét) |
| `Hình 2-7`: Class Integration Service | `image10.png` | `15,50 × 22,11 cm` | `1343 × 1915` | Đạt (rõ nét) |
| `Hình 2-8`: Thành phần Notification | `image11.png` | `15,50 × 9,58 cm` | `1343 × 830` | Đạt (tương đối rõ) |
| `Hình 2-9`: ERD Toàn hệ thống | `image12.png` | `15,50 × 9,61 cm` | `1343 × 832` | **Tên cột/kiểu SQL 15 bảng bị mờ không đọc được** |
| `Hình 2-10`: Tuần tự OTP 6 số | `image13.png` | `15,50 × 19,63 cm` | `1343 × 1700` | Tạm ổn (chữ hơi mảnh nhưng đọc được theo chiều dọc) |
| `Hình 2-11`: Tuần tự Go → Laravel | `image14.png` | `15,50 × 6,56 cm` | `1343 × 569` | **7 lifeline nén cao `6,56 cm` — hoàn toàn không đọc được chữ** |
| `Hình 2-12`: State Machine OTP | `image15.png` | `11,87 × 20,73 cm` | `1031 × 1799` | Ảnh rất rõ *(nhưng caption `P#509` sai style `Nidungvnbn`)* |
| `Hình 3-1`: Triển khai Docker Final | `image16.png` | `15,50 × 21,45 cm` | `1343 × 1858` | Đạt (chi tiết, đúng 13 container & 6 volume) |
| `Hình 3-2`: Trạng thái 13 container | `image17.png` | `15,50 × 6,58 cm` | `1132 × 481` | **Lệch cổng host (`8080/tcp`, `3306/tcp`, `8085/tcp`) vs `Bảng 3.1`** |
| `Hình 3-3`: GitHub Actions CI | `image18.png` | `15,50 × 7,16 cm` | `1895 × 876` | Đạt (`lint-and-validate` pass 11s) |
| `Hình 3-4`: Kết quả Test tầng 1 | `image19.png` | `15,50 × 12,78 cm` | `855 × 705` | **Chỉ có Auth `mvn test` (`18/18`), thiếu PHPUnit & Go `-race`** |
| `Hình 3-5`: `GET /health/services` | `image20.png` | `15,50 × 4,58 cm` | `1917 × 567` | **CRITICAL: Ghi `ALL_SERVICES_UP` nhưng ảnh báo `DEGRADED` (`:18080`)** |
| `Hình 3-6`: Đăng nhập JWT | `image21.png` | `15,50 × 8,24 cm` | `1912 × 1016` | Ảnh rõ *(gọi cổng `:18080` thay vì `:8080`, thiếu truy vấn DB `sha256:`)* |
| `Hình 3-7`: Xác thực OTP 6 số | `image22.png` | `15,50 × 8,24 cm` | `1912 × 1016` | Ảnh rõ *(gọi cổng `:18080` thay vì `:8080`)* |
| `Hình 3-8`: Asset History `200` | `image23.png` | `15,50 × 7,98 cm` | `1908 × 982` | Đạt (`/api/assets/3/history` trả `200`) |
| `Hình 3-9` | *(Thiếu)* | — | — | **CRITICAL: Nhảy số bỏ qua `Hình 3-9`** |
| `Hình 3-10`: Web iBanking Dashboard | `image24.png` | `15,50 × 8,01 cm` | `1917 × 991` | Đạt (ảnh rõ, giao diện hoàn chỉnh) |
| `Hình 3-11`: Thông báo Realtime | `image25.png` | `15,50 × 8,06 cm` | `1918 × 997` | Ảnh rõ *(hiển thị lặp 2 lần do Redis loopback, cần giải thích)* |
| `Hình 3-12`: Đối chiếu DB thực tế | `image26.png` | `15,50 × 12,63 cm` | `837 × 682` | Đạt (`transaction_final_db`, `asset_db`, `integration_db` khớp dữ liệu) |
| `Hình 3-13` | *(Thiếu)* | — | — | **CRITICAL: Được trích dẫn ở `P#566` nhưng không có trong tài liệu** |

---

## 6. Checklist hoàn thiện nhanh trước khi xuất PDF nộp bài

- [ ] Chạy đủ 6 dịch vụ ở cổng `:8080` và chụp lại **`Hình 3-5`** hiển thị `"overallStatus": "ALL_SERVICES_UP"` (cập nhật luôn **`Hình 3-2`**, **`Hình 3-6`**, **`Hình 3-7`** trên đúng cổng `:8080`).
- [ ] Bổ sung ảnh chụp thực nghiệm **PHPUnit (`CashflowTest.php`)**, **Go `-race` (`integration_test.go`)**, **Go Loadtest (`600 reqs`) / `k6` / Newman** và xử lý dứt điểm việc thiếu **`Hình 3-9`** & **`Hình 3-13`** (hoặc đánh số lại chuẩn xác).
- [ ] Xóa sạch toàn bộ **`126` dấu backtick `` ` ``** và **`4` chuỗi `*...*` Markdown** (trong TOC, LOF, LOT, Heading, Bảng, Văn xuôi, TLTK); sửa `services//final` → `services/*/final` ở `Bảng 3.3` và xóa dấu `]` thừa ở `Hình 3-5`.
- [ ] Tách 2 ảnh `Hình 2-3` (`P#253`) và `Hình 2-4` (`P#287`) ra khỏi đoạn `Heading 3`.
- [ ] Xuất lại độ phân giải cao (hoặc tách/xoay hợp lý) cho 5 sơ đồ bị mờ/lỗi: `Hình 1-2` (nên dùng `final-02-usecase-and-otp.puml`), `Hình 2-3`, `Hình 2-5`, `Hình 2-9` (ERD) và `Hình 2-11`.
- [ ] Chèn đầy đủ trích dẫn `[1]`–`[11]` vào nội dung Chương 1–3.
- [ ] Sửa `Bảng 3.3` R1 về đúng `11 @Test + 1 @ParameterizedTest (5 cases) = 16 lượt chạy` cho `AuthApiTest.java`, và làm rõ nguồn collection/request test OTP & `/health/services`.
- [ ] Thống nhất style `pics` cho `Hình 2-12` (`P#509`) và thống nhất dấu `:` cho toàn bộ chú thích `Hình X-Y: ...`.
- [ ] Sửa *"Tôi"* → *"Nhóm thực hiện"* ở Lời cam đoan (`P#64`–`P#66`), xóa `13` dòng trống `tab` (`P#583`–`P#595`), thống nhất en-dash `–` ở trang bìa (`P#14`, `P#40`), kiểm tra đánh số trang Print Preview cho Section 5.
- [ ] Bấm **`Ctrl + A` → `F9` → `Update entire table`** trong Microsoft Word để cập nhật toàn bộ Mục lục, Danh mục Hình và Danh mục Bảng trước khi xuất PDF.
