# Bộ 7 Biểu đồ Lớp & Thành phần theo Frameworks — Phiên bản Cuối kỳ

Tài liệu này tổng hợp 7 biểu đồ lớp và kiến trúc thành phần tương ứng với **Express API Gateway** (`Hình 2-2`) và **6 Microservices Nghiệp vụ** (`Hình 2-3` → `Hình 2-8`), phản ánh chính xác tên lớp, struct, controller, service, middleware, repository và DTO trong mã nguồn `gateway/final/` và `services/*/final/`.

---

## 1. Hình 2-2: Express API Gateway (`gateway/final/`)

```mermaid
classDiagram
    class IndexServer {
        +http.Server server
        +listen(port, host)
        +onUpgrade(req, socket, head)
        +shutdown(signal)
    }

    class AppModule {
        +express.Application app
        +Array wsProxies
        +healthCheck(req, res)
        +aggregatedServicesHealth(req, res)
        +createProxyMiddleware(route)
        +notFoundHandler(req, res)
        +globalErrorHandler(err, req, res, next)
    }

    class ConfigModule {
        +String env
        +Number port
        +String host
        +String jwtSecret
        +Boolean authStrict
        +Object rateLimit
        +Object services
        +Array serviceRoutes
        +Array publicEndpoints
    }

    class RequestTracerMiddleware {
        +requestTracer(req, res, next)
        -generateUuid() String
        -measureHrTime() BigInt
    }

    class AuthGuardMiddleware {
        +authGuard(req, res, next)
        +isPublicEndpoint(method, pathname) Boolean
        +extractToken(req) String
    }

    class DocsRoutes {
        +loadSpec(key) Object
        +availableServices() Array
        +buildMergedSpec() Object
        +swaggerHtml(primaryName) String
    }

    IndexServer --> AppModule : "khởi tạo HTTP + gán wsProxies upgrade"
    AppModule --> ConfigModule : "đọc routes & whitelist"
    AppModule --> RequestTracerMiddleware : "2. X-Request-Id & JSON log"
    AppModule --> DocsRoutes : "6. Swagger UI & OpenAPI"
    AppModule --> AuthGuardMiddleware : "7. jwt.verify(HS256)"
    AuthGuardMiddleware --> ConfigModule : "kiểm tra publicEndpoints & jwtSecret"
```

*Hình 2-2: Biểu đồ Thành phần / Lớp Express API Gateway (`gateway/final`)*

---

## 2. Hình 2-3: Auth & Profile Service (`com.cashflow.auth` — Spring Boot 3.5.16)

