# Brindle Dashboard Helper access controls

Brindle Dashboard Helper adds three client and employee roles, limits access to development tools, and displays their users and permissions under Settings → Brindle Dashboard Helper. Development-tool restrictions apply to all three custom roles. Brindle Employee retains normal appearance editing and the SEO toolbar; client appearance access is limited below.

| Role | Role slug | Initial capability source |
| --- | --- | --- |
| Client Administrator | `brindle_client_administrator` | Administrator |
| Client Editor | `brindle_client_editor` | Editor |
| Brindle Employee | `brindle_employee` | Administrator |

Source capabilities are copied when each role is first created. Registration runs on activation and `init`, applying the explicit restrictions below to both new and existing roles. Other later role customizations are preserved. Restrictions are stored as `false`, so permissions denied to every role still appear in the comparison table.

## Standard WordPress capabilities

Native capabilities handle plugin and theme lifecycle restrictions. The shared `edit_theme_options` permission also needs the scoped menu/widget integration documented below.

| Capability denied | Effect |
| --- | --- |
| `activate_plugins` | Blocks plugin activation and deactivation, including the mapped `activate_plugin`, `deactivate_plugin`, and `deactivate_plugins` checks. |
| `install_plugins` | Blocks plugin installation and the mapped `upload_plugins` check. |
| `update_plugins` | Blocks plugin updates. |
| `delete_plugins` | Blocks plugin deletion. |
| `edit_plugins` | Blocks the plugin file editor. |
| `edit_themes` | Blocks the theme file editor. |
| `switch_themes`, `install_themes`, `update_themes`, `delete_themes` | Blocks theme switching, installation, updates and deletion. |
| `edit_theme_options` | Denied to client roles; native menu/widget requests receive scoped access. Brindle Employee retains this permission. |
| `update_core`, `install_languages`, `update_languages` | Blocks core and language-pack installation/updates. |
| `import`, `export` | Blocks WordPress content import and export. |
| `view_site_health_checks`, `export_others_personal_data`, `erase_others_personal_data` | Blocks technical diagnostics and personal-data export/erasure. The last two also need a meta-capability denial because core maps them to `manage_options`. |

`manage_options`, `edit_posts`, and `edit_pages` retain their source-role values. Core Settings is restricted separately so shared permissions remain available to permitted plugin features and content editing. The privacy-policy page remains editable through Pages; its core configuration screen is blocked.

WordPress applies its normal user overrides and multisite permission rules to native capabilities. The permission table displays stored role values, rather than every contextual permission check.

## Brindle Dashboard Helper page access

`brindle_helper_can_view_settings()` requires the built-in `administrator` role or an actual multisite Super Administrator. It controls both menu registration and direct rendering. A denied direct render returns HTTP 403.

Checking `manage_options` alone would admit the administrator-based custom roles. Checking `is_super_admin()` alone on a single site would also admit custom roles with `delete_users`, so that check is used only on multisite.

## Plugin specific capabilities

These plugins use shared WordPress capabilities for at least some administrative screens. Separate Brindle capabilities let us restrict those tools while preserving unrelated permissions.

| Tool | Brindle capability | Capability used for users without a custom role |
| --- | --- | --- |
| WP Pusher | `brindle_manage_wppusher` | `manage_options` |
| WP Migrate | `brindle_manage_wp_migrate` | `export`, or `manage_network_options` on multisite |
| ACF configuration | `brindle_manage_acf` | `manage_options` |
| GenerateBlocks administration | `brindle_manage_generateblocks` | `manage_options` |
| HFCM | `brindle_manage_hfcm` | `manage_options` |
| WP Umbrella settings | `brindle_manage_wp_umbrella` | `manage_options` |
| Font Awesome settings | `brindle_manage_font_awesome` | `manage_options` |
| Duplicate Page settings | `brindle_manage_duplicate_page` | `manage_options` |
| Site appearance administration | `brindle_manage_appearance` | `edit_theme_options` |
| Core Settings | `brindle_manage_core_settings` | `manage_options` |

`brindle_helper_map_tool_capabilities()` maps these names through WordPress's `map_meta_cap` filter. Users with any of the three custom roles receive `do_not_allow` for these tools, even if a user-level override grants the Brindle capability, except that Brindle Employee can use `brindle_manage_appearance` through its native `edit_theme_options` permission. Other users must satisfy the underlying capability in the table. These capabilities are not independent switches for granting tool access to the restricted roles.

