# Bộ Biểu đồ Lớp & Cấu trúc Mô-đun Thiết kế (Midterm — 100% Native Code)

Tài liệu này tổng hợp 7 biểu đồ lớp / mô-đun (`Hình 2-2` → `Hình 2-8`) phản ánh chính xác các lớp, struct, mô-đun, record DTO và phương thức thực tế trong mã nguồn `gateway/midterm/` và `services/*/midterm/`.

---

## Hình 2-2: Biểu đồ Mô-đun Thiết kế API Gateway (Node.js 20 Native)

```mermaid
classDiagram
    class IndexModule {
        <<module src/index.js>>
        -Map~string,object~ rateLimitMap
        -int RATE_LIMIT_WINDOW_MS = 60000
        -int RATE_LIMIT_MAX_REQUESTS = 300
        +isRateLimited(clientIp) boolean
        +sendJson(res, statusCode, data) void
        +handleRequest(req, res) void
    }

    class ConfigModule {
        <<module src/config.js>>
        +int port
        +string host
        +string jwtSecret
        +boolean authStrict
        +object services
        +Array routeMap
        +Array publicEndpoints
    }

    class JwtModule {
        <<module src/jwt.js>>
        +verifyJwt(token, secret) object
        +extractBearerToken(authHeader) string
    }

    class ProxyModule {
        <<module src/proxy.js>>
        +forwardRequest(req, res, targetBaseUrl, context) void
    }

    class DocsModule {
        <<module src/docs.js>>
        +Array SERVICES
        +loadSpec(key) object
        +availableServices() Array
        +buildMergedSpec() object
        +swaggerHtml(primaryName) string
        +handleDocs(pathname, res) boolean
    }

    IndexModule --> ConfigModule : reads routeMap & publicEndpoints
    IndexModule --> JwtModule : verifyJwt() & extractBearerToken()
    IndexModule --> ProxyModule : forwardRequest()
    IndexModule --> DocsModule : handleDocs()
```

---

## Hình 2-3: Biểu đồ Lớp Auth & Profile Service (Java 17 Native)

```mermaid
classDiagram
    class Main {
        +main(String[] args) void
    }

    class HttpHandler {
        <<interface>>
        +handle(HttpExchange exchange) void
    }

    class RegisterHandler {
        -AuthService authService
        +handle(HttpExchange exchange) void
    }
    class LoginHandler {
        -AuthService authService
        +handle(HttpExchange exchange) void
    }
    class RefreshHandler {
        -AuthService authService
        +handle(HttpExchange exchange) void
    }
    class ValidateHandler {
        -UserRepository userRepository
        +handle(HttpExchange exchange) void
    }
    class ProfileHandler {
        -AuthService authService
        +handle(HttpExchange exchange) void
        -authenticate(HttpExchange exchange) long
    }
    class AdminUserHandler {
        -AuthService authService
        +handle(HttpExchange exchange) void
    }

    class AuthService {
        -UserRepository userRepository
        -RefreshTokenRepository refreshTokenRepository
        +register(username, email, password, fullName, phoneNumber) User
        +login(username, password) Map
        +getAllUsers() List~User~
        +getUserById(long id) User
        +createUserByAdmin(...) User
        +updateUserByAdmin(...) User
        +deleteUserByAdmin(long id) boolean
        +updateProfile(long userId, fullName, phoneNumber) User
        +changePassword(long userId, currentPassword, newPassword) void
    }

    class UserRepository {
        +existsByUsername(String username) boolean
        +existsByEmail(String email) boolean
        +findByUsername(String username) User
        +findByEmail(String email) User
        +findById(long id) User
        +create(User user) long
        +findAll() List~User~
        +update(User user) boolean
        +updatePassword(long id, String hash) boolean
        +deleteById(long id) boolean
        -mapUser(ResultSet rs) User
    }

    class RefreshTokenRepository {
        +save(long userId, String token, Timestamp expiryDate) void
        +findUserIdByToken(String token) Long
        +deleteByToken(String token) void
        +deleteByUserId(long userId) void
    }

    class DatabaseConfig {
        +getConnection() Connection
    }

    class User {
        -long id
        -String username
        -String email
        -String passwordHash
        -String fullName
        -String phoneNumber
        -String role
        -String status
        -Timestamp createdAt
        -Timestamp updatedAt
    }

    class PasswordUtil {
        -int ITERATIONS = 210000
        -int KEY_LENGTH = 256
        -int SALT_LENGTH = 16
        +hashPassword(String password) String
        +verifyPassword(String password, String storedHash) boolean
    }

    class JwtUtil {
        -long EXPIRATION_SECONDS = 3600
        +generateToken(long userId, String username, String role) String
        +validateToken(String token) boolean
        +getUserId(String token) long
        +getUsername(String token) String
        +getRole(String token) String
    }

    class RefreshTokenUtil {
        +generateToken() String
    }

    class JsonUtil {
        +getObjectMapper() ObjectMapper
        +toJson(Object value) String
    }

    class MessageDigestUtil {
        +constantTimeEquals(String a, String b) boolean
    }

    Main --> RegisterHandler
    Main --> LoginHandler
    Main --> RefreshHandler
    Main --> ValidateHandler
    Main --> ProfileHandler
    Main --> AdminUserHandler

    HttpHandler <|.. RegisterHandler
    HttpHandler <|.. LoginHandler
    HttpHandler <|.. RefreshHandler
    HttpHandler <|.. ValidateHandler
    HttpHandler <|.. ProfileHandler
    HttpHandler <|.. AdminUserHandler

    RegisterHandler --> AuthService
    LoginHandler --> AuthService
    RefreshHandler --> AuthService
    ProfileHandler --> AuthService
    AdminUserHandler --> AuthService
    ValidateHandler --> UserRepository

    AuthService --> UserRepository
    AuthService --> RefreshTokenRepository
    AuthService --> PasswordUtil
    AuthService --> JwtUtil
    AuthService --> RefreshTokenUtil

    UserRepository --> DatabaseConfig
    RefreshTokenRepository --> DatabaseConfig
    UserRepository --> User
    PasswordUtil ..> MessageDigestUtil : constant-time compare
    JwtUtil ..> MessageDigestUtil : signature verify
    LoginHandler ..> JsonUtil
```

