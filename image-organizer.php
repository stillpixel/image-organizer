<?php

/**
 * Plugin Name: Image Organizer Gallery
 * Plugin URI:  https://gao.gov/
 * Description: Simple image organizer/gallery with metadata modal and download button.
 * Version:     1.0.0
 * Author:      Ron Rattie
 * Text Domain: image-organizer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

class Image_Organizer_Gallery
{

    public function __construct()
    {
        add_action('init', [$this, 'register_taxonomies']);
        add_shortcode('image_organizer', [$this, 'render_gallery_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_taxonomies()
    {
        // Allow built-in category and post_tag taxonomies on attachments
        register_taxonomy_for_object_type('category', 'attachment');
        register_taxonomy_for_object_type('post_tag', 'attachment');
    }



    public function register_assets()
    {
        $version = '1.0.0';

        wp_register_style(
            'image-organizer-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
            [],
            $version
        );

        wp_register_script(
            'image-organizer-frontend',
            plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
            ['jquery'],
            $version,
            true
        );
    }

    /**
     * Shortcode: [image_organizer ids="1,2,3" columns="4" limit="12"]
     */
    public function render_gallery_shortcode($atts)
    {
        $atts = shortcode_atts(
            [
                'ids'             => '',
                'columns'         => 4,
                'limit'           => 12,
                'categories'      => '', // category slugs
                'tags'            => '', // tag slugs
                'show_filter'     => 'false',
                'filter_taxonomy' => 'category', // 'category' or 'tag'
            ],
            $atts,
            'image_organizer'
        );

        wp_enqueue_style('image-organizer-frontend');
        wp_enqueue_script('image-organizer-frontend');

        $ids = array_filter(array_map('trim', explode(',', $atts['ids'])));

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => intval($atts['limit']),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (! empty($ids)) {
            $args['post__in']       = $ids;
            $args['posts_per_page'] = -1;
            $args['orderby']        = 'post__in';
        }

        // Tax query based on categories/tags
        $tax_query = [];

        // Categories
        if (! empty($atts['categories'])) {
            $category_slugs = array_filter(array_map('trim', explode(',', $atts['categories'])));
            if (! empty($category_slugs)) {
                $tax_query[] = [
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $category_slugs,
                ];
            }
        }

        // Tags
        if (! empty($atts['tags'])) {
            $tag_slugs = array_filter(array_map('trim', explode(',', $atts['tags'])));
            if (! empty($tag_slugs)) {
                $tax_query[] = [
                    'taxonomy' => 'post_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_slugs,
                ];
            }
        }

        if (! empty($tax_query)) {
            // If there are multiple conditions, require all by default.
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        if (! $query->have_posts()) {
            return '<p>' . esc_html__('No images found.', 'image-organizer') . '</p>';
        }

        $columns = max(1, min(6, intval($atts['columns'])));

        // Filter UI settings
        $show_filter     = filter_var($atts['show_filter'], FILTER_VALIDATE_BOOLEAN);
        $filter_taxonomy = ('tag' === strtolower($atts['filter_taxonomy'])) ? 'post_tag' : 'category';

        // Get terms used by the queried attachments for the filter bar
        $filter_terms = [];
        if ($show_filter) {
            $attachment_ids = wp_list_pluck($query->posts, 'ID');
            if (! empty($attachment_ids)) {
                $filter_terms = get_terms(
                    [
                        'taxonomy'   => $filter_taxonomy,
                        'hide_empty' => true,
                        'object_ids' => $attachment_ids,
                    ]
                );
                if (is_wp_error($filter_terms)) {
                    $filter_terms = [];
                }
            }
        }

        ob_start();
?>

        <div class="io-gallery-wrapper">

            <?php if ($show_filter && ! empty($filter_terms)) : ?>
                <div class="io-filters" data-io-taxonomy="<?php echo esc_attr($filter_taxonomy); ?>">
                    <button class="io-filter-button io-filter-active" type="button" data-io-term="all">
                        <?php esc_html_e('All', 'image-organizer'); ?>
                    </button>
                    <?php foreach ($filter_terms as $term) : ?>
                        <button
                            class="io-filter-button"
                            type="button"
                            data-io-term="<?php echo esc_attr('term-' . $term->term_id); ?>">
                            <?php echo esc_html($term->name); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="io-gallery io-columns-<?php echo esc_attr($columns); ?>">
                <?php
                while ($query->have_posts()) :
                    $query->the_post();
                    $attachment_id   = get_the_ID();
                    $title           = get_the_title($attachment_id);
                    $caption         = wp_get_attachment_caption($attachment_id);
                    $description     = get_post_field('post_content', $attachment_id);
                    $alt             = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                    $image_src       = wp_get_attachment_image_src($attachment_id, 'large');
                    $thumb_html      = wp_get_attachment_image(
                        $attachment_id,
                        'medium',
                        false,
                        [
                            'class' => 'io-gallery-thumb',
                        ]
                    );
                    $download_url    = wp_get_attachment_url($attachment_id);

                    // Terms for this item for the active filter taxonomy
                    $item_terms      = [];
                    if ($show_filter && $filter_taxonomy) {
                        $item_terms_ids = wp_get_post_terms(
                            $attachment_id,
                            $filter_taxonomy,
                            ['fields' => 'ids']
                        );
                        if (! is_wp_error($item_terms_ids) && ! empty($item_terms_ids)) {
                            foreach ($item_terms_ids as $term_id) {
                                $item_terms[] = 'term-' . $term_id;
                            }
                        }
                    }

                    $item_terms_attr = ! empty($item_terms) ? implode(' ', $item_terms) : '';
                ?>
                    <div class="io-gallery-item"
                        <?php if ($show_filter) : ?>
                        data-io-terms="<?php echo esc_attr($item_terms_attr); ?>"
                        <?php endif; ?>>
                        <button
                            class="io-gallery-trigger"
                            type="button"
                            data-io-title="<?php echo esc_attr($title); ?>"
                            data-io-caption="<?php echo esc_attr($caption); ?>"
                            data-io-description="<?php echo esc_attr(wp_strip_all_tags($description)); ?>"
                            data-io-alt="<?php echo esc_attr($alt); ?>"
                            data-io-src="<?php echo esc_url($image_src ? $image_src[0] : $download_url); ?>"
                            data-io-download="<?php echo esc_url($download_url); ?>">
                            <?php echo $thumb_html; ?>
                        </button>
                    </div>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            </div>

            <!-- Modal container (once per page) -->
            <div class="io-modal" id="io-modal" aria-hidden="true" role="dialog" aria-modal="true">
                <div class="io-modal-backdrop"></div>
                <div class="io-modal-dialog" role="document">
                    <button type="button" class="io-modal-close" aria-label="<?php esc_attr_e('Close', 'image-organizer'); ?>">&times;</button>
                    <div class="io-modal-image-wrap">
                        <img id="io-modal-image" src="" alt="">
                    </div>
                    <div class="io-modal-meta">
                        <h2 id="io-modal-title"></h2>
                        <p id="io-modal-caption"></p>
                        <p id="io-modal-description"></p>
                        <p><strong><?php esc_html_e('Alt text:', 'image-organizer'); ?></strong> <span id="io-modal-alt"></span></p>
                        <a id="io-modal-download" href="#" download class="io-modal-download">
                            <?php esc_html_e('Download image', 'image-organizer'); ?>
                        </a>
                    </div>
                </div>
            </div>

        </div>
<?php

        return ob_get_clean();
    }
}

new Image_Organizer_Gallery();
