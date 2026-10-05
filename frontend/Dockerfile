# Multi-stage Dockerfile cho Frontend TDTU iBanking (React Vite)

# Stage 1: Build source code
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: Serve static bundle với Nginx
FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html

# Cấu hình Nginx hỗ trợ SPA fallback (tránh 404 khi F5)
RUN echo 'server { \
    listen 80; \
    location / { \
        root /usr/share/nginx/html; \
        index index.html index.htm; \
        try_files $uri $uri/ /index.html; \
    } \
}' > /etc/nginx/conf.d/default.conf

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
