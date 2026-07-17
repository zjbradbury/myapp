# TAB Racing Display Selector

A standalone PHP page that embeds selected TAB Information Display racing pages in configurable panels.

## Included

- `index.php`
- `tab-display.css`
- `tab-display.js`

## Installation

1. Upload the three files to the same folder on your PHP website.
2. Browse to `index.php`.
3. Select display types, jurisdictions, meeting presets, visible panel information, layout and refresh interval.
4. Press **Apply display**.

The user configuration is saved in the browser with `localStorage`.

## Meeting locations

TAB meeting/race page codes can vary. Edit the `$meetingLocations` array near the top of `index.php`.

Example:

```php
$meetingLocations = [
    [
        'id' => 'bendigo-r7',
        'label' => 'Bendigo Race 7',
        'jurisdiction' => 'VIC',
        'page' => 'MR7',
    ],
];
```

The page code is the value after `page=` in a TAB Information Display URL, for example:

```text
https://infodisplay.tab.com.au/racing-results-next-to-go?channelType=retail&jurisdiction=VIC&page=MR7
```

## Important limitation

This version embeds TAB's public display pages in iframes. It does not scrape, copy or republish TAB data.

If TAB adds an iframe restriction such as `X-Frame-Options` or CSP `frame-ancestors`, the embedded panels may stop loading. In that case, approved TAB Studio API access would be required for a fully custom data display.

Some route names on TAB's site may change. Edit `$displayTypes` in `index.php` if a route is renamed.
