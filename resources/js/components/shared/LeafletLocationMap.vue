<script setup lang="ts">
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type LatLngValue = string | number | null | undefined;

const props = withDefaults(
    defineProps<{
        latitude?: LatLngValue;
        longitude?: LatLngValue;
        editable?: boolean;
        openGoogleMapsOnClick?: boolean;
        markerLabel?: string;
        zoom?: number;
    }>(),
    {
        latitude: null,
        longitude: null,
        editable: false,
        openGoogleMapsOnClick: false,
        markerLabel: 'Lokasi latihan',
        zoom: 15,
    },
);

const emit = defineEmits<{
    change: [payload: { latitude: string; longitude: string }];
}>();

const mapEl = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let marker: L.Marker | null = null;

const defaultCenter: L.LatLngExpression = [-7.9173211, 112.6448861];

function numericCoordinate(value: LatLngValue): number | null {
    if (value === null || value === undefined || value === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function currentLatLng(): L.LatLngExpression | null {
    const lat = numericCoordinate(props.latitude);
    const lng = numericCoordinate(props.longitude);
    if (lat === null || lng === null) return null;
    return [lat, lng];
}

function googleMapsUrl(lat: number, lng: number): string {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${lat},${lng}`)}`;
}

function setMarker(lat: number, lng: number, shouldPan = true) {
    if (!map) return;

    if (!marker) {
        marker = L.marker([lat, lng]).addTo(map);
    } else {
        marker.setLatLng([lat, lng]);
    }

    marker.bindPopup(props.markerLabel);

    if (shouldPan) {
        map.setView([lat, lng], Math.max(map.getZoom(), props.zoom));
    }
}

function syncFromProps(shouldPan = false) {
    const lat = numericCoordinate(props.latitude);
    const lng = numericCoordinate(props.longitude);

    if (lat === null || lng === null) {
        return;
    }

    setMarker(lat, lng, shouldPan);
}

function handleMapClick(event: L.LeafletMouseEvent) {
    const { lat, lng } = event.latlng;

    if (props.editable) {
        const latitude = lat.toFixed(7);
        const longitude = lng.toFixed(7);
        emit('change', { latitude, longitude });
        setMarker(lat, lng);
        return;
    }

    if (props.openGoogleMapsOnClick) {
        window.open(googleMapsUrl(lat, lng), '_blank', 'noopener,noreferrer');
    }
}

onMounted(async () => {
    await nextTick();
    if (!mapEl.value) return;

    const initial = currentLatLng() ?? defaultCenter;
    map = L.map(mapEl.value, { scrollWheelZoom: true }).setView(initial, currentLatLng() ? props.zoom : 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    map.on('click', handleMapClick);
    syncFromProps(false);

    setTimeout(() => map?.invalidateSize(), 150);
});

watch(
    () => [props.latitude, props.longitude, props.markerLabel],
    () => syncFromProps(true),
);

onBeforeUnmount(() => {
    if (map) {
        map.off('click', handleMapClick);
        map.remove();
    }
    map = null;
    marker = null;
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-border bg-muted/30">
        <div ref="mapEl" class="h-72 w-full"></div>
    </div>
</template>
