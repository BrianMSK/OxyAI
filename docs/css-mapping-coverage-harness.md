# CSS Mapping Coverage Harness

OxyAI CSS mapping must be driven from the actual Oxygen and Breakdance element
contracts, not from guessed property paths. The source of truth is the installed
plugin source:

- Oxygen core: `oxygen-6.1.0-beta.4/oxygen`
- Breakdance Elements for Oxygen: `breakdance-elements-for-oxygen/elements`
- Breakdance Forms for Oxygen: `breakdance-forms-for-oxygen/elements`

The real-source smoke test accepts reviewer-local source paths through:

- `OXYAI_OXYGEN_CORE_DIR`
- `OXYAI_BREAKDANCE_ELEMENTS_DIR`
- `OXYAI_BREAKDANCE_FORMS_DIR`

When those environment variables are not set the gate skips cleanly so it stays
portable across reviewer machines.

## Breakdance Inventory

Generate a static contract inventory from downloaded Breakdance element sources:

```powershell
php tools/css-mapping/extract-breakdance-contracts.php `
  "<path-to-breakdance-elements>" `
  "<path-to-breakdance-forms>"
```

The inventory extracts, per element:

- element class, display name, CSS class name, category, and availability
- `controlSlugs` from `element.php`
- `design.*` and `content.*` paths used by `css.twig`
- direct CSS declarations from `css.twig`, including selector, CSS property,
  design/content paths used in the value, and whether the property is a CSS
  custom property
- `macros.*(...)` CSS calls from `css.twig` with referenced design/content
  paths
- `design.*` and `content.*` paths used by `html.twig`
- selectors from `default.css`

This tells us which properties actually affect rendered CSS/HTML. Mapper work
should target these paths first, then prove each path compiles with a fixture.
Twig aliases such as `{% set s = design.cart.container.padding %}` are expanded
so declaration rows still point back to their source `design.*` path.

Summarize the inventory into a reviewable coverage matrix:

```powershell
php tools/css-mapping/extract-breakdance-contracts.php `
  "<path-to-breakdance-elements>" `
  "<path-to-breakdance-forms>" `
  | php tools/css-mapping/summarize-breakdance-contracts.php --summary-only -
```

On the current downloaded Breakdance Elements + Forms sources this finds:

- 134 elements
- 1086 unique `design.*` paths referenced by `css.twig`
- 1568 direct CSS declarations across 226 unique CSS property names
- 652 CSS macro calls across 52 macro names
- 0 direct CSS property names are still `unknown-css-property`
- 0 CSS macro names are still `unknown-css-macro`
- 195 paths classified as `native-shared-mapper`
- 50 paths classified as `native-with-guardrails`
- 104 paths classified as `content-or-render-runtime`
- 36 paths classified as `requires-css-fallback`
- 701 paths classified as `element-specific-contract`
- 0 paths still classified as `needs-element-specific-mapper`

These are source-contract counts, not a claim that CSS fallback is safe to strip.
Only paths with JSON-shape tests and compile proof should be marked strip-safe.

Validate the same inventory against the explicit coverage manifest:

```powershell
php tools/css-mapping/extract-breakdance-contracts.php `
  "<path-to-breakdance-elements>" `
  "<path-to-breakdance-forms>" `
  | php tools/css-mapping/validate-breakdance-coverage.php --summary-only `
      config/css-mapping/breakdance-coverage-manifest.json -
