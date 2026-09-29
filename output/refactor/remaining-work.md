# وضعیت ریفکتور

## بندهای `refactor.md`

### ۱. جداکردن validation دامنه و برنامه از HTTP — انجام شد

`ApiException` از policy و guardها حذف شد و mapping به HTTP در boundary انجام می‌شود:

| کلاس | exception دامنه |
| --- | --- |
| `ConsignmentPolicy` | `ConsignmentRuleViolation` |
| `PasswordPolicy` | `WeakPassword` |
| `PricingZoneGuard` | `InvalidPricingZone` |
| `OfferingCommitmentResolver` | `OfferingCommitmentUnavailable` |
| `ScheduleWindows` | `CommitmentWindowUnavailable` |

renderer هر پنج مورد در [bootstrap/app.php](../../bootstrap/app.php) ثبت شد؛ status، پیام، `field_errors` و `reason_code` پاسخ‌ها دست‌نخورده ماند.

### ۲. حذف exception از branching عادی — انجام شد

- `ScheduleWindows::inspectWindow()` نتیجهٔ typed `CommitmentWindowInstance|CommitmentWindowUnavailability` برمی‌گرداند.
- `OfferingCommitmentResolver::inspect()` یک `OfferingCommitmentResolution` می‌دهد؛ `ResolveServiceOfferingsHandler` تنها `available()` را بررسی می‌کند و دیگر `reason_code` را از دل exception نمی‌خواند.
- `resolve()` برای جریان انتخاب سرویس همچنان exception می‌دهد؛ این همان failure استثنایی است.

### ۳. vocabulary ثابت عملیات — انجام شد

enumهای `RoutePlanStatus`، `RoutePlanLegStatus`، `RouteDefinitionLegStatus`، `MovementCommand`، `MovementEntityType`، `CustodyType`، `ManifestParcelStatus`، `OfferingChild` اضافه شدند و `CoverageTarget`/`RoutePurpose`/`ManifestTransition` موجود به‌کار گرفته شدند. امضای `ResolveCoveragePolicyCommand`، `ResolveRouteDefinitionCommand` و `MovementRecorderInterface::record()` نیز typed شد. serialization همان رشتهٔ قبلی را می‌نویسد.

### ۴. نوع‌دهی ماتریس تعرفه — انجام شد

`TariffDraftData::$freightMatrices` از `list<array<string, mixed>>` به `list<FreightMatrixDraft>` تغییر کرد: normalization در `FreightMatrixDraftInput`، serializer تاریخی در `FreightMatrixDraftDocument`، تبدیل به دامنه در `FreightMatrixInput::fromDrafts()`.

تفاوت `linear_tail` و `linear_bands` با `null` بودن property حفظ شد، چون خوانندهٔ metadata ذخیره‌شده این دو نمایش را از روی *وجود کلید* تشخیص می‌دهد.

### ۵. شناسه و رابطهٔ عددی — انجام شد

هر ۸۸ جدول برنامه `increments('id')` دارد و هر ۲۷۲ کلید خارجی از ستون‌های `unsignedInteger` استفاده می‌کند. FKهای ترکیبی tenant نیز با کلیدهای عددی و unique indexهای متناظر حفظ شده‌اند. رابطه‌های مدل، joinها و triggerهای وابسته به کلیدها هماهنگ شده‌اند.

UUID عمومی هر رکورد به‌صورت یک ستون جدا و unique باقی است. برای نمونه، `manifest_parcels.manifest_parcel_id` شناسهٔ عمومی خود ردیف است؛ `manifest_id` و `parcel_id` کلید خارجی عددی‌اند. قراردادهای HTTP، JWT، outbox و audit همچنان UUID می‌گیرند و برمی‌گردانند. تبدیل در لایهٔ persistence با lookup گروهی انجام می‌شود؛ عددی‌شدن FK الزاماً به تغییر قرارداد عمومی نیاز ندارد.

تست MySQL همهٔ FKها و رابطه‌های تعریف‌شده را بررسی می‌کند و ذخیرهٔ عددی، round-trip شناسهٔ عمومی، جداسازی tenant، رابطه‌های چندنوعی، rollback، cascade و تعداد queryهای bulk را پوشش می‌دهد. جزئیات در [schema.md](schema.md) و `tests/Integration/NumericForeignKeysTest.php` است.

این مایگریشن‌ها برای نصب تازه‌اند؛ تبدیل درجا برای داده‌های UUID موجود در دیتابیس مستقر انجام نشده است.

