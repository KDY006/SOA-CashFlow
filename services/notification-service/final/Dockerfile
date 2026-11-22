FROM node:20-alpine

WORKDIR /app

# Cài đặt dependencies
COPY package*.json ./
RUN npm install --omit=dev --no-audit --no-fund

# Sao chép mã nguồn ứng dụng
COPY src/ ./src/

EXPOSE 8086

CMD ["node", "src/index.js"]
