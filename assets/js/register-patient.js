// --- Map initialization ---
let map;
let marker;
let lastValidLatLng = null;
let markerValidationRequestId = 0;
const GEOLOCATION_OPTIONS = {
    enableHighAccuracy: true,
    timeout: 12000,
    maximumAge: 0
};
const addressField = document.querySelector('textarea[name="address"]');
let CAVITE_BOUNDS = L.latLngBounds(
    [14.02, 120.56], // southwest
    [14.52, 121.05]  // northeast
);
const DEFAULT_DISPLAY_BOUNDS = L.latLngBounds(
    [14.06, 120.58], // southwest (looser view)
    [14.50, 121.05]  // northeast (looser view)
);
let DISPLAY_BOUNDS = DEFAULT_DISPLAY_BOUNDS;
const CAVITE_GEOJSON_URLS = [
    '/THESIS/LYINGIN/assets/geojson/cavite-province.geojson'
];
const MAP_MASK_OUTER = [
    [-90, -180],
    [-90, 180],
    [90, 180],
    [90, -180]
];
let cavitePolygonRings = null;
let caviteMaskLayer = null;

function clampToCavite(lat, lng) {
    const southWest = CAVITE_BOUNDS.getSouthWest();
    const northEast = CAVITE_BOUNDS.getNorthEast();

    const clampedLat = Math.min(Math.max(lat, southWest.lat), northEast.lat);
    const clampedLng = Math.min(Math.max(lng, southWest.lng), northEast.lng);

    return { lat: clampedLat, lng: clampedLng };
}

function isPointInsideCavitePolygon(lat, lng) {
    if (!cavitePolygonRings) {
        return CAVITE_BOUNDS.contains([lat, lng]);
    }

    const point = { lat, lng };
    for (const polygon of cavitePolygonRings) {
        if (isPointInPolygon(point, polygon.outer, polygon.holes)) {
            return true;
        }
    }
    return false;
}

function isPointInPolygon(point, outerRing, holeRings) {
    if (!pointInRing(point, outerRing)) {
        return false;
    }
    for (const hole of holeRings) {
        if (pointInRing(point, hole)) {
            return false;
        }
    }
    return true;
}

function pointInRing(point, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const xi = ring[i].lng;
        const yi = ring[i].lat;
        const xj = ring[j].lng;
        const yj = ring[j].lat;

        const intersects = ((yi > point.lat) !== (yj > point.lat)) &&
            (point.lng < (xj - xi) * (point.lat - yi) / ((yj - yi) || Number.EPSILON) + xi);
        if (intersects) inside = !inside;
    }
    return inside;
}

function normalizeRing(coords) {
    return coords.map(([lng, lat]) => ({ lat, lng }));
}

function extractCaviteRings(geojson) {
    if (!geojson || !geojson.type) return [];

    const polygons = [];
    const collectFromGeometry = (geometry) => {
        if (!geometry || !geometry.type || !geometry.coordinates) return;
        if (geometry.type === 'Polygon') {
            polygons.push(geometry.coordinates);
        } else if (geometry.type === 'MultiPolygon') {
            polygons.push(...geometry.coordinates);
        }
    };

    if (geojson.type === 'FeatureCollection') {
        geojson.features.forEach((feature) => collectFromGeometry(feature && feature.geometry));
    } else if (geojson.type === 'Feature') {
        collectFromGeometry(geojson.geometry);
    } else {
        collectFromGeometry(geojson);
    }

    return polygons.map((polyCoords) => {
        const outer = normalizeRing(polyCoords[0] || []);
        const holes = (polyCoords.slice(1) || []).map(normalizeRing);
        return { outer, holes };
    });
}

function computeBoundsFromRings(rings) {
    const bounds = L.latLngBounds([]);
    rings.forEach((poly) => {
        poly.outer.forEach((pt) => bounds.extend([pt.lat, pt.lng]));
    });
    return bounds.isValid() ? bounds : CAVITE_BOUNDS;
}

function tightenBounds(bounds) {
    const tightened = bounds.pad(-0.2);
    return tightened.isValid() ? tightened : bounds;
}

function constrainBounds(bounds, limiter) {
    const sw = bounds.getSouthWest();
    const ne = bounds.getNorthEast();
    const lsw = limiter.getSouthWest();
    const lne = limiter.getNorthEast();
    const constrained = L.latLngBounds(
        [Math.max(sw.lat, lsw.lat), Math.max(sw.lng, lsw.lng)],
        [Math.min(ne.lat, lne.lat), Math.min(ne.lng, lne.lng)]
    );
    return constrained.isValid() ? constrained : bounds;
}

function buildCaviteMask() {
    if (caviteMaskLayer) {
        map.removeLayer(caviteMaskLayer);
        caviteMaskLayer = null;
    }
}

function applyCaviteGeojson(geojson) {
    cavitePolygonRings = extractCaviteRings(geojson);
    CAVITE_BOUNDS = computeBoundsFromRings(cavitePolygonRings);
    DISPLAY_BOUNDS = constrainBounds(tightenBounds(CAVITE_BOUNDS), DEFAULT_DISPLAY_BOUNDS);
    if (map) {
        map.setMaxBounds(DISPLAY_BOUNDS);
        const boundsZoom = map.getBoundsZoom(DISPLAY_BOUNDS, false, [10, 10]);
        map.setMinZoom(Math.max(6, boundsZoom));
        map.fitBounds(DISPLAY_BOUNDS, { padding: [10, 10] });
    }
    buildCaviteMask();
}

function getCachedCaviteGeojson() {
    try {
        const cached = localStorage.getItem('caviteGeojsonCache_v2');
        if (!cached) return null;
        return JSON.parse(cached);
    } catch (error) {
        console.warn('Cavite GeoJSON cache read failed:', error);
        return null;
    }
}

function setCachedCaviteGeojson(data) {
    try {
        localStorage.setItem('caviteGeojsonCache_v2', JSON.stringify(data));
        localStorage.setItem('caviteGeojsonCacheAt_v2', new Date().toISOString());
    } catch (error) {
        console.warn('Cavite GeoJSON cache write failed:', error);
    }
}

function fetchGeojsonNoCache(url) {
    const cacheBuster = `v=${Date.now()}`;
    const finalUrl = url.includes('?') ? `${url}&${cacheBuster}` : `${url}?${cacheBuster}`;
    console.log('Loading Cavite GeoJSON:', finalUrl);
    return fetch(finalUrl, { cache: 'no-store' })
        .then((res) => {
            if (!res.ok) {
                throw new Error(`GeoJSON HTTP ${res.status}`);
            }
            return res.json();
        });
}

function loadCaviteGeojson() {
    let chain = Promise.reject(new Error('GeoJSON sources not tried yet'));
    CAVITE_GEOJSON_URLS.forEach((url) => {
        chain = chain.catch(() => fetchGeojsonNoCache(url));
    });

    return chain
        .then((geojson) => {
            setCachedCaviteGeojson(geojson);
            applyCaviteGeojson(geojson);
        })
        .catch((error) => {
            console.warn('Failed to load Cavite GeoJSON from network. Trying cache.', error);
            const cached = getCachedCaviteGeojson();
            if (cached) {
                applyCaviteGeojson(cached);
                return;
            }
            console.warn('Failed to load Cavite GeoJSON. Falling back to bounds only.', error);
            addressField.value = 'Map boundary failed to load. Please refresh the page.';
        });
}

function isPointInCaviteFromAddress(data) {
    const addr = data && data.address ? data.address : {};
    const province = String(addr.province || '').toLowerCase();
    const state = String(addr.state || '').toLowerCase();
    const county = String(addr.county || '').toLowerCase();
    const stateDistrict = String(addr.state_district || '').toLowerCase();

    return (
        province === 'cavite' ||
        state === 'cavite' ||
        county === 'cavite' ||
        stateDistrict === 'cavite'
    );
}

function restoreLastValidMarkerPosition() {
    if (marker && lastValidLatLng) {
        marker.setLatLng(lastValidLatLng);
        addressField.value = 'Selected point is outside Cavite. Please choose a location within Cavite.';
    }
}

function trySetMarkerAndAddress(lat, lng) {
    const requestId = ++markerValidationRequestId;
    const clamped = clampToCavite(lat, lng);

    if (!isPointInsideCavitePolygon(clamped.lat, clamped.lng)) {
        restoreLastValidMarkerPosition();
        addressField.value = 'Selected point is outside Cavite. Please choose a location within Cavite only.';
        return Promise.resolve(false);
    }

    marker.setLatLng([clamped.lat, clamped.lng]);

    return getAddressFromCoordinates(clamped.lat, clamped.lng).then((isValid) => {
        if (requestId !== markerValidationRequestId) {
            return false;
        }

        if (isValid === null) {
            return false;
        }

        if (isValid) {
            lastValidLatLng = marker.getLatLng();
            return true;
        }
        restoreLastValidMarkerPosition();
        return false;
    }).catch((error) => {
        console.error('Marker/address validation failed:', error);
        if (requestId === markerValidationRequestId) {
            restoreLastValidMarkerPosition();
        }
        return false;
    });
}

