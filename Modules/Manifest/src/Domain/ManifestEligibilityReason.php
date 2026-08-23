<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain;

final class ManifestEligibilityReason
{
    public const Eligible = 'ELIGIBLE';
    public const ParcelNotFound = 'PARCEL_NOT_FOUND';
    public const ParcelAlreadyAssigned = 'PARCEL_ALREADY_ASSIGNED';
    public const PricingStale = 'PRICING_STALE';
    public const StatusNotAllowed = 'STATUS_NOT_ALLOWED';
    public const CurrentNodeMismatch = 'CURRENT_NODE_MISMATCH';
    public const CustodyMismatch = 'CUSTODY_MISMATCH';
    public const PickupTaskNotCompleted = 'PICKUP_TASK_NOT_COMPLETED';
    public const RoutePlanMismatch = 'ROUTE_PLAN_MISMATCH';
    public const RouteLegMismatch = 'ROUTE_LEG_MISMATCH';
    public const RouteLegNotReady = 'ROUTE_LEG_NOT_READY';
    public const ConfigVersionUnavailable = 'CONFIG_VERSION_UNAVAILABLE';
    public const TransportRunMismatch = 'TRANSPORT_RUN_MISMATCH';
    public const TransportRunNotArrived = 'TRANSPORT_RUN_NOT_ARRIVED';
    public const DeliveryRouteIncomplete = 'DELIVERY_ROUTE_INCOMPLETE';
    public const DeliveryNodeMismatch = 'DELIVERY_NODE_MISMATCH';
    public const DriverUnavailable = 'DRIVER_UNAVAILABLE';
    public const VehicleUnavailable = 'VEHICLE_UNAVAILABLE';

    /** @return array{reason_code:string,eligible:bool,severity:string,presentation:array{title:array{fa:string,en:string},detail:array{fa:string,en:string},suggested_action:array{fa:string,en:string}}} */
    public static function metadata(string $code): array
    {
        $copy = self::copy()[$code] ?? self::copy()[self::StatusNotAllowed];

        return [
            'reason_code' => $code,
            'eligible' => $code === self::Eligible,
            'severity' => $code === self::Eligible ? 'SUCCESS' : 'ERROR',
            'presentation' => $copy,
        ];
    }

    public static function safeReason(string $code): string
    {
        return self::metadata($code)['presentation']['detail']['en'];
    }

