# Crimean Tatar Transliterator (`mod_translit`)

A [Joomla](https://www.joomla.org/) site module that transliterates **Crimean Tatar**
text between the **Cyrillic** and **Latin** scripts. It works both for short text
typed directly into the page and for uploaded documents (`.docx`, `.txt`), preserving
the original formatting of Word files.

- Convert direction `crh-cyrl` ⇄ `crh-latn` (Cyrillic ⇄ Latin).
- Inline mode: live transliteration of pasted/typed text via AJAX.
- File mode: upload one or more `.docx` / `.txt` files, transliterate them in the
  background, and download the result as a single `.zip` archive.

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
  letter and the result updates live as you type.
- **Files** – select `.docx`/`.txt` files, choose the target script, and start the
  job. Large documents are split into parts and processed sequentially with a
  progress bar; the finished files are returned as a single `.zip`.

## Architecture

```
Browser (tmpl/default.php, jQuery)
        │  com_ajax POST requests
        ▼
ModTranslitHelper (helper.php)
        │  orchestrates text / DOCX / TXT jobs, file I/O, zipping
        ▼
TranslitProcessor (translit/TranslitProcessor.php)
        │  pure transliteration engine
        ├── constants.inc            – character classes (alphabets, vowels, consonants)
        ├── exeptions.inc            – word-level exception maps (irregular spellings)
        └── regular_expressions.inc  – ordered regex rules + fallback char maps
```

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
│   └── mod_translit.js
├── language/
│   ├── en-GB/ uk-UA/ tr-TR/   # *.ini (front-end) and *.sys.ini (admin); tr-TR = Crimean Tatar Latin
├── tmpl/
│   └── default.php           # Markup + inline JS/CSS for the widget
└── translit/
    ├── TranslitProcessor.php # Transliteration engine
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
- **No build step** for the front-end; CSS/JS are inlined in `tmpl/default.php`
  (`assets/mod_translit.js` is currently empty).
- Keep tags/attributes untouched when changing the DOCX pipeline — only text nodes
  should ever be transliterated.

## Known issues & recommended improvements

These were found while documenting the module and are good candidates for follow-up:

- **Empty `assets/mod_translit.js`** – the inline `<script>` in `tmpl/default.php`
  could be moved here and the file referenced via `Joomla\CMS\HTML\HTMLHelper` for
  caching and CSP friendliness.
- **jQuery from a CDN** – `tmpl/default.php` loads jQuery from `ajax.googleapis.com`.
  Prefer Joomla's bundled jQuery (`HTMLHelper::_('jquery.framework')`) to avoid a
  third-party dependency and duplicate loads.
- **Textarea output** – the inline result is written with `.html()` onto a
  `<textarea>`; `.val()` (or `.text()`) is the correct API for form fields.
- **Default `toVariant`** – the AJAX helpers default to `crh-cyr`, which is not a
  recognized variant (`crh-cyrl`/`crh-latn`) and falls through to "no change".
- **`var_dump` on errors** – `helper.php` dumps exceptions to the response; use
  Joomla logging (`Joomla\CMS\Log\Log`) and return a structured error instead.
- **Directory permissions** – job directories are created with `0777`; tighten to
  the minimum the web server needs.
- **Filename typo** – `exeptions.inc` (should be `exceptions.inc`); renaming requires
  updating the `include` in `TranslitProcessor.php`.

## License

No license file is currently present in this repository. Add one (for example
`LICENSE`) to clarify how the module may be used and distributed. The bundled
PHPWord dependency is licensed separately under the LGPL-3.0.
