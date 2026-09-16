<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain;

/** Immutable timestamp preserving the existing UTC JSON representation. */

final class Instant extends \DateTimeImmutable implements \JsonSerializable
{
    public function jsonSerialize(): string
    {
        return $this->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
