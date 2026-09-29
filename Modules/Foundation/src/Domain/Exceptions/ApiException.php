<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Exceptions;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use RuntimeException;

/**
 * Carries a translation key rather than prose, so one throw serves every locale.
 *
 * The Domain layer stays framework-free, so the key is never resolved here; the
 * Presentation layer renders it against the negotiated locale. Logs therefore
 * show the stable key instead of locale-dependent prose.
 */
final class ApiException extends RuntimeException
{
    /**
     * @param  string  $messageKey  Key into lang/<locale>/api.php, e.g. 'common.resource_not_found'.
     * @param  array<string, list<string>>  $fieldErrors
     * @param  array<string, mixed>  $details
     * @param  array<string, scalar>  $messageParams
     */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        public readonly int $httpStatus,
        public readonly string $messageKey,
        public readonly array $fieldErrors = [],
        public readonly array $details = [],
        public readonly array $messageParams = [],
    ) {
        parent::__construct($messageKey);
    }
}
