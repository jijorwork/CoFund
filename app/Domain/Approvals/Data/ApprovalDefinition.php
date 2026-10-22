<?php

namespace App\Domain\Approvals\Data;

use App\Domain\Identity\Enums\FoundationPermission;
use InvalidArgumentException;

final readonly class ApprovalDefinition
{
    /**
     * @param  list<FoundationPermission>  $stepPermissions
     */
    public function __construct(
        public string $key,
        public int $version,
        public FoundationPermission $submitPermission,
        public array $stepPermissions,
    ) {
        if (
            preg_match('/^[a-z][a-z0-9_.-]{2,119}$/', $key) !== 1
            || $version < 1
            || $stepPermissions === []
            || count($stepPermissions) > 20
        ) {
            throw new InvalidArgumentException('The approval definition is invalid.');
        }
    }
}
