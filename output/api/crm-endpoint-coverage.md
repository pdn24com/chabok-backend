# پوشش API های CRM بر اساس صفحات پروتوتایپ

مرجع: `CRM-Prototype-v12 (1).html` — کاتالوگ `window.API12` و گروه‌بندی `groups` در انتهای همان فایل.
وضعیت ثبت‌شده در این سند از روی مسیرهای واقعیِ ثبت‌شده در روتر برنامه استخراج شده، نه از روی کد.

پایهٔ مسیرها در پروتوتایپ `/api/crm/v1` است؛ در این پروژه `/api/v1/crm` (و برای چند مسیر قدیمی‌تر `/api/v1`).

| علامت | معنی |
|---|---|
| ✅ | پیاده‌سازی شده و مسیرش ثبت است |
| 🟡 | بخشی پیاده شده یا شکل نهایی با پروتوتایپ فرق دارد (توضیح در ستون یادداشت) |
| ❌ | هنوز پیاده نشده |

**جمع‌بندی:** پروتوتایپ ۴۱ گروه API در ۵ دسته دارد: **۲۹ گروه ✅**، **۵ گروه 🟡** و **۷ گروه ❌**.

| دسته | گروه‌ها | ✅ | 🟡 | ❌ |
|---|---|---|---|---|
| پروندهٔ مشتری و سرنخ | ۲۱ | ۱۲ | ۵ | ۴ |
| فروش (فرصت‌ها و اسناد فروش) | ۶ | ۶ | ۰ | ۰ |
| کار و تعامل | ۶ | ۵ | ۰ | ۱ |
| تیم | ۴ | ۴ | ۰ | ۰ |
| کاتالوگ و اسناد | ۴ | ۲ | ۰ | ۲ |

---

## ۱) پروندهٔ مشتری و سرنخ

### صفحهٔ فهرست مشتریان / سرنخ‌ها 🟡

| گروه پروتوتایپ | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت | یادداشت |
|---|---|---|---|---|
| `customer.list` | `GET /customers` | `GET /api/v1/customers` | ✅ | فیلترها: `page,per_page,display_name,customer_code,phase,kind,lifecycle,assignee_id,updated_at[_from,_to]` |
| `lead.list` | `GET /customers?phase=LEAD` | `GET /api/v1/customers?phase=LEAD` | ✅ | همان endpoint با فیلتر `phase` |
| `customer.create` | `POST /customers` | `POST /api/v1/customers` | ✅ | دارای `idempotent:customer.create` |
| `lead.create` | `POST /customers` | `POST /api/v1/customers` | 🟡 | خودِ ثبت ✅ ولی `GET /customers/duplicates` ❌ |
| `lead.convert` | `POST /customers/{id}/convert` | — | ❌ | تبدیل سرنخ به مشتری (تخصیص `customer_code`، `converted_at`) |
| `lead.close` | `POST /customers/{id}/lifecycle` | `PATCH /api/v1/crm/customers/{id}/profile` | 🟡 | تغییر `lifecycle` ممکن است، ولی endpoint اختصاصی «بستن با دلیل» و ثبت `NOTE` در `crm_activities` نیست |

### تب «نمای ۳۶۰ درجه» ✅ کامل

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت |
|---|---|---|---|
| `customer.get` | `GET /customers/{id}` | `GET /api/v1/crm/customers/{customerId}/detail` | ✅ |

### تب «اطلاعات اصلی» 🟡

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت | یادداشت |
|---|---|---|---|---|
| `customer.basic` | `PATCH /customers/{id}` | `GET/PATCH /api/v1/crm/customers/{customerId}/profile` | ✅ | خواندن هم اضافه شد |
| `customer.basic` | `GET /industries?active=true` | `GET /api/v1/crm/industries` | ✅ | |
| `customer.basic` | `PUT /customers/{id}/industries` | — | ❌ | چند صنعت با یک صنعت اصلی؛ فعلاً فقط `primary_industry_id` روی profile |
| `customer.address` | `GET/POST /customers/{id}/addresses` | `GET/POST /api/v1/crm/customers/{customerId}/addresses` | ✅ | `GET/PATCH .../addresses/{addressId}` هم اضافه شد |
| `customer.address` | `GET /geo/provinces`، `/cities` | `GET /api/v1/reference/provinces`، `/reference/cities` | ✅ | ماژول Geography |