---

## Hình 2-4: Biểu đồ Lớp Transaction & Budget Service (PHP 8.2 Native PSR-4 OOP)

```mermaid
classDiagram
    class FrontRouter {
        <<script src/index.php>>
        +spl_autoload_register() void
        +dispatch(method, path) void
    }

    class TransactionController {
        -Transaction transactionModel
        -Budget budgetModel
        -Category categoryModel
        +index() void
        +show(int id) void
        +store() void
        +update(int id) void
        +delete(int id) void
    }

    class BudgetController {
        -Budget budgetModel
        -Transaction transactionModel
        -Category categoryModel
        +index() void
        +progress() void
        +show(int id) void
        +store() void
        +update(int id) void
        +delete(int id) void
    }

    class CategoryController {
        -Category categoryModel
        +index() void
        +store() void
    }

    class Transaction {
        -PDO db
        +getAll(array filters) array
        +getById(int id) ?array
        +create(array data) int
        +update(int id, array data) bool
        +delete(int id) bool
        +getTotalExpenseByCategory(int userId, int categoryId, string startDate, string endDate) float
    }

    class Budget {
        -PDO db
        +getAll(?int userId) array
        +getById(int id) ?array
        +findActiveBudget(int userId, int categoryId, ?string date) ?array
        +create(array data) int
        +update(int id, array data) bool
        +delete(int id) bool
    }

    class Category {
        -PDO db
        +getAll(?string type) array
        +getById(int id) ?array
        +getByName(string name) ?array
        +create(array data) int
    }

    class Database {
        <<Singleton>>
        -PDO instance
        +getConnection() PDO
    }

    class Auth {
        +headerUserId() int
        +isAdmin() bool
        +resolveUserId(?array body) int
        +forbiddenFor(int ownerId) bool
    }

    class Response {
        +json(array data, int statusCode) void
        +success(mixed data, string message, int statusCode) void
        +error(string message, int statusCode, mixed errors) void
    }

    FrontRouter --> TransactionController
    FrontRouter --> BudgetController
    FrontRouter --> CategoryController

    TransactionController --> Transaction
    TransactionController --> Budget
    TransactionController --> Category
    TransactionController ..> Auth
    TransactionController ..> Response

    BudgetController --> Budget
    BudgetController --> Transaction
    BudgetController --> Category
    BudgetController ..> Auth
    BudgetController ..> Response

    CategoryController --> Category
    CategoryController ..> Response

    Transaction --> Database
    Budget --> Database
    Category --> Database
```

---

## Hình 2-5: Biểu đồ Lớp Asset & Investment Service (C# .NET 10 Native)