Integrations live in [brindle-helper.php](brindle-helper.php), [appearance.php](appearance.php), and [core-access.php](core-access.php); this implementation does not patch third-party plugin files.

### ACF configuration

The supported `acf/settings/capability` filter replaces ACF's shared `manage_options` requirement with `brindle_manage_acf`. ACF uses that setting for its Field Groups, Post Types, Taxonomies, Options Pages configuration, Tools, Updates, upgrade screens, and administrative AJAX checks. Its internal post types also use the setting for editing and deletion.

On the `brindle-lola-2` theme, Client Editor cannot access Theme Settings or its Site Setting, Amenities Manager, and Amenities Page Manager forms. The supported `acf/get_options_page` filter changes those pages to `do_not_allow` for this role, covering menus and direct save requests. Both administrator-based roles retain these content forms. Explicit page slugs cover ACF's redirect/reparenting of the Theme Settings parent to Site Setting; children of the original parent are also covered. Other themes and unrelated options pages retain their existing capabilities. Fields attached to ordinary pages remain available for content editing. Creating or editing the definitions of those forms is restricted through ACF's configuration capability.

Installed source: [ACF post types](../advanced-custom-fields-pro/acf.php), [admin menus](../advanced-custom-fields-pro/includes/admin/admin.php), [admin permission helper](../advanced-custom-fields-pro/includes/api/api-helpers.php), and [content options forms](../advanced-custom-fields-pro/pro/admin/admin-options-page.php).

### WP Migrate

The supported `wpmdb_ajax_cap` filter supplies `brindle_manage_wp_migrate` for WP Migrate's AJAX and REST authorization checks. Its menu hard-codes `export` on a single site, so Brindle Dashboard Helper also replaces the menu capability and guards `wp-migrate-db` and `wp-migrate-db-pro` page requests before plugin handlers run. Downloads reached through the migration screen are covered by that page guard.

WordPress content export is now separately disabled under the core administration policy. The WP Migrate integration still isolates its other access paths and preserves existing access for unrestricted users.

Installed source: [menu registration](../wp-migrate-db-pro/class/Common/Plugin/Menu.php), [AJAX and downloads](../wp-migrate-db-pro/class/Common/Http/Http.php), and [REST permissions](../wp-migrate-db-pro/class/Common/Http/WPMDBRestAPIServer.php).

### WP Pusher

The installed menu registrations hard-code `manage_options`, rather than a separate tool capability. Brindle Dashboard Helper replaces capabilities for the parent and every submenu, guards `wppusher` and `wppusher-*` page requests, and changes Settings API authorization through `option_page_capability_*` for these groups: `pusher-token-settings`, `pusher-license-settings`, `pusher-gh-settings`, `pusher-bb-settings`, `pusher-gl-settings`, and `pusher-enable-logging`.

WP Pusher's administrative deployment actions already require `update_plugins` and `update_themes`; denying `update_plugins` blocks those actions through its existing check. Token-authenticated deployment webhooks are a separate authentication path and are not changed by this role policy.

Installed source: [menus and settings groups](../wppusher/Pusher/Pusher.php) and [deployment authorization](../wppusher/Pusher/Dispatcher.php).

### GenerateBlocks administration

Use the supported capability filters wherever they cover the action: `generateblocks_conditions_capability`, `generateblocks_overlays_capability`, `generateblocks_editor_access_capability`, `generateblocks_form_capability`, and `generateblocks_manage_classes_capability`. For custom-role users, management checks require `brindle_manage_generateblocks`; the `use` context retains its original capability so blocks and existing resources can be used in page content.

The installed version also needs three targeted integrations:

- Replace the hard-coded capabilities of the `generateblocks` parent menu and its submenus, and guard `generateblocks-*` page requests.
- Use WordPress's `register_post_type_args` filter to restrict editing, creation, publishing, and deletion for `gblocks_*`, `gb_access_profile`, and `gb_access_set` resources on custom-role requests. Existing read permissions remain intact. Direct resource editors, the Local Patterns admin screen (`wp_block`), and the `gblocks_pattern_collections` taxonomy screen are guarded separately. The shared `wp_block` capability definitions are preserved for editor usage.
- Use `rest_request_before_callbacks` for routes under `/generateblocks/v…/` and `/generateblocks-pro/v…/` whose permission callback is named `update_settings_permission` or `manage_options_permission`. These callbacks hard-code `manage_options`. Editor endpoints using `edit_posts` remain available.

