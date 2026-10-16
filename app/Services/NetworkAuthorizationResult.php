<?php

namespace App\Services;

class NetworkAuthorizationResult
{
    public function __construct(
        public readonly bool $isAuthorized,
        public readonly string $code = 'OK',
        public readonly string $message = '',
        public readonly string $verificationLevel = 'none',
        public readonly ?string $clientSsid = null,
        public readonly ?string $clientBssid = null,
        public readonly ?string $clientIp = null,
    ) {}

    public static function allowed(
        string $message = 'Network access authorized.',
        string $verificationLevel = 'none',
        ?string $clientSsid = null,
        ?string $clientBssid = null,
        ?string $clientIp = null
    ): self {
        return new self(
            isAuthorized: true,
            code: 'AUTHORIZED',
            message: $message,
            verificationLevel: $verificationLevel,
            clientSsid: $clientSsid,
            clientBssid: $clientBssid,
            clientIp: $clientIp
        );
    }

    public static function denied(
        string $message,
        string $code = 'NETWORK_NOT_AUTHORIZED',
        ?string $clientSsid = null,
        ?string $clientBssid = null,
        ?string $clientIp = null
    ): self {
        return new self(
            isAuthorized: false,
            code: $code,
            message: $message,
            verificationLevel: 'none',
            clientSsid: $clientSsid,
            clientBssid: $clientBssid,
            clientIp: $clientIp
        );
    }
}
