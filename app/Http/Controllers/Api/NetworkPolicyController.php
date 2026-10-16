<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NetworkRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkPolicyController extends Controller
{
    public function __construct(
        protected NetworkRestrictionService $networkService
    ) {}

    /**
     * Retrieve the current network restriction policy for authenticated employees.
     */
    public function policy(Request $request): JsonResponse
    {
        $policy = $this->networkService->getPublicPolicy();

        return response()->json([
            'success' => true,
            'policy' => $policy,
            'client_ip' => $request->ip(),
        ]);
    }

    /**
     * Actively verify current network credentials and obtain status without mutating state.
     */
    public function verify(Request $request): JsonResponse
    {
        $result = $this->networkService->evaluate($request);
        $policy = $this->networkService->getPublicPolicy();

        return response()->json([
            'success' => true,
            'authorized' => $result->isAuthorized,
            'error_code' => $result->isAuthorized ? null : $result->code,
            'message' => $result->message,
            'verification_level' => $result->verificationLevel,
            'policy' => $policy,
            'client_info' => [
                'ip' => $request->ip(),
                'detected_ssid' => $result->clientSsid,
                'detected_bssid' => $result->clientBssid,
            ],
        ], $result->isAuthorized ? 200 : 403);
    }
}
