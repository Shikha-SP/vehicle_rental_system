document.addEventListener("DOMContentLoaded", function () {

    // Rental Shop Coordinates
    var shopLat = 27.7124;
    var shopLng = 85.3309;

    // Initialize the map
    var map = L.map('showroom-map').setView([shopLat, shopLng], 16);

    var geoapifyUrl = '/vehicle_rental_system/ajax/map_proxy.php?z={z}&x={x}&y={y}';

    L.tileLayer(geoapifyUrl, {
        attribution: 'Powered by <a href="https://www.geoapify.com/">Geoapify</a> | &copy; OpenStreetMap contributors',
        maxZoom: 20
    }).addTo(map);

    // Add marker
    var marker = L.marker([shopLat, shopLng]).addTo(map);

    var popupContent = `
        <div style="text-align:center; padding:5px;">
            <b style="font-size:1.1em; color:#333;">TD Rentals</b><br>
            <span style="color:#666;">Naxal Bhagawati Marga<br>Kathmandu, Nepal</span>
        </div>
    `;

    marker.bindPopup(popupContent).openPopup();


    // Custom Control Button
    var OpenMapsControl = L.Control.extend({

        options: {
            position: 'bottomleft'
        },

        onAdd: function () {

            var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            container.style.backgroundColor = 'transparent';
            container.style.border = 'none';
            container.style.boxShadow = 'none';
            container.style.marginBottom = '10px';
            container.style.marginLeft = '10px';

            var btn = L.DomUtil.create('button', '', container);

            btn.innerHTML = `
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15.817.113A.5.5 0 0 1 16 .5v14a.5.5 0 0 1-.402.49l-5 1a.502.502 0 0 1-.196 0L5.5 15.01l-4.902.98A.5.5 0 0 1 0 15.5v-14a.5.5 0 0 1 .402-.49l5-1a.5.5 0 0 1 .196 0L10.5.99l4.902-.98a.5.5 0 0 1 .415.103z"/>
                </svg>
                Open in Maps
            `;

            // Button styles
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

            L.DomEvent.disableClickPropagation(btn);


            // Button Click Event
            L.DomEvent.on(btn, 'click', function () {

                var originalChildNodes = Array.from(btn.childNodes);

                btn.innerHTML = "Locating...";
                btn.style.opacity = "0.8";
                btn.disabled = true;


                function resetButton() {

                    btn.innerHTML = "";
                    originalChildNodes.forEach(node => btn.appendChild(node));

                    btn.style.opacity = "1";
                    btn.disabled = false;

                }


                function openMapsWithLocation() {

                    navigator.geolocation.getCurrentPosition(

                        function (position) {

                            var userLat = position.coords.latitude;
                            var userLng = position.coords.longitude;

                            var mapUrl = `https://www.google.com/maps/dir/?api=1&origin=${userLat},${userLng}&destination=${shopLat},${shopLng}&travelmode=driving`;

                            window.open(mapUrl, '_blank');

                            resetButton();

                        },

                        function () {

                            alert("Could not get your location. Opening destination only.");

                            var mapUrl = `https://www.google.com/maps/search/?api=1&query=${shopLat},${shopLng}`;

                            window.open(mapUrl, '_blank');

                            resetButton();

                        },

                        {
                            enableHighAccuracy: true,
                            timeout: 10000
                        }

                    );

                }


                if ("permissions" in navigator) {

                    navigator.permissions.query({ name: "geolocation" }).then(function (permissionStatus) {

                        if (permissionStatus.state === "granted") {

                            openMapsWithLocation();

                        }

                        else if (permissionStatus.state === "prompt") {

                            openMapsWithLocation(); // triggers browser prompt

                        }

                        else if (permissionStatus.state === "denied") {

                            alert("Location permission is blocked. Please enable it in your browser settings.");

                            var mapUrl = `https://www.google.com/maps/search/?api=1&query=${shopLat},${shopLng}`;

                            window.open(mapUrl, '_blank');

                            resetButton();

                        }

                    });

                } else {

                    openMapsWithLocation();

                }

            });

            return container;

        }

    });

    map.addControl(new OpenMapsControl());

});