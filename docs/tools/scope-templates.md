# Scope templates

Bind specific license plans to a scope so each product only exposes the offerings it is authorised to sell (e.g. monthly, quarterly, yearly).

## Overview

`LicenseTemplate` carries the scope reference directly through the `license_scope_id` column. Leave it `null` and the template stays global and can be reused by any scope; set it and the template is bound to the indicated product.

```php
$scope = LicenseScope::create(['name' => 'Reporting Suite']);

$monthly = LicenseTemplate::create([
    'license_scope_id' => $scope->id,
    'name' => 'Monthly',
    'tier_level' => 1,
    'base_configuration' => [
        'max_usages' => 3,
        'validity_days' => 30,
    ],
]);
```

If you need to move a previously created (perhaps global) template onto a scope, use `LicenseScope::assignTemplate()` or the `TemplateService`:

```php
$scope->assignTemplate($monthly);
// or
app(TemplateService::class)->assignTemplateToScope($scope, $monthly);
```

## Retrieving a scope's templates

```php
$templates = $scope->templates()->active()->orderedByTier()->get();

// via the service, applying the default active-only filter
$templates = app(TemplateService::class)->getTemplatesForScope($scope);
```

`TemplateService::getTemplatesForScope()` returns only active templates by default; pass `false` as the second argument to include disabled ones too.

## Creating licenses from templates

Once the scope is linked to a template, you can generate consistent licenses with a one-liner:

```php
$license = $scope->createLicenseFromTemplate($monthly->slug, [
    'key_hash' => hash('sha256', 'customer-key'),
]);

// license_scope_id is assigned automatically
$license->license_scope_id === $scope->id; // true
```

`License::createFromTemplate()` automatically copies `license_scope_id`, base configuration, entitlements, and feature flags from the template.

## Removing or migrating a template

```php
$scope->removeTemplate($monthly);     // Makes the template "global" again
$scope->hasTemplate($monthly);        // false
```

To prevent the same plan from being reused across different scopes, `assignTemplate()` throws an exception if the template is already linked to a different product. In that case, duplicate the template or detach it from the original scope before proceeding.

## Design notes

- There is no dedicated pivot table: no duplicated metadata, fewer queries, less complexity.
- The former `group` column was removed in favour of `license_scope_id`. If you relied on it to group plans, use tags or custom keys in the template's `meta` field.
- Global plans remain supported: leave `license_scope_id` empty to reuse them everywhere, or clone them to specialise per product.

---

[← Docs index](../../README.md#documentation)
