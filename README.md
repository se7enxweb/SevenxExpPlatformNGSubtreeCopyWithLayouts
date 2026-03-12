# SevenxExpPlatformNGSubtreeCopyWithLayouts Bundle

Copies an Ibexa content subtree and automatically:

- Duplicates all matching [Netgen Layouts](https://netgen.io/layouts) resolver
  rules for the newly created location IDs.
- Re-applies the **section assignment** of each source content item to its
  corresponding copy.
- Re-applies all **object state** values of each source content item to its
  corresponding copy.

Ibexa's built-in `LocationService::copySubtree()` creates new locations with
new IDs but loses three pieces of metadata that are not part of content
versions: the Netgen Layouts resolver rules, the section assignment, and the
object state assignments.  This bundle restores all three.

---

## Files

| File | Purpose |
|---|---|
| `SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle.php` | Bundle entry point — loads `Resources/config/services.yaml` |
| `Resources/config/services.yaml` | Autowire / autoconfigure for the bundle namespace |
| `Service/SubtreeLayoutRuleCopier.php` | Core service: parallel tree traversal + Netgen Layouts rule duplication |
| `Service/SubtreeSectionCopier.php` | Service: re-applies section assignments to each copied content item |
| `Service/SubtreeObjectStateCopier.php` | Service: re-applies all object state values to each copied content item |
| `Command/CopySubtreeWithLayoutsCommand.php` | Console command `se7enx:nglayouts:copy-subtree-with-layouts` |

**Modified by bundle registration:** `config/bundles.php`

---

## Namespace

```
App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\
```

The project's existing `composer.json` autoload entry `"App\\": "src/"` covers
this namespace automatically — no additional PSR-4 entry is needed and none was
added (adding a second overlapping entry triggers Symfony's `DebugClassLoader`
to double-include the bundle class file, causing a fatal "Cannot redeclare
class" error).

---

## Installation

See [doc/INSTALL.md](doc/INSTALL.md) for full installation instructions.

The bundle is registered in `config/bundles.php`:

```php
App\Bundle\SevenxExpPlatformNGSubtreeCopyWithLayouts\SevenxExpPlatformNGSubtreeCopyWithLayoutsBundle::class => ['all' => true],
```

No other configuration is required.  All services are autowired.

---

## Usage

```bash
# Full copy — subtree + sections + object states + Netgen Layouts rules
php bin/console se7enx:nglayouts:copy-subtree-with-layouts <source-location-id> <target-parent-location-id>

# Preview what would happen — no content or rules are written
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42 --dry-run

# Copy content + sections + object states only; skip layout rules
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42 --skip-layout-rules

# Copy content + layout rules only; skip section and object state re-assignment
php bin/console se7enx:nglayouts:copy-subtree-with-layouts 385 42 --skip-sections --skip-object-states
```

### Arguments

| Argument | Description |
|---|---|
| `source-location-id` | Location ID of the root of the subtree to copy |
| `target-parent-location-id` | Location ID of the parent that will receive the copied subtree |

## Options

| Option | Description |
|---|---|
| `--dry-run` | Print what would be done without writing anything to the repository |
| `--skip-layout-rules` | Do **not** duplicate Netgen Layouts resolver rules |
| `--skip-sections` | Do **not** re-apply section assignments |
| `--skip-object-states` | Do **not** re-apply object state values |

---

## How It Works

1. **Copy the Ibexa content subtree.**  
   `LocationService::copySubtree(source, targetParent)` is called with the
   admin user (ID 14) as the current user reference, so the copy has full
   repository access.  The new root `Location` is returned.

2. **Build an old → new location ID map.**  
   Both the original and the copied tree are traversed in parallel (child by
   child, in order) via `LocationService::loadLocationChildren()`.  The result
   is an array of the form `[oldLocationId => newLocationId]` covering every
   node in the subtree.

3. **Re-apply section assignments.**  
   For each `[oldLocationId => newLocationId]` pair the section of the source
   content item (`SectionService::getSectionOfContent()`) is read and then
   assigned to the new content item (`SectionService::assignSection()`).  
   Ibexa's built-in copy inherits the section of the target parent; this step
   restores the original per-item section granularity.

4. **Re-apply object state assignments.**  
   For every `ObjectStateGroup` returned by
   `ObjectStateService::loadObjectStateGroups()`, the source content item's
   state is read via `ObjectStateService::getContentState()` and written to the
   new content item via `ObjectStateService::setContentState()`.

5. **Duplicate `ibexa_location` rules for every location.**  
   For each `oldLocationId` in the map, `nglayouts_rule_target` is queried
   directly via Doctrine DBAL (the `LayoutResolverService` API exposes no
   "find rules by target value" lookup).  Every published, enabled rule whose
   target type is `ibexa_location` and whose value equals the old location ID
   is duplicated:
   - A new rule draft is created via `LayoutResolverService::createRule()`.
   - A target is added via `LayoutResolverService::addTarget()`.
   - The rule is published via `LayoutResolverService::publishRule()`.

6. **Duplicate `ibexa_subtree` rules for the copy root.**  
   `ibexa_subtree` rules are subtree-wide, so they are only meaningful for the
   topmost copied location.  The service copies these for the source root into
   a matching rule targeting the new root.

7. **Priority offset.**  
   New rules receive `originalPriority − 1`.  This ensures the original rules
   always take precedence over the copies, preserving existing page behaviour.

---

## Supported Target Types

| Target type | Copied for |
|---|---|
| `ibexa_location` | Every location in the subtree |
| `ibexa_subtree` | The copy root only |
| `path_info_prefix` | **Not copied** — path-based rules depend on URL aliases that Ibexa manages separately |

---

## Dependencies

All injected via autowiring:

| Service | Used for |
|---|---|
| `Ibexa\Contracts\Core\Repository\LocationService` | `copySubtree()`, `loadLocation()`, `loadLocationChildren()` |
| `Ibexa\Contracts\Core\Repository\PermissionResolver` | Setting admin user as current reference |
| `Ibexa\Contracts\Core\Repository\UserService` | Loading admin user by ID |
| `Ibexa\Contracts\Core\Repository\SectionService` | `getSectionOfContent()`, `assignSection()` on each copied item |
| `Ibexa\Contracts\Core\Repository\ObjectStateService` | `loadObjectStateGroups()`, `getContentState()`, `setContentState()` on each copied item |
| `Netgen\Layouts\API\Service\LayoutResolverService` | `newRuleCreateStruct()`, `createRule()`, `newTargetCreateStruct()`, `addTarget()`, `publishRule()`, `loadRuleGroup()` |
| `Doctrine\DBAL\Connection` | Direct query of `nglayouts_rule_target` by target value |

---

## Database Schema Reference

The following nglayouts tables are read (never written to directly):

```sql
-- status = 1 means PUBLISHED in all nglayouts tables
nglayouts_rule        (id, status, uuid, rule_group_id, layout_uuid, description)
nglayouts_rule_data   (rule_id, enabled, priority)
nglayouts_rule_target (id, status, uuid, rule_id, type, value)
```

The root rule group UUID is fixed in the nglayouts schema:

```
00000000-0000-0000-0000-000000000000
```

All new rules are created under this group, matching the behaviour of rules
created via the Netgen Layouts admin UI.

---

## License & Copyright

Copyright © 1998 - 2026, 7x. All rights reserved.

SevenxExpPlatformNGSubtreeCopyWithLayouts is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

SevenxExpPlatformNGSubtreeCopyWithLayouts is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with SevenxExpPlatformNGSubtreeCopyWithLayouts in [LICENSE](LICENSE.md).
If not, see <http://www.gnu.org/licenses/>.

See [COPYRIGHT.md](COPYRIGHT.md) for full copyright and license assignment details.
