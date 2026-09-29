# ممیزی تکمیل — ۲۰۲۶/۰۹/۲۶

**اصلاح نتیجه:** نگاشت زیر شواهد کارهای انجام‌شده است و نباید تأیید کامل همهٔ بندها خوانده شود. بازبینی مستقیم بعدی، پنج دستهٔ ناتمام را با نمونهٔ کد در [remaining-work.md](remaining-work.md) ثبت کرد؛ از جمله مواردی که inventory نام پارامتر و شمارش enum شناسایی نمی‌کرد.

این فایل، شواهد بندهای [refactor.md](../refactor.md) و مواردی را که عمداً حفظ شده‌اند مشخص می‌کند. عدد درصد برای معیارهای کیفی مثل خوانایی یا «نبود هیچ N+1 در همهٔ ورودی‌ها» ساخته نشده است. مرجع نتایج اجرایی [tests.json](tests.json) است.

## نگاشت بندهای سند

| بند | موضوع | پیاده‌سازی / شواهد |
|---|---|---|
| 1 | حفظ رفتار | دو suite کامل؛ regression fingerprint، null/omitted، tenant، quote، نسخه، ledger و rollback. |
| 2، 22–27، 31، 38، 43 | خوانایی، نام و type | DTOهای explicit، متدهای مرحله‌ای، guardها و وابستگی‌های نام‌دار؛ سه متد بلند بررسی‌شده پایین توضیح داده شده‌اند. |
| 3–6، 30 | دیتابیس و مدل | صفر DB::table و toBase در runtime؛ صفر recursive SQL؛ model، relationship و casts بومی. SQL و join استثنایی پایین ثبت‌اند. |
| 7–9، 19 | داده و serialization | Pricing/Consignment/Catalog/Manifest دادهٔ کاربردی نوع‌دار دارند؛ Resource یا serializer در HTTP/cache/provider/persistence. list/map و frozen JSON استثنا هستند. |
| 10–14، 16–18 | ورودی، قرارداد و ساختار | تمام Handlerها فقط handle؛ ۱۳۱ سرویس Application/Domain دارای قرارداد اختصاصی؛ binding و import مشخص؛ FormRequest و controller باریک. |
| 15، 32–37 | سادگی و Laravel-native | wrapperها و facadeهای عبوری حذف؛ shared mapper/serializer؛ authorization و exception طبق قرارداد مشترک؛ transaction/paginator/ORM بومی. |
| 20–21 | schema و identity | ۸۹ PK عددی و migrationهای schema/constraint/invariant مجزا؛ seed مستقل. UUID خارجی و invariantهای مستقیم SQL آگاهانه حفظ شده‌اند. |
| 28–29 | vocabulary دامنه | ۷۱ enum؛ از جمله method/basis/rounding قیمت، زمان‌بندی، eligibility، manifest eligibility/transition، scope و lifecycleهای موجود. کلیدهای catalog قابل‌تعریف یا سند مرزی به string تبدیل می‌شوند. |
| 39 | validation | نتیجه/خطای validation کاتالوگ، matrix، هندسه و قواعد نوع‌دار؛ مرز field/reference/conflict تفکیک شده؛ خطاهای provider/cache در adapter. |
| 40–42 | import و محاسبات | parser/mapper/validator/compiler جدا، rule/facts/result نوع‌دار، محاسبهٔ decimal، محدودیت workbook و رفتار زمان/precision با تست. |
| 44–47 | تست و ممیزی سراسری | ۱۶ ماژول و app؛ inventory متنی و AST، بررسی نوع و named arguments، هر دو suite کامل، گزارش تغییرها و استثناها. |

بند ۴۶ و گزارش معماری اولیه در [audit.md](audit.md) سابقه دارد؛ جریان و هدف هر ماژول پیش از refactor آنجا آمده است. جدول بالا «صفرکردن همهٔ الگوهای متنی» را نتیجه نمی‌گیرد؛ استثناها جزء نتیجهٔ بررسی هستند.

## دامنه و روش شمارش

- ۱۸۵۹ فایل PHP متعلق به پروژه؛ ۱۵۰۲ فایل runtime در `Modules/*/src` و `app`؛ vendor و cache تولیدشده خارج از scope.
- [coverage-counts.json](coverage-counts.json): baseline اولیه، ابتدای ادامهٔ ۲۶ سپتامبر و نتیجهٔ جاری با regex یکسان.
- [current-patterns.json](current-patterns.json): تعداد هر pattern در هر فایل؛ شامل tests/migrations نیز هست و runtime جدا محاسبه می‌شود.
- [module-coverage.json](module-coverage.json): تمام ماژول‌ها و app؛ [service-contracts.json](service-contracts.json): نگاشت قراردادهای واقعی.
- [method-audit.json](method-audit.json): AST متدهای بلند، پارامترهای array با نام‌های پرخطر، entry pointها و returnهای عمومی. این جست‌وجوی کمکی همهٔ semantics را از روی نام متغیر تشخیص نمی‌دهد.
- [type-audit.json](type-audit.json): ۱۱۴۰ نوع پروژه، صفر نوع ناموجود، صفر named-argument mismatch.

