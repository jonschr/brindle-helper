# Brindle Dashboard Helper

Creates three roles using the standard WordPress Administrator or Editor capabilities as a starting point:

| Role | Slug | Based on |
| --- | --- | --- |
| Client Administrator | `brindle_client_administrator` | Administrator |
| Client Editor | `brindle_client_editor` | Editor |
| Brindle Employee | `brindle_employee` | Administrator |

| Standard WordPress capabilities | Client Administrator | Client Editor | Brindle Employee |
| --- | --- | --- | --- |
| `read`, `upload_files` | Yes | Yes | Yes |
| `edit_posts`, `edit_others_posts`, `edit_published_posts`, `edit_private_posts`, `publish_posts`, `read_private_posts` | Yes | Yes | Yes |
| `delete_posts`, `delete_others_posts`, `delete_published_posts`, `delete_private_posts` | Yes | Yes | Yes |
| `edit_pages`, `edit_others_pages`, `edit_published_pages`, `edit_private_pages`, `publish_pages`, `read_private_pages` | Yes | Yes | Yes |
| `delete_pages`, `delete_others_pages`, `delete_published_pages`, `delete_private_pages` | Yes | Yes | Yes |
| `moderate_comments`, `manage_categories`, `manage_links` | Yes | Yes | Yes |
| `list_users`, `create_users`, `edit_users`, `promote_users`, `delete_users`, `remove_users` | Limited¹ | No | Limited¹ |
| `manage_options` | Yes² | No | Yes² |
| `edit_dashboard` | Yes | No | Yes |
| `edit_theme_options` | Scoped³ | Scoped³ | Yes |
| `activate_plugins`, `install_plugins`, `update_plugins`, `delete_plugins`, `edit_plugins` | No | No | No |
| `switch_themes`, `install_themes`, `update_themes`, `delete_themes`, `edit_themes` | No | No | No |
| `update_core`, `install_languages`, `update_languages`, `import`, `export` | No | No | No |
| `view_site_health_checks`, `export_others_personal_data`, `erase_others_personal_data` | No | No | No |

¹ Client Administrators can manage Client Administrators, Client Editors and Subscribers. Employees can also manage other Employees. Built-in Administrators and other protected accounts cannot be managed.

² The capability is retained, but core Settings screens are blocked.

³ Client Administrators can edit menus and widgets; Client Editors can edit menus only.

Other capabilities retain their source-role values. Existing role customizations and WordPress multisite rules still apply.
