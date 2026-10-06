FROM python:3.10-slim

WORKDIR /app

# Copy source code vào container
COPY . .

# Raw HTTP Server chạy trên port 8001 (theo code của Đạt)
EXPOSE 8001

CMD ["python", "app/main.py"]