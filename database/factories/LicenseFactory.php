<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Licence\Kit\Enums\LicenseStatus;
use Simtabi\Laranail\Licence\Kit\Models\License;

class LicenseFactory extends Factory
{
    protected $model = License::class;

    public function definition(): array
    {
        return [
            'key_hash' => hash('sha256', $this->faker->uuid()),
            'status' => LicenseStatus::Pending,
            'licensable_type' => null,
            'licensable_id' => null,
            'activated_at' => null,
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'max_usages' => $this->faker->numberBetween(1, 10),
            'meta' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LicenseStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LicenseStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function withUsages(int $count): static
    {
        return $this->state(fn (array $attributes): array => [
            'max_usages' => $count,
        ]);
    }

    public function licensable(Model $model): static
    {
        return $this->state(fn (array $attributes): array => [
            'licensable_type' => $model::class,
            'licensable_id' => $model->getKey(),
        ]);
    }
}
