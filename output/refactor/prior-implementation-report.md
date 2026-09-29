> Historical checkpoint. Superseded by report.md; counts and remaining-work descriptions below are not current.

# Refactor implementation report — 2026-09-23

**وضعیت: بخشی از ریفکتور سراسری انجام شده؛ Definition of Done کاملِ `output/refactor.md` هنوز محقق نشده است.**

این گزارش تغییرات انجام‌شده را از کارهای باقی‌مانده جدا می‌کند. کاهش یا افزایش شمارش متنی، به‌تنهایی نشانهٔ اصلاح معماری نیست. افزودن Contractها تعداد signatureهای آرایه‌ای را نیز افزایش می‌دهد؛ این موارد اصلاح‌شده محسوب نشده‌اند.

آخرین ادامهٔ کار و نتایج جدید، شامل ناوگان، مسیر، پوشش، مأموریت‌ها و شماره‌گذاری، در [continuation-2026-09-23.md](continuation-2026-09-23.md) ثبت شده‌اند. آمار checkpointهای قبلی در این گزارش تاریخی‌اند؛ تکمیل کل کار همچنان باز است.

## Files Changed

- 1055 فایل tracked تغییر کرده است. بخش قابل‌توجهی شامل import، نام dependency، قالب‌بندی و حذف wrapper است؛ همهٔ این فایل‌ها بازطراحی رفتاری نشده‌اند.
- فهرست کامل: [files-changed.json](files-changed.json).
- `composer.json` اکنون وابستگی مستقیم به `brick/math ^0.18` دارد؛ نسخهٔ بسته‌ها تغییر نکرده است. تنها metadata مربوط به lockfile به‌روز شد.

## Files Removed

- 154 مسیر tracked حذف شده است؛ شامل جابه‌جایی سرویس‌ها، بازنویسی migrationها و حذف واسطه‌های زائد.
- `DeterministicCalculator` با `PricingRuleCalculator` جایگزین شد.
- `FreightMatrices` حذف و مسئولیت‌های آن میان validator، compiler، rank policy و mapper مرزی تفکیک شد؛ band/cell مشترک Domain جای نوع‌های موازی parser را گرفت.
- wrapper آرایه‌ای `PrepareMatrixWorkbookResult` حذف شد؛ Handler نتیجهٔ typed واقعی برمی‌گرداند.
- مسیرهای جابه‌جایی: [service-moves.json](service-moves.json).

## Files Added

- 567 فایل source/test/migration جدید؛ فهرست دقیق در inventory.
- در مرحلهٔ نخست ۱۶۰ Contract سرویس اضافه شد؛ پس از حذف façadeها، نگاشت ۱۴۲ Contract موجود و ۱۸ مورد حذف‌شده جدا ثبت شده است. Contractهای واقعی calculator، matrix validator، matrix compiler و zone rank policy نیز در Domain باقی‌اند.
- Typeهای pricing، enumهای روش/مبنا/دسته/گردکردن/شرط، نتیجه و ردیف محاسبه.
- Typeهای workbook، Resourceهای پاسخ و exception مستقل فایل.
- `AreaEdge` و `AreaHierarchy` و تست‌های جدید رفتار، tenant isolation، تعداد query، precision و امنیت workbook.

## Architecture Changes

