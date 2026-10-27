# Changelog

## 0.1.6 (2026-10-26)

- **A staff guide**, GUIDE.md (see units without a UCUM code, set one, share the report), opened by **Help** on the page at the installed version's tag, with a test that every label it quotes is on the page.
- **Install note:** restart the container after adding, upgrading or removing a module copied in by hand (OpenEMR's official 8.0.0 to 8.5.0 images keep class locations, and classes not found, in APCu).

## 0.1.5 (2026-10-26)

- **Pages are announced in your language.** Each page now declares the signed-in user's OpenEMR language and its direction instead of English, so a screen reader reads the translated text in that language. It comes from openhealthcare-common 1.4.0, which this version requires. If another module's older copy of the library loads first on a site that installs by hand, the pages say English, as before, rather than stop.
- **Manual installs:** the README now says that every module's own copy of openhealthcare-common must be the same version, since OpenEMR loads the library once, from the first module that registers it.

## 0.1.4 (2026-10-26)

- **The README says why bare `units` stays unmapped:** it could be `U` or `[IU]`, so it is listed with no UCUM code and goes out as text, rather than guessed.

## 0.1.3 (2026-10-26)

- **Text and buttons are easier to read on this module's pages.** OpenEMR's blue (#007bff) is 3.97:1 on white, under the 4.5:1 that WCAG 2.1 AA asks of normal text and that HHS's Section 504 rule requires from 2027-05-11. Under OpenEMR's light and manila themes, the module's links and primary buttons now use #0056b3 (7.0:1), and muted and warning text darker shades that stay readable on tinted rows, and a link inside text is underlined; the dark, solar and colour themes keep their own colours, and OpenEMR's own pages are unchanged.

## 0.1.2 (2026-10-25)

- **Fixed: on OpenEMR 7.0.4 and 8.0.0 to 8.0.0.3, enabling this module stopped every OpenEMR page, the login page included (HTTP 500).** Those releases have `OEGlobalsBag` without `getProjectDir()` and the other getters, which came with 8.1.0, and the module called them wherever the class existed. It now reads the values OpenEMR stores there, and openhealthcare-common 1.3.3 does the same for the web root, the kernel and the session (8.0.x's session factory has only `getWrapper()`). Checked on fresh installs of the official 7.0.2, 7.0.4 and 8.0.0 images with every module enabled, and on 8.4.1 and master.

## 0.1.1 (2026-10-23)

- **Shared code:** the OpenEMR compatibility layer (settings, paths, the event dispatcher, CSRF, the refusal page, the audit log) now comes from [openhealthcare-common](https://github.com/anun333/openhealthcare-common) 1.x, which the openhealthcare modules share, instead of a copy in `src/Compat/`. Composer installs it with the module. The module does the same things as before, and its permission checks stay in its own code. Its read of the `version` table moved with it, and FOOTPRINT.md now lists it under the library's path. FOOTPRINT.md reads the library methods the module calls, as part of the module.
- The bootstrap registers the library's namespace before looking for it, since Composer remembers a class it once failed to find. Without the library the module stays off instead of stopping OpenEMR.
- **Accessibility:** the page declares its language (`<html lang>`), unit codes in `code` pass contrast (over 5.6:1; Bootstrap's pink was 3.7:1), and links in running text are underlined.
- **README:** run OpenEMR on a PHP with PHP's php2shell fix (2026-10-21: 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later). The official OpenEMR 7.0.2 and 7.0.3 Docker images can't get it.
- **FOOTPRINT.md** now comes from openhealthcare-common's footprint tool (1.1), the one every module shares; `tools/footprint.php` only loads it. The sheet's closing notes, what it can't show, are this module's own (in composer.json): the old ones were Layout Export's.
- **Checked:** unit tests; the nightly matrix on 8.4.1 and master (24 checks each); fresh official 7.0.2, 7.0.3, 8.1.0, 8.3.0 and 8.5.0 images, with every module enabled and with it alone.

## 0.1.0 (2026-10-21)

First release.

- **Report** of a site's units in UCUM terms: every unit list option (`drug_units`, `drug_form`, `proc_unit` and any list whose id says unit) with the UCUM code an export would send, and the distinct values of eight unit columns with their row counts and codes. Downloadable as JSON, unit strings and counts only.
- **Setting a code** on a list option (`UCUM:<code>` in its codes field, other entries kept), one at a time or all suggested at once. Only codes this OpenEMR can confirm are UCUM are accepted; each change is in the audit log.
- Reads units the way the shared unit layer proposed for OpenEMR core (#14698) does: core's own layer where present, else core's UCUM engine, else the copied spelling table and the codes the engine verified (110).
- `admin/super` for the page, the JSON and every change, as OpenEMR's list editor requires.
- Checked end to end on OpenEMR 8.4.1, 8.5.0-dev and the #14698 reference branch.
