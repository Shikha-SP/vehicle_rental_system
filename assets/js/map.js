document.addEventListener("DOMContentLoaded", function () {
    // Rental Shop Coordinates
    var shopLat = 27.7124;
    var shopLng = 85.3309;

    // Initialize the map and set location view
    var map = L.map('showroom-map').setView([shopLat, shopLng], 16);

    var geoapifyUrl = 'https://maps.geoapify.com/v1/tile/carto/{z}/{x}/{y}.png?&apiKey=ee4b8e9049f44c1387225e75fa5397c7';

    L.tileLayer(geoapifyUrl, {
        attribution: 'Powered by <a href="https://www.geoapify.com/">Geoapify</a> | &copy; OpenStreetMap contributors',
        maxZoom: 20
    }).addTo(map);

    // Add a marker
    var marker = L.marker([shopLat, shopLng]).addTo(map);
    
    // Default popup content
    var popupContent = `
        <div style="text-align:center; padding: 5px;">
            <b style="font-size: 1.1em; color: #333;">TD Rentals</b><br>
            <span style="color: #666;">Naxal Bhagawati Marga<br>Kathmandu, Nepal</span>
        </div>
    `;
    marker.bindPopup(popupContent).openPopup();

    // Create a custom Leaflet Control for the "Open in Maps" button
    var OpenMapsControl = L.Control.extend({
        options: {
            position: 'bottomleft' // Places the control at the bottom left
        },
        onAdd: function (map) {
            // Create container
            var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            container.style.backgroundColor = 'transparent';
            container.style.border = 'none';
            container.style.boxShadow = 'none';
            container.style.marginBottom = '10px';
            container.style.marginLeft = '10px';

            // Create button inside container
            var btn = L.DomUtil.create('button', '', container);
            btn.innerHTML = `
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.502.502 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103zM10 1.91l-4-.8v12.98l4 .8V1.91zm1 12.98 4-.8V1.11l-4 .8v12.98zm-6-.8V1.11l-4 .8v12.98l4-.8z"/>
                </svg>
                Open in Maps
            `;
            
            // Apply inline styles to match the original design
            btn.style.backgroundColor = '#e60000';
            btn.style.color = '#ffffff';
            btn.style.border = 'none';
            btn.style.padding = '10px 15px';
            btn.style.cursor = 'pointer';
            btn.style.borderRadius = '4px';
            btn.style.fontWeight = 'bold';
            btn.style.display = 'flex';
            btn.style.alignItems = 'center';
            btn.style.justifyContent = 'center';
            btn.style.gap = '8px';
            btn.style.boxShadow = '0 4px 6px rgba(0,0,0,0.3)';

            // Crucial: Stop click propagation so clicking the button doesn't affect the map underneath
            L.DomEvent.disableClickPropagation(btn);

            // Handle the click event
            L.DomEvent.on(btn, 'click', function(e) {
                // Change button text to show loading state
                var originalChildNodes = Array.from(btn.childNodes);
                btn.innerHTML = "Locating...";
                btn.style.opacity = "0.8";
                btn.disabled = true;

                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            var userLat = position.coords.latitude;
                            var userLng = position.coords.longitude;
                            var mapUrl = `https://www.google.com/maps/dir/?api=1&origin=${userLat},${userLng}&destination=${shopLat},${shopLng}&travelmode=driving`;
                            window.open(mapUrl, '_blank');
                            
                            btn.innerHTML = "";
                            originalChildNodes.forEach(node => btn.appendChild(node));
                            btn.style.opacity = "1";
                            btn.disabled = false;
                        }, 
                        function(error) {
                            alert("Could not get your location for directions. Opening destination location only on Google Maps.");
                            var mapUrl = `https://www.google.com/maps/search/?api=1&query=${shopLat},${shopLng}`;
                            window.open(mapUrl, '_blank');
                            
                            btn.innerHTML = "";
                            originalChildNodes.forEach(node => btn.appendChild(node));
                            btn.style.opacity = "1";
                            btn.disabled = false;
                        },
                        { timeout: 10000 }
                    );
                } else {
                    alert("Geolocation is not supported by your browser. Opening destination location only on Google Maps.");
                    var mapUrl = `https://www.google.com/maps/search/?api=1&query=${shopLat},${shopLng}`;
                    window.open(mapUrl, '_blank');
                    
                    btn.innerHTML = "";
                    originalChildNodes.forEach(node => btn.appendChild(node));
                    btn.style.opacity = "1";
                    btn.disabled = false;
                }
            });

            return container;
        }
    });

    // Add the control to the map
    map.addControl(new OpenMapsControl());
});