## استثناهای SQL و relationship

**فراخوانی مستقیم DB façade: ۷ occurrence.** چهار مورد در `Geography/Infrastructure/Services/MySqlSpatialTopology` برای `ST_IsValid`/`ST_Intersects` و batch نقطه/چندضلعی است. Eloquent رابطه‌ای جایگزین topology حفره، مرز و MultiPolygon نمی‌شود. همهٔ geometryها و مختصات bound parameter هستند؛ aliasهای batch فقط از عدد داخلی ساخته می‌شوند.

سه مورد دیگر در فرمان محلی `ManageLocalConsignmentFixtures` برای حذف موقت و بازگرداندن trigger حذفِ تاریخچه در cleanup محلی است. محیط و شناسهٔ fixture محدود است و تست `LocalUserCommandTest` بازگشت guardها را بررسی می‌کند. این SQL مسیر درخواست عادی نیست.

**یک join:** `EligibleTariffQuery` برای انتخاب قطعی خانوادهٔ تعرفه بر اساس priority، scope، code و جدیدترین نسخه. فقط ستون‌های نسخه hydrate می‌شوند و `family` eager است. ترتیب باید پیش از `first()` در SQL اعمال شود؛ eager-loading صرف معادل این انتخاب نیست.

**24 occurrence از expression خام Eloquent** جدا از DB façade شمرده شده‌اند: COUNT/SUM/MAX، ترتیب null/legacy code، SLA deadline و JSON expression، اولویت تعرفه، جست‌وجوی lowercase، prefer-version و `COALESCE` اتمی زمان route. فهرست فایل/خط در [pattern-classification.json](pattern-classification.json) است. شرط‌های ورودی bound هستند؛ این‌ها query builder مستقل یا recursive SQL نیستند.

**referenceهای مانیفست:** actor/node/driver/vehicle/route از port مالک و مجموعهٔ IDهای صریح خوانده می‌شوند. سه نقش node در یک query مشترک جمع می‌شوند تا سه eager query جدا لازم نشود. روابط داخلی route و initiator eager می‌شوند؛ aggregation و Resource از خواندن داده جداست.

## استثناهای array و mixed

- ۵۴۵ declaration متنی array شامل ۱۰۳ مورد در Contract، ۱۹۲ property/list/map در DTO/ValueObject، ۶۴ مورد در Resource/serializer/mapper، ۱۰ مورد Presentation و ۱۷۶ مورد سایر runtime است.
- ۶۳۵ return متنی array شامل ۸۱ Contract، ۱۹۱ Resource/serializer/mapper، ۱۳۱ Presentation، ۱۰ DTO/ValueObject و ۲۲۲ سایر runtime است.
- فهرست DTO/مدل، lookup بر اساس ID، مجموعهٔ permission، label چندزبانه، validation Laravel، ستون‌های `insert/upsert` و اسناد HTTP/provider/audit/outbox/cache نیاز به DTO پوششیِ بی‌رفتار ندارند.
- frozen policy، commitment، geometry و audit payload در مرز ذخیره/انتقال سند JSON هستند؛ قیمت و ساعت تعهد در Domain با ساختار persistence محاسبه نمی‌شوند.
- context انتخاب سرویس فیلدهای شناخته‌شدهٔ typed دارد. `facts` فقط کلیدهای اضافهٔ قواعد تنظیم‌شده را نگه می‌دارد. تبدیل صریح برای evaluator مسیرهای dotted/wildcard یک مرز dictionary است؛ ترتیب کلیدها و `{first}`/`{last}` regression دارند.
- از پارامترهای `array` با نام‌های input/data/payload/context/draft/rule/party/selection/reference/record/version/result/condition در Service/UseCase/Adapter فقط سه مورد باقی است: `OutboxEventSchemaRegistry::assertValid` (قرارداد JSON رویدادهای مختلف)، `PublicationRecorder::destroyDeliverySecret` (حذف secret از payload رویداد ذخیره‌شده)، و `LaravelWorkerRuntime::shareContext` (context آزاد logging).
- پنج return متنی mixed: مرتب‌سازی recursive برای fingerprint، evaluator fact، lookup fact در دو VO و callback عمومی `ApiResponder` که نوع Resource ورودی را پشتیبانی می‌کند. نتیجهٔ دارای schema ثابتِ کسب‌وکار با `object` عمومی برنمی‌گردد.

