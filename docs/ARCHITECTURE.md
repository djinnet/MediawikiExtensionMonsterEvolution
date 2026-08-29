# MonsterEvolution architecture

This document explains why the codebase is divided the way it is. It is intended for contributors who need to change behavior without accidentally weakening parsing limits, output escaping, MediaWiki compatibility, accessibility, or progressive enhancement.

## Request-to-screen data flow

```text
wikitext
  -> ParserHooks (MediaWiki lifecycle and preprocessing)
  -> EvolutionTokenizer (bounded lexical structure)
  -> EvolutionValueValidator (scalar allow-lists and limits)
  -> EvolutionParser (grammar, endpoint resolution, graph construction)
  -> EvolutionGraph / Node / Edge / Condition (plain immutable model values)
  -> EvolutionRenderer (MediaWiki HTML, links, thumbnails, dependencies)
  -> server HTML with a semantic relationship list
  -> evolution.js (bounded layout, SVG decoration, controls)
  -> evolution.css (namespaced presentation and fallbacks)
```

Every arrow in this diagram is a trust boundary or a change boundary. Keep transformations one-way: client layout must not become a second wikitext parser, model objects must not contain HTML, and validation must not depend on CSS or DOM behavior.

## How SOLID is applied

### Single responsibility

- `ParserHooks` adapts MediaWiki entry points and error presentation.
- `EvolutionTokenizer` understands quotes, escapes, brackets, statements, and source lines.
- `EvolutionValueValidator` owns scalar security policy.
- `EvolutionParser` understands the evolution grammar and constructs a graph.
- Model classes represent data only.
- `EvolutionRenderer` creates progressive-enhancement markup and registers MediaWiki dependencies.
- The resolver adapters translate model titles into MediaWiki objects.
- The client lays out already-rendered data and owns interaction only.

The rule of thumb is to place code with the policy that causes it to change. For example, accepting a new edge attribute belongs in parsing and the model; deciding its HTML belongs in rendering; deciding its spatial arrangement belongs in the client.

### Open/closed

Edge `type` and node `class` values are safe semantic tokens rather than closed franchise-specific enumerations. Administrators can add visual variants in CSS without changing the parser. Built-in graph directions and themes remain closed allow-lists because they select actual layout behavior.

### Liskov substitution

The codebase favors composition and final value classes over inheritance hierarchies. Resolver interfaces have one precise behavioral contract: a valid input either resolves to the promised MediaWiki abstraction or returns `null`. Implementations must not return external titles or nonexistent files.

### Interface segregation

`EvolutionFileResolver` and `EvolutionLinkResolver` expose one operation each. The renderer is not coupled to the much larger `RepoGroup`, `TitleFactory`, or service container APIs.

### Dependency inversion

`EvolutionRenderer` depends on resolver contracts. `ServiceWiring.php` is the composition root that selects MediaWiki-backed adapters. Runtime classes do not fetch global services themselves.

## Model invariants

- Node IDs are unique ASCII tokens and graph edges refer only to existing IDs.
- Display text is valid bounded UTF-8 plain text.
- Image and icon fields are local file-title text, never URLs or paths.
- Node and edge-label link fields are internal title text, never executable or external URLs.
- Semantic types/classes match a restrictive token grammar before becoming class suffixes.
- Directions, themes, icon positions, dimensions, and Booleans are normalized before entering the model.
- Node, edge, attribute, condition, value, input-byte, and per-page graph limits are enforced before unbounded growth.

`EvolutionGraph` checks duplicate IDs itself as defense in depth, even though `EvolutionParser` normally detects them first.

## Progressive enhancement contract

The server output is the accessible source of truth. It contains cards in source order and a textual list of every relationship and condition. JavaScript adds positions, SVG paths, floating labels, zoom, and highlighting. If JavaScript fails, content remains readable.

SVG is decorative and `aria-hidden`. The semantic relationship list is visually hidden only after enhancement succeeds. This ordering is important: never hide fallback content solely because the document has a `client-js` class.

## Edge label icon and link trust path

```text
icon="Fire Stone.png"
  -> LocalFileNamePolicy
  -> EvolutionEdge.icon
  -> EvolutionFileResolver
  -> File::transform(24 × 24)
  -> server-rendered <img>

link="Fire Stone"
  -> EvolutionValueValidator
  -> EvolutionLinkResolver
  -> LinkRenderer
  -> server-rendered <a> containing the optional <img> and label text
  -> client cloneNode() into the floating label
```

The client never receives a raw icon or link URL attribute. This design lets MediaWiki apply title, repository, and URL rules and keeps Content Security Policy behavior aligned with normal wiki content. Missing icons are registered on `ParserOutput` and omitted without removing the arrow, text label, or internal link.

## Client layout invariants

- Initialization is idempotent per root through a `WeakSet`.
- Edge indexes must be nonnegative and inside the node array.
- Cycle processing and highlighting are iterative and track visited nodes.
- Crossing reduction uses a fixed six sweeps.
- Zoom is clamped between 0.45 and 2.
- Parallel edges and repeated self-loops receive distinct geometry.
- Canvas space includes self-loop clearance.
- Wide charts overflow their own focusable viewport, not the page.

When changing geometry, update `tests/browser/edge-cases.js` with a relationship that would fail before the change. Test all four directions and a narrow viewport.

## MediaWiki compatibility boundary

The extension supports MediaWiki 1.35.2 through current tested branches. Avoid assuming current-only interfaces when a stable compatibility path exists. `Registration::installCompatibilityAliases()` contains the deliberate HTML-class compatibility shim. The ResourceLoader script avoids template literals because MediaWiki 1.35’s JavaScript minifier can corrupt them.

Run `tests/compatibility/check-minifier.php` against a MediaWiki 1.35 checkout whenever client source changes.

## Adding a feature safely

1. Define the user-facing syntax and failure behavior in `Usage.md`.
2. Decide which layer owns each part of the feature.
3. Add model values without embedding rendered HTML or URLs.
4. Reuse centralized validation policies; do not duplicate a security check.
5. Render through MediaWiki APIs and register parser-output dependencies.
6. Preserve the no-JavaScript and assistive-technology representation.
7. Add standalone parser/security tests and MediaWiki integration tests.
8. Add browser assertions for geometry, interaction, responsive behavior, and computed styles.
9. Check PHP syntax, JavaScript syntax, static security invariants, the MediaWiki 1.35 minifier, CodeSniffer, and Phan.

## Comment style

Comments should explain intent, invariants, compatibility constraints, security boundaries, or non-obvious algorithms. Avoid comments that merely translate one line of code into English. Public classes and interfaces should explain their role in the data flow; tricky local code should explain why its approach is necessary.
