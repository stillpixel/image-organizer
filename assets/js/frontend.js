jQuery(function ($) {
    var $modal = $('#io-modal');
    var $image = $('#io-modal-image');
    var $title = $('#io-modal-title');
    var $caption = $('#io-modal-caption');
    var $description = $('#io-modal-description');
    var $alt = $('#io-modal-alt');
    var $download = $('#io-modal-download');

    function openModal(data) {
        $image.attr('src', data.src || '');
        $image.attr('alt', data.alt || '');
        $title.text(data.title || '');
        $caption.text(data.caption || '');
        $description.text(data.description || '');
        $alt.text(data.alt || '');
        $download.attr('href', data.download || '#');

        $modal.addClass('io-open').attr('aria-hidden', 'false');
        $('body').addClass('io-modal-open');
    }

    function closeModal() {
        $modal.removeClass('io-open').attr('aria-hidden', 'true');
        $('body').removeClass('io-modal-open');
    }

    // Open modal on image click
    $(document).on('click', '.io-gallery-trigger', function (e) {
        e.preventDefault();
        var $btn = $(this);

        var data = {
            title: $btn.data('io-title'),
            caption: $btn.data('io-caption'),
            description: $btn.data('io-description'),
            alt: $btn.data('io-alt'),
            src: $btn.data('io-src'),
            download: $btn.data('io-download')
        };

        openModal(data);
    });

    // Close modal handlers
    $modal.on('click', '.io-modal-close, .io-modal-backdrop', function () {
        closeModal();
    });

    $(document).on('keyup', function (e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Filter buttons
    $(document).on('click', '.io-filter-button', function () {
        var $btn = $(this);
        var term = $btn.data('io-term');

        // Set active state
        $btn
            .addClass('io-filter-active')
            .siblings('.io-filter-button')
            .removeClass('io-filter-active');

        var $wrapper = $btn.closest('.io-gallery-wrapper');
        var $items = $wrapper.find('.io-gallery-item');

        if (term === 'all') {
            $items.show();
            return;
        }

        $items.each(function () {
            var $item = $(this);
            var itemTerms = ($item.data('io-terms') || '').toString().split(/\s+/);

            if (itemTerms.indexOf(term) !== -1) {
                $item.show();
            } else {
                $item.hide();
            }
        });
    });
});
