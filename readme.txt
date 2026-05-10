=== Spinda – Export Import Data ===
Contributors: microcodes
Tags: post export, post import, product export import, user export, user import
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export and import posts, custom post types, users, WooCommerce data, and metadata between WordPress sites using JSON.

== Description ==

Spinda is a powerful WordPress plugin designed to simplify data migration between WordPress websites. It allows you to export and import posts, custom post types, users, taxonomies, metadata, and WooCommerce data in a structured JSON format.

Whether you're migrating a site, backing up content, or transferring data between environments, Spinda provides a clean and efficient solution.

= Key Features =

* **📦 Post & Custom Post Type Export/Import** – Transfer any public post type with it's taxonomies & tax-meta easily
* **👥 User Export/Import** – Migrate users with roles and metadata
* **💬 Comments Support** – Optionally include comments
* **🛍️ WooCommerce Support** – Export products and variations
* **⚡ JSON-Based System** – Lightweight and portable data format

= How It Works =

1. Go to the **Spinda** menu in your WordPress admin
2. Choose **Posts** or **Users**
3. Select the **Export** or **Import** tab

= Exporting Data =
- Select post type or user role
- Choose what to include (meta, taxonomies, media, etc.)
- Click **Export**
- Download JSON file

= Importing Data =
- Upload the JSON file
- Click **Import**
- Data will be recreated on your site

= Why Choose Spinda? =

* **Simple & Clean UI** – Easy-to-use admin interface
* **Flexible Export Options** – Choose exactly what to include
* **Portable Format** – JSON makes migration easy and readable
* **No Lock-in** – Our plugin is completly free to use, no pro version it offers, so no barrier.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to **Plugins → Add New**
3. Search for "Spinda Export Import Data"
4. Click **Install Now**
5. Activate the plugin

= Manual Installation =

1. Upload the `spinda` folder to `/wp-content/plugins/`
2. Activate via the **Plugins** menu
3. Access via the **Spinda** admin menu

= Requirements =

- WordPress 5.0 or higher
- PHP 7.2 or higher
- WooCommerce (optional, for product support)

== Screenshots ==

1. **Post Types Export** - Export post-types like post, page, product, cpts.
2. **Users's Export** - Export users with user's meta.
3. **Import Posts/Users** - Import posts, users etc.

== Frequently Asked Questions ==

= Can I migrate data between two WordPress sites? =

Yes! Export data from one site and import it into another using JSON files.

= Does it support custom post types? =

Yes, all public custom post types are supported.

= Will it export custom fields (meta)? =

Yes, you can include post meta, term meta, and user meta.

= Does it support WooCommerce? =

Yes, Spinda supports exporting and importing WooCommerce products and variations.

= Are images included? =

Featured images can be included during export.

= Is it safe to use? =

Yes. Spinda uses WordPress security standards including:
- Nonce verification
- Capability checks
- Sanitization and escaping

= Can I choose what to export? =

Yes, you can toggle:
- Taxonomies
- Meta fields
- Comments
- Media
- Variations

= Will it overwrite existing data? =

Imported data is added to your site. You should test on staging before importing into production.

= Does it work on large sites? =

Yes, but for very large datasets, consider increasing server limits (memory, execution time).


== Changelog ==

= 1.0.1 =
* Taxonomies export & import module added

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.1 =
Taxonomies export & import module added

= 1.0.0 =
Initial release of Spinda – Export Import Data.

== Additional Information ==

= Plugin Support =

For support and feature requests, visit:
https://microcodes.in

= Disclaimer =

Always back up your database before performing import operations, especially on production sites.

= Privacy Notice =

Spinda does not collect or transmit any personal data. All exported data remains within your control and is stored locally as JSON files.