```

Current Breakdance Elements + Forms manifest result:

- `isComplete`: true
- 0 unique `css.twig` design paths are `uncovered`
- 701 unique paths are classified as `element-specific-contract`
- 0 unique paths are classified as `needs-element-specific-mapper`
- 0 rules are marked strip-safe without proof
- 0 unique direct CSS declaration properties are unknown to the shared
  mapper/property coverage manifest
- 0 CSS macro names are unknown to the macro coverage manifest

The manifest is intentionally conservative. To move a path out of `uncovered`,
add an explicit rule with the native target path or fallback reason. To mark any
rule `stripSafe=true`, include both JSON-shape and compiled-CSS proof metadata.
To keep the CSS property gate at zero unknowns, every direct CSS declaration
property must stay classified as native shared mapper, explicit fallback-only,
or runtime/custom-property behavior in `cssDeclarationPropertyCoverage`.
To keep the macro gate at zero unknowns, every `macros.*(...)` call in
`css.twig` must stay classified as a shared mapper family, element-specific
macro contract, or fallback/runtime contract in `cssMacroCoverage`.

Validate the manifest structure itself before trusting a green source gate:

```powershell
php tools/css-mapping/validate-coverage-manifest.php `
  config/css-mapping/breakdance-coverage-manifest.json
```

This rejects malformed match modes, unknown statuses, missing `stripSafe`
booleans, strip-safe rules without proof metadata, and reviewed
`element-specific-contract` rules without a reason.

Cluster mapper work into reviewable buckets:

```powershell
php tools/css-mapping/extract-breakdance-contracts.php `
  "<path-to-breakdance-elements>" `
  "<path-to-breakdance-forms>" `
  | php tools/css-mapping/validate-breakdance-coverage.php `
      config/css-mapping/breakdance-coverage-manifest.json - `
  | php tools/css-mapping/cluster-coverage-gaps.php -
```

The current Breakdance Elements + Forms source inventory has no remaining
`needs-element-specific-mapper` buckets. Historical high-volume buckets such as
Mini Cart, Pricing Table, Product Images, Posts List, Popup, Scroll Progress,
Countdown Timer, Header Builder, Back To Top, testimonials, Gallery, Table of
Contents, and image components are now explicit element-specific contracts.

Render the same validator output as a PR review gate:

```powershell
php tools/css-mapping/extract-breakdance-contracts.php `
  "<path-to-breakdance-elements>" `
  "<path-to-breakdance-forms>" `
  | php tools/css-mapping/validate-breakdance-coverage.php `
      config/css-mapping/breakdance-coverage-manifest.json - `
  | php tools/css-mapping/coverage-review-report.php -
```

This Markdown report is the reviewer-facing gate. It must say `Merge gate:
PASS` before the CSS mapping work can be called complete for the 100% coverage
objective. A `FAIL` with zero uncovered paths means either a new
`needs-element-specific-mapper` row appeared or another gate such as
strip-safe proof failed.

Run the combined real-source gate when reviewing both Breakdance/Forms and
Oxygen core coverage together:

```powershell
php tools/css-mapping/real-source-coverage-gate.php
```

For CI or other automation, use:

```powershell
php tools/css-mapping/real-source-coverage-gate.php --json
```

This uses the same `OXYAI_OXYGEN_CORE_DIR`,
`OXYAI_BREAKDANCE_ELEMENTS_DIR`, and `OXYAI_BREAKDANCE_FORMS_DIR` environment
variables as the smoke test and prints either a compact Markdown PASS/FAIL
table or machine-readable JSON for both inventories.

Generate the element-specific contract backlog from the same full validator
output:

```powershell
php tools/css-mapping/extract-breakdance-contracts.php `
  "<path-to-breakdance-elements>" `
  "<path-to-breakdance-forms>" `
  | php tools/css-mapping/validate-breakdance-coverage.php `
      config/css-mapping/breakdance-coverage-manifest.json - `
  | php tools/css-mapping/element-contract-backlog.php -
```

The backlog groups remaining gaps by element and attaches the direct CSS
declarations or `macros.*(...)` calls that touch each gap path. On the current
source inventory it produces 0 remaining element contracts. If a future plugin
update introduces new design paths, this command becomes the working queue for
reviewing and classifying the new element-specific mapper contracts.

Render the top backlog items as Markdown for PR review:

```powershell
php tools/css-mapping/extract-breakdance-contracts.php `
  "<path-to-breakdance-elements>" `
  "<path-to-breakdance-forms>" `
  | php tools/css-mapping/validate-breakdance-coverage.php `
      config/css-mapping/breakdance-coverage-manifest.json - `
  | php tools/css-mapping/element-contract-backlog.php - `
  | php tools/css-mapping/element-contract-report.php --limit=10 -
