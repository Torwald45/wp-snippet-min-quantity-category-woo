# WP Snippet: Min Quantity by Category for Woo

Enforces a minimum purchase quantity for Woo products assigned to selected product categories. Selected categories can be excluded (for example a "sets" subcategory where a single item may be bought).

## Features

- Minimum quantity per category list, step stays 1 (6, 7, 8...)
- Quantity below the minimum is raised automatically with a notice, no hard error
- Works on the product page, add to cart (including AJAX), classic cart and block cart (Store API)
- Variable products: the rule is checked on the parent product
- Excluded categories override included ones
- Fixes items already in the cart when the rule changes
- No admin UI, configuration by constants
- Unique prefix (torwald45_mqc_) for all functions, constants and filters

## Configuration

Add to wp-config.php:

    define( 'TORWALD45_MQC_INCLUDE', 'flies' );
    define( 'TORWALD45_MQC_EXCLUDE', 'sets' );
    define( 'TORWALD45_MQC_MIN', 6 );

- TORWALD45_MQC_INCLUDE - product_cat slugs, comma separated. Without it the snippet does nothing.
- TORWALD45_MQC_EXCLUDE - product_cat slugs, comma separated (optional).
- TORWALD45_MQC_MIN - minimum quantity (default 6).

Per product override with a filter:

    add_filter( 'torwald45_mqc_min', function ( $min, $product_id ) {
        return 123 === $product_id ? 12 : $min;
    }, 10, 2 );

## Installation

Method 1: Code Snippets plugin - paste the content of torwald45-mqc.php without the opening <?php tag.

Method 2: mu-plugin - copy torwald45-mqc.php to wp-content/mu-plugins/.

Method 3: functions.php - paste the content into your child theme functions.php.

## Notes

- Category match is direct: the product must be assigned to the category itself. Child categories are not included automatically - list them in TORWALD45_MQC_INCLUDE if needed.
- Adding a product that is already in the cart adds at least the minimum again.

## Requirements

- WP 6.5 or higher
- Woo 8.0 or higher
- PHP 7.4 or higher

## License

GPL-2.0-or-later

## Author

Torwald45