function handleGeolocationError(error) {
    if (!error) {
        addressField.value = 'Unable to get GPS location. Set your location manually within Cavite.';
        return;
    }

    if (error.code === 1) {
        addressField.value = 'GPS permission denied. Allow location access in your browser settings.';
        return;
    }

    if (error.code === 2) {
        addressField.value = 'GPS position unavailable. Turn on device location services and try again.';
        return;
    }

    if (error.code === 3) {
        addressField.value = 'GPS request timed out. Move to an open area and try again.';
        return;
    }

    addressField.value = 'Unable to get GPS location. Set your location manually within Cavite.';
}

function requestUserGpsLocation() {
    if (!navigator.geolocation) {
        addressField.value = 'GPS is not supported on this device/browser. Set your location manually within Cavite.';
        return Promise.resolve(false);
    }

    if (!window.isSecureContext) {
        addressField.value = 'GPS requires a secure context. Open this site via localhost/HTTPS.';
        return Promise.resolve(false);
    }

    addressField.value = 'Getting GPS location...';

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const rawLat = position.coords.latitude;
                const rawLng = position.coords.longitude;

                if (!CAVITE_BOUNDS.contains([rawLat, rawLng]) || !isPointInsideCavitePolygon(rawLat, rawLng)) {
                    addressField.value = 'Your GPS location is outside Cavite. Please select a location within Cavite.';
                    map.fitBounds(DISPLAY_BOUNDS);
                    restoreLastValidMarkerPosition();
                    resolve(false);
                    return;
                }

                map.setView([rawLat, rawLng], 14);
                trySetMarkerAndAddress(rawLat, rawLng).then(resolve);
            },
            (error) => {
                console.log('Geolocation error:', error.message);
                handleGeolocationError(error);
                resolve(false);
            },
            GEOLOCATION_OPTIONS
        );
    });
}

function addGpsControl() {
    const gpsControl = L.control({ position: 'topright' });

    gpsControl.onAdd = function () {
        const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
        const button = L.DomUtil.create('a', '', container);
        button.href = '#';
        button.title = 'Use My GPS';
        button.setAttribute('aria-label', 'Use My GPS');
        button.innerHTML = '📍';
        button.style.fontSize = '18px';
        button.style.textAlign = 'center';
        button.style.lineHeight = '30px';

        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.on(button, 'click', (event) => {
            L.DomEvent.preventDefault(event);
            requestUserGpsLocation();
        });

        return container;
    };

    gpsControl.addTo(map);
}

function initMap() {
    const caviteCenter = DISPLAY_BOUNDS.getCenter();
    const defaultLat = caviteCenter.lat;
    const defaultLng = caviteCenter.lng;
    
    map = L.map('mapContainer', {
        maxBounds: DISPLAY_BOUNDS,
        maxBoundsViscosity: 1.0
    });
    map.fitBounds(DISPLAY_BOUNDS);
    addGpsControl();
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    loadCaviteGeojson();
    
    // Add draggable marker (will be positioned by geolocation)
    marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
    lastValidLatLng = marker.getLatLng();
    
    // Update address when marker is dragged
    marker.on('dragend', () => {
        const latlng = marker.getLatLng();
        trySetMarkerAndAddress(latlng.lat, latlng.lng);
    });
    
    // Also allow clicking on map to move marker
    map.on('click', (e) => {
        trySetMarkerAndAddress(e.latlng.lat, e.latlng.lng);
    });
    
    // Auto-detect user's location on load; fallback to Cavite center.
    requestUserGpsLocation().then((ok) => {
        if (!ok) {
            trySetMarkerAndAddress(defaultLat, defaultLng);
        }
    });
}

function getAddressFromCoordinates(lat, lng) {
    // Use server-side proxy to avoid CORS issues (see: auth/api/reverse-geocode.php)
    const url = `auth/api/reverse-geocode.php?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&zoom=18&lang=en`;

    // Show temporary status while we lookup
    addressField.value = 'Looking up address...';

    // Abort previous request if any to avoid race conditions
    if (window._reverseGeocodeAbort) {
        try { window._reverseGeocodeAbort.abort(); } catch (e) {}
    }
    const createRequest = () => {
        const controller = new AbortController();
        window._reverseGeocodeAbort = controller;
        return controller;
    };

    const doFetch = (controller) => {
        return fetch(url, {
            signal: controller.signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-App-Client': 'PatientRegistration'
            }
        })
        .then(res => {
            if (!res.ok) return res.json().then(err => { throw new Error(err.error || ('HTTP ' + res.status)); });
            return res.json();
        })
        .then(data => {
            if (!data) throw new Error('Empty response');
            if (data.error) throw new Error(data.error);

            if (!isPointInCaviteFromAddress(data)) {
                return false;
            }

            // Build complete address from address components when available
            const addr = data.address || {};
            const parts = [];

            const streetName = addr.road || addr.residential || addr.pedestrian || addr.footway || '';
            const houseOrLot = addr.house_number || '';
            const streetLine = [houseOrLot, streetName].filter(Boolean).join(' ').trim();
            if (streetLine) parts.push(streetLine);

            const barangayArea = addr.suburb || addr.quarter || addr.neighbourhood || addr.village || addr.hamlet || '';
            if (barangayArea) parts.push(barangayArea);

            if (addr.city_district) parts.push(addr.city_district);
            if (addr.city) parts.push(addr.city);
            if (addr.town) parts.push(addr.town);
            if (addr.municipality) parts.push(addr.municipality);
            if (addr.province) parts.push(addr.province);
            if (addr.postcode) parts.push(addr.postcode);
            if (addr.country) parts.push(addr.country);

            const displayName = (data.display_name || '').trim();
            const fallbackAddress = parts.length > 0 ? parts.join(', ') : '';
            const address = fallbackAddress || displayName || 'Please enter your full Cavite address (house/lot number, street, barangay, city).';
            addressField.value = address;
            return true;
        });
    };

    // Try once, then retry once after short delay on failure
    return doFetch(createRequest()).catch(err => {
        if (err && err.name === 'AbortError') {
            return null;
        }
        console.error('Geocoding error (first attempt):', err);

        return new Promise((resolve) => {
            setTimeout(resolve, 700);
        }).then(() => {
            return doFetch(createRequest()).catch(err2 => {
                if (err2 && err2.name === 'AbortError') {
                    return null;
                }
                console.error('Geocoding error (retry):', err2);
                // Fallback to manual full address entry
                addressField.value = 'Unable to fetch exact address. Please enter your full Cavite address (house/lot number, street, barangay, city).';
                return false;
            });
        });
    });
}

// Initialize map when page loads
window.addEventListener('load', initMap);

// --- Multi-step navigation ---
const step1 = document.getElementById('step1');
const step2 = document.getElementById('step2');
const nextBtn = document.getElementById('nextBtn');
const prevBtn = document.getElementById('prevBtn');
const stepTabs = document.querySelectorAll('#stepTabs .nav-link');
const STEP1_FIELD_SELECTORS = {
    firstName: 'input[name="first_name"]',
    lastName: 'input[name="last_name"]',
    birthdate: 'input[name="birthdate"]',
    gender: 'select[name="gender"]',
    email: 'input[name="email"]',
    contact: 'input[name="contact_no"]',
    address: 'textarea[name="address"]'
};

function isOnStep1() {
    return !!step1 && !step1.classList.contains('d-none');
}

function setStepRequired(stepEl, enabled) {
    if (!stepEl) return;
    stepEl.querySelectorAll('[required]').forEach((el) => {
        if (enabled) {
            el.setAttribute('required', 'required');
        } else {
            el.removeAttribute('required');
        }
    });
}

// Step 2 is hidden by default, so disable its required fields.
setStepRequired(step2, false);

function getStep1Values() {
    const values = {};
    Object.entries(STEP1_FIELD_SELECTORS).forEach(([key, selector]) => {
        const element = document.querySelector(selector);
        values[key] = key === 'birthdate' || key === 'gender'
            ? (element ? element.value : '')
            : (element ? element.value.trim() : '');
    });
    return values;
}

function isStep1AddressValid(address) {
    return !!address && address !== 'Looking up address...' && !address.startsWith('Location detected');
}

function showStepError(message) {
    let alertDiv = document.getElementById('step1Error');
    if (!alertDiv) {
        alertDiv = document.createElement('div');
        alertDiv.id = 'step1Error';
        alertDiv.className = 'alert alert-danger mb-3';
        const cardBody = document.querySelector('.card-body');
        if (cardBody) {
            cardBody.insertBefore(alertDiv, cardBody.firstChild);
        }
    }
    if (!alertDiv) return;
    alertDiv.textContent = message;
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}

function clearStepError() {
    const old = document.getElementById('step1Error');
    if (old) old.remove();
}