1. سرویس‌های Application در `Services` قرار گرفتند و از `Contracts/*Interface` تزریق می‌شوند. Bindingها صریح و در Provider همان ماژول‌اند. مرزهای خارجی موجود حفظ شدند.
2. ۱۸۵ private `execute` واسط حذف شد. `handle` ورودی عمومی UseCase باقی ماند؛ ترتیب validation، تراکنش، audit و outbox جابه‌جا نشد.
3. نام ۷۳۹ dependency از نوعشان گرفته شد. Dependencyهای fully-qualified به import منتقل شدند. مجموعاً ۲۰ wrapper تکراریِ سادهٔ request-context در Controllerها حذف شد؛ helperهای دارای validation حفظ شدند.
4. سه recursive query به graph typed و پیمایش محدود تبدیل شد. query در هر گام traversal وجود ندارد؛ دسترسی به graph محدود به tenant است. در resolve چند scope نیز graph یک بار بارگذاری می‌شود.
5. `EloquentNetworkRepository` دیگر `DB::table`، `toBase` یا join دستی ندارد. مدل‌های Area/Node/City برمی‌گرداند؛ parent edge با eager loading و child check با relationship خوانده می‌شود. JSON مربوط به Node با cast مدیریت می‌شود.
6. نمایش assignmentهای کاربر با دو relationship و سه query ثابت انجام می‌شود. خروجی reader، `UserAssignmentData` است و نام فیلدهای عمومی توسط `UserAssignmentResource` حفظ شده‌اند.
7. موتور محاسبه روی `PricingRule`، `CalculationFacts` و enumها کار می‌کند و `CalculationResult` برمی‌گرداند. JSON دادهٔ قدیمی در mapper خوانده می‌شود. محاسبات مبلغ و جمع مبالغ با decimal دقیق انجام می‌شوند. fingerprint الگوریتم و ترتیب قبلی را حفظ می‌کند.
8. Preview workbook ورودی، band، cell و نتیجهٔ typed دارد. پاسخ HTTP در Resource ساخته می‌شود. `InvalidMatrixWorkbook` مستقل از HTTP است و در bootstrap به همان پاسخ 422 قبلی نگاشت می‌شود.
9. کلید داخلی عددی از شناسهٔ عمومی جدا شد؛ مسیرهای clone و serialization تطبیق یافتند. ۲۶ backfill حذف شد؛ bootstrap وضعیت به seeder منتقل و دو migration مجوز حذف شدند. نصب، seed تکراری و rollback کامل روی MySQL موقت بررسی شدند.
10. ماتریس تعرفه، band، cell، خروجی compile و خطاهای validation نوع‌دار شدند؛ تبدیل draft/JSON و legacy tail در mapper مرزی انجام می‌شود. workbook مستقیماً همین نوع‌ها را اعتبارسنجی می‌کند. مبلغ ریالی با decimal بررسی و carry می‌شود؛ دقت quantity میان validator/compiler مشترک است. lookupهای catalog در matcher برای هر reference فقط یک بار اجرا می‌شوند. جزئیات: [matrix-pipeline.md](matrix-pipeline.md).
11. تست معماری، استقلال Domain را حفظ می‌کند و استفادهٔ Application از مدل Eloquent را مجاز می‌داند. وابستگی خواندن مدل Geography برای Organization و خواندن scopeهای Organization برای Authorization صریح شد.

12. ۵۵ migration تاریخی به ۲۱۳ migration ایستا با مسئولیت جدا برای جدول، FK و invariant تبدیل شد. schema واقعی ۸۸ جدول برنامه، ۲۷۲ FK، ۲۶ CHECK و ۹۰ trigger حفظ شد؛ rollbackهای وابسته به business data حذف شدند. هم‌ارزی ستون‌ها، defaultها، collationها، indexها و تضمین‌های MySQL با schema پیش از این مرحله بررسی شد. استثناها: [database-invariants.md](database-invariants.md).
13. `TransactionManager` و wrapperهای اجرای یک‌باره حذف شدند؛ `ConnectionInterface::transaction()` بومی لاراول استفاده می‌شود. مسیرهای حساس، `attempts: 1` و سایر مسیرها سیاست سه تلاش قبلی را حفظ کرده‌اند. `Page` سفارشی حذف و `LengthAwarePaginator` بومی در کل پروژه جایگزین شد.
14. Geography از Model، رابطهٔ province، eager loading، فیلتر typed و Resource استفاده می‌کند. Authorization از روابط role/permission/assignment استفاده می‌کند؛ bootstrap مجوز و نقش به upsert گروهی تبدیل شد و شناسه‌های عمومی هنگام seed مجدد حفظ می‌شوند.
15. `AuthorizationContext`، `PermissionScope`، `ModuleEntitlement` و tenant summary نوع‌دار شدند. مصرف‌کننده‌های Authorization، Organization، Consignment، Manifest، Operations، Dashboard، Pricing و ServiceCatalog از همین قرارداد استفاده می‌کنند. JSON در Resource ساخته می‌شود و cache از Repository بومی لاراول استفاده می‌کند.
16. بررسی پوشش scope از snapshot محدود به tenant استفاده می‌کند؛ حلقهٔ نامزدهای نقش/کاربر دیگر برای هر مورد query نمی‌زند. واگذاری و لغو چند permission نیز context و topology را یک بار در همان بررسی بارگذاری می‌کنند. snapshot بین عملیات cache نمی‌شود.
17. Outbox از پیام/رسید/result نوع‌دار و state enum استفاده می‌کند. repository و facadeهای عبوری آن حذف شدند. Notification از context تحویل و receipt نوع‌دار، eager loading کاربر challenge، cast و timestamp مدل استفاده می‌کند. قفل worker، idempotent receipt و حذف secret پس از تحویل حفظ شدند.
18. Audit JSON با cast مدل ذخیره می‌شود. خواندن User، Session، OTP، invitation و credential از مدل است؛ repositoryهای صرفاً عبوری Identity و facade عمومی حذف شدند. مرز واقعی User–Identity در adapter چهارعملیاتی باقی است. پاسخ‌های User، session summary، login/refresh، OTP و readiness Resource دارند.
19. OTP purpose/status، invitation status، publication state، delivery channel، health state، entitlement state و scope type enum دارند. تلاش ناموفق و انقضای OTP با دیتابیس واقعی در تست feature بررسی می‌شوند و قبل از rejection commit می‌شوند. ابطال گروهی session یک write دیتابیس و pipeline Redis دارد.
20. ویرایش پروفایل از `ProfileChanges` استفاده می‌کند و پس از ذخیره query مجدد ندارد. façade بدون مصرف‌کنندهٔ User حذف شد. Idempotency از مدل و enum استفاده می‌کند و قفل actor در adapter مالک User قرار دارد.