Installed source: [core dashboard](../generateblocks/includes/dashboard.php), [core REST checks](../generateblocks/includes/class-rest.php), [Pro REST checks](../generateblocks-pro/includes/class-rest.php), [pattern-library REST checks](../generateblocks/includes/pattern-library/class-pattern-library-rest.php), and [Pro resource classes](../generateblocks-pro/includes/).

### HFCM

The installed version hard-codes `manage_options` for menus and snippet handlers. Brindle Dashboard Helper replaces parent and submenu capabilities, guards all `hfcm-*` page requests, and checks HFCM actions during `admin_init` at priority 1. This includes the `hfcm-request` AJAX action and submissions containing `hfcm_save_security_settings`, before HFCM's own handlers execute. Rendering existing header and footer snippets on the frontend is unchanged.

Installed source: [menus, AJAX registration, settings and snippet handlers](../header-footer-code-manager/99robots-header-footer-code-manager.php).

### WP Umbrella settings

WP Umbrella (installed in `wp-health`) hard-codes `manage_options` for its settings menu and local settings handlers. Brindle Dashboard Helper replaces the `wp-umbrella-settings` menu capability and denies direct page requests using `brindle_manage_wp_umbrella`. Its `admin_init` guard runs before local AJAX and admin-post callbacks, denying these actions even when requested outside the settings page:

| Handler | Restricted actions |
| --- | --- |
| `admin-ajax.php` | `wp_health_proxy`, `wp_health_login`, `wp_health_allow_tracking`, `wp_health_disallow_tracking`, `wp_umbrella_register`, `wp_umbrella_valid_api_key`, `wp_umbrella_check_api_key`, `wp_umbrella_allow_one_click_access`, `wp_umbrella_disallow_one_click_access`, `wp_umbrella_repair_ajax` |
| `admin-post.php` | `wp_umbrella_support_option`, `wp_umbrella_regenerate_secret_token`, `wp_umbrella_hardening_options`, `wp_umbrella_clean_transients`, `wp_umbrella_clean_activity_log_buffer`, `wp_umbrella_clean_redirect_table`, `wp_umbrella_clean_htaccess`, `wp_umbrella_test_ping` |

The explicit action list covers account connection, credentials, tracking, one-click access, support/hardening configuration and settings-page maintenance tools. WP Umbrella's independently authenticated remote monitoring/API paths, scheduled work and personal two-factor authentication controls retain their existing authorization. There is no blanket restriction on the `wp-umbrella` REST namespace or all `wp_umbrella_*` AJAX actions.

Installed source: [settings page](../wp-health/src/Actions/Admin/Pages.php), [local settings handlers](../wp-health/src/Actions/Admin/), [AJAX handlers](../wp-health/src/Actions/Admin/Ajax/), and [remote API authorization](../wp-health/src/Core/Models/TraitApiController.php). Review the settings slug and local action names after updates.

### Font Awesome settings

Font Awesome hard-codes `manage_options` for its `font-awesome` settings page and configuration REST controllers. Brindle Dashboard Helper replaces the menu capability and guards direct page requests using `brindle_manage_font_awesome`. `rest_request_before_callbacks` denies routes under `/font-awesome/v…/config`, `/font-awesome/v…/preference-check` and `/font-awesome/v…/conflict-detection`, covering configuration saves, API token changes, settings preference validation, troubleshooting mode and conflict/blocklist management.

The separate `/font-awesome/v1/api` and `/font-awesome/v1/api/token` endpoints keep their original `manage_options` or `edit_posts` checks so content editors can search and choose icons. Rendering existing icons retains its existing behavior. Built-in Administrators retain settings access.

Installed source: [settings menu](../font-awesome/includes/class-fontawesome.php), [configuration controller](../font-awesome/includes/class-fontawesome-config-controller.php), [preference validation](../font-awesome/includes/class-fontawesome-preference-check-controller.php), [conflict controller](../font-awesome/includes/class-fontawesome-conflict-detection-controller.php), and [icon chooser API controller](../font-awesome/includes/class-fontawesome-api-controller.php). Review the menu slug and REST routes after updates.

### Duplicate Page settings

Duplicate Page hard-codes `manage_options` for its `duplicate_page_settings` menu and settings callback, with no separate settings capability filter. Brindle Dashboard Helper replaces that menu capability with `brindle_manage_duplicate_page` and denies direct requests before the callback runs. Settings save submissions post to the same screen, so the guard covers both viewing and saving without removing the shared `manage_options` permission from otherwise permitted features.

