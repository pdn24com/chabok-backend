<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Modules\ServiceCatalog\Application\Repositories\CatalogCodeRepository;

final readonly class CatalogCode
{
    public function __construct(private CatalogCodeRepository $codes)
    {
    }

    public function generate(string $resource, string $owner): string
    {
        // Callers are in a transaction; serialize automatic allocation per tenant.
        $this->codes->lockOwner($owner);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = (string) random_int(100000, 999999);
            if (!$this->codes->exists($resource, $owner, $code)) {
                return $code;
            }
        }
        throw new \RuntimeException('Unable to allocate catalog code.');
    }
}