21. Dashboard از مدل‌های Eloquent، شمارش‌های نوع‌دار، DTO فعالیت و Resource استفاده می‌کند. façade عبوری حذف شد؛ snapshot محدوده یک بار برای کارت‌ها و shortcutها بارگذاری می‌شود. شمارش‌ها در SQL باقی‌اند تا حافظه با تعداد بارنامه‌ها رشد نکند.
22. وابستگی تعرفهٔ خدمات از رابطهٔ خانواده/نسخه/هزینه/گروه زون و eager loading استفاده می‌کند. repository عبوری و query به‌ازای هر قاعده حذف شدند؛ نتیجهٔ بررسی غیرپرتاب‌کننده، failure enum و خروجی نوع‌دار دارد. نسخهٔ معتبرِ آخر برای هر خانواده جدا انتخاب می‌شود.
23. اعتبارسنجی تعرفه مستقیماً مدل و رابطه‌های لازم را می‌خواند. قواعد، selector، بازه و خطاهای validation نوع‌دارند. validator مستقل Domain، مقایسهٔ decimal و fieldهای نام‌دار را جای float/implode گرفته است. بررسی کاتالوگ گروهی است؛ نتیجهٔ مشترک تعرفه/زون در Resource serialize می‌شود و exception انتشار، نتیجهٔ نوع‌دار حمل می‌کند.
24. بررسی تداخل نسخه و تعرفهٔ پیش‌فرض با Eloquent انجام می‌شود؛ رابطهٔ قواعد/سرویس جای join دستی را گرفته است. مرزهای اعتبار در query دقت میکروثانیه دارند. بررسی انتشار گروه زون در validation دیگر از catch کردن ApiException برای کنترل جریان استفاده نمی‌کند.


