# Security policy

## Supported versions

Security fixes are provided for the latest MonsterEvolution release. The extension is runtime-compatible with MediaWiki 1.35.2 or newer, but compatibility does not extend security support to end-of-life MediaWiki releases. Administrators should run current MediaWiki and PHP security releases. Older MonsterEvolution versions may receive a fix only when a clean backport is practical.

## Reporting a vulnerability

Please report a suspected vulnerability privately to the project maintainers or through the private security-reporting mechanism of the repository that distributes this extension. If the project is hosted on Wikimedia infrastructure, follow the private process at <https://www.mediawiki.org/wiki/Reporting_security_bugs>.

Do not open a public issue, publish exploit details, or probe a wiki you do not own before a patch is available.

Include:

- the affected MonsterEvolution, MediaWiki, PHP, browser, and skin versions;
- the smallest reproduction, including the evolution definition;
- the observed and expected behavior;
- security impact and required privileges;
- relevant logs with secrets and personal information removed;
- any proposed mitigation or patch.

Maintainers will acknowledge the report, reproduce and triage it, prepare regression tests and a fix, coordinate disclosure, and release patched versions. Security bugs receive a regression test whenever practical; fixes address the vulnerability class rather than only a reported payload.