```

Use this report in PRs to review the next mapper contracts without pasting the
full JSON backlog. It includes top gap paths, the declarations/macros that
touch them, and the required proof checklist.

## OxyDance Comparison

OxyDance Pilot's stronger behavior is selector-first rather than inline
element-property-first. It commits generated class selectors into the builder
selector store, maps selector property paths for Oxygen mode, and keeps element
classes attached to the Oxygen selector UUIDs.

Extract its selector mapping table:

```powershell
php tools/css-mapping/extract-oxydance-selector-map.php `
  "<path-to-oxydance-builder-ai.js>"
```

See `docs/oxydance-css-mapping-findings.md` for the concrete path mappings and
normalizers that should inform the OxyAI selector mapper.

The current OxyAI selector registration layer now applies the safe subset of
that comparison before persisting Oxygen selectors:

- path remaps for `layout.align_items`, `layout.justify_content`, `layout.gap`,
  `spacing.padding`, `spacing.margin`, `background.color`, `borders.border`,
  and `borders.radius`
- quoted font-family cleanup
- opacity normalization from CSS scale `0..1` to Oxygen selector scale `0..100`
- `%%SELECTOR%%` custom CSS token rewrite to `:selector`
- `layout.flex_wrap` merge into `layout.flex_direction` when both values are
  available

This improves editor-visible selector data only. It does not make CSS fallback
strip-safe by itself.

## Oxygen Core Inventory

Generate a static inventory from the Oxygen 6 core element source:

```powershell
php tools/css-mapping/extract-oxygen-core-contracts.php `
  "<path-to-oxygen-core>"
```

The Oxygen extractor reads:

- `subplugins/oxygen-elements/elements/*/element.php`
- element-local `css.twig`, `html.twig`, and `default.css`
- universal control sources such as `classes-selectors/controls.php` and
  `plugin/elements/elements-helpers.php`
- `cssProperty` to `affectedPropertyPath` spacing-bar mappings
- `propertyPathsToWhitelistInFlatProps` and
  `propertyPathsToSsrElementWhenValueChanges`

Validate Oxygen core against the same coverage manifest:

```powershell
php tools/css-mapping/extract-oxygen-core-contracts.php `
  "<path-to-oxygen-core>" `
  | php tools/css-mapping/validate-breakdance-coverage.php --summary-only `
      config/css-mapping/breakdance-coverage-manifest.json -
```

Current Oxygen core manifest result:

- `isComplete`: true
- 21 Oxygen core elements
- 30 unique `css.twig` or universal design paths
- 0 paths are `uncovered`
- 0 paths are `needs-element-specific-mapper`
- 0 rules are marked strip-safe without proof

## Current Guardrails

The first coverage guard covers the Referenzen failure class:

- `container.margin.left/right = "auto"` on `EssentialElements\Columns` or
  `EssentialElements\Column` is a dead write. OxyAI suppresses it and keeps CSS
  fallback eligibility.
- `layout.justify_content` on `EssentialElements\Columns` is a dead write.
  OxyAI suppresses it.
- `EssentialElements\Column` alignment must be written as the full
  `align_items + align + vertical_align` bundle.
- The audit scans converted output for those dead writes so direct JSON authors
  see the issue before testing the frontend manually.

## Next Harness Steps

1. Add a generated fixture snapshot under `tests/fixtures/breakdance-contracts`
   from the inventory script.
2. Build a mapper coverage matrix:
   CSS declaration -> target element -> native property path -> compile proof.
3. For each `css.twig` path, add one of:
   `mapped`, `content-only`, `dynamic-only`, `requires-css-fallback`, or
   `unsupported-with-warning`.
4. Gate new mapper entries with smoke tests that assert both the JSON shape and
   the audit behavior. For high-risk paths, verify against compiled CSS on a
   live Oxygen page before marking them strip-safe.