25. ورودی زون و اعضا به draftهای نوع‌دار و enum تبدیل شد. writer مدل‌های Eloquent می‌سازد و geometry را با cast ذخیره می‌کند؛ شهرها و استان‌های همهٔ اعضا با دو query گروهی بررسی می‌شوند. validator مستقل عضویت، مقایسهٔ شناسه و بازهٔ پستی را بدون delimiter و float انجام می‌دهد.
26. reader نسخهٔ تعرفه و زون مدل‌های native با eager loading برمی‌گرداند؛ تاریخچه و فهرست انتخاب نسخه نیز query به‌ازای هر ردیف ندارند. Resource پاسخ عمومی را می‌سازد و serializer مشخصِ سند نسخه، شکل digest انتشار را حفظ می‌کند. شش wrapper نتیجه و façade سراسری Pricing حذف شدند.
27. parsing و serialization GeoJSON در مرز Application قرار دارد. محاسبات هندسی از Geometry/Polygon/GeoPoint استفاده می‌کنند و JSON یا ApiException در هستهٔ محاسباتی ندارند. نتیجهٔ بررسی هندسه نوع‌دار است؛ اعتبارسنجی زون دیگر برای کنترل جریان ApiException را catch نمی‌کند. MySQL همچنان topology حفره‌ها و چندضلعی‌ها را بررسی می‌کند.
28. حل پوشش از مدل، روابط version/policy/node و criterion/location نوع‌دار استفاده می‌کند. اولویت و specificity مستقیم مقایسه می‌شوند؛ برنده و تساوی در یک پیمایش مشخص می‌شوند. شواهد فقط در مرز ذخیره‌سازی serialize می‌شوند. مصرف‌کننده‌های Manifest، route planning و last-mile مستقیم نتیجهٔ نوع‌دار را می‌خوانند؛ façade Coverage حذف شد. وابستگی خواندن Operations به Organization اکنون صریح و در تست معماری مجاز است.
29. نه façade دیگر که صرفاً Handlerها را wrap می‌کردند حذف شدند: Manifest، NetworkAdministration، Movement، FleetAdministration، DeliveryTask، OperationalDirectory، PickupTask، ConsignmentNumberRange و Consignment. مصرف‌کننده‌های واقعی در adapter پروفایل و Manifest به Handlerهای موردنیاز متصل‌اند؛ shorthandهای تست فقط در Tests/Support باقی‌اند. فهرست در [retired-facades.json](retired-facades.json) است. خواندن Driver در adapter پروفایل نیز Eloquent شد.
30. compiler ماتریس، انتشار و اتصال گزینه‌ها را یک‌جا از CatalogSelectionInspector می‌خواند. visibility با publication تفکیک شده تا رفتار دو مصرف‌کنندهٔ قبلی حفظ شود؛ خطاهای compiler نوع‌دارند و repository دو متدیِ عبوریِ هزینه حذف شد. مقایسهٔ context ماتریس به فیلدهای صریح تبدیل شد.

31. CurrentCatalog نسخه‌های گزینه‌ها را از رابطهٔ native خانواده/نسخه به‌صورت گروهی می‌خواند و OptionRevisionSet برمی‌گرداند. quote و matrix matcher نسخه‌های مرتبط را در حلقه query نمی‌کنند؛ انتخاب نسخهٔ جاری و شواهد گزینه از همین snapshot خوانده می‌شود. ورودی‌های یک و صد خانواده در تست واقعی دو query دارند. InvalidTariffMatrix مستقل، خطای نوع‌دار را با همان details.errors قبلی به HTTP تبدیل می‌کند.

32. هشت Handler مدیریت Area/Node مستقیماً مدل یا paginator برمی‌گردانند؛ هشت Result آرایه‌ای و NetworkProjection حذف شدند. NetworkResource پاسخ عمومی را می‌سازد و NetworkDocument فقط در مرز پاسخ/audit استفاده می‌شود. حسابرسی مدل‌های قبل/بعد را دریافت می‌کند؛ parent UUID قدیمی حتی پس از تغییر رابطه حفظ می‌شود.
33. دو FK والد/فرزند در area_hierarchies به unsigned INT و areas.id تبدیل شدند. کلید ترکیبی tenant پابرجاست؛ درج مرزی UUIDها را یک‌جا به ID عددی نگاشت می‌کند و scope graph/Resource با eager relationships UUID عمومی را می‌خوانند. سه query ثابت برای گراف و چهار query ثابت برای صفحهٔ دارای والد، به‌جای query در هر ردیف استفاده می‌شود. مقایسهٔ کل schema نسبت به baseline با همین تغییرات صریح، صفر اختلاف ناخواسته داشت.

34. گزینه‌های سرویس در OfferingOptions از روابط Eloquent و eager loading خوانده می‌شوند. شرط، عنوان و تعریف با cast مدل decode می‌شوند؛ انتخاب گزینه‌ها در OfferingEligibility از OptionRevisionSet گروهی و رابطهٔ نسخه استفاده می‌کند. دو متد repository بدون مصرف و سه dependency واسط OfferingOptions حذف شدند. تعداد query برای ۱ و ۴۰ گزینه ثابت است: دریافت گزینه‌ها ۵، eligibility با گزینه‌های انتخاب‌شده ۶. پاسخ عمومی و قواعد نسخهٔ جاری، tenant، REQUIRED/FORBIDDEN/CONDITIONAL حفظ شدند.

## Conventions Standardized

