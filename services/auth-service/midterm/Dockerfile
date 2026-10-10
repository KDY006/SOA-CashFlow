FROM maven:3.9-eclipse-temurin-17 AS build

WORKDIR /app

COPY pom.xml .

RUN mvn dependency:go-offline

COPY src ./src

RUN mvn clean package -DskipTests

RUN mvn dependency:copy-dependencies \
    -DincludeScope=runtime \
    -DoutputDirectory=target/dependency


FROM eclipse-temurin:17-jre-alpine

WORKDIR /app

COPY --from=build /app/target/classes ./classes
COPY --from=build /app/target/dependency ./dependency

EXPOSE 8081

ENTRYPOINT ["java", "-cp", "classes:dependency/*", "com.cashflow.auth.Main"]