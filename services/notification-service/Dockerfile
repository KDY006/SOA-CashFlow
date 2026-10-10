FROM node:20-alpine

WORKDIR /app

# Hỗ trợ build từ context ./services/notification-service hoặc ./services/notification-service/midterm
COPY midterm/package.json ./package.json
COPY midterm/src/ ./src/

EXPOSE 8086

CMD ["node", "src/index.js"]
