# INSTALL — SevenxExpPlatformNGSubtreeCopyWithLayouts Bundle

Step-by-step installation guide for the `SevenxExpPlatformNGSubtreeCopyWithLayouts`
Symfony/Ibexa DXP bundle.

> **TL;DR** — The bundle lives directly inside the project's `src/` tree and is
> already loaded by the existing `"App\\": "src/"` PSR-4 autoload entry.  
> Installation is: register in `config/bundles.php` → `composer dump-autoload`
> → `bin/console cache:clear`.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [File Layout](#2-file-layout)
3. [Register the Bundle](#3-register-the-bundle)
4. [Autoload](#4-autoload)
5. [Clear the Cache](#5-clear-the-cache)
6. [Verify the Command is Available](#6-verify-the-command-is-available)
7. [Usage](#7-usage)  
   7.1 [Basic copy](#71-basic-copy)  
   7.2 [Dry-run (preview only)](#72-dry-run-preview-only)  
   7.3 [Copy content without layout rules](#73-copy-content-without-layout-rules)  
   7.4 [Verbose output](#74-verbose-output)  
   7.5 [Specific siteaccess](#75-specific-siteaccess)  
8. [Understanding the Output](#8-understanding-the-output)
9. [Admin User ID](#9-admin-user-id)
10. [Troubleshooting](#10-troubleshooting)
11. [Removing the Bundle](#11-removing-the-bundle)

---

## 1. Prerequisites

| Requirement | Version |
|---|---|
| PHP | ≥ 8.2 (project uses 8.5.4) |
| Symfony | ≥ 7.1 |
| Ibexa DXP | ≥ 4.6 |
| Netgen Layouts | ≥ 1.3 |
| Doctrine DBAL | ≥ 3.x (included with Ibexa) |

The bundle uses **constructor autowiring** — no manual service wiring is needed
as long as the dependencies above are present, which they are in any standard
Netgen Media-Site installation.

All bundle source files must already be present in the repository.  Verify:

```bash
find src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts -type f | sort
```

Expected output:

```
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/Command/CopySubtreeWithLayoutsCommand.php
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/Resources/config/services.yaml
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/Service/SubtreeLayoutRuleCopier.php
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/Service/SubtreeSectionCopier.php
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/Service/SubtreeObjectStateCopier.php
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle.php
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/doc/INSTALL.md
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/doc/README.md
```

If any of those files are missing, check out the correct branch or restore them
from the repository.

---

## 2. File Layout

```
src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/
├── Command/
│   └── CopySubtreeWithLayoutsCommand.php   # Console command
├── Resources/
│   └── config/
│       └── services.yaml                   # Autowire/autoconfigure
├── Service/
│   ├── SubtreeLayoutRuleCopier.php         # Core tree-traversal + rule copy service
│   ├── SubtreeSectionCopier.php            # Re-applies section assignments
│   └── SubtreeObjectStateCopier.php        # Re-applies object state values
├── doc/
│   ├── INSTALL.md                          # ← this file
│   └── README.md                           # Architecture overview
└── SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle.php  # Bundle entry point
```

---

## 3. Register the Bundle

Open `config/bundles.php` and append the bundle class to the array **before**
the closing `];`:

```bash
# Open with your editor of choice, e.g.:
nano config/bundles.php
# or
vim config/bundles.php
```

Add the following line (keep it at the end of the array, after all Netgen
bundles, for readability):

```php
    App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle::class => ['all' => true],
```

The tail of `config/bundles.php` should look like this when you are done:

```php
    Netgen\Bundle\IbexaScheduledVisibilityBundle\NetgenIbexaScheduledVisibilityBundle::class => ['all' => true],
    App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle::class => ['all' => true],
];
```

You can verify the line was added correctly:

```bash
grep -n "SevenxExpPlatformNGSubtreeCopyWithLayouts" config/bundles.php
```

Expected output:

```
86:    App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle::class => ['all' => true],
```

---

## 4. Autoload

The bundle namespace `App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\` is already
covered by the project's existing PSR-4 entry in `composer.json`:

```json
"autoload": {
    "psr-4": {
        "App\\": "src/"
    }
}
```

> **Important:** Do NOT add a second PSR-4 entry for the bundle namespace.  
> Symfony's `DebugClassLoader` double-includes the file when two entries resolve
> to the same physical path, resulting in a fatal  
> `Cannot redeclare class ... SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle` error.

Regenerate the Composer autoload classmap to include the new classes:

```bash
composer dump-autoload
```

Or, if running as root on the server (e.g. in a Plesk/cron context):

```bash
COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload
```

Expected output:

```
Generating optimized autoload files
composer/package-versions-deprecated: Generating version class...
composer/package-versions-deprecated: ...done generating version class
Generated optimized autoload files containing 20150 classes
```

The exact class count will vary; what matters is no error is shown.

---

## 5. Clear the Cache

Clear the Symfony container cache so the new bundle's services are compiled:

```bash
# Development environment
php bin/console cache:clear --env=dev

# Production environment
php bin/console cache:clear --env=prod
```

Expected output (dev):

```
 // Clearing the cache for the dev environment with debug true

 [OK] Cache for the "dev" environment (debug=true) was successfully cleared.
```

If `php` is not on `$PATH` use the full binary path, e.g. `/usr/bin/php`:

```bash
/usr/bin/php bin/console cache:clear --env=dev
```

---

## 6. Verify the Command is Available

Confirm the command has been registered as a Symfony console command:

```bash
php bin/console se7enx:nglayouts:copy-subtree-with-layouts --help
```

Expected output:

```
Description:
  Copies an Ibexa content subtree and duplicates all Netgen Layouts resolver rules for the new location IDs.

Usage:
  se7enx:nglayouts:copy-subtree-with-layouts [options] [--] <source-location-id> <target-parent-location-id>

Arguments:
  source-location-id             Location ID of the root of the subtree to copy
  target-parent-location-id      Location ID of the parent that will receive the copied subtree

Options:
      --dry-run                  Print what would be done without creating any rules or copying any content
      --skip-layout-rules        Copy the subtree but do NOT duplicate layout resolver rules
  -h, --help                     Display help for the given command. ...
```

You can also list all `se7enx:` commands to confirm:

```bash
php bin/console list se7enx
```

---

## 7. Usage

### 7.1 Basic copy

Copies the subtree rooted at location **385** as a child of location **42**,
then duplicates all Netgen Layouts resolver rules for the new location IDs:

```bash
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42
```

With a specific siteaccess (recommended on multi-site installations):

```bash
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42 --siteaccess=media
```

### 7.2 Dry-run (preview only)

Shows what *would* happen without writing anything to the repository.  
Use this to confirm you have the correct locations before committing the copy.

```bash
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42 --dry-run
```

Expected output:

```
 ! [NOTE] DRY-RUN mode — no content will be written to the repository.

 Subtree Copy with Netgen Layouts
 =================================

 Source location    385 — My Section Root
 Target parent      42  — Site Root

 Step 1: Copying Ibexa subtree
 ------------------------------
 [DRY-RUN] Would call LocationService::copySubtree()
 [DRY-RUN] Skipping layout rule copy (no new location IDs available in dry-run).

 [OK] Dry-run complete — no changes made.
```

> **Note:** In dry-run mode, no Ibexa subtree copy is performed, which means
> no real new location IDs exist.  Therefore layout rule duplication is also
> skipped in dry-run mode.  The output lists what *would* be called, not what
> rules would be created.

### 7.3 Copy content without layout rules

Copies the subtree but skips all Netgen Layouts rule duplication.
Useful when you want to handle the layout rules manually:

```bash
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42 --skip-layout-rules
```

### 7.4 Verbose output

Add `-v` (normal), `-vv` (verbose), or `-vvv` (debug) for progressively more
output from the tree traversal and rule creation steps:

```bash
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42 -v
```

### 7.5 Specific siteaccess

On multi-site Ibexa DXP installations, always pass `--siteaccess` to avoid
resolving content through the wrong site configuration:

```bash
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42 --siteaccess=media_en
```

---

## 8. Understanding the Output

A successful run produces output in four sections:

```
 Subtree Copy with Netgen Layouts
 =================================

 Source location    385 — My Section Root
 Target parent      42  — Site Root

 Step 1: Copying Ibexa subtree
 ------------------------------
 New root location ID: 601 — My Section Root

 Step 2: Building location ID map (parallel tree traversal)
 -----------------------------------------------------------
 Mapped 13 location(s).

 Step 3: Duplicating Netgen Layouts resolver rules
 --------------------------------------------------
  Creating new rule: target=ibexa_location:601 → layout=a1b2c3d4-... @ priority=9
  Creating new rule: target=ibexa_location:602 → layout=e5f6a7b8-... @ priority=4
  Creating new rule: target=ibexa_subtree:601  → layout=a1b2c3d4-... @ priority=9

 [OK] Done. Subtree copied to location 601. Layout rules created: 3, skipped: 0.

 ------------------- -------------------
  Old location ID     New location ID
 ------------------- -------------------
  385                 601
  386                 602
  387                 603
  ...
 ------------------- -------------------
```

**Column explanations:**

| Output item | Meaning |
|---|---|
| `New root location ID` | The Ibexa location ID assigned to the copied subtree root |
| `Mapped N location(s)` | Total number of old→new ID pairs built by tree traversal |
| `target=ibexa_location:601` | A rule was created targeting the new location exactly |
| `target=ibexa_subtree:601` | A rule was created matching the whole new subtree |
| `layout=UUID` | The Netgen Layouts layout UUID assigned to the new rule (same as the source rule) |
| `@ priority=9` | Rule priority (always `original priority − 1`, so the original wins on conflict) |
| `skipped: N` | Rules skipped (currently only possible in dry-run; reported for future extension) |

---

## 9. Admin User ID

The command runs the Ibexa repository operations as user ID **14** (the Ibexa
default admin user).  This is hardcoded in `CopySubtreeWithLayoutsCommand` as:

```php
private const ADMIN_USER_ID = 14;
```

If your installation uses a different admin user ID, edit this constant:

```bash
nano src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/Command/CopySubtreeWithLayoutsCommand.php
```

Change:

```php
private const ADMIN_USER_ID = 14;
```

to the correct user ID, then clear the cache:

```bash
php bin/console cache:clear --env=dev
```

To find the correct admin user ID in your database:

```sql
SELECT u.contentobject_id, a.login
FROM ezuser u
JOIN ezcontentobject_attribute a ON a.contentobject_id = u.contentobject_id
WHERE a.data_text = 'admin'
  AND a.attribute_original_id = 0
  AND a.version = 1;
```

Or via the Ibexa admin UI: **Admin panel → Users → Administrator Users** →
click the admin user → note the content item ID in the URL.

---

## 10. Troubleshooting

### `Cannot redeclare class SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle`

**Cause:** A second PSR-4 entry was added to `composer.json` for the bundle
namespace alongside the existing `"App\\": "src/"` entry.  Both resolve to the
same file, causing Symfony's `DebugClassLoader` to include it twice.

**Fix:** Remove the duplicate entry.  `composer.json` should only have:

```json
"autoload": {
    "psr-4": {
        "App\\": "src/"
    }
}
```

Then regenerate autoload:

```bash
composer dump-autoload && php bin/console cache:clear --env=dev
```

---

### `The command "se7enx:nglayouts:copy-subtree-with-layouts" does not exist`

**Check 1 — Bundle registered?**

```bash
grep "SevenxExpPlatformNGSubtreeCopyWithLayouts" config/bundles.php
```

If no output: add the bundle registration (see [§ 3](#3-register-the-bundle)).

**Check 2 — Cache cleared?**

```bash
php bin/console cache:clear --env=dev
```

**Check 3 — Autoload current?**

```bash
composer dump-autoload
php bin/console cache:clear --env=dev
```

**Check 4 — PHP binary mismatch?**

If the server does not have `php` in `$PATH`, use the full path:

```bash
/usr/bin/php bin/console list se7enx
```

---

### `Could not load location: Location with ID "X" not found`

The source or target location ID you specified does not exist, or is not
accessible to the admin user.

Find valid location IDs via the Ibexa admin UI (**Content structure** → hover a
content item → the URL contains `/location/view/ID`), or via SQL:

```sql
SELECT id, main_node_id, parent_node_id, name
FROM ezcontentobject_tree t
JOIN ezcontentobject_name n ON n.contentobject_id = t.contentobject_id
  AND n.content_version = t.contentobject_version
WHERE t.depth >= 1
ORDER BY t.path_string
LIMIT 50;
```

---

### `Subtree copy failed: ...`

Ibexa `LocationService::copySubtree()` will throw if:

- The source and target are in the same subtree path (would create a cycle).
- The current user (admin, ID 14) lacks permission to write to the target location.
- A database error occurred during the copy.

Inspect the full exception message printed after `Subtree copy failed:` and
check Symfony logs:

```bash
tail -50 var/log/dev.log
# or
tail -50 var/log/prod.log
```

---

### Warning: `child count mismatch at old=X`

```
Warning: child count mismatch at old=385 (old=5, new=3 children). Some children may be skipped.
```

This means Ibexa's `copySubtree()` produced a different number of children
than the original.  This can happen if:

- Some child content items were excluded from the copy due to permission
  restrictions.
- Some content types are configured to be non-copyable.

Children beyond the minimum of both counts are skipped for location ID mapping.
Layout rules for skipped children will **not** be duplicated.

---

### No layout rules created (`Layout rules created: 0`)

This is expected if the source locations have no published, enabled Netgen
Layouts resolver rules with `ibexa_location` or `ibexa_subtree` targets.

Verify which rules exist for a given location:

```sql
SELECT rt.type, rt.value, r.layout_uuid, rd.priority, rd.enabled
FROM nglayouts_rule_target rt
JOIN nglayouts_rule r ON r.id = rt.rule_id AND r.status = 1
JOIN nglayouts_rule_data rd ON rd.rule_id = r.id
WHERE rt.status = 1
  AND rt.type IN ('ibexa_location', 'ibexa_subtree')
  AND rt.value = '385'  -- replace with your source location ID
ORDER BY rd.priority DESC;
```

---

## 11. Removing the Bundle

1. **Remove from `config/bundles.php`** — delete the line:

   ```php
   App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle::class => ['all' => true],
   ```

2. **Clear the cache:**

   ```bash
   php bin/console cache:clear --env=dev
   php bin/console cache:clear --env=prod
   ```

3. **Optionally remove the source files:**

   ```bash
   rm -rf src/Bundle/SevenxExpPlatformNGSubtreeCopyWithLayouts/
   ```

   > This step is irreversible.  Commit the deletion to version control rather
   > than just removing files locally.

4. **Regenerate autoload:**

   ```bash
   composer dump-autoload
   ```

The bundle writes nothing to the database during installation — there are no
migrations to roll back.  Only the layout resolver rules created by the command
itself (in `nglayouts_rule` / `nglayouts_rule_target`) exist as side effects of
running the command; those must be cleaned up manually via the Netgen Layouts
admin UI if needed.
