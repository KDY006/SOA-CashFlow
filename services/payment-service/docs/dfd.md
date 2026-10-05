# DFD - Payment & Concurrency

## DFD mức 1: xử lý thanh toán

```mermaid
flowchart LR
  U[Người dùng] -->|MSSV, yêu cầu thanh toán| P1((4.1 Khởi tạo giao dịch))
  P1 -->|tra cứu khoản nợ| T[Tuition Service]
  P1 -->|kiểm tra tài khoản| A[User Service]
  P1 -->|ghi giao dịch OTP_PENDING| D1[(Payment DB: Transactions)]
  P1 -->|yêu cầu gửi OTP| O[OTP Service]
  O -->|OTP email| U
  U -->|transaction_id + OTP| P2((4.2 Xác nhận và xử lý))
  P2 -->|khóa account + tuition| R[(Redis Locks)]
  P2 -->|xác thực OTP| O
  P2 -->|đọc số dư / trừ tiền| A
  P2 -->|đọc trạng thái / gạch nợ| T
  P2 -->|cập nhật trạng thái và lịch sử| D1
  P2 -->|kết quả| U
```

## DFD mức 2: xử lý xác nhận và khóa

```mermaid
flowchart TD
  Start([Nhận transaction_id và OTP]) --> Read[Đọc giao dịch]
  Read --> Lock[Khóa account_id và tuition_record_id theo thứ tự cố định]
  Lock --> ValidOTP{OTP hợp lệ, còn hạn, chưa dùng?}
  ValidOTP -- Không --> Reject[Trả lỗi, không trừ tiền]
  ValidOTP -- Có --> Recheck[Đọc lại số dư và tình trạng học phí]
  Recheck --> Conditions{Đủ tiền và học phí chưa PAID?}
  Conditions -- Không --> Fail[Đánh dấu FAILED]
  Conditions -- Có --> Debit[User Service trừ tiền nguyên tử theo transaction_id]
  Debit --> Tuition[Tuition Service gạch nợ nguyên tử theo transaction_id]
  Tuition --> Paid{Gạch nợ thành công?}
  Paid -- Có --> Success[Đánh dấu SUCCEEDED]
  Paid -- Không --> Refund[User Service hoàn tiền với refund:transaction_id]
  Refund --> Fail
  Success --> Release[Nhả khóa]
  Fail --> Release
  Reject --> Release
  Release --> End([Trả kết quả])
```

## Nói ngắn gọn khi thuyết trình

“Em khóa đồng thời tài khoản và khoản học phí trong lúc xác nhận. Vì vậy, hai giao dịch không thể cùng kiểm tra một số dư cũ hoặc cùng gạch một khoản nợ. Sau khi lấy khóa, hệ thống kiểm tra lại OTP, số dư và trạng thái học phí. Nếu trừ tiền xong mà gạch nợ lỗi, hệ thống gọi hoàn tiền bù trừ.”

Redis lock chỉ có tác dụng nếu mọi Payment instance cùng dùng Redis đó. User Service vẫn phải trừ tiền bằng một câu lệnh nguyên tử (ví dụ `UPDATE ... WHERE balance >= amount`) và Tuition Service phải cập nhật có điều kiện `status = 'UNPAID'`; đây là lớp khóa cuối cùng chống race condition.
