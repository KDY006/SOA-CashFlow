# KẾT QUẢ KIỂM TRA & ĐÁNH GIÁ BÁO CÁO GIỮA KỲ (`Bao_Cao_SOA_Gki.docx`)

- **Tệp được kiểm tra:** `Bao_Cao_SOA_Gki.docx` (dung lượng: `9.491.442 bytes` ≈ `9,05 MB`)
- **Phạm vi đối chiếu:** Cấu trúc XML/Style Word, toàn bộ `538` đoạn văn bản, `32` bảng biểu, `35` ảnh nhúng (`37` vị trí chèn ảnh), đối chiếu chéo với mã nguồn Giữa kỳ (`gateway/midterm/`, `services/*/midterm/`, `docker-compose.midterm.yml`, `docs/diagrams/`).

---

## 1. Thống kê tổng quan & Hình thức trình bày

| Hạng mục kiểm tra | Kết quả đo đạc thực tế | Đánh giá |
|---|---|---|
| Khổ giấy & Lề trang (3 Section) | A4 (`21,0 × 29,7 cm`); Lề Trên `3,5 cm`, Dưới `3,0 cm`, Trái `3,5 cm`, Phải `2,0 cm` (vùng in `15,5 × 23,2 cm`) | Đạt chuẩn TDTU |
| Kiểu chữ & Giãn dòng | `Times New Roman`, `Normal` `13 pt`, giãn dòng `1,5`, căn đều hai bên (`justify`) | Đạt chuẩn TDTU |
| Phân chia Section & Đánh số trang | 3 Section: Bìa (không số trang) → Phần đầu (La Mã `i, ii...`) → Nội dung chính (`1..59`, Header căn giữa) | Đạt |
| Đối soát Mục lục (TOC) | `43/43` tiêu đề `Heading 1–3` khớp 100% | Đạt *(nhưng chưa đánh số đồng nhất H3)* |
| Đối soát Danh mục Hình vẽ (LOF) | `25/25` chú thích `Hình X-Y` khớp 100% | Đạt |
| Đối soát Danh mục Bảng biểu (LOT) | `30/30` chú thích `Bảng X.Y` khớp 100% | Đạt |
| Placeholder còn sót (`TODO`, `📸`) | `0` khung nhắc chèn ảnh / `0` từ khóa `TODO` *(chỉ có 6 dòng chấm ký tên ở Phụ lục)* | Đạt |
| Cú pháp Markdown/LaTeX thô còn sót | **4 vị trí** (`$P / n$`, `$r = 0$`, `$2M$`, backtick `` ` ``) | **Cần sửa** |
| Ảnh chèn trùng sai nội dung | **1 lỗi** (`Hình 4-1` dùng lại `image2.png` của `Hình 1-1`) | **Cần sửa ngay** |
| Trích dẫn Tài liệu tham khảo | Trích dẫn `6/10` tài liệu (`[1], [2], [4], [6], [7], [8]`); thiếu `[3], [5], [9], [10]` | **Cần bổ sung** |
| Khung viền trang bìa (`pgBorders`) | `None` (chưa đóng khung 2 trang bìa) | Lưu ý kiểm tra theo yêu cầu khoa |
| Tự động cập nhật Field (`updateFields`) | Chưa bật trong `settings.xml` | Nhớ `Ctrl+A` → `F9` trước khi xuất PDF |

### Điểm mạnh nổi bật
1. **Bố cục chuẩn học thuật TDTU:** Đầy đủ Bìa chính, Bìa phụ, Lời cảm ơn, Cam đoan, Tóm tắt, Mục lục, Danh mục hình vẽ, Danh mục bảng biểu, Danh mục chữ viết tắt, 5 Chương nội dung, Phụ lục phân công 6 thành viên và Tài liệu tham khảo.
2. **Minh chứng thực nghiệm trung thực & khớp số liệu:** Toàn bộ ảnh chụp thực tế ở Chương 4 (`docker compose ps`, Swagger UI, truy vấn MySQL PBKDF2, tạo giao dịch & ngân sách, khấu hao & EMI, mô phỏng Safe/Unsafe, Newman `33/33` assertions `22/22`, Go Loadtest `600 req / 3,295s`, và `8` màn hình Web iBanking) đều rõ nét, số liệu trích dẫn trong văn bản khớp 100% với ảnh chụp.
3. **Đánh giá phạm vi thực nghiệm khách quan:** Mục `4.3`–`4.5` và `Chương 5` phân biệt rõ phần đã chứng minh được và phần cần đánh giá thêm (ví dụ: không thổi phồng `budget_alert` trong phản hồi `POST /api/transactions`, ghi nhận trung thực việc 2 tài khoản seed dùng chung tiền tố salt/hash, ghi nhận cần kiểm thử riêng SSE realtime push).

---

## 2. Danh sách lỗi NGHIÊM TRỌNG cần sửa ngay (Critical)

### [C1] `Hình 4-1` (`Sơ đồ triển khai Docker Compose`) bị chèn trùng ảnh của `Hình 1-1`
- **Vị trí:** Chương 4, Mục `4.1`, `Hình 4-1` (`P#413`, sử dụng `word/media/image2.png`).
- **Hiện trạng:** Ảnh hiển thị tại `Hình 4-1` là bản sao y hệt `image2.png` (`Hình 1-1: Kiến trúc tổng thể SOA CashFlow`), hoàn toàn **không có** mạng `cashflow-midterm-net`, tên 13 container (`cashflow-*`), ánh xạ cổng host/internal hay volume.
- **Cách khắc phục:** Xuất ảnh sơ đồ triển khai Docker Compose từ nguồn chuẩn `docs/diagrams/01-architecture-and-deployment.md` (phần Deployment Diagram) và thay thế vào `Hình 4-1`.

### [C2] Còn sót 2 câu văn bản nháp mâu thuẫn với Chương 4 đã có ảnh & số liệu
- **Vị trí 1:** Phần `TÓM TẮT` (`P#50`):
  > *"Kết quả đo tải và ảnh chụp thực nghiệm được bổ sung vào các vị trí đánh dấu sau khi thực hiện kiểm thử."*
- **Vị trí 2:** Chương 2, Mục `2.7` (`P#215`):
  > *"Chương 4 trình bày kịch bản và mẫu ghi kết quả tương ứng."*
- **Hiện trạng:** Thực tế Chương 4 đã chèn đầy đủ ảnh chụp thực nghiệm, kết quả Newman và Go Loadtest. Hai câu trên lộ dấu vết bản nháp chưa cập nhật.
- **Cách khắc phục:**
  - Tại `TÓM TẮT`: đổi thành tóm lược ngắn kết quả thực nghiệm (ví dụ: *"Kết quả thực nghiệm với Docker Compose (13 container), Swagger UI, Newman (33 request), Go Loadtest (600 request đồng thời, chặn 400 lượt trùng, sai lệch số dư 0) và giao diện Web iBanking xác nhận tính nhất quán của các luồng nghiệp vụ trọng tâm."*).
  - Tại `Mục 2.7`: đổi *"mẫu ghi kết quả"* thành *"kết quả thực nghiệm"*.

### [C3] Còn sót cú pháp Markdown / LaTeX thô chưa chuyển đổi ở 4 vị trí
| STT | Vị trí | Văn bản hiện tại trong Word | Cách sửa |
|---|---|---|---|
| 1 | Mục `3.2.3 (d)` (`P#298`) | `(hoặc $P / n$ nếu $r = 0$)` | Bỏ ký tự `$` → `(hoặc P / n nếu r = 0)` hoặc nhập bằng Word Equation |
| 2 | Mục `3.2.4 (d)` (`P#318`) | `(hoặc $2M$ nếu MAD = 0)` | Bỏ ký tự `$` → `(hoặc 2M nếu MAD = 0)` hoặc nhập bằng Word Equation |
| 3 | `Bảng 3.1` (dòng `Notification Service`) | ``(Gateway `/` và `/health` là public trực tiếp tại Gateway)`` | Bỏ dấu backtick `` ` `` → `(Gateway / và /health là public trực tiếp tại Gateway)` |
| 4 | `Tài liệu tham khảo [9]` (`TBL#32 R9`) | ``Go 1.22 Standard Library (`net/http`, `database/sql`, `sync`) Documentation`` | Bỏ dấu backtick `` ` `` → `Go 1.22 Standard Library (net/http, database/sql, sync) Documentation` |

### [C4] Ký hiệu generic Mermaid `~T~` bị rò rỉ trực tiếp lên 4 ảnh Biểu đồ lớp
Do công cụ vẽ lại sơ đồ không chuyển đổi cú pháp generic của Mermaid (`~...~` thành `<...>`), 4 ảnh sau bị hiển thị nguyên dấu ngã `~`:
- **`Hình 3-2` (`image5.png` — API Gateway):** `-Map~string,object~ rateLimitMap` → đúng phải là `Map<string, object>`.
- **`Hình 3-5` (`image8.png` — Asset Service):** `+ReadBodyAsync~T~(HttpListenerRequest req) Task~T~` → đúng phải là `ReadBodyAsync<T>(...) Task<T>`.
- **`Hình 3-7` (`image10.png` — Integration Service):** `-map~string,keyLock~ locks` → đúng phải là `map[string]*keyLock` (hoặc `map<string, keyLock>`).
- **`Hình 3-8` (`image11.png` — Notification Service):** `-Map~number,Set~ userConnections` và `+parseJsonBody(req) Promise~object~` → đúng phải là `Map<number, Set>` và `Promise<object>`.
- **Ghi chú chung cho 7 sơ đồ lớp (`Hình 3-2` → `Hình 3-8`):** Các lớp đang hiển thị thành 1 khung chữ nhật căn giữa, thiếu 2 vạch ngang phân chia 3 ngăn chuẩn UML (`Tên lớp | Thuộc tính | Phương thức`). Nên xuất lại ảnh trực tiếp bằng Mermaid CLI (`mmdc`) / Mermaid Live từ `docs/diagrams/03-class-diagrams-midterm.md`.

---

## 3. Lỗi kỹ thuật & chất lượng sơ đồ nên khắc phục (Medium)

### [M1] Chất lượng đồ họa & chuẩn UML của các sơ đồ tự vẽ lại (`Hình 1-1`, `1-2`, `3-1`, `3-9`, `3-10`, `3-11`)
1. **`Hình 1-1` (`image2.png` — Kiến trúc tổng thể):**
   - Đường nét đứt từ `Integration :8085` đến `Notification :8086` **cắt xuyên ngang chữ `"Cảnh báo"`**.
   - Đường nét đứt `"GET dữ liệu"` từ `Analytics :8084` đè chồng lên mũi tên từ `Transaction & Budget :8082` xuống `transaction_db`.
   - Thiếu mũi tên kết nối SSE trực tiếp từ `PHP Web iBanking :3000` sang `Notification :8086` dù mục phân tích ngay bên dưới (`P#176`) có mô tả.
2. **`Hình 1-2` (`image3.png` — Biểu đồ Use Case tổng quát):**
   - **Sai ký hiệu UML:** Các tác nhân (`Người dùng`, `Quản trị viên`, `Ngân hàng giả lập`) được vẽ bằng **hình bầu dục (oval)** giống hệt Use Case thay vì hình người (`actor`) và thiếu khung ranh giới hệ thống (System Boundary).
   - Mũi tên `"kế thừa"` từ `Quản trị viên` → `Người dùng` dùng nét đứt thay vì nét liền đầu tam giác rỗng (UML Generalization).
   - Đường nét đứt `"cảnh báo"` cắt xuyên ngang chữ `"cảnh báo"`.
   - Tỉ lệ khung hình quá dẹt (`2529×525 px`, `AR = 4,82`), khi chèn vào Word chỉ cao **`3,22 cm`** nên chữ rất nhỏ khi in A4.
   - **Cách sửa:** Xuất ảnh trực tiếp từ tệp PlantUML chuẩn `docs/diagrams/02-usecase-overall.puml`.
3. **`Hình 3-1` (`image4.png`), `Hình 3-10` (`image13.png`), `Hình 3-11` (`image14.png`) — Sơ đồ tuần tự:**
   - Bị chuyển sang lưu đồ 1 cột dọc (flowchart), mất hoàn toàn đường sinh mệnh (lifeline) và khung rẽ nhánh `alt`/`loop`.
   - Gây lỗi logic trực quan: ở `Hình 3-1`, bước `7. 401 nếu token sai hoặc hết hạn` nối mũi tên thẳng xuống `8. Nếu hợp lệ...`; tương tự ở `Hình 3-11`, bước `3` (`200/409`) và bước `4` (`REJECTED, 422`) nối mũi tên thẳng xuống bước `5` (`Hợp lệ: cập nhật balance...`).
   - **Cách sửa:** Xuất lại ảnh từ các khối `sequenceDiagram` trong `docs/diagrams/05-sequence-and-state-diagrams.md`.
4. **`Hình 3-9` (`image12.png` — ERD tổng thể):**
   - Chưa dùng ký hiệu Crow's Foot (`||--o{`) chuẩn ERD.
   - Chưa hiển thị đường nét đứt biểu diễn tham chiếu logic `user_id` và `transaction_ref` giữa 5 CSDL như phần giải thích đi kèm (`P#378`).
   - **Cách sửa:** Xuất lại ảnh từ `docs/diagrams/04-erd-database-per-service.md`.

### [M2] Sai khác giữa `Bảng 4.1` / `Hình 4-2` và `docker-compose.midterm.yml` hiện tại trong repository
| Container | `Bảng 4.1` & `Hình 4-2` trong `.docx` | `docker-compose.midterm.yml` trong repo hiện tại |
|---|---|---|
| `transaction-service` | `8082 (nội bộ)` | `${TRANSACTION_SERVICE_PORT:-8082}:8082` (dòng 70) |
| `auth-db` | `3306 (nội bộ)` *(container tạo từ 5 weeks ago)* | `3307:3306` (dòng 197 — khớp với `Bảng 1.3` ghi `localhost:3307`) |
| `analytics-db` | `named volume` | Gắn cả `init-analytics-db.sql:/docker-entrypoint-initdb.d/init.sql:ro` (dòng 252) và `analytics_midterm_db_data` |

- **Cách khắc phục:** Chạy `docker compose -f docker-compose.midterm.yml up -d --force-recreate` từ bản repo hiện hành để chụp lại `Hình 4-2` và đồng bộ `Bảng 4.1`, hoặc ghi chú rõ trong `Bảng 4.1` sự khác biệt giữa thời điểm chụp snapshot và file Compose mới nhất.

### [M3] 4/10 Tài liệu tham khảo chưa được trích dẫn trong văn bản
- Các tài liệu đã được trích dẫn trong nội dung: `[1]`, `[2]`, `[4]`, `[6]`, `[7]`, `[8]`.
- Các tài liệu **chưa được trích dẫn** ở bất kỳ đâu trong Chương 1–5:
  - **`[3]`** Redis Serialization Protocol (RESP) → nên trích dẫn tại Mục `2.6` hoặc `3.2.6 (d)`.
  - **`[5]`** OpenAPI Specification v3.0.3 → nên trích dẫn tại Mục `2.2` hoặc `4.3` (Swagger UI).
  - **`[9]`** Go 1.22 Standard Library → nên trích dẫn tại Mục `3.2.5`.
  - **`[10]`** Docker Compose & Multi-stage Builds → nên trích dẫn tại Mục `2.7` hoặc `4.1`.

---

## 4. Lỗi trình bày & chi tiết nhỏ cần chỉnh (Minor / Polish)

1. **[L1] Thiếu khoảng trắng tên giảng viên ở Bìa phụ (`P#30`):** `ThS.Trần Quốc Trường` → sửa thành `ThS. Trần Quốc Trường`. Kiểm tra lại quy định khoa về khung viền trang bìa (hiện cả 2 trang bìa đều `pgBorders = None`).
2. **[L2] Hụt số thứ tự danh sách ở Mục `3.3` (`P#399`):** Xuất hiện mục `2. Cơ chế tự giải cứu bản ghi kẹt (sweeper chạy mỗi 15 giây trong sync.go):` nhưng mục `1.` ngay phía trên (`P#398`) không được đánh số.
3. **[L3] Đánh số `Heading 3` không đồng nhất trong Mục lục:**
   - Mục `3.2` đánh số đầy đủ (`3.2.1` → `3.2.6`).
   - 9 mục `Heading 3` khác lại **không đánh số**: `Quy trình xử lý yêu cầu`, `Mô hình dữ liệu phân tán (ERD)`, `Giao tiếp đồng bộ Analytics – Transaction`, `Đồng bộ nền và cảnh báo SSE`, `Máy trạng thái giao dịch và tính nhất quán cuối cùng`, `Tối ưu Docker image`, `Swagger UI tích hợp tại Gateway`, `Kết quả kiểm thử các luồng nghiệp vụ`, `Kỹ thuật tối ưu và bảo mật Frontend`.
   - **Khuyến nghị:** Đánh số thống nhất (`3.1.1`, `3.3.1`–`3.3.4`, `4.1.1`, `4.3.1`–`4.3.2`, `4.4.1`).
4. **[L4] Sai Style ở ảnh `Hình 4-9a` (`P#472`, `image28.png`):** Ảnh này đang áp dụng style `ReportList` (bị thụt lề trái + hanging indent) thay vì `ReportCaption`/`Normal` như 7 ảnh Web iBanking còn lại (`image29`–`image35`).
5. **[L5] Lỗi chính tả & định dạng JSON khi che token trong `Hình 4-4a` (`image18.png`):** Chỗ che token ghi `[TOKEN DACHE]` (thiếu dấu tiếng Việt `ĐÃ CHE`), thiếu dấu ngoặc kép quanh `"accessToken": [TOKEN DACHE]` và dư dấu nháy đơn ở `"refreshToken": ' [TOKEN DACHE]`.
6. **[L6] Lưu ý giải thích khi bảo vệ (từ ảnh chụp giao diện Web):**
   - Trong `Hình 4-11c` (`image35.png` — `notifications.php`), banner giao diện có dòng chữ *"trên nền tảng Node.js / Express"*.
   - Trong `Hình 4-11b` (`image34.png` — `banking.php`), thẻ tiêu đề ghi *"INTEGRATION SERVICE · GO / NODE"*.
   - Nguyên nhân do dùng chung giao diện Frontend cho cả Giữa kỳ và Cuối kỳ. Nhóm nên sửa dòng mô tả trên frontend rồi chụp lại, hoặc chuẩn bị giải thích rõ khi vấn đáp rằng bản Giữa kỳ chạy Node.js/Go thuần không dùng Express.
7. **[L7] Ngày ký & Cập nhật lại trường tự động:**
   - Lời cảm ơn (`P#35`) và Cam đoan (`P#42`) đang ghi `TP. Hồ Chí Minh, ngày 27 tháng 11 năm 2026` — xác nhận lại đúng ngày nộp của học kỳ.
   - Sau khi hoàn tất mọi chỉnh sửa, nhấn **`Ctrl + A` → `F9` → `Update entire table`** trong Microsoft Word để cập nhật toàn bộ số trang ở Mục lục, Danh mục hình vẽ và Danh mục bảng biểu trước khi xuất PDF.

---

## 5. Bảng kiểm tra chi tiết 25 Hình (`35` ảnh nhúng)

| Hình trong báo cáo | Tệp media | Kích thước hiển thị (Word) | Độ phân giải gốc (px) | Trạng thái kiểm tra |
|---|---|---|---|---|
| Logo TDTU (Bìa chính & phụ) | `image1.png` (×2) | `5,10 × 2,82 cm` | `302 × 167` | Đạt |
| `Hình 1-1`: Kiến trúc tổng thể | `image2.png` | `15,50 × 6,78 cm` | `1771 × 775` | **Lỗi nét cắt chữ `"Cảnh báo"`, đè nét, thiếu SSE** |
| `Hình 1-2`: Use Case tổng quát | `image3.png` | `15,50 × 3,22 cm` | `2529 × 525` | **Sai chuẩn Actor/Boundary, nét cắt chữ, quá dẹt** |
| `Hình 3-1`: Luồng JWT & Anti-IDOR | `image4.png` | `6,89 × 13,06 cm` | `701 × 1329` | **Lưu đồ dọc thay vì Sequence; bước 7→8 sai luồng** |
| `Hình 3-2`: Mô-đun API Gateway | `image5.png` | `15,50 × 5,32 cm` | `1638 × 562` | **Rò rỉ `~string,object~`; thiếu 3 ngăn UML** |
| `Hình 3-3`: Class Auth Service | `image6.png` | `15,50 × 8,10 cm` | `2444 × 1277` | Thiếu 3 ngăn chuẩn UML |
| `Hình 3-4`: Class Transaction | `image7.png` | `15,50 × 7,00 cm` | `2009 × 907` | Thiếu 3 ngăn chuẩn UML |
| `Hình 3-5`: Class Asset Service | `image8.png` | `15,50 × 4,84 cm` | `2737 × 855` | **Rò rỉ `~T~`; thiếu 3 ngăn UML** |
| `Hình 3-6`: Class Analytics | `image9.png` | `7,31 × 13,06 cm` | `444 × 793` | Thiếu 3 ngăn chuẩn UML |
| `Hình 3-7`: Mô-đun Integration | `image10.png` | `15,50 × 5,12 cm` | `2136 × 706` | **Rò rỉ `~string,keyLock~`; thiếu 3 ngăn UML** |
| `Hình 3-8`: Mô-đun Notification | `image11.png` | `15,50 × 4,83 cm` | `1622 × 505` | **Rò rỉ `~number,Set~`, `~object~`; thiếu 3 ngăn UML** |
| `Hình 3-9`: ERD hệ thống (14 bảng) | `image12.png` | `15,50 × 12,13 cm` | `2394 × 1873` | Thiếu Crow's Foot & nét đứt tham chiếu logic |
| `Hình 3-10`: Luồng Analytics–Tx | `image13.png` | `8,03 × 13,06 cm` | `635 × 1032` | Lưu đồ dọc thay vì Sequence Diagram |
| `Hình 3-11`: Webhook, Syncer, SSE | `image14.png` | `6,04 × 13,06 cm` | `753 × 1627` | **Lưu đồ dọc thay vì Sequence; bước 3/4→5 sai luồng** |
| `Hình 3-12`: State Machine | `image15.png` | `7,74 × 13,06 cm` | `640 × 1079` | Chấp nhận được (rõ trạng thái và chuyển tiếp) |
| `Hình 4-1`: Sơ đồ Docker Compose | `image2.png` | `15,50 × 6,78 cm` | `1771 × 775` | **CRITICAL: Chèn trùng ảnh của `Hình 1-1`** |
| `Hình 4-2`: 13 container Up | `image16.png` | `15,50 × 2,88 cm` | `1628 × 302` | Ảnh thật tốt *(chênh cổng `auth-db`/`transaction-service` vs compose hiện tại)* |
| `Hình 4-3`: Swagger UI tập trung | `image17.png` | `15,50 × 7,32 cm` | `1911 × 902` | Đạt (ảnh thật sắc nét) |
| `Hình 4-4`: Login JWT & PBKDF2 | `image18.png`, `image19.png` | `14,26×4,29`, `15,50×2,42 cm` | `1335×402`, `1552×242` | Ảnh thật tốt *(lỗi typo `[TOKEN DACHE]` ở 4-4a)* |
| `Hình 4-5`: Transaction & Budget | `image20.png`, `image21.png` | `15,50×7,38`, `15,50×8,92 cm` | `1902×906`, `1343×773` | Đạt (khớp số liệu) |
| `Hình 4-6`: Khấu hao & EMI | `image22.png`, `image23.png` | `15,50×9,36`, `15,50×9,09 cm` | `1341×810`, `1330×780` | Đạt (khớp số liệu) |
| `Hình 4-7`: Unsafe vs Safe | `image24.png`, `image25.png` | `15,50×8,09`, `15,50×7,92 cm` | `1336×697`, `1337×683` | Đạt (khớp số liệu) |
| `Hình 4-8`: Newman & Go Loadtest | `image26.png`, `image27.png` | `15,50×8,49`, `15,50×5,15 cm` | `953×522`, `1000×332` | Đạt (khớp số liệu) |
| `Hình 4-9`: Login & Dashboard | `image28.png`, `image29.png` | `15,50×7,75`, `15,50×7,84 cm` | `1886×943`, `1892×957` | Ảnh tốt *(sửa style `ReportList` của `image28.png`)* |
| `Hình 4-10`: Tx, Budget, Asset | `image30`–`image32.png` | `15,50 × 7,88 cm` | `~1882 × 955` | Đạt (ảnh thật sắc nét) |
| `Hình 4-11`: Analytics, Bank, Notif | `image33`–`image35.png` | `15,50 × 7,81 cm` | `~1885 × 950` | Ảnh tốt *(lưu ý chữ `"Express"` trên banner 4-11c)* |

---

## 6. Checklist hoàn thiện nhanh trước khi xuất PDF nộp bài

- [ ] Thay thế `Hình 4-1` bằng sơ đồ triển khai Docker Compose thực sự (không dùng lại `image2.png`).
- [ ] Sửa 2 câu văn bản nháp ở `TÓM TẮT` (`P#50`) và `Mục 2.7` (`P#215`).
- [ ] Xóa toàn bộ cú pháp thô `$...$` (`P#298`, `P#318`) và backtick `` ` `` (`Bảng 3.1`, Tài liệu tham khảo `[9]`).
- [ ] Xuất lại các sơ đồ chuẩn Mermaid/PlantUML thay thế các hình bị rò rỉ `~T~` (`Hình 3-2`, `3-5`, `3-7`, `3-8`), cắt chữ/sai chuẩn UML (`Hình 1-1`, `1-2`, `3-1`, `3-9`, `3-10`, `3-11`).
- [ ] Bổ sung trích dẫn `[3]`, `[5]`, `[9]`, `[10]` vào nội dung Chương 2–4.
- [ ] Đồng bộ `Bảng 4.1` / `Hình 4-2` với `docker-compose.midterm.yml` (cổng `transaction-service`, `auth-db`, bind-mount `init-analytics-db.sql`).
- [ ] Sửa các lỗi trình bày nhỏ: `ThS. Trần Quốc Trường`, đánh số danh sách `1.` trước `P#399`, đồng bộ đánh số 9 mục `Heading 3`, đổi style ảnh `Hình 4-9a` từ `ReportList` sang `ReportCaption`/`Normal`, chỉnh `[TOKEN ĐÃ CHE]` ở `Hình 4-4a`, kiểm tra khung viền bìa và ngày nộp.
- [ ] Trong Microsoft Word, bấm `Ctrl + A` → `F9` → `Update entire table` để cập nhật số trang TOC/LOF/LOT trước khi xuất PDF.
