=== Monument Builder ===
Stable tag: 4.0.0

== Changelog ==

= 4.0.0 =
* Canvas background updates automatically when customer selects a color/size variation
* Reset on variation change — selecting a new variation swaps BG image seamlessly
* Proof image generated from canvas and saved as a WordPress media attachment per order
* Proof thumbnail shown in the admin order view
* Proof image embedded in customer order confirmation email
* Proof column added to the WooCommerce orders list for quick visual scan
* Print-ready Engraving Production Sheet accessible from each admin order
* Production sheet shows all engraving fields, fonts, positions, proof image, notes area, sign-off fields
* "Print Engraving Sheet" button on every admin order that contains monument engraving
* Supports both v1/v2 raw engraving data and v3+ canvas JSON on production sheet
* Custom font file upload in Settings (.ttf / .woff) — fonts load into canvas via FontFace API
* Font file URLs passed to canvas JS for accurate preview rendering

= 3.0.0 =
* Fabric.js canvas text overlay — live preview on monument image
* Draggable, resizable text objects
* Double monument layout with 3-column field grid

= 2.0.0 =
* Shortcode [mnb_engraving_fields] + Elementor widget

= 1.0.0 =
* Initial release

== File Structure ==

monument-builder/
├── monument-builder.php
├── includes/
│   ├── class-mnb-post-type.php
│   ├── class-mnb-product-fields.php
│   ├── class-mnb-canvas.php
│   ├── class-mnb-shortcode.php            ← font URL loader added
│   ├── class-mnb-cart.php
│   ├── class-mnb-order.php
│   ├── class-mnb-order-status.php
│   ├── class-mnb-admin.php                ← font file upload added
│   ├── class-mnb-ajax.php                 ← font URL endpoint added
│   ├── class-mnb-proof.php                ← NEW: proof image generation
│   └── class-mnb-production-sheet.php     ← NEW: printable engraving sheet
├── assets/
│   ├── css/mnb-frontend.css
│   ├── css/mnb-admin.css
│   └── js/mnb-canvas.js                   ← variation swap + font loading + proof
└── README.txt
