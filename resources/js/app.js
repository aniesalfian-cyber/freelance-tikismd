import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import Swal from 'sweetalert2';
import L from 'leaflet';

window.Alpine = Alpine;
window.Chart = Chart;
window.Swal = Swal;
window.L = L;

// Koordinat Default: Pusat Kota Samarinda, Kalimantan Timur
window.HSM_SAMARINDA = {
    lat: -0.502106,
    lng: 117.153709,
    zoom: 14,
    label: 'Kota Samarinda, Kalimantan Timur',
};

// Chart.js default dark corporate theme
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.07)';
Chart.defaults.font.family = "'Plus Jakarta Sans Variable', ui-sans-serif, system-ui, sans-serif";

// SweetAlert2 Custom Glassmorphism Helpers
window.HsmSwal = {
    popup(opts = {}) {
        return Swal.fire({
            customClass: { popup: 'hsm-swal' },
            confirmButtonText: 'OK',
            ...opts,
        });
    },
    toast(title, icon = 'success') {
        return Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title,
            showConfirmButton: false,
            timer: 3400,
            timerProgressBar: true,
            customClass: { popup: 'hsm-toast' },
        });
    },
    confirm({ title = 'Konfirmasi Tindakan', text = 'Apakah Anda yakin?', confirmText = 'Ya, Lanjutkan', cancelText = 'Batal', icon = 'warning' } = {}) {
        return Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            reverseButtons: true,
            customClass: { popup: 'hsm-swal' },
        });
    },
};

// Confirm form submission via data-confirm attribute
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const msg = form.dataset.confirm;
    if (!msg || form.dataset.confirmed === '1') return;
    e.preventDefault();
    window.HsmSwal.confirm({
        title: form.dataset.confirmTitle || 'Konfirmasi',
        text: msg,
        confirmText: form.dataset.confirmButton || 'Ya, Lanjutkan',
    }).then((res) => {
        if (res.isConfirmed) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
});

// Haversine formula (meters) in browser for real-time distance preview
window.hsmDistanceMeters = function (lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    return Math.round(2 * R * Math.asin(Math.min(1, Math.sqrt(a))));
};

// Client-side image compression (< 800KB) for task proof upload
window.hsmCompressImage = function (file, maxWidth = 1280, quality = 0.8) {
    return new Promise((resolve) => {
        if (!file || !file.type.startsWith('image/') || file.size <= 600 * 1024) {
            resolve(file);
            return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
            const img = new Image();
            img.onload = () => {
                const scale = Math.min(1, maxWidth / img.width);
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(
                    (blob) => {
                        if (!blob) {
                            resolve(file);
                            return;
                        }
                        resolve(new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }));
                    },
                    'image/jpeg',
                    quality
                );
            };
            img.onerror = () => resolve(file);
            img.src = ev.target.result;
        };
        reader.onerror = () => resolve(file);
        reader.readAsDataURL(file);
    });
};

/**
 * Penampil Peta Gratis & Tanpa API Key (OpenStreetMap + Esri Satelit + Carto Voyager).
 * Koordinat default selalu mengarah ke Kota Samarinda (-0.502106, 117.153709).
 */
window.hsmCreateMap = function (elOrId, lat, lng, zoom = 14) {
    const el = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
    if (!el) return null;

    const validLat = Number.isFinite(Number(lat)) && Number(lat) !== 0 ? Number(lat) : window.HSM_SAMARINDA.lat;
    const validLng = Number.isFinite(Number(lng)) && Number(lng) !== 0 ? Number(lng) : window.HSM_SAMARINDA.lng;

    // Cegah error "Map container is already initialized"
    if (el._hsmMap) {
        el._hsmMap.setView([validLat, validLng], zoom);
        setTimeout(() => el._hsmMap.invalidateSize(), 100);
        return el._hsmMap;
    }
    if (el._leaflet_id) {
        el._leaflet_id = null;
        el.innerHTML = '';
    }

    const osmStreet = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
    });

    const cartoVoyager = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 20,
        subdomains: 'abcd',
        attribution: '&copy; OpenStreetMap &copy; CARTO',
    });

    const esriSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: '&copy; Esri World Imagery',
    });

    const map = L.map(el, {
        zoomControl: true,
        scrollWheelZoom: true,
        layers: [osmStreet],
    }).setView([validLat, validLng], zoom);

    L.control.layers(
        {
            'Peta Jalan (OSM)': osmStreet,
            'Peta Bersih (Voyager)': cartoVoyager,
            'Citra Satelit (Esri)': esriSatellite,
        },
        {},
        { position: 'topright', collapsed: true }
    ).addTo(map);

    el._hsmMap = map;

    // Pastikan ukuran peta selalu terhitung ulang setelah animasi halaman (.rise) & perubahan ukuran layar
    [60, 250, 600, 1000].forEach((ms) => setTimeout(() => map.invalidateSize(), ms));
    if (typeof ResizeObserver !== 'undefined') {
        const ro = new ResizeObserver(() => map.invalidateSize());
        ro.observe(el);
    }

    return map;
};

window.hsmPinIcon = function (color = '#ff6500', glow = 'rgba(255,101,0,0.4)') {
    return L.divIcon({
        className: '',
        html: `<div class="hsm-pin" style="--pin-solid:${color};--pin:${glow}"></div>`,
        iconSize: [20, 20],
        iconAnchor: [10, 10],
    });
};

// Auto-enhance all .tbl tables so every cell has data-label from thead on mobile card view
window.hsmEnhanceTables = function () {
    document.querySelectorAll('table.tbl').forEach((table) => {
        const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach((tr) => {
            const cells = Array.from(tr.querySelectorAll('td'));
            if (cells.length === 1 && cells[0].hasAttribute('colspan')) return;
            cells.forEach((td, idx) => {
                if (!td.hasAttribute('data-label') && headers[idx]) {
                    td.setAttribute('data-label', headers[idx]);
                }
                if (idx === 0 && !td.hasAttribute('data-card-header')) {
                    td.setAttribute('data-card-header', '1');
                }
            });
        });
    });
};
document.addEventListener('DOMContentLoaded', () => window.hsmEnhanceTables());
window.hsmEnhanceTables();

// Jalankan antrean callback script halaman yang menunggu hsmCreateMap / Chart siap
window.__hsmReadyQueue = window.__hsmReadyQueue || [];
window.hsmReady = function (fn) {
    if (typeof fn === 'function') {
        fn();
    }
};
while (window.__hsmReadyQueue.length > 0) {
    const fn = window.__hsmReadyQueue.shift();
    try { fn(); } catch (e) { console.error(e); }
}

Alpine.start();

// Register PWA Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
