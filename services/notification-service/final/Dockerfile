FROM node:18-alpine

WORKDIR /app

COPY package*.json ./
# RUN npm install --production || true

COPY src/ ./src/

EXPOSE 8086

CMD ["node", "src/index.js"]