### تب «راه‌های ارتباطی» ❌

| گروه | مسیر پروتوتایپ | وضعیت |
|---|---|---|
| `customer.contacts` | `GET/PUT /customers/{id}/contact-points` | ❌ |

### تب «افراد مرتبط / روابط» ❌

| گروه | مسیر پروتوتایپ | وضعیت |
|---|---|---|
| `customer.relationships` | `GET /customers/{id}/relationships`، `POST /companies/{companyId}/relationships`، `POST /relationships/{id}/end` | ❌ |

### تب «ساختار سازمانی» ✅ کامل

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت |
|---|---|---|---|
| `company.chart` | `GET /companies/{id}/org-chart` | `GET /api/v1/crm/customers/{customerId}/org-structure` | ✅ |
| `company.chart` | `POST /companies/{id}/units` | `POST /api/v1/crm/customers/{customerId}/departments` | ✅ |
| `company.chart` | `POST /units/{unitId}/positions` | `POST /api/v1/crm/customers/{customerId}/departments/{departmentId}/positions` | ✅ |
| — | — | `PATCH .../departments/{departmentId}`، `PATCH .../positions/{positionId}` | ✅ اضافه بر پروتوتایپ |

### تب «اطلاعات تکمیلی» ✅ کامل

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت | یادداشت |
|---|---|---|---|---|
| `customer.extended` | `PUT /customers/{id}/extended-details` | `GET/PUT /api/v1/crm/customers/{customerId}/extended-details` | ✅ | `salutation`/`birth_date` هم در همین payload |
| `customer.qualification` | `PUT /customers/{id}/qualification` | همان `extended-details` بالا | 🟡 | **endpoint جدا ندارد**: ستون‌های ارزیابی در همان PUT ادغام شده‌اند. `evaluated_by`/`evaluated_at` را سرور می‌گذارد |
| `customer.financial` | `GET/PUT /customers/{id}/financial-details` | `GET/PUT /api/v1/crm/customers/{customerId}/financial-details` | ✅ | مجوز مالی جدا (`crm.finance.*`) |
| `customer.entries` | `GET/POST /customers/{id}/financial-entries` | `GET/POST /api/v1/crm/customers/{customerId}/financial-entries` | ✅ | فیلتر `?kind=`، فیلد `allocated` |
| `customer.entries` | `POST /financial-entries/{receiptId}/allocations` | `POST /api/v1/crm/financial-entries/{entryId}/allocations` | ✅ | خطای `ALLOCATION_EXCEEDS_RECEIPT` با `details.remaining` |
| `customer.invoices` | `POST /customers/{id}/external-invoices` | `GET/POST /api/v1/crm/customers/{customerId}/external-invoices` | ✅ | خواندن هم اضافه شد؛ `409` روی تکرار `(external_system, reference_no)` |
| `customer.banks` | `GET/POST /customers/{id}/bank-accounts` | `GET/POST /api/v1/crm/customers/{customerId}/bank-accounts` | ✅ | پاسخ ماسک‌شده؛ یک حساب اصلی فعال |

### تب «مستندات» 🟡

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت | یادداشت |
|---|---|---|---|---|
| `customer.documents` | `GET /documents?resource_type=CUSTOMER&resource_id={id}` | `GET /api/v1/crm/documents?resource_type=CUSTOMER&resource_id={id}` | ✅ | |
| `customer.documents` | `POST /documents/{documentId}/links` | `POST /api/v1/crm/documents/{documentId}/links` | ✅ | |
| `customer.contracts` | `GET/POST /customers/{id}/contracts` | — | ❌ | شناسنامهٔ قرارداد (`crm_contracts`) در ماژول CrmSales هنوز کد ندارد |

### تب «سوابق تعامل» 🟡

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت | یادداشت |
|---|---|---|---|---|
| `customer.activities` | `GET /customers/{id}/activities?type=&page=` | `GET /api/v1/crm/customers/{customerId}/history`<br>`GET .../history/timeline`<br>`GET .../history/{category}` | 🟡 | شکل پاسخ گسترده‌تر از پروتوتایپ است (شش کارت + تایم‌لاین پیوسته) |

