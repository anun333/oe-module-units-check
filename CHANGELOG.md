# Changelog

## 0.1.1 (unreleased)

- **Shared code:** the OpenEMR compatibility layer (settings, paths, the event dispatcher, CSRF, the refusal page, the audit log) now comes from [openhealthcare-common](https://github.com/anun333/openhealthcare-common) 1.x, which the openhealthcare modules share, instead of a copy in `src/Compat/`. Composer installs it with the module. The module does the same things as before, and its permission checks stay in its own code. Its read of the `version` table moved with it, and FOOTPRINT.md now lists it under the library's path. FOOTPRINT.md reads the library methods the module calls, as part of the module.
- The bootstrap registers the library's namespace before looking for it, since Composer remembers a class it once failed to find. Without the library the module stays off instead of stopping OpenEMR.
- **Accessibility:** the page declares its language (`<html lang>`), unit codes in `code` pass contrast (over 5.6:1; Bootstrap's pink was 3.7:1), and links in running text are underlined.

## 0.1.0 (2026-10-21)

First release.

- **Report** of a site's units in UCUM terms: every unit list option (`drug_units`, `drug_form`, `proc_unit` and any list whose id says unit) with the UCUM code an export would send, and the distinct values of eight unit columns with their row counts and codes. Downloadable as JSON, unit strings and counts only.
- **Setting a code** on a list option (`UCUM:<code>` in its codes field, other entries kept), one at a time or all suggested at once. Only codes this OpenEMR can confirm are UCUM are accepted; each change is in the audit log.
- Reads units the way the shared unit layer proposed for OpenEMR core (#14698) does: core's own layer where present, else core's UCUM engine, else the copied spelling table and the codes the engine verified (110).
- `admin/super` for the page, the JSON and every change, as OpenEMR's list editor requires.
- Checked end to end on OpenEMR 8.4.1, 8.5.0-dev and the #14698 reference branch.