function focusStep1Field(fieldKey) {
    const selector = STEP1_FIELD_SELECTORS[fieldKey];
    if (!selector) return;
    const element = document.querySelector(selector);
    if (element && typeof element.focus === 'function') {
        element.focus();
    }
}

// Validate Step 1 fields and toggle Next button
function validateStep1Fields() {
    const values = getStep1Values();
    const addressValid = isStep1AddressValid(values.address);
    const emailVerifiedFlag = (typeof verifiedEmail !== 'undefined' && verifiedEmail && verifiedEmail === values.email);

    const valid =
        !!values.firstName &&
        !!values.lastName &&
        !!values.birthdate &&
        !!values.gender &&
        !!values.email &&
        !!values.contact &&
        addressValid &&
        emailVerifiedFlag;

    if (nextBtn) {
        nextBtn.disabled = !valid;
    }

    return valid;
}

// Hook validation to inputs
Object.values(STEP1_FIELD_SELECTORS).forEach((selector) => {
    const el = document.querySelector(selector);
    if (el) {
        el.addEventListener('input', validateStep1Fields);
    }
});

if (nextBtn) {
    nextBtn.addEventListener('click', () => {
        const values = getStep1Values();

        const requiredChecks = [
            { key: 'firstName', message: 'Please enter First Name' },
            { key: 'lastName', message: 'Please enter Last Name' },
            { key: 'birthdate', message: 'Please enter Birthdate' },
            { key: 'gender', message: 'Please select Gender' },
            { key: 'email', message: 'Please enter Email' },
            { key: 'contact', message: 'Please enter Contact Number' }
        ];

        for (const check of requiredChecks) {
            if (!values[check.key]) {
                showStepError(check.message);
                focusStep1Field(check.key);
                return;
            }
        }

        if (!isStep1AddressValid(values.address)) {
            showStepError('Please set a valid address by dragging the pin or clicking the map');
            focusStep1Field('address');
            return;
        }

        if (typeof verifiedEmail === 'undefined' || !verifiedEmail || verifiedEmail !== values.email) {
            showStepError('Please verify your email before continuing');
            focusStep1Field('email');
            return;
        }

        clearStepError();
        setStepRequired(step2, true);
        step1.classList.add('d-none');
        step2.classList.remove('d-none');
        stepTabs[0].classList.remove('active');
        stepTabs[1].classList.remove('disabled');
        stepTabs[1].classList.add('active');
    });
}

if (prevBtn) {
    prevBtn.addEventListener('click', () => {
        setStepRequired(step2, false);
        step2.classList.add('d-none');
        step1.classList.remove('d-none');
        stepTabs[1].classList.remove('active');
        stepTabs[1].classList.add('disabled');
        stepTabs[0].classList.add('active');
    });
}

// --- ID validation ---
const idNumber = document.getElementById('idNumber');
const idType = document.getElementById('idType');
const idFile = document.getElementById('idFile');
const idFileBack = document.getElementById('idFileBack');
const idError = document.getElementById('idError');
const fileError = document.getElementById('fileError');
const fileNote = document.getElementById('fileNote');
const fileBackError = document.getElementById('fileBackError');
const fileBackNote = document.getElementById('fileBackNote');
const passwordInput = document.getElementById('passwordInput') || document.querySelector('input[name="password"]');
const confirmPasswordInput = document.getElementById('confirmPasswordInput') || document.querySelector('input[name="confirm_password"]');
const passwordError = document.getElementById('passwordError');
const confirmPasswordError = document.getElementById('confirmPasswordError');

function validatePasswordStrength() {
    if (!passwordInput) return true;
    const value = passwordInput.value || '';
    const upper = (value.match(/[A-Z]/g) || []).length;
    const lower = (value.match(/[a-z]/g) || []).length;
    const number = (value.match(/[0-9]/g) || []).length;
    const special = (value.match(/[^A-Za-z0-9]/g) || []).length;

    const valid = value.length >= 8 && upper >= 2 && lower >= 1 && number >= 1 && special >= 1;
    if (!valid && value.length > 0) {
        passwordInput.classList.add('is-invalid');
        if (passwordError) passwordError.classList.remove('d-none');
    } else {
        passwordInput.classList.remove('is-invalid');
        if (passwordError) passwordError.classList.add('d-none');
    }
    return valid;
}

function validatePasswordMatch() {
    if (!confirmPasswordInput) return true;
    const match = confirmPasswordInput.value === (passwordInput ? passwordInput.value : '');
    if (!match && confirmPasswordInput.value.length > 0) {
        confirmPasswordInput.classList.add('is-invalid');
        if (confirmPasswordError) confirmPasswordError.classList.remove('d-none');
    } else {
        confirmPasswordInput.classList.remove('is-invalid');
        if (confirmPasswordError) confirmPasswordError.classList.add('d-none');
    }
    return match;
}

if (passwordInput) {
    passwordInput.addEventListener('input', () => {
        validatePasswordStrength();
        validatePasswordMatch();
    });
}
if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', validatePasswordMatch);
}

if (idNumber && idError) {
    idNumber.addEventListener('input', () => {
        const shouldShow = idNumber.classList.contains('is-invalid') || !idError.classList.contains('d-none');
        validateIdNumber(shouldShow);
    });
}

function validateIdNumber(showError = false) {
    const type = idType.value;
    const rawValue = idNumber.value.trim();

    const normalizers = {
        passport: v => v.replace(/\s+/g, ''),
        drivers: v => v.replace(/[^A-Z0-9\-]/gi, ''),
        national: v => v.replace(/\D/g, ''),
        philhealth: v => v.replace(/\D/g, ''),
        sss: v => v.replace(/\D/g, '')
    };

    const normalize = normalizers[type] || (v => v);
    const normalized = normalize(rawValue);

    if (['national', 'philhealth', 'sss'].includes(type) && idNumber.value !== normalized) {
        idNumber.value = normalized;
    }

    const valid = !!type && normalized.length > 0;
    if (!valid && showError) {
        idError.classList.remove('d-none');
        idNumber.classList.add('is-invalid');
        idError.textContent = 'Please enter your ID number.';
    } else if (valid) {
        idError.classList.add('d-none');
        idNumber.classList.remove('is-invalid');
        idError.textContent = 'Please enter your ID number.';
    }

    return valid;
}

if (idType && idNumber && idError) {
    idType.addEventListener('change', () => {
        const shouldShow = idNumber.classList.contains('is-invalid') || !idError.classList.contains('d-none');
        validateIdNumber(shouldShow);
    });
}

function validateIdUploadFile(fileInput, errorEl, noteEl, options = {}) {
    if (!fileInput) return false;

    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    const maxSize = 5 * 1024 * 1024;
    const allowPdf = !!options.allowPdf;
    const runImageQualityCheck = !!options.runImageQualityCheck;

    const setError = (message) => {
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.remove('d-none');
        }
        if (noteEl) noteEl.classList.add('d-none');
        fileInput.classList.add('is-invalid');
        fileInput.dataset.qualityOk = '0';
    };

    const clearMessages = () => {
        if (errorEl) errorEl.classList.add('d-none');
        if (noteEl) noteEl.classList.add('d-none');
        fileInput.classList.remove('is-invalid');
        fileInput.dataset.qualityOk = '1';
    };

    const setNote = (message) => {
        if (noteEl) {
            noteEl.textContent = message;
            noteEl.classList.remove('d-none');
        }
    };

    if (!file) {
        fileInput.classList.add('is-invalid');
        fileInput.dataset.qualityOk = '0';
        return false;
    }

    const isPdf = file.type === 'application/pdf';
    const isImage = /^image\//.test(file.type || '');
    const isTypeAllowed = isImage || (allowPdf && isPdf);

    if (file.size > maxSize || !isTypeAllowed) {
        setError('Invalid file (JPG, PNG, PDF only / max 5MB)');
        fileInput.value = '';
        return false;
    }

    clearMessages();

    if (isPdf) {
        setNote('PDF uploaded. Please ensure the ID is clear.');
        return true;
    }

    if (!runImageQualityCheck) {
        return true;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            const targetW = 360;
            const scale = img.width / targetW;
            canvas.width = targetW;
            canvas.height = Math.round(img.height / scale);
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            const blurScore = (() => {
                const w = canvas.width;
                const h = canvas.height;
                const data = ctx.getImageData(0, 0, w, h).data;
                const gray = new Float32Array(w * h);
                for (let i = 0, j = 0; i < data.length; i += 4, j++) {
                    gray[j] = 0.2126 * data[i] + 0.7152 * data[i + 1] + 0.0722 * data[i + 2];
                }
                let sum = 0;
                let sumSq = 0;
                let count = 0;
                for (let y = 1; y < h - 1; y++) {
                    for (let x = 1; x < w - 1; x++) {
                        const idx = y * w + x;
                        const lap = (-4 * gray[idx]) + gray[idx - 1] + gray[idx + 1] + gray[idx - w] + gray[idx + w];
                        sum += lap;
                        sumSq += lap * lap;
                        count++;
                    }
                }
                const mean = sum / (count || 1);
                return (sumSq / (count || 1)) - (mean * mean);
            })();

            const brightness = getAverageBrightness(canvas);
            if (blurScore < 50 || brightness < 40) {
                setError('Image looks blurry or too dark. Please upload a clearer ID photo.');
                return;
            }

            clearMessages();
            if (typeof options.onImageOk === 'function') {
                options.onImageOk(e.target.result);
            }
        };
        img.onerror = () => setError('Could not read the ID image. Please upload a clearer photo.');
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);

    return true;
}