---

## ۲) صفحهٔ فرصت‌های فروش (کانبان) ✅ کامل

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت |
|---|---|---|---|
| `funnel.list` | `GET /sales-funnels?active=true` | `GET /api/v1/crm/sales-funnels?active=1` | ✅ |
| `opp.list` | `GET /opportunities?funnel_id=&customer_id=&assignee_id=` | `GET /api/v1/crm/opportunities` | ✅ |
| `opp.create` | `POST /opportunities` | `POST /api/v1/crm/opportunities` | ✅ |
| `opp.transition` | `POST /opportunities/{id}/step-transitions` | `POST /api/v1/crm/opportunities/{opportunityId}/step-transitions` | ✅ |
| `opp.transition` | `GET /opportunities/{id}/events` | `GET /api/v1/crm/opportunities/{opportunityId}/events` | ✅ |

---

## ۳) صفحهٔ اسناد فروش ✅ کامل

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت |
|---|---|---|---|
| `sales.list` | `GET /sales-documents?customer_id=&status=` | `GET /api/v1/crm/sales-documents` | ✅ |
| `sales.create` | `POST /sales-documents` | `POST /api/v1/crm/sales-documents` | ✅ |
| `sales.create` | `POST /sales-document-versions/{id}/issue` | `POST /api/v1/crm/sales-document-versions/{versionId}/issue` | ✅ |
| `sales.create` | `POST /sales-document-versions/{id}/accept` و `/cancel` | `POST /api/v1/crm/sales-document-versions/{versionId}/accept` و `/cancel` | ✅ |
| — | — | `GET /api/v1/crm/sales-documents/{documentId}`، `POST .../{documentId}/versions` | ✅ اضافه بر پروتوتایپ |

---

## ۴) صفحهٔ کارتابل (کار و تعامل) 🟡

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت | یادداشت |
|---|---|---|---|---|
| `task.list` | `GET /tasks?scope=&bucket=&q=` | `GET /api/v1/tasks` | ✅ | |
| `task.create` | `POST /tasks` | `POST /api/v1/tasks` | ✅ | |
| `task.action` | `POST /tasks/{id}/actions` | `POST /api/v1/tasks/{taskId}/actions` | ✅ | |
| `task.complete` | `POST /tasks/{id}/complete` | `POST /api/v1/tasks/{taskId}/complete` | ✅ | |
| `task.assign` | `POST /tasks/{id}/assignments` | `POST /api/v1/tasks/{taskId}/assignments` | ✅ | |
| `activity.create` | `POST /activities` | — | ❌ | ثبت تعامل مستقل از کار؛ فعلاً فقط از مسیر `tasks/{id}/actions` |

---

## ۵) صفحهٔ تیم‌ها ✅ کامل

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت |
|---|---|---|---|
| `team.list` | `GET /teams?status=ACTIVE` | `GET /api/v1/crm/teams` | ✅ |
| `team.list` | `GET /team-members?team_id=&role=&status=&q=&page=` | `GET /api/v1/crm/team-members` | ✅ |
| `team.save` | `POST /teams` | `POST /api/v1/crm/teams` | ✅ |
| — | — | `PATCH /api/v1/crm/teams/{teamId}` | ✅ اضافه بر پروتوتایپ |
| `member.save` | `POST /teams/{teamId}/members` | `POST /api/v1/crm/teams/{teamId}/members` | ✅ |
| `member.save` | `POST /memberships/{id}/end` | `POST /api/v1/crm/memberships/{membershipId}/end` | ✅ |
| `membership.bulk` | `POST /memberships/bulk` | `POST /api/v1/crm/memberships/bulk` | ✅ |
| — | — | `POST /api/v1/crm/memberships/bulk/preview` | ✅ اضافه بر پروتوتایپ |

---

## ۶) صفحهٔ آرشیو مستندات ✅ کامل