```mermaid
classDiagram
    class AuthController {
        -AuthService authService
        -UserService userService
        +register(Requests.Register) ResponseEntity
        +login(Requests.Login) ResponseEntity
        +validate(AccountPrincipal) ResponseEntity
        +refresh(Requests.Refresh) ResponseEntity
    }

    class ProfileController {
        -UserService userService
        +get(AccountPrincipal) ResponseEntity
        +update(AccountPrincipal, Requests.Profile) ResponseEntity
        +changePassword(AccountPrincipal, Requests.PasswordChange) ResponseEntity
    }

    class AdminUserController {
        -UserService userService
        +list() ResponseEntity
        +get(long id) ResponseEntity
        +create(Requests.AdminCreate) ResponseEntity
        +update(long id, Requests.AdminUpdate) ResponseEntity
        +delete(long id) ResponseEntity
    }

    class SecurityConfig {
        +securityFilterChain(HttpSecurity, JwtDecoder, UserRepository, ObjectMapper, UrlBasedCorsConfigurationSource) SecurityFilterChain
        +corsConfigurationSource(String origins) UrlBasedCorsConfigurationSource
    }

    class JwtConfig {
        +jwtKey(Environment env) SecretKey
        +jwtEncoder(SecretKey key) JwtEncoder
        +jwtDecoder(SecretKey key) JwtDecoder
    }

    class JwtAuthenticationFilter {
        -JwtDecoder decoder
        -UserRepository users
        -ObjectMapper json
        #shouldNotFilter(HttpServletRequest) boolean
        #doFilterInternal(HttpServletRequest, HttpServletResponse, FilterChain) void
        -writeFailure(HttpServletResponse, int, String) void
    }

    class AccessTokens {
        +long EXPIRES_IN
        -JwtEncoder encoder
        +issue(User user) String
    }

    class LegacyPasswordEncoder {
        +int ITERATIONS
        +int HASH_BYTES
        +encode(CharSequence rawPassword) String
        +matches(CharSequence rawPassword, String encodedPassword) boolean
        -derive(char[] chars, byte[] salt, int iterations, int bytes) byte[]
    }

    class AuthService {
        -UserRepository users
        -RefreshTokenRepository tokens
        -PasswordEncoder passwords
        -AccessTokens accessTokens
        -String dummyHash
        +register(Requests.Register) UserView
        +login(Requests.Login) LoginResult
        +refresh(String rawToken) String
        -generateRefreshToken() String
        -digest(String rawToken) String
    }

    class UserService {
        -UserRepository users
        -RefreshTokenRepository tokens
        -PasswordEncoder passwords
        +list() List~UserView~
        +get(long id) UserView.Detailed
        +create(Requests.AdminCreate) UserView.Detailed
        +update(long id, Requests.AdminUpdate) UserView.Detailed
        +delete(long id) void
        +updateProfile(long id, Requests.Profile) UserView.Detailed
        +changePassword(long id, Requests.PasswordChange) void
        -lockedUser(long id) User
        -lockedActiveUser(long id) User
    }

    class UserRepository {
        <<interface>>
        +findByUsername(String) Optional~User~
        +findByEmail(String) Optional~User~
        +findIdByUsername(String) Optional~Long~
        +findIdByEmail(String) Optional~Long~
        +findForUpdate(long id) Optional~User~
    }

    class RefreshTokenRepository {
        <<interface>>
        +findOwnerId(String value) Optional~Long~
        +findByToken(String token) Optional~RefreshToken~
        +revokeForUser(long userId) void
    }

    class User {
        -Long id
        -String username
        -String email
        -String passwordHash
        -String fullName
        -String phoneNumber
        -String role
        -String status
        +isActive() boolean
        +update(...)
        +updateProfile(...)
        +changePassword(...)
    }

    class RefreshToken {
        -Long id
        -User user
        -String token
        -Timestamp expiryDate
        -Timestamp createdAt
        +getOwnerId() Long
    }

    class ApiErrors {
        +handleApiException(ApiException) ResponseEntity
        +handleValidation(MethodArgumentNotValidException) ResponseEntity
        +handleConflict(DataIntegrityViolationException) ResponseEntity
    }

    AuthController --> AuthService
    AuthController --> UserService
    ProfileController --> UserService
    AdminUserController --> UserService
    SecurityConfig --> JwtAuthenticationFilter
    JwtAuthenticationFilter --> JwtConfig : "dùng JwtDecoder"
    JwtAuthenticationFilter --> UserRepository : "đối chiếu ACTIVE & role"
    AuthService --> UserRepository
    AuthService --> RefreshTokenRepository
    AuthService --> AccessTokens
    AuthService --> LegacyPasswordEncoder
    UserService --> UserRepository
    UserService --> RefreshTokenRepository
    UserService --> LegacyPasswordEncoder
    UserRepository --> User
    RefreshTokenRepository --> RefreshToken
    RefreshToken --> User : "ManyToOne LAZY"
```

*Hình 2-3: Biểu đồ Lớp Auth & Profile Service (`Spring Boot 3.5.16`)*

---

## 3. Hình 2-4: Transaction & Budget Service (`App\Http` — Laravel 12)

