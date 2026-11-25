FROM mcr.microsoft.com/dotnet/sdk:10.0 AS build
WORKDIR /app
COPY AssetService.csproj ./
RUN dotnet restore
COPY . ./
RUN dotnet publish -c Release -o /app/publish --no-restore

FROM mcr.microsoft.com/dotnet/runtime:10.0 AS runtime
WORKDIR /app
COPY --from=build /app/publish .
ENV PORT=8083
ENV LISTEN_HOST="+"
EXPOSE 8083
ENTRYPOINT ["dotnet", "AssetService.dll"]
