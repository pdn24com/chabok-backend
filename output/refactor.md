# Laravel Modular DDD Simplification & Refactor Prompt

من یک پروژه Laravel Modular با رویکرد DDD دارم که در حال حاضر بیش از حد پیچیده و Over-engineered شده است.

هدف این Refactor این نیست که DDD یا Modular Architecture را کامل حذف کنیم؛ هدف این است که ساختار پروژه به شکلی **ساده، Laravel-native، قابل فهم، قابل نگهداری و مناسب یک Mid-Level Developer** تبدیل شود.

اولویت اصلی:

> Simplicity > Abstraction > Cleverness

**تمام مثال‌های این Prompt فقط نمونه‌ای از مشکلات موجود در بخش کوچکی از Codebase هستند. Scope این Refactor کل پروژه است. هر Pattern مشابه یا هم‌خانواده باید در سراسر Codebase شناسایی و اصلاح شود، حتی اگر فایل یا Syntax دقیق آن در این Prompt ذکر نشده باشد.**

**معیار اصلی موفقیت این Refactor این است که کد توسط یک Junior / Low-level Developer که آشنایی عمیقی با پروژه، معماری و Business Domain ندارد نیز قابل فهم، قابل دنبال کردن و قابل توسعه باشد.**

Developer نباید برای انجام یک تغییر ساده مجبور باشد:

- چندین Layer غیرضروری را یاد بگیرد.
- Business Context پنهان را حدس بزند.
- بین ده‌ها فایل برای فهم یک Flow جابه‌جا شود.
- Dynamic array shapeها را از روی usage کشف کند.
- Naming مبهم را Decode کند.
- Magic String و Magic Number را حفظ کند.
- Implementation detailهای Database یا Framework را برای فهم Business Flow بداند.
- قبل از تغییر یک Feature، Architecture پیچیده پروژه را کامل یاد بگیرد.

کد باید تا جای ممکن واضح باشد و Developer برای فهمیدن یک Flow ساده مجبور نباشد بین تعداد زیادی Interface، Repository، UseCase، Mapper، Service و Helper جابه‌جا شود.

---

## 1. Business Behavior نباید تغییر کند

قبل از هر Refactor:

- Flow موجود را بررسی کن.
- Business Ruleها را شناسایی کن.
- API Contractها را حفظ کن.
- Request/Responseهای موجود را بدون دلیل تغییر نده.
- Behavior سیستم را تغییر نده.
- Refactor باید تا جای ممکن Behavior-Preserving باشد.

اگر برای یک تغییر معماری نیاز به تغییر Behavior وجود دارد، ابتدا آن را گزارش کن و بدون دلیل انجام نده.

---

## 2. کد باید برای Developer معمولی قابل خواندن باشد

از موارد زیر تا حد ممکن خودداری کن:

- Nested Functions
- Nested Arrow Functions
- Callbackهای پیچیده
- چندین abstraction برای یک عملیات ساده
- Generic Architectureهای غیرضروری
- Magic Code
- Helperهای مبهم
- Service Locator
- Dynamic Resolution غیرضروری
- Reflection-based architecture
- Interfaceهایی که فقط یک Implementation دارند و Boundary واقعی ایجاد نمی‌کنند

کد Explicit و واضح را به کد کوتاه ولی پیچیده ترجیح بده.

مثلاً چنین کدی:

```php
if (
    array_filter(
        $context['module_entitlements'] ?? [],
        fn ($entitlement) =>
            ($entitlement['module_code'] ?? null) === 'LiveOperations'
            && ($entitlement['status'] ?? null) === 'ENABLED'
    ) === []
) {
```

نباید باقی بماند.

آن را به یک Flow واضح‌تر تبدیل کن.

مثلاً:

```php
$hasLiveOperationsAccess = collect($moduleEntitlements)
    ->contains(function ($entitlement) {
        return $entitlement['module_code'] === 'LiveOperations'
            && $entitlement['status'] === 'ENABLED';
    });
```

یا یک Method/Service معنی‌دار، در صورتی که واقعاً چند جا استفاده می‌شود.

هدف readability است، نه صرفاً کم کردن تعداد خطوط.

---

## 3. DB::table و Query Builder مستقیم حذف شوند

تا حد ممکن تمام مواردی مانند:

```php
DB::table(...)
DB::select(...)
DB::statement(...)
```

به Eloquent تبدیل شوند.

استفاده از Raw SQL فقط زمانی قابل قبول است که:

1. واقعاً Eloquent راه منطقی و قابل نگهداری نداشته باشد.
2. Performance Requirement مشخصی وجود داشته باشد.
3. دلیل استفاده در کد یا Documentation توضیح داده شود.

Raw SQL نباید راه‌حل پیش‌فرض باشد.

---

## 4. Raw Recursive Queryها حذف شوند

کدهایی شبیه این:

```php
public function descendantAreaIds(string $hqId, string $areaId): array
{
    return array_map(
        static fn ($row): string => (string) $row->area_id,
        DB::select(<<<'SQL'
        WITH RECURSIVE descendants AS (
            SELECT child_area_id AS area_id FROM area_hierarchies
            WHERE hq_id = ? AND parent_area_id = ?
            UNION ALL
            SELECT h.child_area_id FROM area_hierarchies h
            JOIN descendants d ON h.parent_area_id = d.area_id
            WHERE h.hq_id = ?
        )
        SELECT DISTINCT area_id FROM descendants
        SQL, [$hqId, $areaId, $hqId])
    );
}
```

در Application Code نباید وجود داشته باشند.

برای Hierarchical Data ابتدا بررسی کن آیا می‌توان با:

- Eloquent Relationships
- Recursive Relationships
- dedicated AreaHierarchyService
- Laravel Collection
- یک Eloquent-oriented implementation

مسئله را حل کرد.

پیاده‌سازی باید readable باشد.

اگر واقعاً Recursive CTE برای Performance لازم است، آن را پشت یک Component مشخص و محدود قرار بده و اجازه نده Raw SQL در Application Layer پخش شود.

---

## 5. Joinهای Application Code با Eager Loading جایگزین شوند

در جاهایی که Relationship واقعی بین Modelها وجود دارد از Eloquent Relationships و Eager Loading استفاده کن:

```php
Order::query()
    ->with([
        'customer',
        'branch',
        'driver',
    ])
    ->get();
```

به جای Queryهایی که دستی چندین Table را Join می‌کنند.

Join فقط زمانی استفاده شود که واقعاً برای Query خاص، Reporting، Aggregation یا Performance لازم باشد.

از N+1 نیز جلوگیری کن.

---


## 6. Eloquent Modelها ساده و قابل فهم باشند

Eloquent Model باید مسئول موارد طبیعی خودش باشد:

- Relationships
- Casts
- Scopes ساده
- Attribute configuration
- Model-specific behavior کوچک

مثلاً:

```php
class Order extends Model
{
    protected $casts = [
        'status' => OrderStatus::class,
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
```

Business Workflowهای بزرگ داخل Model قرار نگیرند.

---

## 7. Eloquent Result نباید بی‌دلیل Array شود

در حال حاضر در بخش‌های مختلف:

```php
$model->toArray();
$collection->toArray();
```

زیاد استفاده شده است.

این رفتار را حذف کن مگر جایی که واقعاً Array لازم باشد.

در Application Layer ترجیح بده:

```text
Model
Collection
DTO
Enum
Value Object
```

بین Layerها حرکت کنند.

Eloquent Query باید تا جای ممکن `Collection<Order>` یا Model برگرداند.

تبدیل به Array باید در Boundary مناسب انجام شود، مثلاً:

```text
API Resource
Transformer
Presenter
Serializer
```

نه وسط Business Logic.

---

## 8. Command / Request / DTOها به Array تبدیل نشوند

اگر یک Command Object داریم:

```text
CreateOrderCommand
```

در طول Flow آن را بارها تبدیل نکن:

```php
$command->toArray();
```

و سپس:

```php
$data['branch_id']
$data['customer_id']
```

تا حد ممکن از خود Object استفاده کن:

```php
$command->branchId
$command->customerId
```

DTOها باید Typed باشند.

مثلاً:

```php
final readonly class CreateOrderData
{
    public function __construct(
        public int $branchId,
        public int $customerId,
        public OrderType $type,
    ) {}
}
```

از associative array برای انتقال Domain/Application Data تا حد ممکن خودداری کن.

---


## 9. انتقال Data بین UseCase، Service و Repository با DTO انجام شود

برای پاس دادن Data ساختاریافته بین لایه‌های مختلف پروژه، از DTO استفاده کن.

این Rule به صورت سراسری برای موارد زیر اعمال شود:

- UseCase → Service
- Service → Service
- UseCase → Repository
- Service → Repository
- Repository → Application Layer، در جاهایی که خروجی صرفاً Model/Collection نیست
- Controller → UseCase، در صورت نیاز به Data Object مشخص

از associative array برای انتقال Business/Application Data بین این لایه‌ها استفاده نکن.

مثلاً این ساختار مناسب نیست:

```php
$orderService->create([
    'customer_id' => $customerId,
    'branch_id' => $branchId,
    'priority' => $priority,
]);
```

ترجیح بده:

```php
$orderData = new CreateOrderData(
    customerId: $customerId,
    branchId: $branchId,
    priority: $priority,
);

$orderService->create($orderData);
```

همچنین این مناسب نیست:

```php
$repository->findByFilters([
    'status' => $status,
    'branch_id' => $branchId,
    'from' => $from,
    'to' => $to,
]);
```

ترجیح بده:

```php
$filters = new OrderFilterData(
    status: $status,
    branchId: $branchId,
    from: $from,
    to: $to,
);

$repository->findByFilters($filters);
```

DTOها باید:

- Typed باشند.
- Propertyهای مشخص داشته باشند.
- ترجیحاً `final readonly` باشند اگر Mutable بودن لازم نیست.
- Naming واضح و وابسته به Use Case داشته باشند.
- فقط Data حمل کنند.
- Business Logic سنگین نداشته باشند.
- Validation مربوط به HTTP را داخل خودشان انجام ندهند مگر Convention مشخص پروژه این باشد.
- از `array<string, mixed>` تا حد ممکن دوری کنند.

مثال:

```php
final readonly class AssignDriverData
{
    public function __construct(
        public int $orderId,
        public int $driverId,
        public int $branchId,
    ) {}
}
```

و در Service:

```php
public function assign(AssignDriverData $data): Order
{
    // ...
}
```

### استثنا

اگر Repository یا Service ذاتاً باید یک Eloquent Model یا Collection برگرداند، بی‌دلیل آن را داخل DTO نپیچ.

مثلاً این کاملاً قابل قبول است:

```php
public function findById(int $id): ?Order
```

یا:

```php
public function activeDrivers(DriverFilterData $filters): Collection
```

هدف DTO این نیست که همه‌چیز Wrap شود؛ هدف این است که **Data Structureهای چندفیلدی و associative arrayهای مبهم بین Layerها حذف شوند**.

همچنین DTO را صرفاً برای یک `int` یا `string` ساده ایجاد نکن.

مثلاً این نیازی به DTO ندارد:

```php
public function findById(int $orderId): ?Order
```

اما اگر چند پارامتر مرتبط با یک Context داریم، DTO ترجیح داده شود.

اصل کلی:

> Structured data between layers → DTO

> Entity/result data → Model / Collection when appropriate

> Avoid associative arrays in UseCases, Services and Repositories



### امضای توابع باید Typed و صریح باشد

در تمام `UseCase`ها، `Service`ها، `Repository`ها، `Adapter`ها و سایر Application Components، ورودی و خروجی متدها باید تا حد ممکن ساختار مشخص و قابل فهم داشته باشند.

از Signatureهای مبهم مانند این خودداری کن:

```php
public function attach(
    AuthenticatedPrincipal $actor,
    string $userId,
    array $input,
    string $correlationId,
): ?array
```

مشکل این Signature این است که Developer از روی تعریف تابع نمی‌فهمد:

- داخل `$input` چه Keyهایی وجود دارد.
- کدام Keyها Required هستند.
- کدام مقادیر Nullable هستند.
- `kind` و `mode` چه مقادیری می‌توانند داشته باشند.
- ساختار `driver` یا `node` چیست.
- خروجی Array چه ساختاری دارد.

در چنین شرایطی DTO مشخص تعریف کن.

مثلاً:

```php
final readonly class AttachOperationalProfileData
{
    public function __construct(
        public OperationalProfileKind $kind,
        public OperationalProfileMode $mode,
        public ?CreateDriverData $driver,
        public ?CreateNodeData $node,
        public ?int $existingId,
        public ?int $expectedVersion,
        public ?int $roleId,
    ) {}
}
```

و Signature را به شکل واضح بنویس:

```php
public function attach(
    AuthenticatedPrincipal $actor,
    int $userId,
    AttachOperationalProfileData $data,
    string $correlationId,
): ?OperationalProfileAssignmentData
```

خروجی ساختاریافته نیز به جای Array باید DTO یا Object مشخص باشد.

به جای:

```php
return [
    'role_id' => $input['role_id'],
    'scope_type' => 'NODE',
    'scope_id' => $node['node_id'],
    'includes_descendants' => false,
];
```

ترجیح بده:

```php
return new OperationalProfileAssignmentData(
    roleId: $data->roleId,
    scopeType: ScopeType::NODE,
    scopeId: $node->id,
    includesDescendants: false,
);
```

همچنین متدهایی مانند:

```php
public function forUser(
    AuthenticatedPrincipal $actor,
    string $userId,
): ?array
```

اگر خروجی ساختار مشخص دارد باید به صورت Typed باشد:

```php
public function forUser(
    AuthenticatedPrincipal $actor,
    int $userId,
): ?DriverProfileData
```

یا اگر Entity واقعی مورد نیاز است:

```php
public function forUser(
    AuthenticatedPrincipal $actor,
    int $userId,
): ?Driver
```

### Array Access در Business/Application Code تا حد ممکن حذف شود

کدهایی مثل:

```php
$input['kind']
$input['mode']
$input['driver']
$input['existing_id']
$input['expected_version']
$node['status']
$node['node_id']
```

اگر Structure مشخصی دارند باید با Property Access جایگزین شوند:

```php
$data->kind
$data->mode
$data->driver
$data->existingId
$data->expectedVersion
$node->status
$node->id
```

هدف این است که Contract داده از روی Type و Signature تابع مشخص باشد، نه اینکه Developer مجبور شود داخل Body تابع بگردد تا بفهمد Array چه ساختاری دارد.

### DTOهای مختلف برای Use Caseهای متفاوت

از DTOهای بیش از حد Generic مانند موارد زیر خودداری کن:

```text
DataDto
RequestData
InputDto
PayloadDto
CommonData
OperationData
```

DTO باید Intent مشخص داشته باشد:

```text
CreateDriverData
UpdateDriverData
AttachOperationalProfileData
AssignNodeScopeData
DriverFilterData
CreateShipmentData
```

اگر `CREATE` و `UPDATE` ورودی‌های بسیار متفاوت دارند، ترجیح بده DTOهای مجزا داشته باشند به جای یک DTO بزرگ با تعداد زیادی Property nullable.

مثلاً در صورت مناسب بودن:

```php
CreateOperationalProfileData
AttachExistingOperationalProfileData
```

بهتر از این است:

```php
OperationalProfileData
```

که تعداد زیادی فیلد nullable دارد و Behavior آن به `mode` وابسته است.

### از DTO به عنوان جایگزین Array استفاده کن، نه صرفاً Wrapper آن

این کار مناسب نیست:

```php
final readonly class ExampleData
{
    public function __construct(
        public array $data,
    ) {}
}
```

یا:

```php
final readonly class DriverData
{
    public function __construct(
        public array $driver,
    ) {}
}
```

اگر Structure مشخص است، Propertyهای DTO نیز باید مشخص و Typed باشند.

### تبدیل DTO به Array فقط در Boundary لازم انجام شود

اگر Eloquent برای `create()` یا `update()` به Array نیاز دارد، تبدیل DTO به Array باید در نزدیک‌ترین نقطه به Persistence انجام شود.

مثلاً:

```php
Driver::query()->create([
    'user_id' => $data->userId,
    'display_name' => $data->displayName,
    'home_node_id' => $data->homeNodeId,
]);
```

نه اینکه DTO از ابتدای Flow به Array تبدیل شود و همان Array در تمام Layerها پاس داده شود.

در نتیجه Flow ترجیحی این است:

```text
Request
  ↓
Typed DTO
  ↓
UseCase
  ↓
Typed DTO
  ↓
Service
  ↓
Typed DTO / Model
  ↓
Repository / Eloquent
  ↓
Model / Collection / Typed DTO
```

نه:

```text
Request
  ↓
Array
  ↓
UseCase
  ↓
Array
  ↓
Service
  ↓
Array
  ↓
Repository
  ↓
Array
```

اصل سراسری پروژه:

> Known structure → Typed DTO / Object

> Do not use generic `array $input` for known application data

> Do not return anonymous associative arrays for known result structures

> Convert to array only at the boundary that actually requires an array

---

## 10. UseCaseها بسیار ساده شوند

هر UseCase فقط باید Entry Point اصلی خودش را داشته باشد.

Convention پروژه باید یکسان باشد.

یکی از این دو Convention را برای کل پروژه انتخاب کن:

```php
public function execute(...)
```

یا:

```php
public function handle(...)
```

این دو Convention نباید به‌صورت Random در پروژه Mix شوند.

UseCase نباید ده‌ها private method داشته باشد.

اگر Logic واقعاً مستقل است آن را به Service مناسب منتقل کن.

UseCase باید بیشتر شبیه Orchestrator باشد.

---

## 11. Serviceها ساده و Cohesive باشند

Service نباید تبدیل به محل Dump شدن Logic شود.

هر Service باید مسئولیت مشخص داشته باشد.

خوب:

```text
OrderPricingService
DriverAssignmentService
AreaHierarchyService
AuthorizationService
```

بد:

```text
CommonService
UtilityService
HelperService
GeneralService
OperationsManagerService
```

Serviceهای خیلی بزرگ را براساس Responsibility تقسیم کن.

اما از ایجاد Serviceهای بسیار کوچک و بی‌ارزش نیز خودداری کن.

---

## 12. Service Interfaceها کاملاً یکپارچه و اجباری باشند

در کل پروژه باید برای Serviceها یک Convention واحد وجود داشته باشد.

حتی اگر یک Service فقط یک Implementation دارد، باز هم باید Interface / Contract مخصوص خودش را داشته باشد تا ساختار Dependency Injection و معماری در تمام Moduleها یکسان و قابل پیش‌بینی باشد.

یعنی این حالت:

```text
Application/
  Contracts/
    AuthorizationGuardInterface.php
    OrderPricingServiceInterface.php
    AreaHierarchyServiceInterface.php

  Services/
    AuthorizationGuard.php
    OrderPricingService.php
    AreaHierarchyService.php
```

به صورت یک Convention سراسری در پروژه استفاده شود.

مثلاً:

```php
interface OrderPricingServiceInterface
{
    public function calculate(Order $order): Money;
}
```

و:

```php
final class OrderPricingService implements OrderPricingServiceInterface
{
    public function calculate(Order $order): Money
    {
        // ...
    }
}
```

سپس Dependencyها نیز با Interface تزریق شوند:

```php
public function __construct(
    private OrderPricingServiceInterface $orderPricingService,
) {}
```

و Binding مربوطه در Service Provider مشخص و ساده باشد:

```php
$this->app->bind(
    OrderPricingServiceInterface::class,
    OrderPricingService::class,
);
```

قواعد این بخش:

- تمام Serviceهای پروژه باید Contract/Interface داشته باشند.
- نام‌گذاری Interfaceها در تمام Moduleها یکسان باشد.
- Interfaceها در Folder مشخص مانند `Contracts/` یا `Interfaces/` قرار بگیرند؛ یکی را انتخاب کن و در کل پروژه همان را استفاده کن.
- Implementationها در `Services/` قرار بگیرند.
- هیچ بخشی از پروژه Service را مستقیم Inject نکند اگر برای آن Contract تعریف شده است.
- ترکیب استفاده مستقیم از Service و استفاده از Interface در بخش‌های مختلف پروژه نباید وجود داشته باشد.
- Bindingها باید متمرکز، ساده و قابل دنبال کردن باشند.
- از چند Interface غیرضروری برای یک Service خودداری کن؛ هر Service یک Contract واضح داشته باشد.
- Interface فقط API واقعی Service را تعریف کند و نباید شامل متدهای اضافی، Infrastructure detail یا abstractionهای پیچیده باشد.

