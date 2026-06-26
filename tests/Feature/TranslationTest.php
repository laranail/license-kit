<?php

declare(strict_types=1);

it('registers the license-kit translation namespace', function (): void {
    expect(__('license-kit::license-kit.check.ok'))->toBe('Installation OK.')
        ->and(__('license-kit::license-kit.rotate.key_id', ['kid' => 'k-1']))->toBe('Key ID: k-1')
        ->and(__('license-kit::license-kit.list.no_keys'))->toBe('No keys found.');
});
