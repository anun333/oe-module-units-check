# Units Check: staff guide

For the administrator who looks after OpenEMR's lists. How it reads a unit, and how to install it, are in the [README](README.md).

The module shows, for every unit this site keeps, the UCUM code an export would send, or why it goes out as text only. UCUM is the unit code FHIR, C-CDA, eCQMs and HL7 v2 messages use; a unit sent as text only can be misread by the system that receives it. The module changes nothing except the codes field of a unit list option, when you set one.

## The administrator

### See which units go out without a UCUM code

*Where:* Admin › **Units Check**.

1. Read the summary at the top: how many unit list options carry a UCUM code, how many have a title that gives one that isn't set yet, and how many stored rows have a code, go out as text only, or can't be checked on this OpenEMR.
2. Under **Unit lists**, each option shows its **Title**, its **Codes** field, the **UCUM** code an export would send and, when it has none, **Why**: a bare number, capitals that mean something else in UCUM, or not a unit at all (a dose form such as tablet needs no code).
3. Under **Stored values**, each **Value** the unit fields hold, most common first, with its **Rows** and its code.

### Set a UCUM code on a list option

*Where:* **Unit lists**, the option's row, under **Set the UCUM code**.

1. Type the code (mg/dL, say) and choose **Set**. A code is accepted only if this OpenEMR can confirm it is UCUM; the page says why when it isn't.
2. To set every code the titles give at once, choose the **Unit lists** button that adds all the suggested codes.

Each change keeps whatever else the codes field held, and is written to OpenEMR's audit log with the field before and after.

*What it doesn't do:*
- OpenEMR's exports don't read the code yet: they will once the proposed core change for UCUM (#14698) lands, and codes set now are used then.
- It leaves the bare word "units" (insulin, heparin) without a code on purpose, since it could mean two different UCUM units.
- It never changes a stored value or a patient record.

### Share the report

Choose **Download this report as JSON**. It holds unit strings and row counts only, no patient data, so it can go to a vendor or to OpenEMR's developers.

## Who can use it

The page, the download and every change need an administrator (`admin/super` in OpenEMR's access control), the check OpenEMR's own list editor makes.
