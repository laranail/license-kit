<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Commands;

use Simtabi\Laranail\Licence\Kit\Models\LicensingKey;

class MakeRootKeyCommand extends Command
{
    protected $signature = 'laranail::license-kit.keys.make-root {--force : Force creation even if root exists} {--silent : Do not prompt for missing passphrase}';

    protected $description = 'Generate a new root key pair';

    /** @var list<string> */
    protected array $commandAliases = ['licensing:keys:make-root'];

    public function handle(): int
    {
        $existingRoot = LicensingKey::findActiveRoot();

        if ($existingRoot instanceof LicensingKey) {
            if (! $this->option('force')) {
                $this->line(__('license-kit::license-kit.make_root.exists'));

                return 1;
            }

            if (! $this->confirm('This will revoke the existing root key. Continue?')) {
                return 0;
            }

            $this->line(__('license-kit::license-kit.make_root.revoking'));
            $existingRoot->revoke('replaced');
        }

        if (! $this->ensurePassphrase()) {
            return 3;
        }

        $this->line(__('license-kit::license-kit.make_root.generating'));

        $rootKey = LicensingKey::generateRootKey();

        $this->line(__('license-kit::license-kit.make_root.generated'));
        $this->line(__('license-kit::license-kit.make_root.key_id', ['kid' => $rootKey->kid]));
        $this->line(__('license-kit::license-kit.make_root.bundle_exported', ['path' => $this->getPublicBundlePath()]));
        $this->line(__('license-kit::license-kit.make_root.backup_warning'));

        return 0;
    }

    private function getPublicBundlePath(): string
    {
        return config('licensing.publishing.public_bundle_path', storage_path('app/licensing/public-bundle.json'));
    }

    private function ensurePassphrase(): bool
    {
        $passphrase = config('licensing.crypto.keystore.passphrase');

        if ($passphrase) {
            LicensingKey::cachePassphrase($passphrase);

            return true;
        }

        $isSilent = (bool) $this->option('silent');

        if ($this->input->hasOption('no-interaction')) {
            $isSilent = $isSilent || (bool) $this->input->getOption('no-interaction');
        }

        if ($isSilent) {
            return false;
        }

        $this->line(__('license-kit::license-kit.make_root.passphrase_unset'));
        $this->line(__('license-kit::license-kit.make_root.passphrase_required'));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $passphrase = (string) $this->secret('Create a new passphrase');

            if ($passphrase === '') {
                $this->line(__('license-kit::license-kit.make_root.passphrase_empty'));

                continue;
            }

            $confirmation = (string) $this->secret('Confirm passphrase');

            if ($passphrase !== $confirmation) {
                $this->line(__('license-kit::license-kit.make_root.passphrase_mismatch'));

                continue;
            }

            config()->set('licensing.crypto.keystore.passphrase', $passphrase);
            LicensingKey::cachePassphrase($passphrase);

            $this->line(__('license-kit::license-kit.make_root.passphrase_set'));

            return true;
        }

        $this->line(__('license-kit::license-kit.make_root.passphrase_failed'));

        return false;
    }
}
