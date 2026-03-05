# Image Organizer Gallery

**Version:** 1.1.5  
**Requires at least:** WordPress 6.0+  
**Tested up to:** WordPress 6.8.x  
**Requires PHP:** 7.4+  
**License:** GPL-2.0+  
**Author:** Ron Rattie  
**Plugin URI:** https://stillpixelstudios.com/  

**Description:**  
A powerful yet lightweight, fully accessible image organizer and gallery plugin for WordPress. Includes metadata display, category & tag filtering, a responsive modal viewer, text search, and AJAX “Load More” pagination — designed to meet **WCAG 2.1 AA** and **Section 508** requirements.

---

# 📌 Overview

Image Organizer Gallery enhances WordPress’ media library and turns it into a fully organized, filterable, searchable, paginated gallery system — now fully accessibility-optimized for government, education, and enterprise environments.

## Key Features

- **Uses WordPress Media Library metadata**  
  Automatically pulls title, caption, description, alt text, and file information.

- **Categories & tags for images**  
  Attachments support built-in `category` and `post_tag` taxonomies.

- **Media Library Organizer support (ThemeIsle)**  
  Optionally filter and assign **Media Categories** via the `media_category` taxonomy (provided by the Media Library Organizer plugin).

- **Frontend gallery shortcode**  
  Responsive CSS grid with customizable columns and batch sizes.

- **Accessible modal viewer**  
  Fully ARIA-labeled dialog showing full-size image, caption, description, alt text, and a download button.

- **Dynamic taxonomy filter bar**  
  Accessible button bar with active-state management and ARIA properties.

- **Text search (title/caption/description)**  
  Built-in search box to filter images by title, caption, and description text.

- **One-click alt-text copy**  
  Copy the image’s alt text to the clipboard by clicking the alt text or the copy icon in the modal.

- **AJAX pagination (“Load More”)**  
  Load images in batches for a faster, lighter gallery.

- **Designed for WCAG 2.1 AA & 508**  
  Includes focus management, ARIA roles, reduced-motion support, focus trapping, live region announcements, and color-contrast improvements.

---

# 📦 Installation

1. Upload the plugin folder **`image-organizer`** to:

```
wp-content/plugins/
```

2. Activate in:  
   **WordPress Admin → Plugins**

3. Use shortcode in any page or post.

---

# 🖼️ Adding Metadata to Images

The plugin uses native WordPress metadata:

- Title  
- Caption  
- Description  
- Alt text  

To edit metadata:

1. Go to **Media → Library**
2. Switch to **List view**  
3. Click an image  
4. Modify metadata fields  
5. Press **Update**

All fields populate automatically in the modal.

---

# 📚 Assigning Categories & Tags to Images

This plugin enables categories & tags on attachments.

To categorize images:

1. Open **Media → Library**  
2. Click an image  
3. Assign **Categories** and **Tags** from the sidebar  
4. Save changes

These terms are used for filtering and querying.

---

# 🗂️ Media Categories (Media Library Organizer plugin)

If you install **Media Library Organizer** by ThemeIsle, your media items can also use a taxonomy named:

- `media_category`

This plugin can:
- **Filter the gallery by `media_category`** (via shortcode)
- **Show a filter bar based on `media_category`**
- **Assign a `media_category` to uploads** (via shortcode)

> If Media Library Organizer is not installed/active, any `media_category` options are ignored gracefully.

---

# 🔧 Shortcode Usage

Use the gallery shortcode:

```
[image_organizer]
```

## Parameters

| Parameter                | Type                     | Default    | Description |
|-------------------------|--------------------------|------------|-------------|
| `columns`               | int                      | 4          | Grid columns (1–6) |
| `limit`                 | int                      | 12         | Images per batch |
| `ids`                   | CSV list                 | (empty)    | Restrict to specific attachment IDs |
| `categories`            | CSV list                 | (empty)    | Filter by **WordPress category** slugs |
| `tags`                  | CSV list                 | (empty)    | Filter by **WordPress tag** slugs |
| `media_categories`      | CSV list                 | (empty)    | Filter by **Media Library Organizer** `media_category` slugs |
| `show_filter`           | true/false               | false      | Enable taxonomy filter bar |
| `filter_taxonomy`       | category/tag/media_category/mlo | category | Which taxonomy the filter bar uses |
| `aria_label`            | string                   | (auto)     | Optional accessible label for the gallery region |

### Upload Parameters (optional)

| Parameter                | Type         | Default  | Description |
|-------------------------|--------------|----------|-------------|
| `allow_upload`          | true/false   | false    | Show the upload form |
| `upload_key`            | string       | (empty)  | Optional shared “upload key” gate (prompts user if set) |
| `upload_max_mb`         | int          | 20       | Max upload size in MB |
| `upload_require_review` | true/false   | true     | If true, marks uploads with `_io_pending_review = 1` |
| `upload_category`       | slug         | (empty)  | Assign uploaded files to a **WP `category`** term slug |
| `upload_media_category` | slug         | (empty)  | Assign uploaded files to **MLO `media_category`** term slug |
| `upload_note_text`      | string       | (empty)  | Note text displayed under “Upload an image” |
| `upload_note_url_text`  | string       | (empty)  | Linked text (anchor text) for the note URL |
| `upload_note_url`       | url          | (empty)  | URL used for the linked note |

