# Security Policy

Submit reports through [this package's private vulnerability reporting form](https://github.com/nvl-laravel-suite/pages/security/advisories/new).

Security reports should be sent privately to the package maintainers. Do not disclose an unresolved issue publicly.

Supported stable releases receive security fixes. Reports should include the affected release, configuration, reproduction, expected behavior, and impact without real private data.

Pages fail closed for mutations, handler registration, resource parameters, unsafe paths, stale revisions, and hierarchy violations. The package registers no HTTP routes. Applications remain responsible for binding authorization, constraining every handler query, returning sanitized resource projections, securing their own routes, and configuring Content, SEO, and Metafields policies.
