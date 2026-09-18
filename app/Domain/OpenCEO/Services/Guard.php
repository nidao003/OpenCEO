<?php

namespace Leantime\Domain\OpenCEO\Services;

use Leantime\Core\Exceptions\AuthorizationException;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth;

final class Guard
{
    public static function manager(): void
    {
        if (app()->runningInConsole()) {
            return;
        }
        if (! Auth::userIsAtLeast(Roles::$manager)) {
            throw new AuthorizationException('OpenCEO management access requires manager, admin or owner role.');
        }
    }
}
