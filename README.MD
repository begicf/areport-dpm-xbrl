# AreportDpmXBRL

`AreportDpmXBRL` is a PHP library for parsing DPM/XBRL taxonomies, rendering taxonomy tables, and preparing export payloads for the AReport application.

The library is used by the AReport web application, but it is kept as a separate package so the parsing and rendering logic can evolve independently.

## Scope

The package currently covers:

- taxonomy parsing
- module and table discovery
- HTML table rendering
- spreadsheet, HTML, and PDF rendering helpers
- export preparation for xBRL XML and xBRL CSV

## Recent Changes

The current development version includes the following important fixes and improvements:

- restored legacy hierarchy and domain rendering for DPM `1.0` taxonomies
- improved taxonomy path resolution across multiple DPM taxonomy revisions
- improved module and table handling for taxonomies that do not expose the exact same `mod` structure
- improved label resolution so business labels are preferred over generic `Rows` / `Columns` placeholders
- added compatibility fixes for DPM `2.0` table definitions and open-table patterns
- improved definition matching so valid cells are no longer incorrectly rendered as disabled
- restored dropdown domain rendering for hierarchy-driven cells
- improved handling of DPM metadata captured from table JSON sidecars
- improved xBRL XML generation, including scenario members and filing indicator normalization
- added an xBRL-CSV package writer aligned with local EBA sample package structure
- fixed PhpSpreadsheet compatibility for explicit string cell writes

## Installation

Install the package with Composer:

```bash
composer require begicf/areport-dpm-xbrl
```

## Quick Start

```php
<?php

use AReportDpmXBRL\Library\Data;
use AReportDpmXBRL\Render;

$taxonomy = Data::getTax($tablePath);
$render = new Render($taxonomy);
$htmlForm = $render->renderHtmlForm();
```

The HTML renderer returns an array with keys such as:

- `table`
- `sheets`
- `tableName`
- `aspectNode`
- `tableID`
- `groups`

## Development Notes

The package is currently being maintained with two goals in parallel:

1. preserve the existing DPM `1.0` behavior
2. improve compatibility with DPM `2.0` taxonomy structures

Because of that, changes should be reviewed carefully against both old and new taxonomy packages.

## Related Repository

- AReport application: <https://github.com/begicf/areport>
- Package repository: <https://github.com/begicf/areport-dpm-xbrl>