```mermaid
classDiagram
    class Program {
        +Main(string[] args) Task
    }

    class Router {
        +DispatchAsync(HttpListenerContext ctx) Task
    }

    class AssetHandler {
        +GetAssets(long userId) object
        +CreateAsset(AssetInput x) object
        +UpdateAsset(long id, long userId, AssetInput x) object
        +DeleteAsset(long id, long userId) object
        +CalculateDepreciation(DepreciationInput x) object
        -ValidateAsset(AssetInput x) void
        -AddAssetParameters(MySqlCommand q, AssetInput x) void
    }

    class LoanHandler {
        +GetLoans(long userId) object
        +CreateLoan(LoanInput x) object
        +UpdateLoan(long id, long userId, LoanInput x) object
        +DeleteLoan(long id, long userId) object
        +CalculateLoan(LoanCalculationInput x) object
        -ValidateLoan(LoanInput x) void
    }

    class InvestmentHandler {
        +GetInvestments(long userId) object
        +CreateInvestment(InvestmentInput x) object
        +UpdateInvestment(long id, long userId, InvestmentInput x) object
        +DeleteInvestment(long id, long userId) object
        -ValidateInvestment(InvestmentInput x) void
    }

    class AnalyticsHandler {
        +GetNetWorth(long userId) object
    }

    class Database {
        +GetConnection() MySqlConnection
        +Query(string sql, long userId) List
        +GetById(string table, long id, long userId) Dictionary
    }

    class HttpHelper {
        +JsonSerializerOptions JsonOptions
        +ReadBodyAsync~T~(HttpListenerRequest req) Task~T~
        +WriteJsonAsync(HttpListenerResponse res, int status, object data) Task
        +AuthUserId(HttpListenerRequest req) long
        +IsAdmin(HttpListenerRequest req) bool
        +ResolveUserId(HttpListenerRequest req) long
        +BodyUserId(HttpListenerRequest req, long bodyUserId) long
        +TryParseId(string raw, out long id) bool
    }

    class AssetInput {
        <<record>>
        +long UserId
        +string AssetName
        +string AssetType
        +decimal PurchasePrice
        +decimal CurrentValue
        +decimal SalvageValue
        +int UsefulLifeYears
        +DateTime? PurchaseDate
        +string? Currency
        +string? Note
    }

    class DepreciationInput {
        <<record>>
        +decimal PurchasePrice
        +decimal SalvageValue
        +int UsefulLifeYears
    }

    class LoanInput {
        <<record>>
        +long UserId
        +string LoanTitle
        +decimal PrincipalAmount
        +decimal InterestRate
        +int TermMonths
        +DateTime StartDate
        +string? Status
    }

    class LoanCalculationInput {
        <<record>>
        +decimal PrincipalAmount
        +decimal InterestRate
        +int TermMonths
    }

    class InvestmentInput {
        <<record>>
        +long UserId
        +string Symbol
        +string InvestmentType
        +decimal Quantity
        +decimal BuyPrice
        +decimal CurrentPrice
    }

    Program --> Router : Task.Run(DispatchAsync)
    Router --> AssetHandler
    Router --> LoanHandler
    Router --> InvestmentHandler
    Router --> AnalyticsHandler
    Router --> HttpHelper

    AssetHandler --> Database
    LoanHandler --> Database
    InvestmentHandler --> Database
    AnalyticsHandler --> Database

    AssetHandler ..> AssetInput
    AssetHandler ..> DepreciationInput
    LoanHandler ..> LoanInput
    LoanHandler ..> LoanCalculationInput
    InvestmentHandler ..> InvestmentInput
```

---

## Hình 2-6: Biểu đồ Lớp & Mô-đun Analytics Service (Python 3.11 Native)

```mermaid
classDiagram
    class ThreadingHTTPServer {
        <<http.server>>
        +serve_forever() void
        +server_close() void
    }

    class AnalyticsHandler {
        <<BaseHTTPRequestHandler>>
        -_send(status, payload) void
        -_body() dict
        -_user_id(requested) int
        +do_OPTIONS() void
        +do_GET() void
        +do_POST() void
        +log_message(format, *args) void
    }

    class _DatabaseConnection {
        -PyMySQLConnection raw
        +execute(sql, params) Cursor
        +executescript(script) void
        +commit() void
        +rollback() void
        +close() void
    }

    class AnalyticsCoreModule {
        <<module src/main.py>>
        +_init_database() void
        +_connect_database() _DatabaseConnection
        +_database() ContextManager
        +_fetch_transactions(user_id, start, end) list
        +_normalise(rows) list
        +_period(rows, start, end) list
        +summary(rows) dict
        +category_breakdown(rows) list
        +trends(rows, months) dict
        +anomalies(rows) list
        +_save_report(user_id, report) int
        +_load_reports(user_id, year, month) list
    }

    ThreadingHTTPServer --> AnalyticsHandler : dispatches requests
    AnalyticsHandler --> AnalyticsCoreModule : invokes math & DB helpers
    AnalyticsCoreModule --> _DatabaseConnection : wraps PyMySQL DictCursor
```

---

## Hình 2-7: Biểu đồ Cấu trúc & Mô-đun Integration & Concurrency Service (Go 1.22 Native)

