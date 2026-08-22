=== Spinda – Export & Import Posts, Post Meta, Products, Taxonomies, Users & User Meta ===
Contributors: microcodes
Tags: post export import, product export import, user export import, taxonomies & tax-meta export import
Requires at least: 5.0
Tested up to: 7.0.4
Requires PHP: 7.2
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export and import posts, custom post types, users, WooCommerce data, and metadata between WordPress sites using JSON.

== Description ==

Spinda is a powerful WordPress plugin designed to simplify data migration between WordPress websites. It allows you to export and import posts, custom post types, users, taxonomies, metadata, and WooCommerce data in a structured JSON format.

Whether you're migrating a site, backing up content, or transferring data between environments, Spinda provides a clean and efficient solution.

= Key Features =

* **📦 Post & Custom Post Type Export/Import** – Transfer any public post type with taxonomies, taxonomy metadata, ACF fields, comments, and featured images
* **👥 User Export/Import** – Migrate users by role with complete profile info, capabilities, and metadata (ACF fields supported)
* **🛍️ WooCommerce Products Export/Import** – Export/Import products with variations, categories, tags, attributes, reviews, and metadata
* **🏷️ Taxonomies Export/Import** – Export taxonomy terms independently with hierarchy, term counts, thumbnails, and full metadata
* **⚡ JSON-Based System** – Lightweight and portable data format, works between any WordPress installations
* **🔄 Batch Processing** – Handles large datasets with cron jobs, progress bars, and auto-refresh
* **🖼️ ACF & Meta Support** – Full support for ACF galleries, images, files, links, and serialized data

= How It Works =

1. Go to the **Spinda** menu in your WordPress admin
2. Choose what to export & import from the plugin.

= Exporting Data =
- Select post type or user role or woo product
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

1. **Post Types Import** - Export post-types like post, page, product, cpts & products.
2. **Taxonomies Import** - Export users with user's meta.
3. **Woocommerce products export** - Export Woocommerce products & product-meta export
4. **Woocommerce products import** - Export Woocommerce products & product-meta import of exported json.


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

= 2.0.1 =
* Post Meta HTML contains support.
* Tax Meta HTML contains support.
* Taxonomy support with tax-meta & improved tax parent child linking.

= 2.0.0 =
* WooCommerce products with product-meta & product categories, brands, attributes, reviews export & import support.
* Post-types & post-meta with taxonomies & tax-meta export & import support.
* Taxonomies with tax-meta customized export & import support.
* User with user-meta export & import support.

= 1.0.1 =
* Taxonomies export & import module added

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.1 =
Added Post and Tax Meta HTML support, alongside tax-meta integration and improved parent-child taxonomy linking.

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