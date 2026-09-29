# گزارش نهایی ریفکتور — ۲۰۲۶/۰۹/۲۶

**اصلاح وضعیت پس از بازبینی مستقیم کد:** اجرای تمام بندهای سند تکمیل نشده است. پنج دستهٔ تأییدشده، شامل FKهای داخلی، مرز validation/HTTP، branching با exception، vocabulary ثابت و draft ماتریس تعرفه، در [remaining-work.md](remaining-work.md) ثبت شده‌اند. ادعای تکمیل قبلی با این اصلاح جایگزین می‌شود.

بخش بزرگی از جریان‌های قیمت‌گذاری، بارنامه، کاتالوگ، مانیفست، قرارداد سرویس‌ها و نوشتن گروهی پیاده‌سازی و بررسی شد. **آخرین اجرای کد: 447 تست و 13115 assertion؛ صفر error، failure یا skip.** استثناهای SQL، invariant و workflow در [completion-audit.md](completion-audit.md) مشخص‌اند؛ این اعداد اثبات کامل‌بودن refactor نیستند.

مبنای دامنه، [سند اصلی](../refactor.md) است. هر ۸۹ جدول PK عددی خودافزا دارد، ولی بیشتر FKها همچنان به UUID عمومی unique ارجاع می‌دهند. بند ۲۰ هماهنگی FK با PK و ارجاع به `id` را نیز توضیح می‌دهد؛ کامل دانستن این بخش تنها با عددی‌شدن PKها دقیق نبود. UUID عمومی باید در مرز API حفظ شود، اما این الزام به‌تنهایی توجیه کامل‌بودن روابط داخلی نیست.

## Files Changed / Files Added / Files Removed

نسبت به snapshot ابتدای همین ادامهٔ کار: **407 فایل PHP تغییر، 216 فایل اضافه و 9 مسیر حذف شده‌اند**. فایل‌های جابه‌جاشده در دو ستون اضافه/حذف شمرده می‌شوند. فهرست دقیق: [continuation-files-2026-09-26.json](continuation-files-2026-09-26.json).

[files-changed.json](files-changed.json) وضعیت کل workspace نسبت به Git HEAD را نشان می‌دهد و شامل کارهای قبل از این اجرا نیز هست. هیچ reset یا commit انجام نشده است.

- wrapperهای تک‌مقداری نتیجهٔ quote و session و projection عبوری quote حذف شدند.
- helperهای static به Mapper/Support و شکل‌دهی workflow مانیفست به Serialization منتقل شد.
- DTOهای واقعی تماس، بسته، draft، پذیرش قیمت، انتخاب سرویس، تعهد زمانی، validation و خلاصه/جزئیات مانیفست اضافه شدند.
- قراردادها، bindingها، مصرف‌کننده‌ها، FormRequest/mapperها و Resourceها هم‌زمان هماهنگ شدند؛ تغییر صرفاً اضافه‌کردن کلاس DTO نبود.

## Architecture Changes

1. تمام **۱۳۱ سرویس Application/Domain** قرارداد اختصاصی و binding مشخص دارند؛ تزریق concrete service با تست معماری رد می‌شود. Handlerها فقط `handle` را به‌عنوان ورودی عمومی دارند. اسامی dependencyها از قرارداد مربوطه گرفته شده‌اند.
2. قیمت‌گذاری از ورودی نوع‌دار تا انتخاب کاتالوگ، محاسبه، مدل quote و snapshot پیش می‌رود. serializer فقط در مرز fingerprint، HTTP، provider، Redis و ذخیرهٔ شواهد استفاده می‌شود.
3. ساخت/ویرایش بارنامه با draft، contact، parcel و accepted quote نوع‌دار انجام می‌شود. منطق قیمت و قبول offering مشترک شده و درج بسته/ledger/audit داخل تراکنش حفظ شده است.
4. draft کاتالوگ، خطاهای validation، انتخاب سرویس، context قواعد، شرط‌ها، گروه ناحیه و تعهد زمانی نوع‌دار هستند. snapshotهای منتشرشده با schema تاریخی در مرز ذخیره‌سازی ساخته می‌شوند.
5. خواندن مانیفست model/DTO برمی‌گرداند و Resource پاسخ عمومی را می‌سازد؛ شمارش‌ها و referenceها گروهی خوانده می‌شوند. درج/به‌روزرسانی ردیف‌ها، taskها و aggregate چند بارنامه batch شده است.
6. `DB::table` از کد اجرایی حذف شد. آخرین join فرمان محلی با رابطهٔ Eloquent جایگزین شد. تطبیق point/polygon در adapter فضایی به batchهای ۱۰۰تایی منتقل شد.
7. متدهای بزرگ ساخت/ویرایش بارنامه، پذیرش quote، ایجاد تعرفه، زمان‌بندی تعهد، children کاتالوگ و برنامهٔ مسیر به مرحله‌های مشخص تقسیم شدند. lookup بازه‌های پستی از scan تکراری به index اجزای هویت تبدیل شد.