---

# ✅ Shortcode Examples

## 1) Basic gallery

```
[image_organizer columns="4" limit="12"]
```

## 2) Restrict to specific attachment IDs

```
[image_organizer ids="101,102,103" columns="3" limit="9"]
```

## 3) Show only images in WordPress categories

```
[image_organizer categories="landscape,portraits" columns="4" limit="12"]
```

## 4) Show only images with WordPress tags

```
[image_organizer tags="featured,homepage" columns="3" limit="18"]
```

## 5) Enable filter bar using WordPress categories

```
[image_organizer show_filter="true" filter_taxonomy="category"]
```

## 6) Enable filter bar using WordPress tags

```
[image_organizer show_filter="true" filter_taxonomy="tag"]
```

## 7) Filter using Media Library Organizer “Media Categories”

> Requires the Media Library Organizer plugin (ThemeIsle).  
> Uses taxonomy: `media_category`

```
[image_organizer media_categories="icons,backgrounds" columns="4" limit="12"]
```

## 8) Enable filter bar using Media Library Organizer “Media Categories”

You can use either value:

- `filter_taxonomy="media_category"`
- `filter_taxonomy="mlo"`

```
[image_organizer show_filter="true" filter_taxonomy="media_category"]
```

or

```
[image_organizer show_filter="true" filter_taxonomy="mlo"]
```

## 9) Combine filters (category + tags + media categories)

This will only show images matching **all** supplied taxonomy filters.

```
[image_organizer categories="landscape" tags="featured" media_categories="homepage" limit="12" columns="4"]
```

## 10) Enable uploads (PNG-only, 650px wide) + assign default categories

Assign uploaded items to:
- WordPress category slug: `user-uploads`
- Media Library Organizer media_category slug: `site-assets`

```
[image_organizer allow_upload="true" upload_category="user-uploads" upload_media_category="site-assets"]
```

## 11) Upload note text + link below “Upload an image”

```
[image_organizer
  allow_upload="true"
  upload_note_text="Before uploading, please review our "
  upload_note_url_text="image guidelines"
  upload_note_url="https://example.com/guidelines"
]
```

## 12) Upload gate with an upload key

```
[image_organizer allow_upload="true" upload_key="YOUR-SHARED-KEY"]
```

---

# 🔍 Text Search Filter (Title & Description)

Each gallery instance includes a built-in **text search input** above the grid:

- Filters images based on:
  - **Title**
  - **Caption**
  - **Description**
- Works **together** with taxonomy filters.

---

# ⚙️ AJAX Pagination (“Load More”)

The gallery loads the first batch immediately. The “Load More” button:

- Fetches next images via AJAX  
- Appends items dynamically  
- Hides when no more content remains  
- Announces new images via ARIA live region  

---

# 🖼️ Modal Viewer

Displays:

- Full-size image  
- Title  
- Caption  
- Full description  
- Alt text  
- Download link  

Modal behavior:

- Close with button, backdrop click, or ESC  
- Focus trapping  
- Returns focus to trigger on close  

## Alt Text Copy Feature

Inside the modal:

- Click the alt text  
- Or click the **copy icon (📋)**  
- Copies alt text to clipboard  
- Announces `"Alt text copied to clipboard."` via live region  

Keyboard users can:
- Tab to the alt text  
- Press **Enter** or **Space**  

---

# 🎨 Styling

Gallery layout uses modern CSS Grid and utility classes:

- `.io-gallery`  
- `.io-columns-*`  
- `.io-filters`  
- `.io-text-filter` / `.io-text-filter-input`  
- `.io-pagination`  
- `.io-modal`  
- `.io-modal-alt-row`  
- `.io-alt-copy-button`  
- `.io-upload-card` / `.io-upload-grid` / `.io-upload-field`  

Override in your theme as needed.

---

# ♿ Accessibility Features (WCAG 2.1 AA / Section 508)

### Semantic Roles & ARIA
- `role="region"` with label  
- Modal uses `role="dialog"` and `aria-modal="true"`  
- Filter bar uses `role="toolbar"`  
- Live region `aria-live="polite"`  

### Keyboard Navigation
- Full tab order  
- Escape closes modal  
- Focus trapping inside modal  
- Focus-visible outlines  

### Screen Reader Enhancements
- Descriptive aria-labels on image triggers  
- Announcements for content loading & actions  
- Text search labeled properly  

### Reduced Motion
```
@media (prefers-reduced-motion: reduce) {
  transition-duration: 0.001ms !important;
  animation-duration: 0.001ms !important;
}
```

### Contrast
- Buttons and outlines meet WCAG AA contrast ratios.

---

# 🚀 Developer Info

### AJAX Endpoints

```
action: io_load_more
action: io_search_images
action: io_upload_image
method: POST
```

### Localized Script Vars

```js
ImageOrganizerData.ajax_url
ImageOrganizerData.nonce
ImageOrganizerData.upload_nonce
```

### File Structure

```
image-organizer/
  image-organizer.php
  README.md
  assets/
    img/
      banner-1544-500.png
      icon-512x512.png
    css/frontend.css
    js/frontend.js
```

---

# 🤝 Contributing

Pull requests and accessibility audits are welcome.

---

# 📄 License

GPL-2.0 or later.

---

# 🎉 Thank You!

Thanks for using **Image Organizer Gallery**.
