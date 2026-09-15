const map = L.map('map').setView([50.437, 15.351], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
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