FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /app
COPY AssetService.csproj .
# RUN dotnet restore
COPY src/ ./src/

FROM mcr.microsoft.com/dotnet/aspnet:8.0
WORKDIR /app
# COPY --from=build /app/publish .
EXPOSE 8083
CMD ["echo", "Asset Service (C#) started on port 8083"]
