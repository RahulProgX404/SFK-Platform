# Third-party notices

## Design reference: KUC — UI component library of Cybozu, Inc. (MIT)

The component layer of this system (`modules/Core/Resources/views/filament/ui.blade.php`, "SFK UI") and the colours and
sizes of the Core theme (`modules/Core/Resources/views/filament/theme.blade.php`) are our own implementation. Their
design language — palette, sizes and states of buttons, inputs, drop-downs, check boxes, radio buttons, switches,
tooltips, attachments, field groups and tables — is derived from KUC v1.26.0, the MIT-licensed UI component library of
Cybozu, Inc. No source code of the library is included or shipped. Its notice:

```
MIT License

Copyright (c) 2020 Cybozu, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

The library's own runtime dependencies (not used here) are Lit (BSD-3-Clause, Google LLC), core-js (MIT) and uuid
(MIT).

## 帳票出力 (2026-10-26, docs/documents.md)

| Component | License | Where |
|---|---|---|
| setasign/fpdf 1.9 (Olivier Plathey, Setasign) | MIT | Composer — PDF writer |
| setasign/fpdi 2.6 (Setasign GmbH) | MIT | Composer — imports the pages of uploaded PDF forms |
| pdf.js / pdfjs-dist 4.10.38 (Mozilla) | Apache-2.0 | `public/vendor/pdfjs/` (minified build, CMaps, standard fonts; `LICENSE` alongside) — draws the PDF form in the designer |
| qpdf (Jay Berkenbilt) | Apache-2.0 | Debian package in `docker/php/Dockerfile`, run as a separate program to rewrite PDFs the reader cannot open |

Design reference only: the PrintCreator customization sample of its vendor (toyokumo on GitHub; MIT, © 2015 Yuichi Ochiai)
was read for its output workflow (帳票 drop-down + 出力 on the record and list screens, up to 500 records, checked rows).
No code of it is included.

Japanese text in the generated PDFs uses the standard Japanese CID fonts by name (not embedded): no font file is shipped.

## Dependency license review (2026-10-22)

PHP (Composer, production — `composer licenses --no-dev`, 118 packages): all permissive.

| License | Packages |
|---|---|
| MIT | 105 (Laravel, Filament — its bundled JS such as FilePond and Tippy is MIT — Livewire, Symfony, …) |
| BSD-3-Clause | 6 (league/commonmark, league/config, nikic/php-parser, scrivo/highlight.php, tijsverkoyen/css-to-inline-styles, vlucas/phpdotenv) |
| BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only | 3 (nette/php-generator, nette/schema, nette/utils) — used under BSD-3-Clause |
| Apache-2.0 | 3 (open-telemetry/api, open-telemetry/context, phpoption/phpoption) |
| MIT OR Apache-2.0 | 1 (chillerlan/php-qrcode) — used under MIT |

JavaScript (npm): build tools only (`devDependencies`: Tailwind CSS, Vite, laravel-vite-plugin, concurrently;
`optionalDependencies`: @laravel/multiplex) — all MIT, nothing from npm is shipped to the browser.

To repeat the review: `docker compose exec app composer licenses --no-dev` (and `npm ls --all` once `node_modules`
is installed). A new dependency with a copyleft-only or unknown license needs a decision before it is added.
