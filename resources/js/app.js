import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import Swal from 'sweetalert2';
import L from 'leaflet';

window.Alpine = Alpine;
window.Chart = Chart;
window.Swal = Swal;
window.L = L;

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

// Leaflet dark tile helper
window.hsmCreateMap = function (el, lat = -0.5022, lng = 117.1536, zoom = 15) {
    const map = L.map(el, { zoomControl: true }).setView([lat, lng], zoom);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 20,
        subdomains: 'abcd',
        attribution: '&copy; OpenStreetMap &copy; CARTO',
    }).addTo(map);
    setTimeout(() => map.invalidateSize(), 200);
    return map;
};

window.hsmPinIcon = function (color = '#ff6500', glow = 'rgba(255,101,0,0.35)') {
    return L.divIcon({
        className: '',
        html: `<div class="hsm-pin" style="--pin-solid:${color};--pin:${glow}"></div>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });
};

Alpine.start();

// Register PWA Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
