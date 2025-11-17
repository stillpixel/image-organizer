# Image Organizer Gallery

**Version:** 1.1.0  
**Requires at least:** WordPress 6.0+  
**Tested up to:** WordPress 6.8.x  
**Requires PHP:** 7.4+  
**License:** GPL-2.0+  
**Author:** Ron Rattie  
**Plugin URI:** https://stillpixelstudios.com/  

**Description:**  
A powerful yet lightweight, fully accessible image organizer and gallery plugin for WordPress. Includes metadata display, category & tag filtering, a responsive modal viewer, and AJAX “Load More” pagination — designed to meet **WCAG 2.1 AA** and **Section 508** requirements.

---

# 📌 Overview

Image Organizer Gallery enhances WordPress’ media library and turns it into a fully organized, filterable, paginated gallery system — now fully accessibility-optimized for government, education, and enterprise environments.

## Key Features

- **Uses WordPress Media Library metadata**  
  Automatically pulls title, caption, description, alt text, and file information.

- **Categories & tags for images**  
  Attachments support built-in `category` and `post_tag` taxonomies.

- **Frontend gallery shortcode**  
  Responsive CSS grid with customizable columns and batch sizes.

- **Accessible modal viewer**  
  Fully ARIA-labeled dialog showing full-size image, caption, description, alt text, and a download button.

- **Dynamic taxonomy filter bar**  
  Accessible button bar with active-state management and ARIA properties.

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

# 🔧 Shortcode Usage

Use the gallery shortcode:

```
[image_organizer]
```

### Parameters

| Parameter         | Type        | Default  | Description |
|------------------|-------------|----------|-------------|
| `columns`        | int         | 4        | Grid columns (1–6) |
| `limit`          | int         | 12       | Images per batch |
| `ids`            | CSV list    | (empty)  | Restrict to specific attachment IDs |
| `categories`     | CSV list    | (empty)  | Filter by category slugs |
| `tags`           | CSV list    | (empty)  | Filter by tag slugs |
| `show_filter`    | true/false  | false    | Enable filter bar |
| `filter_taxonomy`| category/tag| category | Which taxonomy filter uses |

### Examples

**Basic gallery**
```
[image_organizer columns="4" limit="12"]
```

**Enable category filter**
```
[image_organizer show_filter="true" filter_taxonomy="category"]
```

**Show only specific categories**
```
[image_organizer categories="landscape,portraits"]
```

**Tag-based filter**
```
[image_organizer show_filter="true" filter_taxonomy="tag"]
```

**Only images tagged "featured"**
```
[image_organizer tags="featured" limit="16" columns="3"]
```

---

# ⚙️ AJAX Pagination (“Load More”)

The gallery loads the first batch immediately. The “Load More” button:

- Fetches next images via AJAX  
- Appends items dynamically  
- Hides when no more content remains  
- Announces new images via ARIA live region for screen readers  

---

# 🖼️ Modal Viewer

Displays:

- Full-size image  
- Title  
- Caption  
- Full description  
- Alt text  
- Download link  

Modal can be closed via:

- Close button  
- Clicking the backdrop  
- Pressing **ESC**

The modal traps keyboard focus until closed.

---

# 🎨 Styling

Gallery layout uses modern CSS Grid:

- `.io-gallery`  
- `.io-columns-*`  
- `.io-filters`  
- `.io-pagination`  
- `.io-modal`  

You may override any class in your theme stylesheet.

---

# ♿ Accessibility Features (WCAG 2.1 AA / Section 508)

## Semantic Roles & ARIA
- Gallery wrapper uses `role="region"` with `aria-label`.
- Modal uses `role="dialog"` with `aria-modal="true"`.
- Modal includes `aria-labelledby`, `aria-describedby`.
- Filter bar includes `role="toolbar"` and `aria-pressed` states.
- Live region (`aria-live="polite"`) announces dynamic updates.

## Keyboard Navigation
- Every interactive element is reachable via keyboard.
- Modal traps focus and returns focus to the trigger upon closing.
- ESC closes modal.
- Fully visible focus outlines using `:focus-visible`.

## Screen Reader Enhancements
- Each thumbnail trigger includes a descriptive `aria-label`.
- Live announcements when loading new images.
- Filter button states announced using `aria-pressed`.

## Reduced Motion Support
The plugin honors:

```css
@media (prefers-reduced-motion: reduce) {
    transition-duration: 0.001ms !important;
    animation-duration: 0.001ms !important;
}
```

## Color Contrast Improvements
- Buttons and active states meet WCAG AA contrast ratios.
- Outlines use accessible blue (#005fcc) with proper offset.

---

# 🚀 Developer Info

### AJAX Endpoint

```
action: io_load_more
method: POST
```

### Localized Script Vars

```js
ImageOrganizerData.ajax_url  
ImageOrganizerData.nonce
```

### File Structure

```
image-organizer/
  image-organizer.php
  README.md
  assets/
	img/banner-1544-500.png
	img/icon-512x512.png
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
