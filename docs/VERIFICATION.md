# Verification record — 2026-08-28

## Completed locally

- Parsed every PHP file with PHP 8.2.12: no syntax errors.
- Parsed `extension.json`, `composer.json`, `package.json`, `package-lock.json`, and both JSON i18n files: no JSON errors.
- Checked `extension.json` top-level keys against the available MediaWiki v2 registration schema: no unknown keys.
- Ran `tests/run-unit.php`: 32 assertions passed. Coverage includes linear shorthand, branching, merging, fusion metadata, Unicode, reversible edges, cycles, self-loops, hostile HTML text, URL/path rejection, duplicate and unknown IDs, configuration-sized node/edge graphs, maximum-plus-one rejection, structured conditions, and bounded processing.
- Expanded and reran `tests/run-unit.php`: 140 assertions passed. Added coverage includes repeated shorthand nodes, mixed-operator chains, all directions and themes, Boolean aliases, controls gating, graph and node dimension boundaries, complete node metadata, multiline and escaped values, CRLF input, isolated nodes, ID/type/class validation boundaries, malformed syntax, invalid UTF-8, source-line metadata, zero/exact/plus-one condition and attribute limits, Unicode character-count limits, and invalid limit construction.
- Ran JavaScript syntax checking and `tests/security/static-check.js`: passed; 14 runtime PHP files were scanned for forbidden execution, network, filesystem, event-handler, serialization, and unsafe DOM patterns.
- Scanned runtime files for common private-key, certificate, AWS-key, and GitHub-token patterns: no matches.
- Browser-tested the ResourceLoader assets in Chromium with a representative eight-node, ten-edge graph:
  - desktop viewport 1280 × 900: eight nodes, ten paths, ten labels, zero node overlaps, no console warnings or errors;
  - mobile viewport 390 × 844: zero page-level horizontal overflow, graph viewport scrolled internally from 326 to 1222 pixels, zero node overlaps, all paths and labels present, no console warnings or errors;
  - zoom changed the canvas scale and stage dimensions;
  - `<details>` opened through pointer interaction;
  - Enter activated the native path-highlight button and updated `aria-pressed`;
  - visual review found a long fusion edge crossing cards; the route was changed to a reserved outer lane and rechecked.
- Loaded release 1.0.1 through MediaWiki 1.35.2's extension registry on PHP 8.2.12. Registration-time configuration validation, service wiring, and the `ParserFirstCallInit` hook all initialized without compatibility errors.
- Instantiated the parser and renderer services from MediaWiki 1.35.2, parsed a two-node graph, and rendered its graph, nodes, and edge label into a `ParserOutput`; the compatibility smoke assertion passed.
- Browser-tested release 1.0.2 with a five-node Awburn graph while deliberately suppressing the normal `wikipage.content` callback: the explicit preview fallback initialized successfully, created four SVG arrow paths and all four labels, placed Awburn on the bottom layer, Kainite and Embear on the middle layer, and both final forms on the top layer. The textual relationship list remained available to assistive technology but was visually hidden after enhancement. No browser warnings or errors were reported.
- Passed the release 1.0.2 script through MediaWiki 1.35.2's `JavaScriptMinifier`; SVG path spaces remained intact after minification.
- Loaded release 1.0.3 on MediaWiki 1.35.2 and invoked the real `OutputPageParserOutput` hook with MonsterEvolution parser metadata. The final `OutputPage` contained both `ext.monsterEvolution` and `ext.monsterEvolution.styles`, covering the edit-preview path that had previously emitted only fallback HTML and CSS.
- Browser-tested release 1.0.4's default palette and shrink-wrapped viewport with the five-node Awburn fixture: a 428-pixel graph occupied a 428-pixel viewport inside a 1200-pixel content area, the viewport background was transparent with no outer border, all four paths and labels rendered, and computed card/label colors were translucent dark with white text, strokes, and arrowheads.
- Ran the dependency-free browser edge-case harness in Chromium: 473 assertions passed at desktop width and again at a 390 × 844 viewport. It covered every direction, finite geometry, node and label collisions, canvas containment, internal mobile scrolling without page overflow, theme colors, connector contrast, zoom and highlighting, disconnected graphs, long names, malformed indexes, cycles, reciprocal edges, three parallel transitions, self-loops in every orientation, a 24-node/48-edge dense cyclic stress graph, edge icons above, beside, and without text, and server-rendered internal links around complete icon-and-text labels. Visual review exposed and then verified the fix for overlapping icon-bearing parallel labels.
- Passed the release 1.0.4 script through MediaWiki 1.35.2's `JavaScriptMinifier`, and validated the `mediawiki.base` ResourceLoader dependency against the installed 1.35 core module registry.
- Reran the expanded standalone suite after the 1.1.0 parser refactor and demo additions: 174 assertions passed, including every shipped `.wiki` demo, safe/unsafe edge icons and internal links, both icon positions, icon-only edges, and all preexisting boundary and security cases.
- Scanned the expanded runtime with `tests/security/static-check.js`: all invariants passed across 18 PHP files, including the new resolver interfaces, centralized file-name policy, and value validator.
- Loaded the refactored services through MediaWiki 1.35.2, parsed the new edge-icon model fields, and rendered a no-file graph through the real MediaWiki parser/renderer services. The compatibility smoke check passed.
- Passed the 1.1.0 ResourceLoader script through MediaWiki 1.35.2's `JavaScriptMinifier` after the icon-label client changes.
- Rendered an internally linked edge label through real MediaWiki 1.35.2 services and verified that the browser clones its server-produced anchor around both icon and text.

## Remaining environment-dependent verification

The local MediaWiki 1.35.2 checkout has no Composer-installed PHPUnit or JSON-schema validator. Therefore its PHPUnit integration suite, schema-validation maintenance script, CodeSniffer, Phan taint analysis, and Composer audit could not be executed locally. Integration tests are committed for standard MediaWiki extension CI; the included GitHub matrix validates the manifest on the legacy 1.35 branch and current target branches, then runs the standalone parser suite. CSP headers, real file repositories, production skins, and production-scale profiling still require verification in the deployment environment.
