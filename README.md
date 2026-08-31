# MonsterEvolution

MonsterEvolution is a MediaWiki parser extension for interactive, accessible creature-evolution graphs. Its model is a directed graph—not a recursive tree—so it supports branches, merges, fusion, cycles, reversible changes, self-loops, multiple roots, disconnected components, layered layouts, and centered radial rings.

For editor-facing syntax and examples, start with [Usage.md](Usage.md). Contributors should also read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), which describes component responsibilities, SOLID boundaries, model invariants, the progressive-enhancement contract, and safe feature-extension steps.

The extension supports MediaWiki 1.35.2 or newer and requires PHP 8.1 or newer. Compatibility with 1.35.2 is provided for existing installations, but that MediaWiki branch is end-of-life and should be upgraded before an Internet-facing production deployment. MonsterEvolution has no runtime Composer or npm dependencies and makes no external network requests. Current release information is maintained at <https://www.mediawiki.org/wiki/Release_notes>.

# Dev's note:
I want to be upfront about the whole project. It has been assist with AI, and so far it only helped with the development time and reduce the pain of making edge cases tests. The rest is still my code and the way of how I approached the extension. However I understand a lot of people's point of view with AI that it is bad, but this project wouldn't be possible without it. You can choose to included it or not, but it is not a proper replacement for the existing chart framework that already existed out there like mermaid mediawiki extension, so again I understand, but this one has focused on internal links and are targeted for wiki.gg sites, but people who are using it outside of wiki.gg are more than welcoming to use the mediawiki extension. I dont need credit, but will welcoming it. (also the Mediawiki Extension Monster Evolution = MEME. You are welcome.)


## Installation

1. Copy this directory to `extensions/MonsterEvolution/` in a MediaWiki installation.
2. Add the following to `LocalSettings.php`:

   ```php
   wfLoadExtension( 'MonsterEvolution' );
   ```

3. Run MediaWiki's normal update process after adding or upgrading extensions. MonsterEvolution creates no database tables.

## Basic syntax

The primary interface is the `<evolution>` tag:

```wiki
<evolution>
Slime -> Big Slime -> King Slime
</evolution>
```

In shorthand-only graphs, each distinct endpoint becomes a node whose display name and internal page link are the supplied text. Images are never guessed.

For maintainable graphs, declare nodes once and refer to their ASCII IDs:

```wiki
<evolution direction="left-to-right">
[node id="slime" name="Slime" image="Slime.png" link="Slime"]
[node id="big-slime" name="Big Slime" image="Big Slime.png" link="Big Slime"]
[node id="fire-slime" name="Fire Slime" image="Fire Slime.png" link="Fire Slime"]

slime -> big-slime [type="level" label="Level 10"]
big-slime -> fire-slime [type="item" label="Fire Stone"]
</evolution>
```

When any `[node]` declaration is present, every edge endpoint must be a declared ID. This intentionally catches misspellings instead of silently creating unwanted nodes.

## Nodes

Supported node attributes are:

| Attribute | Meaning |
| --- | --- |
| `id` | Required internal ID matching `[A-Za-z0-9_-]+`. |
| `name` | Required Unicode display name. Rendered as text. |
| `image` | Local MediaWiki file name, without `File:`. |
| `link` | Internal MediaWiki title. External URLs are rejected. |
| `subtitle` | Optional visible subtitle. |
| `form` | Optional visible form name. |
| `tooltip` | Optional text shown in a keyboard/touch-accessible details element. |
| `class` | Up to eight semantic tokens. Each is safely prefixed as `mw-monster-evolution-node--custom-TOKEN`. |
| `imageWidth`, `imageHeight` | Per-node thumbnail dimensions from 16 through 512 pixels. |

Names, subtitles, form names, and tooltips never accept raw HTML. Both node names and images link to `link` when it is present and valid. Page titles are resolved with MediaWiki's title and link-rendering APIs, so custom paths, namespaces, subpages, URL rewriting, and non-English titles work normally.

Images are resolved as local `File:` titles through MediaWiki's repository APIs. Values containing paths, protocols, traversal sequences, or remote URLs are rejected. A missing file leaves the graph intact and produces an accessible placeholder.

