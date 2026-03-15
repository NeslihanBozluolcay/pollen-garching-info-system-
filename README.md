# Pollen Information System – Garching

A CPEE-driven web application that displays real-time pollen levels in Garching, detailed tree species information, and local plant observation photos. Users interact with the system through QR codes displayed on screen; scanning a QR code advances the CPEE workflow to the next page.

---

## What is CPEE?

CPEE (Cloud Process Execution Engine) is a workflow engine developed at TU Munich. It orchestrates multi-step processes by executing tasks in a defined order, similar to a flowchart that runs on a server.

In this project, CPEE acts as the **controller** that decides which web page to show next on the display screen. Each page is a CPEE task — when the user scans a QR code, the page sends a result back to CPEE via `send.php`, and CPEE moves to the next step in the process (e.g. navigate to `trees.html`).

Key concepts used in this project:

- **Callback URL** (`window.name`): When CPEE opens a page, it passes a unique callback URL as the browser window name. The page embeds this URL inside each QR code.
- **PUT callback**: When a QR code is scanned, `send.php` does an HTTP PUT to that callback URL with the scanned value as the body. This signals to CPEE that the task is done and what the user chose.
- **Process model**: CPEE holds the logic of what happens next — which page to open, what data to pass, and how to branch based on the scanned value (e.g. "home" vs. "photos" vs. a pollen name).

This means the HTML pages themselves contain no navigation logic — they just display data and report back what was scanned. All routing decisions live in the CPEE process.

---

## System Overview

The system is built around four active display pages, two PHP backend scripts, and a CPEE callback relay. The pages are served from a university web server and orchestrated by the Cloud Process Execution Engine (CPEE).

### CPEE Workflow

```
pollen.html  ──── user scans a pollen QR code ────▶  send.php
    │                                                     │
    │              (PUT scientific name to CPEE callback) │
    │◀────────────────────────────────────────────────────┘
    │
    ├──▶ trees.html        (shows species info for the scanned pollen type)
    │        │
    │        ├── "Go Home" QR      → send.php → CPEE → pollen.html
    │        └── "See More Photos" QR → send.php → CPEE → observation.html
    │
    └──▶ observation.html  (shows recent Munich photos of the scanned plant)
             │
             └── "Scan to continue" QR → send.php → CPEE → pollen.html
```

### Prepare and Finalize

Each CPEE node has two data-handling sections:

- **Prepare** — runs *before* the node executes. Used to set up endpoints or pass data into the node. For example, `endpoints.frames_display += attributes.framesid` registers which browser frame this node should display its page in.
- **Finalize** — runs *after* the node receives a result (e.g. a QR code scan). The result returned by `send.php` is captured into an Access Variable and then stored in a CPEE data field so it can be used by later nodes.

### CPEE Node Screenshots

**a1 – Init Frame:** Initialises the browser frame. Prepare registers the frame endpoint (`endpoints.frames_init += attributes.framesid`) so later nodes know where to display content.

![Init Frame node](Screenshots/Screenshot%202026-03-15%20at%2020.45.14.png)

**a2 – Clear:** Clears the current display. Finalize sets `data.timeout = false` to reset the timeout flag at the start of each loop.

![Clear node](Screenshots/Screenshot%202026-03-15%20at%2020.45.22.png)

**a4 – Show QR:** Opens `pollen.html` and waits for the user to scan a QR code. When scanned, `send.php` sends the scientific name (e.g. `Salix`) back to CPEE. Finalize captures it as `result` and stores it in `data.wereceived`, which is then passed to the next nodes.

![Show QR node](Screenshots/Screenshot%202026-03-15%20at%2020.45.34.png)

**a5 – Show individual page:** Opens `trees.html` passing `data.wereceived` as the `species` URL parameter, so the page knows which tree to look up. When the user scans a QR ("Go Home" or "See More Photos"), Finalize stores the result into `data.isHome`. The condition `data.isHome != "home" && !data.timeout` then determines whether to proceed to observations or loop back.

![Show individual page node](Screenshots/Screenshot%202026-03-15%20at%2020.45.43.png)

**a9 – Show observations:** Opens `observation.html` passing `data.wereceived` as the `taxon` URL parameter, so the page fetches photos of the same plant the user selected in a4.

![Show observations node](Screenshots/Screenshot%202026-03-15%20at%2020.45.53.png)