```mermaid
classDiagram
    class ResolveUser {
        +handle(Request request, Closure next) Response
    }

    class Controller {
        <<abstract>>
    }

    class CashflowController {
        <<abstract>>
        #userId(Request request) int
        #owned(string table, Request request) Builder
        #record(string table, Request request, int id) object
        #ok(mixed data, int status) JsonResponse
        #category(Request request, int id, string type) object
        #moneyRule() array
    }

    class CategoryController {
        +index(Request request) JsonResponse
        +show(Request request, int category) JsonResponse
        +store(Request request) JsonResponse
        +update(Request request, int category) JsonResponse
        +destroy(Request request, int category) JsonResponse
        -save(Request request, int? id) JsonResponse
    }

    class TransactionController {
        +index(Request request) JsonResponse
        +show(Request request, int transaction) JsonResponse
        +totals(Request request) array
        +balance(Request request) JsonResponse
        +store(Request request) JsonResponse
        +update(Request request, int transaction) JsonResponse
        +destroy(Request request, int transaction) JsonResponse
        -save(Request request, int? id) JsonResponse
    }

    class BudgetController {
        +index(Request request) JsonResponse
        +show(Request request, int budget) JsonResponse
        +report(Request request) array
        +progress(Request request) JsonResponse
        +store(Request request) JsonResponse
        +update(Request request, int budget) JsonResponse
        +destroy(Request request, int budget) JsonResponse
        -save(Request request, int? id) JsonResponse
    }

    class DatabaseSeeder {
        +run() void
    }

    class DBQueryBuilder {
        <<Laravel Facade>>
        +table(string name) Builder
        +transaction(Closure callback) mixed
        +lockForUpdate() Builder
    }

    Controller <|-- CashflowController
    CashflowController <|-- CategoryController
    CashflowController <|-- TransactionController
    CashflowController <|-- BudgetController
    ResolveUser ..> CashflowController : "gắn user_id vào request attributes"
    TransactionController ..> BudgetController : "gọi report() tính budget_alerts"
    CategoryController --> DBQueryBuilder : "DB::table('categories') + lockForUpdate"
    TransactionController --> DBQueryBuilder : "DB::table('transactions') + BCMath"
    BudgetController --> DBQueryBuilder : "DB::table('budgets') + CarbonImmutable"
    DatabaseSeeder --> DBQueryBuilder : "seed idempotent transaction_final_db"
```

*Hình 2-4: Biểu đồ Lớp Transaction & Budget Service (`Laravel 12`)*

---

## 4. Hình 2-5: Asset & Investment Service (`AssetService.Final` — ASP.NET Core 8 + EF Core 8)

