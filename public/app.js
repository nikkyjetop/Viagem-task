// Covered scope: katastrální území Jičín (povinné minimum) plus three
// neighbouring territories (Valdice, Holín, Železnice). Keeping the app
// focused on this area is what lets it stay fluid — see README for why.
const SCOPE_BOUNDS = L.latLngBounds(
    [50.38, 15.25],
    [50.52, 15.44]
);

const map = L.map('map', {
    maxBounds: SCOPE_BOUNDS.pad(0.05),
    maxBoundsViscosity: 1.0,
    minZoom: 12
}).fitBounds(SCOPE_BOUNDS);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// ČÚZK WMS overlay so parcel boundaries/numbers are visible across the
// whole map, not just for the parcel that was last clicked.
L.tileLayer.wms('https://services.cuzk.cz/wms/wms.asp', {
    layers: 'KN',
    format: 'image/png',
    transparent: true,
    version: '1.1.1',
    attribution: '&copy; ČÚZK'
}).addTo(map);

// Keep track of the currently displayed parcel.
let parcelLayer = null;

// Ignore clicks while a request is already in flight, so a quick series of
// clicks can't pile up out-of-order responses.
let isLoading = false;

map.on('click', async function (event) {
    if (isLoading) {
        return;
    }

    isLoading = true;

    const lat = event.latlng.lat;
    const lng = event.latlng.lng;

    if (parcelLayer) {
        map.removeLayer(parcelLayer);
        parcelLayer = null;
    }

    L.popup()
        .setLatLng(event.latlng)
        .setContent('Loading…')
        .openOn(map);

    try {
        const response = await fetch(
            `/api/parcel.php?lat=${lat}&lng=${lng}`
        );

        const data = await response.json();

        if (!response.ok) {
            L.popup()
                .setLatLng(event.latlng)
                .setContent(
                    data.error === 'Parcel is outside the covered area'
                        ? `Parcel is outside the covered area (${data.cadastralTerritory}).`
                        : 'No parcel found here.'
                )
                .openOn(map);

            return;
        }

        // Display the newly selected parcel.
        parcelLayer = L.polygon(data.geometry).addTo(map);

        // Show basic parcel information in a popup.
        parcelLayer.bindPopup(`
            <strong>Parcel:</strong> ${data.label}<br>
            <strong>Area:</strong> ${data.area} m²<br>
            <strong>Reference:</strong> ${data.nationalReference}<br>
            <strong>Cadastral territory:</strong> ${data.cadastralTerritory}
        `).openPopup();
    } catch (error) {
        L.popup()
            .setLatLng(event.latlng)
            .setContent('Could not reach the ČÚZK service. Please try again.')
            .openOn(map);
    } finally {
        isLoading = false;
    }
});