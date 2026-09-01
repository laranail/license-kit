<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends LicenseController
{
    public function issue(Request $request): JsonResponse
    {
        return $this->refresh($request);
    }
}
