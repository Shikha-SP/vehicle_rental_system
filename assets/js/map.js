document.addEventListener("DOMContentLoaded", function () {
    // Initialize the map and set location view
    var map = L.map('showroom-map').setView([27.7124, 85.3309], 16);


    var geoapifyUrl = 'https://maps.geoapify.com/v1/tile/carto/{z}/{x}/{y}.png?&apiKey=ee4b8e9049f44c1387225e75fa5397c7';

    L.tileLayer(geoapifyUrl, {
        attribution: 'Powered by <a href="https://www.geoapify.com/">Geoapify</a> | &copy; OpenStreetMap contributors',
        maxZoom: 20
    }).addTo(map);

    // Add a marker
    var marker = L.marker([27.7124, 85.3309]).addTo(map);
    marker.bindPopup("<b>TD Rentals</b><br>Naxal Bhagawati Marga, Kathmandu, Nepal").openPopup();
});
