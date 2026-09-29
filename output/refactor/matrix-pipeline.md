# Typed freight matrix pipeline

## Scope

ادامهٔ بندهای ۲۷، ۲۸، ۳۹، ۴۰، ۴۱ و ۴۲ سند refactor برای ماتریس تعرفه؛ این مرحله به معنی تکمیل ریفکتور سراسری نیست.

- `FreightMatrixInput` draft و metadata ذخیره‌شده را به `FreightMatrix`، `FreightMatrixBand` و `FreightMatrixCell` تبدیل می‌کند. legacy `linear_tail` فقط همین‌جا normalize می‌شود و metadata اصلی تغییر نمی‌کند.
- `FreightMatrixValidatorInterface` و پیاده‌سازی آن، ورودی نوع‌دار و `MatrixValidationIssue` برمی‌گردانند. state، zone policy و error codeها enum هستند؛ nullable state/policy امکان جمع‌آوری خطاهای ورودی مرزی را بدون `ValueError` فراهم می‌کند. گزارش policy نامعتبر در orchestration مرزی باقی است.
- `FreightMatrixRuleCompilerInterface` قواعد ثابت و خطی را به `CompiledMatrixRateRule` تبدیل می‌کند. ترتیب قواعد ثابت و سپس خطی حفظ شده است. compiler فاقد JSON، HTTP و persistence است.
- `ZoneRankPolicyInterface` فقط کامل‌بودن، مثبت‌بودن و یکتایی rankها را بررسی می‌کند.
- `MatrixQuantityPrecision` دقت چهار رقم اعشار و حداکثر گام را متمرکز می‌کند؛ compiler و validator از یک policy استفاده می‌کنند. tolerance فقط برای خطای نمایش float در quantity است؛ مبلغ با float محاسبه نمی‌شود.
- `MatrixValidationSerializer` و `MatrixRateRuleSerializer` شکل سابق خطاها و قواعد را در مرز API/storage حفظ می‌کنند. `MatrixDraftSerializer` فقط برای پاسخ HTTP استفاده می‌شود؛ bridge parser→array→validator حذف شده است.
- workbook همان Domain band/cell را می‌سازد. normalization عدد فارسی/عربی به decimal دقیق انجام می‌شود؛ سقف integer امن سمت کلاینت حفظ شده است.
- matrix matcher، نسخه‌های offering را یک بار و نسخه‌های هر option را یک بار در هر فراخوانی می‌خواند. تست با ۴۰ ماتریس تعداد فراخوانی هر reference را یک بررسی می‌کند.
- cell پایه برای هر band یک بار براساس zone index می‌شود؛ جست‌وجوی کامل لیست برای هر سلول حذف شده است.

## Behavior and verification

- ۲۰۰۰ مقایسهٔ strict با پیاده‌سازی قبل از این مرحله: draft validation، publish validation، compilation و ورودی‌های نامعتبر شامل gap، step صفر، state ناشناخته، ID تکراری، zone تکراری، تعریف هم‌زمان tail و bands و fixed bands خالی؛ همه موفق.
- تست regression برای carry دقیق ریال بالاتر از 2^53، گام decimal، نبود نرخ پایه، مبلغ کسری بزرگ، integer overflow، normalization legacy بدون تغییر ورودی و تعداد lookupهای catalog افزوده شد.
- دو regression اکسل: نرخ صحیح فارسی نزدیک سقف integer و رد اعشار ریال که قبلاً ممکن بود در تبدیل float حذف شود.
- اصلاح عمدی ورودی‌های معیوب: اعشار ریال و overflow پذیرفته نمی‌شوند؛ band/step غیرعددی رد می‌شود؛ خطای نمایش float در quantity معتبر overlap کاذب ایجاد نمی‌کند.
- نتایج suiteها و syntax در `tests.json` و گزارش اصلی ثبت می‌شوند.

## Remaining boundaries

TariffMatrixCompiler و ValidateTariffHandler در orchestration هنوز ورودی draft آرایه‌ای دارند. DTOسازی همهٔ tariff flows، خطاهای تعرفه خارج از matrix، persistence و Resourceهای سایر endpointها هنوز بخشی از کار باقی‌مانده‌اند. تبدیل FKها و حذف triggerها در این مرحله انجام نشده است.
