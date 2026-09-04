<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TokenController extends LicenseController
{
    public function issue(Request $request): JsonResponse
    {
        return $this->refresh($request);
    }
}