هدف اینجا حذف Interface نیست؛ هدف **Consistency و Predictability** در کل پروژه است.

---

## 13. Dependency Injection Naming استاندارد شود

وقتی یک Service Inject می‌شود نام Property دقیقاً از نام Service گرفته شود.

مثلاً:

```php
public function __construct(
    private AuthorizationGuard $authorizationGuard,
    private OrderPricingService $orderPricingService,
    private AreaHierarchyService $areaHierarchyService,
) {}
```

از نام‌هایی مثل:

```text
$guard
$service
$svc
$auth
$manager
$helper
```

برای Dependency مشخص استفاده نکن.

Naming در کل پروژه consistent باشد.

---

## 14. Fully Qualified Class Name داخل Constructor استفاده نشود

این کار:

```php
private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard
```

نباید وجود داشته باشد.

همه Dependencies بالای فایل Import شوند:

```php
use Modules\Authorization\Application\Services\AuthorizationGuard;
```

و بعد:

```php
public function __construct(
    private AuthorizationGuard $authorizationGuard,
) {}
```

این Rule برای کل پروژه اعمال شود.

---

## 15. Shared Helperها یکپارچه شوند

اگر Logic ساده‌ای چندین بار تکرار شده است، ابتدا بررسی کن که واقعاً Helper لازم است یا خیر.

مثلاً:

```php
private function principal(Request $request): AuthenticatedPrincipal
{
    return $request->attributes->get('principal');
}
```

نباید در ده‌ها Controller کپی شود.

دو انتخاب داریم:

### Option A — اگر فقط یک خط ساده است

مستقیم استفاده شود:

```php
$principal = $request->attributes->get('principal');
```

### Option B — اگر Behavior/Validation مشترک دارد

یک abstraction واحد ایجاد شود.

مثلاً:

```text
AuthenticatedPrincipalResolver
```

ولی برای یک خط ساده، Service جدید ایجاد نکن.

---

## 16. Folder Structure تمیز شود

در حال حاضر تعداد زیادی Class متفاوت مستقیماً داخل Folderهایی مانند:

```text
Application/
Operations/
Domain/
```

ریخته شده‌اند.

هر Class باید در Folder منطقی خودش قرار گیرد.

مثلاً:

```text
Modules/
  Orders/
    Application/
      UseCases/
      DTOs/
      Services/

    Domain/
      Models/
      Enums/
      Exceptions/
      Services/
      ValueObjects/

    Infrastructure/
      Persistence/
      ExternalServices/

    Presentation/
      Http/
        Controllers/
        Requests/
        Resources/

    Policies/
```

برای مواردی مانند:

- Services
- Policies
- Exceptions
- Scopes
- Enums
- DTOs
- Actions / UseCases
- Resources
- Requests
- Events
- Listeners
- Jobs

Folder مشخص داشته باش.

اما Folder Structure را بیش از حد Deep نکن.

---

## 17. Controllerها Thin باشند

Controller فقط باید کارهای زیر را انجام دهد:

1. دریافت Request
2. Validation از FormRequest
3. ساخت DTO در صورت نیاز
4. صدا زدن UseCase/Service
5. Return Resource/Response

Controller نباید Business Logic داشته باشد.

---

## 18. FormRequest برای Validation

Validationهای HTTP داخل Controller یا UseCase پخش نشوند.

از `FormRequest` استفاده کن.

تفاوت این دو را حفظ کن:

```text
Input Validation
Business Validation
```

---

## 19. Laravel Resource برای API Response

به جای اینکه UseCase یا Service آرایه Response بسازد، Model یا DTO را برگرداند.

Formatting API در `Http/Resources` انجام شود.

---

## 20. Migrationها باید بسیار ساده، Laravel-native و فقط مسئول Schema باشند

Migrationها در این پروژه نباید تبدیل به محل اجرای Business Logic، Validation، Seed Data، Trigger Management یا Runtime Policy شوند.

Migration باید تا حد ممکن فقط با API استاندارد Laravel Schema نوشته شود:

```php
Schema::create('operational_statuses', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('hq_id')->nullable();
    $table->string('code', 32);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['hq_id', 'code']);
    $table->index(['hq_id', 'is_active']);
});
```

و برای تغییر جدول:

```php
Schema::table('consignments', function (Blueprint $table) {
    $table->string('current_status', 32)->change();
});
```

اصل:

> Migration = schema change only.

> Keep migrations boring, explicit and predictable.

### `DB::statement`, `DB::unprepared` و Raw SQL در Migration تا حد ممکن حذف شوند

کدهایی مثل:

```php
DB::statement(...);
DB::unprepared(...);
```

نباید روش عادی Migration باشند.

به‌خصوص برای موارد زیر Raw SQL ایجاد نکن:

- Trigger
- Validation rule
- Immutability rule
- Status validation
- Business invariant
- Dynamic DDL
- Runtime access rule

اگر Laravel Schema API قابلیت مورد نیاز را دارد، فقط از همان استفاده کن.

اگر یک Constraint کاملاً Database-level و ضروری است و Laravel Schema API نسخه پروژه واقعاً آن را پوشش نمی‌دهد، آن مورد باید:

1. استثنایی باشد.
2. دلیل واضح داشته باشد.
3. در یک Migration کوچک و مستقل قرار بگیرد.
4. با Business Logic و Seed Data ترکیب نشود.

اما Default پروژه باید **بدون Raw SQL Migration** باشد.

### Triggerها در Migrationهای عادی پروژه ایجاد نشوند

Patternهایی مانند:

```sql
CREATE TRIGGER ...
BEFORE UPDATE ...
BEFORE INSERT ...
BEFORE DELETE ...
```

تا حد ممکن از پروژه حذف شوند.

Business Ruleهایی مثل:

```text
Status must exist
Status identity is immutable
Revision cannot be changed
Entity cannot be deleted
```

باید ابتدا با ساختارهای ساده‌تر حل شوند:

- Application/Domain validation
- Service / UseCase rule
- Policy
- Model event در صورت مناسب بودن
- Foreign key
- Unique constraint
- NOT NULL
- Standard database constraint

Trigger فقط اگر Requirement دیتابیسی بسیار مشخص و غیرقابل جایگزین وجود داشته باشد مجاز است؛ نه به‌عنوان معماری پیش‌فرض.

هدف این است که Behavior سیستم با خواندن Laravel code قابل فهم باشد و Developer مجبور نباشد برای فهمیدن Behavior به Triggerهای مخفی Database مراجعه کند.

### Schema Change و Seed Data در یک Migration مخلوط نشوند

این Pattern مناسب نیست:

```php
Schema::create('operational_statuses', ...);

foreach (
    json_decode(
        file_get_contents(...),
        true,
    ) as $status
) {
    DB::table('operational_statuses')->insert(...);
}
```

Migration نباید:

- JSON file بخواند.
- Reference catalog import کند.
- UUID تولید کند.
- `now()` برای seed record بسازد.
- Loop روی seed data اجرا کند.

Reference Data باید در Concern جدا مدیریت شود، مثلاً:

```text
Seeder
Dedicated bootstrap service
Explicit application setup command
```

با توجه به نیاز واقعی پروژه.

اگر برای Deploy حتماً Data Migration لازم است، آن را از Schema Migration جدا و تک‌مسئولیتی نگه دار.

### Migration نباید به فایل‌های Resources وابسته باشد

این نوع dependency:

```php
file_get_contents(
    __DIR__ . '/../../resources/operational-statuses.json'
);
```

داخل Migration نباید وجود داشته باشد.

Migration باید deterministic و self-contained باشد و اجرای آن وابسته به وجود یک Resource File با Structure خارجی نباشد.

### Permission / Role / Access Data داخل Migration ساخته نشود

Migration نباید برای ساخت یا Assign کردن Permission، Role، Role-Permission mapping یا سایر Authorization Data استفاده شود.

Patternهایی مثل:

```php
$permissionId = DB::table('permissions')
    ->where('permission_code', $permissionCode)
    ->value('permission_id');

DB::table('permissions')->updateOrInsert(...);

DB::table('roles')
    ->where(...)
    ->pluck('role_id');

DB::table('role_permissions')->insertOrIgnore(...);
```

داخل Migration مناسب نیستند.

این نوع Logic یک Schema Change نیست؛ بلکه Application Bootstrap / Reference Data / Authorization Setup است.

آن را براساس ساختار پروژه به یکی از Concernهای مناسب منتقل کن:

```text
Seeder
AuthorizationSeeder
PermissionSeeder
RolePermissionSeeder
Application Bootstrap Command
Explicit Setup / Sync Command
```

اگر Permissionها باید در هر Deploy به صورت idempotent sync شوند، یک Command یا Service واضح برای sync کردن آن‌ها داشته باش؛ Migration را به Deployment Script مخفی برای Authorization تبدیل نکن.

مثلاً:

```php
final class SyncPermissionsCommand extends Command
{
    public function handle(
        PermissionSynchronizer $permissionSynchronizer,
    ): int {
        $permissionSynchronizer->sync();

        return self::SUCCESS;
    }
}
```

یا Seeder ساده، اگر Requirement پروژه را پوشش می‌دهد.

### Authorization bootstrap باید Eloquent / Service-based و Typed باشد

اگر Permission و Role در Application Model واقعی دارند، برای bootstrap نیز تا حد امکان از همان Model / Service استفاده کن و `DB::table()` پراکنده نساز.

مثلاً به جای:

```php
DB::table('permissions')->updateOrInsert(...);
```

ترجیح:

```php
Permission::query()->updateOrCreate(
    ['code' => PermissionCode::MANAGE_OPERATIONAL_STATUS->value],
    [
        'module' => ModuleCode::CONSIGNMENT,
        'resource' => PermissionResource::OPERATIONAL_STATUS,
        'action' => PermissionAction::MANAGE,
        'status' => PermissionStatus::ACTIVE,
    ],
);
```

نام Model/Enum واقعی را براساس Domain پروژه انتخاب کن.

هدف این است که Authorization Data نیز همان Contract و Naming استاندارد پروژه را رعایت کند.

### Permission Codeها و Authorization Keyها Magic String نباشند

Stringهایی مانند:

```text
operational_status.manage
Consignment
operational_status
manage
ACTIVE
GLOBAL
hq_admin
```

اگر Contract شناخته‌شده و تکرارشونده پروژه هستند، باید در Type/Enum/Constant مشخص قرار بگیرند.

مثلاً:

```php
PermissionCode::MANAGE_OPERATIONAL_STATUS
ModuleCode::CONSIGNMENT
PermissionStatus::ACTIVE
RoleCode::HQ_ADMIN
```

یا ساختار ساده‌تر متناسب با پروژه.

هدف جلوگیری از Typo و پراکندگی Vocabulary مربوط به Authorization است.

### UUID generation برای Permission/Role mapping با Strategy کل پروژه هماهنگ باشد

Patternهایی مثل:

```php
(string) Str::uuid()
```

برای `permission_id` یا `role_permission_id` باید با Primary Key Strategy کل پروژه بررسی شوند.

اگر Primary Keyهای داخلی پروژه `INT AUTO_INCREMENT` هستند، UUID به‌عنوان Primary Key جدید تولید نکن.

در صورت نیاز به Public UUID:

```text
id          INT AUTO_INCREMENT PRIMARY KEY
public_uuid UUID UNIQUE
```

همان Convention کل پروژه رعایت شود.

### `up()` نباید Runtime Query و Conditional Data Mutation سنگین داشته باشد

Migration نباید برای تصمیم‌گیری درباره Data موجود، Query بزند و براساس نتیجه Permission یا Assignment ایجاد کند.

مثلاً:

```php
$id = DB::table(...)
    ->value(...)
    ?? (string) Str::uuid();
```

این Logic deployment/bootstrap logic است، نه schema migration.

Schema Migration باید مستقل از وضعیت Business Data تا حد ممکن قابل پیش‌بینی باشد.

### `down()` خالی یا Comment-only نشانه Migration نامناسب است

این Pattern:

```php
public function down(): void
{
    /* Keep assignments and audit-compatible permission identity. */
}
```

نشان می‌دهد فایل بیشتر Data Bootstrap است تا Migration.

اگر چیزی نباید rollback شود، آن عملیات احتمالاً نباید به‌عنوان Schema Migration نوشته شده باشد.

برای Data Bootstrap از Seeder/Command با behavior صریح استفاده کن.

### One-line Migration Code ممنوع

این Style:

```php
$id=DB::table(...)->where(...)->value(...) ?? (string)Str::uuid();
```

یا:

```php
foreach(...) DB::table(...)->insertOrIgnore(...);
```

نباید در پروژه باقی بماند.

حتی برای Migration/Seeder ساده نیز formatting و readability باید همان استاندارد کل پروژه را داشته باشد:

```php
foreach ($roles as $role) {
    $rolePermissionService->assignPermission(
        $role,
        $permission,
    );
}
```

کوتاه کردن فایل با حذف line break یا block structure ارزش معماری ندارد.

### Migrationها Dynamic Programming نداشته باشند

کدهایی مانند:

```php
private array $columns = [...];

foreach ($this->columns as $table => $columns) {
    foreach ($columns as $column) {
        DB::statement(...);
    }
}
```

برای Migration مناسب نیستند.

حتی اگر چند خط بیشتر شود، Schema Changeها را explicit بنویس:

```php
Schema::table('consignments', function (Blueprint $table) {
    $table->string('current_status', 32)->change();
});

Schema::table('parcels', function (Blueprint $table) {
    $table->string('current_status', 32)->change();
});
```

Migration جایی نیست که DRY بودن را به قیمت خوانایی دنبال کنیم.

اصل:

> Explicit migration code is better than clever migration code.

### هر Migration باید یک هدف مشخص داشته باشد

یک Migration نباید همزمان:

```text
Create operational statuses
Create revisions
Change consignment columns
Change parcel columns
Create triggers
Insert catalog data
Add checks
Define rollback business policy
```

را انجام دهد.

این‌ها باید به Migrationهای کوچک‌تر و مستقل تقسیم شوند.

مثلاً:

```text
create_operational_statuses_table
create_operational_status_revisions_table
change_consignment_status_columns
add_operational_status_indexes
```

هر فایل باید از روی نامش مشخص کند چه Schema Changeای انجام می‌دهد.

### Primary Key در Migrationها `INT AUTO_INCREMENT` باشد

طبق Standard این پروژه، Primary Keyهای داخلی باید:

```text
INT AUTO_INCREMENT
```

باشند.

در Laravel ترجیح:

```php
$table->increments('id');
```

نه:

```php
$table->uuid('status_id')->primary();
$table->uuid('revision_id')->primary();
```

اگر Public UUID یا External Identifier لازم است، آن را جدا نگه دار:

```php
$table->increments('id');
$table->uuid('public_uuid')->unique();
```

UUID نباید بدون Requirement مشخص Primary Key داخلی Database باشد.

Foreign Keyها نیز باید با Type همان Primary Key هماهنگ باشند.

مثلاً:

```php
$table->unsignedInteger('status_id');

$table->foreign('status_id')
    ->references('id')
    ->on('operational_statuses')
    ->restrictOnDelete();
```

### Naming استاندارد Laravel برای Primary Key ترجیح داده شود

تا حد امکان از:

```text
id
```

برای Primary Key استفاده کن.

و Foreign Key:

```text
status_id
hq_id
user_id
```

به جای Primary Keyهایی مثل:

```text
status_id as PK
revision_id as PK
route_plan_id as PK
```

که باعث می‌شوند Modelها دائماً configuration اضافه نیاز داشته باشند.

اگر دلیل Domain/Legacy مشخصی وجود ندارد، Convention خود Laravel را انتخاب کن.

### Constraintها ساده و declarative باشند

Constraintهای استاندارد را در Schema تعریف کن:

```php
$table->unique(...);
$table->index(...);
$table->foreign(...);
$table->nullable(...);
```

Business validation را با Constraintهای پیچیده و SQL expressionهای سخت‌خوان داخل Migration پیاده نکن.

مثلاً Regex validation برای Status Code:

```sql
CHECK (code REGEXP ...)
```

اگر یک Domain Rule است، در Validation/Value Object/Service مربوطه نیز باید واضح باشد.

Database constraint فقط در صورت نیاز به Data Integrity اضافه شود و نباید تنها محل تعریف Business Rule باشد.

### `down()` باید ساده و predictable باشد

این نوع rollback مناسب نیست:

```php
if (
    DB::table(...)->exists()
    || DB::table(...)->exists()
) {
    throw new RuntimeException(...);
}
```

`down()` نباید Business Decision بگیرد یا براساس Runtime Data تصمیم بگیرد که rollback مجاز هست یا نه.

ترجیح:

```php
public function down(): void
{
    Schema::dropIfExists('operational_status_revisions');
    Schema::dropIfExists('operational_statuses');
}
```

اگر Migration ذاتاً safe rollback ندارد، این موضوع باید در Deployment Strategy مدیریت شود؛ نه با Logic پیچیده داخل `down()`.

### Migration نباید Application Runtime Logic داشته باشد

داخل Migration از موارد زیر استفاده نکن مگر ضرورت بسیار مشخص:

- Application Service
- Domain Service
- Repository
- API Exception
- Business Enum برای Runtime decision
- Clock abstraction
- Identifier generator
- HTTP concerns
- Authorization

Migration باید Infrastructure schema definition باقی بماند.

### Formatting Migration باید خوانا باشد

این Style:

```php
$t->uuid('status_id')->primary(); $t->uuid('hq_id')->nullable(); $t->string('owner_key',36);
```

قابل قبول نیست.

هر Statement در خط خودش:

```php
$table->increments('id');
$table->unsignedInteger('hq_id')->nullable();
$table->string('owner_key', 36);
$table->string('code', 32);
```

Closureهای یک‌خطی و چند statement در یک خط در Migrationها حذف شوند.

### Table/Column Definitionها نیز Naming واضح داشته باشند

نام‌هایی مثل:

```text
owner_key
active_slot
lock
```

اگر Meaning آن‌ها بدون context مشخص نیست، بررسی و Rename شوند.

Schema Naming نیز باید همان Naming Ruleهای کل پروژه را رعایت کند.

### Lock Tableهای مصنوعی بررسی و حذف شوند

جدول‌هایی مثل:

```text
operational_status_catalog_lock
```

اگر فقط برای workaround یا synchronization سفارشی ساخته شده‌اند باید بررسی شوند.

ابتدا از قابلیت‌های استاندارد Laravel/Database برای:

- Transaction
- Locking
- Unique constraint
- Atomic update

استفاده کن.

Table اضافی فقط در صورت Requirement واقعی باقی بماند.

### Migration نمونه مورد انتظار

