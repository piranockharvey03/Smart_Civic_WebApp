(function(window) {
    'use strict';

    const app = window.SmartCivicMaps = window.SmartCivicMaps || {};

    app.createHeatLayer = function(points) {
        if (!window.L || !window.L.heatLayer) {
            return null;
        }

        const heatPoints = (points || [])
            .filter((point) => point && Number.isFinite(Number(point.latitude)) && Number.isFinite(Number(point.longitude)))
            .map((point) => [Number(point.latitude), Number(point.longitude), Number(point.heat_weight || app.markerWeight(point))]);

        return window.L.heatLayer(heatPoints, {
            radius: 28,
            blur: 22,
            minOpacity: 0.3,
            maxZoom: 17,
            gradient: {
                0.2: '#2ecc71',
                0.4: '#f1c40f',
                0.65: '#f39c12',
                0.8: '#e74c3c',
                1.0: '#8e0000'
            }
        });
    };
})(window);