```mermaid
classDiagram
    class ExceptionMiddleware {
        -RequestDelegate _next
        +InvokeAsync(HttpContext context) Task
    }

    class CurrentUser {
        -IHttpContextAccessor _accessor
        +TryReadId(ClaimsPrincipal? principal, out long userId)$ bool
        +Resolve(long? queryId, long? bodyId) long
    }

    class AssetsController {
        -AssetManager _manager
        -NetWorthService _netWorth
        -CurrentUser _currentUser
        +List(long? user_id, CancellationToken ct) Task
        +Get(long id, CancellationToken ct) Task
        +Create(AssetInput input, CancellationToken ct) Task
        +Update(long id, AssetInput input, CancellationToken ct) Task
        +Delete(long id, CancellationToken ct) Task
        +Depreciation(DepreciationInput input) IActionResult
        +NetWorth(long? user_id, CancellationToken ct) Task
        +History(long id, CancellationToken ct) Task
        +Valuation(long id, DateTime? as_of, CancellationToken ct) Task
    }

    class LoansController {
        -LoanService _service
        -CurrentUser _currentUser
        +List(long? user_id, CancellationToken ct) Task
        +Get(long id, CancellationToken ct) Task
        +Create(LoanInput input, CancellationToken ct) Task
        +Update(long id, LoanInput input, CancellationToken ct) Task
        +Delete(long id, CancellationToken ct) Task
        +Calculate(LoanCalculationInput input) IActionResult
        +Schedule(long id, CancellationToken ct) Task
        +SetStatus(long id, LoanStatusInput input, CancellationToken ct) Task
    }

    class InvestmentsController {
        -InvestmentService _service
        -CurrentUser _currentUser
        +List(long? user_id, CancellationToken ct) Task
        +Get(long id, CancellationToken ct) Task
        +Create(InvestmentInput input, CancellationToken ct) Task
        +Update(long id, InvestmentInput input, CancellationToken ct) Task
        +Delete(long id, CancellationToken ct) Task
        +Summary(long? user_id, CancellationToken ct) Task
    }

    class HealthController {
        -AssetDbContext _db
        +Health() IActionResult
        +Ready(CancellationToken ct) Task
    }

    class AssetManager {
        -AssetDbContext _db
        +List(long user, CancellationToken ct) Task
        +Get(long user, long id, CancellationToken ct) Task
        +Create(long user, AssetInput x, CancellationToken ct) Task
        +Update(long user, long id, AssetInput x, CancellationToken ct) Task
        +Delete(long user, long id, CancellationToken ct) Task
        +History(long user, long id, CancellationToken ct) Task
        +Valuation(long user, long id, DateTime? asOf, CancellationToken ct) Task
    }

    class LoanService {
        -AssetDbContext _db
        +List(long user, CancellationToken ct) Task
        +Get(long user, long id, CancellationToken ct) Task
        +Create(long user, LoanInput x, CancellationToken ct) Task
        +Update(long user, long id, LoanInput x, CancellationToken ct) Task
        +Delete(long user, long id, CancellationToken ct) Task
        +SetStatus(long user, long id, string status, CancellationToken ct) Task
        +Schedule(long user, long id, CancellationToken ct) Task
    }

    class InvestmentService {
        -AssetDbContext _db
        +List(long user, CancellationToken ct) Task
        +Get(long user, long id, CancellationToken ct) Task
        +Create(long user, InvestmentInput x, CancellationToken ct) Task
        +Update(long user, long id, InvestmentInput x, CancellationToken ct) Task
        +Delete(long user, long id, CancellationToken ct) Task
        +Summary(long user, CancellationToken ct) Task
    }

    class NetWorthService {
        -AssetDbContext _db
        +Get(long user, CancellationToken ct) Task~NetWorthResponse~
    }

    class FinanceMath {
        <<static>>
        +decimal MaxMoney
        +int MaxTerm
        +Money(decimal value, string name)$ decimal
        +Depreciation(DepreciationInput x)$ DepreciationResult
        +CalculateLoan(LoanCalculationInput x)$ LoanCalculationResult
        +Schedule(decimal principal, decimal rate, int months, DateTime start)$ List~LoanInstallment~
        +BookValue(decimal purchase, decimal salvage, int life, decimal elapsedYears)$ decimal
    }

    class AssetDbContext {
        +DbSet~Asset~ Assets
        +DbSet~Loan~ Loans
        +DbSet~Investment~ Investments
        +DbSet~AssetHistory~ AssetHistory
        #OnModelCreating(ModelBuilder) void
    }

    class Asset {
        +long Id
        +long UserId
        +decimal CurrentValue
        +List~AssetHistory~ History
    }

    class AssetHistory {
        +long Id
        +long AssetId
        +decimal Value
        +DateTime RecordedAt
    }

    AssetsController --> AssetManager
    AssetsController --> NetWorthService
    AssetsController --> CurrentUser
    AssetsController --> FinanceMath
    LoansController --> LoanService
    LoansController --> CurrentUser
    LoansController --> FinanceMath
    InvestmentsController --> InvestmentService
    InvestmentsController --> CurrentUser
    HealthController --> AssetDbContext
    AssetManager --> AssetDbContext
    AssetManager --> FinanceMath
    LoanService --> AssetDbContext
    LoanService --> FinanceMath
    InvestmentService --> AssetDbContext
    NetWorthService --> AssetDbContext : "RepeatableRead"
    AssetDbContext --> Asset
    AssetDbContext --> AssetHistory
    Asset "1" *-- "0..*" AssetHistory
```