Migration باید چیزی در این سطح از سادگی باشد:

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('code', 32);
            $table->string('title_fa', 200);
            $table->string('title_en', 200)->nullable();
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hq_id', 'code']);
            $table->index(['hq_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_statuses');
    }
};
```

هدف Migration همین سطح از سادگی و predictability است.

---

## 21. Primary Key Strategy یکپارچه شود

هدف این است که Primary Keyهای Database به:

```text
INT AUTO_INCREMENT
```

تبدیل شوند.

اما این تغییر را کورکورانه انجام نده.

قبل از تغییر بررسی کن:

- Foreign Keyها
- Existing Data
- API Contractها
- External Integrations
- UUID references
- Public identifiers
- Pivot tables
- Audit tables
- Queue payloads

اگر UUID برای Public ID یا External ID لازم است، می‌توان ساختاری مانند:

```text
id INT AUTO_INCREMENT PRIMARY KEY
uuid UUID UNIQUE
```

داشت.

---

## 22. Guard Clause به جای Nested If

از Nested Ifهای عمیق دوری کن.

ترجیح:

```php
if (!$condition) {
    return;
}
```

یا Exception مناسب.

Methodها باید Flow خطی و قابل دنبال کردن داشته باشند.

---

## 23. Complex Conditionها نام‌گذاری شوند

Conditionهای طولانی باید به Variable یا Method معنادار تبدیل شوند.

مثلاً:

```php
$canAssignDriver = ...
```

یا:

```php
if (!$assignmentService->canAssign($order, $driver)) {
    ...
}
```

---

## 24. Collection Pipelineهای پیچیده ساده شوند

Laravel Collection خوب است، ولی Chainهای بسیار طولانی و سخت‌خوان شکسته شوند.

Intermediate Variable استفاده کن.

کد خوانا از کد Clever مهم‌تر است.

---

## 25. Readability در کل پروژه بالاترین اولویت را داشته باشد

در تمام Refactorها، خوانایی کد باید یکی از مهم‌ترین معیارهای تصمیم‌گیری باشد.

کدهایی که از نظر فنی درست هستند ولی برای فهمیدن آن‌ها نیاز به Decode کردن naming، dynamic key، interpolation، mapping یا loopهای فشرده وجود دارد باید ساده‌تر شوند.

مثلاً چنین کدی:

```php
foreach (DashboardMetricDefinitions::CONSIGNMENT_STATUSES as $status) {
    $statusCounts[$status] = (int) ($row["s_{$status}"] ?? 0);
}
```

اگرچه کوتاه است، اما Intent آن در نگاه اول واضح نیست و به naming convention مخفی مثل `s_{status}` وابسته است.

این نوع کدها باید به ساختار واضح‌تر و self-explanatory تبدیل شوند.

مثلاً ترجیح داده می‌شود mapping یا method معناداری وجود داشته باشد که معنی این تبدیل را مشخص کند:

```php
foreach (DashboardMetricDefinitions::CONSIGNMENT_STATUSES as $status) {
    $columnName = DashboardMetricDefinitions::statusCountColumn($status);
    $statusCounts[$status] = (int) ($row[$columnName] ?? 0);
}
```

یا در صورتی که این Logic در چند جا استفاده می‌شود، داخل یک Service / Mapper مشخص قرار بگیرد:

```php
$statusCounts = $dashboardMetricsMapper->mapConsignmentStatusCounts($row);
```

اما صرفاً برای مخفی کردن Logic در Methodهای بی‌ارزش abstraction جدید ایجاد نکن.

موارد زیر در کل پروژه Audit و ساده شوند:

- Dynamic array keys
- Dynamic property access
- String interpolationهای مبهم
- Variable variableها
- Nested ternary
- Nested null coalescing
- Long boolean expressions
- Compact loops که Intent آن‌ها واضح نیست
- Callbackهای پیچیده
- چندین transformation در یک statement
- Magic prefixes و suffixها
- Naming conventionهایی که فقط با دانستن context داخلی پروژه قابل فهم هستند
- Array manipulationهای پیچیده
- `array_map` / `array_filter` / `array_reduce`های تو در تو
- Collection chainهای سخت‌خوان
- چندین cast و fallback در یک expression
- Dynamic field generation بدون naming واضح

در صورت نیاز از Intermediate Variableهای معنادار استفاده کن.

مثلاً به جای:

```php
$value = (int) ($row["s_{$status}"] ?? 0);
```

ترجیح بده:

```php
$statusColumn = DashboardMetricDefinitions::statusCountColumn($status);
$statusCount = $row[$statusColumn] ?? 0;

$statusCounts[$status] = (int) $statusCount;
```

هدف این نیست که تعداد خطوط کمتر شود؛ هدف این است که Intent کد بدون نیاز به تحلیل ذهنی اضافه قابل فهم باشد.

در کل پروژه این اصل رعایت شود:

> Readability > Brevity

و همچنین:

> Explicit code > Clever code

اگر بین دو Implementation از نظر Performance و Behavior تفاوت معناداری وجود ندارد، Implementation خواناتر را انتخاب کن.

---


### Functional-style و Array Gymnastics نباید خوانایی را خراب کنند

کد کوتاه‌تر الزاماً کد بهتر نیست.

Patternهایی مثل این در پروژه نباید باقی بمانند:

```php
$ids = static fn(array $fields): array =>
    array_values(
        array_unique(
            array_filter(
                array_merge(
                    [],
                    ...array_map(
                        static fn($row): array =>
                            array_map(
                                static fn($field) => $row->{$field},
                                $fields,
                            ),
                        $manifests,
                    ),
                ),
            ),
            SORT_REGULAR,
        ),
    );
```

همچنین Patternهایی مثل:

```php
array_replace(
    [],
    ...array_map(
        fn($row) => [
            $row->node_id => $this->manifestContextShape->nodeResource($row),
        ],
        $rows,
    ),
);
```

از نظر تعداد خط کم هستند، اما برای Developer معمولی سخت‌خوان و نیازمند Decode کردن چند مرحله transformation هستند.

این نوع کدها باید در کل پروژه ساده شوند.

از موارد زیر خودداری کن:

- `array_map` تو در تو
- `array_filter` تو در تو
- `array_reduce`های پیچیده
- `array_merge` با spread روی نتیجه چند callback
- `array_replace` برای ساخت Mapهای پیچیده
- Closure محلی که خودش چند transformation انجام می‌دهد
- Dynamic property access مانند `$row->{$field}` برای schema مشخص
- چند transformation مختلف در یک Expression
- Functional one-linerهایی که برای فهمیدنشان باید از داخل به بیرون خوانده شوند

اگر یک `foreach` ساده خواناتر است، `foreach` را انتخاب کن.

مثلاً این:

```php
$nodesById = [];

