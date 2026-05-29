(function(window) {
    'use strict';

    const app = window.SmartCivicMaps;

    function getWrapper(selector) {
        return document.querySelector(selector);
    }

    async function updateAddressFromPoint(wrapper, lat, lng) {
        const addressField = wrapper.querySelector('[name="address"]');
        const address = await app.reverseGeocode(lat, lng);

        if (address && addressField && !addressField.value) {
            addressField.value = address;
        }
    }

    function initPicker(wrapper) {
        const mapContainer = wrapper.querySelector('#reportIssueMap');
        const geolocateButton = wrapper.querySelector('[data-geolocate-btn]');
        const geolocateSpinner = wrapper.querySelector('[data-geolocate-spinner]');
        const searchInput = wrapper.querySelector('[data-map-search-input]');
        const searchButton = wrapper.querySelector('[data-map-search-btn]');
        const statusElement = wrapper.querySelector('[data-location-status]');
        const coordinateDisplay = wrapper.querySelector('[data-coordinate-display]');
        const latitudeField = wrapper.querySelector('[name="latitude"]');
        const longitudeField = wrapper.querySelector('[name="longitude"]');
        const addressField = wrapper.querySelector('[name="address"]');
        const centerLat = Number(wrapper.dataset.mapCenterLat || 0.3476);
        const centerLng = Number(wrapper.dataset.mapCenterLng || 32.5825);
        const zoom = Number(wrapper.dataset.mapZoom || 13);
        const existingLat = Number(latitudeField && latitudeField.value ? latitudeField.value : '');
        const existingLng = Number(longitudeField && longitudeField.value ? longitudeField.value : '');

        if (!mapContainer || !window.L) {
            return;
        }

        const mapState = {
            map: app.createBaseMap(mapContainer, { lat: centerLat, lng: centerLng, zoom }),
            marker: null
        };

        const syncFields = (lat, lng, address) => {
            app.updateInputValue(latitudeField, lat.toFixed(8));
            app.updateInputValue(longitudeField, lng.toFixed(8));
            app.updateInputValue(coordinateDisplay, app.formatCoordinates(lat, lng));
            if (addressField && address) {
                addressField.value = address;
            }
        };

        const placePoint = async (lat, lng, address, zoomLevel) => {
            app.setPointOnMap(mapState, lat, lng, address, {
                draggable: true,
                zoom: zoomLevel || 16,
                statusTier: 'primary',
                onSet: (nextLat, nextLng, nextAddress) => {
                    syncFields(nextLat, nextLng, nextAddress);
                    app.updateStatusText(statusElement, 'Location selected: ' + app.formatCoordinates(nextLat, nextLng), 'success');
                },
                onMove: async (nextLat, nextLng) => {
                    syncFields(nextLat, nextLng, addressField ? addressField.value : '');
                    app.updateStatusText(statusElement, 'Marker moved. Coordinates updated.', 'success');
                    await updateAddressFromPoint(wrapper, nextLat, nextLng);
                }
            });
        };

        mapState.map.on('click', async (event) => {
            await placePoint(event.latlng.lat, event.latlng.lng, '', 16);
            await updateAddressFromPoint(wrapper, event.latlng.lat, event.latlng.lng);
        });

        if (!Number.isNaN(existingLat) && !Number.isNaN(existingLng) && existingLat !== 0 && existingLng !== 0) {
            placePoint(existingLat, existingLng, addressField ? addressField.value : '', 16);
        }

        if (geolocateButton) {
            geolocateButton.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    app.updateStatusText(statusElement, 'Geolocation is not supported in this browser.', 'danger');
                    return;
                }

                geolocateButton.disabled = true;
                if (geolocateSpinner) {
                    geolocateSpinner.classList.remove('d-none');
                }

                app.updateStatusText(statusElement, 'Requesting your current location...', 'neutral');

                navigator.geolocation.getCurrentPosition(async (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const address = await app.reverseGeocode(lat, lng);
                    await placePoint(lat, lng, address, 17);
                    app.updateStatusText(statusElement, 'GPS coordinates captured successfully.', 'success');
                    geolocateButton.disabled = false;
                    if (geolocateSpinner) {
                        geolocateSpinner.classList.add('d-none');
                    }
                }, (error) => {
                    let message = 'Location access was denied or unavailable.';
                    if (error && error.code === error.PERMISSION_DENIED) {
                        message = 'Location permission was denied. You can still place the marker manually.';
                    }
                    app.updateStatusText(statusElement, message, 'danger');
                    geolocateButton.disabled = false;
                    if (geolocateSpinner) {
                        geolocateSpinner.classList.add('d-none');
                    }
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            });
        }

        const performSearch = async () => {
            const query = searchInput ? searchInput.value.trim() : '';
            if (!query) {
                app.updateStatusText(statusElement, 'Type a place name before searching.', 'danger');
                return;
            }

            app.updateStatusText(statusElement, 'Searching OpenStreetMap...', 'neutral');

            try {
                const results = await app.searchLocation(query);
                if (!results || !results.length) {
                    app.updateStatusText(statusElement, 'No matching location was found.', 'danger');
                    return;
                }

                const result = results[0];
                await placePoint(Number(result.lat), Number(result.lon), String(result.display_name || query), 16);
                app.updateStatusText(statusElement, 'Location found and marker updated.', 'success');
            } catch (error) {
                app.updateStatusText(statusElement, 'Location search failed. Try a different search term.', 'danger');
            }
        };

        if (searchButton) {
            searchButton.addEventListener('click', performSearch);
        }

        if (searchInput) {
            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    performSearch();
                }
            });
        }
    }

    function initReadonly(wrapper) {
        const mapContainer = wrapper;
        const lat = Number(wrapper.dataset.issueLat);
        const lng = Number(wrapper.dataset.issueLng);
        const title = wrapper.dataset.issueTitle || 'Reported issue';
        const address = wrapper.dataset.issueAddress || '';

        if (!mapContainer || !window.L || Number.isNaN(lat) || Number.isNaN(lng)) {
            return;
        }

        const map = app.createBaseMap(mapContainer, { lat, lng, zoom: 15 });
        const marker = window.L.marker([lat, lng], { icon: app.createMarkerIcon('danger') }).addTo(map);

        marker.bindPopup('<div class="map-popup"><div class="map-popup__ticket">' + title + '</div><div class="map-popup__meta">' + (address ? address : app.formatCoordinates(lat, lng)) + '</div></div>').openPopup();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const pickerWrapper = getWrapper('[data-map-mode="picker"]');
        const readonlyWrapper = getWrapper('[data-map-mode="readonly"]');

        if (pickerWrapper) {
            initPicker(pickerWrapper);
        }

        if (readonlyWrapper) {
            initReadonly(readonlyWrapper);
        }
    });
})(window);