**a3 – Wait 60 seconds:** Timeout node running in parallel with the user task nodes. If 60 seconds pass with no QR scan, Finalize sets `data.timeout = true` and the workflow loops back to the beginning.

![Wait 60 seconds node](Screenshots/Screenshot%202026-03-15%20at%2020.46.00.png)

---

Each QR code encodes a URL like:
```
https://.../send.php?info=<value>&cb=<CPEE_callback_URL>
```
`send.php` receives the scan, does a PUT request to the CPEE callback URL with `info` as the body, and the CPEE engine then decides which page to show next based on its process model.

---

## File Descriptions

### Display Pages

| File | URL Parameter | Purpose |
|------|--------------|---------|
| `pollen.html` | — | Main dashboard. Fetches live pollen data via `pollen_proxy.php`, which web-scrapes Donnerwetter.de in real time. Displays a card per active pollen type (level > 0) with emoji, category badge, level pill, and a QR code. Adapts to 1 or 2 columns depending on item count. |
| `trees.html` | `?species=<name>` | Species detail page. Receives a pollen/tree name, queries the Trefle API via `tree.php`, and displays a photo, scientific name, family, genus, author, year, status, and bibliography. Always shows two QR codes: Go Home and See More Photos. |
| `observation.html` | `?taxon=<scientific_name>` | Photo gallery page. Takes a scientific genus name (e.g. `Salix`), fetches the 10 most recent research-grade observations from iNaturalist filtered to Munich (falls back to Germany if fewer than 4 Munich results), and displays them in a 5×2 photo grid. |

### PHP Backend Scripts

**`pollen_proxy.php`** — Web scraper for Donnerwetter.de. Fetches the Garching pollen forecast page using `file_get_contents` with GDPR consent cookies. Parses the raw HTML by splitting on `pollg*.gif` image markers and extracting pollen names from `<b>` tags and levels from `poll[0-4].gif` filenames. Translates German names to English using a built-in dictionary. Returns active pollen (level > 0) as JSON with name, category (Tree/Grass/Weed), and level (1–4). Required because Donnerwetter.de cannot be fetched directly from the browser due to CORS restrictions.

**`tree.php`** — Server-side proxy for the Trefle plant database API. Takes a `?species=<name>` query parameter, forwards it to `trefle.io/api/v1/species/search`, and returns the JSON response to the browser. Required because the Trefle API token must be kept server-side and cannot be exposed in client-side JavaScript.

**`send.php`** — CPEE callback relay. Takes `?info=<value>&cb=<url>` and does a PUT request to the CPEE callback URL with `info` as the plain-text body. This is how QR code scans communicate back to the CPEE engine.

---

## Possible Improvements

### Centralised Pollen Data Fetching in CPEE

Currently `pollen.html` fetches `pollen_proxy.php` directly from the browser, meaning Donnerwetter.de is scraped on every page load and `pollen.html` is tightly coupled to the proxy URL.

A cleaner architecture would be to add a dedicated **CPEE service call node** at the start of the workflow that fetches `pollen_proxy.php` once and stores the JSON result in a CPEE data field. CPEE would then pass this data to `pollen.html` as a URL parameter. The HTML page would simply read the data from the URL instead of fetching it itself.

Benefits:
- **Loosely coupled** — pages have no dependency on `pollen_proxy.php`; they only consume data provided by CPEE
- **Single fetch** — Donnerwetter.de is scraped once per workflow run, not once per page
- **Reusable** — the same pollen data can be passed to multiple CPEE nodes without re-fetching
- **Easier to swap** — the data source can be changed in one place (the CPEE service call) without touching any HTML

---

## External APIs Used

| API | Used By | Authentication |
|-----|---------|---------------|
| [Donnerwetter.de](https://www.donnerwetter.de/pollenflug/garching/DE16830.html) | `pollen_proxy.php` | None (web scraping with GDPR cookies) |
| [Trefle API](https://trefle.io) | `tree.php` | API token (stored in `tree.php`) |
| [iNaturalist API](https://api.inaturalist.org/v1) | `observation.html` | None (public read access) |
| [QRCode.js](https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js) | All display pages | None (CDN library) |

---

## Pollen Level Scale

| Level | Label | Color |
|-------|-------|-------|
| 1 | Low | Green |
| 2 | Moderate | Yellow |
| 3 | High | Orange |
| 4 | Very High | Red |

