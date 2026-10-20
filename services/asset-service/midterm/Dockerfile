FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /app
COPY midterm/ ./
RUN dotnet restore
RUN dotnet publish -c Release -o /app/publish

FROM mcr.microsoft.com/dotnet/runtime:8.0 AS final
WORKDIR /app
COPY --from=build /app/publish .
ENV PORT=8083
ENV LISTEN_HOST="+"
EXPOSE 8083
ENTRYPOINT ["dotnet", "AssetService.dll"]