# UsedItemsExport — Configurable PDF Report Modifications

## What was changed and why

### 1. Lithuanian character support (font fix)
**Problem:** Footer text showed `Aukš?iau išvardinta ?ranga` — question marks instead of Lithuanian characters (š, ž, ė, ų, etc.).

**Fix:** The PDF template now uses `dejavusans` font family instead of `helvetica`. DejaVu Sans supports 200+ languages including Lithuanian, Latvian, and all Baltic/Cyrillic scripts. The font is selectable from the config UI — default is `DejaVu Sans (UTF-8)`.

Every `<table>`, `<td>`, `<th>`, `<div>`, and `<body>` in the template explicitly sets `font-family` to the configured font, ensuring no element falls back to Helvetica.

### 2. Uploadable logo from GLPI interface
**Problem:** Logo was hardcoded to `logo.png` copied from GLPI's default during install. Changing it required SSH access.

**Fix:** Config form now has a file upload field. Accepts PNG, JPG, GIF, SVG. The file is saved to the plugin's doc directory. MIME type is auto-detected for proper `data:` URI encoding in the PDF. Logo size is configurable in millimeters (0 = auto).

### 3. Entity address from user's entity
**Problem:** Address was taken from `$_SESSION['glpiactive_entity']` — the entity of whoever generates the report, not the user being exported.

**Fix:** Changed to `$User->fields['entities_id']` so the PDF shows the entity that the **target user** belongs to. Falls back to active entity if the user has no entity set.

### 4. Configurable document title
**Problem:** "Asset export ref :" was hardcoded via `__('Asset export ref : ')`.

**Fix:** New `document_title` config field. Admin can type any text (e.g. "Turto eksporto ref" in Lithuanian). Shown as `{title} : {refnumber}` in the PDF.

### 5. All field labels translatable from GLPI interface
**Problem:** Column headers ("Serial Number", "Inventory Number", "Name", "Type", "Signature") used GLPI's `__()` translations which may not have Lithuanian translations for this plugin.

**Fix:** Each label has a dedicated text field in the config. If filled, the custom text is used. If left empty, the default GLPI translation is used as fallback. This lets admins type Lithuanian labels like:
- Serial Number → `Serijinis numeris`
- Inventory Number → `Inventorinis numeris`
- Name → `Pavadinimas`
- Type → `Tipas`
- Signature → `Parašas`

---

## Files changed

| File | Location | Changes |
|------|----------|---------|
| `config.class.php` | `inc/` | New DB fields, logo upload handler, migration |
| `config.form.php` | `front/` | Added `handleLogoUpload()` call + `enctype` |
| `config.html.twig` | `templates/` | Full config form with all new options |
| `export.class.php` | `inc/` | User entity, labels, font, logo MIME, custom columns |
| `export_template.html.twig` | `templates/` | DejaVu font, configurable labels, conditional sections |

## New database columns (auto-migrated)

```sql
logo_filename       VARCHAR(255) DEFAULT 'logo.png'
logo_width          INT DEFAULT 0
show_logo           TINYINT DEFAULT 1
show_entity_address TINYINT DEFAULT 1
show_signature      TINYINT DEFAULT 1
show_serial         TINYINT DEFAULT 1
show_otherserial    TINYINT DEFAULT 1
show_name           TINYINT DEFAULT 1
show_type           TINYINT DEFAULT 1
document_title      VARCHAR(255) DEFAULT 'Asset export ref'
label_serial        VARCHAR(255) DEFAULT ''
label_otherserial   VARCHAR(255) DEFAULT ''
label_name          VARCHAR(255) DEFAULT ''
label_type          VARCHAR(255) DEFAULT ''
label_signature     VARCHAR(255) DEFAULT ''
header_text         TEXT DEFAULT NULL
disclaimer_text     TEXT DEFAULT NULL
custom_columns      TEXT DEFAULT NULL
font_family         VARCHAR(100) DEFAULT 'dejavusans'
```

All default to original behavior — upgrade is non-breaking.

## Installation

1. Copy the 5 files to their respective directories in the plugin folder.
2. In GLPI: **Setup → Plugins → UsedItemsExport → Upgrade** (or uninstall/reinstall).
3. Configure at **Setup → General → Used items export** tab.
4. First thing to do: set Font to `DejaVu Sans` and test a PDF to confirm Lithuanian characters work.

## Custom columns (bonus feature)

In the config, there's a "Extra custom columns" textarea where you can add additional columns by typing one per line in `field_name|Label` format:

```
contact|Kontaktinis asmuo
comment|Komentaras
locations_id|Vieta
states_id|Būsena
manufacturers_id|Gamintojas
```

Foreign key fields (`*_id`) are automatically resolved to their display names.
