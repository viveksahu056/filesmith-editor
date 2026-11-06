=== FileSmith Editor ===
Contributors: wpwebguru
Tags: file editor, theme editor, plugin editor, code editor
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A theme and plugin file editor with the familiar layout, tabs, quick open, PHP syntax check and automatic backups. Upgrade to Pro for file management, trash, compare, format, activity log and more.

== Description ==

The built-in Theme File Editor and Plugin File Editor are plain. FileSmith Editor keeps the same familiar layout, with the code editor on the left, the file list on the right and the theme or plugin selector on top, and adds tabs, quick open and a safety net.

**Features in the free version**

* Syntax highlighting with the same code editor WordPress uses.
* Open several files as tabs. Each tab keeps its own changes, cursor and undo history.
* Quick open (Ctrl+P, or Cmd+P on Mac): type part of a file name and jump to it. Arrow keys and Enter pick a file, Esc closes.
* Image preview: click a PNG, JPG, GIF, WebP, BMP, ICO or AVIF file to see it, with its size and type.
* Fullscreen mode (Esc to exit) gives the editor and the file list the whole browser window.
* Your open theme or plugin and open files are remembered, so a page reload brings you back to the same place.
* Editor options in Settings: font size, theme, tab size, wrap long lines and show line numbers.
* Refresh button (or Ctrl+Alt+R): reloads the open file and the file list from disk without reloading the page.
* Changed-on-disk warning: before saving, the plugin checks that the file was not changed elsewhere since you opened it, and asks before overwriting.
* Automatic backup before every save. The latest 5 backups of each file are kept, and you can load one back into the editor.
* PHP syntax check before saving, so a typo cannot break your site.
* Clear save errors: when a file cannot be saved, you are told why (permissions, expired session, server limit, firewall and more).
* Works with themes and plugins from one screen.
* Settings page: allowed file types, excluded folders, editor font size and theme.
* Access control by role for view, edit and backups. Roles that cannot use the editor, such as Editor, are shown with a warning and locked.
* Built-in updates: an alert on the Dashboard, the Plugins screen and the FileSmith screens when a new version is available, with one-click update.

**FileSmith Editor Pro**

Pro adds create, upload, rename, move, duplicate and delete, a right-click menu, trash and restore, compare with disk and a line-by-line backup diff, Format for CSS, JS and JSON, JSON, JS and CSS checks, a site test with automatic rollback, unsaved-draft recovery, zip download, up to 100 backups per file, access by user and an activity log. Pro installs over the free version and keeps your settings and backups.

**Built with security in mind**

* Every request is protected by a nonce and a capability check (`edit_themes` or `edit_plugins`).
* Respects `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS`, so hosts that turn file editing off stay protected.
* Paths are validated, so nothing outside the selected theme or plugin folder can be read or changed.
* Only text based file types can be edited.
* Updates are downloaded over HTTPS from the author's site only and are checked against a SHA-256 hash before they are installed.

Editing live code is always risky. FileSmith Editor adds a safety net, but you should still keep regular full backups of your site.

**Updates and privacy**

FileSmith Editor is not hosted on WordPress.org, so it checks the author's site (plugins.wpwebguru.com) for new versions, at most once every 12 hours. The check is a plain HTTPS request to a small JSON file. No site address, user data or other information is sent by the plugin. To turn the check off, add `define( 'FSED_DISABLE_UPDATE_CHECK', true );` to `wp-config.php`.

== Installation ==

1. Download the plugin zip from https://plugins.wpwebguru.com/filesmith-editor/
2. In the admin, go to Plugins, Add New, Upload Plugin, choose the zip and click Install Now. Or unzip it and upload the `filesmith-editor` folder to `/wp-content/plugins/`.
3. Activate the plugin through the Plugins screen.
4. Go to FileSmith, then Editor, in the admin menu.
5. Choose Theme or Plugin, pick the item to edit, and select a file from the list.

To upgrade to Pro, upload the Pro zip the same way. WordPress offers to replace the current version. Your settings and backups are kept.

