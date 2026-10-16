<?php

namespace App\Services;

use App\Models\NetworkRestrictionSetting;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class NetworkRestrictionService
{
    /**
     * Evaluate whether the incoming HTTP request complies with the active network restriction policy.
     */
    public function evaluate(Request $request): NetworkAuthorizationResult
    {
        $setting = NetworkRestrictionSetting::current();

        if (!$setting->is_enabled) {
            return NetworkAuthorizationResult::allowed(
                message: 'Network restriction is currently disabled by administrator.',
                verificationLevel: 'disabled',
                clientIp: $request->ip()
            );
        }

        $clientSsid = $this->extractSsid($request);
        $clientBssid = $this->extractBssid($request);
        $clientNetworkType = $this->extractNetworkType($request);
        $clientIp = $request->ip();

        $method = $setting->verification_method ?: 'client_assisted';

        switch ($method) {
            case 'trusted_ip_range':
                return $this->evaluateTrustedIpRange(
                    $setting,
                    $clientIp,
                    $clientSsid,
                    $clientBssid,
                    $clientNetworkType
                );

            case 'school_vpn_or_gateway':
                return $this->evaluateSchoolVpnOrGateway(
                    $setting,
                    $request,
                    $clientIp,
                    $clientSsid,
                    $clientBssid
                );

            case 'client_assisted':
            default:
                return $this->evaluateClientAssisted(
                    $setting,
                    $clientSsid,
                    $clientBssid,
                    $clientNetworkType,
                    $clientIp
                );
        }
    }

    /**
     * Throw an HTTP 403 HttpResponseException if network authorization fails.
     */
    public function validateOrThrow(Request $request): NetworkAuthorizationResult
    {
        $result = $this->evaluate($request);

        if (!$result->isAuthorized) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error_code' => $result->code,
                'message' => $result->message,
            ], 403));
        }

        return $result;
    }

    /**
     * Get non-sensitive policy information safe for mobile client consumption.
     */
    public function getPublicPolicy(): array
    {
        $setting = NetworkRestrictionSetting::current();
        $statusInfo = $setting->evaluateStatus();

        return [
            'is_enabled' => (bool) $setting->is_enabled,
            'authorized_ssid' => $setting->authorized_ssid,
            'has_authorized_bssids' => !empty($setting->authorized_bssid_list),
            'network_description' => $setting->network_description ?: 'Bansud Central School Main Wi-Fi',
            'verification_method' => $setting->verification_method ?: 'client_assisted',
            'verification_status' => $statusInfo['status'],
            'verification_badge' => $statusInfo['badge'],
            'warning_message' => $statusInfo['warning'],
            'is_strong_verification' => (bool) $statusInfo['is_strong'],
        ];
    }

    /**
     * Evaluate preliminary client-assisted Wi-Fi matching.
     */
    protected function evaluateClientAssisted(
        NetworkRestrictionSetting $setting,
        ?string $clientSsid,
        ?string $clientBssid,
        ?string $clientNetworkType,
        ?string $clientIp
    ): NetworkAuthorizationResult {
        $authorizedSsid = trim((string) $setting->authorized_ssid);

        if (empty($authorizedSsid)) {
            return NetworkAuthorizationResult::denied(
                message: 'School network restriction is enabled, but the authorized SSID is not configured.',
                code: 'NETWORK_VERIFICATION_UNAVAILABLE',
                clientSsid: $clientSsid,
                clientBssid: $clientBssid,
                clientIp: $clientIp
            );
        }

        if (empty($clientNetworkType) || strtolower($clientNetworkType) !== 'wifi') {
            return NetworkAuthorizationResult::denied(
                message: 'School network restriction required. Please connect to the authorized school Wi-Fi network (' . $authorizedSsid . ').',
                code: 'NETWORK_NOT_AUTHORIZED',
                clientSsid: $clientSsid,
                clientBssid: $clientBssid,
                clientIp: $clientIp
            );
        }

        if (empty($clientSsid)) {
            return NetworkAuthorizationResult::denied(
                message: 'Unable to detect Wi-Fi network name. Please ensure location permission is granted on Android to detect the school Wi-Fi SSID.',
                code: 'NETWORK_NOT_AUTHORIZED',
                clientSsid: $clientSsid,
                clientBssid: $clientBssid,
                clientIp: $clientIp
            );
        }

        if (strcasecmp($clientSsid, $authorizedSsid) !== 0) {
            return NetworkAuthorizationResult::denied(
                message: 'Connected Wi-Fi (' . $clientSsid . ') does not match the authorized school network (' . $authorizedSsid . ').',
                code: 'NETWORK_NOT_AUTHORIZED',
                clientSsid: $clientSsid,
                clientBssid: $clientBssid,
                clientIp: $clientIp
            );
        }

        $authorizedBssids = $setting->authorized_bssid_list;
        if (!empty($authorizedBssids)) {
            if (empty($clientBssid)) {
                return NetworkAuthorizationResult::denied(
                    message: 'Connected Wi-Fi access point identity (BSSID) could not be determined.',
                    code: 'NETWORK_NOT_AUTHORIZED',
                    clientSsid: $clientSsid,
                    clientBssid: $clientBssid,
                    clientIp: $clientIp
                );
            }

            $normalizedClientBssid = strtoupper(trim($clientBssid));
            if (!in_array($normalizedClientBssid, $authorizedBssids, true)) {
                return NetworkAuthorizationResult::denied(
                    message: 'Access point BSSID (' . $clientBssid . ') is not authorized for ATTENDSYNC attendance operations.',
                    code: 'NETWORK_NOT_AUTHORIZED',
                    clientSsid: $clientSsid,
                    clientBssid: $clientBssid,
                    clientIp: $clientIp
                );
            }
        }

        return NetworkAuthorizationResult::allowed(
            message: 'Client-assisted Wi-Fi verification matched the authorized school network.',
            verificationLevel: 'preliminary_client_assisted',
            clientSsid: $clientSsid,
            clientBssid: $clientBssid,
            clientIp: $clientIp
        );
    }

    /**
     * Evaluate server-side trusted IP/CIDR verification.
     */
    protected function evaluateTrustedIpRange(
        NetworkRestrictionSetting $setting,
        ?string $clientIp,
        ?string $clientSsid,
        ?string $clientBssid,
        ?string $clientNetworkType
    ): NetworkAuthorizationResult {
        $trustedRanges = $setting->trusted_ip_list;

        if (empty($trustedRanges)) {
            return NetworkAuthorizationResult::denied(
                message: 'Server-side trusted IP verification is configured, but no authorized IP ranges have been defined.',
                code: 'NETWORK_VERIFICATION_UNAVAILABLE',
                clientSsid: $clientSsid,
                clientBssid: $clientBssid,
                clientIp: $clientIp
            );
        }

        if (!$clientIp || !IpUtils::checkIp($clientIp, $trustedRanges)) {
            return NetworkAuthorizationResult::denied(
                message: 'Connection rejected. Your connection IP (' . ($clientIp ?: 'unknown') . ') did not originate from an authorized school network gateway or subnet.',
                code: 'NETWORK_NOT_AUTHORIZED',
                clientSsid: $clientSsid,
                clientBssid: $clientBssid,
                clientIp: $clientIp
            );
        }

        // Secondary check on SSID if configured
        $authorizedSsid = trim((string) $setting->authorized_ssid);
        if (!empty($authorizedSsid) && !empty($clientSsid)) {
            if (strcasecmp($clientSsid, $authorizedSsid) !== 0) {
                return NetworkAuthorizationResult::denied(
                    message: 'Device reports Wi-Fi (' . $clientSsid . ') which differs from the authorized network name (' . $authorizedSsid . ').',
                    code: 'NETWORK_NOT_AUTHORIZED',
                    clientSsid: $clientSsid,
                    clientBssid: $clientBssid,
                    clientIp: $clientIp
                );
            }
        }

        return NetworkAuthorizationResult::allowed(
            message: 'Server-side IP authorization confirmed via authorized school gateway/subnet.',
            verificationLevel: 'strong_server_verified',
            clientSsid: $clientSsid,
            clientBssid: $clientBssid,
            clientIp: $clientIp
        );
    }

    /**
     * Evaluate school-controlled VPN or dedicated reverse proxy gateway.
     */
    protected function evaluateSchoolVpnOrGateway(
        NetworkRestrictionSetting $setting,
        Request $request,
        ?string $clientIp,
        ?string $clientSsid,
        ?string $clientBssid
    ): NetworkAuthorizationResult {
        $trustedRanges = $setting->trusted_ip_list;

        if (empty($trustedRanges)) {
            return NetworkAuthorizationResult::denied(
                message: 'School VPN or gateway verification is enabled, but no authorized gateway IPs or subnets are configured on the server.',
                code: 'NETWORK_VERIFICATION_UNAVAILABLE',
                clientSsid: $clientSsid,
                clientBssid: $clientBssid,
                clientIp: $clientIp
            );
        }

        if (!$clientIp || !IpUtils::checkIp($clientIp, $trustedRanges)) {
            return NetworkAuthorizationResult::denied(
                message: 'Connection rejected. Request did not originate through the authorized school VPN or gateway IP.',
                code: 'NETWORK_NOT_AUTHORIZED',
                clientSsid: $clientSsid,
                clientBssid: $clientBssid,
                clientIp: $clientIp
            );
        }

        return NetworkAuthorizationResult::allowed(
            message: 'Connection verified through authorized school gateway / VPN boundary.',
            verificationLevel: 'strong_server_verified',
            clientSsid: $clientSsid,
            clientBssid: $clientBssid,
            clientIp: $clientIp
        );
    }

    protected function extractSsid(Request $request): ?string
    {
        $raw = $request->header('X-Network-SSID')
            ?: $request->input('network_ssid');

        if (!$raw) {
            return null;
        }

        $clean = trim((string) $raw, " \t\n\r\0\x0B\"'");

        if (strcasecmp($clean, '<unknown ssid>') === 0 || strcasecmp($clean, 'null') === 0 || $clean === '') {
            return null;
        }

        return $clean;
    }

    protected function extractBssid(Request $request): ?string
    {
        $raw = $request->header('X-Network-BSSID')
            ?: $request->input('network_bssid');

        if (!$raw) {
            return null;
        }

        $clean = trim((string) $raw, " \t\n\r\0\x0B\"'");

        if (strcasecmp($clean, '<unknown bssid>') === 0 || strcasecmp($clean, 'null') === 0 || $clean === '') {
            return null;
        }

        return $clean;
    }

    protected function extractNetworkType(Request $request): ?string
    {
        $raw = $request->header('X-Network-Type')
            ?: $request->input('network_type');

        if (!$raw) {
            return null;
        }

        return strtolower(trim((string) $raw));
    }
}
