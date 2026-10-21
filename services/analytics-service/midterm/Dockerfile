FROM python:3.11-slim
WORKDIR /app
COPY midterm/requirements.txt ./requirements.txt
RUN pip install --no-cache-dir -r requirements.txt
COPY midterm/src/ ./src/
COPY db/init-analytics-db.sql ./db/init-analytics-db.sql
ENV ANALYTICS_DB_SCHEMA=/app/db/init-analytics-db.sql
EXPOSE 8084
CMD ["python", "src/main.py"]
