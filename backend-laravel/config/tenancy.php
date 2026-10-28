<?php

$domains = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $domain): string => rtrim(strtolower(trim($domain)), '.'),
    explode(',', $value),
)));

return [
    'admin_base_domain' => ($domain = trim((string) env('ADMIN_BASE_DOMAIN', ''))) === ''
        ? null
        : rtrim(strtolower($domain), '.'),
    'storefront_base_domain' => ($domain = trim((string) env('STOREFRONT_BASE_DOMAIN', ''))) === ''
        ? null
        : rtrim(strtolower($domain), '.'),
    'custom_domain_allowlist' => $domains((string) env('CUSTOM_DOMAIN_ALLOWLIST', '')),
    'trusted_proxies' => $domains((string) env('TRUSTED_PROXIES', '')),
    'lifecycle_cli_enabled' => (bool) env('TENANT_LIFECYCLE_CLI_ENABLED', false),
    'cli_operator_ref' => trim((string) env('TENANT_CLI_OPERATOR_REF', '')),
];