== Frequently Asked Questions ==

= I cannot see the editor. =

The editor is hidden when `DISALLOW_FILE_EDIT` or `DISALLOW_FILE_MODS` is set in `wp-config.php`, or when your user cannot edit themes or plugins. On a multisite network only super admins can use it.

= Why can my Editor role not see FileSmith? =

FileSmith needs the WordPress capability `edit_themes` or `edit_plugins`, and the Editor role does not have it. The settings can only limit access, never give more than WordPress allows. The Access control tab shows a warning for roles that cannot be given access.

= Are single-file plugins supported? =

Not yet. A plugin needs to live in its own folder.

= Which files can I edit? =

php, css, scss, less, js, json, txt, md, html, htm, xml, svg, po, pot, ini, yml and yaml. Developers can change the list with the `fsed_editable_extensions` filter.

= How do I see changes made outside the editor? =

Click Refresh (or press Ctrl+Alt+R). It reloads the open file and the file list from disk. If you have unsaved changes in the file, you are asked first. If you save a file that changed on disk after you opened it, you are asked before it is overwritten.

= How do I open a file quickly? =

Press Ctrl+P (Cmd+P on Mac), type part of the file name and press Enter. It searches the files of the selected theme or plugin. Letters in order also match, so `clsapi` finds `class-api.php`.

= Where are the backups stored? =

In a folder with a random name inside your uploads folder. The folder is protected with an `index.php` and an `.htaccess` file, and backups use the `.bak` extension so they are never executed. Each backup also records the name of the user who saved the change. The latest 5 backups of each file are kept in the free version. If a backup cannot be created, the file is not saved.

= Does the PHP syntax check catch every error? =

No. It catches parse errors, such as a missing semicolon or bracket. It cannot catch runtime errors, such as calling a function that does not exist. Pro adds a site test that loads your site after each PHP save and rolls the file back if it fails.

= Can I preview images? =

Yes. Click an image in the file list. Images are read-only and up to 5 MB can be previewed. Change the limit with the `fsed_max_preview_size` filter. SVG files stay editable as text.

= How do updates work? =

When a new version is released, you see an alert on the Dashboard, the Plugins screen and the FileSmith screens. Click Update now, or use the Check for updates button in FileSmith, Settings, About. The update is installed with the normal WordPress updater, after its SHA-256 hash is verified.

= Can I turn off the update check? =

Yes. Add `define( 'FSED_DISABLE_UPDATE_CHECK', true );` to `wp-config.php`, or use the `fsed_disable_update_check` filter.

= How do I keep the backups when I delete the plugin? =

Add `define( 'FSED_KEEP_DATA', true );` to your `wp-config.php` before deleting the plugin. Otherwise the plugin options and backup folder are removed on uninstall.

= Which hooks are available for developers? =

* `fsed_editable_extensions` filters the editable file types.
* `fsed_max_file_size` filters the maximum size of an editable file.
* `fsed_pre_save` runs before a file is saved and can stop the save by returning a `WP_Error`.
* `fsed_check_php_syntax` turns the PHP syntax check on or off.
* `fsed_max_preview_size` filters the maximum size of a previewed image.
* `fsed_require_backup` decides whether a failed backup stops the save.
* `fsed_max_backups` filters how many backups are kept for each file (never more than 5 in the free version).
* `fsed_disable_update_check` turns the update check on or off.
* `fsed_update_hosts` filters the hosts an update package may be downloaded from.

= Which constants can I set in wp-config.php? =

* `FSED_KEEP_DATA` keeps the settings and backups when the plugin is deleted.
* `FSED_DISABLE_UPDATE_CHECK` turns off the update check.

== Changelog ==

= 1.0.0 =
* Initial release of the free version.
* Editor with tabs, Quick open (Ctrl+P), image preview, fullscreen and session restore.
* Automatic backups (5 per file), PHP syntax check and changed-on-disk warning.
* Refresh button and detailed save error messages.
* Settings and access control by role.
* Built-in update alerts with one-click update and hash verification.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
