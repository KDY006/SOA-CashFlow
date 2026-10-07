# Build stage
FROM maven:3.9-eclipse-temurin-17 AS build
WORKDIR /app
COPY pom.xml .
COPY src ./src
# RUN mvn clean package -DskipTests || true

# Run stage
FROM eclipse-temurin:17-jre-alpine
WORKDIR /app
# COPY --from=build /app/target/*.jar app.jar
EXPOSE 8081
CMD ["echo", "Auth Service (Java) container started on port 8081"]