## Conventions Standardized

- Controller/FormRequest → mapper/command → `handle` → سرویس قراردادی و model → Resource.
- مدل و Collection بومی برای دادهٔ ذخیره‌شده؛ DTO برای دادهٔ ساختاریافتهٔ کاربردی؛ array برای list/map مشخص و اسناد مرزی.
- قراردادها در `Contracts`، پیاده‌سازی‌ها در `Services`؛ helper صرفاً static در `Support`/`Mappers`/`Serialization`.
- enum برای vocabulary دامنهٔ محدود؛ کدهای وضعیت عملیاتی قابل‌تعریف توسط tenant به‌صورت catalog value حفظ می‌شوند.
- تراکنش، قفل، paginator، casts و relationships بومی Laravel؛ audit/outbox در همان مرز تراکنش.
- queryهای batch با tenant scope، ترتیب قفل و حفظ شمارهٔ رویداد؛ fallback درج تک‌ردیفی فقط برای تعارض واقعی unique مورد انتظار.

## Behavior Changes

هدف این ادامه، حفظ قرارداد HTTP، permission، tenant، fingerprint، ترتیب ledger، نسخه، idempotency و rollback بوده است. suiteهای کامل و regressionهای جدید این رفتارها را پوشش می‌دهند؛ این نتیجه معادل اثبات ریاضی تمام ورودی‌های ممکن نیست.

- دقت timestamp مانیفست برای model و رشتهٔ خام مطابق قرارداد قبلی حفظ و با regression مستقل بررسی شد.
- omitted/null، عدد رشته‌ای و ترتیب fact keyها برای قراردادهای تاریخی حفظ شده‌اند؛ wildcard/first/last در factهای تنظیم‌شده نیز regression دارند.
- خطای integrity نامرتبط به تعارض مجاز تبدیل نمی‌شود؛ bundle قدیمی Redis با همان سند round-trip می‌شود.
- نوع‌های داخلیِ مصرف‌کننده‌های PHP تغییر کرده‌اند؛ HTTP Resourceها شکل پاسخ قبلی را نگه می‌دارند.
- `PricingReader::snapshotDetail` اکنون نبود snapshot را صریحاً با `firstOrFail` اعلام می‌کند؛ مسیرهای عمومی پیش از آن visibility را بررسی می‌کنند.
- دو نام index مشترک event برای سازگاری SQLite به نام‌های مختص جدول تغییر کردند؛ uniqueness همان است.
- اصلاحات رفتاری قدیمی دربارهٔ precision، خطای پیکربندی نامعتبر، canonical address و lookup خالی در [گزارش تاریخی](report-2026-09-24.md) محفوظ‌اند و تغییر تازهٔ این اجرا شمرده نشده‌اند.

## Tests

| اجرا | تست | assertion | خطا / شکست / skip |
|---|---:|---:|---:|
| Unit + Feature + Modules | 326 | 10877 | 0 / 0 / 0 |
| MySQL + Redis integration | 121 | 2238 | 0 / 0 / 0 |

شواهد قابل بررسی: [tests.json](tests.json)، [JUnit اصلی](default_suite_mysql_redis.xml)، [JUnit integration](integration_mysql_redis.xml).

- syntax هر ۱۸۵۹ فایل PHP متعلق به پروژه موفق؛ ۱۱۴۰ نوع ارجاع‌شدهٔ پروژه resolve شدند و mismatch آرگومان نام‌دار constructor پیدا نشد.
- Pint، `git diff --check`، فهرست ۱۷۴ route و Composer validate موفق؛ فقط دو هشدار قبلی exact-version در Composer باقی است.
- نصب جاری آزمایشی: ۸۹ PK عددی، ۲۷۲ FK، ۲۶ CHECK و ۹۰ trigger؛ [مشاهدهٔ schema](schema-observation-2026-09-26.json).
- MySQL/Redis موقت با port اختصاصی استفاده شدند. `.env` و دیتابیس کاری کاربر تغییر نکردند. suiteهای دارای دیتابیس مشترک پشت‌سرهم اجرا شدند.

