# G-Snippets Plugin — Overview & Code Structure

This document explains what the G-Snippets plugin does, how it works, and how the code is organized. Use it for onboarding, debugging, or extending the plugin.

---

## What the Plugin Does

**G-Snippets** is a WordPress plugin that lets you create **reusable content snippets** and **inject them automatically** into posts and pages. Snippets are edited with the Gutenberg (block) editor and shown before or after the main content based on configurable rules.

### Core behavior

1. **Snippets as content**  
   Each snippet is a `g_snippet` post: it has a title and body (Gutenberg content). The body is the HTML/blocks that get injected.

2. **Where they appear**  
   Snippets are injected into `the_content`:

   - **Before content**: snippet(s) → then post body
   - **After content**: post body → then snippet(s)

   You choose “before” or “after” per snippet.

3. **When a snippet is shown**  
   A snippet is shown on a given post/page only if **all** of these are true:

   - Snippet is **Active** (ACF true/false).
   - Post type is in the snippet’s **Post Types** (e.g. `post`, `page`).
   - If **Categories** are set, the post has at least one of those categories.
   - If **Include Posts** is set, the current post is in that list.
   - The current post is **not** in **Exclude Posts**.

4. **Multiple snippets**  
   If several snippets match the same post:

   - **Settings → “All that matches”**: every matching snippet is shown (before/after groups each sorted by priority).
   - **Settings → “First matches by Priority”**: only the first (lowest priority number) before-snippet and first after-snippet are shown.

5. **Priority**  
   Lower number = higher priority (e.g. 5 runs before 10). Used to order snippets and, in “first only” mode, to pick the single before/after snippet.

6. **Extras**
   - **Settings**: global “display all vs first”, space gap between snippets, custom CSS in `wp_head`.
   - **Category importer**: CSV/XLSX import to assign post categories (for use with snippet category rules).
   - **List table**: admin columns show Post Types, Location, Priority, Active, Include/Exclude counts.

---

## Dependencies

- **WordPress** 5.0+ (for Gutenberg).
- **Advanced Custom Fields (ACF)** (free or Pro). Used for all snippet options (post types, location, priority, active, include/exclude, categories). The plugin checks for ACF on activation and shows an admin notice if it’s missing.

---

## Code Structure

### File layout

```
g-snippets/
├── g-snippets.php                    # Bootstrap: constants, ACF check, activation, loader
├── includes/
│   ├── class-g-snippets.php          # Main orchestrator
│   ├── class-g-snippets-post-type.php
│   ├── class-g-snippets-acf.php
│   ├── class-g-snippets-content-injector.php
│   ├── class-g-snippets-list-table.php
│   ├── class-g-snippets-settings.php
│   └── class-g-snippets-category-importer.php
├── admin/
│   └── css/
│       └── admin.css
├── README.md
└── PLUGIN-OVERVIEW.md                # This file
```

All PHP under `includes/` uses the **`G_Snippets`** namespace and follows a **singleton** pattern: `get_instance()` and private constructor.

---

### Bootstrap: `g-snippets.php`

- Defines constants: `G_SNIPPETS_VERSION`, `G_SNIPPETS_PLUGIN_FILE`, `G_SNIPPETS_PLUGIN_DIR`, `G_SNIPPETS_PLUGIN_URL`.
- **ACF check**
  - `g_snippets_check_acf_dependency()`: looks for ACF constant/functions/classes or `is_plugin_active` for ACF.
  - **Activation**: if ACF is missing, plugin is deactivated and `wp_die` with message.
  - **Runtime**: on `plugins_loaded`, if ACF is missing, an admin notice is shown and the main class is not loaded.
- **Hooks**:
  - Activation: flush rewrite rules.
  - Deactivation: flush rewrite rules.
  - Uninstall: `g_snippets_uninstall()` (currently no data removal).
- **Loader**: On `plugins_loaded`, calls `g_snippets_init()` which requires `includes/class-g-snippets.php` and returns `G_Snippets\G_Snippets::get_instance()`.

---

### Main orchestrator: `class-g-snippets.php`

- **Role**: Loads all includes and wires components.
- **`load_dependencies()`**: `require_once` for the six class files (post type, ACF, content injector, list table, settings, category importer).
- **`init_hooks()`**:
  - `init` priority 5 → `init_components()`.
  - `admin_enqueue_scripts` → `enqueue_admin_scripts()`.
- **`init_components()`**: Gets singleton of each component (order matters only in that post type and ACF should be ready before list table/settings):
  - `Post_Type::get_instance()`
  - `ACF_Fields::get_instance()`
  - `Content_Injector::get_instance()`
  - `List_Table::get_instance()`
  - `Settings::get_instance()`
  - `Category_Importer::get_instance()`
- **`enqueue_admin_scripts($hook)`**: Enqueues `admin/css/admin.css` on:
  - Screens where `post_type === 'g_snippet'`
  - Settings page (`g-snippets-settings`)
  - Category importer page (`g-snippets-category-importer`)

---

### Custom post type: `class-g-snippets-post-type.php`

- Registers post type **`g_snippet`** on `init` priority 20.
- **Labels**: “Snippets” in menu, “Snippet” singular, etc.
- **Args**:
  - Not public (no front-end archive/permalink).
  - `show_ui` true, `show_in_menu` true, `capability_type` `post`, `supports` `['title', 'editor']`.
  - **`show_in_rest` => true** so the block editor is used for snippet content.

