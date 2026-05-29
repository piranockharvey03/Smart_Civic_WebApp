(function(window) {
    'use strict';

    const app = window.SmartCivicMaps = window.SmartCivicMaps || {};
    const defaultCenter = [0.3476, 32.5825];

    const statusClassMap = {
        resolved: 'success',
        closed: 'success',
        in_progress: 'warning',
        assigned: 'warning',
        under_review: 'warning',
        pending: 'warning',
        reopened: 'danger',
        submitted: 'danger'
    };

    function getLeaflet() {
        return window.L || null;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    app.defaultCenter = defaultCenter;
    app.statusClassMap = statusClassMap;

    app.statusTier = function(status) {
        const normalized = String(status || '').toLowerCase();
        if (normalized === 'resolved' || normalized === 'closed') {
            return 'success';
        }

        if (normalized === 'in_progress' || normalized === 'assigned' || normalized === 'under_review' || normalized === 'pending') {
            return 'warning';
        }

        return 'danger';
    };

    app.markerWeight = function(point) {
        const priority = String(point && point.priority ? point.priority : 'medium').toLowerCase();

        switch (priority) {
            case 'critical':
                return 1;
            case 'high':
                return 0.8;
            case 'low':
                return 0.35;
            default:
                return 0.55;
        }
    };

    app.formatCoordinates = function(lat, lng) {
        const latitude = Number(lat);
        const longitude = Number(lng);

        if (Number.isNaN(latitude) || Number.isNaN(longitude)) {
            return 'No coordinates selected yet';
        }

        return latitude.toFixed(6) + ', ' + longitude.toFixed(6);
    };

    app.createMarkerIcon = function(statusTier) {
        const leaflet = getLeaflet();
        if (!leaflet) {
            return null;
        }

        const tier = statusTier || 'danger';

        return leaflet.divIcon({
            className: 'smart-civic-marker smart-civic-marker--' + tier,
            html: '<span class="smart-civic-marker__pin"></span>',
            iconSize: [20, 32],
            iconAnchor: [10, 32],
            popupAnchor: [0, -28]
        });
    };

    app.createBaseMap = function(container, options) {
        const leaflet = getLeaflet();
        if (!leaflet) {
            return null;
        }

        const config = options || {};
        const lat = Number(config.lat ?? defaultCenter[0]);
        const lng = Number(config.lng ?? defaultCenter[1]);
        const zoom = Number(config.zoom ?? 13);
        const map = leaflet.map(container, {
            zoomControl: true,
            scrollWheelZoom: false,
            preferCanvas: true
        }).setView([lat, lng], zoom);

        leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        return map;
    };

    app.buildPopupHtml = function(issue) {
        const ticket = escapeHtml(issue.ticket_number);
        const title = escapeHtml(issue.title);
        const status = escapeHtml(issue.status || 'submitted');
        const priority = escapeHtml(issue.priority || 'medium');
        const category = escapeHtml(issue.category_name || 'Unknown');
        const location = escapeHtml(issue.location || '');
        const address = escapeHtml(issue.address || '');
        const division = escapeHtml(issue.division || 'Unknown');
        const reporter = escapeHtml(issue.reporter_name || 'Unknown');
        const link = escapeHtml(issue.issue_url || '#');

        return (
            '<div class="map-popup">' +
                '<div class="map-popup__ticket">' + ticket + '</div>' +
                '<div class="map-popup__title">' + title + '</div>' +
                '<div class="map-popup__meta">Status: ' + status + ' | Priority: ' + priority + '</div>' +
                '<div class="map-popup__meta">Category: ' + category + '</div>' +
                '<div class="map-popup__meta">Division: ' + division + '</div>' +
                (location ? '<div class="map-popup__meta">Location: ' + location + '</div>' : '') +
                (address ? '<div class="map-popup__meta">Address: ' + address + '</div>' : '') +
                '<div class="map-popup__meta">Reporter: ' + reporter + '</div>' +
                '<a class="map-popup__link" href="' + link + '">Open ticket</a>' +
            '</div>'
        );
    };

    app.searchLocation = async function(query) {
        const value = String(query || '').trim();

        if (!value) {
            return [];
        }

        const url = new URL('https://nominatim.openstreetmap.org/search');
        url.searchParams.set('format', 'jsonv2');
        url.searchParams.set('limit', '5');
        url.searchParams.set('q', value);

        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Location search failed.');
        }

        return response.json();
    };

    app.reverseGeocode = async function(lat, lng) {
        const latitude = Number(lat);
        const longitude = Number(lng);

        if (Number.isNaN(latitude) || Number.isNaN(longitude)) {
            return '';
        }

        const url = new URL('https://nominatim.openstreetmap.org/reverse');
        url.searchParams.set('format', 'jsonv2');
        url.searchParams.set('lat', String(latitude));
        url.searchParams.set('lon', String(longitude));

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                return '';
            }

            const data = await response.json();
            return String(data.display_name || '');
        } catch (error) {
            return '';
        }
    };

    app.updateInputValue = function(field, value) {
        if (!field) {
            return;
        }

        field.value = value == null ? '' : String(value);
    };

    app.updateStatusText = function(element, message, tone) {
        if (!element) {
            return;
        }

        element.textContent = message || '';
        element.dataset.state = tone || 'neutral';
    };

    app.setPointOnMap = function(mapState, lat, lng, address, options) {
        const leaflet = getLeaflet();
        if (!leaflet || !mapState || !mapState.map) {
            return;
        }

        const latitude = Number(lat);
        const longitude = Number(lng);
        if (Number.isNaN(latitude) || Number.isNaN(longitude)) {
            return;
        }

        const config = options || {};
        const markerOptions = {
            icon: config.icon || app.createMarkerIcon(config.statusTier || 'danger'),
            draggable: Boolean(config.draggable)
        };

        if (mapState.marker) {
            mapState.map.removeLayer(mapState.marker);
        }

        mapState.marker = leaflet.marker([latitude, longitude], markerOptions).addTo(mapState.map);
        mapState.lat = latitude;
        mapState.lng = longitude;

        if (config.popupHtml) {
            mapState.marker.bindPopup(config.popupHtml).openPopup();
        }

        if (markerOptions.draggable) {
            mapState.marker.on('dragend', async (event) => {
                const next = event.target.getLatLng();
                mapState.lat = next.lat;
                mapState.lng = next.lng;

                if (typeof config.onMove === 'function') {
                    await config.onMove(next.lat, next.lng);
                }
            });
        }

        mapState.map.setView([latitude, longitude], config.zoom || mapState.map.getZoom(), { animate: true });

        if (typeof config.onSet === 'function') {
            config.onSet(latitude, longitude, address || '');
        }
    };
})(window);