- Application service → `Application/Services` + `Application/Contracts/*Interface` + binding صریح.
- UseCase entry point → `handle`؛ private execute pass-through باقی نمانده است.
- Dependency names → نام کامل و معنادار نوع dependency.
- Imports و قالب‌بندی PHP در کد مالکیت پروژه؛ vendor دست‌کاری نشده است.
- pricing core → DTO/value object + enum، مبلغ integer rial با محاسبهٔ decimal.
- Transaction و pagination → APIهای بومی Laravel؛ policy تعداد تلاش صریح.
- مجوز و scope → قرارداد مشترک typed و بررسی بدون query داخل حلقهٔ نامزدها.
- Workbook HTTP output → JsonResource؛ خطای parsing → exception مستقل.

**این یکسان‌سازی به معنی تکمیل DTO و Eloquent در تمام ماژول‌ها نیست.**

## Behavior Changes

برای ورودی‌های معتبرِ تست‌شده، قرارداد HTTP و نتایج pricing حفظ شده‌اند. ۱۰۰۰ مقایسه با calculator نسخهٔ HEAD شامل هر شش روش محاسبه، ردیف‌ها، جمع‌ها و fingerprint یکسان بود.

تغییرات عمدی و محدود:

- پیکربندی نامعتبرِ محاسبه (مثلاً FIXED بدون مبلغ، rounding بدون step معتبر) به‌جای صفر یا fallback خاموش، خطای صریح دارد.
- validator ماتریس و parser اکسل، اعشار ریال را حتی نزدیک مرز دقت float رد می‌کنند؛ مقدار صحیح بزرگ حفظ می‌شود. خطای نمایش float در quantity چهاررقمی، overlap یا discontinuity کاذب ایجاد نمی‌کند.
- محاسبهٔ مالی دقیق، خطاهای precision قبلی را در مقادیر بزرگ/مرزی اصلاح می‌کند؛ از جمله حفظ آخرین ریال بالاتر از مرز دقت float. ادعای برابری برای تمام رفتارهای خطادار قبلی نداریم.
- اعتبارسنجی تعرفه با تاریخ شروعِ خالی، خطای الزامی‌بودن تاریخ را نگه می‌دارد و بررسی وابسته به تاریخ را انجام نمی‌دهد؛ fallback ضمنی به «اکنون» حذف شده است.
- traversal در graph معیوبِ cyclic پایان می‌یابد و خود root را در descendants نمی‌آورد؛ graph معتبر همان descendants را دارد.
- GeoJSON با position دارای کلیدهای نامعتبر یا مختصات غیرمتناهی، در parser به نتیجهٔ خطای مشخص تبدیل می‌شود؛ این داده‌ها ورودی معتبر HTTP نیستند.
- workbook دارای لینک خارجی با single quote یا فاصله در XML نیز رد می‌شود؛ محدودیت حجم پیش از decode بررسی می‌شود.

با تأیید کاربر برای نصب تازه، migrationهای اولیه بازنویسی شدند. همهٔ ۸۹ جدولِ schema نصب‌شده دارای PK عددی خودافزا هستند و ۸۰ مدل فعلی از هویت عددی native استفاده می‌کنند؛ UUID عمومی جدا و unique باقی است. FK مربوط به revision وضعیت و دو FK والد/فرزند سلسله‌مراتب ناحیه عددی شدند؛ سایر FKهای مبتنی بر UUID عمومی هنوز باقی‌اند. هیچ migration روی دیتابیس موجود کاربر اجرا نشده است. جزئیات و محدودیت‌ها در [schema.md](schema.md).

## Tests

- ادامهٔ فعلی: مجموعهٔ پیش‌فرض **۲۱۲ تست، ۳۱۸۹ assertion، پاس**. تست‌های متمرکز کاتالوگ **۱۳ تست، ۷۲ assertion، پاس**؛ ۸ مورد جدید رفتار و query count گزینه‌ها را پوشش می‌دهند. syntax و Pint هفت فایل این مرحله موفق‌اند. اجرای کامل integration نیز **۱۲۰ تست، ۲۲۱۸ assertion، صفر خطا/شکست/skip** داشت؛ شامل هر چهار تست LocalUserCommand و مسیرهای HTTP کاتالوگ و pricing. اجرای کامل جدید در ۲۶۳ ثانیه پایان یافت.