| گروه | مسیر پروتوتایپ | مسیر پیاده‌شده | وضعیت |
|---|---|---|---|
| `doc.list` | `GET /documents?status=&category_id=&q=` | `GET /api/v1/crm/documents` | ✅ |
| `doc.list` | `GET /document-categories` | `GET /api/v1/crm/document-categories` | ✅ |
| `doc.save` | `POST /documents` | `POST /api/v1/crm/documents` | ✅ |
| `doc.save` | `PATCH /documents/{id}` | `PATCH /api/v1/crm/documents/{documentId}` | ✅ |
| `doc.save` | `POST /documents/{id}/archive` | `POST /api/v1/crm/documents/{documentId}/archive` | ✅ |

**رجیستری اتصال (`document_links.resource_type`):** پروتوتایپ پنج نوع دارد — `CUSTOMER`، `OPPORTUNITY`، `CONTRACT`، `SALES_DOCUMENT`، `CATALOG_ITEM`.
فعلاً فقط **`CUSTOMER` و `OPPORTUNITY`** پذیرفته می‌شوند، چون بقیه read model ندارند و اتصالی که قابل بررسی نباشد ثبت نمی‌شود. افزودن هرکدام = یک `case` روی enum + یک `arm` روی adapter.

---

## ۷) صفحهٔ کاتالوگ ❌ پیاده نشده

| گروه | مسیر پروتوتایپ | وضعیت |
|---|---|---|
| `catalog.list` | `GET /catalog-items?kind=&status=&category_id=&q=` | ❌ |
| `catalog.save` | `POST /catalog-items` | ❌ |
| `catalog.save` | `GET /catalog/categories · /catalog/personas · /catalog/sales-models` | ❌ |

جدول‌های `crm_catalog_items` و جدول‌های مرجعش migration دارند ولی ماژول `CrmCatalog` فقط `GET /crm/industries` را سرو می‌کند.

---

## فهرست کارهای باقی‌مانده (به ترتیب پیشنهادی)

1. **`customer.contacts`** — `GET/PUT /customers/{id}/contact-points` (جدول `crm_contact_points` آماده است)
2. **`customer.relationships`** — روابط شخص و شرکت (`crm_relationships`، `crm_positions`)
3. **`customer.contracts`** — شناسنامهٔ قرارداد؛ `contract_id` روی «فاکتور بیرونی» هم منتظر همین است
4. **`lead.convert` / `lead.close`** — چرخهٔ سرنخ
5. **`customer.basic → PUT /industries`** — چند صنعت با صنعت اصلی
6. **`activity.create`** — ثبت تعامل مستقل
7. **`lead.create → GET /customers/duplicates`** — کنترل تکراری بودن
8. **`catalog.list` / `catalog.save`** — کل صفحهٔ کاتالوگ

---

## یادداشت‌های انحراف از پروتوتایپ

- **تاریخ‌ها**: هر تاریخ در ورودی و خروجی، **unix timestamp بر حسب ثانیه** است (نه رشتهٔ `YYYY-MM-DD` پروتوتایپ). برای ستون‌های نوع `date` فقط بخش روزِ تقویمی نگهداری می‌شود.
- **شناسه‌ها**: همهٔ شناسه‌ها در پاسخ **رشته** هستند (`"11"` نه `11`).
- **صفحه‌بندی**: `meta.pagination.{page,page_size,total,total_pages}`؛ پروتوتایپ `size` می‌گفت، اینجا `per_page`.
- **مجوزها**: هر صفحه جفت‌مجوز خودش را دارد و همه زیر entitlement `Customer` هستند —
  `customer.*`، `crm.industry.view`، `crm.finance.{view,manage}`، `crm.opportunity.{view,manage}`، `crm.document.{view,manage}` و مجوزهای task/team.
- **خطاها**: بدنهٔ یکسان `error_code` + `message` + `field_errors` + `details`، دوزبانه بر اساس `Accept-Language` (پیش‌فرض `fa`).

## بدهی فنی شناخته‌شده

- `Modules/Customer/src/Application/UseCases/{GetCustomerQualification,SaveCustomerQualification}` بعد از ادغام ارزیابی در `extended-details` بی‌استفاده مانده‌اند و باید حذف شوند.
- `Modules/CrmTask/src/Application/Ports/OpportunityDirectoryInterface` هیچ adapter و binding ندارد.
- شش هندلر `CrmTeam` به‌خاطر bind نشدن `TeamAccessGuardInterface` از کانتینر resolve نمی‌شوند.
