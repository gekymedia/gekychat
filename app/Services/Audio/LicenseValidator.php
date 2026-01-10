<?php

namespace App\Services\Audio;

class LicenseValidator
{
    // Safe licenses we allow
    private const SAFE_LICENSES = [
        'Creative Commons 0', // CC0 - Public Domain
        'Attribution', // CC BY
    ];
    
    // Blocked licenses (contain restrictions)
    private const BLOCKED_TERMS = [
        'Noncommercial',
        'Non-Commercial',
        'NC',
        'Share Alike',
        'ShareAlike',
        'SA',
        'NoDerivatives',
        'ND',
        'Sampling',
    ];
    
    /**
     * Check if a license is safe for redistribution
     */
    public function isSafeLicense(string $licenseName): bool
    {
        // First check if it contains any blocked terms
        foreach (self::BLOCKED_TERMS as $blocked) {
            if (stripos($licenseName, $blocked) !== false) {
                return false;
            }
        }
        
        // Then check if it's in our safe list
        foreach (self::SAFE_LICENSES as $safeLicense) {
            if (stripos($licenseName, $safeLicense) !== false) {
                return true;
            }
        }
        
        // If not in safe list and doesn't contain blocked terms, be conservative
        return false;
    }
    
    /**
     * Check if license requires attribution
     */
    public function requiresAttribution(string $licenseName): bool
    {
        // CC0 doesn't require attribution
        if (stripos($licenseName, 'CC0') !== false || stripos($licenseName, 'Creative Commons 0') !== false) {
            return false;
        }
        
        // Attribution license requires attribution
        return stripos($licenseName, 'Attribution') !== false;
    }
    
    /**
     * Format attribution text
     */
    public function formatAttribution(array $soundData): string
    {
        $name = $soundData['name'] ?? 'Unknown';
        $username = $soundData['username'] ?? 'Unknown';
        
        return sprintf('"%s" by %s (Freesound)', $name, $username);
    }
    
    /**
     * Validate and prepare license information
     */
    public function validateAndPrepare(array $soundData): array
    {
        $license = $soundData['license'] ?? '';
        
        if (!$this->isSafeLicense($license)) {
            throw new \Exception("License '{$license}' is not allowed for redistribution. Only CC0 and CC BY licenses are permitted.");
        }
        
        return [
            'license_type' => $license,
            'license_url' => $soundData['license'] ?? '',
            'attribution_required' => $this->requiresAttribution($license),
            'attribution_text' => $this->requiresAttribution($license) 
                ? $this->formatAttribution($soundData) 
                : null,
        ];
    }
    
    /**
     * Filter search results to only include safe licenses
     */
    public function filterSafeResults(array $results): array
    {
        return array_values(array_filter($results, function($sound) {
            $license = $sound['license'] ?? '';
            return $this->isSafeLicense($license);
        }));
    }
    
    /**
     * Get license type category
     */
    public function getLicenseCategory(string $licenseName): string
    {
        if (stripos($licenseName, 'CC0') !== false || stripos($licenseName, 'Creative Commons 0') !== false) {
            return 'public_domain';
        }
        
        if (stripos($licenseName, 'Attribution') !== false) {
            return 'attribution';
        }
        
        return 'unknown';
    }
    
    /**
     * Validate license snapshot for compliance
     */
    public function validateSnapshot(array $licenseSnapshot): array
    {
        $issues = [];
        
        // Check if license is still safe
        $license = $licenseSnapshot['license'] ?? '';
        if (!$this->isSafeLicense($license)) {
            $issues[] = 'License is no longer safe for redistribution';
        }
        
        // Check if required fields are present
        if (empty($licenseSnapshot['name'])) {
            $issues[] = 'Missing sound name';
        }
        
        if (empty($licenseSnapshot['username'])) {
            $issues[] = 'Missing creator username';
        }
        
        return [
            'is_compliant' => empty($issues),
            'issues' => $issues,
        ];
    }
}
