const map = L.map('map').setView([50.437, 15.351], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

map.on('click', function (event) {
    console.log('Latitude:', event.latlng.lat);
    console.log('Longitude:', event.latlng.lng);
});