## حلقه، batch و تراکنش

- درج/ذخیرهٔ ردیف مانیفست و projection چند consignment و pickup/delivery task، batchهای ۱۰۰تایی هستند. query تعداد ۱/۴۰ ثابت است و ۱۰۱ فقط به‌خاطر اندازهٔ batch افزایش می‌یابد.
- route یک consignment و دریافت یک leg در همان درخواست cache می‌شود؛ task/driver مشترک به‌ازای هر parcel دوباره نوشته نمی‌شود. شمارندهٔ version مطابق رفتار قبلی با تعداد تغییرهای منطقی حفظ می‌شود.
- **استثنای workflow مستقل:** ensure/activate delivery و ایجاد/پیشروی route برای هر والد مستقل، قفل و تاریخچهٔ خودش را دارد. ادغام این commandها در یک upsert عمومی، validation، ترتيب event و rollback را تغییر می‌دهد. scope، ترتیب قفل و transaction حفظ شده‌اند؛ این مسیر «query ثابت برای تعداد نامحدود والد» معرفی نشده است.
- **استثنای تعارض:** fallback تک‌ردیفی فقط برای race روی unique active-slot و retry محدود تولید code است. خطاهای دیگر دیتابیس دوباره پرتاب می‌شوند.
- **استثنای topology هنگام ویرایش پیکربندی:** `PricingZoneGuard` هر geometry را اعتبارسنجی و تقاطع چندضلعی‌های ناحیه‌های مختلف را با MySQL بررسی می‌کند تا ترتیب اولین خطا و semantics دقیق حفظ شود. این هزینهٔ pairwise تغییر پیکربندی باقی است؛ containment در مسیر قیمت‌گذاری به batch منتقل شده است.

## متدهای بلند بازبینی‌شده

فقط سه متد Service/UseCase/Adapter/Repository در threshold کمکی بیش از ۸۰ خط مانده‌اند:

- `OutboxEventSchemaRegistry::schemas`، ۹۱ خط: فهرست declarative schema رویدادها؛ flow یا query ندارد.
- `SaveCatalogRecordHandler::handle`، ۸۲ خط: یک تراکنش idempotent شامل version lock، clone/create، validation و publish. scope و dependency normalization جدا شده‌اند؛ شرط بازگشت idempotent و مرز lock صریح حفظ شده‌اند.
- `PlanConsignmentRouteHandler::handle`، ۸۱ خط: تراکنش یک route با شواهد immutable. درج legها جداست؛ بخش عمدهٔ خطوط mapping صریح ستون‌های plan/evidence است.

تقسیم مصنوعی تنها برای رسیدن به عدد خط کمتر انجام نشده است. ساخت/ویرایش consignment، زمان‌بندی تعهد و پذیرش quote که چند مسئولیت داشتند تقسیم شده‌اند.

## identity و تضمین دیتابیس

- ۸۹ جدول با `id INT AUTO_INCREMENT`؛ ۸۸ جدول برنامه به‌علاوهٔ migrations.
- ۲۷۲ FK با type منطبق با ستون مرجع فعلی؛ سه FK به id داخلی عددی و بیشتر باقی به UUID عمومی unique ارجاع می‌دهند. این با کامل‌شدن الگوی FK عددیِ توضیح‌داده‌شده در بند ۲۰ یکسان نیست؛ اصلاح برداشت قبلی در remaining-work آمده است.
- ۹۰ trigger برای append-only، تغییرناپذیری نسخهٔ منتشرشده، tenant/status و code قدیمی حفظ شده‌اند. observer به‌تنهایی direct SQL و bulk write را پوشش نمی‌دهد. استثنای code Area به‌روشنی ثبت شده است؛ [database-invariants.md](database-invariants.md).
- lock tableهای کوچک برای serialization کاتالوگ global/tenant و تخصیص شماره حفظ شده‌اند؛ زمانی که هنوز ردیف دامنه‌ای برای lock وجود ندارد، قفل والد پایدار لازم است. ردیف lock به‌شکل idempotent در runtime ساخته می‌شود.
- migrationها برای fresh install هستند؛ اجرای upgrade دیتابیس موجود یا deployment بخشی از خروجی انجام‌شده نیست.

## نتیجهٔ اعتبارسنجی

۳۲۶ تست اصلی و ۱۲۱ تست یکپارچه، در مجموع ۱۳۱۱۵ assertion؛ صفر شکست، خطا و skip. fixtureهای مرتبط با مرزهای DTO/Resource به‌روز شدند و regressionهای رفتار و شمارش query اضافه شدند. محیط موقت از `.env` و دیتابیس کاری مستقل بود. هیچ مورد شکست‌خوردهٔ شناخته‌شده در پایان اجرا باز نمانده است.
