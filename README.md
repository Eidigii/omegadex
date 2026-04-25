# OMEGADEX (Web Edition)

Web-friendly Omegadex for **ARK: Survival Ascended - Omega Mod**.

This project mirrors the in-game Omegadex in a browser UI while keeping source content easy to edit and OCR-friendly. Most content lives in plain `.txt` files under `Data/`, and the PHP renderer converts that text into styled HTML at runtime.

## What This Project Is

- A website version of the Omega mod documentation (guide, progression, variants, dinos, FAQs, links, etc.).
- Built to preserve gameplay/reference parity with the in-game Omegadex while improving readability/navigation on desktop and mobile.
- Designed so contributors can update data quickly without hand-authoring complex HTML.

## Repository Layout

- `Data/`  
  Primary content source. Organized by numbered folders (`#1 Welcome`, `#3 Progression Guide`, `#4 Variants`, etc.).
- `lib/renderer/`  
  Runtime rendering rules (`OmegadexRenderer`, rulesets, plain markup expansion).
- `lib/renderer/config/`  
  Renderer section/ruleset config JSON files.
- `assets/`  
  Styles and frontend scripts.

## Content Source Format (OCR-Friendly)

Most editable content is stored as plain `.txt` in `Data/` to make copy/paste from in-game text and OCR output easy.

Examples:

- `Data/#1 Welcome/index.txt`
- `Data/#3 Progression Guide/#1 Getting Started.txt`
- `Data/#4 Variants/Cosmic/Anti-Matter⁷.txt`
- `Data/#16 FAQs/index.txt`

## Custom Plain Markup Reference

The renderer expands plain syntax to HTML when content has no real angle-bracket HTML.

### Headings

Use hashes:

```text
## Section Title
### Subsection Title
#### Smaller Heading
```

### Inline formatting

```text
**bold**
*italic*
__underline__
[Link Label](https://example.com)
```

### Line breaks

```text
Line one <<br>> line two
```

### Small italic note line

```text
!small! updated 11/19/2025. Balance pass and data refresh.
```

### INI/config block

```text
Best mod for this is "Inventory Backup Saver" with GameUserSettings.ini addition:
@ini:lightblue
[InventorySaver]
ReturnItems=True
@end
```

`@ini:<color>` accepts CSS color names/values (e.g. `lightblue`, `#80d0ff`).

### Damage reduction tooltip pattern

```text
Damage Reduction: 95% !tip:Only bypassed by Multi-Tool harvest damage type
```

### Sidecar include (for heavy HTML blocks)

If a page needs custom HTML/JS table/app content, keep the `.txt` minimal and include a sidecar fragment:

```text
!INCLUDE:_egg_table.html!
```

Rules:

- Include must be the only meaningful content in the file.
- Sidecar file must sit in the same folder as the `.txt`.
- Prefer sidecars for large custom widgets/tables and keep normal prose in plain text.

## Section Separators and Rule Behavior

Some sections use `----` delimiters in source to split generated sections according to ruleset config.

```text
Intro/header text
----
Body section text
```

Different folders use different parsers (variants, dinos, list pages, FAQs, etc.), so keep edits consistent with nearby files in the same directory.

## Contributor Guide

### 1) Pick the right file

- Find the relevant page under `Data/...`.
- Keep naming/folder conventions unchanged.

### 2) Prefer plain `.txt` updates

- Use the custom plain markup above.
- Avoid raw HTML unless truly needed.
- For large custom HTML blocks, use `!INCLUDE:_your_fragment.html!`.

### 3) Keep formatting consistent

- Match tone/structure of neighboring entries.
- Preserve Q/A structure in FAQs.
- Preserve `----` separators where present.

### 4) Validate visually

- Open the page in the local site.
- Check spacing, headings, list alignment, and links.
- Verify no accidental markdown/raw HTML regressions.

### 5) Commit focused changes

- Keep content-only edits separate from renderer/CSS edits where possible.
- Describe *why* in commit messages (e.g., “update cooldown values from latest in-game pass”).

## How To Contribute

- Submit fixes to outdated values, typos, missing entries, or formatting issues.
- If you update data from in-game/OCR, include a short note in your PR about source/version/date.
- For large rewrites, prefer smaller PRs by section (`Variants`, `FAQs`, `Progression`, etc.) for easier review.

