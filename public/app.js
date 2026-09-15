const map = L.map('map').setView([50.437, 15.351], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

map.on('click', async function (event) {
    const lat = event.latlng.lat;
    const lng = event.latlng.lng;

    const response = await fetch(
        `/api/parcel.php?lat=${lat}&lng=${lng}`
    );

    const data = await response.json();

    console.log(data);
});