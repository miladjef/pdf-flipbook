=== ELIMO PDF Flipbook ===
Contributors: miladjafari
Tags: pdf, flipbook, pdf viewer, catalog, brochure
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A responsive online PDF viewer and flipbook for WordPress.

== Features ==
* Upload/select PDF from WordPress Media Library
* Shareable standalone catalog URL
* Shortcode: [elimo_pdf id="123"]
* Direct URL shortcode: [elimo_pdf url="https://example.com/file.pdf"]
* Flipbook or single-page mode
* Responsive desktop and mobile viewer
* Swipe navigation on mobile
* Keyboard navigation
* Thumbnails sidebar
* Zoom
* Fullscreen
* Optional download button
* Optional share button
* Lightbox mode: [elimo_pdf id="123" display="lightbox" label="View catalog"]

== Installation ==
1. Upload the plugin ZIP under Plugins > Add New > Upload Plugin.
2. Activate ELIMO PDF Flipbook.
3. Go to PDF Flipbooks > Add New.
4. Select a PDF from the Media Library.
5. Publish and copy the shortcode.

PDF rendering uses PDF.js. By default the library loads from jsDelivr. The URLs can be changed under PDF Flipbooks > Settings.


== 1.0.1 ==
* Bundled PDF.js 3.11.174 locally to avoid CDN/CSP/optimization failures.
* Added same-origin PDF streaming for Media Library attachments with byte-range support.
* Added automatic www/non-www URL normalization and direct-file fallback.
* Added attachment ID persistence when choosing PDFs from Media Library.
* Improved viewer diagnostics when loading fails.

= 1.0.4 =
* Restricts the full-file Firefox workaround to Firefox desktop only.
* Adds a substantially more natural 3D page-turn animation with front/back page faces, fold lighting, edge shadow and smoother timing.
* Keeps the lightweight mobile navigation behavior for performance.