## Connections and conditions

An edge uses `source -> target`. A chain is shorthand for multiple edges:

```wiki
A -> B -> C
```

Supported edge attributes are `type`, `label`, `link`, `conditions`, `icon`, and `iconPosition`:

```wiki
slime -> moon-slime [
    type="special"
    label="Level 30 + Night + Moon Stone"
    conditions="Level >= 30; Time = Night; Item = Moon Stone"
]
```

An optional local MediaWiki file can be displayed inside the floating label:

```wiki
slime -> moon-slime [
    type="item"
    label="Moon Stone"
    icon="Moon Stone icon.png"
    iconPosition="above"
    link="Moon Stone"
]
```

`link` accepts an internal wiki title and makes the complete icon-and-text label clickable. External and executable URLs are rejected. `iconPosition` accepts `next-to` (the default) or `above`. Icons are resolved as local files and rendered as 24 × 24 thumbnails. Missing icons are omitted without removing the arrow or label. See [Usage.md](Usage.md#5-arrows-labels-links-conditions-and-icons) for icon-only labels and complete behavior.

`type` is an arbitrary validated semantic token, not a hard-coded franchise list. Common values include `level`, `item`, `friendship`, `location`, `time`, `quest`, `fusion`, `trade`, `gender`, `stat`, `skill`, `condition`, `special`, `temporary`, `form`, and `custom`. It becomes the safely prefixed class `mw-monster-evolution-edge-path--TYPE`, which administrators can style in `MediaWiki:Common.css`.

Conditions are split at semicolons or newlines. They remain available in an accessible `<details>` list while the concise label stays near the visual arrow.

## Branches, merges, and fusion

Branching requires no special syntax:

```wiki
slime -> fire-slime
slime -> water-slime
slime -> earth-slime
```

Multiple parents and fusion are ordinary graph edges:

```wiki
monster-a -> fusion-ab [type="fusion" label="Fusion"]
monster-b -> fusion-ab [type="fusion" label="Fusion"]
```

This representation preserves each contributor and works for any number of parents.

## Reversible changes, cycles, and self-loops

Use `<->` for a reversible pair:

```wiki
monster-a <-> monster-b [type="form" label="Change form"]
```

It creates two directed edges. Explicit cycles and self-loops are supported:

```wiki
A -> B
B -> C
C -> A
C -> C [type="temporary" label="Refresh transformation"]
```

All traversals track visited nodes and edges. Layout is iterative and bounded, so these graphs cannot recurse forever.

## Direction, themes, and interaction

Tag options are:

| Option | Values | Default |
| --- | --- | --- |
| `layout` | `layered`, `radial` | `layered` |
| `direction` | `left-to-right`, `right-to-left`, `top-to-bottom`, `bottom-to-top`; aliases `horizontal`, `vertical` | `left-to-right` |
| `center` | Declared node ID; required by radial layout | none |
| `radialShape` | `circle`, `polygon` | `circle` |
| `radialStart` | `top`, `right`, `bottom`, `left` | `top` |
| `theme` | `default`, `compact`, `cards`, `minimal` | `default` |
| `imageWidth`, `imageHeight` | 16–512 | 96 |
| `zoom` | `true` or `false` | `false` |
| `controls` | `true` or `false`; requires zoom | `false` |

Layout requires no editor-supplied coordinates. The bundled ResourceLoader module assigns layers, performs six bounded barycentric ordering sweeps, positions nodes, and draws SVG arrows. The viewport shrink-wraps ordinary graphs, becomes internally scrollable when a graph exceeds the available content width, and keeps its outer background transparent. Native touch scrolling provides panning; optional controls zoom, reset, or fit the graph.

Radial layout places an explicitly selected node at the center, uses shortest graph distance for successive rings, and preserves source order around each ring. Circle mode staggers alternate rings; polygon mode retains common angular spokes. See [Usage.md](Usage.md#radial-layout) for a complete Eevee example and edge-case behavior.

Selecting the target button on a node highlights all reachable previous and future paths. It works with pointer, touch, and keyboard input. `prefers-reduced-motion` disables transitions.

The default palette uses translucent dark cards and labels with white plain text, connectors, and arrowheads while leaving MediaWiki link colors unchanged. The CSS is namespaced below `mw-monster-evolution` and includes a print layout. Administrators can override all namespaced selectors in `MediaWiki:Common.css`.

## Templates and parser-function form

The tag expands templates, parser functions, and template arguments once through MediaWiki's recursive preprocessing API before MonsterEvolution tokenizes the result. MediaWiki's own expansion and recursion limits apply. Expanded output is still treated as untrusted plain syntax and text; it is never accepted as pre-rendered HTML.

```wiki
<evolution>
[node id="slime" name="{{Monster name|Slime}}" link="Slime"]
[node id="king" name="King Slime" link="King Slime"]
slime -> king [label="{{Evolution label|Level 30}}"]
</evolution>
```

A template-friendly parser function is also registered:

```wiki
{{#evolution:
A -> B -> C
|direction=top-to-bottom
|theme=compact
}}
```

Use the tag for most page content; the parser function is useful when pre-save transformation or template composition matters.

## Configuration

Set these after `wfLoadExtension()` in `LocalSettings.php`:

| Setting | Default | Valid range or values |
| --- | ---: | --- |
| `$wgMonsterEvolutionDefaultDirection` | `left-to-right` | Four canonical directions |
| `$wgMonsterEvolutionDefaultTheme` | `default` | Four built-in themes |
| `$wgMonsterEvolutionDefaultImageWidth` | `96` | 16–512 |
| `$wgMonsterEvolutionDefaultImageHeight` | `96` | 16–512 |
| `$wgMonsterEvolutionMaxInputBytes` | `131072` | 1 KiB–1 MiB |
| `$wgMonsterEvolutionMaxNodes` | `250` | 1–1000 |
| `$wgMonsterEvolutionMaxEdges` | `500` | 1–4000 |
| `$wgMonsterEvolutionMaxConditionsPerEdge` | `20` | 0–100 |
| `$wgMonsterEvolutionMaxAttributes` | `32` | 1–64 |
| `$wgMonsterEvolutionMaxValueLength` | `4096` | 1–16384 Unicode characters |
| `$wgMonsterEvolutionMaxNodeIdLength` | `128` | 1–128 ASCII characters |
| `$wgMonsterEvolutionMaxGraphsPerPage` | `20` | 1–100 |
| `$wgMonsterEvolutionEnableZoom` | `true` | Boolean administrator gate |
| `$wgMonsterEvolutionMissingImage` | empty | Optional local file name |
| `$wgMonsterEvolutionEnableTrackingCategory` | `true` | Add error tracking category |
| `$wgMonsterEvolutionEnableMissingPageTrackingCategory` | `true` | Track graphs containing missing or invalid internal links |

Configuration is validated during extension registration. Invalid numeric ranges or enumerations stop startup with a concise configuration error rather than causing unpredictable runtime behavior.

When missing-page tracking is enabled, a page containing a red or unresolvable
node/arrow-label link is added to `Category:Pages with missing Monster Evolution
links`. Set the option to `false` after `wfLoadExtension()` to disable this category:

```php
$wgMonsterEvolutionEnableMissingPageTrackingCategory = false;
```

## Accessibility and progressive enhancement

Without JavaScript, linked monster cards appear in source order and every relationship remains in a semantic list. JavaScript enhances that markup with automatic placement and decorative SVG arrows; SVG is hidden from assistive technology. Image alternative text, visible names, keyboard-accessible links and controls, non-hover details, high-contrast focus indicators, bidirectional text support, reduced motion, and a print fallback are built in.

## Security architecture

The trust path is deliberately explicit:

```text
attacker-controlled wikitext
  -> bounded tokenizer
  -> attribute/type validation
  -> typed EvolutionGraph
  -> MediaWiki title/file resolution
  -> Html and LinkRenderer output encoding
  -> ResourceLoader-enhanced DOM
```

- Every wikitext value is untrusted, including template-expanded values. Attribute names are whitelisted.
- IDs and semantic class/type tokens use separate restrictive ASCII grammars. Display text supports Unicode.
- HTML, CSS, script, event-handler, URL, filesystem-path, and arbitrary HTML attributes are never accepted.
- Internal links use `TitleFactory` and `LinkRenderer`. Images use `TitleFactory`, `RepoGroup`, and MediaWiki thumbnail transforms. No value becomes a URL, filesystem path, or network request.
- Output is created with MediaWiki's HTML helpers. User text is never passed as already-safe HTML. Client code uses `textContent`, `cloneNode`, `Map`, `Set`, numeric indexes, scoped selectors, and no `eval`, `innerHTML`, or global graph objects. Edge-label links and icons clone only MediaWiki-rendered anchors and local thumbnails; raw wikitext never becomes a URL.
- Input, node, edge, condition, attribute, value, ID, and per-page graph limits are checked before or during bounded processing. The client layout is iterative with a fixed number of ordering sweeps.
- Parser output is deterministic and uses normal link/file dependency registration. No cookies, request parameters, permissions, timestamps, random server IDs, or user-specific state affect cached HTML.
- The extension is read-only: it defines no API write module, form submission, database table, privileged action, shell command, remote fetch, telemetry, or secret. Any future state-changing feature must use MediaWiki authorization and CSRF tokens server-side.
- Runtime code has no third-party dependencies or CDN resources. Development dependencies are pinned and audited in CI.

See [SECURITY.md](SECURITY.md), [docs/SECURITY-REVIEW.md](docs/SECURITY-REVIEW.md), and the recorded [verification results](docs/VERIFICATION.md).

## Error handling and troubleshooting

Malformed definitions produce localized, escaped errors with source lines when available. Pages can be found through `Category:Pages with Monster Evolution errors` unless tracking is disabled.

- “Unknown node”: a declaration exists, so the endpoint must match a declared ID exactly.
- “Unknown attribute”: attributes are strict and case-insensitive; check the tables above.
- “Invalid image”: supply a local file title without `File:`, a URL, or path separators.
- No graphical arrows: confirm ResourceLoader is working. Cards and the relationship list remain usable without JavaScript.
- Missing thumbnail: confirm the file exists in MediaWiki's local or configured shared repository.

## Demo

The [`demo`](demo) directory contains generic and franchise-themed charts for branching, merging, conditions, icon labels, multiple roots, level chains, item requirements, stat requirements, and fusion. No third-party artwork is bundled.

## Development and testing

Run the extension inside a MediaWiki checkout:

```console
composer test
composer phan
php tests/phpunit/phpunit.php extensions/MonsterEvolution/tests/phpunit
npm test
```

`composer test` runs PHP parallel lint, MediaWiki CodeSniffer, and executable-bit checks. `composer phan` includes MediaWiki's taint-check plugin. `npm test` performs JavaScript syntax and security-invariant checks without downloading runtime libraries. The PHP test suites cover parsing, graph limits, cycles, Unicode, hostile payloads, escaped rendering, internal links, and missing files.

The CI JavaScript job also opens [`tests/browser/edge-cases.html`](tests/browser/edge-cases.html) in headless Chrome. Its dependency-free browser harness checks every layered direction, centered circles, polygon and multi-ring placement, narrow-container scrolling, transparent/dark theme defaults, white connector contrast, controls, highlighting, long text, disconnected components, malformed client metadata, cycles, reciprocal paths, parallel transitions, and repeated self-loops. Open that fixture in a browser for visual review while changing layout or CSS.

The parser and renderer use stable modern interfaces documented in MediaWiki's tag-extension, `extension.json`, ParserOutput, LinkRenderer, and file-repository references. No deprecated parser-test manifest registration is used.

### Automated releases

The GitHub Actions workflow runs every verification job before its release job. A successful push to the repository's default branch publishes automatically; `workflow_dispatch` can publish a manually selected commit. Pull requests and scheduled runs verify the extension but never create releases.

The release version starts with the numeric `MAJOR.MINOR.PATCH` value in `package.json`, which must match `extension.json`. If that tag already exists, the workflow increments the patch number until it finds the first unused tag. The generated tag points to the tested commit, or to a release-only child commit containing the incremented version metadata; it does not push that generated version commit onto the source branch. Each GitHub release contains `MonsterEvolution-VERSION.zip` and its SHA-256 checksum, and the same files remain available as a workflow artifact.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