- checkpoint پیشین مجموعهٔ پیش‌فرض روی MySQL/Redis موقت: **۱۹۰ تست، ۳۱۰۸ assertion، پاس**. checkpoint شامل تغییرات Idempotency، Dashboard و tariff validation است.
- Integration کامل پس از AuthorizationContext، Idempotency و Dashboard: **۱۱۸ تست، ۲۱۸۸ assertion، پاس**؛ پس از تغییرات tariff validation نیز کامل بازاجرا شد. `null` در `menu_keys` معنای منوی خودکار دارد و در DTO حفظ شد.
- Dashboard: **۴ تست، ۹۳ assertion، پاس**. تعرفه/کاتالوگ پس از validator جدید: **۱۴ تست، ۳۵۹ assertion، پاس**. تست‌های مستقل query ثابت، انتخاب نسخهٔ معتبر، decimal ranges، HTTP validation و microsecond boundary نیز پاس شدند.
- آخرین checkpoint امنیت ورود/نشست، onboarding و Outbox: **۳۰ تست، ۳۳۴ assertion، پاس**؛ checkpoint مجوز/واگذاری نقش: **۲۰ تست، ۳۴۰ assertion، پاس**؛ Geography: **۲ تست، ۳۲ assertion، پاس**.
- snapshot scope: **۵ تست، ۹۸ assertion، پاس**؛ OTP persistence: **۳ تست، ۱۳ assertion، پاس**. این تست‌ها پوشش tenant، نبود query در حلقه، SELF scope، commit خطا و انقضا و نگهداری hash را بررسی می‌کنند.
- نصب تازه، seed دوباره و rollback کامل روی دیتابیس اختصاصی `chabok_refactor` موفق بود. schema consolidation در مقایسهٔ واقعی MySQL اختلافی با schema پیش از آن نداشت.
- بررسی syntax: **۱۷۸۹ فایل PHP، صفر خطا**؛ named constructor arguments، route listing و Composer validation موفق‌اند (دو هشدار exact-version قبلی باقی‌اند). **۱۰۰۰** مقایسهٔ تداخل بازه با الگوریتم نسخهٔ HEAD نیز برابر بود.
- مقایسه‌های تاریخی موتور pricing و matrix: **۱۰۰۰** و **۲۰۰۰** ورودی، نتایج valid برابر. گزارش جزئی matrix مستقل باقی است.
- extensionها، MySQL و Redis آزمایشی فقط در `/tmp` آماده شدند. دیتابیس موجود کاربر، `.env` و PHP سیستم تغییر نکردند؛ محافظ محیط integration فعال است.


## Pattern Coverage

ستون runtime شامل `Modules/*/src` و `app` است. ستون کل، test و migration و seeder را نیز دارد. فهرست فایل و occurrence در baseline/current-patterns.json ثبت شده است.

| Pattern | Runtime before | Runtime after | All before | All after |
|---|---:|---:|---:|---:|
| `db_table` | 177 | 122 | 662 | 596 |
| `raw_sql` | 9 | 6 | 181 | 240 |
| `join` | 126 | 87 | 133 | 93 |
| `to_base` | 350 | 286 | 350 | 286 |
| `array_argument` | 1123 | 1037 | 1177 | 1137 |
| `array_return` | 1085 | 1029 | 1136 | 1158 |
| `untyped_result` | 310 | 265 | 310 | 265 |
| `json` | 120 | 102 | 151 | 139 |
| `recursive_sql` | 3 | 0 | 3 | 0 |
| `trigger` | 2 | 2 | 51 | 92 |
| `interface` | 90 | 230 | 90 | 230 |
| `enum` | 1 | 25 | 3 | 25 |

## Remaining Issues

