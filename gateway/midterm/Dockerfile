FROM node:20-alpine

WORKDIR /app

# Zero-dependency Native Node.js Gateway
COPY package.json ./
COPY src/ ./src/

EXPOSE 8080

CMD ["node", "src/index.js"]
