# Viagem Task

Interview task for Viagem — a map of cadastral parcels in the Jičín district
(okres Jičín). Click a parcel to see its label, area, and cadastral reference.

## Requirements

- PHP 8.1 or newer (developed and tested with PHP 8.5.10)
- `curl` and `simplexml` PHP extensions (bundled with a standard PHP install)
- Git
- No database, no Composer dependencies — the backend is plain PHP and talks
  to ČÚZK live over HTTPS on every request.

## Run

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000`.

## How it works

- **Map overview** — the frontend (Leaflet) loads an OpenStreetMap base layer
  plus a ČÚZK WMS overlay (`services.cuzk.cz/wms/wms.asp`, layer `KN`), so
  parcel boundaries and numbers are visible across the whole covered area
  without waiting for a click.
- **Parcel details** — clicking the map calls [`public/api/parcel.php`](public/api/parcel.php),
  which queries the ČÚZK WFS `GetFeatureByPoint` stored query for the
  `CadastralParcel` feature at that point, parses the returned GML, and
  returns the parcel's label, area, national reference, cadastral territory,
  and boundary geometry as JSON. The frontend draws that geometry and shows
  the details in a popup.

## Covered area

The povinné minimum is katastrální území **Jičín**. This app also covers
three adjacent territories — **Valdice**, **Holín**, and **Železnice** — so
the whole covered area forms one contiguous region on screen rather than
disconnected islands.

The scope is enforced two ways:
1. The map's `maxBounds` keep panning within a bounding box around those
   four territories.
2. `parcel.php` reads the parcel's cadastral territory from the WFS
   response (the `zoning` reference's title) and checks it against a
   whitelist, so a click near the edge of the map bounds can't return data
   for a parcel just outside the four supported territories.

## Zápisník (decisions, surprises, what I'd do differently)

- **District-wide visualization vs. per-parcel fetching.** Rather than
  fetching every parcel's geometry via WFS and rendering it client-side
  (which would mean paging through a bounding-box query and parsing a lot of
  GML up front), I overlaid ČÚZK's own WMS cadastral tiles. It's the same
  data ČÚZK's own map viewers use, renders instantly at any zoom, and keeps
  the app fluid over the whole covered area. WFS is only used for the single
  feature the user actually clicks, where I need real attribute data (area,
  reference) that the raster WMS tiles don't carry.
- **Live queries, no local cache/database.** Each click is a live WFS
  request. A full local copy of the district's parcels would need a spatial
  index (and a database) to stay fast, which felt disproportionate to the
  required scope — a single-point WFS lookup typically returns in well under
  a second.
- **Choosing which 4 territories to cover.** I picked Jičín's immediate
  neighbours (Valdice, Holín, Železnice) specifically so the covered area is
  one contiguous block rather than four scattered dots on the map — makes
  the `maxBounds` restriction feel natural instead of arbitrary.
- **Surprise: ČÚZK signals "no parcel here" without an HTTP error.** A click
  that misses the cadastre (water, gap, outside CZ) still returns HTTP 200
  with a bare `<FeatureCollection/>` that has no `cp` namespace, instead of
  the usual `<cp:CadastralParcel>` root. `parcel.php` has to detect this
  explicitly (`isset($namespaces['cp'])`) rather than relying on the HTTP
  status or a parse failure.
- **Surprise: the WMS parcel layer is scale-dependent.** Boundaries/numbers
  only render once zoomed in reasonably close — that's a deliberate ČÚZK
  setting (`ScaleHint`), not a bug, so at district-wide zoom you'll see the
  base map without parcel lines until you zoom in.
- **What I'd do with more time:** extend coverage towards the full district
  by fetching the district boundary from ČÚZK's administrative-unit WFS
  instead of a hard-coded bounding box + whitelist; add a proper loading
  cursor/spinner and click debouncing; write automated tests around
  `parcel.php`'s XML parsing (empty response, missing geometry, multi-part
  parcels); show the katastrální území boundary itself on the map; and add
  basic response caching (parcels don't change often) to cut down repeat
  ČÚZK round-trips when a user re-clicks the same area.
