# Monument Builder for WooCommerce

A complete custom product configurator for monument/headstone retailers, built as a
WooCommerce plugin. Customers design engraved monuments in the browser — per-line text,
fonts, colors, sizes — preview the result on a live canvas, and check out; the shop gets
a production-ready proof image attached to every order.

## What it does

**Customer-facing configurator**
- Monument product pages with per-line engraving text and font customization
- Live canvas preview (Fabric.js) — background swaps automatically when the customer
  picks a color/size variation
- Gallery integration — canvas background syncs with variation image selection
- Configured design flows through cart → checkout → order without template hacks

**Order & production workflow**
- Proof image rendered from the canvas and saved as a WordPress media attachment on
  every order
- Proof thumbnail in the admin order view + a proof column on the WooCommerce orders
  list for at-a-glance visual scanning
- Proof image embedded in the customer order confirmation email
- Printable production sheet per order for the shop floor
- Custom order statuses for the monument fulfillment pipeline

**Catalog management**
- `mnb_monument` custom post type for monument products
- Bulk import (CSV template included) and Elementor import for large catalogs
- Template system and shortcodes for embedding the configurator anywhere

## Architecture

16 single-responsibility classes, each with a static `init()` boot method:

| Class | Responsibility |
| ----- | -------------- |
| `MNB_Post_Type` | Monument CPT registration |
| `MNB_Product_Fields` | Per-line text/font product fields |
| `MNB_Canvas` | Fabric.js canvas setup + data localization |
| `MNB_Shortcode` / `MNB_Template` | Frontend embedding |
| `MNB_Cart` / `MNB_Order` / `MNB_Order_Status` | Cart-to-order flow |
| `MNB_Proof` | Canvas → proof image → media attachment |
| `MNB_Production_Sheet` | Shop-floor production sheet |
| `MNB_Bulk_Import` / `MNB_Elementor_Import` | Catalog imports |
| `MNB_Epitaph` | Epitaph text handling |
| `MNB_Ajax` / `MNB_Admin` | AJAX endpoints + admin UI |

All functions prefixed `mnb_`, constants `MNB_`, text domain `monument-builder`.

## Requirements

- WordPress 6.0+, PHP 8.0+, WooCommerce 7.0+

## Installation

1. Upload the `monument-builder` folder to `wp-content/plugins/`.
2. Activate **Monument Builder** in Plugins.
3. Configure under the Monument Builder admin menu; import catalog via the bundled
   `bulk-import-template.csv`.

## Notes

- Built with AI-assisted development (Claude), tested against a live WooCommerce store.
- The canvas layer uses Fabric.js 5.3.1 via CDN with Google Fonts for the live preview.
