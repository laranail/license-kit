# Performance

This guide covers performance optimization techniques for the License Kit package in high-load environments.

## Database optimization

### Essential indexes

```sql
-- License table indexes
CREATE INDEX idx_licenses_status ON licenses (status);
CREATE INDEX idx_licenses_expires_at ON licenses (expires_at);
CREATE UNIQUE INDEX idx_licenses_key_hash ON licenses (key_hash);
CREATE INDEX idx_licenses_licensable ON licenses (licensable_type, licensable_id);

-- Usage table indexes  
CREATE UNIQUE INDEX idx_usages_license_fingerprint ON license_usages (license_id, usage_fingerprint);
CREATE INDEX idx_usages_last_seen ON license_usages (last_seen_at);
CREATE INDEX idx_usages_status ON license_usages (status);
```

### Query optimization

```php
// Efficient license validation query
class OptimizedLicenseValidator
{
    public function validateLicense(string $licenseKey, string $fingerprint): array
    {
        // Single query with joins to get all needed data
        $result = DB::table('licenses')
            ->select([
                'licenses.*',
                'usage.id as usage_id',
                'usage.status as usage_status',
                'usage.last_seen_at',
            ])
            ->leftJoin('license_usages as usage', function($join) use ($fingerprint) {
                $join->on('licenses.id', '=', 'usage.license_id')
                     ->where('usage.usage_fingerprint', $fingerprint)
                     ->where('usage.status', 'active');
            })
            ->where('licenses.key_hash', License::hashKey($licenseKey))
            ->first();
            
        return $this->processValidationResult($result);
    }
}
```

## Caching strategies

### License caching

```php
class CachedLicenseService
{
    private const CACHE_TTL = 300; // 5 minutes
    
    public function findByKey(string $key): ?License
    {
        $keyHash = License::hashKey($key);
        
        return Cache::remember(
            "license:{$keyHash}",
            self::CACHE_TTL,
            fn() => License::where('key_hash', $keyHash)->first()
        );
    }
    
    public function invalidateLicense(License $license): void
    {
        Cache::forget("license:{$license->key_hash}");
        Cache::tags(['license:' . $license->id])->flush();
    }
}
```

### Token verification caching

```php
class CachedTokenVerifier implements TokenVerifier
{
    public function verify(string $token): array
    {
        $tokenHash = hash('sha256', $token);
        
        // Cache valid tokens for their remaining lifetime
        return Cache::remember(
            "token_claims:{$tokenHash}",
            now()->addMinutes(5),
            function() use ($token) {
                $claims = $this->baseVerifier->verify($token);
                
                // Only cache if token has significant time left
                $expiresIn = $claims['exp'] - time();
                if ($expiresIn < 300) { // Less than 5 minutes
                    Cache::put(
                        "token_claims:" . hash('sha256', $token),
                        $claims,
                        min($expiresIn, 300)
                    );
                }
                
                return $claims;
            }
        );
    }
}
```

## Connection pooling

The package uses the default database connection. To pool connections, configure
`PDO::ATTR_PERSISTENT` on your existing connection in `config/database.php`.

## Load testing

```php
// Performance test example
class LicensingLoadTest
{
    public function testValidationThroughput()
    {
        $startTime = microtime(true);
        $iterations = 1000;
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->validateRandomLicense();
        }
        
        $duration = microtime(true) - $startTime;
        $throughput = $iterations / $duration;
        
        $this->assertGreaterThan(100, $throughput, 'Should handle 100+ validations/second');
    }
}
```

Performance optimization focuses on database efficiency, strategic caching, and proper indexing to handle high-volume licensing operations.

---

[← Docs index](../README.md#documentation)
