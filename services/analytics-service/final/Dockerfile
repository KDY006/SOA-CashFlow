FROM python:3.11-slim
WORKDIR /app
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 HOST=0.0.0.0 PORT=8084
COPY final/requirements.txt ./requirements.txt
RUN pip install --no-cache-dir -r requirements.txt
COPY final/src/ ./src/
COPY db/init-analytics-db.sql ./db/init-analytics-db.sql
ENV ANALYTICS_DB_SCHEMA=/app/db/init-analytics-db.sql
EXPOSE 8084
CMD ["sh", "-c", "uvicorn src.main:app --host ${HOST} --port ${PORT}"]
