<?php

declare(strict_types=1);

return [
    'check' => [
        'failed' => 'Installation check failed. Resolve the items marked FAIL above.',
        'ok' => 'Installation OK.',
    ],

    'check_expirations' => [
        'dry_grace' => '[dry-run] would transition :count active licenses to grace.',
        'dry_expired' => '[dry-run] would transition :count grace licenses to expired.',
        'dry_notify' => '[dry-run] would notify :count licenses expiring within :days days.',
        'transitioned_grace' => 'Transitioned to grace: :count',
        'transitioned_expired' => 'Transitioned to expired: :count',
        'notifications_dispatched' => 'Expiring-soon notifications dispatched: :count',
    ],

    'cleanup' => [
        'disabled' => 'Auto-revoke disabled (licensing.policies.usage_inactivity_auto_revoke_days is null).',
        'dry' => '[dry-run] would revoke :count inactive usages (>:days days).',
        'revoked' => 'Revoked :count inactive usages.',
    ],

    'export' => [
        'no_root' => 'No active root key found.',
        'no_signing' => 'No active signing keys found.',
        'pem_not_applicable' => 'PEM format is not applicable for Ed25519 keys. Using JSON format.',
    ],

    'list' => [
        'no_keys' => 'No keys found.',
    ],

    'notify' => [
        'disabled' => 'Expiring-license notifications are disabled.',
        'dry' => '[dry-run] would notify for :count expiring license(s).',
        'sent' => 'Expiring-license notifications sent for: :count',
    ],

    'revoke' => [
        'not_found' => "Key with KID ':kid' not found.",
        'already_revoked' => "Key ':kid' is already revoked.",
        'cancelled' => 'Revocation cancelled.',
        'revoked' => 'Key revoked successfully',
        'key_id' => 'Key ID: :kid',
        'reason' => 'Reason: :reason',
    ],

    'license' => [
        'not_found' => 'No license found for: :value',
        'transitioned' => 'License :uid → :status.',
        'reinstated' => 'License :uid reinstated (active).',
        'aborted' => 'Aborted.',
        'unknown_action' => 'Unknown action: :action. Use show|suspend|cancel|revoke|reinstate.',
    ],

    'make_root' => [
        'exists' => 'Active root key already exists. Use --force to replace.',
        'revoking' => 'Revoking existing root key...',
        'generating' => 'Generating root key pair...',
        'generated' => 'Root key generated successfully',
        'key_id' => 'Key ID: :kid',
        'bundle_exported' => 'Public key bundle exported to: :path',
        'backup_warning' => 'IMPORTANT: Back up your private key and passphrase securely!',
        'passphrase_unset' => 'Passphrase environment variable LICENSING_KEY_PASSPHRASE not set.',
        'passphrase_required' => 'A passphrase is required to encrypt generated keys.',
        'passphrase_empty' => 'Passphrase cannot be empty.',
        'passphrase_mismatch' => 'Passphrases do not match.',
        'passphrase_set' => 'Passphrase set for this run.',
        'passphrase_failed' => 'Failed to capture passphrase.',
    ],

    'issue_signing' => [
        'no_root' => 'No active root key found. Run licensing:keys:make-root first.',
        'scope_not_found' => 'Scope not found: :scope',
        'available_scopes' => 'Available scopes:',
        'scope_line' => '  - :slug (:name)',
        'days_positive' => 'The --days option must be a positive integer.',
        'generating' => 'Generating signing key pair...',
        'generating_rsa' => 'Generating RSA key pair',
        'creating_cert' => 'Creating certificate',
        'signing_cert' => 'Signing certificate with root key',
        'storing' => 'Storing key in keystore',
        'issued' => 'Signing key issued successfully',
        'key_id' => 'Key ID: :kid',
        'scope' => 'Scope: :name (:slug)',
        'scope_global' => 'Scope: Global',
        'valid_for' => 'Valid for: :days days',
        'failed' => 'Failed to issue signing key: :error',
    ],

    'offline_token' => [
        'args_required' => 'Both --license and --fingerprint are required.',
        'license_not_found' => 'License not found: :license',
        'no_usage' => 'No active usage found for fingerprint: :fingerprint',
        'no_signing' => 'No active signing key available',
        'signing_revoked' => 'Signing key is revoked',
        'issued' => 'Offline token issued successfully',
        'token_label' => 'Token:',
        'failed' => 'Failed to issue token: :error',
    ],

    'rotate' => [
        'invalid_reason' => 'Invalid reason. Must be "routine" or "compromised".',
        'security_immediate' => 'SECURITY: Rotating compromised key immediately...',
        'no_root' => 'No active root key found.',
        'rotating' => 'Rotating signing key...',
        'revoked' => 'Current signing key revoked',
        'issued' => 'New signing key issued',
        'key_id' => 'Key ID: :kid',
        'compromised_invalid' => 'All tokens signed with the compromised key are now invalid',
        'refresh_clients' => 'Clients must refresh their tokens immediately',
        'update_clients' => 'IMPORTANT: Update all clients immediately with the new public key bundle.',
    ],
];
