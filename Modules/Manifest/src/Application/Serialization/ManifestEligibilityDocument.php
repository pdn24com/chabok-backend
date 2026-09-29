<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Serialization;

use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;

final class ManifestEligibilityDocument
{
    /** @return array{reason_code:string,eligible:bool,severity:string,presentation:array{title:array{fa:string,en:string},detail:array{fa:string,en:string},suggested_action:array{fa:string,en:string}}} */
    public static function metadata(ManifestEligibilityReason|string $reason): array
    {
        $code = $reason instanceof ManifestEligibilityReason ? $reason->value : $reason;
        $copy = self::copy()[$code] ?? self::copy()[ManifestEligibilityReason::StatusNotAllowed->value];

        return [
            'reason_code' => $code,
            'eligible' => $code === ManifestEligibilityReason::Eligible->value,
            'severity' => $code === ManifestEligibilityReason::Eligible->value ? 'SUCCESS' : 'ERROR',
            'presentation' => $copy,
        ];
    }

    public static function safeReason(ManifestEligibilityReason $code): string
    {
        return self::metadata($code)['presentation']['detail']['en'];
    }

    /** @return array<string, array{title:array{fa:string,en:string},detail:array{fa:string,en:string},suggested_action:array{fa:string,en:string}}> */
    private static function copy(): array
    {
        return [
            ManifestEligibilityReason::Eligible->value => self::item('آماده', 'Eligible', 'بسته با زمینه عملیاتی مانیفست سازگار است.', 'The Parcel matches the Manifest operational context.', 'بسته را اضافه یا اعتبارسنجی کنید.', 'Add or validate the Parcel.'),
            ManifestEligibilityReason::ParcelNotFound->value => self::item('بسته پیدا نشد', 'Parcel not found', 'شماره بسته یا مرسوله در دامنه عملیاتی این گره پیدا نشد.', 'The Parcel or Consignment number was not found in this node scope.', 'شماره برچسب و گره جاری را بررسی کنید.', 'Check the label number and current node.'),
            ManifestEligibilityReason::ParcelAlreadyAssigned->value => self::item('تخصیص فعال دیگر', 'Already assigned', 'بسته در یک مانیفست باز دیگر برای همین وضعیت هدف فعال است.', 'The Parcel is active in another open Manifest for this target.', 'مانیفست فعال را تکمیل یا بسته را در همان مانیفست پیگیری کنید.', 'Complete or follow the Parcel in the active Manifest.'),
            ManifestEligibilityReason::PricingStale->value => self::item('قیمت‌گذاری نیازمند بازبینی', 'Pricing requires review', 'قیمت‌گذاری مرسوله پیش از عملیات باید دوباره نهایی شود.', 'Consignment pricing must be recalculated and locked before operations.', 'مرسوله را بازقیمت‌گذاری کنید.', 'Recalculate the Consignment pricing.'),
            ManifestEligibilityReason::StatusNotAllowed->value => self::item('وضعیت فعلی مجاز نیست', 'Status not allowed', 'وضعیت فعلی بسته اجازه این انتقال مانیفست را نمی‌دهد.', 'The Parcel current status does not allow this Manifest transition.', 'فرآیند مالک وضعیت فعلی را تکمیل کنید.', 'Complete the workflow that owns the current status.'),
            ManifestEligibilityReason::CurrentNodeMismatch->value => self::item('بسته در این گره نیست', 'Parcel is at another node', 'گره فعلی بسته با گره عملیاتی مانیفست یکسان نیست.', 'The Parcel current node does not match the Manifest operational node.', 'بسته را در گره صحیح دریافت یا مانیفست مناسب را باز کنید.', 'Receive it at the correct node or use the matching Manifest.'),
            ManifestEligibilityReason::CustodyMismatch->value => self::item('تحویل‌داری معتبر نیست', 'Custody mismatch', 'تحویل‌دار فعلی بسته با عملیات انتخاب‌شده سازگار نیست.', 'The current Parcel custodian does not match the selected operation.', 'تحویل‌داری را از مسیر عملیاتی درست اصلاح کنید.', 'Correct custody through the owning operational workflow.'),
            ManifestEligibilityReason::PickupTaskNotCompleted->value => self::item('جمع‌آوری تکمیل نشده', 'Pickup not completed', 'وظیفه جمع‌آوری تکمیل‌شده‌ای برای تحویل این بسته به گره وجود ندارد.', 'No completed Pickup Task delivers this Parcel to the current node.', 'ابتدا جمع‌آوری را تکمیل کنید.', 'Complete the Pickup Task first.'),
            ManifestEligibilityReason::RoutePlanMismatch->value => self::item('برنامه مسیر متفاوت است', 'Route Plan mismatch', 'برنامه مسیر فعال بسته با زمینه مانیفست یکسان نیست.', 'The Parcel active Route Plan does not match the Manifest context.', 'زمینه مسیر صحیح را انتخاب کنید.', 'Select the matching Route Plan context.'),
            ManifestEligibilityReason::RouteLegMismatch->value => self::item('گام مسیر متفاوت است', 'Route Leg mismatch', 'گام مسیر فعال بسته با گام انتخاب‌شده یکسان نیست.', 'The Parcel active Route Leg does not match the selected leg.', 'گام مسیر متناظر بسته را انتخاب کنید.', 'Select the Parcel matching Route Leg.'),
            ManifestEligibilityReason::RouteLegNotReady->value => self::item('گام مسیر آماده نیست', 'Route Leg is not ready', 'گام مسیر در وضعیت لازم برای این عملیات نیست.', 'The Route Leg is not in the lifecycle state required by this operation.', 'مرحله قبلی مسیر را تکمیل کنید.', 'Complete the preceding route operation.'),
            ManifestEligibilityReason::ConfigVersionUnavailable->value => self::item('نسخه مسیر در دسترس نیست', 'Route version unavailable', 'نسخه منتشرشده‌ای که برنامه مسیر به آن ارجاع می‌دهد در دسترس نیست.', 'The published Route Definition Version referenced by the plan is unavailable.', 'پیکربندی مسیر را اصلاح و برنامه جانشین ایجاد کنید.', 'Repair route configuration and create a successor plan.'),
            ManifestEligibilityReason::PickupAssignmentMismatch->value => self::item('تخصیص جمع‌آوری متفاوت است', 'Pickup assignment mismatch', 'تخصیص راننده جمع‌آوری با مانیفست قبلی سازگار نیست.', 'The pickup Driver assignment does not match the preceding PD Manifest.', 'تخصیص جمع‌آوری را بررسی کنید.', 'Review the pickup assignment.'),
            ManifestEligibilityReason::RoutePlanUnavailable->value => self::item('برنامه مسیر در دسترس نیست', 'Route Plan unavailable', 'برنامه مسیر معتبر و منتشرشده‌ای پیدا نشد.', 'No valid configuration-derived Route Plan is available.', 'پیکربندی مسیر را منتشر کنید.', 'Publish valid route configuration.'),
            ManifestEligibilityReason::RouteLegUnavailable->value => self::item('گام مسیر در دسترس نیست', 'Route Leg unavailable', 'گام مسیر متناظر در برنامه مسیر پیدا نشد.', 'The required Route Plan Leg is unavailable.', 'برنامه مسیر را بررسی کنید.', 'Review the Route Plan.'),
            ManifestEligibilityReason::PreviousMovementMismatch->value => self::item('حرکت قبلی متفاوت است', 'Previous movement mismatch', 'شواهد مانیفست خروج با دریافت مقصد سازگار نیست.', 'The preceding OS Manifest evidence does not match destination reception.', 'مانیفست خروج متناظر را انتخاب کنید.', 'Select the matching OS Manifest.'),
            ManifestEligibilityReason::DeliveryRouteIncomplete->value => self::item('مسیر تحویل کامل نیست', 'Delivery route incomplete', 'همه گام‌های مسیر بسته هنوز در گره مقصد دریافت نشده‌اند.', 'Not every Route Plan Leg has been received at the destination node.', 'دریافت گام‌های باقی‌مانده را تکمیل کنید.', 'Complete receipt of the remaining Route Legs.'),
            ManifestEligibilityReason::DeliveryNodeMismatch->value => self::item('گره تحویل متفاوت است', 'Delivery node mismatch', 'این گره، گره تحویل عملیاتی مرسوله نیست.', 'The current node is not the Consignment delivery node.', 'مانیفست تحویل را در گره مقصد ایجاد کنید.', 'Create the delivery Manifest at the destination node.'),
            ManifestEligibilityReason::DriverUnavailable->value => self::item('راننده در دسترس نیست', 'Driver unavailable', 'راننده تحویل انتخاب‌شده دیگر فعال، آزاد، توانمند یا در دامنه این گره نیست.', 'The selected delivery Driver is no longer active, available, capable, and in node scope.', 'راننده معتبر دیگری انتخاب کنید.', 'Select another eligible Driver.'),
            ManifestEligibilityReason::DriverIncapable->value => self::item('قابلیت راننده کافی نیست', 'Driver incapable', 'راننده قابلیت لازم برای عملیات را ندارد.', 'The selected Driver lacks the required operational capability.', 'راننده توانمند دیگری انتخاب کنید.', 'Select a capable Driver.'),
            ManifestEligibilityReason::DriverOutOfScope->value => self::item('راننده خارج از دامنه است', 'Driver out of scope', 'راننده به گره یا HQ عملیاتی تعلق ندارد.', 'The selected Driver is outside the authorized HQ or Node.', 'راننده داخل دامنه انتخاب کنید.', 'Select a Driver in scope.'),
            ManifestEligibilityReason::VehicleUnavailable->value => self::item('خودرو در دسترس نیست', 'Vehicle unavailable', 'خودرو انتخاب‌شده دیگر فعال، آزاد یا در دامنه این گره نیست.', 'The selected Vehicle is no longer active, available, and in node scope.', 'خودرو معتبر دیگری انتخاب کنید.', 'Select another eligible Vehicle.'),
            ManifestEligibilityReason::VehicleIncapable->value => self::item('خودرو نامتناسب است', 'Vehicle incapable', 'خودرو برای حرکت خطی انتخاب‌شده مناسب نیست.', 'The selected Vehicle is not capable of the movement.', 'خودرو مناسب دیگری انتخاب کنید.', 'Select a capable Vehicle.'),
            ManifestEligibilityReason::VehicleOutOfScope->value => self::item('خودرو خارج از دامنه است', 'Vehicle out of scope', 'خودرو به گره یا HQ عملیاتی تعلق ندارد.', 'The selected Vehicle is outside the authorized HQ or Node.', 'خودرو داخل دامنه انتخاب کنید.', 'Select a Vehicle in scope.'),
            ManifestEligibilityReason::CoverageNotFound->value => self::item('پوشش یافت نشد', 'Coverage not found', 'پوشش منتشرشده‌ای برای مقصد یافت نشد.', 'No published coverage matches the destination.', 'پیکربندی پوشش را تکمیل کنید.', 'Complete coverage configuration.'),
            ManifestEligibilityReason::CoverageAmbiguous->value => self::item('پوشش مبهم است', 'Coverage ambiguous', 'بیش از یک پوشش هم‌اولویت با مقصد مطابقت دارد.', 'Multiple equal-priority coverage rules match.', 'پیکربندی پوشش را رفع ابهام کنید.', 'Disambiguate coverage configuration.'),
            ManifestEligibilityReason::ExceptionReviewRequired->value => self::item('بازبینی استثنا لازم است', 'Exception review required', 'این انتقال فقط پس از تأیید بازبین مجاز اعمال می‌شود.', 'This transition is applied only after authorized Exception Review.', 'درخواست را برای بازبینی ارسال کنید.', 'Submit the Exception for review.'),
        ];
    }

    /** @return array{title:array{fa:string,en:string},detail:array{fa:string,en:string},suggested_action:array{fa:string,en:string}} */
    private static function item(
        string $faTitle,
        string $enTitle,
        string $faDetail,
        string $enDetail,
        string $faAction,
        string $enAction,
    ): array {
        return [
            'title' => ['fa' => $faTitle, 'en' => $enTitle],
            'detail' => ['fa' => $faDetail, 'en' => $enDetail],
            'suggested_action' => ['fa' => $faAction, 'en' => $enAction],
        ];
    }
}