The separate `admin_action_dt_duplicate_post_as_draft` handler, its per-post nonce checks, and the plugin's content-editing permissions retain their existing behavior. All three roles keep the Duplicate This actions on editable posts/pages and can authorize page duplication. Existing editor and toolbar duplication controls retain their behavior. Built-in Administrators retain settings access.

Installed source: [menu, duplication handler and links](../duplicate-page/duplicatepage.php) and [settings form/save callback](../duplicate-page/inc/admin-settings.php). Review the settings slug and save path after plugin updates.

## Login page

[login.php](login.php) uses WordPress's `login_enqueue_scripts`, `login_headerurl` and `login_headertext` hooks to style the native login and password-recovery screens. No form fields, authentication handlers or third-party plugin code are replaced. [login.css](login.css) creates a white left-hand login panel and a separate decorative right-hand panel on desktop, removes the form card, and gives the login button a dark neutral treatment. At widths of 782px and below, the right-hand panel is hidden and the form uses the full screen width.

[assets/brindle-logo-dark.svg](assets/brindle-logo-dark.svg) preserves the supplied full Brindle Digital Marketing logo and changes its fill to `#1d2327`. The logo links to `https://brindledigital.com/`; [login.js](login.js) adds `target="_blank"` and `rel="noopener noreferrer"` to core's logo anchor, whose accessible text announces the new tab. The destination remains Brindle's website even when JavaScript is unavailable.

The right-hand panel uses the supplied **Zoomed-Out Wilder iPhone Website Mockup.jpg**, bundled as [assets/brindle-login-photo.jpg](assets/brindle-login-photo.jpg). The 1536×1024 JPEG is about 105 KB. CSS centers the cover crop, and the image URL includes an asset version to invalidate the old photo cache. The login page makes no remote image request.

## Navigation cleanup

The WordPress logo (`wp-logo`) and its child links are removed from the toolbar for every role, including built-in Administrators, in both wp-admin and the frontend. This site-wide branding change uses WordPress's toolbar API and does not alter permissions.

Every role receives a static Brindle Digital logo at the top-left of the desktop screen. The `wp_before_admin_bar_render` hook renders the logo as an accessible link using the adjacent `site-name` toolbar node’s destination: the homepage in admin, and the dashboard on the front end. Full logos, wordmarks and collapsed marks share this link, with no hover effect; native keyboard focus remains available. Branding renders after toolbar cleanup and before core binds the nodes. The top toolbar shifts right by the sidebar width, and desktop navigation starts below the 68px logo area. The supplied white SVG is bundled locally as [assets/brindle-logo.svg](assets/brindle-logo.svg); its viewBox trims the original canvas whitespace without changing the artwork. [assets/brindle-mark.svg](assets/brindle-mark.svg) reuses the original B path for collapsed desktop layouts. No logo is hotlinked. The wordmark is limited to 112px wide with 24px horizontal padding; the collapsed mark is limited to 16px wide. At widths of 782px and below, the logo is hidden and the toolbar, page spacing and sidebar use WordPress's native mobile layout. The background stays dark for contrast across admin color schemes. These styles load on every admin screen, including network/user administration. Block editors use the same Brindle wordmark as the desktop front-end toolbar. In fullscreen mode, the brand panel is 32px tall with no vertical padding, keeping the editor’s back button and controls unobstructed, including when the sidebar preference is folded.

The front-end WordPress toolbar uses [assets/brindle-wordmark.svg](assets/brindle-wordmark.svg), which retains the supplied SVG's seven Brindle letter paths and omits the Digital Marketing line. `wp_before_admin_bar_render` renders the same linked brand container; the front-end version fits the 32px toolbar height. `body_class` mirrors core's `mfold` and `unfold` user settings, including the browser cookie, so toolbar links begin at the same 160px or 36px offset as wp-admin, including automatic folding at 783–960px. [front-toolbar.css](front-toolbar.css) loads only when the toolbar is shown and keeps desktop account controls on the first row, truncating crowded link labels while retaining their dropdowns. At mobile widths, the logo is hidden and the toolbar remains full width. Anonymous visitors and users with the toolbar disabled receive no branding markup or styles; the public site's navigation is unaffected.

Custom roles receive the additional navigation cleanup below, with the employee exceptions shown. Built-in Administrators and other roles retain those items. Comments and SEO cleanup are presentation changes. Client Elements editing is additionally restricted by the appearance policy below. The permissions table continues to display actual stored capabilities.

