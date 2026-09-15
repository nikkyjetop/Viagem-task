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

map.on('click', async function (event) {
    const lat = event.latlng.lat;
    const lng = event.latlng.lng;

    const response = await fetch(
        `/api/parcel.php?lat=${lat}&lng=${lng}`
    );

    const data = await response.json();

    console.log(data);

    // Remove the previously displayed parcel.
    if (parcelLayer) {
        map.removeLayer(parcelLayer);
    }

    // Display the newly selected parcel.
    parcelLayer = L.polygon(data.geometry).addTo(map);

    // Show basic parcel information in a popup.
    parcelLayer.bindPopup(`
        <strong>Parcel:</strong> ${data.label}<br>
        <strong>Area:</strong> ${data.area} m²<br>
        <strong>Reference:</strong> ${data.nationalReference}
    `).openPopup();
});