## قاعدهٔ Repository — کامل شد

قاعده: هیچ کلاسی بیرون از لایهٔ persistence نباید Eloquent یا Query Builder بنویسد.

در شروع، ۲۲۸ فایل لایهٔ Application/Presentation کوئری مستقیم داشتند و لایهٔ Repository تنها ۵ کلاس داشت. الان **۵۳ contract** در `Application/Repositories` و **۵۸ پیاده‌سازی** در `Infrastructure/Repositories` وجود دارد و هر ۵۳ مورد در provider ماژول خودش bind شده است.

| ماژول | contract | Eloquent |
| --- | --- | --- |
| Operations | ۸ | ۱۲ |
| Consignment | ۷ | ۸ |
| Pricing | ۷ | ۷ |
| Authorization | ۶ | ۶ |
| Manifest | ۵ | ۵ |
| ServiceCatalog | ۵ | ۵ |
| Identity | ۴ | ۴ |
| Organization | ۴ | ۴ |
| Geography | ۲ | ۲ |
| Dashboard / Foundation / Notification / Outbox / User | ۱ هرکدام | ۱ هرکدام |

### تغییرهای ساختاری همراه این کار

- کلاس‌های `Application/Queries` که Builder به بیرون می‌دادند حذف شدند: `ConsignmentQuery`، `ManifestParcelQuery`، `DashboardQuery`، `CatalogQuery`، `CommitmentScheduleQuery`، `PricingQuery`، `EligibleTariffQuery`. منطق هرکدام داخل repository ماند و متدها نتیجه برمی‌گردانند، نه Builder.
- `UserIdentifierQuery` به `Application/Services/UserIdentifierResolver` با contract خودش منتقل شد؛ هیچ `Application/Queries` باقی نمانده است.
- نشت cross-module بسته شد: `NodeRepository` جای خواندن مستقیم `NodeRecord` در هشت ماژول را گرفت؛ همین‌طور `UserRepository`، `AreaRepository`، `TenantRepository`، `CityRepository`/`ProvinceRepository` و `CatalogRepository`.
- `CatalogIdentityRecord`/`CatalogVersionRecord` و `PricingIdentityRecord`/`PricingVersionRecord` اضافه شدند تا contractهای پنج‌گانهٔ کاتالوگ و قیمت‌گذاری به `Eloquent\Model` وابسته نباشند — تست معماری پروژه دقیقاً همین را ممنوع می‌کند.
- دو دستور fixture محلی (`chabok:local-user` و `chabok:local-consignment-fixtures`) روی `LocalUserFixtureStore` و `LocalConsignmentFixtureStore` در `app/Infrastructure/Fixtures` نشستند؛ نقش این دو همان seeder است.

### مواردی که خودشان مرز persistence هستند

`OperationalProfileAdapter`، `AuthorizationUserAssignmentReader`، `AuthorizationUserScopeAuthorizer`، `ConsignmentPricingTarget`، `MySqlSpatialTopology`، `DatabaseAccessSessionValidator`، `EloquentScopeTopology`، `UserCommandActorLock`، `MySqlAuditWriter`، `MySqlOutboxWriter`، و seeder و migrationها. این‌ها پیاده‌سازی Infrastructure یک port هستند و خودشان نقش repository را دارند.

## اعتبارسنجی — به‌روزرسانی ۲۰۲۶/۰۹/۲۷

تست‌ها با PHP 8.3، MySQL 8.4 و Redis در کانتینرهای موقت اجرا شدند. مقایسه با snapshot قبل از تغییر روابط نشان داد در Unit/Feature تعداد خطاها از ۲۸ به ۲۲ رسیده و خطای جدیدی اضافه نشده است؛ ۲۹۴ تست و ۹۸۳۳ assertion اجرا شد. یک تست risky قدیمی باقی است. در suite ماژول‌ها نیز همان یک error و یک failure قبلی در ۳۲ تست باقی است. این خطاها شامل تست‌های هماهنگ‌نشده با constructorهای ریفکتورشده و محدودیت‌های محیط تست‌اند؛ همه را خطای محیطی تلقی نمی‌کنیم.

suite کامل یکپارچه با ۱۳۲ تست و ۴۲۰۵ assertion پاس شد؛ تست‌های نهایی cache و تعداد query نیز پاس شدند. نصب تازه، seed کامل دوباره و rollback کامل موفق بودند. جزئیات در [schema.md](schema.md) ثبت شده است. دیتابیس واقعی برنامه در این بررسی تغییر نکرده است.
