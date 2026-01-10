<?php
// Helpers for deriving Mikrotik PPP profile names from package rows.

if (!function_exists('package_ppp_profile_name')) {
    /**
     * Return the MikroTik PPP profile name for a package row, if present.
     * Prefers dedicated columns like profile_name/profile, falling back to
     * the package name only for backward compatibility.
     */
    function package_ppp_profile_name(?array $package): ?string {
        if (!$package) {
            return null;
        }
        $candidates = [
            'profile_name',
            'profile',
            'pppoe_profile',
            'mt_profile',
            'router_profile',
            'name',
        ];
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $package)) {
                continue;
            }
            $val = trim((string)$package[$key]);
            if ($val !== '') {
                return $val;
            }
        }
        return null;
    }
}
