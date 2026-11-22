FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt || true

COPY src/ ./src/

EXPOSE 8084

CMD ["python", "-m", "http.server", "8084"]