idFile.addEventListener('click', () => {
    // Ensure re-selecting the same file still triggers a change event.
    idFile.value = '';
    if (fileNote) fileNote.classList.add('d-none');
});

idFile.addEventListener('change', () => {
    validateIdUploadFile(idFile, fileError, fileNote, {
        allowPdf: true,
        runImageQualityCheck: true
    });
});

if (idFileBack) {
    idFileBack.addEventListener('click', () => {
        idFileBack.value = '';
        if (fileBackNote) fileBackNote.classList.add('d-none');
    });

    idFileBack.addEventListener('change', () => {
        validateIdUploadFile(idFileBack, fileBackError, fileBackNote, {
            allowPdf: true,
            runImageQualityCheck: false
        });
    });
}

// --- Form submission with fetch ---
const patientForm = document.getElementById('patientForm');
const postRegisterModalEl = document.getElementById('postRegisterModal');
const postRegisterMessage = document.getElementById('postRegisterMessage');
const proceedLoginBtn = document.getElementById('proceedLoginBtn');
const registerAnotherBtn = document.getElementById('registerAnotherBtn');
const submitBtn = patientForm ? patientForm.querySelector('button[type="submit"]') : null;
const submitBtnDefaultText = submitBtn ? submitBtn.textContent : 'Register Patient';
const submitBtnLoadingHtml = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Registering...';
let registrationCompleted = false;
const REGISTER_PATIENT_API = '/THESIS/LYINGIN/auth/api/register-patient.php';

function setOrCreateHiddenInput(id, name, value) {
    let el = document.getElementById(id);
    if (!el) {
        el = document.createElement('input');
        el.type = 'hidden';
        el.id = id;
        el.name = name;
        document.getElementById('patientForm').appendChild(el);
    }
    el.value = value;
}

function syncFaceMatchInputs(meta) {
    setOrCreateHiddenInput('hiddenFaceVerified', 'face_verified', '1');
    setOrCreateHiddenInput('hiddenFaceLive1', 'face_live_1', JSON.stringify(meta.live1));
    setOrCreateHiddenInput('hiddenFaceLive2', 'face_live_2', JSON.stringify(meta.live2));
    setOrCreateHiddenInput('hiddenFaceId', 'face_id', JSON.stringify(meta.id));
    setOrCreateHiddenInput('hiddenFaceD1', 'face_dist_1', String(meta.d1));
    setOrCreateHiddenInput('hiddenFaceD2', 'face_dist_2', String(meta.d2));
    setOrCreateHiddenInput('hiddenFaceDLive', 'face_live_dist', String(meta.dlive));
}

function passesSubmissionGuards() {
    if (!verifiedEmail) {
        showEmailStatus('Please verify your email first before registering', 'danger');
        return false;
    }

    if (!window.faceVerificationComplete) {
        showFaceVerificationStatus('Please complete face verification before registering', 'danger');
        return false;
    }

    return true;
}

function showPostRegisterModal(message) {
    if (postRegisterMessage) {
        postRegisterMessage.textContent = message || 'Your account has been created.';
    }
    if (!postRegisterModalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(postRegisterModalEl);
    modal.show();
}

if (proceedLoginBtn) {
    proceedLoginBtn.addEventListener('click', () => {
        window.location.href = 'index.html?showLogin=1';
    });
}

if (registerAnotherBtn) {
    registerAnotherBtn.addEventListener('click', () => {
        resetRegistrationFormState();
        if (postRegisterModalEl) {
            hideModalSafely(postRegisterModalEl);
        }
    });
}

if (postRegisterModalEl) {
    postRegisterModalEl.addEventListener('hide.bs.modal', () => {
        blurFocusedElementInside(postRegisterModalEl);
    });

    postRegisterModalEl.addEventListener('hidden.bs.modal', () => {
        cleanupModalBackdrops();
        if (emailInput && typeof emailInput.focus === 'function') {
            emailInput.focus();
        }
    });
}

patientForm.addEventListener('submit', e => {
    if (isOnStep1()) {
        e.preventDefault();
        return;
    }
    e.preventDefault();

    if (!passesSubmissionGuards()) {
        return;
    }

    if (registrationCompleted) {
        return;
    }

    const passOk = validatePasswordStrength();
    const matchOk = validatePasswordMatch();
    if (!passOk || !matchOk) {
        alert('Please fix the password requirements before submitting.');
        return;
    }

    const idNumberValid = validateIdNumber(true);
    const backFile = idFileBack && idFileBack.files ? idFileBack.files[0] : null;
    if (!idNumberValid || idFile.classList.contains('is-invalid') || !idType.value || !backFile || (idFileBack && idFileBack.classList.contains('is-invalid'))) {
        alert('Please provide valid front and back ID files before submitting.');
        return;
    }

    const formData = new FormData(patientForm);

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = submitBtnLoadingHtml;
    }

    // Use a root-absolute API path to avoid relative-path 404s when served from nested pages
    fetch(REGISTER_PATIENT_API, {
        method: 'POST',
        body: formData
    })
    .then(async res => {
        const text = await res.text();
        try {
            const data = JSON.parse(text);
            if (!res.ok) throw data;
            return data;
        } catch (e) {
            console.error('Non-JSON response from server:', text);
            throw { status: 'error', message: 'Server returned an unexpected response. Check console for details.', raw: text, statusCode: res.status };
        }
    })
    .then(data => {
        if (data.status === 'success') {
            registrationCompleted = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Registered';
            }
            showPostRegisterModal(data.message || 'Registration successful.');
        } else {
            alert(data.message || 'Registration failed. Please try again.');
        }
    })
    .catch(err => {
        console.error('Registration Error:', err);
        const message = (err && err.message) ? err.message : 'An unexpected error occurred. Check console for details.';

        if (/Face verification did not meet security thresholds/i.test(message)) {
            resetFaceVerificationForRetry('Face verification needs to be retried. Please verify your face again.');
        }

        alert(message);
        if (err && err.raw) console.log('Server response (raw):', err.raw);
    })
    .finally(() => {
        if (!registrationCompleted && submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtnDefaultText;
        }
    });
});

// ===== FACE VERIFICATION SYSTEM =====
window.faceVerificationComplete = false;
let capturedFaceImage = null;
let capturedFaceDescriptor = null;
let firstLiveDescriptor = null;
let secondLiveDescriptor = null;
let idPhotoDescriptor = null;
let firstLiveMeta = null;
let secondLiveMeta = null;
let idPhotoMeta = null;
let modelsLoaded = false;
let faceMatchMeta = null;

const useCameraBtn = document.getElementById('useCameraBtn');
const capturePhotoBtn = document.getElementById('capturePhotoBtn');
const faceVerifyBtn = document.getElementById('faceVerifyBtn');
const previewImage = document.getElementById('previewImage');
const previewArea = document.getElementById('previewArea');
const verificationResult = document.getElementById('verificationResult');
const confirmFaceBtn = document.getElementById('confirmFaceBtn');
const videoStream = document.getElementById('videoStream');
const videoContainer = document.getElementById('videoContainer');
const faceCaptureArea = document.getElementById('faceCaptureArea');
const faceModalCloseBtn = document.getElementById('faceModalCloseBtn');
const faceVerificationStatus = document.getElementById('faceVerificationStatus');
const faceVerificationModalEl = document.getElementById('faceVerificationModal');
let cameraStream = null;
let videoTrack = null;
let imageCapture = null;
let lightingInterval = null;
let torchOn = false;
let isStartingCamera = false;

// Load face-api models
async function loadFaceModels() {
    if (modelsLoaded) return;
    
    try {
        const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
        
        // Load both Tiny and SSD detection models if available (Tiny is faster)
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        try { await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL); } catch (e) { /* SSD not available, fallback to Tiny */ }
        await Promise.all([
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URL)
        ]);
        
        modelsLoaded = true;
        console.log('Face API models loaded successfully');
    } catch (error) {
        console.error('Error loading face models:', error);
        showVerificationStatus('Error loading face detection models. Please try again.', 'error');
    }
}

