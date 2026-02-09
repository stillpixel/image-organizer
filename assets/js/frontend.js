jQuery(function ($) {

    // Track the most recent text-search request to avoid stale AJAX overwrites
    var ioSearchRequestId = 0;

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

        var $dialog = $modal.find('.io-modal-dialog');
        var $image = $modal.find('.io-modal-image');
        var $title = $modal.find('.io-modal-title');
        var $caption = $modal.find('.io-modal-caption');
        var $description = $modal.find('.io-modal-description-text');
        var $alt = $modal.find('.io-modal-alt');
        var $download = $modal.find('.io-modal-download');

        $image.attr('src', data.src || '');
        $image.attr('alt', data.alt || '');
        $title.text(data.title || '');
        $caption.text(data.caption || '');
        $description.text(data.description || '');
        $alt.text(data.alt || '');
        $download.attr('href', data.download || '#');

        // Remember last focused element to restore on close
        $modal.data('io-last-focus', $trigger);

        $modal.addClass('io-open').attr('aria-hidden', 'false');
        $('body').addClass('io-modal-open');

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
        var last = $focusable[$focusable.length - 1];

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

    /**
     * Copy the current modal alt text to the clipboard.
     * @param {jQuery} $wrapper - The .io-gallery-wrapper for this instance.
     */
    function copyAltText($wrapper) {
        var $modal = $wrapper.find('.io-modal');
        var $alt = $modal.find('.io-modal-alt');
        var text = $.trim($alt.text() || '');

        if (!text) {
            return;
        }

        var $status = $wrapper.find('.io-status');

        function announce(message) {
            if ($status.length) {
                $status.text(message);
            }
        }

        function fallbackCopy() {
            var $temp = $('<textarea readonly></textarea>')
                .css({
                    position: 'absolute',
                    left: '-9999px',
                    top: '0'
                })
                .val(text)
                .appendTo('body');

            $temp[0].select();
            try {
                document.execCommand('copy');
                announce('Alt text copied to clipboard.');
            } catch (err) {
                announce('Unable to copy alt text.');
            }
            $temp.remove();
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(function () {
                    announce('Alt text copied to clipboard.');
                })
                .catch(function () {
                    fallbackCopy();
                });
        } else {
            fallbackCopy();
        }
    }

    /**
     * Apply combined taxonomy + text search filtering to items in this wrapper.
     * - Taxonomy filter is controlled by .io-filter-button.io-filter-active (if enabled).
     * - Text filter is from .io-text-filter-input (matches title, caption, description).
     * @param {jQuery} $wrapper
     */
    function applyCombinedFilter($wrapper) {
        var showFilter = String($wrapper.data('io-show-filter')) === 'true';
        var $search = $wrapper.find('.io-text-filter-input');
        var query = ($search.val() || '').toString().toLowerCase().trim();

        var activeTerm = 'all';
        if (showFilter) {
            var $active = $wrapper.find('.io-filter-button.io-filter-active').first();
            if ($active.length) {
                activeTerm = $active.data('io-term') || 'all';
            }
        }

        var $items = $wrapper.find('.io-gallery-item');

        $items.each(function () {
            var $item = $(this);
            var $btn = $item.find('.io-gallery-trigger').first();

            // Taxonomy match
            var passesTax = true;
            if (showFilter) {
                var itemTermsRaw = ($item.data('io-terms') || '').toString();
                var itemTerms = itemTermsRaw.length ? itemTermsRaw.split(/\s+/) : [];
                if (activeTerm !== 'all') {
                    passesTax = itemTerms.indexOf(activeTerm) !== -1;
                }
            }

            // Text/title/description match
            var title = ($btn.data('io-title') || '').toString();
            var caption = ($btn.data('io-caption') || '').toString();
            var desc = ($btn.data('io-description') || '').toString();

            var haystack = (title + ' ' + caption + ' ' + desc).toLowerCase();
            var passesSearch = !query || haystack.indexOf(query) !== -1;

            if (passesTax && passesSearch) {
                $item.show();
            } else {
                $item.hide();
            }
        });
    }

    // ------------------------------------
    // Modal open/close behavior
    // ------------------------------------

    $(document).on('click', '.io-gallery-trigger', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $wrapper = $btn.closest('.io-gallery-wrapper');

        var data = {
            title: $btn.data('io-title'),
            caption: $btn.data('io-caption'),
            description: $btn.data('io-description'),
            alt: $btn.data('io-alt'),
            src: $btn.data('io-src'),
            download: $btn.data('io-download')
        };

        openModal($wrapper, $btn, data);
    });

    $(document).on('click', '.io-modal-close, .io-modal-backdrop', function () {
        var $modal = $(this).closest('.io-modal');
        closeModal($modal);
    });

    $(document).on('keyup', function (e) {
        if (e.key === 'Escape') {
            $('.io-modal.io-open').each(function () {
                closeModal($(this));
            });
        }
    });

    $(document).on('keydown', '.io-modal.io-open .io-modal-dialog', function (e) {
        trapFocus(e, $(this));
    });

    // ------------------------------------
    // Alt text copy handlers
    // ------------------------------------

    $(document).on('click', '.io-modal-alt', function () {
        var $wrapper = $(this).closest('.io-gallery-wrapper');
        copyAltText($wrapper);
    });

    $(document).on('keydown', '.io-modal-alt', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            var $wrapper = $(this).closest('.io-gallery-wrapper');
            copyAltText($wrapper);
        }
    });

    $(document).on('click', '.io-alt-copy-button', function () {
        var $wrapper = $(this).closest('.io-gallery-wrapper');
        copyAltText($wrapper);
    });

    // ------------------------------------
    // Taxonomy filter buttons
    // ------------------------------------

    $(document).on('click', '.io-filter-button', function () {
        var $btn = $(this);
        var $wrapper = $btn.closest('.io-gallery-wrapper');

        $btn
            .addClass('io-filter-active')
            .attr('aria-pressed', 'true')
            .siblings('.io-filter-button')
            .removeClass('io-filter-active')
            .attr('aria-pressed', 'false');

        applyCombinedFilter($wrapper);
    });

    // ------------------------------------
    // Text search filter (title/description) using server-side search
    // ------------------------------------

    $(document).on('input', '.io-text-filter-input', function () {
        var $input = $(this);
        var $wrapper = $input.closest('.io-gallery-wrapper');
        var $gallery = $wrapper.find('.io-gallery');
        var $status = $wrapper.find('.io-status');
        var $loadMore = $wrapper.find('.io-load-more');

        var query = ($input.val() || '').toString();

        // Increment global request ID and capture this one
        ioSearchRequestId++;
        var thisRequestId = ioSearchRequestId;

        var data = {
            action: 'io_search_images',
            nonce: (typeof ImageOrganizerData !== 'undefined') ? ImageOrganizerData.nonce : '',
            search: query,
            categories: $wrapper.data('io-categories') || '',
            tags: $wrapper.data('io-tags') || '',
            filter_taxonomy: $wrapper.data('io-filter-taxonomy') || 'category',
            show_filter: $wrapper.data('io-show-filter') || 'false',
            ids: $wrapper.data('io-ids') || ''
        };

        $.post(ImageOrganizerData.ajax_url, data, function (response) {
            // Ignore stale responses (if a newer request was fired)
            if (thisRequestId !== ioSearchRequestId) {
                return;
            }

            if (!response || !response.success) {
                if ($status.length) {
                    $status.text('Error filtering images.');
                }
                return;
            }

            var html = response.data && response.data.html ? response.data.html : '';
            $gallery.html(html);

            // Once we are in "search" mode, disable pagination for this view
            if ($loadMore.length) {
                $loadMore.remove();
            }

            if ($status.length) {
                var $tmp = $('<div>').html(html);
                var count = $tmp.find('.io-gallery-item').length;
                var qTrim = query.trim();

                if (qTrim === '') {
                    $status.text(
                        count === 1
                            ? 'Showing all 1 image.'
                            : 'Showing all ' + count + ' images.'
                    );
                } else {
                    $status.text(
                        count === 1
                            ? '1 image matches your search.'
                            : count + ' images match your search.'
                    );
                }
            }
        });
    });

    // Clear search: reset the gallery back to its original state
    $(document).on('click', '.io-text-filter-clear', function () {
        var $wrapper = $(this).closest('.io-gallery-wrapper');
        var $input = $wrapper.find('.io-text-filter-input');
        var $status = $wrapper.find('.io-status');

        // Clear the input visually (not strictly needed since we reload, but nice)
        $input.val('');

        if ($status.length) {
            $status.text('');
        }

        // Easiest and most robust: full page reload restores original gallery,
        // including pagination, filters, and markup as rendered by the shortcode.
        window.location.reload();
    });

    // ------------------------------------
    // Upload form submit (AJAX)
    // ------------------------------------

    $(document).on('submit', '.io-upload-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $wrapper = $form.closest('.io-gallery-wrapper');
        var $gallery = $wrapper.find('.io-gallery');
        var $status = $form.find('.io-upload-status');

        if (typeof ImageOrganizerData === 'undefined' || !ImageOrganizerData.ajax_url) {
            if ($status.length) {
                $status.text('Upload error: missing AJAX configuration.');
            }
            return;
        }

        var fileInput = $form.find('input[type="file"][name="io_file"]')[0];
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
            if ($status.length) {
                $status.text('Please choose an image to upload.');
            }
            return;
        }

        // Disable submit while uploading
        var $submit = $form.find('.io-upload-submit');
        $submit.prop('disabled', true);

        if ($status.length) {
            $status.text('Uploading…');
        }

        var fd = new FormData($form[0]);
        fd.append('action', 'io_upload_image');
        fd.append('nonce', ImageOrganizerData.upload_nonce || '');

        // Ensure gallery context is included (your PHP expects io_gallery_id)
        if (!fd.get('io_gallery_id')) {
            fd.append('io_gallery_id', $wrapper.attr('id') || '');
        }

        $.ajax({
            url: ImageOrganizerData.ajax_url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (response) {
                if (!response || !response.success) {
                    var msg = (response && response.data && response.data.message) ? response.data.message : 'Upload failed.';
                    if ($status.length) {
                        $status.text(msg);
                    }
                    return;
                }

                var data = response.data || {};

                // Build a new gallery item consistent with server markup
                var $item = $('<div/>', {
                    'class': 'io-gallery-item',
                    'role': 'listitem'
                });

                var ariaLabel = 'View image details';
                if (data.title) {
                    ariaLabel = 'View details for "' + data.title + '"';
                }

                var $btn = $('<button/>', {
                    'class': 'io-gallery-trigger',
                    'type': 'button',
                    'aria-label': ariaLabel
                });

                $btn.attr('data-io-title', data.title || '');
                $btn.attr('data-io-caption', data.caption || '');
                $btn.attr('data-io-description', data.description || '');
                $btn.attr('data-io-alt', data.alt || '');
                $btn.attr('data-io-src', data.src || data.download || '');
                $btn.attr('data-io-download', data.download || '');

                // thumb returned as HTML from server (already sanitized with wp_kses_post server-side)
                if (data.thumb) {
                    $btn.append($(data.thumb));
                } else {
                    // fallback: show an img
                    $btn.append($('<img/>', { 'class': 'io-gallery-thumb', 'src': (data.src || ''), 'alt': (data.alt || '') }));
                }

                $item.append($btn);

                // Add to top of gallery so user sees it immediately
                if ($gallery.length) {
                    $gallery.prepend($item);
                }

                // Clear the form
                $form[0].reset();

                if ($status.length) {
                    if (data.pending) {
                        $status.text('Upload received. This image is pending review.');
                    } else {
                        $status.text('Upload successful.');
                    }
                }
            },
            error: function (xhr) {
                var msg = 'Upload failed.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                if ($status.length) {
                    $status.text(msg);
                }
            },
            complete: function () {
                $submit.prop('disabled', false);
            }
        });
    });

    // ------------------------------------
    // Load more + live region updates
    // ------------------------------------

    $(document).on('click', '.io-load-more', function () {
        var $btn = $(this);
        var $wrapper = $btn.closest('.io-gallery-wrapper');
        var $gallery = $wrapper.find('.io-gallery');
        var $status = $wrapper.find('.io-status');

        var currentPage = parseInt($wrapper.data('io-current-page'), 10) || 1;
        var maxPages = parseInt($wrapper.data('io-max-pages'), 10) || 1;

        if (currentPage >= maxPages) {
            $btn.remove();
            if ($status.length) {
                $status.text('No more images to load.');
            }
            return;
        }

        $btn.prop('disabled', true).text('Loading...');

        var data = {
            action: 'io_load_more',
            nonce: (typeof ImageOrganizerData !== 'undefined') ? ImageOrganizerData.nonce : '',
            page: currentPage + 1,
            per_page: $wrapper.data('io-per-page'),
            columns: $wrapper.data('io-columns'),
            categories: $wrapper.data('io-categories') || '',
            tags: $wrapper.data('io-tags') || '',
            filter_taxonomy: $wrapper.data('io-filter-taxonomy') || 'category',
            show_filter: $wrapper.data('io-show-filter') || 'false',
            ids: $wrapper.data('io-ids') || ''
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
                var $tmp = $('<div>').html(html);
                var newCount = $tmp.find('.io-gallery-item').length;

                $gallery.append(html);

                // Re-apply combined filter so new items respect current search+taxonomy
                applyCombinedFilter($wrapper);

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