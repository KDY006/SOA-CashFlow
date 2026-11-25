FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /src
COPY AssetService.csproj ./
RUN dotnet restore AssetService.csproj
COPY . ./
RUN dotnet publish AssetService.csproj -c Release -o /app/publish --no-restore
FROM mcr.microsoft.com/dotnet/aspnet:8.0 AS runtime
WORKDIR /app
COPY --from=build /app/publish ./
ENV ASPNETCORE_URLS=http://+:8083
EXPOSE 8083
USER app
ENTRYPOINT ["dotnet", "AssetService.dll"]