```mermaid
classDiagram
    class Server {
        <<struct server.go>>
        +Config cfg
        +*sql.DB db
        +*keyedLocker locker
        +*syncer syncer
        +*notifier notifier
        +time.Time startedAt
        +routes() http.Handler
        +handleBankWebhook(w, r) void
        +handleSimulate(w, r) void
        +handleGetRates(w, r) void
        +handleSyncRates(w, r) void
        +handleAccountTransactions(w, r) void
        +applyWebhook(ctx, in) webhookResult
    }

    class Config {
        <<struct config.go>>
        +string Port
        +string DBHost
        +string DBPort
        +string DBName
        +string TransactionURL
        +string NotificationURL
        +string WebhookSecret
        +int SyncWorkers
        +money LargeAmount
        +time.Duration HTTPTimeout
    }

    class keyedLocker {
        <<struct locker.go>>
        -sync.Mutex mu
        -map~string,keyLock~ locks
        +Lock(key string) func()
        +size() int
    }

    class keyLock {
        <<struct locker.go>>
        +sync.Mutex mu
        +int refs
    }

    class syncer {
        <<struct sync.go>>
        -*sql.DB db
        +Config cfg
        +*http.Client client
        +*notifier notifier
        +chan~int64~ queue
        +start(ctx) void
        +enqueue(id int64) void
        +pending() int
        -worker(ctx, n int) void
        -sweeper(ctx) void
        -sweep(ctx) void
        -syncOne(ctx, id int64) error
        -forward(ctx, t bankTx, userID int64) tuple
    }

    class notifier {
        <<struct notifier.go>>
        +string baseURL
        +*http.Client client
        +send(userID, title, message, typ, meta) void
    }

    class money {
        <<int64 1/100 VND>>
        +Float() float64
        +String() string
        +MarshalJSON() byte[]
        +Scan(src) error
        +Value() driver.Value
    }

    class webhookRequest {
        <<struct webhook.go>>
        +int64 AccountID
        +string Provider
        +string ExternalRef
        +string BankCode
        +string AccountNo
        +string Direction
        +float64 Amount
        +string Category
        +string Description
        +string OccurredAt
    }

    class webhookInput {
        <<struct webhook.go>>
        +int64 AccountID
        +string Provider
        +string ExternalRef
        +string IdempotencyKey
        +string RequestHash
        +string BankCode
        +string AccountNo
        +string Direction
        +money Amount
        +time.Time OccurredAt
    }

    class bankTx {
        <<struct webhook.go>>
        +int64 ID
        +int64 AccountID
        +string Provider
        +string ExternalRef
        +string IdempotencyKey
        +string Direction
        +money Amount
        +money BalanceAfter
        +string Status
        +int64 TransactionRef
        +int SyncAttempts
    }

    Server --> Config
    Server --> keyedLocker
    Server --> syncer
    Server --> notifier
    keyedLocker "1" o-- "0..*" keyLock : ref-counted locks
    syncer --> notifier : sends BUDGET_ALERT
    Server ..> webhookRequest : binds input
    Server ..> webhookInput : normalizes & hashes
    Server ..> bankTx : persists & returns
    bankTx --> money : uses int64 cents
    webhookInput --> money : uses int64 cents
```

---

## Hình 2-8: Biểu đồ Mô-đun Notification Service (Node.js 20 Native)

```mermaid
classDiagram
    class NotificationIndex {
        <<module src/index.js>>
        +sendJson(res, statusCode, data) void
        +parseJsonBody(req) Promise~object~
        +headerUserIdFrom(req) number
        +isAdmin(req) boolean
        +resolveUserId(req, paramId, bodyUserId, queryUserId) number
        +handleHttpRequest(req, res) void
    }

    class SseModule {
        <<module src/sse.js>>
        -Map~number,Set~ userConnections
        +registerClient(userId, req, res) void
        +broadcastToUser(userId, notification) number
        +broadcastToAll(notification) number
        +getClientCount() number
    }

    class RedisClientModule {
        <<module src/redisClient.js>>
        -boolean isConnected
        -function messageHandler
        -Socket nativeSocket
        +onNotification(handler) void
        +initRedis() void
        +publishNotification(data) void
        +getStatus() object
    }

    class StoreModule {
        <<module src/store.js>>
        -Array notifications
        -number nextId
        +seedInitialData() void
        +addNotification(payload) object
        +getByUser(userId, options) object
        +markAsRead(id, userId) object
        +markAllAsRead(userId) number
    }

    class ConfigModule {
        <<module src/config.js>>
        +number port = 8086
        +string host
        +object redis
        +Array notificationTypes
    }

    NotificationIndex --> ConfigModule
    NotificationIndex --> SseModule : registerClient() & broadcastToUser()
    NotificationIndex --> RedisClientModule : publishNotification() & onNotification()
    NotificationIndex --> StoreModule : addNotification(), getByUser(), markAsRead()
```