// Extract face descriptor and landmarks from image
async function extractFaceData(image) {
    const start = (typeof performance !== 'undefined') ? performance.now() : Date.now();
    try {
        // Try TinyFaceDetector first for speed, fallback to default detector if needed
        let detection = null;
        try {
            detection = await faceapi.detectSingleFace(image, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.4 })).withFaceLandmarks().withFaceDescriptor();
        } catch (e) {
            // Tiny may not be available; fallback
            detection = await faceapi.detectSingleFace(image).withFaceLandmarks().withFaceDescriptor();
        }

        const elapsed = Math.round(((typeof performance !== 'undefined') ? performance.now() : Date.now()) - start);

        if (!detection) {
            throw new Error('No face detected. Please center your face and try again.');
        }

        // detection.detection.score contains confidence for SSD/Tiny
        const score = detection.detection && typeof detection.detection.score === 'number' ? detection.detection.score : (detection.score || 0);
        window.lastDetectionTimeMs = elapsed;
        window.lastDetectionScore = score;

        // If confidence low, ask user to try again with better conditions
        const MIN_CONFIDENCE = 0.6;
        if (score < MIN_CONFIDENCE) {
            throw new Error('Face not detected clearly. Please improve lighting and try again.');
        }

        const imgW = image.naturalWidth || image.width || 1;
        const imgH = image.naturalHeight || image.height || 1;
        const box = detection.detection && detection.detection.box ? detection.detection.box : null;
        const boxW = box ? box.width : 0;
        const boxH = box ? box.height : 0;
        const faceAreaRatio = (boxW && boxH) ? ((boxW * boxH) / (imgW * imgH)) : 0;

        return {
            descriptor: detection.descriptor,
            landmarks: detection.landmarks,
            score,
            elapsed,
            faceAreaRatio
        };
    } catch (error) {
        throw new Error((error && error.message) ? error.message : 'Unable to detect face clearly. Please try again.');
    }
}

// Calculate Euclidean distance between two descriptors
function calculateDistance(descriptor1, descriptor2) {
    if (!descriptor1 || !descriptor2) return Infinity;
    let sum = 0;
    for (let i = 0; i < descriptor1.length; i++) {
        sum += Math.pow(descriptor1[i] - descriptor2[i], 2);
    }
    return Math.sqrt(sum);
}

// Show verification status
function showVerificationStatus(message, type = 'info') {
    const statusDiv = document.createElement('div');
    statusDiv.className = `verification-status status-${type}`;
    statusDiv.textContent = message;
    verificationResult.innerHTML = '';
    verificationResult.appendChild(statusDiv);
}

// Compute average brightness from a canvas (center region)
function getAverageBrightness(canvas) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const w = Math.max(10, Math.round(canvas.width * 0.15));
    const h = Math.max(10, Math.round(canvas.height * 0.15));
    const sx = Math.round((canvas.width - w) / 2);
    const sy = Math.round((canvas.height - h) / 2);
    try {
        const imageData = ctx.getImageData(sx, sy, w, h).data;
        let total = 0, count = 0;
        for (let i = 0; i < imageData.length; i += 4) {
            const r = imageData[i], g = imageData[i+1], b = imageData[i+2];
            const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
            total += lum; count++;
        }
        return total / count;
    } catch (e) {
        return 255; // can't sample yet
    }
}

// Periodically check lighting and disable capture if too dark
function checkLighting() {
    if (!videoStream || videoStream.readyState < 2) return;
    const temp = document.createElement('canvas');
    const vw = videoStream.videoWidth || 320;
    const vh = videoStream.videoHeight || 240;
    const w = Math.max(30, Math.round(vw * 0.2));
    const h = Math.max(30, Math.round(vh * 0.2));
    temp.width = w; temp.height = h;
    const ctx = temp.getContext('2d', { willReadFrequently: true });
    try {
        ctx.drawImage(videoStream, Math.round((vw-w)/2), Math.round((vh-h)/2), w, h, 0, 0, w, h);
        const avg = getAverageBrightness(temp);
        const hint = document.getElementById('lightingHint');
        if (avg < 50) {
            hint.textContent = 'Low light detected. Please improve lighting or enable flash.';
            capturePhotoBtn.disabled = true;
        } else {
            hint.textContent = 'Lighting looks good.';
            capturePhotoBtn.disabled = false;
        }
    } catch (e) {}
}

// Toggle device torch (if supported)
async function toggleTorch() {
    if (!videoTrack || !videoTrack.applyConstraints) return;
    torchOn = !torchOn;
    try {
        await videoTrack.applyConstraints({ advanced: [{ torch: torchOn }] });
        const btn = document.getElementById('torchToggleBtn');
        btn.innerHTML = torchOn ? '<i class="bi bi-lightbulb-fill"></i> Flash On' : '<i class="bi bi-lightbulb"></i> Turn On Flash';
    } catch (err) {
        console.warn('Torch toggle failed:', err);
    }
}

// Simple canvas enhancement: adjust brightness towards a target and add slight contrast
function enhanceCanvas(canvas) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imgData.data;
    let sum = 0;
    for (let i = 0; i < data.length; i += 4) {
        sum += (0.2126 * data[i] + 0.7152 * data[i+1] + 0.0722 * data[i+2]);
    }
    const avg = sum / (data.length / 4 || 1);
    const target = 110;
    const adjust = target / (avg || 1);
    const contrast = 1.05;

    for (let i = 0; i < data.length; i += 4) {
        data[i] = Math.min(255, Math.max(0, ((data[i] - 128) * contrast + 128) * adjust));
        data[i+1] = Math.min(255, Math.max(0, ((data[i+1] - 128) * contrast + 128) * adjust));
        data[i+2] = Math.min(255, Math.max(0, ((data[i+2] - 128) * contrast + 128) * adjust));
    }
    ctx.putImageData(imgData, 0, 0);
}

// Show face verification status
function showFaceVerificationStatus(message, type = 'info') {
    const statusDiv = document.getElementById('faceVerificationStatus');
    statusDiv.innerHTML = `<div class="alert alert-${type} mb-0 small">${message}</div>`;
}


// Update face verification UI
function updateFaceVerificationUI() {
    const statusDiv = document.getElementById('faceVerificationStatus');
    
    if (window.faceVerificationComplete) {
        statusDiv.innerHTML = '<div class="verification-status status-success"><i class="bi bi-check-circle"></i> Face verification complete!</div>';
        if (faceVerifyBtn) {
            faceVerifyBtn.disabled = true;
            faceVerifyBtn.classList.add('disabled');
        }
        if (useCameraBtn) {
            useCameraBtn.disabled = true;
            useCameraBtn.style.display = 'none';
        }
        if (faceCaptureArea) faceCaptureArea.style.display = 'none';
        if (videoContainer) videoContainer.style.display = 'none';
        if (capturePhotoBtn) {
            capturePhotoBtn.style.display = 'none';
            capturePhotoBtn.disabled = true;
        }
        if (confirmFaceBtn) {
            confirmFaceBtn.style.display = 'none';
            confirmFaceBtn.disabled = true;
        }
        const torchBtn = document.getElementById('torchToggleBtn');
        if (torchBtn) torchBtn.style.display = 'none';
        if (previewArea && previewImage && previewImage.src) {
            previewArea.style.display = 'block';
        }
        if (faceModalCloseBtn) faceModalCloseBtn.style.display = 'inline-block';
    } else {
        statusDiv.innerHTML = '';
        if (faceVerifyBtn) {
            faceVerifyBtn.disabled = false;
            faceVerifyBtn.classList.remove('disabled');
        }
        if (useCameraBtn) {
            useCameraBtn.disabled = false;
            useCameraBtn.style.display = 'inline-block';
        }
        if (faceCaptureArea) faceCaptureArea.style.display = 'block';
        if (previewArea) previewArea.style.display = 'none';
        if (faceModalCloseBtn) faceModalCloseBtn.style.display = 'inline-block';
    }
}

// Camera flow
if (useCameraBtn) {
    useCameraBtn.addEventListener('click', startCamera);
}

function canStartFaceVerification(showMessage = true) {
    const file = idFile && idFile.files ? idFile.files[0] : null;

    if (!file) {
        if (showMessage) {
            showFaceVerificationStatus('Please upload your valid ID first before face verification.', 'danger');
        }
        return false;
    }

    if (idFile.classList.contains('is-invalid') || idFile.dataset.qualityOk === '0') {
        if (showMessage) {
            showFaceVerificationStatus('Please upload a clear and valid ID image first.', 'danger');
        }
        return false;
    }

    if (file.type === 'application/pdf') {
        if (showMessage) {
            showFaceVerificationStatus('Please upload an ID image (JPG/PNG) for face verification.', 'danger');
        }
        return false;
    }

    return true;
}