| Item | Integration |
| --- | --- |
| Comments | Remove WordPress's `edit-comments.php` sidebar menu and `comments` toolbar node. |
| Dashboard Command K search | Remove the core `wp_enqueue_command_palette_assets` callback from `admin_enqueue_scripts` on custom-role admin requests, preventing the dashboard palette and its shortcut from initializing. Remove the `command-palette` toolbar node as well. Script packages remain registered for block-editor dependencies; the editor's own command palette is preserved. |
| GeneratePress Elements | Client roles lose the Appearance submenu `edit.php?post_type=gp_elements` and toolbar nodes `gp_elements-menu` and `new-gp_elements`. Brindle Employee retains these entries and native editing access. |
| GenerateBlocks Overlay Panels | Remove the `gb_overlays-menu` toolbar node. Its sidebar and management access are already covered by the GenerateBlocks restrictions above. Existing overlays remain available for content usage. |
| SEO notifications | Client roles lose SEOPress’s `seopress` toolbar parent and children. Brindle Employee retains the native dropdown on both admin and front-end screens. SEO fields in the page editor and existing plugin permissions remain intact. |

Toolbar cleanup runs on `wp_before_admin_bar_render` after menu registration, both in wp-admin and on the frontend. Removing a parent node prevents its descendants from being rendered.

Installed source: [WordPress dashboard palette initializer](../../../wp-includes/script-loader.php), [core toolbar](../../../wp-includes/admin-bar.php), [GeneratePress Elements toolbar](../gp-premium/elements/elements.php), [GenerateBlocks overlays toolbar](../generateblocks-pro/includes/overlays/class-overlays.php), and [SEOPress toolbar and notifications](../wp-seopress/inc/admin/admin-bar/admin-bar.php). Review these node IDs and the core enqueue callback after updates.

## Dashboard widgets

For all three custom roles, `brindle_helper_clean_dashboard_widgets()` runs on `wp_dashboard_setup` at priority 999 and uses WordPress's `remove_meta_box()` API after widget registration. It removes the following widgets from every dashboard column, including saved user layouts:

| Widget | Registration ID | Provider |
| --- | --- | --- |
| At a Glance | `dashboard_right_now` | WordPress |
| Quick Draft | `dashboard_quick_press` | WordPress |
| WordPress Events and News | `dashboard_primary` | WordPress |
| Popup Analytics | `pum_analytics_basic` | Popup Maker |
| Portfolio Activity | `vpf_recent_portfolio_activity` | Visual Portfolio |
| Activity | `dashboard_activity` | WordPress |

Other widgets retain their existing behavior. Built-in Administrators and other roles keep their widgets. This is a display change and does not alter editing or analytics capabilities.

Popup Maker and Visual Portfolio require their specific widget IDs because they do not expose separate capabilities for these dashboard panels. Installed source: [WordPress dashboard](../../../wp-admin/includes/dashboard.php), [Popup Maker dashboard registration](../popup-maker/classes/Controllers/WP/Dashboard.php), and [Visual Portfolio dashboard registration and glance counts](../visual-portfolio/classes/class-dashboard.php). Review the widget IDs after plugin updates.

### Brindle information widget

[dashboard.php](dashboard.php) combines information from the seven active custom widgets into one native **Brindle Dashboard** widget. The local copy's Ultimate Dashboard records were the approved starting point; this content has not been compared with the live dashboard. Text and destinations are bundled in Brindle Dashboard Helper, independent of Ultimate Dashboard or its stored records. The original data remains intact.