### شمارش query در regressionهای واقعی

| مسیر | اندازه‌ها | query |
|---|---|---|
| درج و ذخیرهٔ وضعیت ردیف مانیفست | 1 / 40 / 101 | 1 / 1 / 2 |
| projection چند بارنامه | 1 / 40 / 101 | 5 / 5 / 7 |
| taskهای pickup مانیفست | 1 / 40 / 101 | 4 / 4 / 5 |
| تطبیق فضایی نقطه با چندضلعی | 1 / 40 / 101 | 1 / 1 / 2 |
| خواندن گزینه‌های سرویس | 1 / 40 | 5 / 5 |
| eligibility گزینه‌ها | 1 / 40 | 6 / 6 |

این اعداد برای مسیرهای آزموده‌شده‌اند؛ ادعای constant-query بودن تمام workflowهای دامنه نیستند.

## Pattern Coverage

تمام ۱۶ ماژول و `app`، شامل ۱۵۰۲ فایل PHP اجرایی، بررسی شدند. فهرست: [current-patterns.json](current-patterns.json)، [module-coverage.json](module-coverage.json)، [method-audit.json](method-audit.json).

| الگو | baseline اولیه | ابتدای این ادامه | اکنون |
|---|---:|---:|---:|
| `db_table` | 177 | 31 | 0 |
| `raw_sql` | 9 | 6 | 7 |
| `join` | 126 | 2 | 1 |
| `to_base` | 350 | 0 | 0 |
| `array_argument` | 1123 | 471 | 545 |
| `array_return` | 1085 | 601 | 635 |
| `untyped_result` | 310 | 4 | 5 |
| `json` | 120 | 30 | 29 |
| `recursive_sql` | 3 | 0 | 0 |
| `trigger` | 2 | 2 | 2 |
| `interface` | 90 | 77 | 185 |
| `enum` | 1 | 66 | 71 |

`array_argument` شمارش متنی declarationهای `array` است و propertyهای DTO را نیز می‌شمارد. `raw_sql` فقط فراخوانی مستقیم DB façade را می‌شمارد؛ expressionهای Eloquent جداگانه در audit ثبت‌اند. افزایش تعداد interface/DTO باعث تکرار signatureهای لیست و map شده است؛ بنابراین عدد کلی array معیار «کار باقی‌مانده» نیست. موارد و علت‌های باقیمانده در audit طبقه‌بندی شده‌اند.

## Remaining Issues / Intentional Exceptions

- **FK عمومی UUID:** حفظ شده؛ PK همهٔ جدول‌ها عددی است. این گزارش تبدیل FKها را ادعا نمی‌کند.
- **۹۰ trigger و CHECKهای ضروری:** تضمین مستقیم SQL برای tenant، immutability و code قدیمی در migrationهای مستقل حفظ شده است؛ [دلیل هر دسته](database-invariants.md).
- **SQL فضایی، aggregate و یک join تعرفه:** معادل بومی ساده‌ای که همان semantics و ordering را بدهد جایگزین نشده؛ دلایل دقیق در [audit](completion-audit.md).
- **عملیات ترتیبی دامنه:** lock/تاریخچهٔ route و delivery برای هر والد مستقل، retry تعارض unique، و بررسی topology هنگام تغییر پیکربندی، استثناهای مستندِ query در حلقه‌اند. query تکراری به‌ازای بستهٔ یک والد از مسیر مانیفست حذف شده است.
- **نصب دیتابیس:** migrationها مسیر fresh install پروژه‌اند؛ مهاجرت دیتابیس deployشده یا اجرای production انجام نشده است. مقایسهٔ schema و rollback کامل گزارش‌های قبلی تاریخی‌اند؛ دوباره به‌عنوان نتیجهٔ تازه شمرده نشده‌اند.

تست شکست‌خورده‌ای در آخرین اجرا ثبت نشده، اما کارهای باز معماری در [remaining-work.md](remaining-work.md) وجود دارند. استثناهای فنی و کارهای ناتمام باید جدا ارزیابی شوند؛ وضعیت اصلاح‌شدهٔ ابتدای این گزارش بر جمع‌بندی‌های قبلی اولویت دارد.
