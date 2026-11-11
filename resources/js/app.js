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

window.hsmCourierPinIcon = function (c) {
    const isWorking = Boolean(c.is_working);
    const isR4 = c.vehicle_type === 'R4';
    const bg = isWorking ? (isR4 ? '#2563eb' : '#10b981') : '#64748b';
    const ring = isWorking ? '0 0 0 4px rgba(16,185,129,0.28), 0 4px 10px rgba(15,23,42,0.35)' : '0 2px 6px rgba(15,23,42,0.25)';
    const badgeBg = isR4 ? '#1e3a8a' : '#ea580c';
    const html = `
        <div style="position:relative;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer;">
            <div style="width:32px;height:32px;border-radius:9999px;background:${bg};border:2.5px solid #ffffff;box-shadow:${ring};display:flex;align-items:center;justify-content:center;color:#ffffff;font-weight:900;font-size:11px;font-family:'Plus Jakarta Sans Variable',sans-serif;">
                ${c.initials || 'KR'}
            </div>
            <span style="position:absolute;bottom:-3px;right:-3px;background:${badgeBg};color:#fff;font-size:8px;font-weight:800;padding:1px 4px;border-radius:6px;border:1.5px solid #fff;line-height:1;">
                ${c.vehicle_type || 'R2'}
            </span>
        </div>
    `;
    return L.divIcon({
        className: '',
        html,
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        popupAnchor: [0, -18],
    });
};

// PWA Install Prompt Handling
window.__hsmDeferredPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.__hsmDeferredPrompt = e;
    window.dispatchEvent(new CustomEvent('hsm-pwa-ready'));
});

window.hsmTriggerPwaInstall = async function () {
    if (window.__hsmDeferredPrompt) {
        window.__hsmDeferredPrompt.prompt();
        const { outcome } = await window.__hsmDeferredPrompt.userChoice;
        if (outcome === 'accepted') {
            window.__hsmDeferredPrompt = null;
            window.HsmSwal.toast('Aplikasi D&P PT. HSM sedang dipasang!', 'success');
        }
        return;
    }

    // Fallback panduan jika browser sudah menginstal atau memerlukan menu manual (iOS Safari / Chrome Mobile)
    window.HsmSwal.popup({
        icon: 'info',
        title: 'Cara Pasang Aplikasi (PWA)',
        html: `
            <div style="text-align:left;font-size:13px;line-height:1.6;">
                <p style="margin-bottom:8px;">Untuk memasang aplikasi <b>D&P PT. HSM</b> di layar utama HP Anda:</p>
                <ol style="padding-left:18px;margin:0;space-y:4px;">
                    <li><b>Android (Chrome/Edge):</b> Ketuk ikon menu <b>⋮</b> di pojok kanan atas browser, lalu pilih <b>"Instal aplikasi"</b> atau <b>"Tambahkan ke Layar utama"</b>.</li>
                    <li style="margin-top:6px;"><b>iPhone / iOS (Safari):</b> Ketuk tombol <b>Bagikan (Share)</b> di bagian bawah layar, lalu pilih <b>"Tambah ke Layar Utama (Add to Home Screen)"</b>.</li>
                </ol>
            </div>
        `,
        confirmButtonText: 'Mengerti',
    });
};

// Web Notification & ServiceWorker Push Helper
window.hsmRequestNotificationPermission = async function () {
    if (!('Notification' in window)) {
        window.HsmSwal.toast('Browser perangkat ini tidak mendukung notifikasi.', 'warning');
        return 'unsupported';
    }
    const perm = await Notification.requestPermission();
    if (perm === 'granted') {
        window.HsmSwal.toast('Push Notifikasi berhasil diaktifkan!', 'success');
        window.hsmShowPushNotification(
            'Notifikasi Aktif — D&P PT. HSM',
            'Anda akan menerima pemberitahuan instan setiap kali ada tugas baru dari Admin HRD.',
            '/pwa/workspace'
        );
    } else if (perm === 'denied') {
        window.HsmSwal.toast('Izin notifikasi ditolak di pengaturan browser.', 'warning');
    }
    return perm;
};

window.hsmShowPushNotification = function (title, body, url = '/pwa/workspace') {
    if (window.HsmSwal) {
        window.HsmSwal.toast(title, 'info');
    }
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.ready
            .then((reg) => {
                reg.showNotification(title, {
                    body,
                    icon: '/icons/icon-192.svg',
                    badge: '/icons/icon-192.svg',
                    vibrate: [200, 100, 200],
                    data: { url },
                });
            })
            .catch(() => {
                try {
                    const n = new Notification(title, { body, icon: '/icons/icon-192.svg' });
                    n.onclick = () => { window.location.href = url; };
                } catch (_) {}
            });
    }
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
