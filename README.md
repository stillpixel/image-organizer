# Image Organizer Gallery

**Version:** 1.1.0\
**Requires at least:** WordPress 6.0+\
**Tested up to:** WordPress 6.8.x\
**Requires PHP:** 7.4+\
**License:** GPL-2.0+\
**Author:** Ron Rattie\
**Description:**\
A powerful yet lightweight image organizer and gallery plugin for
WordPress. It allows you to manage images with categories & tags,
display them in a responsive frontend gallery, open an image modal with
full metadata, and load additional images dynamically via AJAX
pagination.

## 📌 Overview

Image Organizer Gallery enhances WordPress' media library and turns it
into a fully-organized, filterable, paginated gallery system.

### Key Features

-   **Uses WordPress Media Library metadata**\
    (title, caption, description, alt text)

-   **Allows categories & tags for images**\
    Media attachments can be organized with normal WordPress `category`
    and `post_tag` taxonomies.

-   **Frontend gallery shortcode**\
    Responsive CSS grid with customizable columns and ordering.

-   **Modal image viewer**\
    Displays full-size image, title, caption, description, alt text, and
    a download button.

-   **Dynamic taxonomy filter**\
    Shows category or tag filter buttons allowing users to filter images
    client-side.

-   **AJAX Pagination ("Load More")**\
    Load images in batches instead of dumping everything at once.

-   **Lightweight --- no external libraries**\
    Uses built-in WP functions and a small amount of clean JS/CSS.

## 📦 Installation

1.  Upload the plugin folder `image-organizer` to:

        wp-content/plugins/

2.  Activate the plugin from\
    **WordPress Admin → Plugins → Installed Plugins**\

3.  The plugin is now ready for use.

## 🖼️ Adding Metadata to Images

This plugin uses the built-in metadata fields from the WordPress Media
Library:

-   **Title**
-   **Caption**
-   **Description**
-   **Alt Text**

To edit metadata:

1.  Go to **Media → Library**
2.  Switch to **List view**
3.  Click on an image
4.  Enter the title, caption, alt text, and description
5.  Click **Update**

All metadata appears automatically in the frontend modal.

## 📚 Assigning Categories & Tags to Images

The plugin enables WordPress' existing taxonomies on attachments.

To categorize images:

1.  Open **Media → Library**
2.  Switch to **List view**
3.  Click an image to edit it
4.  On the right sidebar you will now see:
    -   **Categories**
    -   **Tags**
5.  Assign any categories/tags you want

These categories and tags will be used for filtering and querying.

## 🔧 Shortcode Usage

Insert the gallery using:

    [image_organizer]

### Shortcode Parameters

  ----------------------------------------------------------------------------
  Parameter           Type           Default          Description
  ------------------- -------------- ---------------- ------------------------
  `columns`           int            4                Number of grid columns
                                                      (1--6)

  `limit`             int            12               Number of images per
                                                      "page" (AJAX batch size)

  `ids`               CSV            empty            Only show specified
                                                      attachment IDs

  `categories`        CSV            empty            Filter: include only
                                                      images in these category
                                                      slugs

  `tags`              CSV            empty            Filter: include only
                                                      images in these tag
                                                      slugs

  `show_filter`       true/false     false            Show the filter bar

  `filter_taxonomy`   category/tag   category         Which taxonomy the
                                                      filter bar uses
  ----------------------------------------------------------------------------

### Basic Gallery

    [image_organizer columns="4" limit="12"]

### Enable Category Filter

    [image_organizer show_filter="true" filter_taxonomy="category"]

### Show Only a Specific Category

    [image_organizer categories="landscape,portraits"]

### Filter Bar Using Tags

    [image_organizer show_filter="true" filter_taxonomy="tag"]

### Only Show Images Tagged "featured"

    [image_organizer tags="featured" limit="16" columns="3"]

## ⚙️ AJAX Pagination ("Load More")

The plugin includes a bottom "Load more" button.

### How It Works

-   The first batch (`limit`) is loaded with the shortcode\
-   Clicking **Load More** requests the next batch from
    `admin-ajax.php`\
-   Images are appended dynamically\
-   The button hides automatically when no more images are available

## 🖼️ Modal Viewer

Clicking an image opens a responsive modal containing:

-   Large version of the image\
-   Title\
-   Caption\
-   Full description\
-   Alt text\
-   Download link for the original image

Modal can be closed by:

-   Close button\
-   Clicking background overlay\
-   ESC key

## 🎨 Styling Notes

Gallery layout uses CSS Grid:

-   `.io-gallery`
-   `.io-columns-*`
-   `.io-filters`
-   `.io-pagination`
-   `.io-modal`

Override in theme CSS anytime.

## 🚀 Developer Info

### AJAX Endpoint

    action: io_load_more
    method: POST
    file: admin-ajax.php

### Localized Script Vars

``` js
ImageOrganizerData.ajax_url  
ImageOrganizerData.nonce
```

### Files

    image-organizer/
      image-organizer.php
      README.md
      assets/
        css/frontend.css
        js/frontend.js

## 🤝 Contributing

Pull requests and enhancements welcome.

## 📄 License

GPL-2.0 or later.

## 🎉 Thank You!

Thanks for using Image Organizer Gallery!
