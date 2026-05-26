# OxyDance CSS Mapping Findings

OxyDance Pilot does not try to turn every visual declaration into inline element
design properties. Its stronger path is selector-first:

- keep generated class selectors as the visual source of truth
- commit selectors directly into Breakdance `global.cssSelectors`
- in Oxygen mode, transform selector properties into `oxySelectors`
- attach Oxygen selector UUIDs back to element `meta.classes`
- keep original class names on `settings.advanced.classes`

## Relevant OxyDance Mechanics

`assets/js/builder-ai.js` contains the key behavior:

- `directCommitSelectors()` writes class selectors into Breakdance's Vuex store.
- `injectOxygenSelectors()` writes transformed selector properties into
  `global.oxySelectors` and creates an `OxyDance` selector collection.
- `transformElementForOxygen()` preserves class names and adds
  `properties.meta.classes` UUIDs so Oxygen can connect elements to
  `oxySelectors`.
- `transformOxygenSelectorProperties()` extracts breakpoint-keyed tuples,
  remaps Breakdance/converter paths to Oxygen selector schema paths, and
  performs post-processing.

The extracted OxyDance selector map currently has 31 direct/post-process path
mappings. Generate it with:

```powershell
php tools/css-mapping/extract-oxydance-selector-map.php `
  "C:\Users\Denis\Downloads\oxydance-pilot-2.0.0\oxydance-pilot\assets\js\builder-ai.js"
```

## Mapping Ideas To Port

These are concrete improvements for OxyAI's converter, independent of
element-specific Breakdance widget work:

- Build a selector-property normalization layer separate from element inline
  design properties.
- Treat class selector CSS as the fidelity source until a path is proven
  strip-safe.
- Add an Oxygen selector schema remapper for known path mismatches:
  `layout.align_items -> layout.flex_align.cross_axis`,
  `layout.justify_content -> layout.flex_align.primary_axis`,
  `layout.gap -> layout.gap.row/column`,
  `background.color -> background.background_color`,
  `borders.border -> borders.borders`,
  `borders.radius -> borders.border_radius`,
  `spacing.padding -> spacing.spacing.padding`,
  `spacing.margin -> spacing.spacing.margin`.
- Add post-process normalizers for:
  box shadows, background layers, flex wrap, transitions, opacity scale, and
  custom CSS selector tokens.
- Keep Breakdance/Oxygen selector store writes and element class attachment as
  first-class verified behavior, not a fallback detail.

## Why This Matters

The current OxyAI failure mode is trying to make element properties native too
early. OxyDance's approach accepts that selector CSS is the most robust carrier
for arbitrary HTML/CSS generation, then improves editability by registering
selectors into the builder's native selector system.

For the 100% coverage target, OxyAI should use two tracks:

1. Selector coverage: every supported CSS property gets a verified selector
   schema path or explicit CssCode fallback.
2. Element-specific coverage: widget internals such as Mini Cart, Pricing Table,
   Product Images, Popups, and Loop cards get dedicated mappers only after their
   element contract and compiled CSS proof are documented.
