# Changelog

## 1.1.0 — 2026-08-28

- Added optional local-file icons to arrow labels with `above` and `next-to` layouts, safe MediaWiki repository resolution, icon-only labels, missing-file fallback, and accessible non-JavaScript behavior.
- Added optional internal links around complete arrow labels, including their icons, using MediaWiki title resolution and server-rendered anchors with a linked no-JavaScript fallback.
- Separated grammar construction from scalar validation, centralized local-file policy, introduced focused link/file resolver interfaces, and documented the codebase's SOLID boundaries and invariants.
- Added newcomer-focused comments throughout parser, model, hook, resolver, renderer, layout, and service-composition code.
- Added a detailed `Usage.md`, an architecture guide, and copyable Pokémon, Digimon, LumenTale, Coromon, Anode Heart, and Temtem demo charts without bundling third-party artwork.
- Expanded the standalone parser and security suite from 32 to 174 assertions, including validation of every shipped demo and new icon/link syntax and security cases.
- Added a dependency-free browser regression fixture with more than 100 layout, direction, interaction, responsive, theme, malformed-metadata, cycle, reciprocal-edge, parallel-edge, and self-loop assertions, and made it part of GitHub Actions through headless Chrome.
- Added browser coverage for icons above, beside, and without label text, and prevented icon-bearing parallel labels from overlapping.
- Ignored invalid negative client edge indexes instead of allowing malformed relationship metadata to interrupt enhancement.
- Separated repeated transitions and repeated self-loops so their curves and labels no longer render directly on top of one another.
- Reserved canvas space for self-loop arrows and labels, and added a subtle contrast shadow so white connectors remain visible on light wiki backgrounds.

## 1.0.4 — 2026-08-25

- Fixed MediaWiki 1.35 ResourceLoader compatibility by depending on `mediawiki.base` instead of the unavailable `mediawiki` module.
- Made the enhanced graph viewport shrink to the graph width, up to the available content width, with a transparent outer background.
- Changed the default visual palette to translucent dark cards and labels with white plain text, connectors, and arrowheads while preserving the wiki's normal link colors.
- Added gated CI release automation with cross-version verification, ResourceLoader dependency checks, collision-safe patch tags, verified ZIP packaging, SHA-256 checksums, workflow artifacts, and GitHub Releases.
- Updated the CI coding-standard dependency to `mediawiki/mediawiki-codesniffer` 51.0.1, which uses the security-fixed PHP_CodeSniffer 3.13.6 while retaining PHP 8.2 development compatibility.
- Fixed standalone CI Phan analysis by loading MediaWiki REL1_45 core, development dependencies, and integration-test symbols without analyzing MediaWiki's own source as extension code, and added real renderer smoke coverage to the supported-version matrix.
- Added rendering compatibility for MediaWiki's namespaced `Html` class and stable `LinkTarget` interface across MediaWiki 1.35–1.46.
- Allowed MediaWiki 1.35's historically pinned test dependencies only in its compatibility job and supplied the minimal test configuration to registration validation on every supported MediaWiki branch.
- Fixed release checksum verification so archive paths are resolved relative to the `dist` directory where the checksum manifest is generated.

## 1.0.3 — 2026-08-25

- Fixed MediaWiki 1.35 edit previews dropping the graph script module by re-registering graph assets through `OutputPageParserOutput` whenever the parser output contains MonsterEvolution markup.
- Added parser-output metadata and integration coverage for both ResourceLoader modules.

## 1.0.2 — 2026-08-25

- Fixed client initialization in MediaWiki 1.35 edit previews and other late-loading content contexts.
- Replaced SVG template strings that MediaWiki 1.35's JavaScript minifier could corrupt, restoring arrows and edge labels.
- Visually hides the accessible textual relationship list after the enhanced graph initializes.
- Added a bottom-to-top Awburn branching fixture covering four labeled arrows and five automatically layered nodes.

## 1.0.1 — 2026-08-25

- Backported registration, hook, parser, title, repository, and HTML API usage to MediaWiki 1.35.2 while retaining the PHP 8.1 requirement.
- Added MediaWiki and PHP platform constraints to extension registration and a local 1.35 compatibility fixture.

## 1.0.0 — 2026-08-25

- Initial release.
- Added validated tag and parser-function syntaxes, shorthand chains, structured nodes and edges, branching, merging, fusion, reversible edges, cycles, and self-loops.
- Added MediaWiki-native internal links, file thumbnails, ResourceLoader assets, bounded automatic layout, responsive scrolling, zoom controls, path highlighting, themes, print styles, progressive enhancement, and accessible relationship markup.
- Added configurable parser limits, localized errors and tracking category, security architecture, adversarial tests, static analysis, and CI metadata.
