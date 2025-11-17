jQuery(function ($) {

    /**
     * Open a modal within a specific gallery wrapper.
     * @param {jQuery} $wrapper
     * @param {jQuery} $trigger
     * @param {Object} data
     */
    function openModal($wrapper, $trigger, data) {
        var $modal = $wrapper.find('.io-modal');
        if (!$modal.length) {
            return;
        }

        var $dialog      = $modal.find('.io-modal-dialog');
        var $image       = $modal.find('.io-modal-image');
        var $title       = $modal.find('.io-modal-title');
        var $caption     = $modal.find('.io-modal-caption');
        var $description = $modal.find('.io-modal-description');
        var $alt         = $modal.find('.io-modal-alt');
        var $download    = $modal.find('.io-modal-download');

        $image.attr('src', data.src || '');
        $image.attr('alt', data.alt || '');
        $title.text(data.title || '');
        $caption.text(data.caption || '');
        $description.text(data.description || '');
        $alt.text(data.alt || '');
        $download.attr('href', data.download || '#');

        // Remember last focused element to restore focus on close
        $modal.data('io-last-focus', $trigger);

        $modal.addClass('io-open').attr('aria-hidden', 'false');
        $('body').addClass('io-modal-open');

        // Move focus into dialog for screen readers & keyboard users
        if ($dialog.length) {
            $dialog.trigger('focus');
        }
    }

    /**
     * Close a specific modal, restore focus to the last trigger.
     * @param {jQuery} $modal
     */
    function closeModal($modal) {
        if (!$modal || !$modal.length) {
            return;
        }

        $modal.removeClass('io-open').attr('aria-hidden', 'true');
        $('body').removeClass('io-modal-open');

        var $last = $modal.data('io-last-focus');
        if ($last && $last.length) {
            $last.trigger('focus');
        }
    }

    /**
     * Trap focus inside the modal dialog when open.
     * @param {KeyboardEvent} e
     * @param {jQuery} $dialog
     */
    function trapFocus(e, $dialog) {
        if (e.key !== 'Tab') {
            return;
        }

        var $modal = $dialog.closest('.io-modal');
        var $focusable = $modal
            .find('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])')
            .filter(':visible');

        if (!$focusable.length) {
            return;
        }

        var first = $focusable[0];
        var last  = $focusable[$focusable.length - 1];

        if (e.shiftKey) {
            if (document.activeElement === first) {
                e.preventDefault();
                last.focus();
            }
        } else {
            if (document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    // ------------------------------------
    // Modal open/close behavior
    // ------------------------------------

    // Open modal on image button click
    $(document).on('click', '.io-gallery-trigger', function (e) {
        e.preventDefault();
        var $btn     = $(this);
        var $wrapper = $btn.closest('.io-gallery-wrapper');

        var data = {
            title:       $btn.data('io-title'),
            caption:     $btn.data('io-caption'),
            description: $btn.data('io-description'),
            alt:         $btn.data('io-alt'),
            src:         $btn.data('io-src'),
            download:    $btn.data('io-download')
        };

        openModal($wrapper, $btn, data);
    });

    // Close modal when clicking close button or backdrop
    $(document).on('click', '.io-modal-close, .io-modal-backdrop', function () {
        var $modal = $(this).closest('.io-modal');
        closeModal($modal);
    });

    // ESC to close any open modal
    $(document).on('keyup', function (e) {
        if (e.key === 'Escape') {
            $('.io-modal.io-open').each(function () {
                closeModal($(this));
            });
        }
    });

    // Trap focus inside open modal dialog
    $(document).on('keydown', '.io-modal.io-open .io-modal-dialog', function (e) {
        trapFocus(e, $(this));
    });

    // ------------------------------------
    // Filter buttons (with aria-pressed)
    // ------------------------------------

    $(document).on('click', '.io-filter-button', function () {
        var $btn  = $(this);
        var term  = $btn.data('io-term');

        $btn
            .addClass('io-filter-active')
            .attr('aria-pressed', 'true')
            .siblings('.io-filter-button')
            .removeClass('io-filter-active')
            .attr('aria-pressed', 'false');

        var $wrapper = $btn.closest('.io-gallery-wrapper');
        var $items   = $wrapper.find('.io-gallery-item');

        if (term === 'all') {
            $items.show();
            return;
        }

        $items.each(function () {
            var $item     = $(this);
            var itemTerms = ($item.data('io-terms') || '').toString().split(/\s+/);

            if (itemTerms.indexOf(term) !== -1) {
                $item.show();
            } else {
                $item.hide();
            }
        });
    });

    // ------------------------------------
    // Load more + live region updates
    // ------------------------------------

    $(document).on('click', '.io-load-more', function () {
        var $btn     = $(this);
        var $wrapper = $btn.closest('.io-gallery-wrapper');
        var $gallery = $wrapper.find('.io-gallery');
        var $status  = $wrapper.find('.io-status');

        var currentPage = parseInt($wrapper.data('io-current-page'), 10) || 1;
        var maxPages    = parseInt($wrapper.data('io-max-pages'), 10) || 1;

        if (currentPage >= maxPages) {
            $btn.remove();
            if ($status.length) {
                $status.text('No more images to load.');
            }
            return;
        }

        $btn.prop('disabled', true).text('Loading...');

        var data = {
            action:          'io_load_more',
            nonce:           (typeof ImageOrganizerData !== 'undefined') ? ImageOrganizerData.nonce : '',
            page:            currentPage + 1,
            per_page:        $wrapper.data('io-per-page'),
            columns:         $wrapper.data('io-columns'),
            categories:      $wrapper.data('io-categories') || '',
            tags:            $wrapper.data('io-tags') || '',
            filter_taxonomy: $wrapper.data('io-filter-taxonomy') || 'category',
            show_filter:     $wrapper.data('io-show-filter') || 'false',
            ids:             $wrapper.data('io-ids') || ''
        };

        $.post(ImageOrganizerData.ajax_url, data, function (response) {
            if (!response || !response.success) {
                $btn.prop('disabled', false).text('Load more');
                if ($status.length) {
                    $status.text('Error loading images.');
                }
                return;
            }

            var html = response.data && response.data.html ? response.data.html : '';

            if (html) {
                // Count new items before appending for announcement
                var $tmp     = $('<div>').html(html);
                var newCount = $tmp.find('.io-gallery-item').length;

                $gallery.append(html);

                if ($status.length && newCount > 0) {
                    $status.text(newCount === 1 ? '1 image loaded.' : newCount + ' images loaded.');
                }
            }

            if (response.data && response.data.has_more) {
                var nextPage = response.data.next_page || currentPage + 1;
                $wrapper.data('io-current-page', nextPage);
                $btn.prop('disabled', false).text('Load more');
            } else {
                $btn.remove();
                if ($status.length) {
                    $status.text('No more images to load.');
                }
            }
        });
    });

});
