FROM node:20-alpine

WORKDIR /app

# Hỗ trợ build trực tiếp từ context ./services/notification-service
COPY final/package*.json ./
RUN npm install --omit=dev --no-audit --no-fund

COPY final/src/ ./src/

EXPOSE 8086

CMD ["node", "src/index.js"]