| دستهٔ خواسته‌شده | اصلاح انجام‌شده | موارد باقی‌مانده / دلیل |
|---|---|---|
| DTO / anonymous arrays | calculator، workbook، matrix، hierarchy، context مجوز، پیام/receipt، OTP، session و profile update typed شدند | قراردادهای آرایه‌ای بسیاری در Consignment، Manifest، Operations، ServiceCatalog و سایر flowها هنوز نیاز به تبدیل end-to-end دارند؛ این‌ها استثنای تکمیل‌شده نیستند. |
| Eloquent / DB usage | Network، Geography، Identity، Audit و Outbox؛ روابط Authorization و قرارداد context سراسری | DB::table و toBase در Repositoryهای دیگر باقی است. جایگزینی متنی خطرناک است چون مصرف‌کننده‌ها از stdClass و `(array)` و JSON خام استفاده می‌کنند. |
| Service interfaces | 160 Application service + چهار Domain contract | contractهای قدیمیِ integration port نام‌های خود را دارند؛ static utilityها interface نگرفته‌اند. |
| UseCase structure | 185 wrapper حذف؛ handle یکسان | تعدادی Handler بزرگ و facadeهای Service → Handler هنوز نیاز به ساده‌سازی مسئولیت دارند. |
| Naming | DI نام‌گذاری و import سراسری؛ calculator و workbook نام روشن | vocabulary سایر generic serviceها و نام متدهای قدیمی کامل بازبینی/تغییر نشده است. |
| Enums / magic values | pricing calculation، matrix state/code و zone policy | status/type/event/authorization stringهای دیگر باقی‌اند. |
| Long methods | entry point و formatting و calculator روشن‌تر شد | Dashboard و tariff validator تفکیک شدند؛ quote orchestration و تعدادی workflow عملیاتی هنوز نیاز به تفکیک دارند. |
| Validation | invalid pricing configuration صریح؛ workbook parsing و typed matrix validation جدا | tariff validation و خروجی مشترک تعرفه/زون typed شدند؛ validation هندسه و عضویت زون نوع‌دار شدند؛ exception-based branching در سایر ماژول‌ها باقی است. |
| Migrations / primary keys | PK عددی همهٔ جدول‌ها، ۸۰ مدل، FK عددی revision و سلسله‌مراتب ناحیه، seed وضعیت، حذف ۲۶ backfill و دو migration مجوز | بیشتر FKها هنوز به UUID عمومی unique ارجاع دارند؛ تبدیل دیگر FKها و جایگزینی triggerهای فاقد الزام مستقیم دیتابیس باقی است؛ rollback guardها حذف شدند. جزئیات در schema.md. |
| Laravel-native replacements | Eloquent relations/casts و JsonResource در flowهای اصلاح‌شده | TransactionManager و Page حذف شدند. بازبینی auth سفارشی و wrapperهای باقی‌مانده هنوز ادامه دارد. |
| Query-in-loop / N+1 | assignment title lookup؛ hierarchy traversal؛ بارگذاری گروهی نسخه‌های گزینه در quote و matrix matcher | queryهای هر قاعده/خانواده در tariff dependencies و validation حذف شدند؛ بررسی وابستگی compiler ماتریس نیز گروهی شد؛ queryهای داخل loop برای گزینه‌های OfferingOptions و OfferingEligibility حذف شدند؛ queryهای داخل loop در route planning، catalog dependencies دیگر و aggregationها باقی‌اند؛ ادعای نبود N+1 در کل پروژه نداریم. |
| Serialization | workbook/assignment Resource؛ محاسبات و draft serializer مشخص | quote orchestration و persistence هنوز array snapshot مصرف می‌کنند؛ ماتریس در مرز به Domain object تبدیل می‌شود. API Resource migration سراسری کامل نیست. |
| Parser/import | ورودی/خروجی و cell/band typed؛ parser مستقیماً validator typed را فراخوانی می‌کند؛ security/precision تست شدند | header و row parsing هنوز در preview service هستند؛ serializer فقط در مرز HTTP استفاده می‌شود. |
| Domain calculators | PricingRuleCalculator و FreightMatrixRuleCompiler typed/pure و decimal money؛ validator و rank policy مستقل | PricingFacts، commitment calculations، quote orchestration و سایر محاسبات عملیاتی هنوز audit عمیق‌تر لازم دارند. |
| Exception handling | InvalidMatrixWorkbook و InvalidPricingConfiguration | ApiExceptionهای Application/Domain دیگر همچنان باقی‌اند. |
| Folder structure | 45 سرویس منتقل؛ Data/Resources/Enums/Exceptions مشخص | چند utility و catalog/exception قدیمی در ریشهٔ Application/Domain باقی است. |

### وضعیت ادامهٔ ریفکتور

پاسخ کاربر دریافت شد: پروژه تازه است و migrationهای اولیه قابل بازنویسی‌اند. این تصمیم اعمال و روی MySQL واقعی بررسی شد. با این حال، جدول Remaining Issues بالا هنوز بخشی از درخواست اصلی است؛ این گزارش به معنای تکمیل تمام Definition of Done سند نیست.
