# Crimean Tatar Transliterator (`mod_translit`)

A [Joomla](https://www.joomla.org/) site module that transliterates **Crimean Tatar**
text between the **Cyrillic** and **Latin** scripts. It works both for short text
typed directly into the page and for uploaded documents (`.docx`, `.txt`), preserving
the original formatting of Word files.

- Convert direction `crh-cyrl` ⇄ `crh-latn` (Cyrillic ⇄ Latin).
- Inline mode: live transliteration of pasted/typed text **in the browser** (a JS port
  of the PHP engine), with the server `com_ajax` endpoint kept only as a fallback.
- File mode: upload one or more `.docx` / `.txt` files, transliterate them in the
  background, and download the result as a single `.zip` archive.
- On-screen **virtual keyboard** for Crimean Tatar Latin and Cyrillic special letters.
- **Full-screen** editing view and a **share link** that encodes the current text and
  direction in the URL query string.

> Powered by a rule-based engine (character maps + regular expressions) derived from
> the Crimean Tatar orthography rules, adapted from the MediaWiki `crh` language converter.

---

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Architecture](#architecture)
- [AJAX API](#ajax-api)
- [Project layout](#project-layout)
- [Localization](#localization)
- [Development](#development)
- [Known issues & recommended improvements](#known-issues--recommended-improvements)
- [License](#license)

---

## Requirements

| Dependency | Notes |
|------------|-------|
| Joomla     | Site module (`client="site"`). Uses both the modern `Joomla\CMS\Factory` API and legacy `JFactory`/`JText`/`JModuleHelper` aliases. |
| PHP        | 7.x+ with the `zip` (ZipArchive) and `mbstring`/PCRE-UTF8 extensions enabled. |
| Composer   | The DOCX helper relies on [`phpoffice/phpword`](https://github.com/PHPOffice/PHPWord), loaded from `translit/vendor/autoload.php`. |
| Web server write access | The server must be able to create and clean up files under `<JPATH_BASE>/media/docs/`. |

### Composer dependencies

`helper.php` expects a Composer autoloader at `mod_translit/translit/vendor/autoload.php`.
Install it before packaging:

```bash
cd mod_translit/translit
composer require phpoffice/phpword
```

This `vendor/` directory is intentionally **not** committed; generate it at build time.

## Installation

1. Build an installable package by zipping the contents of the `mod_translit/`
   directory (the folder that contains `mod_translit.xml`) **after** generating the
   Composer `vendor/` directory described above.
2. In the Joomla admin, go to **System → Install → Extensions** and upload the zip.
3. Publish the module to a position via **Content → Site Modules**.
4. Ensure `<JPATH_BASE>/media/docs/` is writable by the web server (file mode).

## Usage

The module renders two tabs (see `tmpl/default.php`):

- **Text** – type or paste text; the source script is auto-detected from the first
  letter and the result updates live as you type. Extra controls: virtual keyboard
  (special characters), full-screen view, clear, copy result, and a share link that
  stores the text and direction in the URL (`?text=…&lang=…&lang2=…`) so the page can
  re-create the transliteration when opened later.
- **Files** – select `.docx`/`.txt` files, choose the target script, and start the
  job. Large documents are split into parts and processed sequentially with a
  progress bar; the finished files are returned as a single `.zip`.

### Deploying changes to a live Joomla site

This sandbox cannot reach your server, so deployment is manual. For the files in this
module, the quickest path (no reinstall) is:

1. Copy the changed files over FTP/SFTP into `<site_root>/modules/mod_translit/`
   (e.g. `tmpl/default.php` and the `language/*/*.ini` files).
2. In Joomla admin run **System → Clear Cache** — language strings are cached, so the
   old text may persist until the cache is cleared.

For a clean install/upgrade instead, generate `translit/vendor/` with Composer, zip the
contents of `mod_translit/`, and upload via **System → Install → Extensions**.

## Architecture

```
Browser (tmpl/default.php, jQuery)
        │
        ├── inline text → client-side engine (assets/mod_translit.js
        │                 + assets/translit-data.js)   ◀── default, no network
        │
        └── files / fallback → com_ajax POST requests
                              ▼
                ModTranslitHelper (helper.php)
                              │  orchestrates DOCX / TXT jobs, file I/O, zipping
                              ▼
                TranslitProcessor (translit/TranslitProcessor.php)
                              │  pure transliteration engine (source of truth)
                              ├── constants.inc            – character classes (alphabets, vowels, consonants)
                              ├── exeptions.inc            – word-level exception maps (irregular spellings)
                              └── regular_expressions.inc  – ordered regex rules + fallback char maps
```

### Client-side engine (`assets/mod_translit.js`)

Inline transliteration runs entirely in the browser. `assets/mod_translit.js` is a
faithful JS port of `TranslitProcessor` (same exceptions → tokenize → ordered regex →
`strtr` fallback pipeline). It consumes `assets/translit-data.js`, an **auto-generated**
file that exports the exact rules/exceptions/character-classes from the PHP `.inc` files
so the JS output is byte-for-byte identical to PHP. This removes the per-keystroke
server round-trips that previously dominated input latency. If the engine fails to load,
`tmpl/default.php` falls back to the `transliterate` `com_ajax` endpoint. Document
(`.docx`/`.txt`) processing still runs server-side via PHPWord.

> **Do not edit `assets/translit-data.js` by hand** — it is generated. After changing any
> `.inc` file, regenerate it and re-run the parity test (see [Development](#development)).

### Transliteration engine (`TranslitProcessor`)

`TranslitProcessor::translate($text, $toVariant)` is the single public entry point:

1. **Exceptions first** – whole irregular words are replaced from the exception map
   (`exeptions.inc`) before any rule runs.
2. **Tokenize** – text is split into *words* (runs of alphabet characters for the
   source script) and *delimiters* (everything else) using `preg_split` with
   `PREG_SPLIT_DELIM_CAPTURE`.
3. **Apply rules** – words are joined with `\n`, then each ordered regular
   expression from `regular_expressions.inc` is applied; a final `all_other_letters`
   fallback maps any remaining 1:1 characters via `strtr`.
4. **Reassemble** – words and delimiters are merged back in their original order.

`$toVariant` accepts `crh-cyrl` (→ Cyrillic) or `crh-latn` (→ Latin); any other
value returns the input unchanged.

### Document pipeline (`ModTranslitHelper`)

A `.docx` is an OPC (zip) container; the readable text lives in `word/document.xml`.
The helper processes it in chunks so very large documents don't exhaust memory or
the request time limit:

1. `explodeDOCX()` – splits `word/document.xml` on `</w:p>` paragraph boundaries
   into ~100-paragraph parts written to `src/parts/partN.txt`.
2. `translitDOCX()` – transliterates one part per request (driven by the client),
   updating the progress bar between calls.
3. `translitDOCXFinish()` – rebuilds the `.docx` by copying the original archive and
   replacing `word/document.xml` (and other XML members) with transliterated content.
4. `makeZip()` – packs all output files into `<hash>.zip` for download.

`translitText()` only transliterates the text nodes (`>…<`) of the XML so tags and
attributes are never touched, and uses `htmlspecialchars`/`htmlspecialchars_decode`
to keep entities intact.

### Temporary storage & cleanup

Each job gets a unique directory `media/docs/<hash>/` (`hash` is a Unix timestamp),
with `src/`, `src/parts/`, and `trg/` subfolders. `garbageCollect()` runs on every
upload and removes job directories older than one hour.

## AJAX API

All endpoints are exposed through Joomla's `com_ajax` dispatcher and map to public
static methods on `ModTranslitHelper` (`method=<name>` → `<name>Ajax`).

| Method (`method=`) | Helper | Params | Returns (`data`) |
|--------------------|--------|--------|------------------|
| `transliterate` | `transliterateAjax` | `text`, `toVariant` | `{ text }` – transliterated string |
| `uploadFiles` | `uploadFilesAjax` | `file[]` (multipart) | `hash` of the new job, or `false` |
| `transliterateUploaded` | `transliterateUploadedAjax` | `hash`, `toVariant`, `part` | progress object, or `{ is_finished, result }` |

Example (inline text):

```
POST /index.php?option=com_ajax&module=translit&method=transliterate&format=json
Body: text=Селям&toVariant=crh-latn
→ { "data": { "text": "Selâm" } }
```

The `transliterateUploaded` call is invoked repeatedly by the client (incrementing
`part`) until the response contains `is_finished: true` and a `result` download URL.

## Project layout

```
mod_translit/
├── mod_translit.xml          # Install manifest (files, languages, metadata)
├── mod_translit.php          # Module entry point; loads the layout
├── helper.php                # ModTranslitHelper: AJAX + document pipeline
├── index.html                # Empty index (directory-listing guard)
├── assets/
│   ├── mod_translit.css
│   ├── mod_translit.js       # Client-side transliteration engine (JS port of the PHP one)
│   └── translit-data.js      # AUTO-GENERATED rules/exceptions export (do not edit by hand)
├── language/
│   ├── en-GB/ uk-UA/ tr-TR/   # *.ini (front-end) and *.sys.ini (admin); tr-TR = Crimean Tatar Latin
├── tmpl/
│   └── default.php           # Markup + inline JS/CSS for the widget
├── test/
│   └── parity.js             # 0-diff gate: asserts JS engine == PHP engine
└── translit/
    ├── TranslitProcessor.php # Transliteration engine (source of truth)
    ├── export_rules.php      # Build step: exports the .inc data to assets/translit-data.js
    ├── constants.inc         # Alphabet / phonetic character classes
    ├── exeptions.inc         # Word-level exception maps
    └── regular_expressions.inc # Ordered regex rules + fallback char maps
```

## Localization

UI strings live in `language/<tag>/<tag>.mod_translit.ini`; the admin name and
description live in the matching `.sys.ini`. Bundled languages: **en-GB**, **uk-UA**,
and **tr-TR** (the `tr-TR` tag carries **Crimean Tatar Latin** / crh-Latn strings,
not Turkish). To add a language:

1. Create `language/<tag>/<tag>.mod_translit.ini` and `.sys.ini` with the same keys
   as `en-GB` (see the full list of keys used in `tmpl/default.php`).
2. Register both files under `<languages>` in `mod_translit.xml`.

## Development

- **Lint PHP**: `php -l mod_translit/helper.php` (repeat for changed `.php` files).
- **Regenerate the client-side data** after editing any `translit/*.inc` file:
  ```bash
  cd mod_translit/translit
  php export_rules.php > ../assets/translit-data.js
  ```
  This keeps the browser engine in sync with the PHP source of truth.
- **Parity test (quality gate)** – proves the JS engine and PHP engine produce identical
  output. Run it before every commit/release that touches the engine, rules, or
  exceptions; it must report **0 diffs**:
  ```bash
  cd mod_translit
  node test/parity.js          # requires php + node on PATH
  ```
- The inline widget JS/CSS still live in `tmpl/default.php`; only the transliteration
  engine has been extracted to `assets/`.
- Keep tags/attributes untouched when changing the DOCX pipeline — only text nodes
  should ever be transliterated.

## Fixed in this branch

The following issues were found by comparing the live production version with the
repository and have been corrected here:

- **Reflected XSS** – the template echoed `$_GET['text']` straight into a `<textarea>`.
  Input is now read through Joomla's input filter and escaped with `htmlspecialchars`,
  and the `lang`/`lang2` direction parameters are whitelisted to the known variants.
- **Broken option pre-selection** – the direction `<select>`s emitted the invalid
  attribute `select` (always, on every option); they now emit a single correct
  `selected` based on the sanitized URL parameters.
- **Output written with `.html()`** onto a `<textarea>` → switched to `.val()`.
- **Duplicate jQuery `<script>`** include removed (it was loaded twice).
- **Full-screen close handler** referenced out-of-scope variables (`ReferenceError`);
  it now writes back to the real DOM nodes.
- **Hard-coded / Russian UI strings** (`Пробел`, alert texts, swap-button title) moved
  to language keys so every label follows the active site language.
- **Complete localization** – all `MOD_*` keys used by the template are now defined in
  `en-GB`, `uk-UA`, and `tr-TR` (Crimean Tatar Latin), so a fresh install renders real
  labels instead of raw keys.
- **Client-side inline transliteration** – inline text is now transliterated in the
  browser by a JS port of the PHP engine (`assets/mod_translit.js` + generated
  `assets/translit-data.js`), eliminating ~one server request per keystroke. A parity
  test (`test/parity.js`) guarantees the JS output stays byte-for-byte identical to PHP;
  the server endpoint remains as a fallback. The previously empty `assets/mod_translit.js`
  is now the engine.

## Known issues & recommended improvements

Still open, good candidates for follow-up:

- **jQuery from a CDN** – `tmpl/default.php` loads jQuery from `ajax.googleapis.com`.
  Prefer Joomla's bundled jQuery (`HTMLHelper::_('jquery.framework')`).
- **Default `toVariant`** – the AJAX helpers default to `crh-cyr`, which is not a
  recognized variant (`crh-cyrl`/`crh-latn`) and falls through to "no change".
- **`var_dump` on errors** – `helper.php` dumps exceptions to the response; use
  Joomla logging (`Joomla\CMS\Log\Log`) and return a structured error instead.
- **Directory permissions** – job directories are created with `0777`; tighten to
  the minimum the web server needs.
- **Filename typo** – `exeptions.inc` (should be `exceptions.inc`); renaming requires
  updating the `include` in `TranslitProcessor.php`.
- **`action="upload.php"`** on the file form has no matching file; uploads actually go
  through the `com_ajax` `uploadFiles` endpoint, so the attribute is dead and could be
  removed.

## License

No license file is currently present in this repository. Add one (for example
`LICENSE`) to clarify how the module may be used and distributed. The bundled
PHPWord dependency is licensed separately under the LGPL-3.0.