if (faceVerifyBtn) {
    faceVerifyBtn.addEventListener('click', (e) => {
        if (!canStartFaceVerification(true)) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
}

async function startCamera() {
    if (isStartingCamera) return;

    if (cameraStream && videoStream && videoStream.srcObject) {
        videoContainer.style.display = 'block';
        capturePhotoBtn.style.display = 'inline-block';
        capturePhotoBtn.disabled = false;
        showVerificationStatus('Camera ready. Position your face and press Capture.', 'info');
        return;
    }

    isStartingCamera = true;
    try {
        await loadFaceModels();
        videoContainer.style.display = 'block';
        previewArea.style.display = 'none';
        showVerificationStatus('Starting camera...', 'info');

        // Prefer a good resolution for face detection; the browser will use supported constraints
        const constraints = { video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } }, audio: false };
        cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
        videoStream.srcObject = cameraStream;
        videoStream.play();

        // Ensure we only enable capture after metadata is available
        capturePhotoBtn.disabled = true;
        const onReady = () => {
            capturePhotoBtn.disabled = false;
            showVerificationStatus('Camera ready. Position your face and press Capture.', 'info');
            videoStream.removeEventListener('loadedmetadata', onReady);
        };
        videoStream.addEventListener('loadedmetadata', onReady);

        // Setup track and ImageCapture if available
        videoTrack = cameraStream.getVideoTracks()[0];
        try { imageCapture = (typeof ImageCapture !== 'undefined' && videoTrack) ? new ImageCapture(videoTrack) : null; } catch (e) { imageCapture = null; }

        // Torch support
        const capabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : {};
        const torchBtn = document.getElementById('torchToggleBtn');
        if (capabilities.torch) {
            torchBtn.style.display = 'inline-block';
            torchBtn.onclick = toggleTorch;
        } else {
            torchBtn.style.display = 'none';
        }

        // Start periodic lighting check to advise user
        lightingInterval = setInterval(checkLighting, 600);

        // Ensure capture button is visible and enabled
        capturePhotoBtn.style.display = 'inline-block';
    } catch (error) {
        console.error('Camera error:', error);
        showVerificationStatus('Unable to access camera. Please allow camera access or use a supported device.', 'error');
    } finally {
        isStartingCamera = false;
    }
} 

function stopCamera() {
    if (lightingInterval) { clearInterval(lightingInterval); lightingInterval = null; }
    if (cameraStream) {
        cameraStream.getTracks().forEach(t => t.stop());
        cameraStream = null;
    }
    // turn off torch if active
    if (torchOn && videoTrack && videoTrack.applyConstraints) {
        try { videoTrack.applyConstraints({ advanced: [{ torch: false }] }); } catch (e) {}
        torchOn = false;
    }
    imageCapture = null;
    videoTrack = null;
    videoStream.srcObject = null;
    videoContainer.style.display = 'none';
    capturePhotoBtn.style.display = 'none';
    const torchBtn = document.getElementById('torchToggleBtn'); if (torchBtn) torchBtn.style.display = 'none';

    // Ensure spinner hidden
    hideDetectionSpinner();
}

function showDetectionSpinner() {
    const s = document.getElementById('detectionSpinner'); if (s) s.classList.remove('d-none');
}
function hideDetectionSpinner() {
    const s = document.getElementById('detectionSpinner'); if (s) s.classList.add('d-none');
} 

if (capturePhotoBtn) {
capturePhotoBtn.addEventListener('click', async () => {
    try {
        // Freeze and capture frame
        showVerificationStatus('Capturing photo...', 'info');
        capturePhotoBtn.disabled = true;

        if (!videoStream || videoStream.readyState < 2 || !videoStream.videoWidth || !videoStream.videoHeight) {
            showVerificationStatus('Camera not ready yet. Please wait a moment and try again.', 'error');
            capturePhotoBtn.disabled = false;
            return;
        }

        const canvas = document.getElementById('canvas');
        // Downscale for faster detection (target width)
        const targetWidth = 320;
        const videoWidth = videoStream.videoWidth || 640;
        const videoHeight = videoStream.videoHeight || 480;
        const scale = videoWidth / targetWidth;
        canvas.width = targetWidth;
        canvas.height = Math.round(videoHeight / scale);
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(videoStream, 0, 0, canvas.width, canvas.height);

        // Quick brightness check and enhancement
        const avg = getAverageBrightness(canvas);
        if (avg < 50) {
            showVerificationStatus('Lighting too low. Please increase lighting or enable flash.', 'error');
            capturePhotoBtn.disabled = false;
            previewArea.style.display = 'none';
            videoContainer.style.display = 'block';
            return;
        }

        // Apply simple enhancement to improve detection in suboptimal lighting
        enhanceCanvas(canvas);

        const dataUrl = canvas.toDataURL('image/jpeg');
        // Store preview but keep it hidden until verification succeeds
        previewImage.src = dataUrl;
        previewArea.style.display = 'none';
        videoContainer.style.display = 'none';

        // Run face detection on a smaller frame to speed up processing
        const img = new Image();
        img.onload = async () => {
            try {
                showVerificationStatus('Detecting face...', 'info');
                showDetectionSpinner();
                const faceData = await extractFaceData(img);
                hideDetectionSpinner();
                capturedFaceDescriptor = faceData.descriptor;
                capturedFaceImage = img;

                // Show detection metrics
                showVerificationStatus('Face captured successfully.', 'success');

                if (!firstLiveDescriptor) {
                    firstLiveDescriptor = capturedFaceDescriptor;
                    firstLiveMeta = faceData;
                    showVerificationStatus('First capture complete. Please capture a second live photo.', 'info');
                    confirmFaceBtn.style.display = 'none';
                    previewArea.style.display = 'none';
                    videoContainer.style.display = 'block';
                    capturePhotoBtn.disabled = false;
                    return;
                }

                secondLiveDescriptor = capturedFaceDescriptor;
                secondLiveMeta = faceData;
                // Two captures complete; proceed to face comparison

                // Compare with ID photo if available
                if (idPhotoDescriptor) {
                    compareFaces();
                } else {
                    confirmFaceBtn.style.display = 'none';
                }

                capturePhotoBtn.disabled = false;
            } catch (err) {
                hideDetectionSpinner();
                showVerificationStatus(err.message, 'error');
                // allow retry: re-enable capture and restart camera
                capturePhotoBtn.disabled = false;
                previewArea.style.display = 'none';
                videoContainer.style.display = 'block';
            }
        };
        img.src = dataUrl;
    } catch (err) {
        console.error('Capture error:', err);
        showVerificationStatus('Error capturing photo. Please try again.', 'error');
        capturePhotoBtn.disabled = false;
    }
});
}

// Stop camera and restore focus when face modal closes
if (faceVerificationModalEl) {
    faceVerificationModalEl.addEventListener('hide.bs.modal', () => {
        blurFocusedElementInside(faceVerificationModalEl);
    });

    faceVerificationModalEl.addEventListener('hidden.bs.modal', () => {
        stopCamera();
        cleanupModalBackdrops();
        if (faceVerifyBtn && typeof faceVerifyBtn.focus === 'function') {
            faceVerifyBtn.focus();
        }
    });
}

