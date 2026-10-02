<?php
/**
 * WooCommerce values for Product schema: {{product_price}}, {{product_currency}},
 * {{product_availability}}, {{product_sku}}, {{product_rating}} and
 * {{product_review_count}}. Only loaded when WooCommerce is active, and only
 * through the plugin's own public filters.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Integrations;

use UnlimitedSchema\API\Hooks;

class WooCommerce
{
    /** Product field => token used as its default. */
    private const PRODUCT_DEFAULTS = [
        'sku'           => 'product_sku',
        'price'         => 'product_price',
        'priceCurrency' => 'product_currency',
        'availability'  => 'product_availability',
        'ratingValue'   => 'product_rating',
        'reviewCount'   => 'product_review_count',
    ];

    private const AVAILABILITY = [
        'instock'     => 'https://schema.org/InStock',
        'outofstock'  => 'https://schema.org/OutOfStock',
        'onbackorder' => 'https://schema.org/BackOrder',
    ];

    public static function register(): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }
        add_filter(Hooks::TOKENS, [self::class, 'tokens']);
        add_filter(Hooks::TOKEN_VALUES, [self::class, 'tokenValues'], 10, 2);
        add_filter(Hooks::TYPE_DEFINITION, [self::class, 'productDefaults'], 10, 2);
    }

    public static function tokens(array $tokens): array
    {
        return $tokens + [
            'product_price'        => __('Product price', 'unlimited-schema'),
            'product_currency'     => __('Store currency', 'unlimited-schema'),
            'product_availability' => __('Product stock status', 'unlimited-schema'),
            'product_sku'          => __('Product SKU', 'unlimited-schema'),
            'product_rating'       => __('Product average rating', 'unlimited-schema'),
            'product_review_count' => __('Product review count', 'unlimited-schema'),
        ];
    }

    /**
     * @param \WP_Post|null $post
     */
    public static function tokenValues(array $values, $post): array
    {
        $values += array_fill_keys(self::PRODUCT_DEFAULTS, '');
        $product = $post instanceof \WP_Post && $post->post_type === 'product' ? wc_get_product($post->ID) : null;
        if (!$product) {
            return $values;
        }

        $reviews = (int) $product->get_review_count();
        $price = (string) $product->get_price();

        return array_merge($values, [
            'product_price'        => $price === '' ? '' : wc_format_decimal($price, wc_get_price_decimals()),
            'product_currency'     => get_woocommerce_currency(),
            'product_availability' => self::AVAILABILITY[$product->get_stock_status()] ?? '',
            'product_sku'          => (string) $product->get_sku(),
            'product_rating'       => $reviews > 0 ? (string) $product->get_average_rating() : '',
            'product_review_count' => $reviews > 0 ? (string) $reviews : '',
        ]);
    }

    /**
     * New Product schemas start wired to the product's own data.
     */
    public static function productDefaults($definition, $name)
    {
        if ($name !== 'Product' || !is_array($definition)) {
            return $definition;
        }
        foreach (self::PRODUCT_DEFAULTS as $field => $token) {
            if (isset($definition['fields'][$field]) && empty($definition['fields'][$field]['default'])) {
                $definition['fields'][$field]['default'] = '{{' . $token . '}}';
            }
        }
        return $definition;
    }
}
