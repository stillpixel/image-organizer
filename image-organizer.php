<?php
/**
 * Plugin Name: Simple Image Organizer
 * Plugin URI:  https://stillpixelstudios.com/
 * Description: Simple image organizer with metadata modal and download button.
 * Version:     1.1.6
 * Author:      Ron Rattie
 * Text Domain: image-organizer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit;
}

class Image_Organizer_Gallery {

    /**
     * Media Library Organizer taxonomy slug.
     * ThemeIsle Media Library Organizer uses 'media_category' for attachments.
     */
    const MLO_TAXONOMY = 'media_category';

    public function __construct() {
        add_action('init', array($this, 'register_taxonomies'));
        add_shortcode('image_organizer', array($this, 'render_gallery_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));

        // AJAX for load more pagination.
        add_action('wp_ajax_io_load_more', array($this, 'ajax_load_more'));
        add_action('wp_ajax_nopriv_io_load_more', array($this, 'ajax_load_more'));

        // AJAX for title/description search.
        add_action('wp_ajax_io_search_images', array($this, 'ajax_search_images'));
        add_action('wp_ajax_nopriv_io_search_images', array($this, 'ajax_search_images'));

        // Upload handler.
        add_action('wp_ajax_io_upload_image', array($this, 'ajax_upload_image'));
        add_action('wp_ajax_nopriv_io_upload_image', array($this, 'ajax_upload_image'));
    }

    public function register_taxonomies() {
        // Allow built-in category and post_tag taxonomies on attachments.
        register_taxonomy_for_object_type('category', 'attachment');
        register_taxonomy_for_object_type('post_tag', 'attachment');

        // Media Library Organizer plugin registers its own taxonomy; we don't register it here.
        // But if it exists, we will query/assign it via self::MLO_TAXONOMY.
    }

    public function register_assets() {
        $version = '1.1.6';

        wp_register_style(
            'image-organizer-frontend',
            plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
            array(),
            $version
        );

        wp_register_script(
            'image-organizer-frontend',
            plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
            array('jquery'),
            $version,
            true
        );

        wp_localize_script(
            'image-organizer-frontend',
            'ImageOrganizerData',
            array(
                'ajax_url'     => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('io_load_more'),
                'upload_nonce' => wp_create_nonce('io_upload_image'),
            )
        );
    }

    /**
     * Returns true if Media Library Organizer taxonomy exists.
     *
     * @return bool
     */
    private function mlo_is_available() {
        return taxonomy_exists(self::MLO_TAXONOMY);
    }

    /**
     * Normalize taxonomy key from shortcode/POST into a taxonomy slug.
     * Supports: category|tag|media_category|mlo (Media Library Organizer)
     *
     * @param string $raw
     * @return string
     */
    private function normalize_filter_taxonomy($raw) {
        $raw = strtolower((string) $raw);

        if ('tag' === $raw || 'post_tag' === $raw) {
            return 'post_tag';
        }

        if ('media_category' === $raw || 'mlo' === $raw || 'media' === $raw) {
            return $this->mlo_is_available() ? self::MLO_TAXONOMY : 'category';
        }

        // Default.
        return 'category';
    }

    /**
     * Parse comma-separated slugs, returning array of slugs.
     *
     * @param string $csv
     * @return array
     */
    private function parse_slug_csv($csv) {
        $csv   = (string) $csv;
        $parts = array_filter(array_map('trim', explode(',', $csv)));
        $out   = array();

        foreach ($parts as $p) {
            $p = sanitize_title($p);
            if ('' !== $p) {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Build tax_query based on shortcode/POST filters including Media Library Organizer.
     *
     * Shortcode attributes supported:
     * - categories="a,b"         -> WP category slugs
     * - tags="a,b"               -> WP tag slugs
     * - media_categories="a,b"   -> MLO media_category slugs (if taxonomy exists)
     *
     * @param array $atts
     * @return array tax_query array (possibly empty)
     */
    private function build_tax_query_from_atts($atts) {
        $tax_query = array();

        // WP categories.
        if (! empty($atts['categories'])) {
            $category_slugs = $this->parse_slug_csv($atts['categories']);
            if (! empty($category_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $category_slugs,
                );
            }
        }

        // WP tags.
        if (! empty($atts['tags'])) {
            $tag_slugs = $this->parse_slug_csv($atts['tags']);
            if (! empty($tag_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'post_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_slugs,
                );
            }
        }

        // Media Library Organizer categories.
        if (! empty($atts['media_categories']) && $this->mlo_is_available()) {
            $mlo_slugs = $this->parse_slug_csv($atts['media_categories']);
            if (! empty($mlo_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => self::MLO_TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $mlo_slugs,
                );
            }
        }

        if (! empty($tax_query) && count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        return $tax_query;
    }

    /**
     * Shortcode: [image_organizer ids="1,2,3" columns="4" limit="12"]
     */
    public function render_gallery_shortcode($atts) {
        static $instance = 0;
        $instance++;

        $atts = shortcode_atts(
            array(
                'ids'              => '',
                'columns'          => 4,
                'limit'            => 12,          // per-page.
                'categories'       => '',          // WP category slugs.
                'tags'             => '',          // WP tag slugs.
                'media_categories' => '',          // MLO media_category slugs.
                'show_filter'      => 'false',
                'filter_taxonomy'  => 'category',  // category|tag|media_category|mlo.
                'aria_label'       => '',

                // Upload.
                'allow_upload'           => 'false',
                'upload_category'        => '',  // WP category slug (optional).

                // IMPORTANT: support both names:
                // - upload_media_category (preferred)
                // - upload_media_categories (alias)
                'upload_media_category'  => '',
                'upload_media_categories'=> '',

                'upload_key'             => '',
                'upload_require_review'  => 'true',
                'upload_max_mb'          => 20,
                'upload_note_text'       => '',
                'upload_note_url_text'   => '',
                'upload_note_url'        => '',
            ),
            $atts,
            'image_organizer'
        );

        // Normalize upload media category (support alias).
        if (empty($atts['upload_media_category']) && ! empty($atts['upload_media_categories'])) {
            $atts['upload_media_category'] = $atts['upload_media_categories'];
        }

        wp_enqueue_style('image-organizer-frontend');
        wp_enqueue_script('image-organizer-frontend');

        $ids      = array_filter(array_map('trim', explode(',', $atts['ids'])));
        $per_page = max(1, intval($atts['limit']));

        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $per_page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'paged'          => 1,
        );

        if (! empty($ids)) {
            $args['post__in'] = $ids;
            $args['orderby']  = 'post__in';
        }

        // Tax query (WP categories/tags + MLO media_category).
        $tax_query = $this->build_tax_query_from_atts($atts);
        if (! empty($tax_query)) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        if (! $query->have_posts()) {
            return '<p>' . esc_html__('No images found.', 'image-organizer') . '</p>';
        }

        $columns = max(1, min(6, intval($atts['columns'])));

        // Unique IDs per gallery instance.
        $gallery_id           = 'io-gallery-' . $instance;
        $gallery_region_label = $atts['aria_label'];
        if ('' === $gallery_region_label) {
            $gallery_region_label = __('Image gallery', 'image-organizer');
        }
        $gallery_list_id    = $gallery_id . '-list';
        $modal_id           = $gallery_id . '-modal';
        $modal_title_id     = $gallery_id . '-modal-title';
        $modal_desc_wrapper = $gallery_id . '-modal-description';
        $status_id          = $gallery_id . '-status';

        // Filter UI settings.
        $show_filter     = filter_var($atts['show_filter'], FILTER_VALIDATE_BOOLEAN);
        $filter_taxonomy = $this->normalize_filter_taxonomy($atts['filter_taxonomy']);

        // Collect terms for filter bar (based on first page).
        $filter_terms = array();
        if ($show_filter) {
            $attachment_ids = wp_list_pluck($query->posts, 'ID');
            if (! empty($attachment_ids)) {
                $filter_terms = get_terms(
                    array(
                        'taxonomy'   => $filter_taxonomy,
                        'hide_empty' => true,
                        'object_ids' => $attachment_ids,
                    )
                );
                if (is_wp_error($filter_terms)) {
                    $filter_terms = array();
                }
            }
        }

        // Pagination info.
        $max_pages = (int) $query->max_num_pages;
        $has_more  = $max_pages > 1;

        // Upload config (render-time).
        $allow_upload          = filter_var($atts['allow_upload'], FILTER_VALIDATE_BOOLEAN);
        $upload_category       = sanitize_title($atts['upload_category']); // WP category slug.
        $upload_media_category = sanitize_title($atts['upload_media_category']); // MLO slug.
        $upload_key_required   = sanitize_text_field($atts['upload_key']);
        $upload_require_review = filter_var($atts['upload_require_review'], FILTER_VALIDATE_BOOLEAN);
        $upload_max_mb         = max(1, (int) $atts['upload_max_mb']);
        $upload_note_text      = sanitize_text_field($atts['upload_note_text']);
        $upload_note_url_text  = sanitize_text_field($atts['upload_note_url_text']);
        $upload_note_url       = esc_url_raw($atts['upload_note_url']);

        // Store the required upload key server-side per gallery instance (so we don't expose it in HTML).
        set_transient('io_upload_key_' . $gallery_id, $upload_key_required, DAY_IN_SECONDS);

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
            data-io-media-categories="<?php echo esc_attr($atts['media_categories']); ?>"
            data-io-filter-taxonomy="<?php echo esc_attr($filter_taxonomy); ?>"
            data-io-show-filter="<?php echo $show_filter ? 'true' : 'false'; ?>"
            data-io-ids="<?php echo esc_attr(implode(',', $ids)); ?>"
            data-io-max-pages="<?php echo esc_attr($max_pages); ?>"
            data-io-current-page="1">

            <!-- Text filter: title/description search -->
            <div class="io-text-filter">
                <label for="<?php echo esc_attr($gallery_id); ?>-text-filter" class="screen-reader-text">
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
                    $attachment_id = get_the_ID();
                    $title         = get_the_title($attachment_id);
                    $caption       = wp_get_attachment_caption($attachment_id);
                    $description   = get_post_field('post_content', $attachment_id);
                    $alt           = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                    $image_src     = wp_get_attachment_image_src($attachment_id, 'large');
                    $thumb_html    = wp_get_attachment_image(
                        $attachment_id,
                        'medium',
                        false,
                        array(
                            'class' => 'io-gallery-thumb',
                        )
                    );
                    $download_url  = wp_get_attachment_url($attachment_id);

                    $item_terms = array();
                    if ($show_filter && $filter_taxonomy) {
                        $item_terms_ids = wp_get_post_terms(
                            $attachment_id,
                            $filter_taxonomy,
                            array('fields' => 'ids')
                        );
                        if (! is_wp_error($item_terms_ids) && ! empty($item_terms_ids)) {
                            foreach ($item_terms_ids as $term_id) {
                                $item_terms[] = 'term-' . $term_id;
                            }
                        }
                    }

                    $item_terms_attr = ! empty($item_terms) ? implode(' ', $item_terms) : '';

                    // Accessible label for the trigger button.
                    $button_label_parts = array();
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

            <!-- Upload form (optional, based on shortcode attributes) -->
            <?php if ($allow_upload) : ?>
                <div class="io-upload-separator">
                    <div class="io-upload-card">
                        <h3 class="io-upload-title">
                            <?php esc_html_e('Upload an image', 'image-organizer'); ?>
                        </h3>

                        <?php if ('' !== $upload_note_text || '' !== $upload_note_url) : ?>
                            <p class="io-upload-note">
                                <?php if ('' !== $upload_note_text) : ?>
                                    <?php echo esc_html($upload_note_text); ?>
                                <?php endif; ?>

                                <?php if ('' !== $upload_note_url && '' !== $upload_note_url_text) : ?>
                                    <a href="<?php echo esc_url($upload_note_url); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer">
                                        <?php echo esc_html($upload_note_url_text); ?>
                                    </a>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <form class="io-upload-form" enctype="multipart/form-data">
                            <div class="io-upload-grid">

                                <div class="io-upload-field io-upload-field--file">
                                    <label class="io-upload-label" for="<?php echo esc_attr($gallery_id); ?>-upload-file">
                                        <?php esc_html_e('Choose image', 'image-organizer'); ?>
                                    </label>
                                    <input
                                        id="<?php echo esc_attr($gallery_id); ?>-upload-file"
                                        class="io-upload-file"
                                        type="file"
                                        name="io_file"
                                        accept="image/png"
                                        required />
                                    <p class="io-upload-help">
                                        <?php
                                        printf(
                                            esc_html__('Max size: %dMB. Allowed: PNG only (650px wide).', 'image-organizer'),
                                            (int) $upload_max_mb
                                        );
                                        ?>
                                    </p>
                                </div>

                                <div class="io-upload-field">
                                    <label class="io-upload-label" for="<?php echo esc_attr($gallery_id); ?>-upload-title">
                                        <?php esc_html_e('Title', 'image-organizer'); ?>
                                    </label>
                                    <input
                                        id="<?php echo esc_attr($gallery_id); ?>-upload-title"
                                        class="io-upload-title-input"
                                        type="text"
                                        name="io_title" />
                                </div>

                                <div class="io-upload-field">
                                    <label class="io-upload-label" for="<?php echo esc_attr($gallery_id); ?>-upload-alt">
                                        <?php esc_html_e('Alt text', 'image-organizer'); ?>
                                    </label>
                                    <input
                                        id="<?php echo esc_attr($gallery_id); ?>-upload-alt"
                                        class="io-upload-alt"
                                        type="text"
                                        name="io_alt" />
                                </div>

                                <div class="io-upload-field io-upload-field--desc">
                                    <label class="io-upload-label" for="<?php echo esc_attr($gallery_id); ?>-upload-desc">
                                        <?php esc_html_e('Keywords', 'image-organizer'); ?>
                                    </label>
                                    <textarea
                                        id="<?php echo esc_attr($gallery_id); ?>-upload-desc"
                                        class="io-upload-desc"
                                        name="io_description"
                                        rows="3"></textarea>
                                </div>

                                <?php if (! empty($upload_key_required)) : ?>
                                    <div class="io-upload-field">
                                        <label class="io-upload-label" for="<?php echo esc_attr($gallery_id); ?>-upload-key">
                                            <?php esc_html_e('Upload key', 'image-organizer'); ?>
                                        </label>
                                        <input
                                            id="<?php echo esc_attr($gallery_id); ?>-upload-key"
                                            class="io-upload-key"
                                            type="password"
                                            name="io_upload_key"
                                            required />
                                    </div>
                                <?php endif; ?>

                                <!-- Honeypot (hidden) -->
                                <div class="io-upload-hp" aria-hidden="true">
                                    <label for="<?php echo esc_attr($gallery_id); ?>-website">Website</label>
                                    <input
                                        id="<?php echo esc_attr($gallery_id); ?>-website"
                                        type="text"
                                        name="website"
                                        tabindex="-1"
                                        autocomplete="off" />
                                </div>

                                <input type="hidden" name="io_gallery_id" value="<?php echo esc_attr($gallery_id); ?>" />
                                <input type="hidden" name="io_upload_category" value="<?php echo esc_attr($upload_category); ?>" />

                                <!-- Support both hidden names (backend reads either). -->
                                <input type="hidden" name="io_upload_media_category" value="<?php echo esc_attr($upload_media_category); ?>" />
                                <input type="hidden" name="io_upload_media_categories" value="<?php echo esc_attr($upload_media_category); ?>" />

                                <input type="hidden" name="io_require_review" value="<?php echo $upload_require_review ? '1' : '0'; ?>" />
                                <input type="hidden" name="io_max_mb" value="<?php echo esc_attr($upload_max_mb); ?>" />

                                <div class="io-upload-actions">
                                    <button type="submit" class="io-upload-submit">
                                        <?php esc_html_e('Upload', 'image-organizer'); ?>
                                    </button>
                                    <div class="io-upload-status" aria-live="polite" aria-atomic="true"></div>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

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
                            <p class="io-modal-description">
                                <strong><?php esc_html_e('Keywords:', 'image-organizer'); ?></strong>
                                <span class="io-modal-description-text"></span>
                            </p>
                            <p class="io-modal-alt-row">
                                <strong><?php esc_html_e('Alt text:', 'image-organizer'); ?></strong>

                                <span
                                    class="io-modal-alt"
                                    role="button"
                                    tabindex="0"
                                    aria-describedby="<?php echo esc_attr($gallery_id); ?>-alt-copy-help">
                                </span>

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

        </div><!-- end .io-gallery-wrapper -->

        <?php
        return ob_get_clean();
    }

    public function ajax_search_images() {
        if (
            ! isset($_POST['nonce']) ||
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'io_load_more')
        ) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 400);
        }

        $search          = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $categories      = isset($_POST['categories']) ? sanitize_text_field(wp_unslash($_POST['categories'])) : '';
        $tags            = isset($_POST['tags']) ? sanitize_text_field(wp_unslash($_POST['tags'])) : '';
        $media_categories= isset($_POST['media_categories']) ? sanitize_text_field(wp_unslash($_POST['media_categories'])) : '';

        $filter_taxonomy_raw = isset($_POST['filter_taxonomy'])
            ? sanitize_text_field(wp_unslash($_POST['filter_taxonomy']))
            : '';
        $filter_taxonomy = $this->normalize_filter_taxonomy($filter_taxonomy_raw);

        $show_filter = ! empty($_POST['show_filter']) && 'true' === $_POST['show_filter'];

        $ids_raw = isset($_POST['ids']) ? sanitize_text_field(wp_unslash($_POST['ids'])) : '';
        $ids     = array_filter(array_map('trim', explode(',', $ids_raw)));

        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ('' !== $search) {
            $args['s'] = $search;
        }

        if (! empty($ids)) {
            $args['post__in'] = $ids;
            $args['orderby']  = 'post__in';
        }

        $tax_query = array();

        if (! empty($categories)) {
            $category_slugs = $this->parse_slug_csv($categories);
            if (! empty($category_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $category_slugs,
                );
            }
        }

        if (! empty($tags)) {
            $tag_slugs = $this->parse_slug_csv($tags);
            if (! empty($tag_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'post_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_slugs,
                );
            }
        }

        if (! empty($media_categories) && $this->mlo_is_available()) {
            $mlo_slugs = $this->parse_slug_csv($media_categories);
            if (! empty($mlo_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => self::MLO_TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $mlo_slugs,
                );
            }
        }

        if (! empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        if (! $query->have_posts()) {
            wp_send_json_success(array('html' => ''));
        }

        ob_start();

        while ($query->have_posts()) :
            $query->the_post();
            $attachment_id = get_the_ID();
            $title         = get_the_title($attachment_id);
            $caption       = wp_get_attachment_caption($attachment_id);
            $description   = get_post_field('post_content', $attachment_id);
            $alt           = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            $image_src     = wp_get_attachment_image_src($attachment_id, 'large');
            $thumb_html    = wp_get_attachment_image($attachment_id, 'medium', false, array('class' => 'io-gallery-thumb'));
            $download_url  = wp_get_attachment_url($attachment_id);

            $item_terms = array();
            if ($show_filter && $filter_taxonomy) {
                $item_terms_ids = wp_get_post_terms($attachment_id, $filter_taxonomy, array('fields' => 'ids'));
                if (! is_wp_error($item_terms_ids) && ! empty($item_terms_ids)) {
                    foreach ($item_terms_ids as $term_id) {
                        $item_terms[] = 'term-' . $term_id;
                    }
                }
            }

            $item_terms_attr = ! empty($item_terms) ? implode(' ', $item_terms) : '';

            $button_label_parts = array();
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
                ? sprintf(__('View details for "%s"', 'image-organizer'), implode(' – ', $button_label_parts))
                : __('View image details', 'image-organizer');
            ?>
            <div class="io-gallery-item" role="listitem" <?php if ($show_filter) : ?>data-io-terms="<?php echo esc_attr($item_terms_attr); ?>"<?php endif; ?>>
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

        wp_send_json_success(array('html' => ob_get_clean()));
    }

    public function ajax_load_more() {
        if (
            ! isset($_POST['nonce']) ||
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'io_load_more')
        ) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 400);
        }

        $page     = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 12;
        $columns  = isset($_POST['columns']) ? max(1, min(6, intval($_POST['columns']))) : 4;

        $categories       = isset($_POST['categories']) ? sanitize_text_field(wp_unslash($_POST['categories'])) : '';
        $tags             = isset($_POST['tags']) ? sanitize_text_field(wp_unslash($_POST['tags'])) : '';
        $media_categories = isset($_POST['media_categories']) ? sanitize_text_field(wp_unslash($_POST['media_categories'])) : '';

        $filter_taxonomy_raw = isset($_POST['filter_taxonomy'])
            ? sanitize_text_field(wp_unslash($_POST['filter_taxonomy']))
            : '';
        $filter_taxonomy = $this->normalize_filter_taxonomy($filter_taxonomy_raw);

        $show_filter = ! empty($_POST['show_filter']) && 'true' === $_POST['show_filter'];

        $ids_raw = isset($_POST['ids']) ? sanitize_text_field(wp_unslash($_POST['ids'])) : '';
        $ids     = array_filter(array_map('trim', explode(',', $ids_raw)));

        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image',
            'posts_per_page' => $per_page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'paged'          => $page,
        );

        if (! empty($ids)) {
            $args['post__in'] = $ids;
            $args['orderby']  = 'post__in';
        }

        $tax_query = array();

        if (! empty($categories)) {
            $category_slugs = $this->parse_slug_csv($categories);
            if (! empty($category_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $category_slugs,
                );
            }
        }

        if (! empty($tags)) {
            $tag_slugs = $this->parse_slug_csv($tags);
            if (! empty($tag_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'post_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_slugs,
                );
            }
        }

        if (! empty($media_categories) && $this->mlo_is_available()) {
            $mlo_slugs = $this->parse_slug_csv($media_categories);
            if (! empty($mlo_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => self::MLO_TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $mlo_slugs,
                );
            }
        }

        if (! empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);

        if (! $query->have_posts()) {
            wp_send_json_success(array('html' => '', 'has_more' => false, 'next_page' => null));
        }

        ob_start();

        while ($query->have_posts()) :
            $query->the_post();
            $attachment_id = get_the_ID();
            $title         = get_the_title($attachment_id);
            $caption       = wp_get_attachment_caption($attachment_id);
            $description   = get_post_field('post_content', $attachment_id);
            $alt           = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            $image_src     = wp_get_attachment_image_src($attachment_id, 'large');
            $thumb_html    = wp_get_attachment_image($attachment_id, 'medium', false, array('class' => 'io-gallery-thumb'));
            $download_url  = wp_get_attachment_url($attachment_id);

            $item_terms = array();
            if ($show_filter && $filter_taxonomy) {
                $item_terms_ids = wp_get_post_terms($attachment_id, $filter_taxonomy, array('fields' => 'ids'));
                if (! is_wp_error($item_terms_ids) && ! empty($item_terms_ids)) {
                    foreach ($item_terms_ids as $term_id) {
                        $item_terms[] = 'term-' . $term_id;
                    }
                }
            }

            $item_terms_attr = ! empty($item_terms) ? implode(' ', $item_terms) : '';

            $button_label_parts = array();
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
                ? sprintf(__('View details for "%s"', 'image-organizer'), implode(' – ', $button_label_parts))
                : __('View image details', 'image-organizer');
            ?>
            <div class="io-gallery-item" role="listitem" <?php if ($show_filter) : ?>data-io-terms="<?php echo esc_attr($item_terms_attr); ?>"<?php endif; ?>>
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

        wp_send_json_success(array('html' => $html, 'has_more' => $has_more, 'next_page' => $next_page));
    }

    public function ajax_upload_image() {
        if (
            ! isset($_POST['nonce']) ||
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'io_upload_image')
        ) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 400);
        }

        // Honeypot.
        $hp = isset($_POST['website']) ? sanitize_text_field(wp_unslash($_POST['website'])) : '';
        if (! empty($hp)) {
            wp_send_json_error(array('message' => 'Blocked'), 400);
        }

        // Identify which gallery instance is posting (used to look up upload_key server-side).
        $gallery_id = isset($_POST['io_gallery_id']) ? sanitize_text_field(wp_unslash($_POST['io_gallery_id'])) : '';
        if ('' === $gallery_id) {
            wp_send_json_error(array('message' => 'Missing gallery context'), 400);
        }

        // Optional shared key (internal gate) stored server-side per gallery instance.
        $required_key = (string) get_transient('io_upload_key_' . $gallery_id);
        $posted_key   = isset($_POST['io_upload_key']) ? sanitize_text_field(wp_unslash($_POST['io_upload_key'])) : '';

        if ('' !== $required_key && ! hash_equals($required_key, $posted_key)) {
            wp_send_json_error(array('message' => 'Invalid upload key'), 403);
        }

        if (empty($_FILES['io_file']) || ! isset($_FILES['io_file']['tmp_name'])) {
            wp_send_json_error(array('message' => 'No file provided'), 400);
        }

        // Validate file is a real PNG and exactly 650px wide (before moving it).
        $tmp_name = $_FILES['io_file']['tmp_name'];

        if (! is_uploaded_file($tmp_name)) {
            wp_send_json_error(array('message' => 'Invalid upload.'), 400);
        }

        $check = wp_check_filetype_and_ext($tmp_name, $_FILES['io_file']['name']);
        if (empty($check['type']) || 'image/png' !== $check['type']) {
            wp_send_json_error(array('message' => 'Only PNG files are allowed.'), 400);
        }

        $size = @getimagesize($tmp_name);
        if (empty($size) || empty($size[0])) {
            wp_send_json_error(array('message' => 'Could not read image dimensions.'), 400);
        }

        $width = (int) $size[0];
        if (650 !== $width) {
            wp_send_json_error(array('message' => 'PNG must be exactly 650px wide.'), 400);
        }

        $max_mb    = isset($_POST['io_max_mb']) ? max(1, (int) sanitize_text_field(wp_unslash($_POST['io_max_mb']))) : 5;
        $max_bytes = $max_mb * 1024 * 1024;

        if (isset($_FILES['io_file']['size']) && (int) $_FILES['io_file']['size'] > $max_bytes) {
            wp_send_json_error(array('message' => 'File too large'), 400);
        }

        // Rate limit by IP.
        $ip       = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $rate_key = 'io_upload_rl_' . md5($ip);
        $count    = (int) get_transient($rate_key);
        if ($count > 20) {
            wp_send_json_error(array('message' => 'Rate limit exceeded'), 429);
        }
        set_transient($rate_key, $count + 1, 10 * MINUTE_IN_SECONDS);

        // Allowed extensions/mimes.
        $overrides = array(
            'test_form' => false,
            'mimes'     => array(
                'png' => 'image/png',
            ),
        );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file = wp_handle_upload($_FILES['io_file'], $overrides);

        if (isset($file['error'])) {
            wp_send_json_error(array('message' => $file['error']), 400);
        }

        // IMPORTANT: these keys must match the form field names.
        $title          = isset($_POST['io_title']) ? sanitize_text_field(wp_unslash($_POST['io_title'])) : '';
        $description    = isset($_POST['io_description']) ? sanitize_textarea_field(wp_unslash($_POST['io_description'])) : '';
        $alt            = isset($_POST['io_alt']) ? sanitize_text_field(wp_unslash($_POST['io_alt'])) : '';
        $category_slug  = isset($_POST['io_upload_category']) ? sanitize_title(wp_unslash($_POST['io_upload_category'])) : '';

        // Support BOTH hidden field names.
        $mlo_slug = '';
        if (isset($_POST['io_upload_media_category'])) {
            $mlo_slug = sanitize_title(wp_unslash($_POST['io_upload_media_category']));
        } elseif (isset($_POST['io_upload_media_categories'])) {
            $mlo_slug = sanitize_title(wp_unslash($_POST['io_upload_media_categories']));
        }

        $require_review = ! empty($_POST['io_require_review']) && '1' === sanitize_text_field(wp_unslash($_POST['io_require_review']));

        $attachment = array(
            'post_mime_type' => $file['type'],
            'post_title'     => $title ? $title : preg_replace('/\.[^.]+$/', '', basename($file['file'])),
            'post_content'   => $description,
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $file['file']);
        if (is_wp_error($attach_id)) {
            wp_send_json_error(array('message' => 'Could not create attachment'), 500);
        }

        $meta = wp_generate_attachment_metadata($attach_id, $file['file']);
        wp_update_attachment_metadata($attach_id, $meta);

        if ('' !== $alt) {
            update_post_meta($attach_id, '_wp_attachment_image_alt', $alt);
        }

        // Mark as internal/pending review if desired.
        update_post_meta($attach_id, '_io_uploaded_public', 1);
        if ($require_review) {
            update_post_meta($attach_id, '_io_pending_review', 1);
        }

        // Optional: assign WP category term.
        if ($category_slug) {
            $term = get_term_by('slug', $category_slug, 'category');
            if ($term && ! is_wp_error($term)) {
                wp_set_object_terms($attach_id, (int) $term->term_id, 'category', true);
            }
        }

        // Optional: assign Media Library Organizer "media_category" term.
        if ($mlo_slug) {
            if (! $this->mlo_is_available()) {
                wp_send_json_error(array('message' => 'Media Library Organizer taxonomy not available.'), 400);
            }

            $mlo_term = get_term_by('slug', $mlo_slug, self::MLO_TAXONOMY);
            if (! $mlo_term || is_wp_error($mlo_term)) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        'Media category "%s" not found. Create it first in Media Library Organizer.',
                        $mlo_slug
                    ),
                ), 400);
            }

            // Assign by term ID (append true).
            wp_set_object_terms($attach_id, array((int) $mlo_term->term_id), self::MLO_TAXONOMY, true);
        }

        $image_src    = wp_get_attachment_image_src($attach_id, 'large');
        $thumb_html   = wp_get_attachment_image($attach_id, 'medium', false, array('class' => 'io-gallery-thumb'));
        $download_url = wp_get_attachment_url($attach_id);

        wp_send_json_success(
            array(
                'id'          => $attach_id,
                'src'         => $image_src ? $image_src[0] : $download_url,
                'download'    => $download_url,
                'title'       => get_the_title($attach_id),
                'caption'     => wp_get_attachment_caption($attach_id),
                'description' => get_post_field('post_content', $attach_id),
                'alt'         => get_post_meta($attach_id, '_wp_attachment_image_alt', true),
                'thumb'       => wp_kses_post($thumb_html),
                'pending'     => $require_review,
            )
        );
    }
}

new Image_Organizer_Gallery();