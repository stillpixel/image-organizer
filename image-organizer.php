<?php

/**
 * Plugin Name: Image Organizer Gallery
 * Plugin URI:  https://stillpixelstudios.com/
 * Description: Simple image organizer/gallery with metadata modal and download button.
 * Version:     1.1.3
 * Author:      Ron Rattie
 * Text Domain: image-organizer-gallery
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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

        // AJAX for load more pagination
        add_action('wp_ajax_io_load_more', [$this, 'ajax_load_more']);
        add_action('wp_ajax_nopriv_io_load_more', [$this, 'ajax_load_more']);

        // AJAX for title/description search
        add_action('wp_ajax_io_search_images', [$this, 'ajax_search_images']);
        add_action('wp_ajax_nopriv_io_search_images', [$this, 'ajax_search_images']);
    }


    public function register_taxonomies()
    {
        // Allow built-in category and post_tag taxonomies on attachments
        register_taxonomy_for_object_type('category', 'attachment');
        register_taxonomy_for_object_type('post_tag', 'attachment');
    }

    public function register_assets()
    {
        $version = '1.1.3';

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

        wp_localize_script(
            'image-organizer-frontend',
            'ImageOrganizerData',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('io_load_more'),
            ]
        );
    }

    /**
     * Shortcode: [image_organizer ids="1,2,3" columns="4" limit="12"]
     */
    public function render_gallery_shortcode($atts)
    {
        static $instance = 0;
        $instance++;

        $atts = shortcode_atts(
            [
                'ids'             => '',
                'columns'         => 4,
                'limit'           => 12,          // per-page
                'categories'      => '',          // category slugs
                'tags'            => '',          // tag slugs
                'show_filter'     => 'false',
                'filter_taxonomy' => 'category',  // 'category' or 'tag'
                'aria_label'      => '',          // optional accessible name for region
            ],
            $atts,
            'image_organizer'
        );

        wp_enqueue_style('image-organizer-frontend');
        wp_enqueue_script('image-organizer-frontend');

        $ids      = array_filter(array_map('trim', explode(',', $atts['ids'])));
        $per_page = max(1, intval($atts['limit']));

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $per_page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'paged'          => 1,
        ];

        if (! empty($ids)) {
            $args['post__in'] = $ids;
            $args['orderby']  = 'post__in';
        }

        // Tax query (categories/tags)
        $tax_query = [];

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

        // Unique IDs per gallery instance
        $gallery_id           = 'io-gallery-' . $instance;
        $gallery_region_label = $atts['aria_label'];
        if ('' === $gallery_region_label) {
            $gallery_region_label = __('Image gallery', 'image-organizer');
        }
        $gallery_list_id     = $gallery_id . '-list';
        $modal_id            = $gallery_id . '-modal';
        $modal_title_id      = $gallery_id . '-modal-title';
        $modal_desc_wrapper  = $gallery_id . '-modal-description';
        $status_id           = $gallery_id . '-status';

        // Filter UI settings
        $show_filter     = filter_var($atts['show_filter'], FILTER_VALIDATE_BOOLEAN);
        $filter_taxonomy = ('tag' === strtolower($atts['filter_taxonomy'])) ? 'post_tag' : 'category';

        // Collect terms for filter bar (based on first page)
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

        // Pagination info
        $max_pages = (int) $query->max_num_pages;
        $has_more  = $max_pages > 1;

        ob_start();