*Hình 2-5: Biểu đồ Lớp Asset & Investment Service (`ASP.NET Core 8 + EF Core 8`)*

---

## 5. Hình 2-6: Analytics Service (`services/analytics-service/final/src/main.py` — FastAPI 0.110.0)

```mermaid
classDiagram
    class FastAPIApp {
        +String title
        +String version
        +lifespan(app) AsyncContextManager
        +CORSMiddleware cors
        +health() dict
        +get_summary(...) dict
        +get_category_breakdown(...) dict
        +get_trends(...) dict
        +get_anomalies(...) dict
        +get_reports(...) dict
        +aggregate(AggregateRequest) dict
    }

    class AnalyticsError {
        +int status_code
        +str code
        +str message
    }

    class TransactionInput {
        <<Pydantic BaseModel>>
        +int|str|None id
        +int|None user_id
        +float amount
        +str type
        +str|None category
        +str|None category_name_rel
        +date|datetime|str|None transaction_date
        +date|datetime|str|None date
    }

    class AggregateRequest {
        <<Pydantic BaseModel>>
        +int user_id
        +date|None from_date
        +date|None to_date
        +list~TransactionInput~|None transactions
    }

    class AnalyticsEngine {
        <<Module Functions>>
        +_connect() PyMySQLConnection
        +_init_database() void
        +_resolve_user_id(requested, x_user_id, x_user_role) int
        +_normalise(rows) list~dict~
        +_fetch_transactions(user_id, start, end) list~dict~
        +_summary(rows) dict
        +_category_breakdown(rows) list~dict~
        +_trends(rows, months) dict
        +_anomalies(rows) list~dict~
        +_save_report(user_id, report) void
        +_load_reports(user_id, year, month) list~dict~
    }

    FastAPIApp --> AggregateRequest : "validate body qua Pydantic v2"
    AggregateRequest *-- TransactionInput
    FastAPIApp --> AnalyticsEngine : "điều phối truy vấn & tính toán"
    FastAPIApp --> AnalyticsError : "@app.exception_handler"
    AnalyticsEngine ..> AnalyticsError : "raise 400 / 502"
```

*Hình 2-6: Biểu đồ Thành phần Analytics Service (`FastAPI 0.110.0 + Pydantic v2`)*

---

## 6. Hình 2-7: Integration & Concurrency Service (`internal/` — Go 1.22 Gin + GORM)

```mermaid
classDiagram
    class Server {
        -gorm.DB db
        -config.Config cfg
        -locker.KeyedLocker locker
        -services.Syncer syncer
        -services.Notifier notifier
        -http.Client httpClient
        +SetupRouter() gin.Engine
        +HandleHealth(c *gin.Context)
        +HandleStats(c *gin.Context)
        +HandleGetRates(c *gin.Context)
        +HandleSyncRates(c *gin.Context)
        +HandleListAccounts(c *gin.Context)
        +HandleCreateAccount(c *gin.Context)
        +HandleGetAccount(c *gin.Context)
        +HandleDeleteAccount(c *gin.Context)
        +HandleAccountTransactions(c *gin.Context)
        +HandleBankWebhook(c *gin.Context)
        +HandleRetrySync(c *gin.Context)
        +HandleSimulate(c *gin.Context)
    }

    class KeyedLocker {
        -sync.Mutex mu
        -map~string, lockEntry~ locks
        +Lock(key string) func()
        +Size() int
    }

    class Syncer {
        -gorm.DB db
        -config.Config cfg
        -http.Client client
        -Notifier notifier
        -chan int64 queue
        -sync.WaitGroup wg
        +Start(ctx context.Context)
        +Wait()
        +Enqueue(id int64)
        +Pending() int
        -worker(ctx context.Context, n int)
        -sweeper(ctx context.Context)
        -sweep(ctx context.Context)
        -syncOne(ctx context.Context, id int64)
        -forward(ctx context.Context, t BankTransaction, userID int64)
    }

    class Notifier {
        -string baseURL
        -http.Client client
        +Send(userID int64, title string, message string, typ string, meta map)
    }

    class DatabaseModule {
        <<package database>>
        +Open(cfg config.Config) gorm.DB
        +MySQLErrNo(err error) uint16
        +IsDuplicateKey(err error) bool
        +IsRetryable(err error) bool
    }

    class Money {
        <<int64 fixed-point 1/100 VND>>
        +Float() float64
        +String() string
        +MarshalJSON() byte[]
        +Scan(src any) error
        +Value() driver.Value
    }

    class LinkedAccount {
        +int64 ID
        +int64 UserID
        +string BankCode
        +string AccountNo
        +Money Balance
        +int64 Version
    }

    class BankTransaction {
        +int64 ID
        +int64 AccountID
        +string Provider
        +string ExternalRef
        +string IdempotencyKey
        +string RequestHash
        +string Direction
        +Money Amount
        +Money BalanceAfter
        +string Status
        +int64 TransactionRef
        +int SyncAttempts
    }

    Server --> KeyedLocker : "khóa theo BankCode:AccountNo"
    Server --> Syncer : "đẩy job sync nền"
    Server --> Notifier : "cảnh báo LARGE_TRANSACTION / REJECTED"
    Server --> DatabaseModule : "GORM READ COMMITTED + FOR UPDATE"
    Syncer --> Notifier : "cảnh báo BUDGET_ALERT"
    Syncer --> BankTransaction : "claim SYNCING -> SYNCED/FAILED"
    LinkedAccount "1" -- "0..*" BankTransaction
    LinkedAccount --> Money
    BankTransaction --> Money
```

