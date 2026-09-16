<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Kit\Commands\Command as KitCommand;
use Simtabi\Laranail\Package\Tools\Testing\AssertsDriverContract;

uses(AssertsDriverContract::class);

/**
 * An option supplied without a value (`--kid=`) arrives as `''`, not `null`, so a
 * `??` default never fires for it. `??` looks like a complete default, which is
 * what made this hard to see.
 */
it('generates a key id when --kid is written without a value', function (): void {
    // Was: `$this->option('kid') ?? 'signing-'.…` -> '' passed straight through,
    // and the empty string was persisted as the key's kid, signed with, and
    // returned. A kid is how a key is found again, so an empty one breaks rotation.
    $command = new ReflectionClass(\Simtabi\Laranail\Licence\Kit\Commands\IssueSigningKeyCommand::class);
    $source = file_get_contents((string) $command->getFileName());

    expect($source)->not->toContain("\$this->option('kid') ??")
        ->and($source)->toContain("\$this->strOption('kid') ??");
});

it('falls back to the documented default when --expiring-within is not numeric', function (): void {
    // Was: `(int) $this->option('expiring-within')` -> 0 -> addDays(0) -> an empty
    // window, reported as "no licences expiring".
    $command = new ReflectionClass(\Simtabi\Laranail\Licence\Kit\Commands\CheckExpirationsCommand::class);
    $source = file_get_contents((string) $command->getFileName());

    expect($source)->not->toContain("(int) \$this->option('expiring-within')")
        ->and($source)->toContain("intOption('expiring-within', 7)");
});

it('the base command exposes the normalising accessors', function (): void {
    foreach (['strOption', 'intOption', 'boolOption'] as $method) {
        expect(method_exists(KitCommand::class, $method))->toBeTrue("base must expose {$method}()");
    }
});

/**
 * The family-wide guard, adopted here with no file-level exemptions. The one
 * null-only test that is genuinely correct -- IssueSigningKeyCommand's `--days`
 * branch, which validates `is_numeric() || <= 0` inside and fails closed -- is
 * annotated on its own line, so every OTHER read in that file stays guarded.
 */
it('has no console option defaulted by a null-only test', function (): void {
    $this->assertNoNullOnlyOptionGuards(__DIR__ . '/../../src');
});