    /** @return array<string, array{title:array{fa:string,en:string},detail:array{fa:string,en:string},suggested_action:array{fa:string,en:string}}> */
    private static function copy(): array
    {
        return [
            self::Eligible => self::item('آماده', 'Eligible', 'بسته با زمینه عملیاتی مانیفست سازگار است.', 'The Parcel matches the Manifest operational context.', 'بسته را اضافه یا اعتبارسنجی کنید.', 'Add or validate the Parcel.'),
            self::ParcelNotFound => self::item('بسته پیدا نشد', 'Parcel not found', 'شماره بسته یا مرسوله در دامنه عملیاتی این گره پیدا نشد.', 'The Parcel or Consignment number was not found in this node scope.', 'شماره برچسب و گره جاری را بررسی کنید.', 'Check the label number and current node.'),
            self::ParcelAlreadyAssigned => self::item('تخصیص فعال دیگر', 'Already assigned', 'بسته در یک مانیفست باز دیگر برای همین وضعیت هدف فعال است.', 'The Parcel is active in another open Manifest for this target.', 'مانیفست فعال را تکمیل یا بسته را در همان مانیفست پیگیری کنید.', 'Complete or follow the Parcel in the active Manifest.'),
            self::PricingStale => self::item('قیمت‌گذاری نیازمند بازبینی', 'Pricing requires review', 'قیمت‌گذاری مرسوله پیش از عملیات باید دوباره نهایی شود.', 'Consignment pricing must be recalculated and locked before operations.', 'مرسوله را بازقیمت‌گذاری کنید.', 'Recalculate the Consignment pricing.'),
            self::StatusNotAllowed => self::item('وضعیت فعلی مجاز نیست', 'Status not allowed', 'وضعیت فعلی بسته اجازه این انتقال مانیفست را نمی‌دهد.', 'The Parcel current status does not allow this Manifest transition.', 'فرآیند مالک وضعیت فعلی را تکمیل کنید.', 'Complete the workflow that owns the current status.'),
            self::CurrentNodeMismatch => self::item('بسته در این گره نیست', 'Parcel is at another node', 'گره فعلی بسته با گره عملیاتی مانیفست یکسان نیست.', 'The Parcel current node does not match the Manifest operational node.', 'بسته را در گره صحیح دریافت یا مانیفست مناسب را باز کنید.', 'Receive it at the correct node or use the matching Manifest.'),
            self::CustodyMismatch => self::item('تحویل‌داری معتبر نیست', 'Custody mismatch', 'تحویل‌دار فعلی بسته با عملیات انتخاب‌شده سازگار نیست.', 'The current Parcel custodian does not match the selected operation.', 'تحویل‌داری را از مسیر عملیاتی درست اصلاح کنید.', 'Correct custody through the owning operational workflow.'),
            self::PickupTaskNotCompleted => self::item('جمع‌آوری تکمیل نشده', 'Pickup not completed', 'وظیفه جمع‌آوری تکمیل‌شده‌ای برای تحویل این بسته به گره وجود ندارد.', 'No completed Pickup Task delivers this Parcel to the current node.', 'ابتدا جمع‌آوری را تکمیل کنید.', 'Complete the Pickup Task first.'),
            self::RoutePlanMismatch => self::item('برنامه مسیر متفاوت است', 'Route Plan mismatch', 'برنامه مسیر فعال بسته با زمینه مانیفست یکسان نیست.', 'The Parcel active Route Plan does not match the Manifest context.', 'زمینه مسیر صحیح را انتخاب کنید.', 'Select the matching Route Plan context.'),
            self::RouteLegMismatch => self::item('گام مسیر متفاوت است', 'Route Leg mismatch', 'گام مسیر فعال بسته با گام انتخاب‌شده یکسان نیست.', 'The Parcel active Route Leg does not match the selected leg.', 'گام مسیر متناظر بسته را انتخاب کنید.', 'Select the Parcel matching Route Leg.'),
            self::RouteLegNotReady => self::item('گام مسیر آماده نیست', 'Route Leg is not ready', 'گام مسیر در وضعیت لازم برای این عملیات نیست.', 'The Route Leg is not in the lifecycle state required by this operation.', 'مرحله قبلی مسیر را تکمیل کنید.', 'Complete the preceding route operation.'),
            self::ConfigVersionUnavailable => self::item('نسخه مسیر در دسترس نیست', 'Route version unavailable', 'نسخه منتشرشده‌ای که برنامه مسیر به آن ارجاع می‌دهد در دسترس نیست.', 'The published Route Definition Version referenced by the plan is unavailable.', 'پیکربندی مسیر را اصلاح و برنامه جانشین ایجاد کنید.', 'Repair route configuration and create a successor plan.'),
            self::TransportRunMismatch => self::item('سفر خطی متفاوت است', 'Transport Run mismatch', 'بسته عضو سفر خطی انتخاب‌شده نیست یا سفر فعال آن متفاوت است.', 'The Parcel is not loaded on the selected Transport Run.', 'سفر خطی متناظر را انتخاب کنید.', 'Select the matching Transport Run.'),
            self::TransportRunNotArrived => self::item('سفر هنوز نرسیده است', 'Transport Run has not arrived', 'سفر خطی در گره جاری به وضعیت رسیده نرسیده است.', 'The Transport Run has not reached ARRIVED at the current node.', 'ابتدا ورود سفر را در عملیات حمل ثبت کنید.', 'Record transport arrival first.'),
            self::DeliveryRouteIncomplete => self::item('مسیر تحویل کامل نیست', 'Delivery route incomplete', 'همه گام‌های مسیر بسته هنوز در گره مقصد دریافت نشده‌اند.', 'Not every Route Plan Leg has been received at the destination node.', 'دریافت گام‌های باقی‌مانده را تکمیل کنید.', 'Complete receipt of the remaining Route Legs.'),
            self::DeliveryNodeMismatch => self::item('گره تحویل متفاوت است', 'Delivery node mismatch', 'این گره، گره تحویل عملیاتی مرسوله نیست.', 'The current node is not the Consignment delivery node.', 'مانیفست تحویل را در گره مقصد ایجاد کنید.', 'Create the delivery Manifest at the destination node.'),
            self::DriverUnavailable => self::item('راننده در دسترس نیست', 'Driver unavailable', 'راننده تحویل انتخاب‌شده دیگر فعال، آزاد، توانمند یا در دامنه این گره نیست.', 'The selected delivery Driver is no longer active, available, capable, and in node scope.', 'راننده معتبر دیگری انتخاب کنید.', 'Select another eligible Driver.'),
            self::VehicleUnavailable => self::item('خودرو در دسترس نیست', 'Vehicle unavailable', 'خودرو انتخاب‌شده دیگر فعال، آزاد یا در دامنه این گره نیست.', 'The selected Vehicle is no longer active, available, and in node scope.', 'خودرو معتبر دیگری انتخاب کنید.', 'Select another eligible Vehicle.'),
        ];
    }

    /** @return array{title:array{fa:string,en:string},detail:array{fa:string,en:string},suggested_action:array{fa:string,en:string}} */
    private static function item(string $faTitle, string $enTitle, string $faDetail, string $enDetail, string $faAction, string $enAction): array
    {
        return [
            'title' => ['fa' => $faTitle, 'en' => $enTitle],
            'detail' => ['fa' => $faDetail, 'en' => $enDetail],
            'suggested_action' => ['fa' => $faAction, 'en' => $enAction],
        ];
    }
}