// Handle ID photo for comparison
idFile.addEventListener('change', async (event) => {
    const file = event.target.files[0];
    
    if (!file) return;
    if (idFile.dataset.qualityOk === '0') {
        idPhotoDescriptor = null;
        return;
    }
    
    // If it's a PDF, we can't extract face from it
    if (file.type === 'application/pdf') {
        console.log('PDF uploaded - Face comparison will be done manually');
        idPhotoDescriptor = null;
        return;
    }
    
    try {
        await loadFaceModels();
        
        const reader = new FileReader();
        reader.onload = async (e) => {
            const img = new Image();
            img.onload = async () => {
                try {
                    const faceData = await extractFaceData(img);
                    idPhotoDescriptor = faceData.descriptor;
                    idPhotoMeta = faceData;
                    console.log('ID face extracted successfully');
                    
                    // If we already have a captured face, compare them
                    if (capturedFaceDescriptor) {
                        compareFaces();
                    }
                } catch (error) {
                    console.log('Could not extract face from ID photo:', error.message);
                    idPhotoDescriptor = null;
                    if (fileError) {
                        fileError.textContent = 'Could not detect a face on the ID. Please upload a clearer ID with your face visible.';
                        fileError.classList.remove('d-none');
                        idFile.classList.add('is-invalid');
                    }
                }
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    } catch (error) {
        console.error('Error processing ID photo:', error);
    }
});

// Compare captured face with ID photo using Euclidean distance (lower is better)
function compareFaces() {
    if (!firstLiveDescriptor || !secondLiveDescriptor || !idPhotoDescriptor) {
        showVerificationStatus('Both face photo and ID are required for verification.', 'error');
        confirmFaceBtn.style.display = 'none';
        return;
    }

    const minLiveFaceArea = 0.08;
    const minIdFaceArea = 0.05;
    if (!firstLiveMeta || !secondLiveMeta || !idPhotoMeta) {
        showVerificationStatus('Face data incomplete. Please recapture your photo.', 'error');
        confirmFaceBtn.style.display = 'none';
        return;
    }
    if (firstLiveMeta.faceAreaRatio < minLiveFaceArea || secondLiveMeta.faceAreaRatio < minLiveFaceArea) {
        showVerificationStatus('Face too small in the frame. Move closer and try again.', 'error');
        confirmFaceBtn.style.display = 'none';
        return;
    }
    if (idPhotoMeta.faceAreaRatio < minIdFaceArea) {
        showVerificationStatus('ID face is too small or unclear. Please upload a clearer ID.', 'error');
        confirmFaceBtn.style.display = 'none';
        return;
    }

    const thresholdDistance = 0.58; // balanced threshold (lower is stricter)
    const selfMatchThreshold = 0.50; // live captures should be similar
    const distance1 = calculateDistance(firstLiveDescriptor, idPhotoDescriptor);
    const distance2 = calculateDistance(secondLiveDescriptor, idPhotoDescriptor);
    const liveDistance = calculateDistance(firstLiveDescriptor, secondLiveDescriptor);
    const isMatching = distance1 <= thresholdDistance && distance2 <= thresholdDistance && liveDistance <= selfMatchThreshold;

    // Debugging info
    console.log('==== FACE MATCH DEBUG ====');
    console.log('Live #1 vs ID:', distance1, '(threshold:', thresholdDistance, ') =', distance1 <= thresholdDistance ? 'PASS' : 'FAIL');
    console.log('Live #2 vs ID:', distance2, '(threshold:', thresholdDistance, ') =', distance2 <= thresholdDistance ? 'PASS' : 'FAIL');
    console.log('Live #1 vs Live #2:', liveDistance, '(threshold:', selfMatchThreshold, ') =', liveDistance <= selfMatchThreshold ? 'PASS' : 'FAIL');
    console.log('Face area ratios - Live1:', firstLiveMeta.faceAreaRatio.toFixed(4), 'Live2:', secondLiveMeta.faceAreaRatio.toFixed(4), 'ID:', idPhotoMeta.faceAreaRatio.toFixed(4));
    console.log('Overall match:', isMatching ? 'VERIFIED ✓' : 'NOT VERIFIED ✗');
    console.log('==========================');

    if (isMatching) {
        faceMatchMeta = {
            live1: Array.from(firstLiveDescriptor),
            live2: Array.from(secondLiveDescriptor),
            id: Array.from(idPhotoDescriptor),
            d1: distance1,
            d2: distance2,
            dlive: liveDistance
        };
        
        // Auto-confirm verification
        window.faceVerificationComplete = true;
        syncFaceMatchInputs(faceMatchMeta);
        
        showVerificationStatus('✓ Verified', 'success');
        
        // Show preview and update UI
        if (previewArea && previewImage && previewImage.src) {
            previewArea.style.display = 'block';
        }
        if (videoContainer) videoContainer.style.display = 'none';
        updateFaceVerificationUI();
    } else {
        faceMatchMeta = null;
        showVerificationStatus('✗ Not verified. Please try again.', 'error');
        if (previewArea) previewArea.style.display = 'none';
        if (videoContainer) videoContainer.style.display = 'block';
        capturePhotoBtn.disabled = false;
    }
}

// Confirm face verification
if (confirmFaceBtn) {
confirmFaceBtn.addEventListener('click', () => {
    if (!faceMatchMeta) {
        showVerificationStatus('Please complete face verification before confirming.', 'error');
        return;
    }
    window.faceVerificationComplete = true;
    updateFaceVerificationUI();

    if (videoContainer) videoContainer.style.display = 'none';
    if (previewArea && previewImage && previewImage.src) {
        previewArea.style.display = 'block';
    }

    syncFaceMatchInputs(faceMatchMeta);

    // Show success message
    hideModalSafely(faceVerificationModalEl);

    alert('Face verification complete! You can now submit your registration.');
});
}

// Initialize face models and start camera when modal opens
if (faceVerificationModalEl) faceVerificationModalEl.addEventListener('show.bs.modal', async (event) => {
    if (window.faceVerificationComplete) {
        if (verificationResult) verificationResult.innerHTML = '';
        if (videoContainer) videoContainer.style.display = 'none';
        if (previewArea && previewImage && previewImage.src) {
            previewArea.style.display = 'block';
        }
        updateFaceVerificationUI();
        return;
    }
    if (!canStartFaceVerification(true)) {
        if (event) event.preventDefault();
        return;
    }
    await loadFaceModels();
    // auto-start camera for convenience
    if (confirmFaceBtn) confirmFaceBtn.style.display = 'none';
    if (verificationResult) verificationResult.innerHTML = '';
    if (previewArea) previewArea.style.display = 'none';
    firstLiveDescriptor = null;
    secondLiveDescriptor = null;
    faceMatchMeta = null;
    startCamera();
});

// ========== OTP VERIFICATION SYSTEM ==========
let verifiedEmail = null;

const emailInput = document.getElementById('emailInput');
const verifyEmailBtn = document.getElementById('verifyEmailBtn');
const emailVerificationStatus = document.getElementById('emailVerificationStatus');

if (verifyEmailBtn) {
    verifyEmailBtn.classList.add('d-none');
}

const OTP_RESEND_COOLDOWN_MS = 60000;
let otpSendInProgress = false;
let lastOtpEmail = '';
let lastOtpSentAt = 0;
let lastOtpNoticeAt = 0;

function updateVerifyEmailButton() {
    if (!verifyEmailBtn || !emailInput) return;
    const email = emailInput.value.trim();
    const isVerified = !!(verifiedEmail && verifiedEmail === email);

    if (isVerified) {
        verifyEmailBtn.classList.add('d-none');
        verifyEmailBtn.disabled = true;
        return;
    }

    if (isValidEmail(email)) {
        verifyEmailBtn.classList.remove('d-none');
        verifyEmailBtn.disabled = false;
        verifyEmailBtn.textContent = 'Send Code';
    } else {
        verifyEmailBtn.classList.add('d-none');
        verifyEmailBtn.disabled = true;
    }
}

function showOtpModalMessage(message, type = 'info', openModal = true) {
    const otpModalEl = document.getElementById('otpVerificationModal');
    const otpErrorMsg = document.getElementById('otpErrorMsg');
    if (otpErrorMsg) {
        otpErrorMsg.innerHTML = `<div class="alert alert-${type} mb-0 small">${message}</div>`;
        setTimeout(() => {
            if (otpErrorMsg) otpErrorMsg.innerHTML = '';
        }, 4000);
    }
    if (openModal && otpModalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(otpModalEl);
        modal.show();
    }
}

function cleanupModalBackdrops() {
    const anyOpen = document.querySelector('.modal.show');
    if (anyOpen) return;
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
}

function blurFocusedElementInside(modalEl) {
    if (!modalEl) return;
    const activeEl = document.activeElement;
    if (activeEl && modalEl.contains(activeEl) && typeof activeEl.blur === 'function') {
        activeEl.blur();
    }
}

function hideModalSafely(modalEl) {
    if (!modalEl) return;
    blurFocusedElementInside(modalEl);
    const modal = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.hide();
}

function removeHiddenFaceInputs() {
    const hiddenFaceIds = [
        'hiddenFaceVerified',
        'hiddenFaceLive1',
        'hiddenFaceLive2',
        'hiddenFaceId',
        'hiddenFaceD1',
        'hiddenFaceD2',
        'hiddenFaceDLive'
    ];
    hiddenFaceIds.forEach((id) => {
        const node = document.getElementById(id);
        if (node && node.parentNode) node.parentNode.removeChild(node);
    });
}

function resetFaceVerificationForRetry(statusMessage = '') {
    stopCamera();
    window.faceVerificationComplete = false;
    faceMatchMeta = null;
    capturedFaceImage = null;
    capturedFaceDescriptor = null;
    firstLiveDescriptor = null;
    secondLiveDescriptor = null;
    idPhotoDescriptor = null;
    firstLiveMeta = null;
    secondLiveMeta = null;
    idPhotoMeta = null;
    if (previewImage) previewImage.src = '';
    if (previewArea) previewArea.style.display = 'none';
    if (verificationResult) verificationResult.innerHTML = '';
    removeHiddenFaceInputs();
    updateFaceVerificationUI();
    if (statusMessage) {
        showFaceVerificationStatus(statusMessage, 'warning');
    }
}

function resetRegistrationFormState() {
    if (patientForm) patientForm.reset();
    registrationCompleted = false;

    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = submitBtnDefaultText;
    }

    // Stop camera and clear face state
    stopCamera();
    window.faceVerificationComplete = false;
    updateFaceVerificationUI();
    capturedFaceImage = null;
    capturedFaceDescriptor = null;
    firstLiveDescriptor = null;
    secondLiveDescriptor = null;
    faceMatchMeta = null;
    idPhotoDescriptor = null;
    if (previewImage) previewImage.src = '';
    if (previewArea) previewArea.style.display = 'none';
    if (verificationResult) verificationResult.innerHTML = '';

    // Clear email/OTP state
    verifiedEmail = null;
    emailInput.readOnly = false;
    emailInput.value = '';
    lastOtpEmail = '';
    lastOtpSentAt = 0;
    lastOtpNoticeAt = 0;
    emailVerificationStatus.innerHTML = '';
    updateVerifyEmailButton();
    const hiddenEmail = document.getElementById('hiddenVerifiedEmail');
    if (hiddenEmail && hiddenEmail.parentNode) hiddenEmail.parentNode.removeChild(hiddenEmail);
    removeHiddenFaceInputs();

    const otpInput = document.getElementById('otpInput');
    const otpErrorMsg = document.getElementById('otpErrorMsg');
    if (otpInput) { otpInput.value = ''; otpInput.disabled = false; }
    if (otpErrorMsg) otpErrorMsg.innerHTML = '';

    // Clear field-specific errors and notes
    if (idFile) {
        idFile.value = '';
        idFile.classList.remove('is-invalid');
        idFile.dataset.qualityOk = '0';
    }
    if (idFileBack) {
        idFileBack.value = '';
        idFileBack.classList.remove('is-invalid');
        idFileBack.dataset.qualityOk = '0';
    }
    if (fileError) fileError.classList.add('d-none');
    if (fileNote) fileNote.classList.add('d-none');
    if (fileBackError) fileBackError.classList.add('d-none');
    if (fileBackNote) fileBackNote.classList.add('d-none');
    if (idNumber) idNumber.classList.remove('is-invalid');
    if (idError) idError.classList.add('d-none');
    if (passwordError) passwordError.classList.add('d-none');
    if (confirmPasswordError) confirmPasswordError.classList.add('d-none');
    if (addressField) addressField.value = '';

    // Return to Step 1 and reset step UI
    step2.classList.add('d-none');
    step1.classList.remove('d-none');
    stepTabs[1].classList.add('disabled');
    stepTabs[1].classList.remove('active');
    stepTabs[0].classList.add('active');

    validateStep1Fields();

    cleanupModalBackdrops();
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function showInvalidEmailToast() {
    const toastEl = document.getElementById('invalidEmailToast');
    if (!toastEl || !window.bootstrap || !bootstrap.Toast) return;
    const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2500 });
    toast.show();
}

