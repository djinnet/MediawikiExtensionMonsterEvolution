# Adversarial security review

This review records the acceptance analysis for MonsterEvolution 1.1.0. Automated coverage lives in `tests/phpunit`, `tests/security`, and the dependency-free browser harness.

## Trust boundaries

All tag content, tag options, parser-function arguments, template results, page titles, file names, node IDs, display strings, classes, edge types, labels, and conditions are attacker-controlled. `LocalSettings.php` is administrator-controlled but validated because malformed values can harm availability. MediaWiki services and HTML produced by MediaWiki's file transform are trusted within MediaWiki's own security boundary.

## Attack results

| Attack | Control and result |
| --- | --- |
| Script, SVG, image-event, closing-tag, and attribute injection in text | Preserved as model text and contextually escaped by `Html::element`; never executable. |
| Event, style, `src`, `href`, `srcdoc`, or unknown attributes | Rejected by explicit whitelists before a model is created. |
| CSS payload in type/class/theme | Theme is an enumeration; type/class require a short lowercase semantic token and receive a fixed extension prefix. |
| `javascript:`, `data:`, `file:`, remote and localhost node/edge links | Shared link prevalidation rejects executable/external forms; `TitleFactory` and `Title::isExternal()` provide the final internal-title boundary. |
| Remote image, traversal, Windows/Unix path, encoded traversal | One shared local-file policy protects node images, configured placeholders, and edge icons. It rejects protocols, colons, separators, traversal, and encoded separators; resolution is only through `RepoGroup`. No HTTP or filesystem API receives the value. |
| DOM XSS in labels, links, icons, or tooltips | Client code writes unlinked labels with `textContent` and clones only MediaWiki-rendered internal anchors and local-file thumbnails; it never builds a URL. Tooltip markup is generated server-side with escaped text. |
| Prototype pollution IDs | IDs are values in PHP associative arrays and browser graph collections use numeric indexes, `Map`, and `Set`; no attacker keys are assigned to object prototypes. |
| Inline script/CSP bypass | No inline JavaScript, `eval`, `Function`, string event handlers, CDN, or `innerHTML`; all resources use ResourceLoader. |
| Oversized input or values | Byte, character, attribute, node, edge, condition, ID, and graph-count limits produce controlled localized errors. Hard registration ranges prevent hostile administrator misconfiguration. |
| Dense, radial, and cyclic graphs | Tokenizer advances monotonically. Layered layout uses bounded queue processing and exactly six ordering sweeps; radial layout uses one bounded breadth-first traversal. Highlight traversals are iterative with visited sets. Self-loops and cycles terminate. |
| ReDoS | Regexes are small, anchored where validation is intended, and contain no nested ambiguous quantifiers; structural parsing is character-by-character. |
| Parser recursion | Tag input is expanded once with MediaWiki's recursive preprocessor and current frame. MonsterEvolution does not recursively parse rendered results; core expansion limits remain active. |
| Cache poisoning or user-data leak | Output depends only on parser input, validated configuration, localized messages, and normal title/file state. It does not inspect request, user, cookie, time, random, or permission data. File and link dependencies are registered on `ParserOutput`. |
| SQL, command, serialization, CSRF, or privilege attacks | The extension has no database access, shell execution, serialization, state change, endpoint, privilege, or token handling. |
| Information disclosure | Visitor errors use localized controlled diagnostics; exceptions, paths, stack traces, and raw payloads are not returned. |
| Supply chain and privacy | No runtime third-party dependency, remote resource, telemetry, analytics, or secret. CI audits pinned development packages. |

## Manual review checklist

- [x] Raw HTML has no input path to trusted HTML.
- [x] Attribute and CSS namespaces are closed by validation.
- [x] Links and files resolve only through MediaWiki services.
- [x] No remote or local path access is present.
- [x] ResourceLoader is the sole script/style delivery path.
- [x] CSP needs neither `unsafe-inline` nor `unsafe-eval`.
- [x] Graph processing is bounded and cycle-aware.
- [x] Parser-cache inputs and link/file dependencies were reviewed.
- [x] JavaScript uses scoped DOM access and safe text insertion.
- [x] Edge icons reuse node-image validation, file resolution, and cache dependencies.
- [x] Edge links reuse node-link validation, MediaWiki title resolution, and link dependencies.
- [x] Errors are localized and escaped.
- [x] No secrets, write action, database query, or shell command exists.
- [x] `SECURITY.md`, regression tests, static checks, and taint-analysis configuration exist.

Remaining deployment verification—running the integration suite against each supported MediaWiki branch, CSP headers, real file repositories, skins, browsers, mobile assistive technology, and production-scale profiling—must occur in the target MediaWiki environment before a public deployment. Static source review alone cannot certify the surrounding MediaWiki installation.