?>

        <div
            class="io-gallery-wrapper"
            id="<?php echo esc_attr($gallery_id); ?>"
            role="region"
            aria-label="<?php echo esc_attr($gallery_region_label); ?>"
            data-io-per-page="<?php echo esc_attr($per_page); ?>"
            data-io-columns="<?php echo esc_attr($columns); ?>"
            data-io-categories="<?php echo esc_attr($atts['categories']); ?>"
            data-io-tags="<?php echo esc_attr($atts['tags']); ?>"
            data-io-filter-taxonomy="<?php echo esc_attr($filter_taxonomy); ?>"
            data-io-show-filter="<?php echo $show_filter ? 'true' : 'false'; ?>"
            data-io-ids="<?php echo esc_attr(implode(',', $ids)); ?>"
            data-io-max-pages="<?php echo esc_attr($max_pages); ?>"
            data-io-current-page="1">

            <!-- Text filter: title/description search -->
            <div class="io-text-filter">
                <label
                    for="<?php echo esc_attr($gallery_id); ?>-text-filter"
                    class="screen-reader-text">
                    <?php esc_html_e('Filter images by title or description', 'image-organizer'); ?>
                </label>

                <input
                    type="search"
                    id="<?php echo esc_attr($gallery_id); ?>-text-filter"
                    class="io-text-filter-input"
                    placeholder="<?php esc_attr_e('Filter by title or description…', 'image-organizer'); ?>"
                    aria-label="<?php esc_attr_e('Filter images by title or description', 'image-organizer'); ?>" />

                <button
                    type="button"
                    class="io-text-filter-clear"
                    aria-label="<?php esc_attr_e('Clear search and reset the gallery', 'image-organizer'); ?>">
                    <?php esc_html_e('Clear', 'image-organizer'); ?>
                </button>
            </div>

            <?php if ($show_filter && ! empty($filter_terms)) : ?>
                <div
                    class="io-filters"
                    data-io-taxonomy="<?php echo esc_attr($filter_taxonomy); ?>"
                    role="toolbar"
                    aria-label="<?php esc_attr_e('Filter images', 'image-organizer'); ?>">
                    <button
                        class="io-filter-button io-filter-active"
                        type="button"
                        data-io-term="all"
                        aria-pressed="true">
                        <?php esc_html_e('All', 'image-organizer'); ?>
                    </button>
                    <?php foreach ($filter_terms as $term) : ?>
                        <button
                            class="io-filter-button"
                            type="button"
                            data-io-term="<?php echo esc_attr('term-' . $term->term_id); ?>"
                            aria-pressed="false">
                            <?php echo esc_html($term->name); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div
                class="io-gallery io-columns-<?php echo esc_attr($columns); ?>"
                id="<?php echo esc_attr($gallery_list_id); ?>"
                role="list">
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

                    $item_terms = [];
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

                    // Accessible label for the trigger button
                    $button_label_parts = [];
                    if ($title) {
                        $button_label_parts[] = $title;
                    }
                    if ($caption) {
                        $button_label_parts[] = $caption;
                    }
                    if ($alt && empty($button_label_parts)) {
                        $button_label_parts[] = $alt;
                    }
                    $button_aria_label = $button_label_parts
                        ? sprintf(
                            /* translators: %s: image title, caption, or alt text. */
                            __('View details for "%s"', 'image-organizer'),
                            implode(' – ', $button_label_parts)
                        )
                        : __('View image details', 'image-organizer');


                ?>
                    <div
                        class="io-gallery-item"
                        role="listitem"
                        <?php if ($show_filter) : ?>
                        data-io-terms="<?php echo esc_attr($item_terms_attr); ?>"
                        <?php endif; ?>>
                        <button
                            class="io-gallery-trigger"
                            type="button"
                            aria-label="<?php echo esc_attr($button_aria_label); ?>"
                            data-io-title="<?php echo esc_attr($title); ?>"
                            data-io-caption="<?php echo esc_attr($caption); ?>"
                            data-io-description="<?php echo esc_attr(wp_strip_all_tags($description)); ?>"
                            data-io-alt="<?php echo esc_attr($alt); ?>"
                            data-io-src="<?php echo esc_url($image_src ? $image_src[0] : $download_url); ?>"
                            data-io-download="<?php echo esc_url($download_url); ?>">
                            <?php echo wp_kses_post($thumb_html); ?>
                        </button>
                    </div>
                <?php endwhile; ?>
                <?php wp_reset_postdata(); ?>
            </div>

            <?php if ($has_more) : ?>
                <div class="io-pagination">
                    <button
                        type="button"
                        class="io-load-more"
                        data-io-page="1"
                        aria-label="<?php esc_attr_e('Load more images', 'image-organizer'); ?>"
                        aria-controls="<?php echo esc_attr($gallery_list_id); ?>">
                        <?php esc_html_e('Load more', 'image-organizer'); ?>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Live region for status updates (AJAX, copy, etc.) -->
            <div
                id="<?php echo esc_attr($status_id); ?>"
                class="io-status"
                aria-live="polite"
                aria-atomic="true">
            </div>

            <!-- Modal (once per gallery) -->
            <div
                class="io-modal"
                id="<?php echo esc_attr($modal_id); ?>"
                aria-hidden="true"
                role="dialog"
                aria-modal="true"
                aria-labelledby="<?php echo esc_attr($modal_title_id); ?>"
                aria-describedby="<?php echo esc_attr($modal_desc_wrapper); ?>">
                <div class="io-modal-backdrop" tabindex="-1"></div>
                <div class="io-modal-dialog" role="document" tabindex="-1">
                    <button
                        type="button"
                        class="io-modal-close"
                        aria-label="<?php esc_attr_e('Close image details', 'image-organizer'); ?>">
                        &times;
                    </button>
                    <div class="io-modal-image-wrap">
                        <img class="io-modal-image" src="" alt="">
                    </div>
                    <div class="io-modal-meta">
                        <h2 id="<?php echo esc_attr($modal_title_id); ?>" class="io-modal-title"></h2>
                        <div id="<?php echo esc_attr($modal_desc_wrapper); ?>" class="io-modal-desc-wrapper">
                            <p class="io-modal-caption"></p>
                            <p class="io-modal-description"></p>
                            <p class="io-modal-alt-row">
                                <strong><?php esc_html_e('Alt text:', 'image-organizer'); ?></strong>

                                <!-- Clickable alt text (for mouse & keyboard) -->
                                <span
                                    class="io-modal-alt"
                                    role="button"
                                    tabindex="0"
                                    aria-describedby="<?php echo esc_attr($gallery_id); ?>-alt-copy-help">
                                </span>

                                <!-- Dedicated copy icon/button -->
                                <button
                                    type="button"
                                    class="io-alt-copy-button"
                                    title="<?php esc_attr_e('Copy alt text to clipboard', 'image-organizer'); ?>"
                                    aria-label="<?php esc_attr_e('Copy alt text to clipboard', 'image-organizer'); ?>">
                                    📋
                                </button>
                            </p>

                            <span
                                id="<?php echo esc_attr($gallery_id); ?>-alt-copy-help"
                                class="screen-reader-text">
                                <?php esc_html_e('Press Enter or Space to copy the alt text to the clipboard.', 'image-organizer'); ?>
                            </span>

                            <a
                                class="io-modal-download"
                                id="<?php echo esc_attr($gallery_id); ?>-modal-download"
                                href="#"
                                download>
                                <?php esc_html_e('Download image', 'image-organizer'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div> <!-- end .io-gallery-wrapper -->
        <?php

        return ob_get_clean();
    }

    public function ajax_search_images()
    {
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'io_load_more')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 400);
        }

        $search          = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $categories      = isset($_POST['categories']) ? sanitize_text_field(wp_unslash($_POST['categories'])) : '';
        $tags            = isset($_POST['tags']) ? sanitize_text_field(wp_unslash($_POST['tags'])) : '';
        $filter_taxonomy_raw = isset($_POST['filter_taxonomy'])
            ? sanitize_text_field(wp_unslash($_POST['filter_taxonomy']))
            : '';

        $filter_taxonomy = ('tag' === strtolower($filter_taxonomy_raw)) ? 'post_tag' : 'category';

        $show_filter     = ! empty($_POST['show_filter']) && 'true' === $_POST['show_filter'];
        $ids_raw         = isset($_POST['ids']) ? sanitize_text_field(wp_unslash($_POST['ids'])) : '';
        $ids             = array_filter(array_map('trim', explode(',', $ids_raw)));

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,        // 🔑 search across ALL matching images
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Search by title/description/content
        if ('' !== $search) {
            $args['s'] = $search;
        }

        // Restrict to specific IDs if provided in shortcode
        if (! empty($ids)) {
            $args['post__in'] = $ids;
            $args['orderby']  = 'post__in';
        }

        // Taxonomy domain: 🔑 if categories option is set, constrain search to that category
        $tax_query = [];

        if (! empty($categories)) {
            $category_slugs = array_filter(array_map('trim', explode(',', $categories)));
            if (! empty($category_slugs)) {
                $tax_query[] = [
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $category_slugs,
                ];
            }
        }

        // Keep tags consistent if shortcode passed tags as well
        if (! empty($tags)) {
            $tag_slugs = array_filter(array_map('trim', explode(',', $tags)));
            if (! empty($tag_slugs)) {
                $tax_query[] = [
                    'taxonomy' => 'post_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_slugs,
                ];
            }
        }

        if (! empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        if (! $query->have_posts()) {
            wp_send_json_success([
                'html' => '',
            ]);
        }

        ob_start();

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

            $item_terms = [];
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

            // Accessible label (same pattern as elsewhere)
            $button_label_parts = [];
            if ($title) {
                $button_label_parts[] = $title;
            }
            if ($caption) {
                $button_label_parts[] = $caption;
            }
            if ($alt && empty($button_label_parts)) {
                $button_label_parts[] = $alt;
            }
            $button_aria_label = $button_label_parts
                ? sprintf(
                    /* translators: %s: image title, caption, or alt text. */
                    __('View details for "%s"', 'image-organizer'),
                    implode(' – ', $button_label_parts)
                )
                : __('View image details', 'image-organizer');


        ?>
            <div
                class="io-gallery-item"
                role="listitem"
                <?php if ($show_filter) : ?>
                data-io-terms="<?php echo esc_attr($item_terms_attr); ?>"
                <?php endif; ?>>
                <button
                    class="io-gallery-trigger"
                    type="button"
                    aria-label="<?php echo esc_attr($button_aria_label); ?>"
                    data-io-title="<?php echo esc_attr($title); ?>"
                    data-io-caption="<?php echo esc_attr($caption); ?>"
                    data-io-description="<?php echo esc_attr(wp_strip_all_tags($description)); ?>"
                    data-io-alt="<?php echo esc_attr($alt); ?>"
                    data-io-src="<?php echo esc_url($image_src ? $image_src[0] : $download_url); ?>"
                    data-io-download="<?php echo esc_url($download_url); ?>">
                    <?php echo wp_kses_post($thumb_html); ?>
                </button>
            </div>
        <?php
        endwhile;
        wp_reset_postdata();

        $html = ob_get_clean();

        wp_send_json_success(
            [
                'html' => $html,
            ]
        );
    }

    public function ajax_load_more()
    {
        if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'io_load_more')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 400);
        }

        $page            = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page        = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 12;
        $columns         = isset($_POST['columns']) ? max(1, min(6, intval($_POST['columns']))) : 4;
        $categories      = isset($_POST['categories']) ? sanitize_text_field(wp_unslash($_POST['categories'])) : '';
        $tags            = isset($_POST['tags']) ? sanitize_text_field(wp_unslash($_POST['tags'])) : '';
        $filter_taxonomy_raw = isset($_POST['filter_taxonomy'])
            ? sanitize_text_field(wp_unslash($_POST['filter_taxonomy']))
            : '';

        $filter_taxonomy = ('tag' === strtolower($filter_taxonomy_raw)) ? 'post_tag' : 'category';

        $show_filter     = ! empty($_POST['show_filter']) && 'true' === $_POST['show_filter'];
        $ids_raw         = isset($_POST['ids']) ? sanitize_text_field(wp_unslash($_POST['ids'])) : '';
        $ids             = array_filter(array_map('trim', explode(',', $ids_raw)));

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $per_page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'paged'          => $page,
        ];

        if (! empty($ids)) {
            $args['post__in'] = $ids;
            $args['orderby']  = 'post__in';
        }

        $tax_query = [];

        if (! empty($categories)) {
            $category_slugs = array_filter(array_map('trim', explode(',', $categories)));
            if (! empty($category_slugs)) {
                $tax_query[] = [
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $category_slugs,
                ];
            }
        }

        if (! empty($tags)) {
            $tag_slugs = array_filter(array_map('trim', explode(',', $tags)));
            if (! empty($tag_slugs)) {
                $tax_query[] = [
                    'taxonomy' => 'post_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_slugs,
                ];
            }
        }

        if (! empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        if (! $query->have_posts()) {
            wp_send_json_success(
                [
                    'html'      => '',
                    'has_more'  => false,
                    'next_page' => null,
                ]
            );
        }

        ob_start();

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

            $item_terms = [];
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

            // Same accessible label logic as in main render
            $button_label_parts = [];
            if ($title) {
                $button_label_parts[] = $title;
            }
            if ($caption) {
                $button_label_parts[] = $caption;
            }
            if ($alt && empty($button_label_parts)) {
                $button_label_parts[] = $alt;
            }
            $button_aria_label = $button_label_parts
                ? sprintf(
                    /* translators: %s: image title, caption, or alt text. */
                    __('View details for "%s"', 'image-organizer'),
                    implode(' – ', $button_label_parts)
                )
                : __('View image details', 'image-organizer');


        ?>
            <div
                class="io-gallery-item"
                role="listitem"
                <?php if ($show_filter) : ?>
                data-io-terms="<?php echo esc_attr($item_terms_attr); ?>"
                <?php endif; ?>>
                <button
                    class="io-gallery-trigger"
                    type="button"
                    aria-label="<?php echo esc_attr($button_aria_label); ?>"
                    data-io-title="<?php echo esc_attr($title); ?>"
                    data-io-caption="<?php echo esc_attr($caption); ?>"
                    data-io-description="<?php echo esc_attr(wp_strip_all_tags($description)); ?>"
                    data-io-alt="<?php echo esc_attr($alt); ?>"
                    data-io-src="<?php echo esc_url($image_src ? $image_src[0] : $download_url); ?>"
                    data-io-download="<?php echo esc_url($download_url); ?>">
                    <?php echo wp_kses_post($thumb_html); ?>
                </button>
            </div>
<?php
        endwhile;
        wp_reset_postdata();

        $html      = ob_get_clean();
        $max_pages = (int) $query->max_num_pages;
        $has_more  = $page < $max_pages;
        $next_page = $has_more ? $page + 1 : null;

        wp_send_json_success(
            [
                'html'      => $html,
                'has_more'  => $has_more,
                'next_page' => $next_page,
            ]
        );
    }
}

new Image_Organizer_Gallery();
