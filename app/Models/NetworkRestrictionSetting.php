<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkRestrictionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_enabled',
        'authorized_ssid',
        'authorized_bssids',
        'network_description',
        'verification_method',
        'trusted_ip_ranges',
        'verification_status',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static ?self $currentSetting = null;

    /**
     * Retrieve the current network restriction settings singleton.
     */
    public static function current(): self
    {
        if (static::$currentSetting !== null) {
            return static::$currentSetting;
        }

        $setting = static::query()->orderBy('id')->first();

        if (!$setting) {
            $setting = static::create([
                'is_enabled' => false,
                'authorized_ssid' => 'BansudCS-Faculty',
                'authorized_bssids' => null,
                'network_description' => 'Bansud Central School Main Wi-Fi',
                'verification_method' => 'client_assisted',
                'trusted_ip_ranges' => null,
                'verification_status' => 'configured',
            ]);
        }

        return static::$currentSetting = $setting;
    }

    /**
     * Clear the cached singleton instance.
     */
    public static function clearCurrentCache(): void
    {
        static::$currentSetting = null;
    }

    public function save(array $options = []): bool
    {
        $saved = parent::save($options);
        static::$currentSetting = $this;

        return $saved;
    }

    /**
     * Parse authorized BSSIDs into a normalized array of MAC addresses.
     *
     * @return array<string>
     */
    public function getAuthorizedBssidListAttribute(): array
    {
        if (empty($this->authorized_bssids)) {
            return [];
        }

        $raw = preg_split('/[\r\n,]+/', (string) $this->authorized_bssids);
        $list = [];

        foreach ($raw as $item) {
            $clean = strtoupper(trim($item));
            if (!empty($clean)) {
                $list[] = $clean;
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * Parse trusted IP/CIDR ranges into an array.
     *
     * @return array<string>
     */
    public function getTrustedIpListAttribute(): array
    {
        if (empty($this->trusted_ip_ranges)) {
            return [];
        }

        $raw = preg_split('/[\r\n,]+/', (string) $this->trusted_ip_ranges);
        $list = [];

        foreach ($raw as $item) {
            $clean = trim($item);
            if (!empty($clean)) {
                $list[] = $clean;
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * Evaluate the configuration status, warning, and strong verification state.
     */
    public function evaluateStatus(): array
    {
        if (!$this->is_enabled) {
            return [
                'status' => 'configured',
                'badge' => 'Disabled',
                'warning' => null,
                'is_strong' => false,
                'description' => 'Network restriction is currently disabled. All networks are permitted.',
            ];
        }

        $trimmedSsid = trim((string) $this->authorized_ssid);

        if (empty($trimmedSsid) && $this->verification_method === 'client_assisted') {
            return [
                'status' => 'misconfigured',
                'badge' => 'Misconfigured',
                'warning' => 'Network restriction is enabled, but no authorized SSID has been configured.',
                'is_strong' => false,
                'description' => 'An authorized Wi-Fi SSID must be supplied when restriction is enabled.',
            ];
        }

        if ($this->verification_method === 'client_assisted') {
            return [
                'status' => 'unverified',
                'badge' => 'Preliminary Only',
                'warning' => 'SSID configured, but strong network verification is not configured. Matching Wi-Fi names alone do not guarantee an authorized connection.',
                'is_strong' => false,
                'description' => 'Client-assisted Wi-Fi check (SSID/BSSID) is active. Note that Wi-Fi names can be replicated.',
            ];
        }

        if ($this->verification_method === 'trusted_ip_range') {
            $ips = $this->trusted_ip_list;

            if (empty($ips)) {
                return [
                    'status' => 'misconfigured',
                    'badge' => 'Misconfigured',
                    'warning' => 'Trusted IP/CIDR verification selected, but no trusted IP addresses or CIDR blocks are configured.',
                    'is_strong' => false,
                    'description' => 'Provide at least one static IP or CIDR block (e.g. 192.168.1.0/24 or a school public WAN IP).',
                ];
            }

            return [
                'status' => 'verified',
                'badge' => 'Strong (IP/CIDR Verified)',
                'warning' => null,
                'is_strong' => true,
                'description' => 'Server verifies client source IP against configured authorized school network ranges.',
            ];
        }

        if ($this->verification_method === 'school_vpn_or_gateway') {
            $ips = $this->trusted_ip_list;

            if (empty($ips)) {
                return [
                    'status' => 'misconfigured',
                    'badge' => 'Misconfigured',
                    'warning' => 'School VPN/Gateway verification selected, but no gateway IP addresses or subnet CIDR blocks are configured.',
                    'is_strong' => false,
                    'description' => 'Provide the static IP or subnet of your school VPN or gateway router.',
                ];
            }

            return [
                'status' => 'verified',
                'badge' => 'Strong (Gateway/VPN Verified)',
                'warning' => null,
                'is_strong' => true,
                'description' => 'Server verifies requests arrive via the authorized school VPN or gateway IP/subnet.',
            ];
        }

        return [
            'status' => 'unverified',
            'badge' => 'Unverified',
            'warning' => 'Network verification method is unverified.',
            'is_strong' => false,
            'description' => 'Network status could not be verified.',
        ];
    }
}
