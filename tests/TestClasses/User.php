<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Tests\TestClasses;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Simtabi\Laranail\Licence\Kit\Contracts\CanInitiateLicenseTransfers;
use Simtabi\Laranail\Licence\Kit\Contracts\CanReceiveLicenseTransfers;
use Simtabi\Laranail\Licence\Kit\Models\License;

class User extends Authenticatable implements CanInitiateLicenseTransfers, CanReceiveLicenseTransfers
{
    protected $fillable = ['name', 'email'];

    protected $table = 'users';

    public $timestamps = true;

    public function licenses()
    {
        return $this->morphMany(License::class, 'licensable');
    }

    public function canReceiveLicenseTransfers(): bool
    {
        return true;
    }

    public function getMaxLicenseLimit(): ?int
    {
        return 10;
    }

    public function getActiveLicenseCount(): int
    {
        return $this->licenses()->where('status', 'active')->count();
    }

    public function hasReachedLicenseLimit(): bool
    {
        $limit = $this->getMaxLicenseLimit();

        return $limit !== null && $this->getActiveLicenseCount() >= $limit;
    }

    public function canInitiateLicenseTransfer(License $license): bool
    {
        return $this->ownsLicense($license);
    }

    public function ownsLicense(License $license): bool
    {
        return $license->licensable_type === static::class &&
               $license->licensable_id === $this->id;
    }

    public function getLicenseRole(License $license): ?string
    {
        return $this->ownsLicense($license) ? 'owner' : null;
    }

    public function hasPermission(string $permission): bool
    {
        return property_exists($this, 'hasPermission') && is_callable($this->hasPermission)
            ? call_user_func($this->hasPermission, $permission)
            : false;
    }
}
