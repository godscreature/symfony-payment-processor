# 006. Keep `OpenApi\Attributes` despite the deprecation

Date: 2026-11-24 · Status: Accepted

## Context

swagger-php 7 deprecated every class in `OpenApi\Attributes` (removed in 8.0) in favour of `OpenApi\Spec`.
nelmio/api-doc-bundle 5.14, the latest version, reads controller attributes only as
`OpenApi\Annotations\AbstractAnnotation`; the new `Spec` classes do not extend it, so nelmio ignores them.

## Decision

Keep the classic attributes. They work unchanged through swagger-php 7.x. Migrate when nelmio supports
`Spec`, using the Rector rules shipped with swagger-php (`rector/set/classic-to-spec.php`).

## Consequences

- PhpStorm shows deprecation warnings on OpenAPI attributes; they are expected.
- Tracked in [docs/tech_debt/README.md](../tech_debt/README.md).
