from http.server import HTTPServer, BaseHTTPRequestHandler
import json
import sqlite3
import re

# 1. KHỞI TẠO DATABASE (SQLite thuần)
def init_db():
    conn = sqlite3.connect("user_db.db")
    cursor = conn.cursor()
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            phone_number TEXT,
            balance REAL DEFAULT 0.0,
            role TEXT DEFAULT 'user'
        )
    ''')
    # Thêm user mẫu nếu chưa có
    try:
        cursor.execute('''
            INSERT INTO users (username, password, full_name, email, phone_number, balance, role)
            VALUES ('dat_user', '123456', 'Nguyễn Văn Đạt', 'dat@tdtu.edu.vn', '0901234567', 5000000.0, 'user')
        ''')
        conn.commit()
    except sqlite3.IntegrityError:
        pass
    conn.close()

# 2. XỬ LÝ REQUEST BẰNG TAY (Raw HTTP Handler)
class RawRequestHandler(BaseHTTPRequestHandler):

    # Hàm gửi Response JSON bằng tay
    def send_json_response(self, status_code, data):
        self.send_response(status_code)
        self.send_header('Content-Type', 'application/json')
        # Thêm CORS Header bằng tay để Frontend gọi được
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.end_headers()
        self.wfile.write(json.dumps(data, ensure_ascii=False).encode('utf-8'))

    # Xử lý CORS Options Request
    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.end_headers()

    # Xử lý các API phương thức POST (ví dụ: Đăng nhập)
    def do_POST(self):
        # Đọc độ dài body bằng tay
        content_length = int(self.headers.get('Content-Length', 0))
        post_data = self.rfile.read(content_length)
        
        try:
            body = json.loads(post_data.decode('utf-8'))
        except:
            body = {}

        # API 1: /api/auth/login (Đăng nhập bằng tay)
        if self.path == '/api/auth/login':
            username = body.get('username')
            password = body.get('password')

            conn = sqlite3.connect("user_db.db")
            cursor = conn.cursor()
            cursor.execute("SELECT id, username, role FROM users WHERE username=? AND password=?", (username, password))
            user = cursor.fetchone()
            conn.close()

            if user:
                # Trả về Token giả lập dạng chuỗi
                fake_token = f"fake-jwt-token-for-user-{user[0]}"
                return self.send_json_response(200, {
                    "access_token": fake_token,
                    "token_type": "bearer",
                    "user_id": user[0],
                    "username": user[1]
                })
            else:
                return self.send_json_response(401, {"error": "Sai tên đăng nhập hoặc mật khẩu"})

        # API 2: /api/users/update-balance (Admin/Payment cộng trừ tiền)
        elif re.match(r'^/api/users/(\d+)/update-balance$', self.path):
            user_id = re.match(r'^/api/users/(\d+)/update-balance$', self.path).group(1)
            amount = body.get('amount', 0)

            conn = sqlite3.connect("user_db.db")
            cursor = conn.cursor()
            cursor.execute("SELECT balance FROM users WHERE id=?", (user_id,))
            user = cursor.fetchone()

            if not user:
                conn.close()
                return self.send_json_response(404, {"error": "User không tồn tại"})

            new_balance = user[0] + amount
            if new_balance < 0:
                conn.close()
                return self.send_json_response(400, {"error": "Số dư không đủ"})

            cursor.execute("UPDATE users SET balance=? WHERE id=?", (new_balance, user_id))
            conn.commit()
            conn.close()

            return self.send_json_response(200, {"message": "Cập nhật số dư thành công", "new_balance": new_balance})

        else:
            return self.send_json_response(404, {"error": "Endpoint không tồn tại"})

    # Xử lý các API phương thức GET (ví dụ: Lấy thông tin cá nhân)
    def do_GET(self):
        # API 3: /api/users/me (Lấy thông tin cá nhân & Số dư bằng tay)
        if self.path.startswith('/api/users/me'):
            # Lấy token từ Header Authorization bằng tay
            auth_header = self.headers.get('Authorization', '')
            
            if not auth_header.startswith("Bearer "):
                return self.send_json_response(401, {"error": "Thiếu Authorization Header"})

            # Giả lập lấy user_id từ token
            # Ở bài bằng tay, lấy tạm user ID = 1 để demo
            user_id = 1 

            conn = sqlite3.connect("user_db.db")
            cursor = conn.cursor()
            cursor.execute("SELECT id, username, full_name, email, phone_number, balance, role FROM users WHERE id=?", (user_id,))
            user = cursor.fetchone()
            conn.close()

            if user:
                user_data = {
                    "id": user[0],
                    "username": user[1],
                    "full_name": user[2],
                    "email": user[3],
                    "phone_number": user[4],
                    "balance": user[5],
                    "role": user[6]
                }
                return self.send_json_response(200, user_data)
            else:
                return self.send_json_response(404, {"error": "Không tìm thấy người dùng"})

        else:
            return self.send_json_response(404, {"error": "Endpoint không tồn tại"})

# 3. CHẠY SERVER BẰNG TAY (Port 8001)
if __name__ == '__main__':
    init_db()
    server_address = ('', 8001)
    httpd = HTTPServer(server_address, RawRequestHandler)
    print("🚀 Raw Python Server (Không Framework) đang chạy tại port 8001...")
    httpd.serve_forever()