foreach ($nodes as $node) {
    $nodesById[$node->id] = $node;
}
```

از یک `array_replace + array_map + spread` پیچیده بهتر است.

در جایی که Laravel Collection خوانایی را بهتر می‌کند، از قابلیت Native لاراول استفاده کن:

```php
$nodesById = $nodes->keyBy('id');
```

اما Collection chain نیز نباید به یک Pipeline طولانی و سخت‌خوان تبدیل شود.

اصل:

> Use loops when loops are clearer.

> Use Collections when Collections make the intent clearer.

> Do not use functional programming style just to make code shorter.

### Schema مخفی داخل Arrayهای چندلایه حذف شود

ساختارهایی مانند:

```php
$references['nodes']
$references['drivers']
$references['vehicles']
$references['plans']
$references['legs']
```

یک Contract مخفی ایجاد می‌کنند که از Signature تابع مشخص نیست.

اگر این Data یک Concept واقعی در Application است، باید Object / DTO مشخص داشته باشد.

مثلاً:

```php
final readonly class ManifestSummaryReferences
{
    public function __construct(
        public Collection $nodesById,
        public Collection $driversById,
        public Collection $vehiclesById,
        public Collection $routePlansById,
        public Collection $routeLegsById,
    ) {}
}
```

و به جای:

```php
$this->summary($manifest, $references);
```

ترجیح:

```php
$this->summary($manifest, $manifestReferences);
```

باشد.

به این ترتیب Developer از روی Type می‌فهمد Context شامل چه چیزهایی است.

### ورودی Collectionها نیز Typed باشند

اگر Method مجموعه‌ای از Eloquent Modelها دریافت می‌کند، از Signature مبهم زیر استفاده نکن:

```php
public function summaries(string $hqId, array $manifests): array
```

اگر واقعاً Eloquent Collection است، Contract را واضح کن:

```php
public function summaries(
    int $hqId,
    Collection $manifests,
): Collection
```

و با PHPDoc یا Generic Type مشخص کن Collection شامل چه چیزی است:

```php
/** @param Collection<int, Manifest> $manifests */
/** @return Collection<int, ManifestSummaryData> */
```

اگر Data از نوع DTO است نیز همان Type واقعی را مستند و حفظ کن.

### برای استخراج چند نوع ID از Dynamic Field List استفاده نکن

Patternهایی مثل:

```php
$ids(['node_id', 'origin_node_id', 'destination_node_id'])
```

که پشت آن dynamic property access وجود دارد، Intent را مخفی می‌کنند.

ترجیح بده Concept واقعی را نام‌گذاری کنی:

```php
$nodeIds = $this->manifestReferenceCollector->collectNodeIds($manifests);
$driverIds = $this->manifestReferenceCollector->collectDriverIds($manifests);
$vehicleIds = $this->manifestReferenceCollector->collectVehicleIds($manifests);
```

یا اگر Logic به اندازه کافی ساده است با Collection/Loop واضح و مستقیم انجام بده.

هدف این است که نام Method بگوید چه IDهایی جمع می‌شوند؛ نه اینکه یک utility generic با لیست string fieldها schema را در runtime حدس بزند.

### قبل از Manual Reference Loading، Eloquent Relationship و Eager Loading را بررسی کن

اگر `Manifest` واقعاً Relationshipهایی مانند Node، Driver، Vehicle، RoutePlan و RouteLeg دارد، اول بررسی کن آیا کل Flow با Eloquent Relationship و Eager Loading ساده‌تر می‌شود.

مثلاً:

```php
$manifests->loadMissing([
    'node',
    'originNode',
    'destinationNode',
    'assignedDriver',
    'assignedVehicle',
    'routePlan',
    'routePlanLeg',
]);
```

اگر Eager Loading مسئله را تمیز و performant حل می‌کند، manual ID collection + چند Query + ساخت reference map اختصاصی انجام نده.

Manual batch loading فقط زمانی باقی بماند که دلیل واقعی مانند performance، query shape خاص یا architecture constraint وجود داشته باشد.

### Serialization / Resource Shaping از Aggregation جدا باشد

کدهایی مانند:

```php
[
    'route_plan_id' => (string) $routePlan->route_plan_id,
    'consignment_id' => (string) $routePlan->consignment_id,
    'route_code' => (string) $routePlan->route_code,
    'status' => (string) $routePlan->status,
    'version' => (int) $routePlan->version,
]
```

نباید وسط Serviceای که مسئول جمع‌آوری referenceها است پخش شوند.

اگر خروجی برای API است، shaping را در Laravel Resource انجام بده.

اگر خروجی Application Data است، DTO مشخص بساز.

Service جمع‌آوری Data نباید همزمان Serializer نیز باشد.

### Service Method نباید چندین نوع Reference را دستی Assemble کند

اگر یک Method:

- Node reference می‌سازد
- Driver reference می‌سازد
- Vehicle reference می‌سازد
- Route Plan reference می‌سازد
- Route Leg reference می‌سازد
- و در نهایت Summary می‌سازد

باید بررسی شود که آیا Responsibility بیش از حد بزرگ شده است.

در صورت وجود Concept واقعی، مسئولیت loading/assembling referenceها را به Component مشخصی مانند:

```text
ManifestSummaryReferenceLoader
ManifestSummaryContextBuilder
```

منتقل کن.

اما Component جدید باید Business/Application Meaning واضح داشته باشد و صرفاً برای مخفی کردن چند خط کد ایجاد نشود.


## 26. Methodها کوچک اما نه مصنوعی باشند

Method نباید خیلی بزرگ باشد.

اما هر دو خط را هم به private method تبدیل نکن.

Extract Method فقط زمانی انجام شود که:

- Responsibility مستقل دارد.
- نام Method Intent را توضیح می‌دهد.
- Logic reusable است.
- Method اصلی را واقعاً خواناتر می‌کند.

---

## 27. Type Safety را افزایش بده

تا جای ممکن از Typeهای مشخص استفاده کن:

```text
string
int
bool
Enum
DTO
Collection
Model
ValueObject
```

از `mixed`، `array` و `object` در Business/Application Logic تا جای ممکن دوری کن.

Return Type تمام Methodها مشخص باشد.

---

## 28. Enumها به شکل هدفمند در بخش‌های مورد نیاز پروژه استفاده شوند

در کل پروژه بررسی کن کجا Data دارای مجموعه محدود، مشخص و شناخته‌شده‌ای از مقادیر است و در این موارد از PHP Enum استفاده کن.

Enum باید جایگزین Magic Stringها و Magic Numberهایی شود که Business Meaning مشخص دارند.

نمونه‌های مناسب:

```text
OrderStatus
DriverStatus
UserStatus
ShipmentStatus
PaymentStatus
OperationalProfileKind
OperationalProfileMode
ScopeType
ModuleStatus
AssignmentStatus
```

به جای:

```php
if ($driver->status === 'ACTIVE') {
```

ترجیح بده:

```php
if ($driver->status === DriverStatus::ACTIVE) {
```

و Model Cast نیز در صورت مناسب بودن تعریف شود:

```php
protected function casts(): array
{
    return [
        'status' => DriverStatus::class,
    ];
}
```

یا مطابق Convention نسخه Laravel پروژه.

### Enum برای مواردی که Domain Meaning دارند

Enum زمانی مناسب است که:

- مقدار فقط چند حالت معتبر دارد.
- مقدار در چند بخش پروژه استفاده می‌شود.
- اشتباه تایپی در String می‌تواند Bug ایجاد کند.
- Business Rule بر اساس آن مقدار تصمیم می‌گیرد.
- آن Concept در Domain اسم مشخصی دارد.
- مقدار در Database ذخیره می‌شود و State/Type واقعی است.

### Enum را بی‌دلیل برای همه چیز نساز

برای هر String یا Constant ساده Enum ایجاد نکن.

مثلاً مواردی که صرفاً:

- Label نمایشی هستند.
- فقط یک بار استفاده شده‌اند.
- Config value هستند.
- Text آزاد هستند.
- External payload موقتی هستند و Domain Meaning ندارند.

الزاماً نیاز به Enum ندارند.

هدف:

> Replace meaningful magic values with typed domain enums.

نه:

> Turn every string in the codebase into an enum.

### Enumها باید جای مشخص داشته باشند

Enumها در Folder مشخص و consistent هر Module قرار بگیرند، مثلاً:

```text
Domain/
  Enums/
```

یا Convention واحد دیگری که برای کل پروژه انتخاب می‌شود.

نباید بعضی Enumها در `Application`، بعضی کنار Controller و بعضی در Root Module پراکنده باشند، مگر دلیل معماری مشخصی وجود داشته باشد.

### Enumها در Signatureها نیز استفاده شوند

اگر Method فقط چند مقدار معتبر می‌گیرد، String خام نگیرد.

به جای:

```php
public function changeStatus(string $status): void
```

ترجیح بده:

```php
public function changeStatus(DriverStatus $status): void
```

این Rule برای DTOها نیز اعمال شود.

---

## 29. Magic Number / Magic String حذف شوند

Business Constantها باید نام مشخص داشته باشند یا در Config قرار بگیرند.

---

## 30. Scopeها فقط برای Query Logic

Eloquent Scope فقط Query-related logic داشته باشد.

Business Workflow داخل Scope قرار نده.

---

## 31. Naming باید واضح، ساده و مبتنی بر Intent باشد

نام‌گذاری در کل پروژه باید بازنگری شود.

نام Classها، Serviceها، UseCaseها، Methodها، Variableها، DTOها، Repositoryها، Enumها و سایر Componentها باید به شکلی باشد که Developer تا حد ممکن فقط با دیدن نام آن‌ها بفهمد مسئولیتشان چیست.

نام‌گذاری نباید مبهم، بیش از حد Generic یا وابسته به دانستن Context پنهان پروژه باشد.

### نام Serviceها

Service باید بر اساس کاری که واقعاً انجام می‌دهد نام‌گذاری شود.

نام‌های مبهم مانند این‌ها تا حد ممکن حذف یا Rename شوند:

```text
OperationService
ManagementService
CommonService
GeneralService
UtilityService
HelperService
ProcessService
ProcessorService
HandlerService
ManagerService
DataService
ApplicationService
AdministrationService
```

مگر اینکه واقعاً Meaning مشخص و شناخته‌شده‌ای در Domain داشته باشند.

مثلاً به جای:

```text
FleetAdministrationService
```

اگر مسئولیت واقعی آن مدیریت Driverها است، نام مشخص‌تری مثل:

```text
DriverManagementService
DriverService
DriverAdministrationService
```

انتخاب کن؛ نام نهایی باید بر اساس مسئولیت واقعی Class باشد.

به جای:

```text
NetworkAdministrationService
```

اگر مسئول مدیریت Nodeها است، چیزی مانند:

```text
NodeManagementService
NetworkNodeService
```

واضح‌تر است.

Service نباید چند مسئولیت مختلف داشته باشد فقط چون نامش Generic است.

### نام Methodها

نام Method باید دقیقاً Action یا Query آن را مشخص کند.

از نام‌های مبهم مثل این‌ها دوری کن:

```php
handle()
process()
run()
do()
executeAction()
manage()
perform()
apply()
resolve()
getData()
setData()
load()
save()
```

مگر اینکه Context Class به اندازه کافی معنی آن را واضح کند.

مثلاً:

```php
$service->process($data);
```

خوانایی کمی دارد.

ترجیح:

```php
$driverService->assignUser($data);
$pricingService->calculateOrderPrice($data);
$nodeService->activateNode($data);
```

برای Queryها نیز نام باید Result را مشخص کند.

به جای:

```php
get()
find()
data()
info()
```

در جایی که Context کافی نیست، ترجیح بده:

```php
findDriverByUserId()
findActiveNode()
getOrderPricingSummary()
listAvailableDrivers()
```

نام Method باید باعث شود Call Site بدون باز کردن Method قابل فهم باشد.

### نام Variableها

از نام‌های Generic و کوتاه در Business Logic خودداری کن.

نام‌هایی مانند:

```text
$data
$input
$row
$item
$result
$temp
$obj
$info
$value
$list
$record
$entity
$payload
```

فقط زمانی قابل قبول هستند که Scope بسیار کوچک باشد و Meaning کاملاً واضح باشد.

در غیر این صورت نام واقعی Data را بنویس.

به جای:

```php
$row = ...;
$data = ...;
$result = ...;
```

ترجیح:

```php
$driverRecord = ...;
$operationalProfileData = ...;
$assignmentResult = ...;
```

مثلاً این:

```php
foreach ($items as $item) {
```

اگر Collection مربوط به Driverها است، بهتر است:

```php
foreach ($drivers as $driver) {
```

باشد.

### نام DTOها

نام DTO باید Use Case یا Data Shape را مشخص کند.

از:

```text
InputData
RequestData
OperationData
PayloadData
CommonData
DataDto
ResultDto
```

تا حد امکان استفاده نکن.

ترجیح:

```text
CreateDriverData
UpdateDriverData
AttachOperationalProfileData
OrderPricingResult
DriverAssignmentResult
NodeScopeData
```

### نام UseCaseها

UseCase باید به شکل واضح Business Action را توضیح دهد.

مثلاً:

```text
CreateDriverUseCase
AttachOperationalProfileUseCase
AssignDriverToOrderUseCase
DeactivateNodeUseCase
```

از نام‌های کلی مثل:

```text
DriverOperationUseCase
ManageDriverUseCase
ProcessOrderUseCase
HandleProfileUseCase
```

خودداری کن.

### نام Repositoryها

Repository باید Entity یا Aggregate مشخصی را نشان دهد.

مثلاً:

```text
DriverRepository
OrderRepository
AreaHierarchyRepository
```

Methodهای آن نیز باید Query یا Persistence Action را واضح بیان کنند.

اگر Context Repository کافی است:

```php
$driverRepository->findById($id);
```

و اگر کافی نیست:

```php
$repository->findDriverById($id);
```

### نام Booleanها

Booleanها باید به شکل Question خوانده شوند.

ترجیح:

```php
$isActive
$hasPermission
$canAssignDriver
$shouldNotifyCustomer
$includesDescendants
```

نه:

```php
$status
$flag
$check
$permission
$active
```

### نام Collectionها

Collectionها باید جمع باشند:

```php
$drivers
$orders
$nodeIds
$activeAssignments
```

و Item داخل Loop مفرد:

```php
foreach ($drivers as $driver)
```

### Acronym و Abbreviation

از abbreviationهای غیرضروری مثل:

```text
$svc
$mgr
$cfg
$ctx
$tmp
$usr
$drv
$op
$reqData
```

خودداری کن.

نام کامل و قابل فهم بنویس:

```text
$service
$manager
$configuration
$context
$user
$driver
$operation
$requestData
```

اگر abbreviation در Domain پروژه استاندارد و شناخته‌شده است، استفاده از آن قابل قبول است.

### Naming باید Consistent باشد

برای Concept یکسان در کل پروژه یک نام واحد داشته باش.

نباید یک Concept در بخش‌های مختلف با نام‌های متفاوت نمایش داده شود.

مثلاً اگر Concept اصلی `Driver` است، در جاهای دیگر بدون دلیل از:

```text
Courier
Rider
FleetMember
Operator
```

برای همان Concept استفاده نکن.

یا اگر Action اصلی `assign` است، برای همان Behavior در جاهای مختلف ترکیبی از:

```text
attach
bind
link
connect
assign
```

استفاده نکن مگر واقعاً Meaning متفاوتی داشته باشند.

یک Vocabulary مشخص برای Domain ایجاد کن و در کل پروژه همان را رعایت کن.

### Rename کردن بخشی از Refactor است

اگر Class، Service، Method یا Variable فعلی نام بد یا مبهم دارد، فقط به دلیل جلوگیری از تغییر نام آن را نگه ندار.

در این Refactor Renameهای لازم را در کل Codebase انجام بده و تمام Referenceها، Interfaceها، Bindings، Tests و Imports مربوطه را نیز Update کن.

هدف نهایی این است که Developer بتواند با نگاه کردن به کد، بدون باز کردن چندین فایل، بفهمد:

- این Service برای چیست.
- این Method چه کاری انجام می‌دهد.
- این Variable چه Dataیی نگه می‌دارد.
- این DTO مربوط به کدام Operation است.
- خروجی هر Call چه معنایی دارد.

اصل سراسری:

> Names should explain intent.

> Prefer domain language over generic technical names.

> Clear long names are better than short ambiguous names.

> If a name needs a comment to explain what it means, the name should probably be improved.

---

## 32. Abstractionهای یک‌بارمصرف حذف شوند

اگر ساختاری مثل این فقط یک Implementation دارد:

```text
Interface
    ↓
AbstractClass
    ↓
ConcreteClass
    ↓
Adapter
    ↓
Service
```

Simplify کن.

هر Layer باید دلیل واضحی برای وجود داشته باشد.

اگر نتوانی در یک جمله توضیح بدهی چرا یک abstraction وجود دارد، احتمالاً باید حذف شود.

---

## 33. Dependency Direction ساده و مشخص باشد

Flow کلی باید قابل فهم باشد:

```text
HTTP Request
    ↓
Controller
    ↓
UseCase
    ↓
Service / Model
    ↓
Eloquent / External Infrastructure
```

Response:

```text
Model / DTO
    ↓
Resource
    ↓
JSON Response
```

---

## 34. قابلیت‌های Native لاراول را به پیاده‌سازی Custom ترجیح بده

در کل پروژه، قبل از نوشتن هر abstraction، helper، middleware، resolver، validator، serializer، query utility، authentication/authorization component یا framework-level code سفارشی، ابتدا بررسی کن که آیا Laravel خودش راه‌حل استاندارد و قابل استفاده برای آن دارد یا خیر.

اگر Laravel قابلیت مناسب دارد، **نباید نسخه Custom همان قابلیت توسط ما دوباره نوشته شود** مگر اینکه Requirement مشخص و قابل دفاعی وجود داشته باشد که قابلیت Native لاراول آن را پوشش نمی‌دهد.

اصل کلی:

> Laravel-native first.

> Do not rebuild framework features that Laravel already provides.

> Prefer standard framework conventions over custom infrastructure.

### نمونه‌هایی که باید از قابلیت خود Laravel استفاده شوند

تا حد ممکن از قابلیت‌های استاندارد زیر استفاده کن:

- Middleware
- FormRequest
- Validation Rules
- Policies
- Gates
- Authentication Guards
- Authorization
- API Resources
- Eloquent Models
- Relationships
- Eager Loading
- Query Scopes
- Attribute Casts
- Custom Casts
- Accessors / Mutators
- Route Model Binding
- Dependency Injection / Service Container
- Service Providers
- Events
- Listeners
- Jobs / Queues
- Notifications
- Mail
- Cache
- Rate Limiting
- Pagination
- Collections
- Database Transactions
- Exceptions / Exception Handler
- Logging
- Config
- Filesystem
- HTTP Client
- Scheduler
- Broadcasting، در صورت نیاز پروژه

مثلاً اگر Laravel Middleware می‌تواند مسئله را حل کند، برای همان مسئولیت یک Pipeline، Guard Wrapper یا Request Processor اختصاصی نساز.

اگر Authorization با `Policy` یا `Gate` قابل انجام است، Authorization framework موازی نساز.

اگر Validation مربوط به HTTP است، به جای Validator Service سفارشی از `FormRequest` و Laravel Validation استفاده کن.

اگر Response مربوط به API است، به جای ساخت manual response mapperهای پراکنده از `JsonResource` استفاده کن.

اگر Relation بین دو Entity در Database وجود دارد، به جای query helper یا join utility سفارشی از Eloquent Relationship استفاده کن.

اگر مقدار یک Column نیاز به تبدیل مشخص دارد، ابتدا `cast` یا `custom cast` لاراول را بررسی کن.

اگر مدل باید از Route resolve شود، از Route Model Binding استفاده کن و resolver اختصاصی نساز مگر Requirement واقعی وجود داشته باشد.

### Middlewareهای Custom فقط برای Business Concern واقعی

Middleware سفارشی فقط زمانی نوشته شود که Responsibility مشخص پروژه‌ای داشته باشد و Middleware استاندارد Laravel آن را پوشش ندهد.

Middlewareهای Custom نباید برای کارهایی ایجاد شوند که Laravel از قبل برایشان راه استاندارد دارد، مثل:

- Authentication پایه
- Throttling
- Signed URLs
- CORS در صورت وجود راه استاندارد پروژه
- Request binding
- Basic authorization که Policy/Gate مناسب آن است

همچنین Middleware نباید Business Logic سنگین داشته باشد.

### Helper Functionهای Custom به حداقل برسند

قبل از نوشتن Helper Function بررسی کن که آیا Laravel یا PHP خودش Method مناسب دارد یا خیر.

از ساخت Helperهای عمومی برای مواردی که با این‌ها قابل حل است خودداری کن:

```text
Collection
Arr
Str
data_get
optional
blank / filled
config
app
response
abort
validator
now
```

اما صرفاً برای کوتاه‌تر کردن کد هم Helper نساز.

Helper فقط زمانی ایجاد شود که یک Concern واقعی، reusable و واضح پروژه‌ای وجود داشته باشد.

### Service و Wrapper اضافی روی Laravel نساز

این نوع Wrapperها اگر فقط Laravel را بدون ارزش اضافه Wrap می‌کنند حذف شوند:

```text
CacheService -> Cache facade
ValidationService -> Validator
ResponseService -> response()/Resource
DatabaseService -> Eloquent/DB transaction
ConfigService -> config()
CollectionService -> Collection
```

اگر Wrapper هیچ Business Meaning یا Infrastructure Boundary واقعی ایجاد نمی‌کند، نگه داشتن آن فقط complexity اضافه می‌کند.

### از Facade یا Dependency مناسب با Context استفاده کن

هدف این Rule حذف همه abstractionها نیست.

برای codeهای تست‌پذیر و dependencyهای واقعی می‌توان از DI استفاده کرد، اما نباید برای هر قابلیت Laravel یک Interface + Service + Adapter جدید ساخته شود.

انتخاب باید ساده، استاندارد و متناسب با Context باشد.

---

## 35. Authorization یکپارچه شود

Authorization نباید بی‌قاعده بین Middleware، Policy، Guard، Helper، Controller و UseCase پخش شده باشد.

ترجیح:

- HTTP-level permission → Middleware / Policy
- Resource authorization → Policy
- Business authorization → Application/Domain Service فقط اگر واقعاً لازم باشد

Duplicate authorization checks حذف شوند.

---

## 36. Exceptionها ساختار مشخص داشته باشند

از Generic Exception برای Business Error استفاده نکن.

اما برای هر Error کوچک هم Class جدید نساز مگر Meaning واقعی داشته باشد.

---

## 37. Overengineering جدید ایجاد نکن

در طول Refactor بدون ضرورت اضافه نکن:

- CQRS
- Event Sourcing
- Mediator
- Command Bus
- Custom ORM
- Generic Repository
- Specification Pattern
- Complex Factory hierarchy
- Abstract Service layers

هدف پروژه ساده‌تر شدن است.

---

## 38. متدهای بزرگ و چندمسئولیتی Service / UseCase باید شکسته و خوانا شوند

در کل پروژه متدهایی که چندین مسئولیت مستقل را داخل یک Flow طولانی انجام می‌دهند باید Refactor شوند.

یک متد نباید همزمان مسئول همه موارد زیر باشد:

- Load / Lock کردن Entity
- Validation
- ساخت Input برای Service دیگر
- Resolve کردن Configuration
- Query کردن Route / Coverage
- ساخت Model
- Persistence چند Entity مختلف
- Mapping
- Serialization
- Audit
- Event Recording
- ساخت Response

اگر برای فهمیدن یک Method مجبوریم چندین بار بالا و پایین برویم یا State ذهنی زیادی نگه داریم، Method بیش از حد مسئولیت دارد.

مثال Problematic:

```php
public function createPlan(
    AuthenticatedPrincipal $actor,
    string $node,
    string $consignmentId,
    string $correlationId,
): object {
    // load consignment
    // validate destination
    // build coverage input
    // resolve coverage
    // resolve route
    // load legs
    // insert route plan
    // insert plan legs
    // build evidence
    // insert evidence
    // audit
    // record event
    // reload and return plan
}
```

این Flow باید به یک Orchestration واضح تبدیل شود.

هدف این نیست که هر 3 خط را داخل یک Service جدید ببری. مسئولیت‌ها را فقط زمانی Extract کن که Concept مشخص و معنی‌دار دارند.

Flow نهایی باید در سطح بالا چیزی شبیه این باشد:

```php
public function execute(CreateRoutePlanData $data): RoutePlan
{
    return DB::transaction(function () use ($data) {
        $consignment = $this->consignmentService->lockForRoutePlanning($data);

        $destination = $this->destinationGeographyService
            ->resolveForConsignment($consignment);

        $coverage = $this->coverageService->resolve(
            ResolveCoverageData::from($data, $consignment, $destination),
        );

        $route = $this->routeService->resolve(
            ResolveRouteData::from($data, $consignment, $coverage),
        );

        return $this->routePlanService->create(
            CreateResolvedRoutePlanData::from(
                $data,
                $consignment,
                $coverage,
                $route,
            ),
        );
    });
}
```

نام‌ها و تعداد Serviceهای واقعی را براساس Domain پروژه انتخاب کن؛ این فقط نمونه‌ای از سطح خوانایی مورد انتظار است.

### هر Method باید یک سطح Abstraction داشته باشد

داخل یک Method، high-level business orchestration را با جزئیات low-level persistence و serialization قاطی نکن.

این ترکیب مناسب نیست:

```php
$coverage = $this->coverage->resolve(...);

$this->routeState->insertResolutionEvidence([
    'matched_geometry_evidence' => isset($matched['geometry'])
        ? json_encode($matched['geometry'], JSON_THROW_ON_ERROR)
        : null,
]);
```

در یک سطح از کد یا Business Flow را بخوانیم، یا Persistence Detail را.

### Query داخل Loop تا حد ممکن ممنوع

Database Query داخل `foreach` می‌تواند هم خوانایی را خراب کند و هم N+1 ایجاد کند.

مثلاً این Pattern:

```php
foreach ($legs as $leg) {
    $legacyId = $this->routeState->legacyLegId(
        $hqId,
        $routeDefinitionId,
        $leg->leg_order,
    );
}
```

باید بررسی و در صورت امکان با یک Query قبل از Loop حل شود.

مثلاً:

```php
$legacyLegIdsByOrder = $this->routeRepository
    ->legacyLegIdsByOrder($routeDefinitionId);

foreach ($legs as $leg) {
    $legacyLegId = $legacyLegIdsByOrder->get($leg->legOrder);
}
```

اصل سراسری:

> No database query inside loops unless there is a documented and unavoidable reason.

### Persistence باید Typed و واضح باشد

این نوع Persistence:

```php
$this->routeState->insertPlan([
    'route_plan_id' => $id,
    'hq_id' => $actor->hqId,
    'consignment_id' => $consignmentId,
    'status' => 'PLANNED',
    // ...
]);
```

برای Data با Structure مشخص مناسب نیست.

از DTO مشخص استفاده کن:

```php
$routePlanData = new CreateRoutePlanRecordData(
    hqId: $actor->hqId,
    consignmentId: $data->consignmentId,
    status: RoutePlanStatus::PLANNED,
    // ...
);

$routePlan = $this->routePlanRepository->create($routePlanData);
```

Repository / Service نباید مجبور باشد Structure یک associative array را حدس بزند.

### خروجی Serviceها Array ناشناس نباشد

این نوع استفاده:

```php
$coverage['target_node_id']
$route['route_definition_id']
$route['route_definition_version_id']
$coverage['matched_evidence']
```

باید تا حد ممکن با DTO / Object Typed جایگزین شود:

```php
$coverage->targetNodeId
$route->routeDefinitionId
$route->routeDefinitionVersionId
$coverage->matchedEvidence
```

مثلاً:

```php
final readonly class CoverageResolutionResult
{
    public function __construct(
        public int $targetNodeId,
        public int $coveragePolicyId,
        public int $coveragePolicyVersionId,
        public int $coverageRuleId,
        public CoverageCriterionType $criterionType,
        public int $priority,
        public CoverageEvidenceData $matchedEvidence,
    ) {}
}
```

### Return Type مبهم ممنوع

تا حد امکان از Return Typeهای زیر برای Flowهای Domain/Application دوری کن:

```php
object
array
mixed
```

به جای:

```php
public function createPlan(...): object
```

از Type واقعی استفاده کن:

```php
public function createPlan(...): RoutePlan
```

یا:

```php
public function createPlan(...): RoutePlanData
```

### Magic Stringهای Flow باید Enum باشند

Stringهایی مثل:

```text
DESTINATION_GATEWAY
TRUNK
PLANNED
PENDING
ROUTE_PLAN_CREATED
ROUTE_PLAN
```

اگر State / Type / Purpose / Event Name شناخته‌شده پروژه هستند، باید Enum یا Constant Typed مناسب داشته باشند.

مثلاً:

```php
CoveragePurpose::DESTINATION_GATEWAY
RoutePurpose::TRUNK
RoutePlanStatus::PLANNED
RoutePlanLegStatus::PENDING
AuditAction::ROUTE_PLAN_CREATED
AuditEntityType::ROUTE_PLAN
```

از پخش شدن Magic Stringهای Business در Serviceها جلوگیری کن.

### Castهای بی‌دلیل IDها حذف شوند

Patternهایی مثل:

```php
(string) $actor->hqId
(string) $c->receiver_city_id
(string) $coverage['target_node_id']
```

نشانه Typeهای inconsistent هستند.

ID Typeها را در کل پروژه یکپارچه کن.

با توجه به Strategy پروژه برای Primary Keyهای `INT AUTO_INCREMENT`، IDهای داخلی تا حد ممکن `int` باشند.

اگر Public UUID یا External ID وجود دارد، Type و نام آن باید واضح و جدا باشد:

```text
id
publicUuid
externalId
```

از Cast کردن مداوم ID بین `string` و `int` در Business Logic جلوگیری کن.

### UUID generation با Primary Key Strategy هماهنگ شود

اگر Entity از Primary Key داخلی `INT AUTO_INCREMENT` استفاده می‌کند، Service نباید برای Primary Key آن UUID تولید کند.

اگر UUID برای Public Identifier لازم است، به صورت Field جدا نگه داشته شود:

```text
id          INT AUTO_INCREMENT PRIMARY KEY
public_uuid UUID UNIQUE
```

### JSON Encoding دستی در Service حذف شود

این Pattern:

```php
json_encode($coverage['input'], JSON_THROW_ON_ERROR)
```

نباید در Business/Application Service پخش شود.

اگر Column از نوع JSON است، از Eloquent Cast مناسب استفاده کن:

```php
protected function casts(): array
{
    return [
        'resolution_input' => 'array',
        'matched_geography_evidence' => 'array',
        'ordered_route_legs' => 'array',
    ];
}
```

یا DTO / Custom Cast مناسب.

Service باید Object/Array ساختاریافته را به Persistence Layer بدهد و Serialization تا حد ممکن توسط Laravel/Eloquent انجام شود.

### created_at / updated_at را دستی مدیریت نکن

اگر Eloquent timestamps فعال است، این موارد را دستی در Service پاس نده:

```php
'created_at' => $at,
'updated_at' => $at,
```

Laravel/Eloquent این بخش را مدیریت کند.

Timestampهایی که Business Meaning دارند، مانند:

```text
resolved_at
published_at
activated_at
```

می‌توانند صریح باقی بمانند.

### Multi-write Flow باید Transaction داشته باشد

اگر یک عملیات چند Write وابسته دارد، مانند:

```text
Create Route Plan
Create Route Plan Legs
Create Resolution Evidence
Update State
```

باید Transaction Boundary مشخص داشته باشد.

از Laravel `DB::transaction()` استفاده کن.

همچنین `lockForUpdate()` فقط در Transaction معنی درست دارد و باید بررسی شود Lock واقعاً داخل Transaction فعال است.

اصل:

> Multiple dependent writes = one explicit transaction boundary.

### Database Invariantها را با Database Constraint نیز enforce کن

اگر Logicی مثل `active_slot` یا Hash برای جلوگیری از چند Record فعال استفاده می‌شود، بررسی کن آیا Constraint واقعی Database می‌تواند Rule را واضح‌تر و مطمئن‌تر enforce کند.

Business Invariantهای مهم فقط به Conventionهای مبهم String/Hash در Service وابسته نباشند.

در صورت امکان استفاده کن:

- UNIQUE INDEX
- FOREIGN KEY
- NOT NULL
- CHECK CONSTRAINT
- Composite unique constraint

Application Validation جای Database Integrity را نگیرد.

### Validationهای قابل نام‌گذاری از وسط Flow خارج شوند

Validationهایی مثل:

```php
preg_match('/^\d{10}$/', (string) $postalCode)
```

اگر Business Meaning دارند باید نام واضح داشته باشند.

مثلاً:

```php
PostalCode::isValid($postalCode)
```

یا Value Object / Validator مشخص، اگر در چند جای پروژه استفاده می‌شود.

Regex و low-level rule نباید وسط Business Flow باعث سخت شدن خوانایی شود.

### Exceptionها باید Intent واضح داشته باشند

در Serviceهای عمیق تا حد امکان Exceptionهای Domain/Application مشخص Throw شوند و تبدیل آن‌ها به HTTP Response در Boundary مناسب Laravel انجام شود.

به جای پخش کردن HTTP Status و Message در چند Service:

```php
throw new ApiException(..., 422, ...);
```

در صورت مناسب بودن از Exception مشخص استفاده کن:

```php
throw RoutePlanUnavailable::forConsignment($consignmentId);
```

و Mapping به Response را در Exception Handler / Laravel Boundary انجام بده.

این Rule نباید باعث ایجاد صدها Exception بی‌ارزش شود؛ فقط Errorهای Business مشخص را Typed کن.

### از Re-query غیرضروری بعد از Create جلوگیری کن

اگر Persistence Method بعد از Create می‌تواند Model کامل را برگرداند، دوباره برای همان Entity Query نزن.

به جای:

```php
$this->routeState->insertPlan($data);

return $this->routeState->plan($id);
```

ترجیح:

```php
return $this->routePlanRepository->create($data);
```

مگر اینکه Reload واقعاً برای Data تولیدشده توسط Database لازم باشد.

### Audit و Event Recording باید واضح و استاندارد باشد

Audit/Eventها نباید Business Flow اصلی را با چند Call کم‌معنی شلوغ کنند.

اگر Laravel Event/Listener برای این Concern مناسب است، از قابلیت Native آن استفاده کن.

مثلاً:

```php
RoutePlanCreated::dispatch($routePlan);
```

و Listenerهای مشخص:

```text
WriteRoutePlanAuditLog
RecordManifestTransition
```

اما Event را فقط برای مخفی کردن Side Effectهای ضروری اضافه نکن. Transaction consistency و ترتیب اجرای Side Effectها باید مشخص باشد.

### هدف نهایی

یک Method سطح بالا باید مثل یک Story قابل خواندن باشد:

```text
1. Consignment را برای Route Planning دریافت کن
2. Destination Geography را مشخص کن
3. Coverage را Resolve کن
4. Route را Resolve کن
5. Route Plan را Persist کن
6. Audit/Event مورد نیاز را ثبت کن
7. RoutePlan Typed را برگردان
```

نه اینکه Developer مجبور باشد همزمان SQL/Persistence structure، Array keyها، JSON serialization، Magic Stringها، ID casting، validation و Business Flow را در یک Method دنبال کند.

اصل نهایی:

> One method should tell one clear story.

> High-level orchestration must not be mixed with low-level implementation details.

> Typed contracts and meaningful names should make the flow understandable without decoding arrays and magic values.

---

## 39. Validation Flowها باید کوچک، Typed، دسته‌بندی‌شده و قابل فهم باشند

Validationهای بزرگ نباید داخل یک Method طولانی با ده‌ها `if`، `try/catch`، array access و Magic String جمع شوند.

متدی مانند این:

```php
private function execute(
    AuthenticatedPrincipal $actor,
    string $versionId,
): array
{
    // access check
    // load version
    // validate matrices
    // validate effective zones
    // validate dates
    // validate overlaps
    // validate every pricing rule
    // detect duplicate rules
    // validate ambiguous ranges
    // resolve dependencies
    // validate default conflicts
    // validate successor compatibility
    // return array result
}
```

یک Validation God Method است و باید Refactor شود.

هدف این است که Validation Flow در سطح بالا مانند یک لیست واضح از Business Ruleها خوانده شود.

مثلاً:

```php
public function validate(
    AuthenticatedPrincipal $actor,
    TariffVersion $tariffVersion,
): TariffValidationResult {
    $errors = new ValidationErrorCollection();

    $errors->addMany(
        $this->matrixValidator->validate($tariffVersion),
    );

    $errors->addMany(
        $this->effectivePeriodValidator->validate($tariffVersion),
    );

    $errors->addMany(
        $this->pricingRuleValidator->validate($actor, $tariffVersion),
    );

    $errors->addMany(
        $this->serviceDependencyValidator->validate($actor, $tariffVersion),
    );

    return TariffValidationResult::fromErrors($errors);
}
```

این فقط نمونه سطح خوانایی مورد انتظار است. تعداد Validatorها و مرزبندی واقعی باید براساس Domain پروژه انتخاب شود و نباید به ایجاد ده‌ها Class بی‌ارزش منجر شود.

### Validationها براساس Concern دسته‌بندی شوند

Validationهای متفاوت را در یک Loop یا Method بزرگ مخلوط نکن.

مثلاً موارد زیر Concernهای جدا هستند:

```text
Matrix validation
Zone validation
Effective date validation
Version overlap validation
Pricing rule validation
Rule ambiguity validation
Catalog dependency validation
Default tariff conflict validation
Successor compatibility validation
```

اگر چند Rule متعلق به یک Concept هستند، آن‌ها را در Validator/Service همان Concept گروه‌بندی کن.

مثلاً Ruleهای زیر:

```text
FIXED requires fixed amount
PER_UNIT requires unit rate
TIERED requires unit rate
SLAB requires fixed amount or unit rate
PERCENT requires percentage
MIN_MAX requires minimum or maximum amount
minimum <= maximum
rounding mode requires rounding step
```

همگی مربوط به اعتبار یک Pricing Rule هستند و بهتر است در یک Component مشخص مثل:

```text
PricingRuleValidator
```

قرار بگیرند، نه وسط UseCase اصلی.

### هر Rule باید Intent واضح داشته باشد

از `if`های طولانی که Business Rule داخل expression پنهان شده است خودداری کن.

مثلاً:

```php
if (
    $rule['range_from'] !== null
    && $rule['range_to'] !== null
    && (float) $rule['range_from'] >= (float) $rule['range_to']
) {
```

ترجیح:

```php
if (!$pricingRule->hasValidRange()) {
    $errors->add(PricingValidationError::invalidRange());
}
```

یا در Validator:

```php
$this->validateRange($pricingRule, $errors);
```

به شرطی که Extract Method واقعاً خوانایی را بهتر کند و صرفاً fragmentation ایجاد نکند.

### Validation Errorها Array ناشناس نباشند

این Pattern:

```php
$errors[] = [
    'code' => 'PRICING_RANGE_INVALID',
    'field' => 'rules',
];
```

نباید Contract اصلی Validation باشد.

از DTO / Object Typed استفاده کن:

```php
new ValidationErrorData(
    code: PricingValidationErrorCode::RANGE_INVALID,
    field: PricingField::RULES,
)
```

یا Type مشخص مشابه.

نتیجه Validation نیز به جای:

```php
return [
    'valid' => $errors === [],
    'errors' => $errors,
];
```

Typed باشد:

```php
return new TariffValidationResult(
    isValid: $errors->isEmpty(),
    errors: $errors,
);
```

### Error Codeها و Field Nameها Magic String نباشند

Stringهایی مثل:

```text
PRICING_ZONE_RANK_INCOMPLETE
PRICING_VALID_FROM_REQUIRED
PRICING_EFFECTIVE_INTERVAL_INVALID
PRICING_RULE_AMBIGUOUS
PRICING_SERVICE_DEPENDENCY_INVALID
rules
valid_from
zone_set_version_id
```

اگر در چند جای پروژه Contract مشخص Validation هستند، باید Enum / Constant Typed یا Value Object مناسب داشته باشند.

مثلاً:

```php
PricingValidationErrorCode::ZONE_RANK_INCOMPLETE
PricingValidationErrorCode::VALID_FROM_REQUIRED
PricingValidationErrorCode::EFFECTIVE_INTERVAL_INVALID
```

و در صورت منطقی بودن:

```php
PricingValidationField::RULES
PricingValidationField::VALID_FROM
```

اما برای field nameهایی که مستقیماً API field هستند و Enum ارزش واقعی اضافه نمی‌کند، abstraction اضافی نساز.

### Domain Model / DTO Typed به جای `$version['...']` و `$rule['...']`

Validation نباید روی associative array بزرگ انجام شود اگر Structure داده مشخص است.

به جای:

```php
$version['zone_policy']
$version['freight_matrices']
$version['rules']
$rule['calculation_method']
$rule['fixed_amount']
$rule['unit_rate']
```

از Objectهای Typed استفاده کن:

```php
$tariffVersion->zonePolicy
$tariffVersion->freightMatrices
$tariffVersion->rules
$pricingRule->calculationMethod
$pricingRule->fixedAmount
$pricingRule->unitRate
```

و `calculation_method` باید Enum مناسب باشد:

```php
PricingCalculationMethod::FIXED
PricingCalculationMethod::PER_UNIT
PricingCalculationMethod::TIERED
PricingCalculationMethod::SLAB
PricingCalculationMethod::PERCENT
PricingCalculationMethod::MIN_MAX
```

### Ternaryهای سنگین از Business Flow حذف شوند

کدی مثل:

```php
$zones = $version['zone_set_version_id']
    ? $this->pricingReader->zoneVersion(... )['zones']
    : [['pricing_zone_id' => TariffMatrixCompiler::GLOBAL_COLUMN]];
```

چند Concept را در یک expression مخفی می‌کند.

بهتر است Intent نام‌گذاری شود:

```php
$zones = $this->pricingZoneService
    ->zonesForTariffValidation($actor, $tariffVersion);
```

یا Flow به مراحل ساده و واضح شکسته شود.

### Exception را برای Flow Control عادی استفاده نکن

Patternهایی مثل:

```php
try {
    $effectiveVersionId = $resolver->resolve(...);
} catch (ApiException $exception) {
    if (!in_array($exception->errorCode, [...], true)) {
        throw $exception;
    }

    $errors[] = ...;
}
```

نشان می‌دهد Exception همزمان هم Error واقعی است و هم بخشی از Validation Flow.

اگر unresolved/ambiguous بودن یک نتیجه مورد انتظار Validation است، API Resolver باید در صورت مناسب بودن Result Typed برگرداند:

```php
$zoneResolution = $this->pricingZoneResolver->resolve(...);

if (!$zoneResolution->isResolved()) {
    $errors->add(...);
}
```

یا Exceptionهای دقیق و Domain-specific داشته باشد که Catch کردنشان معنی مشخصی داشته باشد.

از Catch کردن یک `ApiException` عمومی و بررسی `errorCode` داخل آن به‌عنوان branching mechanism تا حد ممکن خودداری کن.

### Validation Service نباید به HTTP Exception وابسته باشد

Business/Application Validation نباید برای فهمیدن نتیجه به HTTP-oriented Exceptionهایی مثل `ApiException` وابسته باشد.

HTTP mapping باید در Laravel Boundary انجام شود.

Validator بهتر است:

- Validation Result برگرداند؛ یا
- Domain/Application Exception مشخص Throw کند، اگر failure واقعاً exceptional است.

### Duplicate detection باید واضح و Typed باشد

این Pattern:

```php
$key = implode('|', [
    $rule['service_offering_version_id'],
    $rule['service_option_version_id'],
    $rule['origin_zone_id'],
    $rule['destination_zone_id'],
    $rule['charge_type_id'],
    $rule['priority'],
    $rule['range_from'],
    $rule['range_to'],
]);
```

یک Composite Key مخفی و شکننده می‌سازد.

از concat کردن Business Data با delimiter برای identity/uniqueness تا حد ممکن خودداری کن.

ترجیح بده:

- Value Object مشخص برای Rule Identity
- Collection grouping با key واضح
- Database constraint، اگر uniqueness واقعاً Database invariant است
- Comparator/Policy مشخص

مثلاً:

```php
$identity = PricingRuleIdentity::fromRule($pricingRule);
```

نه `implode('|', ...)`.

### Validation داخل Loop باید به یک Rule Validator واگذار شود

این شکل:

```php
foreach ($version['rules'] as $rule) {
    if (...) {
        $errors[] = ...;
    }

    if (...) {
        $errors[] = ...;
    }

    if (...) {
        $errors[] = ...;
    }

    // many more validations...
}
```

با زیاد شدن Ruleها به‌سرعت غیرقابل نگهداری می‌شود.

ترجیح:

```php
foreach ($tariffVersion->rules as $pricingRule) {
    $errors->addMany(
        $this->pricingRuleValidator->validate(
            $actor,
            $tariffVersion,
            $pricingRule,
        ),
    );
}
```

و داخل `PricingRuleValidator` نیز Ruleها با Methodهای معنی‌دار و یک سطح abstraction پیاده شوند.

### External Dependency Validation از Local Field Validation جدا باشد

Validationهایی که فقط Data داخلی را بررسی می‌کنند:

```text
valid_from required
valid_to > valid_from
minimum <= maximum
fixed amount required
```

نباید با Validationهایی که Query یا External/Other Module Call دارند مخلوط شوند:

```text
offering has published successor
option is bound
effective zone version exists
service tariff dependency resolves
default tariff conflict exists
```

این دو دسته Cost و Failure Mode متفاوت دارند.

ساختار باید روشن کند:

```text
Local validation
Cross-aggregate / dependency validation
Persistence conflict validation
```

### Queryهای تکراری در Validation به حداقل برسند

Validation نباید برای هر Rule یا هر Check دوباره اطلاعات یکسان را از Database/Service بخواند.

Data مشترک را یک بار Load کن و Typed به Validatorها بده.

به‌خصوص در Loopهای Rule Validation مراقب N+1 باش.

### Date/Time Logic متمرکز و Typed باشد

کدهایی مثل:

```php
CarbonImmutable::parse($version['valid_from'] ?? 'now')
    ->max(CarbonImmutable::instance($this->clock->now()))
```

Business Intent را مبهم می‌کنند و fallback به `'now'` ممکن است Validation Rule دیگری را پنهان کند.

ابتدا required بودن `valid_from` را واضح validate کن.

سپس Dateهای معتبر را به Type مناسب تبدیل کن و از آن‌ها استفاده کن.

از `parse(... ?? 'now')` برای عبور دادن Data ناقص از Validation جلوگیری کن.

اگر پروژه Clock abstraction دارد، Conversion و timezone policy باید consistent باشد.

### Booleanها و naming باید معنی Rule را نشان دهند

به جای:

```php
if (!$this->matrices->ranksValid($effectiveZones)) {
```

اگر naming دقیق‌تر ممکن است:

```php
if (!$this->pricingMatrixValidator->hasCompleteZoneRanks($effectiveZones)) {
```

یا naming متناسب با Domain واقعی.

همچنین نام‌هایی مثل:

```text
$version
$rule
$method
$r
$e
```

در Methodهای بزرگ کافی نیستند.

ترجیح:

```text
$tariffVersion
$pricingRule
$calculationMethod
$validationException
```

### Access Check از Validation Result جدا باشد

Authorization:

```php
$this->pricingAccessGuard->assertAccess(...);
```

یک Validation Error نیست.

Access check باید در Boundary مناسب انجام شود و Failure آن با `ValidationErrorCollection` مخلوط نشود.

Policy/Middleware/Gate لاراول را نیز طبق Ruleهای قبلی بررسی کن.

### نتیجه نهایی Validation باید قابل استفاده و self-documenting باشد

Flow نهایی باید چیزی شبیه این باشد:

```php
public function validate(
    AuthenticatedPrincipal $actor,
    string $tariffVersionId,
): TariffValidationResult {
    $tariffVersion = $this->tariffVersionRepository
        ->findForValidation($actor->hqId, $tariffVersionId);

    $errors = new ValidationErrorCollection();

    $errors->addMany(
        $this->tariffStructureValidator->validate($tariffVersion),
    );

    $errors->addMany(
        $this->pricingRuleValidator->validateAll(
            $actor,
            $tariffVersion,
        ),
    );

    $errors->addMany(
        $this->tariffDependencyValidator->validate(
            $actor,
            $tariffVersion,
        ),
    );

    return TariffValidationResult::fromErrors($errors);
}
```

هدف این نیست که دقیقاً همین Classها ساخته شوند؛ هدف این است که Developer بتواند از روی Method اصلی بفهمد چه دسته Validationهایی انجام می‌شوند و برای فهم یک Business Rule مجبور نباشد یک متد صدخطی را Decode کند.

اصل نهایی:

> Validation code is business code and must be readable.

> Group related rules by domain concern.

> Do not represent validation contracts with anonymous arrays.

> Expected validation failures should not rely on generic exceptions for control flow.

> Local validation and dependency validation should have clear boundaries.

---

## 40. Import / Parser / Workbook Serviceها باید Parsing، Validation و Domain Logic را از هم جدا کنند

کلاس‌هایی که فایل، Workbook، CSV، XLSX یا Payload خارجی را Parse می‌کنند نباید همزمان مسئول تمام مراحل زیر باشند:

- Decode / Read کردن فایل
- تشخیص Header
- Normalize کردن مقدارها
- Parse کردن Number
- Validation ساختار فایل
- Validation Business Rule
- ساخت ID
- ساخت Domain Data
- ساخت API Response
- Throw کردن HTTP-oriented Exception

مثلاً یک Service شبیه این:

```php
public function preview(string $encoded, array $matrix): array
{
    // read workbook
    // validate header
    // parse rows
    // normalize localized numbers
    // create ids
    // create matrix structure
    // validate pricing matrix
    // create response array
}
```

بیش از حد مسئولیت دارد.

Flow باید براساس Concernهای واقعی قابل فهم باشد:

```text
Workbook Input
    ↓
Workbook Reader
    ↓
Workbook Parser
    ↓
Typed Parsed Data
    ↓
Domain Validator
    ↓
Preview Result
```

این تفکیک نباید باعث ساختن Classهای ریز و بی‌معنی شود؛ فقط Conceptهایی که Responsibility واقعی دارند جدا شوند.

### Service Contract نباید با `array` ناشناس تعریف شود

این Signature:

```php
public function preview(string $encoded, array $matrix): array
```

برای Dataیی که Structure مشخص دارد مناسب نیست.

ترجیح:

```php
public function preview(
    MatrixWorkbookPreviewData $data,
): MatrixWorkbookPreviewResult
```

یا در صورت مناسب بودن:

```php
public function preview(
    WorkbookContent $workbook,
    TariffMatrixData $matrix,
): MatrixWorkbookPreviewResult
```

هم ورودی و هم خروجی باید Typed و self-documenting باشند.

### Parsed Rowها و Cellها Object / DTO مشخص داشته باشند

به جای ساختارهایی مانند:

```php
[
    'id' => $id,
    'zone_id' => $zoneId,
    'state' => 'RATE',
    'amount' => $amount,
]
```

از Type مشخص استفاده کن:

```php
new MatrixCellData(
    id: $id,
    zoneId: $zoneId,
    state: MatrixCellState::RATE,
    amount: $amount,
)
```

و برای Band:

```php
new MatrixBandData(
    id: $id,
    from: $from,
    to: $to,
    cells: $cells,
)
```

هدف این است که Matrix Structure از روی Typeها قابل فهم باشد، نه از روی Array Keyهای پراکنده.

### Magic Stringهای Parser و Domain جدا شوند

مقادیر Domain مانند:

```text
EMPTY
UNCOVERED
RATE
HIGHER_ZONE_RANK
DIRECTIONAL
```

اگر مجموعه محدود و مشخص دارند باید Enum باشند:

```php
MatrixCellState::EMPTY
MatrixCellState::UNCOVERED
MatrixCellState::RATE
ZonePolicy::HIGHER_ZONE_RANK
ZonePolicy::DIRECTIONAL
```

اما tokenهای صرفاً مربوط به parsing فایل، مثل نمایش‌های مختلف infinity:

```text
∞
بی نهایت
```

Domain Enum نیستند و باید در Parser/Normalizer به صورت واضح و متمرکز مدیریت شوند.

### Nested ternary و parsing expressionهای فشرده حذف شوند

این نوع کد:

```php
$state = $raw === ''
    ? 'EMPTY'
    : (in_array($raw, ['بدون پوشش', 'UNCOVERED'], true)
        ? 'UNCOVERED'
        : 'RATE');
```

خوانایی پایینی دارد.

ترجیح بده Intent نام‌گذاری شود:

```php
$cellState = $this->resolveCellState($rawValue);
```

یا اگر Logic خیلی ساده است از `match` استفاده کن:

```php
$cellState = match (true) {
    $rawValue === '' => MatrixCellState::EMPTY,
    $this->isUncoveredValue($rawValue) => MatrixCellState::UNCOVERED,
    default => MatrixCellState::RATE,
};
```

### Method Naming باید دقیق باشد

نام‌هایی مثل:

```php
number()
reject()
sample()
```

اگر Context کافی ندارند باید واضح‌تر شوند.

مثلاً:

```php
parseLocalizedNumber()
throwInvalidWorkbook()
generateSampleWorkbook()
```

یا Naming بهتر متناسب با مسئولیت واقعی.

Developer باید از روی نام Method بفهمد چه کاری انجام می‌شود.

### Number normalization باید یک Concern مشخص باشد

کدی مثل:

```php
strtr(...)
str_replace(['٬', ',', '٫'], ['', '', '.'], ...)
preg_match(...)
```

یک Parsing/Normalization Concern مشخص است.

اگر در چند بخش پروژه استفاده می‌شود، یک Component واحد مثل:

```text
LocalizedNumberParser
PersianNumberNormalizer
```

داشته باش.

اگر فقط مختص Workbook است، می‌تواند private method واضح داخل Parser باقی بماند.

اما نام `number()` برای چنین Logicی مبهم است.

### Magic Numberها باید معنی مشخص داشته باشند

عددهایی مثل:

```php
9007199254740991
```

نباید بدون توضیح وسط Business Logic باشند.

اگر این مقدار JS safe integer limit یا محدودیت مشخص سیستم است، Constant معنادار تعریف کن:

```php
private const MAX_SAFE_INTEGER = 9_007_199_254_740_991;
```

یا Config/Value Object مناسب، اگر Business configurable است.

همین Rule برای offsetهای مبهم و شماره ردیف‌هایی مثل:

```php
$index + 2
```

اعمال شود؛ اگر مفهوم مهمی دارند نام‌گذاری شوند.

### Header Parsing باید Self-documenting باشد

این نوع شرط:

```php
if (
    !$header
    || count($rows) === 0
    || !str_starts_with(trim($header[0] ?? ''), 'از')
    || !str_starts_with(trim($header[1] ?? ''), 'تا')
) {
```

چند Rule را در یک expression جمع کرده است.

ترجیح بده Validation intent واضح باشد:

```php
if (!$this->headerValidator->isValid($header)) {
    ...
}
```

یا چند Guard Clause واضح اگر Service جدا ارزش ندارد.

همچنین Header column indexهای `0`, `1`, `2`, `3` در صورت استفاده گسترده باید نام یا Constant مشخص داشته باشند.

### Parser Validation و Domain Validation جدا باشند

این دو یک چیز نیستند:

```text
Workbook has required columns
Cell contains a valid number
```

با:

```text
Matrix band sequence is valid
Zone policy is valid
Pricing ranges are valid
```

دسته اول Parsing/Input Validation است.

دسته دوم Domain Validation است.

Parser نباید Domain Ruleهای پیچیده را خودش پیاده کند.

Parsed DTO را به Domain Validator بده.

### ID Generation داخل Parser فقط با دلیل مشخص

Parser اصولاً باید Data را Parse کند.

اگر IDهایی که در Preview ساخته می‌شوند صرفاً temporary/client-facing هستند، این Requirement باید مشخص باشد.

در غیر این صورت تولید ID داخل Parser مسئولیت اضافی است.

بررسی کن آیا ID باید:

- هنگام Persistence تولید شود؛
- توسط Factory تولید شود؛
- یا واقعاً برای Preview لازم است.

ID generation را فقط چون ساخت Array فعلی به `id` نیاز دارد داخل Parser نگه ندار.

### Error Handling فایل از HTTP جدا باشد

Workbook Parser نباید برای هر Input Error مستقیماً به HTTP Status وابسته باشد:

```php
throw new ApiException(
    ApiErrorCode::ValidationError,
    422,
    $message,
);
```

ترجیح بده Parsing/Validation Result یا Exception مشخص Application/Domain داشته باشی و Laravel Exception Handler آن را به HTTP Response تبدیل کند.

مثلاً:

```php
throw InvalidMatrixWorkbook::invalidHeader();
```

یا Typed validation result، بسته به Flow پروژه.

### Messageهای کاربر در صورت نیاز به Localization متمرکز شوند

Messageهایی مثل:

```text
شیت اول باید ساختار فایل نمونه را داشته باشد...
تعداد ستون‌های زون فایل با ماتریس انتخاب‌شده برابر نیست...
نرخ ردیف ... باید عدد صحیح مثبت باشد...
```

اگر User-facing هستند و امکان Localization یا reuse وجود دارد، از Laravel localization (`lang`) یا Message catalog مشخص استفاده کن.

Business Service نباید پر از متن UI/API presentation باشد.

اگر پروژه فقط یک زبان دارد و Message واقعاً local به همین UseCase است، overengineering نکن؛ اما Convention باید یکسان باشد.

### Security Requirement نباید فقط Comment باشد

Commentهایی مثل:

```php
/** Bounded XLSX draft preview. Never evaluates formulas or extracts archive paths. */
```

به‌تنهایی Security Guarantee محسوب نمی‌شوند.

اگر این Rule امنیتی است باید با implementation و test enforce شود.

برای Workbook/File import بررسی کن:

- File size limit
- Row limit
- Column limit
- Archive entry limit
- Formula evaluation disabled
- External link/reference disabled در صورت applicable بودن
- Path traversal / archive extraction safety
- Memory limits
- MIME/type validation در Boundary مناسب

این محدودیت‌ها تا حد امکان در Reader/Storage/Input Boundary enforce شوند، نه وسط Domain Service.

### Fully Qualified Name و Relative Namespace در Property Type استفاده نشود

این نوع Constructor:

```php
public function __construct(
    private FreightMatrices $matrices,
    private Contracts\MatrixWorkbookStorage $workbooks,
    private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
)
```

باید با `use`های واضح در بالای فایل نوشته شود:

```php
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Pricing\Application\Contracts\MatrixWorkbookStorage;
```

و سپس:

```php
public function __construct(
    private FreightMatrices $freightMatrices,
    private MatrixWorkbookStorage $matrixWorkbookStorage,
    private IdentifierGenerator $identifierGenerator,
) {}
```

هم Naming dependencyها واضح‌تر است و هم Class خواناتر می‌شود.

### Class باید در Folder مربوط به مسئولیت خودش باشد

Classهایی مثل `MatrixWorkbook` نباید صرفاً در Root `Application/` قرار بگیرند اگر Service/Parser مشخص هستند.

براساس مسئولیت واقعی، Folder واضح انتخاب کن، مثلاً:

```text
Application/
  Services/
  Parsers/
  DTOs/
  Contracts/
```

یا structure ساده‌تر و consistent پروژه.

نام Class نیز باید مسئولیت را نشان دهد.

اگر Class فقط Preview Workbook تعرفه را انجام می‌دهد، نامی مانند:

```text
MatrixWorkbookPreviewService
TariffMatrixWorkbookParser
```

ممکن است واضح‌تر از `MatrixWorkbook` باشد؛ نام دقیق را براساس Responsibility واقعی انتخاب کن.

### خروجی Sample نیز Typed باشد

این Signature:

```php
public function sample(array $titles): array
```

اگر Structure مشخصی دارد باید Typed شود.

مثلاً:

```php
public function generateSample(
    MatrixWorkbookSampleData $data,
): WorkbookFile
```

اگر واقعاً فقط `list<string>` ساده است و DTO ارزش اضافه ندارد، حداقل type contract با PHPDoc مشخص باشد؛ اما طبق Convention پروژه برای Service dataهای ساختاریافته DTO ترجیح داده شود.

### نتیجه نهایی

یک Workbook Service سطح بالا باید Story واضح داشته باشد:

```text
1. Workbook را بخوان
2. Structure آن را validate کن
3. Rowها را به DTOهای Typed parse کن
4. Matrix Domain Rules را validate کن
5. Preview Result Typed را برگردان
```

نه اینکه Developer در یک Method مجبور باشد همزمان:

```text
array index
Persian number normalization
magic state
UUID generation
band ordering
pricing validation
HTTP exception
response shaping
```

را دنبال کند.

اصل نهایی:

> Parse external data at the boundary.

> Convert parsed data to typed objects early.

> Keep file-format concerns separate from domain rules.

> Security constraints must be enforced, not documented only in comments.

---
## 41. Domain Calculator / Rule Engineها باید Typed، Pure و قابل دنبال کردن باشند

Calculatorها و Rule Engineهای Domain نباید بر پایه‌ی `array<string, mixed>`، Magic String، Dynamic Key و expressionهای فشرده ساخته شوند.

کلاسی شبیه:

```php
public function calculate(array $rules, array $facts): array
```

Contract کافی و قابل فهمی ندارد.

Developer از روی Signature نمی‌فهمد:

- Rule دقیقاً چه Structureای دارد.
- Facts شامل چه مقادیری است.
- Calculation Methodهای معتبر چیست.
- خروجی شامل چه Lineهایی است.
- Money با چه واحدی نگه‌داری می‌شود.
- چه Validationهایی قبل از Calculation تضمین شده‌اند.

ترجیح:

```php
public function calculate(
    PricingRuleCollection $rules,
    PricingFacts $facts,
): PricingCalculationResult
```

یا Structure ساده‌تر و Typed متناسب با پروژه.

اصل:

> Domain calculation must operate on typed domain data.

> Do not make the calculator decode the shape of anonymous arrays.

### Ruleها باید Object / DTO Typed باشند

به جای:

```php
$rule['calculation_method']
$rule['basis']
$rule['fixed_amount']
$rule['unit_rate']
$rule['percentage_bps']
$rule['category']
```

از Object واضح استفاده کن:

```php
$rule->calculationMethod
$rule->basis
$rule->fixedAmount
$rule->unitRate
$rule->percentageBps
$rule->category
```

مثلاً:

```php
final readonly class PricingRule
{
    public function __construct(
        public int $id,
        public PricingCalculationMethod $calculationMethod,
        public PricingBasis $basis,
        public ChargeCategory $category,
        public int $priority,
        public ?int $fixedAmount,
        public ?int $percentageBps,
    ) {}
}
```

Type واقعی Propertyها را براساس Domain پروژه تعیین کن.

### Facts نباید `array<string, float|int|bool>` باشند

این:

```php
$facts['actual_weight_kg']
$facts['billable_weight_kg']
$facts['parcel_count']
$facts['declared_value_amount']
$facts['cod_amount']
```

یک Contract مخفی ایجاد می‌کند.

ترجیح:

```php
final readonly class PricingFacts
{
    public function __construct(
        public float $actualWeightKg,
        public float $billableWeightKg,
        public int $parcelCount,
        public int $declaredValueAmount,
        public int $codAmount,
    ) {}
}
```

یا Value Objectهای دقیق‌تر در صورت نیاز واقعی.

### Calculation Methodها باید Enum باشند

Magic Stringهایی مثل:

```text
FIXED
PER_UNIT
SLAB
TIERED
PERCENT
MIN_MAX
```

باید با Enum مشخص جایگزین شوند:

```php
PricingCalculationMethod::FIXED
PricingCalculationMethod::PER_UNIT
PricingCalculationMethod::SLAB
PricingCalculationMethod::TIERED
PricingCalculationMethod::PERCENT
PricingCalculationMethod::MIN_MAX
```

همین Rule برای موارد زیر نیز اعمال شود:

```text
ACTUAL_WEIGHT
BILLABLE_WEIGHT
PARCEL_COUNT
DECLARED_VALUE
COD_AMOUNT

BASE
SURCHARGE
COMMISSION
DISCOUNT
TAX

NONE
CEIL
FLOOR
HALF_UP
```

در صورت Domain Meaning، Enumهای مناسب مانند:

```text
PricingBasis
ChargeCategory
AmountRoundingMode
```

تعریف شوند.

### `default => 0` برای State ناشناخته ممنوع

این Pattern:

```php
default => 0,
```

در یک Domain Calculator خطرناک است چون Configuration یا State نامعتبر را silently به Amount صفر تبدیل می‌کند.

اگر تمام Methodها Enum هستند، Match باید exhaustive باشد.

اگر به هر دلیلی مقدار نامعتبر وارد شد، باید failure مشخص داشته باشد، نه fallback خاموش.

اصل:

> Invalid pricing configuration must fail explicitly, not calculate zero silently.

### Validation و Calculation مرز مشخص داشته باشند

Calculator نباید با `?? 0` و fallbackهای متعدد Invalid Configuration را مخفی کند.

مثلاً:

```php
(int) ($rule['fixed_amount'] ?? 0)
(float) ($rule['unit_rate'] ?? 0)
```

اگر `FIXED` بدون `fixedAmount` نامعتبر است، این Rule باید قبل از calculation validate شده باشد.

Calculator باید تا حد ممکن روی داده‌ای کار کند که invariantهای لازم آن قبلاً تضمین شده‌اند.

مثلاً:

```php
PricingRuleValidator
```

تضمین کند:

```text
FIXED -> fixedAmount exists
PER_UNIT -> unitRate exists
PERCENT -> percentageBps exists
MIN_MAX -> required bound exists
```

سپس Calculator مجبور به fallbackهای مبهم نباشد.

### Business Logicهای Calculation باید Methodهای معنی‌دار داشته باشند

یک `match` بسیار بزرگ با expressionهای طولانی نباید تمام Calculation Algorithm را در یک Statement نگه دارد.

مثلاً این نوع branch:

```php
'SLAB' => isset(...)
    ? $this->incrementalAmount(...)
    : $this->money(...),
```

باید به Method معنادار تبدیل شود:

```php
PricingCalculationMethod::SLAB =>
    $this->calculateSlabAmount($rule, $quantity),
```

و Call Site باید Business Intent را نشان دهد.

اگر تعداد Methodها و رفتارها بعداً زیاد شد، می‌توان Strategyهای مستقل بررسی کرد؛ اما از ابتدا برای هر Method یک hierarchy پیچیده نساز.

### Calculator باید Pure باقی بماند

Domain Calculator نباید:

- Database Query بزند.
- Eloquent Model reload کند.
- HTTP Exception بسازد.
- Event dispatch کند.
- Log بنویسد.
- Clock یا Request بخواند.
- Configuration را از Database resolve کند.

همه Inputهای لازم باید از طریق Parameterهای Typed به آن داده شوند.

Calculation باید برای Input یکسان Result یکسان بدهد.

### `json_decode()` داخل Domain Calculator نباشد

این Pattern:

```php
json_decode($rule['conditions'], true)
json_decode($rule['basis_charge_codes'], true)
```

نشان می‌دهد Persistence Representation وارد Domain Logic شده است.

JSON باید در Eloquent Cast / Mapper / Repository Boundary decode شود.

Calculator باید از ابتدا Data Typed بگیرد:

```php
$rule->conditions
$rule->basisChargeCodes
```

نه JSON string.

### Condition Evaluation نباید Dynamic Array Matching باشد

این نوع generic code:

```php
foreach ($conditions as $key => $expected) {
    if (($facts[$key] ?? null) !== $expected) {
        return false;
    }
}
```

Schema و Ruleهای مجاز را مخفی می‌کند.

اگر Conditionها Domain Concept واقعی هستند، Type مشخص داشته باشند.

مثلاً:

```php
PricingCondition
PricingConditionType
```

یا مجموعه‌ای از condition DTOها.

اگر فقط چند condition مشخص داریم، explicit بودن بهتر از یک mini dynamic rule language مبهم است.

از ساخت Rule Engine generic بدون Requirement واقعی خودداری کن.

### Money Calculation با `float` انجام نشود

برای Amountهای مالی، `float` می‌تواند خطای precision ایجاد کند.

این نوع code باید بررسی شود:

```php
$quantity * (float) $rule->unitRate
floor($amount + 0.5)
```

Money Amountها تا حد ممکن با integer minor unit نگه‌داری شوند:

```text
rial
toman
cent
minor unit
```

و Rateها نیز با representation دقیق و مشخص باشند.

اگر Decimal precision واقعی نیاز است، از Strategy دقیق و consistent پروژه استفاده کن؛ package جدید را بدون بررسی نیاز پروژه اضافه نکن.

اصل:

> Money must not depend on binary floating-point precision.

### Weight / Quantity precision باید Value واضح داشته باشد

این:

```php
$quantity * 10000
$step * 10000
```

یک precision policy پنهان دارد.

اگر چهار رقم اعشار Business Rule است، Constant یا Value Object مشخص داشته باش:

```php
private const QUANTITY_SCALE = 10_000;
```

یا Type مشخص برای Weight/Quantity.

Magic scaling factor نباید وسط Calculation پخش شود.

### Result Lineها باید Typed باشند

این Structure:

```php
$lines[] = [
    'charge_type_id' => ...,
    'charge_code' => ...,
    'category' => ...,
    'calculation_method' => ...,
    'quantity' => ...,
    'unit_rate' => ...,
    'amount' => ...,
    'taxable' => ...,
    'explanation' => [...],
];
```

باید به DTO/Object مشخص تبدیل شود.

مثلاً:

```php
new PricingCalculationLine(
    chargeTypeId: $rule->chargeTypeId,
    chargeCode: $rule->chargeCode,
    category: $rule->category,
    calculationMethod: $rule->calculationMethod,
    quantity: $quantity,
    amount: $amount,
    taxable: $rule->taxable,
    explanation: $explanation,
)
```

و `explanation` نیز اگر Structure مشخص دارد DTO مخصوص خودش داشته باشد، نه nested associative array ناشناس.

### Calculation Result باید Typed باشد

به جای:

```php
return [
    'lines' => $lines,
    'subtotal_amount' => $subtotal,
    'discount_amount' => $discount,
    'tax_amount' => $tax,
    'total_amount' => $total,
    'result_fingerprint' => $fingerprint,
];
```

ترجیح:

```php
return new PricingCalculationResult(
    lines: $lines,
    subtotalAmount: $subtotal,
    discountAmount: $discount,
    taxAmount: $tax,
    totalAmount: $total,
    fingerprint: $fingerprint,
);
```

### Aggregate calculation با Collection/Loop واضح انجام شود

این:

```php
array_sum(array_map(
    fn ($line) => in_array(...)
        ? $line['amount']
        : 0,
    $lines,
));
```

اگر خوانایی را پایین می‌آورد باید ساده شود.

مثلاً Calculation Result Collection می‌تواند Methodهای واضح داشته باشد:

```php
$subtotal = $lines->subtotalAmount();
$discount = $lines->discountAmount();
$tax = $lines->taxAmount();
```

یا یک `foreach` واضح.

هدف کم کردن تعداد خطوط نیست؛ هدف واضح بودن Rule مالی است.

### Sorting Ruleها باید Method معنی‌دار داشته باشد

این Comparator:

```php
usort(
    $rules,
    static fn ($a, $b) =>
        [
            (int) $a['priority'],
            (string) $a['charge_type_code'],
            (float) ($a['range_from'] ?? 0),
        ]
        <=>
        [
            (int) $b['priority'],
            (string) $b['charge_type_code'],
            (float) ($b['range_from'] ?? 0),
        ],
);
```

در نگاه اول Business Ordering را توضیح نمی‌دهد.

Sorting Rule باید نام مشخص داشته باشد:

```php
$rules = $this->ruleOrdering->sort($rules);
```

یا comparator Method واضح:

```php
usort($rules, $this->comparePricingRules(...));
```

اگر Collection Typed داریم، sorting باید intent-based باشد.

### Slab tracking با Array Flag مبهم نباشد

این:

```php
$slabs[$rule['charge_type_code']] = true;
```

و:

```php
isset($slabs[$rule['charge_type_code']])
```

باید naming واضح داشته باشد.

مثلاً:

```php
$appliedSlabChargeCodes
```

یا Set/Collection مشخص.

Variable باید بگوید چرا این state نگه‌داری می‌شود.

### Fingerprint generation از Main Calculation جدا باشد

این:

```php
hash(
    'sha256',
    json_encode(
        [$fingerprintLines, $subtotal, $discount, $tax, $total],
        JSON_THROW_ON_ERROR,
    ),
)
```

یک Concern مستقل است.

اگر fingerprint Contract مهم سیستم است، یک Component یا Method مشخص داشته باشد:

```php
$fingerprint = $this->fingerprintGenerator->generate($result);
```

یا حداقل:

```php
$fingerprint = $this->generateFingerprint(...);
```

همچنین Input fingerprint باید canonical و deterministic باشد.

Sorting، numeric representation و serialization format باید مشخص باشند تا تغییر incidental در array shape fingerprint را ناخواسته تغییر ندهد.

### اسم `DeterministicCalculator` مسئولیت را کامل توضیح نمی‌دهد

`Deterministic` یک ویژگی implementation است، نه Domain Responsibility.

اگر این Calculator مربوط به Pricing/Tariff/Freight است، نام باید آن را بگوید:

```text
TariffCalculator
FreightPricingCalculator
PricingRuleCalculator
```

نام دقیق براساس Domain واقعی انتخاب شود.

### Methodهای One-line و چند Statement در یک خط حذف شوند

این Style:

```php
private function money(float $amount): int { return (int) floor($amount + 0.5); }
```

یا:

```php
if (...) continue;
```

در جاهایی که Flow پیچیده است باید به formatting خوانا تبدیل شود.

Guard clause کوتاه می‌تواند یک خط باشد اگر کاملاً واضح است، اما در Domain Calculationهای پیچیده readability اولویت دارد.

### PHPDoc نباید جای Type واقعی را بگیرد

این:

```php
/** @param list<array<string, mixed>> $rules */
```

اگر Structure واقعی Rule شناخته‌شده است، نباید جای DTO/Class را بگیرد.

PHPDoc Generic برای Collection Type مفید است، اما نباید anonymous array shape را به Domain Model تبدیل کند.

### نتیجه مطلوب

Flow Calculator باید در سطح بالا تقریباً چنین Storyای داشته باشد:

```text
1. Ruleها را با Ordering مشخص مرتب کن
2. Ruleهای applicable را مشخص کن
3. Quantity مورد نیاز Rule را resolve کن
4. Amount را براساس Calculation Method محاسبه کن
5. Rounding Policy را اعمال کن
6. Calculation Line Typed بساز
7. Totals را محاسبه کن
8. Fingerprint deterministic بساز
9. PricingCalculationResult برگردان
```

Developer باید بتواند این Flow را بدون Decode کردن ده‌ها array key، Magic String و nested expression دنبال کند.

اصل نهایی:

> Financial domain code must be explicit.

> Typed domain objects are preferred over anonymous rule/fact arrays.

> Invalid configuration must fail explicitly.

> Determinism must come from explicit ordering and canonical data, not incidental array behavior.

---
## 42. Domain Classها نباید Validator، Compiler، Compatibility Adapter و Utility را همزمان انجام دهند

Domain Class باید Responsibility مشخص و محدود داشته باشد.

کلاسی که همزمان متدهایی مانند:

```text
validate()
compile()
linearBands()
ranksValid()
```

دارد، باید از نظر Cohesion و Responsibility بازبینی شود.

در چنین ساختاری معمولاً چند Concern متفاوت با هم مخلوط شده‌اند:

```text
Matrix validation
Matrix-to-rule compilation
Legacy format compatibility
Zone rank validation
Precision calculation
Duplicate detection
Rule construction
```

این‌ها الزاماً نباید داخل یک Class باقی بمانند.

هدف این نیست که برای هر Method یک Class جدید ساخته شود؛ هدف این است که Componentها براساس Business Responsibility واقعی گروه‌بندی شوند.

مثلاً در صورت تطابق با Domain واقعی پروژه:

```text
FreightMatrixValidator
FreightMatrixCompiler
FreightMatrixNormalizer
ZoneRankValidator
```

یا ساختاری ساده‌تر که همان Separation of Concerns را حفظ کند.

اگر Component استخراج‌شده Service محسوب می‌شود و از Dependency Injection استفاده می‌کند، باید طبق Convention سراسری پروژه Contract/Interface مشخص داشته باشد.

### نام Class باید Responsibility واقعی را توضیح دهد

نامی مثل:

```text
FreightMatrices
```

مبهم است، چون مشخص نمی‌کند Class:

- Entity است؟
- Collection است؟
- Validator است؟
- Compiler است؟
- Service است؟
- Factory است؟

نام Class باید از روی مسئولیتش قابل فهم باشد.

مثلاً:

```text
FreightMatrixValidator
FreightMatrixRuleCompiler
FreightMatrixCompatibilityNormalizer
```

نام دقیق باید براساس Responsibility واقعی انتخاب شود.

### Validation closureهای محلی و Error Collectorهای مبهم حذف شوند

این Pattern:

```php
$error = static function (
    string $code,
    string $field,
) use (&$errors): void {
    $errors[] = compact('code', 'field');
};
```

خوانایی Validation را پایین می‌آورد و Validation Contract را به anonymous array وابسته می‌کند.

ترجیح بده Error Collection یا Result Typed داشته باشی:

```php
$errors->add(
    PricingValidationError::duplicateMatrixId($fieldPath),
);
```

یا:

```php
$errors->add(
    new ValidationErrorData(
        code: PricingValidationErrorCode::MATRIX_ID_DUPLICATE,
        field: $fieldPath,
    ),
);
```

### Validation path stringها تا حد ممکن متمرکز باشند

ساختن pathهایی مثل:

```php
$path = "freight_matrices.{$matrixIndex}";
$bandPath = $path . '.bands.' . $bandIndex;
$cellPath = $bandPath . '.cells.' . $cellIndex;
```

اگر بخشی از API Validation Contract هستند، باید ساختار واضح و consistent داشته باشند.

در صورت تکرار زیاد، Helper/Value Object مشخص برای ساخت Validation Path می‌تواند استفاده شود، اما فقط اگر خوانایی را بهتر کند.

از string concatenation پراکنده در ده‌ها Validator جلوگیری کن.

### Duplicate Detection با array flag و composite string مبهم نباشد

Patternهایی مثل:

```php
$ids[$matrix['id']] = true;
$contexts[$context] = true;
$seen[$cell['zone_id']] = true;
```

اگر فقط Set semantics دارند، naming باید واضح باشد:

```text
$seenMatrixIds
$seenMatrixContexts
$seenZoneIds
```

و اگر context identity از چند Field ساخته می‌شود، از:

```php
implode('|', [...])
```

یا concatهای مبهم استفاده نکن.

در صورت نیاز Value Object یا key builder معنادار استفاده کن.

### Validation conditionهای بسیار بلند باید شکسته شوند

این نوع شرط:

```php
if (
    $last === null
    || $last['to'] === null
    || (float) $segment['from'] !== (float) $last['to']
    || !is_finite($step)
    || $step <= 0
    || $step > 99999999
    || abs($step * 10000 - round($step * 10000)) > 0.00001
    || ($end === null && $linearIndex !== array_key_last($linear))
) {
```

چند Business Rule مستقل را در یک boolean expression مخفی می‌کند.

آن‌ها را براساس Concept نام‌گذاری کن:

```php
if (!$this->hasValidLinearContinuation($previousBand, $segment)) {
    ...
}

if (!$this->hasValidStep($segment->stepKg)) {
    ...
}

if (!$this->hasValidOpenEndedPosition($segment, $linearBands)) {
    ...
}
```

یا ساختار واضح مشابه.

هدف این است که Error دقیقاً به Rule مشخص مربوط باشد، نه یک شرط بزرگ که معلوم نیست کدام بخش fail شده است.

### Precision Policy باید یکجا تعریف شود

این Patternها:

```php
$step * 10000
$from * 10000
$to * 10000
0.00001
99999999
```

Business/technical constraints مهمی را به صورت Magic Number پخش می‌کنند.

باید مشخص شود:

```text
Quantity precision
Maximum step
Comparison epsilon
Scale
```

چه هستند و چرا.

مثلاً:

```php
private const QUANTITY_SCALE = 10_000;
private const MAX_LINEAR_STEP = 99_999_999;
```

یا Value Object/Policy مناسب اگر در چند بخش Domain استفاده می‌شوند.

همان Precision Policy باید در Validator و Compiler یکسان باشد.

### Float equality مستقیم برای Domain quantity بررسی شود

کدی مثل:

```php
(float) $segment['from'] !== (float) $last['to']
```

برای decimal quantity قابل اعتماد نیست.

اگر Domain چهار رقم precision دارد، ابتدا مقدار را normalize/scale کن و سپس compare کن.

Representation و comparison strategy باید در کل Pricing Domain یکسان باشد.

### `compile()` نباید anonymous rule array بسازد

این:

```php
$rules[] = [
    'rate_rule_id' => ...,
    'calculation_method' => 'SLAB',
    'basis' => 'BILLABLE_WEIGHT',
    'priority' => 10,
    'conditions' => [],
];
```

باید با DTO/Object مشخص جایگزین شود:

```php
$rules[] = new CompiledPricingRule(
    rateRuleId: $cell->id,
    calculationMethod: PricingCalculationMethod::SLAB,
    basis: PricingBasis::BILLABLE_WEIGHT,
    priority: PricingPriority::MATRIX,
    // ...
);
```

نام Type واقعی را براساس Architecture پروژه تعیین کن.

Compiler باید یک Collection/DTO Typed برگرداند، نه `list<array<string,mixed>>`.

### Rule Construction تکراری باید متمرکز شود

در نمونه‌های مشابه، ساخت Rule ثابت و Linear تعداد زیادی Field مشترک دارد:

```text
rate_rule_id
matrix_cell_id
service_offering_version_id
service_option_version_id
origin_zone_id
destination_zone_id
charge_type_id
calculation_method
basis
priority
conditions
basis_charge_codes
```

این duplication باید با Factory/Builder ساده و معنی‌دار یا Constructor Typed کاهش پیدا کند.

اما Generic Builder پیچیده ایجاد نکن.

مثلاً:

```php
$rule = CompiledPricingRule::fromMatrixCell(
    matrix: $matrix,
    band: $band,
    cell: $cell,
    chargeTypeId: $chargeTypeId,
);
```

و برای Linear behavior فقط Fieldهای مربوطه مشخص باشند.

### Magic Priority حذف شود

این:

```php
'priority' => 10,
```

اگر Meaning Domain دارد نباید Magic Number باشد.

از Constant/Enum/Policy مشخص استفاده کن:

```php
PricingRulePriority::FREIGHT_MATRIX
```

یا:

```php
private const DEFAULT_MATRIX_RULE_PRIORITY = 10;
```

بسته به اینکه Priority واقعاً Domain Concept است یا implementation detail.

### Triple Nested Loop باید از نظر خوانایی بازبینی شود

این Pattern:

```php
foreach ($matrices as $matrix) {
    foreach ($matrix['bands'] as $band) {
        foreach ($band['cells'] as $cell) {
            ...
        }
    }
}
```

خودش الزاماً اشتباه نیست، اما اگر داخل آن Rule construction و branching زیاد وجود دارد، Method باید به operationهای معنادار شکسته شود.

مثلاً:

```php
$rules->addMany(
    $this->compileFixedBands($matrix, $chargeTypeId),
);

$rules->addMany(
    $this->compileLinearBands($matrix, $chargeTypeId),
);
```

هدف کاهش nesting و واضح شدن Business Flow است.

### Legacy Compatibility نباید وسط Domain API پخش باشد

این Method:

```php
public function linearBands(array $matrix): array
{
    if (array_key_exists('linear_bands', $matrix)) {
        return $matrix['linear_bands'];
    }

    return empty($matrix['linear_tail'])
        ? []
        : [[...$matrix['linear_tail'], 'to' => null]];
}
```

در واقع Compatibility Logic برای Format قدیمی است.

این Concern باید در Boundary یا Normalizer مشخص انجام شود تا Domain Logic همیشه یک Shape canonical بگیرد.

ترجیح:

```text
Legacy persisted shape
    ↓
FreightMatrixNormalizer
    ↓
Canonical FreightMatrix
    ↓
Validator / Compiler
```

Validator و Compiler نباید هر بار Legacy Data Shape را بفهمند.

### Canonical Data Shape باید قبل از Domain Logic ساخته شود

در Domain Core نباید همزمان از:

```text
linear_bands
linear_tail
```

برای یک Concept پشتیبانی شود.

در Boundary آن‌ها را normalize کن و داخل Domain فقط یک Representation داشته باش.

اصل:

> Normalize once at the boundary, not repeatedly inside domain logic.

### `ranksValid()` باید در Context مناسب خودش قرار بگیرد

متدی مثل:

```php
public function ranksValid(array $zones): bool
```

ارتباط مستقیمی با Compile کردن Freight Matrix ندارد.

اگر Zone Rank Validation یک Domain Rule مستقل است، باید در Validator مربوط به Zone/Policy قرار بگیرد.

این Rule کمک می‌کند Classها cohesive باقی بمانند.

### Array lookup برای Base Cellها ساده و efficient شود

این Pattern:

```php
$base = array_values(
    array_filter(
        $last['cells'] ?? [],
        static fn ($cell) =>
            $cell['zone_id'] === $currentCell['zone_id'],
    ),
);
```

برای هر Cell دوباره لیست را scan می‌کند و خوانایی هم پایین است.

اگر lookup براساس `zone_id` داریم، یک Map واضح بساز:

```php
$baseCellsByZoneId = $this->indexCellsByZoneId($previousBand->cells);
```

یا Collection:

```php
$baseCellsByZoneId = $previousBand->cells->keyBy(
    fn (MatrixCell $cell) => $cell->zoneId,
);
```

سپس:

```php
$baseCell = $baseCellsByZoneId->get($cell->zoneId);
```

این هم Intent را روشن می‌کند و هم از repeated scan جلوگیری می‌کند.

### Assumptionهای خطرناک قبل از index access مشخص شوند

کدی مثل:

```php
$base = $bases[$cell['zone_id']];
```

فرض می‌کند key همیشه وجود دارد.

اگر Validator این invariant را تضمین کرده، این contract باید روشن باشد.

در غیر این صورت failure باید explicit باشد.

از undefined index به‌عنوان روش کشف invalid data استفاده نکن.

### `linearBands()` و helperهای مشابه public نباشند مگر API واقعی Domain باشند

Public Method باید بخشی از Contract واقعی Class باشد.

اگر Method فقط internal normalization helper است، public بودن آن API surface را بی‌دلیل بزرگ می‌کند.

بعد از Responsibility split، visibility هر Method را بازبینی کن.

### Comment نباید جای Architecture را بگیرد

Commentهایی مثل:

```php
/** Version-owned editing metadata compiled into the existing typed rate-rule engine. */
```

یا:

```php
/** Read legacy single tails without modifying saved versions or snapshots. */
```

ممکن است Context مفیدی بدهند، اما اگر Class هنوز چند Responsibility و چند Data Shape دارد، Comment مشکل Architecture را حل نمی‌کند.

کد و Typeها باید Responsibility و compatibility boundary را خودشان نشان دهند.

### نتیجه مطلوب

Flow کلی باید چیزی شبیه این باشد:

```text
Raw / Legacy Freight Matrix Data
    ↓
Normalizer
    ↓
Typed FreightMatrix
    ↓
FreightMatrixValidator
    ↓
FreightMatrixCompiler
    ↓
Collection<CompiledPricingRule>
```

و هر Component فقط Concern خودش را بداند.

اصل نهایی:

> One domain class should have one coherent reason to change.

> Normalize legacy data before it enters core domain logic.

> Validation and compilation are different responsibilities.

> Compile typed rules, not anonymous arrays.

> Precision policy must be explicit and shared.

---

## 43. کد باید برای Junior Developer و Developer ناآشنا با Business قابل فهم باشد

سادگی و قابل فهم بودن کد یک Preference نیست؛ **Requirement اصلی این Refactor است.**

کد نهایی باید طوری باشد که یک Developer با سطح Junior یا Mid-Level پایین که:

- تازه وارد پروژه شده؛
- با Business Domain آشنایی زیادی ندارد؛
- تمام تاریخچه تصمیمات معماری را نمی‌داند؛
- Context ذهنی تیم فعلی را ندارد؛

بتواند در زمان منطقی Flow یک Feature را بفهمد و با ریسک قابل کنترل تغییر دهد.

### کد باید بدون Knowledge پنهان قابل فهم باشد

برای فهمیدن یک Method یا Class نباید نیاز باشد Developer بداند:

- چرا 2 سال قبل فلان naming انتخاب شده؛
- فلان key مخفف چه چیزی است؛
- این Array چه Shapeای دارد؛
- این Magic Number از کجا آمده؛
- کدام Service به طور ضمنی چه Side Effectی دارد؛
- کدام Trigger در Database Behavior را تغییر می‌دهد؛
- کدام Helper پشت صحنه چه Dataیی اضافه می‌کند.

این Context باید از طریق:

```text
Type
Naming
Folder structure
DTO
Enum
Method signature
Relationship
Simple control flow
```

قابل فهم باشد.

### Call Site باید تا حد ممکن Self-explanatory باشد

Developer باید با دیدن:

```php
$routePlan = $this->routePlanService->create($data);
```

یا:

```php
$errors = $this->pricingRuleValidator->validate($pricingRule);
```

تا حد زیادی بفهمد چه اتفاقی می‌افتد.

این Call Site:

```php
$result = $this->manager->process($data, true, 3);
```

قابل قبول نیست چون Meaning پارامترها و Action مشخص نیست.

ترجیح:

```php
$result = $this->pricingRuleService->publish(
    rule: $pricingRule,
    validateDependencies: true,
);
```

یا DTO مشخص.

### Method باید از بالا به پایین قابل خواندن باشد

Flow اصلی باید Linear و قابل دنبال کردن باشد.

ترجیح:

```php
$consignment = $this->loadConsignment($data);

$destination = $this->resolveDestination($consignment);

$route = $this->resolveRoute($consignment, $destination);

return $this->createRoutePlan($data, $route);
```

نه Flowی که Developer مجبور باشد:

```text
nested callback
nested if
array mutation
dynamic key
exception branch
raw query
json encode
manual mapping
```

را همزمان دنبال کند.

### Complexity Budget داشته باش

هر Class و Method باید Complexity محدودی داشته باشد.

اگر Method برای فهمیدن نیاز به چند دقیقه تمرکز و دنبال کردن Stateهای متعدد دارد، باید ساده شود.

نشانه‌های هشدار:

- Methodهای بسیار بلند
- بیش از حد branch
- nested condition
- nested loops با Logic زیاد
- تعداد زیاد local variable با معنی مبهم
- mutation زیاد روی یک array
- چند نوع side effect داخل یک method
- بیش از یک سطح abstraction در یک flow
- ورودی‌های generic
- خروجی‌های generic

هدف این نیست که metric مصنوعی مثل حداکثر 20 خط enforce شود؛ هدف **cognitive simplicity** است.

### Business Ruleها باید از روی Naming قابل حدس باشند

اگر Rule این است:

```text
Driver must be active before assignment
```

کد باید چیزی نزدیک به این باشد:

```php
if (!$driver->isActive()) {
    throw DriverMustBeActive::forAssignment();
}
```

نه:

```php
if ($driver->status !== 'ACTIVE') {
    ...
}
```

در چند جای مختلف پروژه.

### Folder Structure باید قابل پیش‌بینی باشد

Developer جدید باید بتواند حدس بزند:

```text
Service کجاست؟
DTO کجاست؟
Enum کجاست؟
Exception کجاست؟
Policy کجاست؟
Repository کجاست؟
Resource کجاست؟
```

نباید Classهای مختلف بدون Convention داخل Root Folderهایی مثل:

```text
Application/
Domain/
Operations/
```

پخش شده باشند.

### یک Feature باید End-to-End قابل Follow باشد

Developer باید بتواند Flow را تقریباً به این شکل دنبال کند:

```text
Route
→ Controller
→ FormRequest
→ DTO
→ UseCase
→ Service
→ Repository / Eloquent
→ Model
→ Resource
```

اگر برای یک Feature ساده مجبور باشد 15 تا 20 فایل واسط بدون Business Value باز کند، Architecture بیش از حد پیچیده است.

### تعداد Jump بین فایل‌ها را کم کن

Abstraction زمانی ارزش دارد که:

- Responsibility را واضح‌تر کند؛
- reuse واقعی ایجاد کند؛
- boundary واقعی بسازد؛
- testing یا maintainability را واقعاً بهتر کند.

اگر فقط باعث شود Developer برای فهمیدن یک خط 4 فایل دیگر باز کند، abstraction نامناسب است.

### Explicit بودن به clever بودن ترجیح دارد

این:

```php
foreach ($drivers as $driver) {
    if (!$driver->isActive()) {
        continue;
    }

    $availableDrivers[] = $driver;
}
```

در بسیاری از موارد از pipeline پیچیده Collection/Callback بهتر است.

هدف نشان دادن مهارت زبانی نیست؛ هدف maintainable code است.

### Comment فقط برای Why باشد، نه What

اگر لازم است Comment توضیح دهد کد چه می‌کند، ابتدا Naming و Structure را بهتر کن.

Comment مناسب:

```php
// This threshold matches the carrier contract and must remain backward-compatible.
```

Comment نامناسب:

```php
// Loop through drivers and check active status.
```

کد باید What را خودش توضیح دهد.

### Onboarding Test به عنوان معیار ذهنی استفاده شود

برای هر بخش مهم از کد این سؤال را بپرس:

> اگر فردا یک Developer جدید وارد تیم شود و فقط Laravel را در حد معمول بلد باشد، آیا می‌تواند بدون توضیح شفاهی طولانی بفهمد این کد چه می‌کند و کجا باید تغییر بدهد؟

اگر پاسخ خیر است، Structure یا Naming هنوز بیش از حد پیچیده است.

### تغییر Feature نباید نیازمند دانستن کل سیستم باشد

هر Module/Feature باید تا حد ممکن Local Reasoning داشته باشد.

Developer برای تغییر Pricing Rule نباید مجبور باشد:

- Authorization internals
- unrelated module architecture
- custom framework layer
- database trigger behavior
- generic infrastructure abstraction

را کامل بفهمد.

Boundaryها باید Context موردنیاز را محدود کنند.

### از اصطلاحات Business واقعی استفاده کن، اما آن‌ها را پیچیده نکن

Domain Language خوب است، ولی نام‌های بیش از حد آکادمیک یا معماری‌محور خوانایی را خراب می‌کنند.

ترجیح:

```text
DriverAssignmentService
RoutePlanService
PricingRuleValidator
```

به جای:

```text
OperationalAssignmentOrchestrator
RoutingResolutionCoordinator
PricingSpecificationEvaluationManager
```

اسم باید دقیق ولی ساده باشد.

### Refactor موفق یعنی کاهش Cognitive Load

در پایان Refactor باید:

- تعداد conceptهایی که برای فهم یک flow لازم است کمتر شده باشد.
- نام‌ها واضح‌تر شده باشند.
- Data shapeها typed شده باشند.
- Flowها کوتاه‌تر و خطی‌تر شده باشند.
- رفتارهای پنهان کمتر شده باشند.
- framework-native code بیشتر شده باشد.
- custom abstraction کمتر و هدفمندتر شده باشد.
- نیاز به tribal knowledge کاهش پیدا کرده باشد.

اصل نهایی:

> Code should be understandable before it is impressive.

> Optimize for the next developer, not for architectural sophistication.

> A junior developer should be able to trace and modify common flows safely.

> Business knowledge should help understanding the code, not be required to decode the code.

> Prefer boring, explicit and predictable code.

---

## 44. Tests باید حفظ شوند

قبل و بعد از Refactor:

- Existing Tests را اجرا کن.
- Broken Tests را بررسی کن.
- برای بخش‌های Critical که Test ندارند Test اضافه کن.
- Regression ایجاد نکن.

بیشتر Behavior را Test کن، نه جزئیات Architecture را.

---

## 45. Refactor باید سراسری و یکپارچه روی کل پروژه انجام شود

تمام مثال‌هایی که در این سند آورده شده‌اند فقط **نمونه‌ای از مشکلات واقعی Codebase** هستند و به هیچ عنوان Scope کار را محدود نمی‌کنند.

AI نباید فقط:

- همان Classهایی که در مثال‌ها آمده‌اند؛
- همان Methodهایی که در مثال‌ها آمده‌اند؛
- همان Syntax دقیق؛
- همان Moduleها؛
- یا همان فایل‌هایی که در Prompt ذکر شده‌اند

را Refactor کند.

این مثال‌ها باید به عنوان **Pattern Detection Guide** در نظر گرفته شوند.

یعنی اگر در هر جای دیگری از پروژه Pattern مشابه، هم‌خانواده یا شکل متفاوتی از همان مشکل وجود دارد، باید شناسایی و اصلاح شود.

مثلاً اگر در Prompt نمونه‌ای از این دیده شده:

```php
array_map(...)
```

هدف صرفاً حذف همان `array_map` نیست؛ هدف حذف **functional-style سخت‌خوان** در هر شکلی از پروژه است.

اگر نمونه‌ای از این دیده شده:

```php
array $input
```

هدف صرفاً تغییر همان Method نیست؛ هدف این است که تمام Contractهای دارای Structure مشخص در:

```text
UseCase
Service
Repository
Adapter
Parser
Validator
Calculator
Controller
```

به DTO/Object Typed تبدیل شوند.

اگر نمونه‌ای از `DB::table()` دیده شده، فقط همان Query تغییر نکند؛ کل پروژه برای:

```text
DB::table
DB::select
DB::statement
DB::unprepared
Raw SQL
manual joins
```

Audit شود.

اگر نمونه‌ای از Magic String دیده شده، تمام Domain State/Type/Purposeهایی که باید Enum باشند در کل Codebase بررسی شوند.

اگر نمونه‌ای از Method طولانی دیده شده، تمام Service/UseCase/Validator/Calculator/Parserهای بزرگ و چندمسئولیتی Audit شوند.

اصل مهم:

> Examples are representative, not exhaustive.

> Fix the underlying pattern everywhere, not only the shown occurrence.

> Refactor by problem category, not by example file.

### کل Codebase باید Pattern-based Audit شود

AI باید قبل و حین Refactor، کل پروژه را برای دسته‌های مشکل زیر جستجو کند:

- `array $input`
- `array $data`
- `: array`
- `: object`
- `mixed`
- associative arrayهای دارای Structure مشخص
- nested array functions
- nested arrow functions
- long callback chains
- dynamic property access
- dynamic array keys
- Magic String
- Magic Number
- manual JSON encode/decode
- raw SQL
- `DB::table`
- `DB::select`
- `DB::statement`
- `DB::unprepared`
- joins قابل جایگزینی با relationships
- queries inside loops
- N+1
- FQCN داخل constructor/body
- relative namespace type مثل `Contracts\X`
- generic Service names
- generic Method names
- generic Variable names
- one-line methods
- multiple statements per line
- long methods
- god services
- god validators
- parser + validator + mapper ترکیبی
- calculatorهای array-driven
- HTTP exception داخل Domain/Application code
- Laravel feature reimplementation
- duplicate helpers
- inconsistent service interface usage
- manual timestamp handling
- manual serialization
- UUID primary keys برخلاف Strategy پروژه
- complicated migrations
- seed/bootstrap logic داخل migration
- trigger-heavy migrations
- rollback logic وابسته به business data
- validation arrays
- duplicate detection با string concatenation
- exception-based normal branching
- hidden schema contracts
- hidden precision rules
- implicit type casting
- inconsistent naming/vocabulary

این لیست نیز exhaustive نیست.

اگر Pattern دیگری پیدا شد که با اهداف اصلی این Refactor در تضاد است، باید اصلاح شود.

### مشابه بودن Concept مهم‌تر از مشابه بودن Syntax است

AI نباید فقط با Search/Replace ساده کار کند.

مثلاً این سه مورد ممکن است از نظر Syntax متفاوت باشند ولی یک مشکل معماری داشته باشند:

```php
$input['driver_id']
```

```php
$data['driver_id']
```

```php
$payload['driver']['id']
```

اگر هر سه Contract مشخص دارند، مشکل اصلی **anonymous structured array** است و هر سه باید با Type مناسب اصلاح شوند.

یا این موارد:

```php
$service->process(...)
$handler->handle(...)
$manager->run(...)
```

ممکن است همگی Naming مبهم داشته باشند، حتی اگر اسم‌ها دقیقاً مشابه مثال‌های Prompt نباشند.

### فقط Symptom را اصلاح نکن؛ Root Cause را اصلاح کن

اگر یک Method سخت‌خوان است، صرفاً آن را به چند private method تقسیم نکن.

بررسی کن Root Cause چیست:

- Data structure اشتباه؟
- Service responsibility بیش از حد؟
- Missing DTO؟
- Missing Enum؟
- Eloquent relationship تعریف نشده؟
- Validation concern اشتباه؟
- Laravel native feature استفاده نشده؟
- Naming ضعیف؟
- Layer boundary اشتباه؟
- Persistence detail وارد Domain شده؟

Refactor باید Root Cause را حل کند.

### Refactor باید Project-wide Consistency ایجاد کند

بعد از Refactor نباید دو یا چند Style برای یک Concern باقی بماند.

مثلاً نباید:

```text
Module A -> DTO
Module B -> array
Module C -> mixed payload
```

یا:

```text
Module A -> Service Interface
Module B -> concrete Service directly
```

یا:

```text
Module A -> Eloquent
Module B -> DB::table
```

یا:

```text
Module A -> Enum
Module B -> Magic String
```

برای یک Concept مشابه وجود داشته باشد.

اگر Convention جدید انتخاب شده، کل پروژه باید با آن هماهنگ شود.

### تمام Moduleها و Shared Code شامل Scope هستند

Audit باید حداقل شامل این بخش‌ها باشد:

```text
Modules/
Application/
Domain/
Infrastructure/
Presentation/
Http/
Controllers/
Requests/
Resources/
Services/
Contracts/
Repositories/
Models/
DTOs/
Enums/
Policies/
Middleware/
Exceptions/
Scopes/
Events/
Listeners/
Jobs/
Console/
Parsers/
Validators/
Calculators/
Factories/
Mappers/
Providers/
Database/
Migrations/
Seeders/
Tests/
Shared/Foundation/Common code
```

هیچ بخشی فقط به این دلیل که در مثال‌ها نام برده نشده از Refactor خارج نیست.

### Generated / Vendor / Framework Code دستکاری نشود

Refactor Project-wide است، اما Scope باید روی Code تحت مالکیت پروژه باشد.

موارد زیر بدون دلیل تغییر نکنند:

```text
vendor/
framework internals
generated code
third-party packages
compiled/build artifacts
```

### نتیجه نهایی باید نشان دهد مشکل در کل پروژه حل شده

در پایان Refactor فقط نگو:

```text
Fixed shown examples.
```

باید گزارش بدهی:

```text
Pattern audited:
Anonymous arrays

Occurrences found:
X

Occurrences refactored:
X

Remaining exceptions:
Y

Reason for remaining exceptions:
...
```

برای دسته‌های اصلی مشکل نیز همین رویکرد استفاده شود.

گزارش نهایی حداقل باید برای این دسته‌ها Coverage بدهد:

```text
DTO / anonymous arrays
Eloquent / DB usage
Service interfaces
UseCase structure
Naming
Enums / magic values
Long methods
Validation
Migrations
Laravel-native replacements
Query-in-loop / N+1
Serialization
Parser/import code
Domain calculators
Exception handling
Folder structure
```

اگر موردی عمداً باقی مانده، باید دلیل مشخص و فنی داشته باشد.

اصل نهایی:

> The shown code samples are symptoms discovered in a small part of the project.

> The task is to fix the architectural and readability problems across the entire codebase.

> Do not stop when the examples are fixed.

> Continue until the same class of problem has been audited project-wide.

---

## 46. قبل از تغییر هر Module آن را توضیح بده

برای هر Module ابتدا مشخص کن:

```text
Current Flow:
Controller
→ UseCase
→ Repository
→ Service
→ Model
```

سپس پیشنهاد بده:

```text
Target Flow:
Controller
→ UseCase
→ Service / Model
```

و توضیح بده چه abstractionهایی حذف می‌شوند و چرا.

---

## 47. Definition of Done

Refactor زمانی تمام است که:

- DB::tableهای غیرضروری وجود نداشته باشند.
- Raw SQL فقط موارد کاملاً justified باشد.
- Eloquent Relationships استفاده شوند.
- Eager Loading درست باشد.
- N+1 وجود نداشته باشد.
- Repositoryهای بی‌دلیل حذف شده باشند.
- تمام Serviceها Contract/Interface داشته باشند و استفاده از آن‌ها در کل پروژه یک Convention واحد و یکپارچه داشته باشد.
- UseCaseها فقط Entry Point اصلی داشته باشند.
- Business Data بی‌دلیل به Array تبدیل نشود.
- DTOها Typed باشند.
- Dataهای ساختاریافته بین UseCaseها، Serviceها و Repositoryها با DTO منتقل شوند و associative arrayهای مبهم بین Layerها باقی نمانند.
- متدهای UseCase، Service، Repository و Adapter از `array $input` یا `?array` برای Dataهای دارای Structure مشخص استفاده نکنند و Signatureهای Typed و self-documenting داشته باشند.
- Array accessهای Business/Application Layer تا حد ممکن با DTO، Object، Model، Enum و Value Object جایگزین شده باشند.
- API Serialization در Resource باشد.
- Controllerها Thin باشند.
- Naming یکپارچه باشد.
- نام Serviceها، UseCaseها، Methodها، DTOها و Variableها واضح، domain-oriented و self-explanatory باشد و نام‌های generic یا مبهم تا حد ممکن حذف شده باشند.
- برای Conceptها و Actionهای یکسان در کل پروژه Vocabulary واحد استفاده شده باشد.
- Fully Qualified Dependency داخل Class body وجود نداشته باشد.
- Folderها Responsibility مشخص داشته باشند.
- Nested callbackهای سخت‌خوان حذف شده باشند.
- Dynamic keyها، expressionهای فشرده و mappingهای مبهم تا حد ممکن به کد واضح و self-explanatory تبدیل شده باشند.
- Readability در کل Codebase نسبت به brevity و cleverness اولویت داشته باشد.
- nested `array_map` / `array_filter` / `array_reduce` و Array Gymnasticsهای سخت‌خوان حذف شده باشند.
- Dynamic field extraction و schemaهای مخفی مبتنی بر string field name تا حد ممکن با APIهای Typed و intent-based جایگزین شده باشند.
- Reference mapهای چندلایه و associative arrayهای دارای Contract مشخص با DTO/Object/Collection مناسب جایگزین شده باشند.
- قبل از manual batch loading، استفاده از Eloquent Relationships و Eager Loading بررسی و در صورت مناسب بودن استفاده شده باشد.
- Data loading، aggregation و serialization/resource shaping بی‌دلیل در یک Method یا Service ترکیب نشده باشند.
- Validation God Methodهای طولانی به Validatorهای cohesive یا Rule groupهای معنی‌دار تبدیل شده باشند.
- Validation Resultها و Errorها Typed باشند و `array` ناشناس Contract اصلی Validation نباشد.
- Magic Stringهای مربوط به validation code، calculation method، status و type در صورت داشتن Domain Meaning با Enum/Type مشخص جایگزین شده باشند.
- Exception عمومی مثل `ApiException` برای branching عادی Validation Flow استفاده نشده باشد.
- Local field validation، cross-module dependency validation و persistence conflict validation مرزهای واضح داشته باشند.
- Composite uniqueness با `implode` و delimiterهای مخفی پیاده نشده باشد و identity/constraint واضح داشته باشد.
- Date validation با fallbackهای مبهم مانند `parse(... ?? 'now')` Business Rule ناقص را مخفی نکرده باشد.
- Import/Workbook/Parserها file-format parsing، normalization، domain validation و response shaping را بی‌دلیل در یک Method ترکیب نکرده باشند.
- Parsed external data در اولین Boundary منطقی به DTO/Object Typed تبدیل شده باشد و Array Keyهای ناشناس در Flow ادامه پیدا نکنند.
- Magic Stateهای Matrix/Import با Enum مناسب جایگزین شده باشند و parsing tokenها از Domain valueها تفکیک شده باشند.
- Security constraintهای file import فقط Comment نباشند و با implementation و test enforce شده باشند.
- User-facing validation messageها طبق Convention واحد پروژه مدیریت شوند و Serviceها بی‌دلیل presentation text پراکنده نداشته باشند.
- Domain Calculatorها و Rule Engineها از `array<string,mixed>` به Contractهای Typed برای Rule، Facts، Line و Result منتقل شده باشند.
- Pricing calculation method، basis، category و rounding modeهای محدود با Enumهای Domain مشخص پیاده شده باشند.
- Calculatorها Invalid Configuration را با fallbackهایی مثل `?? 0` یا `default => 0` مخفی نکنند.
- JSON decoding و Persistence representation وارد Domain Calculator نشده باشد.
- محاسبات Money به binary float وابسته نباشند و precision policy مشخص داشته باشند.
- Fingerprint generation canonical، deterministic و از Main Calculation Flow جدا و قابل فهم باشد.
- Domain Classهایی که Validation، Compilation، Legacy Compatibility و Utility concern را مخلوط کرده‌اند به Componentهای cohesive تقسیم شده باشند.
- Legacy data shape قبل از ورود به Domain Core به یک canonical typed shape normalize شده باشد.
- Matrix/Rule Compilerها anonymous array تولید نکنند و Result Typed داشته باشند.
- Precision policy، scale، epsilon و limitهای محاسباتی به شکل explicit و مشترک تعریف شده باشند.
- repeated array scanning برای lookupهای ID/zone با Map/Collection مناسب جایگزین شده باشد.
- Service/UseCaseهای بزرگ و چندمسئولیتی به Flowهای کوتاه، cohesive و قابل دنبال کردن تبدیل شده باشند.
- Query داخل Loop و N+1های ناشی از آن حذف شده باشند مگر موارد استثنایی مستند.
- Multi-write operationها Transaction Boundary مشخص داشته باشند و `lockForUpdate()` فقط داخل Transaction استفاده شود.
- JSON serialization، timestamps استاندارد و سایر قابلیت‌های Persistence که Eloquent می‌تواند مدیریت کند داخل Serviceها دستی انجام نشده باشند.
- Return Typeهای مبهم مانند `object`, `array`, `mixed` برای Resultهای دارای Structure مشخص با Model/DTO Typed جایگزین شده باشند.
- Magic Stringهای مربوط به Status/Type/Purpose/Event با Enum یا Type مشخص جایگزین شده باشند.
- ID Typeها consistent باشند و Castهای مکرر `string`/`int` در Business Logic باقی نمانده باشد.
- Duplicate helperها حذف شده باشند.
- Primary Key Strategy یکپارچه شده باشد.
- Migrationها ساده باشند.
- Migrationها فقط Schema concern داشته باشند و Seed Data، JSON loading، Trigger، Runtime validation و Business Rule داخل آن‌ها نباشد.
- Permission، Role و Role-Permission assignment داخل Migration انجام نشده باشد و Authorization bootstrap در Seeder/Command/Service مشخص قرار گرفته باشد.
- Authorization code/status/role keyهای تکرارشونده Magic String نباشند و طبق Convention پروژه Type/Enum/Constant مشخص داشته باشند.
- Migrationهایی با `down()` خالی یا comment-only بررسی شده باشند؛ Data bootstrap masquerading as migration حذف شده باشد.
- Migrationها عمدتاً با `Schema::create` / `Schema::table` نوشته شده باشند و Raw SQL فقط استثنای مستند باشد.
- Primary Keyهای داخلی Migrationها از استاندارد `INT AUTO_INCREMENT` پیروی کنند و UUID در صورت نیاز به‌عنوان شناسه جدا استفاده شود.
- `down()`ها ساده، deterministic و بدون Query/Business Decision باشند.
- Migrationهای بزرگ چندمنظوره به Migrationهای کوچک و تک‌مسئولیتی تقسیم شده باشند.
- Triggerها و lock tableهای سفارشی فقط در صورت Requirement غیرقابل جایگزین باقی مانده باشند.
- قابلیت‌هایی که Laravel به‌صورت استاندارد پوشش می‌دهد با implementation سفارشی موازی بازنویسی نشده باشند و تا حد ممکن از Laravel-native features استفاده شده باشد.
- Stateها و Typeهای محدود و دارای Business Meaning با Enumهای Typed و consistent پیاده‌سازی شده باشند.
- Existing Behavior حفظ شده باشد.
- تمام Exampleهای این Prompt به عنوان Patternهای representative در نظر گرفته شده باشند و Audit فقط به همان فایل‌ها محدود نشده باشد.
- برای هر دسته مشکل اصلی، کل Codebase جستجو و Refactor شده باشد و صرفاً occurrenceهای نشان‌داده‌شده در Prompt اصلاح نشده باشند.
- هیچ Module یا Shared Code تحت مالکیت پروژه بدون دلیل از Pattern-based Audit خارج نمانده باشد.
- گزارش نهایی تعداد/نوع Patternهای پیدا شده، اصلاح شده و استثناهای باقی‌مانده را مشخص کند.
- Tests پاس شوند.
- Code برای یک Mid-Level Laravel Developer بدون نیاز به دانستن Architecture پیچیده قابل دنبال کردن باشد.
- Flowهای رایج پروژه برای یک Junior / Low-level Laravel Developer که Business Context عمیقی ندارد نیز قابل فهم و قابل تغییر باشند.
- برای فهم Featureهای معمول نیاز به tribal knowledge، توضیح شفاهی طولانی یا دانستن implementation detailهای پنهان وجود نداشته باشد.
- Cognitive load کد نسبت به وضعیت فعلی به شکل محسوس کاهش یافته باشد و simplicity معیار اصلی تصمیمات Refactor باشد.

---

# نحوه انجام کار

ابتدا **هیچ تغییری نده**.

مرحله اول کل Codebase را بررسی کن و گزارشی با این ساختار بده:

```text
1. Current Architecture
2. Main Complexity Problems
3. Unnecessary Abstractions
4. DB / Eloquent Problems
5. UseCase Problems
6. Service Problems
7. Repository Problems
8. DTO / Array Problems
9. Folder Structure Problems
10. Naming / Readability Problems
11. Database / Primary Key Problems
12. Proposed Target Architecture
13. Refactor Phases
14. High-risk Changes
```

سپس Refactor کامل را روی کل Codebase انجام بده.

در پایان Refactor یک گزارش نهایی با این ساختار ارائه کن:

```text
Files Changed
Files Removed
Files Added
Architecture Changes
Conventions Standardized
Behavior Changes: None / Explain
Tests
Remaining Issues
```

همچنین مشخص کن چه Conventionهایی در کل پروژه یکپارچه شده‌اند.

هر جا بین:

```text
More abstraction
```

و:

```text
Simpler readable Laravel code
```

انتخاب وجود داشت، تا زمانی که Business Requirement یا Technical Requirement خلاف آن را اجبار نکرده، گزینه ساده‌تر را انتخاب کن.

هدف نهایی این است که Developer بتواند یک Feature را از Controller تا Database به‌راحتی Follow کند و برای فهمیدن یک Flow ساده مجبور نباشد بین تعداد زیادی Layer و Abstraction حرکت کند.
