# Units Check for OpenEMR

Shows what an OpenEMR site keeps as units in UCUM terms, and lets an administrator add UCUM codes to the site's unit list options.

UCUM (the Unified Code for Units of Measure) is how units are exchanged: FHIR quantities, C-CDA physical quantities, eCQM calculations and HL7 v2 unit fields all use its codes. OpenEMR stores units as list option ids, list titles and free text (`mcg`, `MG/DL`, `9`), and its exporters have labelled those as UCUM without checking them. OpenEMR's plan to make UCUM its one unit vocabulary is [openemr/openemr#14698](https://github.com/openemr/openemr/issues/14698). This module lets a site see where it stands before and after that change, and fix the part that is its own: the options it added or renamed in its unit lists.

> **Status: 0.1.2, a pre-release.** Tested with synthetic data on OpenEMR 8.4.1, on 8.5.0-dev (master, which has core's UCUM engine) and on the UCUM reference branch for #14698 (see [Compatibility](#compatibility)). The only thing it changes is a unit list option's codes field, when an administrator asks it to.

What installing it changes in your database (nothing), what it reads and writes, and what it hooks into are listed in [FOOTPRINT.md](FOOTPRINT.md), generated from the source.

## What it shows

**Admin › Units Check** has three parts.

- **A summary:** how many unit list options carry a UCUM code; how many have a title that gives one that isn't set yet; and how many stored rows have a UCUM code, would go out as text only, or can't be checked on this OpenEMR.
- **Unit lists:** every option of `drug_units`, `drug_form`, `proc_unit` and any other list whose id says unit, with:
  - its codes field;
  - the UCUM code an export would send, from a `UCUM:` entry in its codes field or else read from its title;
  - why it has none, when it has none: a bare number (in OpenEMR, an option id), capitals that UCUM reads as something else (`PG` is petagauss), or not a unit at all (`tablet`, a dose form, needs no code).
- **Stored values:** the distinct values each unit column holds, most common first, with their row counts and their UCUM code. The columns are `immunizations.amount_administered_unit`, `prescriptions.unit` and `drugs.unit` (`drug_units` ids), `procedure_type.units` (`proc_unit` ids), `procedure_result.units`, `procedure_specimen.volume_unit`, `form_observation.ob_unit` and `form_vitals_calculation_components.value_unit`. A list option id reads as its option; anything else, such as a unit a FHIR client wrote, reads as a string. The Vitals form keeps each measurement in a fixed unit, so it has nothing to map.

The report downloads as JSON: unit strings and row counts only, no patient identifiers or clinical values.

## What it changes

For any unit list option, an administrator can set its UCUM code. The module writes `UCUM:<code>` into the option's `codes` field and keeps every other entry there (`NCI-CONCEPT-ID:C28253;UCUM:mg`). **Add all suggested codes** does that for every option whose title gives a code. A code is accepted only if this OpenEMR can confirm it is UCUM:

- with core's UCUM engine, the engine decides;
- without it, only the codes the module carries, each checked by the engine when the module was built, are accepted.

A bare number is refused, and so is an all-capitals code the module doesn't know as one. Each change is written to OpenEMR's audit log (event `other`) with the codes field before and after.

The `UCUM:` entry is what the proposed core change (#14698) reads first for a list option, so codes set now are used once that lands. Until then, OpenEMR's exporters don't read it.

## How it reads a unit

The same way the shared unit layer proposed for core does, with the same table of spellings (copied from it by `tools/sync_spellings.php`, which a test keeps honest):

| This OpenEMR has | The module uses |
|---|---|
| the shared unit layer (`OpenEMR\Common\Units\UcumCode`, the #14698 branch) | core's own reading |
| core's UCUM engine only (master since 2026-10-11) | the engine, with the module's rules |
| neither (every release up to 8.4.1) | the copied table, and the codes the engine verified when the module was built |

The rules: a bare number is never a unit; a spelling with no lower-case letter outside brackets is not trusted as written but looked up (`MG/DL` is `mg/dL`, `PG` is `pg`); and `K/uL` reads as thousands per microlitre, not kelvin.

## Who can open it

The page, the JSON and every change need `admin/super`, the check OpenEMR's own list editor makes. Writes need the page's CSRF token.

## Compatibility

**PHP security.** PHP's php2shell advisory (2026-10-21; CVE-2026-109618 and CVE-2026-109619) is remote code execution without logging in, and it is being exploited. Run OpenEMR on PHP 8.2.35, 8.3.36, 8.4.27, 8.5.12 or later, whatever the module needs. The official OpenEMR 7.0.2 and 7.0.3 Docker images can't get that fix: they carry PHP 8.2.16 and 8.3.15 on Alpine releases that no longer get updates. Run 7.0 only on a patched PHP, or upgrade OpenEMR. OpenEMR rebuilt its 8.4.1, 8.3.0, 8.2.0 and 8.0.0.3 images with the fix on 2026-10-22 (forum t/27157); a container started before then needs pulling again.

Written for OpenEMR 7.0 and later through the same compatibility layer as Layout Export (settings, session, CSRF, access denied and the audit log changed between releases). Checked end to end with synthetic data (24 checks: the page, who may open it, each synthetic value read as predicted, setting codes, add-all, the JSON, the audit log, and `list_options` restored to its checksum afterwards) on:

- OpenEMR 8.4.1 (no engine: the module's tables);
- 8.5.0-dev at 61b4a46a4a (the engine);
- the #14698 reference branch at b9eb0cf88e (core's layer).

On fresh official 7.0.2, 7.0.3, 8.1.0, 8.3.0 and 8.5.0 images (2026-10-23) it installs through Module Manager and its page loads, with every module enabled and with it alone (`dev/module_compat_check.sh`). PHP 8.2 to 8.5.

## Install

```
composer require anun333/oe-module-units-check
```

Then enable it under **Modules › Manage Modules**. It brings [openhealthcare-common](https://github.com/anun333/openhealthcare-common), the small library of OpenEMR compatibility code the openhealthcare modules share; Composer installs it alongside. Or copy this directory to `interface/modules/custom_modules/oe-module-units-check/`, run `composer install --no-dev` in it to fetch that library, and register it there; without the library the module stays off.

## Development

```
composer install
vendor/bin/phpunit
php tools/footprint.php --check
php tools/sync_spellings.php --check <path to UcumCode.php>
```

`tools/sync_spellings.php <UcumCode.php> <OpenEMR tree with the engine>` copies the spelling tables and re-checks every code the module trusts against core's engine.

## Our other modules

[Clinic Forms](https://github.com/anun333/oe-module-clinic-forms), [UDS Report](https://github.com/anun333/oe-module-uds-report), [Staff Worklist](https://github.com/anun333/oe-module-staff-worklist), [Sliding Fee Discount](https://github.com/anun333/oe-module-sliding-fee), [Care Management](https://github.com/anun333/oe-module-care-management), [Migration](https://github.com/anun333/oe-module-migration), [Layout Export](https://github.com/anun333/oe-module-layout-export).

## Licence

GPL-3.0-or-later.
