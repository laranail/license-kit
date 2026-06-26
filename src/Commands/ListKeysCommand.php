<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;

class ListKeysCommand extends Command
{
    protected $signature = 'laranail::license-kit.keys.list';

    protected $description = 'List all licensing keys with their status';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:keys:list'];

    public function handle(): int
    {
        $keys = LicensingKey::orderBy('type')->orderBy('created_at', 'desc')->get();

        if ($keys->isEmpty()) {
            $this->line('No keys found.');

            return 0;
        }

        $headers = ['Type', 'KID', 'Status', 'Valid From', 'Valid Until', 'Revoked At'];
        $rows = [];

        foreach ($keys as $key) {
            $rows[] = [
                $key->type->value,
                $key->kid ?? 'N/A',
                $key->status->value,
                $key->valid_from->format('Y-m-d'),
                $key->valid_until?->format('Y-m-d') ?? 'perpetual',
                $key->revoked_at?->format('Y-m-d') ?? '-',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