const otpSendCandidates = [
    'auth/api/send-otp.php',
    '/THESIS/LYINGIN/auth/api/send-otp.php'
];

const otpVerifyCandidates = [
    'auth/api/verify-otp.php',
    '/THESIS/LYINGIN/auth/api/verify-otp.php'
];

async function tryPostCandidates(candidateUrls, body, logLabel) {
    let lastError = null;
    for (const url of candidateUrls) {
        try {
            console.log(`Attempting ${logLabel} to:`, url);
            const resp = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });
            const text = await resp.text();
            // If server returned HTML (404 page etc.) JSON.parse will throw and we'll treat it as failure
            let data = null;
            try { data = JSON.parse(text); } catch (e) { throw { url, status: resp.status, raw: text } }
            if (!resp.ok) throw { url, status: resp.status, data };
            return data;
        } catch (err) {
            console.warn('OTP attempt failed for', url, err);
            lastError = err;
        }
    }
    throw lastError || new Error('All candidate endpoints failed');
}

async function sendOtpForEmail(email, opts = {}) {
    if (otpSendInProgress) return;
    if (!isValidEmail(email)) {
        showInvalidEmailToast();
        return;
    }
    if (verifiedEmail && verifiedEmail === email) return;

    const now = Date.now();
    if (!opts.force && lastOtpEmail === email && (now - lastOtpSentAt) < OTP_RESEND_COOLDOWN_MS) {
        if ((now - lastOtpNoticeAt) > OTP_RESEND_COOLDOWN_MS) {
            showOtpModalMessage('OTP already sent. Please check your email.', 'info');
            lastOtpNoticeAt = now;
        }
        showOTPModal(email);
        return;
    }

    otpSendInProgress = true;
    lastOtpEmail = email;

    if (verifyEmailBtn) {
        verifyEmailBtn.disabled = true;
    }
    showOtpModalMessage('Sending OTP...', 'info');

    try {
        const data = await tryPostCandidates(otpSendCandidates, 'email=' + encodeURIComponent(email) + '&user_type=patient', 'OTP send');
        if (data.status === 'success') {
            lastOtpSentAt = Date.now();
            showOTPModal(email);
            showOtpModalMessage('OTP sent! Check your email.', 'success', false);
        } else {
            showOtpModalMessage(data.message || 'Failed to send OTP', 'danger');
        }
    } catch (error) {
        console.error('Error sending OTP (all candidates failed):', error);
        // If the server sent back raw HTML for a 404, include a helpful message
        if (error && error.raw) console.log('Server response (raw):', error.raw);
        showOtpModalMessage('Error sending OTP. Server endpoint not found or returned an error. Check console.', 'danger');
    } finally {
        otpSendInProgress = false;
        if (verifyEmailBtn) {
            verifyEmailBtn.disabled = false;
        }
        updateVerifyEmailButton();
    }
}

if (emailInput) {
    emailInput.addEventListener('input', () => {
        emailVerificationStatus.innerHTML = '';
        updateVerifyEmailButton();
    });

    emailInput.addEventListener('blur', () => {
        const email = emailInput.value.trim();
        if (email && !isValidEmail(email)) {
            showInvalidEmailToast();
        }
    });
}

if (verifyEmailBtn) {
    verifyEmailBtn.addEventListener('click', () => {
        const email = emailInput ? emailInput.value.trim() : '';
        if (!isValidEmail(email)) {
            showInvalidEmailToast();
            return;
        }
        sendOtpForEmail(email);
    });
}

updateVerifyEmailButton();

function showEmailStatus(message, type) {
    showOtpModalMessage(message, type);
    emailVerificationStatus.innerHTML = '';
}

function showOTPModal(email) {
    const otpModalEl = document.getElementById('otpVerificationModal');
    if (!otpModalEl) return;
    const otpModal = bootstrap.Modal.getOrCreateInstance(otpModalEl);
    
    const otpInput = document.getElementById('otpInput');
    const otpErrorMsg = document.getElementById('otpErrorMsg');
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    let otpVerifying = false;

    const setOtpError = (message) => {
        otpErrorMsg.innerHTML = `<div class="alert alert-danger mb-0 small">${message}</div>`;
    };
    
    otpInput.value = '';
    otpErrorMsg.innerHTML = '';
    otpInput.disabled = false;
    
    const verifyOtp = async () => {
        const otp = otpInput.value.trim();
        
        if (!otp || otp.length !== 6) {
            setOtpError('Please enter a 6-digit OTP');
            return;
        }
        
        if (otpVerifying) return;
        otpVerifying = true;
        otpInput.disabled = true;
        
        try {
            const data = await tryPostCandidates(
                otpVerifyCandidates,
                'email=' + encodeURIComponent(email) + '&otp=' + encodeURIComponent(otp),
                'OTP verify'
            );
            
            if (data.status === 'success') {
                verifiedEmail = email;
                // Don't disable the input (disabled inputs are not submitted). Use readOnly so the value is still posted.
                emailInput.readOnly = true;
                // Also keep a hidden input as a fallback to ensure the email is always submitted
                let hiddenEmail = document.getElementById('hiddenVerifiedEmail');
                if (!hiddenEmail) {
                    hiddenEmail = document.createElement('input');
                    hiddenEmail.type = 'hidden';
                    hiddenEmail.id = 'hiddenVerifiedEmail';
                    hiddenEmail.name = 'email';
                    document.getElementById('patientForm').appendChild(hiddenEmail);
                }
                hiddenEmail.value = email;

                showOtpModalMessage('Email verified successfully!', 'success', false);
                setTimeout(() => {
                    try {
                        const activeEl = document.activeElement;
                        if (activeEl && otpModalEl.contains(activeEl) && typeof activeEl.blur === 'function') {
                            activeEl.blur();
                        }
                        otpModal.hide();
                    } catch (e) {}
                }, 600);
                updateVerifyEmailButton();
                
                // Re-run validation to enable Next when all fields are valid
                validateStep1Fields();
            } else {
                setOtpError(data.message || 'Invalid OTP');
                otpInput.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            setOtpError('Error verifying OTP. Please try again.');
            otpInput.disabled = false;
        } finally {
            otpVerifying = false;
        }
    };

    otpInput.oninput = () => {
        const otp = otpInput.value.trim();
        if (otp.length === 6 && !otpVerifying) {
            verifyOtp();
        }
    };

    if (resendOtpBtn) {
        resendOtpBtn.onclick = () => {
            sendOtpForEmail(email, { force: true });
        };
    }
    
    otpModalEl.addEventListener('hide.bs.modal', () => {
        const activeEl = document.activeElement;
        if (activeEl && otpModalEl.contains(activeEl) && typeof activeEl.blur === 'function') {
            activeEl.blur();
        }
    }, { once: true });

    otpModal.show();
    if (otpModalEl) {
        otpModalEl.addEventListener('hidden.bs.modal', () => {
            cleanupModalBackdrops();
            if (emailInput && typeof emailInput.focus === 'function') {
                emailInput.focus();
            }
        }, { once: true });
    }
}
