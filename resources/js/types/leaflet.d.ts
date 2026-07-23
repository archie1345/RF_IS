declare module 'leaflet' {
    type LatLngTuple = [number, number];
    export type LatLngExpression = LatLngTuple | { lat: number; lng: number };
    export type LeafletMouseEvent = { latlng: { lat: number; lng: number } };

    export interface Map {
        setView(center: LatLngExpression, zoom?: number): Map;
        getZoom(): number;
        on(event: string, handler: (event: LeafletMouseEvent) => void): Map;
        off(event: string, handler: (event: LeafletMouseEvent) => void): Map;
        remove(): void;
        invalidateSize(): void;
    }

    export interface Marker {
        addTo(map: Map): Marker;
        setLatLng(latlng: LatLngExpression): Marker;
        bindPopup(content: string): Marker;
    }

    export namespace Icon {
        class Default {
            static prototype: Default;
            static mergeOptions(options: Record<string, unknown>): void;
        }
    }

    const L: {
        Icon: typeof Icon;
        map(element: HTMLElement, options?: Record<string, unknown>): Map;
        marker(latlng: LatLngExpression): Marker;
        tileLayer(urlTemplate: string, options?: Record<string, unknown>): { addTo(map: Map): unknown };
    };

    export default L;
}

declare module 'leaflet/dist/leaflet.css';
declare module 'leaflet/dist/images/marker-icon-2x.png';
declare module 'leaflet/dist/images/marker-icon.png';
declare module 'leaflet/dist/images/marker-shadow.png';
