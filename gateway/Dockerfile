FROM node:18-alpine

WORKDIR /app

# Optionally copy package files if added
# COPY package*.json ./
# RUN npm install --production || true

COPY src/ ./src/

EXPOSE 8080

CMD ["node", "src/index.js"]
