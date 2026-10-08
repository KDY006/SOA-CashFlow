# Build native C# service
FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
WORKDIR /app

# Copy file .csproj để restore các package (như MySql.Data)
COPY AssetService.csproj .
RUN dotnet restore

# Copy toàn bộ mã nguồn vào và tiến hành publish ứng dụng
COPY . .
RUN dotnet publish -c Release -o /app/publish

# Use the same .NET major version as the project target
FROM mcr.microsoft.com/dotnet/runtime:10.0
WORKDIR /app

# Copy kết quả đã build từ giai đoạn trên sang
COPY --from=build /app/publish .

# Mở port 8083 theo đúng API Spec của dịch vụ Asset Service
EXPOSE 8083

# Khởi chạy file DLL của dịch vụ
ENTRYPOINT ["dotnet", "AssetService.dll"]
