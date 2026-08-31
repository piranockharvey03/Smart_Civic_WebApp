(function(window) {
    'use strict';

    const app = window.SmartCivicMaps;

    function getFilters(form) {
        const params = new URLSearchParams();
        if (!form) {
            return params;
        }

        const formData = new FormData(form);
        for (const [key, value] of formData.entries()) {
            const trimmed = String(value || '').trim();
            if (trimmed !== '') {
                params.set(key, trimmed);
            }
        }

        return params;
    }

    function buildPopup(issue) {
        return app.buildPopupHtml(issue);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.getElementById('adminIssueMap');
        if (!wrapper || !window.L) {
            return;
        }

        const map = app.createBaseMap(wrapper, { lat: 0.3476, lng: 32.5825, zoom: 12 });
        const clusterLayer = window.L.markerClusterGroup({ chunkedLoading: true, disableClusteringAtZoom: 17 });
        const heatLayer = app.createHeatLayer([]);
        const filtersForm = document.getElementById(wrapper.dataset.mapFilters || 'issueMapFilters');
        const statusElement = document.querySelector('[data-map-status]');
        const viewButtons = Array.from(document.querySelectorAll('[data-map-view]'));
        let activeView = 'markers';
        let activeHeatLayer = heatLayer;
        let activePointCount = 0;

        function updateStatus(message) {
            if (statusElement) {
                statusElement.textContent = message;
            }
        }

        function setView(view) {
            activeView = view;
            viewButtons.forEach((button) => {
                button.classList.toggle('active', button.dataset.mapView === view);
            });

            if (activeView === 'heat') {
                if (map.hasLayer(clusterLayer)) {
                    map.removeLayer(clusterLayer);
                }
                if (activeHeatLayer && !map.hasLayer(activeHeatLayer)) {
                    activeHeatLayer.addTo(map);
                }
            } else {
                if (activeHeatLayer && map.hasLayer(activeHeatLayer)) {
                    map.removeLayer(activeHeatLayer);
                }
                if (!map.hasLayer(clusterLayer)) {
                    map.addLayer(clusterLayer);
                }
            }
        }

        async function loadIssues() {
            const source = wrapper.dataset.mapSource;
            const params = getFilters(filtersForm);
            const role = wrapper.dataset.mapRole || new URLSearchParams(window.location.search).get('role');
            const url = new URL(source, window.location.origin);

            if (role) {
                url.searchParams.set('role', role);
            }

            for (const [key, value] of params.entries()) {
                url.searchParams.set(key, value);
            }

            updateStatus('Loading issue locations...');

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to load map data.');
            }

            const payload = await response.json();
            const items = Array.isArray(payload.items) ? payload.items : [];

            clusterLayer.clearLayers();
            if (activeHeatLayer && map.hasLayer(activeHeatLayer)) {
                map.removeLayer(activeHeatLayer);
            }
            if (map.hasLayer(clusterLayer)) {
                map.removeLayer(clusterLayer);
            }

            activeHeatLayer = app.createHeatLayer(items);

            items.forEach((issue) => {
                if (!Number.isFinite(Number(issue.latitude)) || !Number.isFinite(Number(issue.longitude))) {
                    return;
                }

                const marker = window.L.marker([Number(issue.latitude), Number(issue.longitude)], {
                    icon: app.createMarkerIcon(app.statusTier(issue.status))
                });

                marker.bindPopup(buildPopup(issue));
                clusterLayer.addLayer(marker);
            });

            activePointCount = items.length;
            setView(activeView);
            map.invalidateSize();
            if (items.length) {
                const bounds = clusterLayer.getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds.pad(0.12));
                }
            }

            updateStatus(items.length ? (items.length + ' mapped issues loaded.') : 'No mapped issues match the selected filters.');
        }

        viewButtons.forEach((button) => {
            button.addEventListener('click', () => setView(button.dataset.mapView || 'markers'));
        });

        if (filtersForm) {
            filtersForm.addEventListener('submit', (event) => {
                event.preventDefault();
                loadIssues().catch(() => updateStatus('Could not refresh the map right now.'));
            });
        }

        loadIssues().catch(() => updateStatus('Could not load map data.'));
    });
})(window);