| Content | Local source record | Visibility and destination |
| --- | --- | --- |
| Introduction | 2047 | Sidebar editing guidance and partnership/support message; the personalized welcome greeting is omitted. |
| Update Specials | 1952 | Rent Fetch must be loaded (`RENTFETCH_VERSION`), the Properties post type must be editable, and at least one non-trashed property must exist. Draft, pending, future, private and published properties count; auto-drafts and trash do not. Links to the Properties list. |
| Update Pop-Up Announcement | 1952 | Popup Maker must be loaded (`popup_maker_config()`), and the Popup post type must be editable. Links to its list, allowing a first announcement to be created even if no popups exist yet. |
| Rent Fetch Settings | 1132 | Link to `admin.php?page=rentfetch-options`; shown when Rent Fetch is loaded and the user has its existing `manage_options` permission. |
| View Leads | 1133 | Gravity Forms must be loaded, the user must have `gravityforms_view_entries` or `gform_full_access`, and at least one non-trashed form must exist. Active and inactive forms count, since inactive forms can retain leads. Links to Gravity Forms entries. |
| Support | 1129 | Website support message and a direct link to the [Hipporello support form](https://brindledigitalmarketing.hipporello.net/desk/form/b93fef0e6ae444a5a98d75f9f086a00a). |
| Quick Links | 1130 | Edit Pages when permitted, plus Visit Website using this installation's own site URL. |
| Leave a Review | 1128 | Existing invitation and Google review link. |

The introduction is followed by **Your website** (Edit Pages, Update Specials, Update Pop-Up Announcement, Visit Website), **Management** (View Leads, Rent Fetch Settings), then **Help & feedback** (support and review messages/links). Empty sections are omitted. Visit Website, support and review links remain available to all dashboard users. Operational shortcuts do not grant permissions.

The inactive Leads video widget (record 1131) is not registered. The original widget styles, remote logo, button treatments, inline styles and fixed heights are omitted. Output uses ordinary headings, paragraphs, lists and links. Scoped styles in `admin.css` give section headings a compact bold treatment and place light 1px dividers between links within each section. Links opening a new tab have WordPress's external-link Dashicon and screen-reader text announcing the new tab. No additional widget scripts are needed. The consolidated widget reuses `brindle_helper_welcome` in the normal column at high priority, preserving that widget's per-user visibility, collapse and ordering preferences; the other six separate boxes are no longer registered.

Installed integration references: [Rent Fetch plugin marker](../rentfetch/rentfetch.php), [Rent Fetch menu](../rentfetch/lib/admin/options-pages-setup/admin-menu-setup.php), [Popup Maker plugin marker](../popup-maker/popup-maker.php), [Gravity Forms entries menu](../gravityforms/gravityforms.php), [Gravity Forms form-ID lookup](../gravityforms/forms_model.php), and WordPress post-type editing capabilities. Review the plugin markers and native access requirements after updates. No third-party plugin code or role capabilities are changed by these shortcuts.

## Appearance access

Access is enforced for direct requests and APIs as well as navigation.

| Control | Client Administrator | Client Editor | Brindle Employee |
| --- | --- | --- | --- |
| Menus (`brindle_edit_menus`) | Yes | Yes | Yes |
| Widgets (`brindle_edit_widgets`) | Yes | No | Yes |
| Design, Customize, Fonts, Font Library, GeneratePress, Elements, Popup Themes | No | No | Yes |
| Theme selection, installation, updates, deletion and Theme File Editor | No | No | No |
| Lola Theme Settings and its three content forms | Yes | No | Yes |

Brindle Employee retains `edit_theme_options` and `brindle_manage_appearance`. Its Appearance submenu preserves all registered entries except `themes.php` and `theme-editor.php`. Direct theme selection/installation/file-editor requests remain denied; plugin pages hosted under `themes.php` remain available. Native GeneratePress, Elements, popup-theme and font permissions, REST callbacks and legacy save handlers apply to this role. Its appearance toolbar keeps native design links except Themes.

### Client roles

WordPress shares `edit_theme_options` across menus, widgets and site design. Store it as `false` on both client roles and map it to `brindle_edit_menus` or `brindle_edit_widgets` only during recognized requests:

- `nav-menus.php` and menu AJAX actions `add-menu-item`, `menu-get-metabox`, `menu-quick-search`, `menu-locations-save`. Legacy `delete-post` is allowed only for a `nav_menu_item` target.
- `widgets.php` and widget AJAX actions `widgets-order`, `save-widget`, `delete-inactive-widgets`.
- Actual REST callbacks under `/wp/v2/menus`, `/wp/v2/menu-items`, `/wp/v2/menu-locations`, `/wp/v2/widgets`, `/wp/v2/widget-types` and `/wp/v2/sidebars`.

A REST-context stack prevents nested requests from borrowing another route’s permission. Other appearance requests, `customize` and `edit_css` are denied to client roles. The early `_admin_menu` pass preserves the Appearance parent during core reparenting; later cleanup leaves Menus and permitted Widgets. Client toolbar links follow this policy.

GeneratePress Elements (`gp_elements`), popup themes (`popup_theme`) and GeneratePress fonts (`gp_font`) receive denied editing/creation/deletion capabilities on client requests. Reads of existing resources and selection of existing popup themes remain available. Direct lists/editors are denied before plugin handlers run. Brindle Employee retains the original post-type capabilities.

GeneratePress REST namespaces `/generatepress-pro/v…/` and `/generatepress-font-library/v…/` are denied to client roles. Legacy submissions are intercepted at `admin_init` priority 1: `generate_multi_activate`, `gp_premium_license_key`, `generate_action`, `generate_reset_action`, `generate_reset_customizer`, and `generate_package_*_activate_package` / `generate_package_*_deactivate_package`, including submissions to unrelated admin screens.

Direct `themes.php`, `theme-install.php`, `site-editor.php`, `font-library.php` and `customize.php` requests and plugin pages `generate-options` / `generatepress-*` are denied to client roles. Client Editor also loses the Lola Theme Settings forms through ACF’s supported options-page capability filter, as described above.

Installed source: [core menu editor](../../../wp-admin/nav-menus.php), [widget editor](../../../wp-admin/widgets.php), [menu and widget AJAX handlers](../../../wp-admin/includes/ajax-actions.php), [GeneratePress Elements registration](../gp-premium/elements/class-post-type.php), [GeneratePress dashboard REST](../gp-premium/inc/class-rest.php), [GeneratePress font REST](../gp-premium/font-library/class-font-library-rest.php), [GeneratePress legacy handlers](../gp-premium/inc/legacy/), and [Popup Maker post types](../popup-maker/classes/Controllers/PostTypes.php). Review route namespaces, resource types and legacy submission keys after updates.

## Core administration and user management

All three roles retain Dashboard Home, Posts with categories/tags, Pages, Media, their own Profile, and the approved Appearance controls. Comments remain hidden as previously requested. Core Updates and its toolbar badge, Available Tools, Import, Export, Site Health, Export Personal Data, Erase Personal Data, and the core Settings screens are removed from navigation and denied through direct requests. Plugin submenus under Tools and Settings retain their separate access rules.

Core Settings includes General, Connectors, Writing, Reading, Discussion, Media, Permalinks, and Privacy. `option_page_capability_*` requires the denied `brindle_manage_core_settings` capability for the native groups, including the raw options editor and Connectors. `admin_init` also blocks direct native screen and save requests. Both reads and writes to `/wp/v2/settings` are denied, covering core settings and connector credentials. A `/wp/v2/connectors` route, if registered, is also denied.

`manage_options` remains available to the administrator-based roles for permitted plugin features. `manage_privacy_options` retains its native mapping. Core adds that settings permission to editing the configured privacy-policy page; a targeted `edit_post`/`edit_page` mapping removes only the added `manage_options` requirement for that page and its revisions, preserving all ordinary page-editing checks. This lets the Client Editor maintain policy content without gaining settings access. Changing core privacy configuration remains blocked. Personal-data export and erasure receive explicit `map_meta_cap` denials because their native mappings would otherwise ignore the stored false values.

### Operational account management

| Actor | Roles they can assign and manage |
| --- | --- |
| Client Administrator | Client Administrator, Client Editor, Subscriber |
| Client Editor | Own Profile only |
| Brindle Employee | Client Administrator, Client Editor, Brindle Employee, Subscriber |

The two administrator-based roles retain native Users and Add User screens. Core content roles such as unrestricted Editor/Author/Contributor are not assignable here: new operational content accounts use the custom roles so they inherit the dashboard restrictions. Existing accounts with any role outside the actor's allowed list are protected, including true Administrators, unmanaged legacy roles, and employee accounts when the actor is a client. Multisite Super Administrators are also protected. Protected users may appear in the users list, but cannot be edited, promoted, deleted, removed, or have application passwords managed by these roles.

`editable_roles` limits role choices in native forms and REST role validation. Object-specific `edit_user`, `promote_user`, `delete_user`, and `remove_user` checks enforce protection for direct requests, bulk actions, password resets and REST writes. Own Profile editing remains available to every role; self-deletion/removal is denied. `user_profile_update_errors` validates form role assignments and new-user defaults. REST requests validate assigned/default roles before callbacks, rejecting forbidden roles, empty role lists, and privilege escalation. Bulk submissions using core's special `none` role are also denied.

Native user operations retain WordPress's existing checks, including multisite restrictions; this plugin does not grant network user-management capabilities. True Administrators without a custom role retain their normal access.

User Switching is active locally. Its default authorization already checks `edit_user`, but its optional `switch_users` override can bypass that check. The target-account policy also covers its `switch_to_user` capability, so restricted roles cannot impersonate protected accounts even with that override. Switching to an allowed operational account still requires User Switching's own permission. Installed source: [User Switching capability filters](../user-switching/user-switching.php).

Installed source: [core menus](../../../wp-admin/menu.php), [capability mappings](../../../wp-includes/capabilities.php), [user forms](../../../wp-admin/includes/user.php), [bulk user handlers](../../../wp-admin/users.php), [user REST controller](../../../wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php), [core Settings API](../../../wp-admin/options.php), and [connector credential registration](../../../wp-includes/connectors.php).

## JSON plugin updates

Brindle Dashboard Helper bundles [Plugin Update Checker 5.7](https://github.com/YahnisElsts/plugin-update-checker/tree/v5.7) with its MIT license. `brindle_helper_update_checker()` initializes once on `plugins_loaded`, including cron and CLI requests, and targets `https://raw.githubusercontent.com/jonschr/brindle-helper/master/update.json`. It uses the JSON metadata path rather than GitHub API update discovery. WordPress provides its normal plugin details, update notifications and update installation UI; the existing custom-role plugin-update denials remain enforced.

[update.json](update.json) matches the plugin’s version and points to the corresponding GitHub tag ZIP. To release, publish that tag (including the bundled library/assets), then publish the matching manifest on `master`. Keep the plugin header, JSON version and download tag synchronized. The remote manifest and release ZIP must be publicly available before updates can be delivered. PUC fixes the archive directory name during installation.

## Popup Maker marker

The green exclamation marker is hidden for every role in both the sidebar and front-end toolbar using narrowly scoped `.pum-notifications-marker` rules in `admin.css` and `front-toolbar.css`. Popup editing and the plugin’s notification panel remain available. Source: [Popup Maker marker registration](../popup-maker/classes/Controllers/Admin/ToolbarNotifications.php).

## Maintaining integrations

Prefer a standard WordPress capability when it controls the requested feature without removing unrelated access. If a plugin shares a broader capability, use its supported capability filter. Keep menu, request, or REST adapters only for paths that the capability filter does not cover.

After updating an integrated plugin, compare its current menu slugs, settings groups, internal post types, AJAX actions, and REST permission callbacks with the entries above. GenerateBlocks' REST callback names and the WP Pusher settings-group names are explicit dependencies of this implementation. When upstream adds a suitable capability filter, replace the matching adapter and rerun the access checks.

These integrations were checked against the local installation: ACF Pro 6.8.10, WP Migrate Pro 2.7.11, WP Pusher 3.0.18, GenerateBlocks 2.4.1, GenerateBlocks Pro 2.7.1, GenerateCloud 1.2.0, HFCM 1.1.46, WP Umbrella 2.27.4, Font Awesome 5.2.1, and Duplicate Page 4.5.9. Other plugins can independently modify role capabilities; the comparison table reflects their stored changes.

## Verification

Run from the WordPress root with Brindle Dashboard Helper and the integrated plugins active:

```sh
wp eval-file wp-content/plugins/brindle-helper/tests/roles.php
wp eval-file wp-content/plugins/brindle-helper/tests/updates.php
wp eval-file wp-content/plugins/brindle-helper/tests/admin-access.php
wp eval-file wp-content/plugins/brindle-helper/tests/admin-access.php http
```

The update check exercises JSON parsing and native update hooks with a mocked future release; it makes no network request or installation. The role check exercises creation, repeat registration, explicit denials, Brindle Dashboard Helper page access, and table rendering. The integration check requires an Administrator, a user in each custom role, and a published page. It checks tool capabilities, GenerateBlocks management versus usage, ACF configuration and role-specific theme forms, client menu/widget scopes, employee appearance access, and representative REST authorization. Font Awesome probes check settings denial, Administrator settings access and retained icon chooser permissions. Write probes run the actual REST permission checks, then intercept dispatch before saving data or calling the external icon API. HTTP checks also exercise every restricted WP Umbrella local settings action.

Duplicate Page checks verify the Duplicate This page action and run the plugin's real nonce/permission handler with the copy method replaced by a probe, preventing content creation. HTTP checks verify both direct settings access and a settings submission with a valid nonce are denied for every custom role.

The optional HTTP check runs only on a `.localhost` development site. It verifies authenticated direct admin URLs, sidebar and toolbar cleanup, and absence of the dashboard command-palette initializer for all three roles, including the frontend toolbar, using temporary sessions that it removes afterward. It also checks allowed employee appearance pages, client denial of theme-form reads and valid-nonce submissions, and role-specific SEO toolbar visibility. It makes no content changes. Browser verification should also confirm that a custom-role user can open an existing page and use GenerateBlocks, and that a built-in Administrator retains access to the tools and normal navigation.
