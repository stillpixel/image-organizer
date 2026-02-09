# Image Organizer Gallery

**Version:** 1.1.4  
**Requires at least:** WordPress 6.0+  
**Tested up to:** WordPress 6.8.x  
**Requires PHP:** 7.4+  
**License:** GPL-2.0+  
**Author:** Ron Rattie  
**Plugin URI:** https://stillpixelstudios.com/  

**Description:**  
A powerful yet lightweight, fully accessible image organizer and gallery plugin for WordPress. Includes metadata display, category & tag filtering, a responsive modal viewer, client-side text search, and AJAX “Load More” pagination — designed to meet **WCAG 2.1 AA** and **Section 508** requirements.

---

# 📌 Overview

Image Organizer Gallery enhances WordPress’ media library and turns it into a fully organized, filterable, searchable, paginated gallery system — now fully accessibility-optimized for government, education, and enterprise environments.

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

- **Client-side text search (title & description)**  
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

# 🔧 Shortcode Usage

Use the gallery shortcode:

```
[image_organizer]
```

### Parameters

| Parameter          | Type         | Default  | Description                                   |
|-------------------|--------------|----------|-----------------------------------------------|
| `columns`         | int          | 4        | Grid columns (1–6)                            |
| `limit`           | int          | 12       | Images per batch                              |
| `ids`             | CSV list     | (empty)  | Restrict to specific attachment IDs           |
| `categories`      | CSV list     | (empty)  | Filter by category slugs                      |
| `tags`            | CSV list     | (empty)  | Filter by tag slugs                           |
| `show_filter`     | true/false   | false    | Enable taxonomy filter bar                    |
| `filter_taxonomy` | category/tag | category | Which taxonomy the filter bar uses            |
| `aria_label`      | string       | (auto)   | Optional accessible label for the gallery     |

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

# 🔍 Text Search Filter (Title & Description)

Each gallery instance includes a built-in **text search input** above the grid:

- Filters images based on:
  - **Title**
  - **Caption**
  - **Description**
- Filtering is **client-side** (no AJAX).
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