---

### ACF fields: `class-g-snippets-acf.php`

- Registers one ACF field group for `post_type == g_snippet` (sidebar placement).
- **Fields** (all stored as post meta on `g_snippet` posts):
  - **g_snippet_post_types** (checkbox): which post types (from `get_post_types(['public' => true])`); default `['post']`.
  - **g_snippet_categories** (taxonomy checkbox, category): limit to posts in these categories; empty = any.
  - **g_snippet_location** (select): `before` | `after`; default `after`.
  - **g_snippet_priority** (number): 1–999; default 10.
  - **g_snippet_active** (true_false): default 1.
  - **g_snippet_include_posts** (post_object, multiple): only these posts; empty = all matching post type.
  - **g_snippet_exclude_posts** (post_object, multiple).
- Hook: `acf/init` and `init` (fallback) at priority 20. Uses a static `$registered` flag to avoid duplicate registration.

---

### Content injection: `class-g-snippets-content-injector.php`

- **Hook**: `the_content` at priority **15**.
- **Flow in `inject_snippet($content)`**:
  1. Bail if ACF missing or not singular; get `$post`.
  2. `get_matching_snippets($post_id, $post_type)` → list of snippet posts that match (cached per `$post_id_$post_type`).
  3. Read settings: display option (all vs first), space gap.
  4. Split snippets into “before” and “after” by `g_snippet_location`; if display option is “first”, keep only the first before and first after.
  5. Wrap each snippet in `<div id="g-snippet-{id}" class="g-snippet g-snippet-{id}">…</div>`; content from `get_snippet_content($id)` (post content filtered by `g_snippets_snippet_content`).
  6. Apply `apply_space_gap()` to before/after groups (margin-bottom between items).
  7. Concatenate: before snippets + `$content` + after snippets; return filtered by `g_snippets_content_to_display`.
- **Matching** (`snippet_matches()`):  
  Post type in snippet’s post types; if snippet has categories, post has at least one; if snippet has include list, post in list; post not in exclude list.
- **Ordering**: `get_active_snippets()` loads all published `g_snippet` by `g_snippet_priority` ASC; then `sort_snippets_by_priority()` uses `get_field('g_snippet_priority')` with default 10 for stable ordering.
- **Caching**: `$this->snippet_cache[$cache_key]` stores result of `get_matching_snippets()` per request to avoid repeated queries.

---

### List table: `class-g-snippets-list-table.php`

- **Filters**: `manage_g_snippet_posts_columns` (add columns), `manage_edit-g_snippet_sortable_columns` (priority, active).
- **Actions**: `manage_g_snippet_posts_custom_column` (fill cells), `pre_get_posts` (meta_key/orderby for priority and active).
- **Columns added after Title**: Post Types, Location, Priority, Active, Include count, Exclude count. All read via `get_field()`.

---

### Settings: `class-g-snippets-settings.php`

- **Option name**: `g_snippets_settings` (single array).
- **Submenu**: under `edit.php?post_type=g_snippet`, “Settings”, slug `g-snippets-settings`.
- **Sections/fields**:
  - Display: **display_options** (all / first), **space_gap** (e.g. `20px`).
  - Custom CSS: **custom_css** (textarea); sanitized with `wp_strip_all_tags`, output in `wp_head` in `<style id="g-snippets-custom-css">`.
- **Public API**: `get_settings()`, `get_display_option()`, `get_space_gap()`.

---

### Category importer: `class-g-snippets-category-importer.php`

- **Submenu**: “Post Category Assignment”, slug `g-snippets-category-importer`.
- **Flow**: Upload CSV or XLSX → parse (CSV native; XLSX via PhpSpreadsheet if available) → store in transient → preview step (choose “post slug” column and “category name” column) → import: for each row, find post by slug, get or create category by name, add category to post (additive). Results (success/skipped/errors) stored in transient and shown on results step.
- **Security**: `manage_options`, `check_admin_referer('g_snippets_importer_action')`, sanitized inputs.

---

## Key hooks and filters

| Hook/Filter                     | Where                                 | Purpose                                                  |
| ------------------------------- | ------------------------------------- | -------------------------------------------------------- |
| `the_content` (priority 15)     | Content_Injector                      | Inject snippet HTML before/after content                 |
| `g_snippets_snippet_content`    | Content_Injector::get_snippet_content | Filter raw snippet post content                          |
| `g_snippets_content_to_display` | Content_Injector::inject_snippet      | Filter final assembled string (before + content + after) |

---

## Constants and globals

- **Constants**: `G_SNIPPETS_VERSION`, `G_SNIPPETS_PLUGIN_FILE`, `G_SNIPPETS_PLUGIN_DIR`, `G_SNIPPETS_PLUGIN_URL`.
- **Post type**: `g_snippet`.
- **ACF meta keys**: `g_snippet_post_types`, `g_snippet_categories`, `g_snippet_location`, `g_snippet_priority`, `g_snippet_active`, `g_snippet_include_posts`, `g_snippet_exclude_posts`.

---

## Summary

G-Snippets adds a private CPT `g_snippet` (Gutenberg + ACF) and injects its content into `the_content` based on post type, categories, include/exclude, and priority. Settings control “all vs first” and spacing; the category importer helps assign categories so snippet category rules can be used. The codebase is modular (one class per concern) and uses singletons and WordPress/ACF APIs throughout.