*Hình 2-7: Biểu đồ Lớp Integration & Concurrency Service (`Go 1.22 Gin + GORM`)*

---

## 7. Hình 2-8: Notification & OTP Service (`Express 4 + Socket.io 4 + ioredis`)

```mermaid
classDiagram
    class EntryIndex {
        +http.Server server
        +initSocket(server)
        +initRedis()
        +onRedisMessage(channel, rawMessage)
        +gracefulShutdown()
    }

    class NotificationController {
        +resolveUserId(req) Number
        +getNotifications(req, res)
        +getUnreadCount(req, res)
        +getNotificationById(req, res)
        +createNotification(req, res)
        +markAsRead(req, res)
        +markAllAsRead(req, res)
        +deleteNotification(req, res)
        +streamSse(req, res)
    }

    class OtpController {
        +generateOtp(req, res)
        +verifyOtp(req, res)
    }

    class OtpService {
        -Map otpStore
        +generateCode(length) String
        +createOtp(params) Object
        +verifyOtp(params) Object
    }

    class SocketModule {
        -SocketIO.Server io
        +initSocket(httpServer)
        +emitToUser(userId, event, data) Number
        +broadcastAll(event, data)
        +getSocketClientCount() Number
    }

    class SseModule {
        -Map userConnections
        +registerClient(userId, req, res)
        +broadcastToUser(userId, notification) Number
        +getClientCount() Number
    }

    class RedisModule {
        -IORedis redisPub
        -IORedis redisSub
        +initRedis()
        +onMessage(handler)
        +publish(channel, data)
        +getStatus() Object
    }

    class NotificationStore {
        -Array notifications
        +seedInitialData()
        +addNotification(data) Object
        +getByUser(userId, opts) Object
        +markAsRead(id, userId) Object
        +markAllAsRead(userId) Number
        +getUnreadCount(userId) Number
    }

    EntryIndex --> SocketModule
    EntryIndex --> RedisModule
    EntryIndex --> SseModule
    EntryIndex --> NotificationStore
    NotificationController --> NotificationStore
    NotificationController --> SseModule
    NotificationController --> SocketModule
    NotificationController --> RedisModule
    OtpController --> OtpService
    OtpService --> NotificationStore
    OtpService --> SseModule
    OtpService --> SocketModule
    OtpService --> RedisModule
```

*Hình 2-8: Biểu đồ Thành phần Notification & OTP Service (`Express 4 + Socket.io 4 + ioredis`)*
