// --- Map initialization ---
let map;
let marker;
const addressField = document.querySelector('textarea[name="address"]');

function initMap() {
    // Default location (Philippines center)
    const defaultLat = 12.8797;
    const defaultLng = 121.7740;
    
    map = L.map('mapContainer').setView([defaultLat, defaultLng], 13);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Add draggable marker (will be positioned by geolocation)
    marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
    
    // Update address when marker is dragged
    marker.on('dragend', () => {
        const latlng = marker.getLatLng();
        getAddressFromCoordinates(latlng.lat, latlng.lng);
    });
    
    // Also allow clicking on map to move marker
    map.on('click', (e) => {
        marker.setLatLng(e.latlng);
        getAddressFromCoordinates(e.latlng.lat, e.latlng.lng);
    });
    
    // Auto-detect user's location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                // Move map and marker to user's location
                map.setView([lat, lng], 15);
                marker.setLatLng([lat, lng]);
                
                // Auto-fill address
                getAddressFromCoordinates(lat, lng);
            },
            (error) => {
                console.log('Geolocation error:', error.message);
                // If geolocation fails, use default location already set
                getAddressFromCoordinates(defaultLat, defaultLng);
            }
        );
    } else {
        console.log('Geolocation not supported');
        getAddressFromCoordinates(defaultLat, defaultLng);
    }
}

function getAddressFromCoordinates(lat, lng) {
    // Use server-side proxy to avoid CORS issues (see: auth/api/reverse-geocode.php)
    const url = `/THESIS/LYINGIN/auth/api/reverse-geocode.php?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`;

    // Show temporary status while we lookup
    addressField.value = 'Looking up address...';

    // Abort previous request if any to avoid race conditions
    if (window._reverseGeocodeAbort) {
        try { window._reverseGeocodeAbort.abort(); } catch (e) {}
    }
    const ac = new AbortController();
    window._reverseGeocodeAbort = ac;

    const doFetch = () => {
        return fetch(url, {
            signal: ac.signal,
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

            // Build complete address from address components when available
            const addr = data.address || {};
            const parts = [];

            if (addr.house_number && addr.road) {
                parts.push(`${addr.house_number} ${addr.road}`);
            } else if (addr.road) {
                parts.push(addr.road);
            }

            if (addr.village) parts.push(addr.village);
            if (addr.city_district) parts.push(addr.city_district);
            if (addr.city) parts.push(addr.city);
            if (addr.municipality) parts.push(addr.municipality);
            if (addr.province) parts.push(addr.province);
            if (addr.postcode) parts.push(addr.postcode);
            if (addr.country) parts.push(addr.country);

            const address = parts.length > 0 ? parts.join(', ') : (data.display_name || `Location: ${lat.toFixed(4)}, ${lng.toFixed(4)}`);
            addressField.value = address;
            return true;
        });
    };

    // Try once, then retry once after short delay on failure
    doFetch().catch(err => {
        console.error('Geocoding error (first attempt):', err);
        setTimeout(() => {
            doFetch().catch(err2 => {
                console.error('Geocoding error (retry):', err2);
                // Fallback to showing coordinates and asking user to verify
                addressField.value = `Location detected (${lat.toFixed(4)}, ${lng.toFixed(4)}) - Please verify address`;
            });
        }, 700);
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

// Validate Step 1 fields and toggle Next button
function validateStep1Fields() {
    const firstName = document.querySelector('input[name="first_name"]').value.trim();
    const lastName = document.querySelector('input[name="last_name"]').value.trim();
    const birthdate = document.querySelector('input[name="birthdate"]').value;
    const gender = document.querySelector('select[name="gender"]').value;
    const email = document.querySelector('input[name="email"]').value.trim();
    const contact = document.querySelector('input[name="contact_no"]').value.trim();
    const address = document.querySelector('textarea[name="address"]').value.trim();

    const addressValid = address && address !== 'Looking up address...' && !address.startsWith('Location detected');
    const emailVerifiedFlag = (typeof verifiedEmail !== 'undefined' && verifiedEmail && verifiedEmail === email);

    const valid = firstName && lastName && birthdate && gender && email && contact && addressValid && emailVerifiedFlag;
    nextBtn.disabled = !valid;
    return valid;
}

// Hook validation to inputs
['input[name="first_name"]','input[name="last_name"]','input[name="birthdate"]','select[name="gender"]','input[name="email"]','input[name="contact_no"]','textarea[name="address"]'].forEach(selector => {
    const el = document.querySelector(selector);
    if (el) {
        el.addEventListener('input', validateStep1Fields);
    }
});

nextBtn.addEventListener('click', () => {
    function showStepError(message) {
        let alertDiv = document.getElementById('step1Error');
        if (!alertDiv) {
            alertDiv = document.createElement('div');
            alertDiv.id = 'step1Error';
            alertDiv.className = 'alert alert-danger mb-3';
            const cardBody = document.querySelector('.card-body');
            cardBody.insertBefore(alertDiv, cardBody.firstChild);
        }
        alertDiv.textContent = message;
        setTimeout(() => { if (alertDiv && alertDiv.parentNode) alertDiv.parentNode.removeChild(alertDiv); }, 5000);
    }

    const firstName = document.querySelector('input[name="first_name"]').value.trim();
    const lastName = document.querySelector('input[name="last_name"]').value.trim();
    const birthdate = document.querySelector('input[name="birthdate"]').value;
    const gender = document.querySelector('select[name="gender"]').value;
    const email = document.querySelector('input[name="email"]').value.trim();
    const contact = document.querySelector('input[name="contact_no"]').value.trim();
    const address = document.querySelector('textarea[name="address"]').value.trim();

    if (!firstName) { showStepError('Please enter First Name'); document.querySelector('input[name="first_name"]').focus(); return; }
    if (!lastName) { showStepError('Please enter Last Name'); document.querySelector('input[name="last_name"]').focus(); return; }
    if (!birthdate) { showStepError('Please enter Birthdate'); document.querySelector('input[name="birthdate"]').focus(); return; }
    if (!gender) { showStepError('Please select Gender'); document.querySelector('select[name="gender"]').focus(); return; }
    if (!email) { showStepError('Please enter Email'); document.querySelector('input[name="email"]').focus(); return; }
    if (!contact) { showStepError('Please enter Contact Number'); document.querySelector('input[name="contact_no"]').focus(); return; }
    if (!address || address === 'Looking up address...' || address.startsWith('Location detected')) {
        showStepError('Please set a valid address by dragging the pin or clicking the map');
        return;
    }

    if (typeof verifiedEmail === 'undefined' || !verifiedEmail || verifiedEmail !== email) {
        showStepError('Please verify your email before continuing');
        return;
    }

    const old = document.getElementById('step1Error'); if (old) old.remove();

    step1.classList.add('d-none');
    step2.classList.remove('d-none');
    stepTabs[0].classList.remove('active');
    stepTabs[1].classList.remove('disabled');
    stepTabs[1].classList.add('active');
});

prevBtn.addEventListener('click', () => {
    step2.classList.add('d-none');
    step1.classList.remove('d-none');
    stepTabs[1].classList.remove('active');
    stepTabs[1].classList.add('disabled');
    stepTabs[0].classList.add('active');
});

// --- ID validation ---
const idNumber = document.getElementById('idNumber');
const idType = document.getElementById('idType');
const idFile = document.getElementById('idFile');
const idError = document.getElementById('idError');
const fileError = document.getElementById('fileError');

const idRules = {
    passport: /^[A-Z0-9]{6,9}$/i,
    drivers: /^[A-Z0-9\-]{5,20}$/i,
    national: /^\d{10,16}$/,
    philhealth: /^\d{12,14}$/,
    sss: /^\d{10,12}$/
};

idNumber.addEventListener('input', () => {
    const type = idType.value;
    let value = idNumber.value.trim();

    const normalizers = {
        passport: v => v.replace(/\s+/g, ''),
        drivers: v => v.replace(/[^A-Z0-9\-]/gi, ''),
        national: v => v.replace(/\D/g, ''),
        philhealth: v => v.replace(/\D/g, ''),
        sss: v => v.replace(/\D/g, '')
    };

    const normalize = normalizers[type] || (v => v);
    const normalized = normalize(value);

    if (['national', 'philhealth', 'sss'].includes(type)) {
        if (idNumber.value !== normalized) {
            idNumber.value = normalized;
            value = normalized;
        }
    }

    const errorMessages = {
        passport: 'Passport should be 6-9 letters/numbers',
        drivers: "Driver's License should be 5-20 characters (letters, numbers, dashes)",
        national: 'National/PhilSys ID should be 10-16 digits',
        philhealth: 'PhilHealth ID should be 12-14 digits',
        sss: 'SSS ID should be 10-12 digits'
    };

    if (!type || !idRules[type]?.test(normalized)) {
        idError.classList.remove('d-none');
        idNumber.classList.add('is-invalid');
        idError.textContent = errorMessages[type] || 'Invalid ID number format';
    } else {
        idError.classList.add('d-none');
        idNumber.classList.remove('is-invalid');
        idError.textContent = 'Invalid ID number format';
    }
});

// Helper for showing expected ID formats
function updateIdFormatHelp() {
    const help = document.getElementById('idFormatHelp');
    if (!help) return;
    const type = idType.value;
    const examples = {
        passport: 'Passport: 6-9 letters/numbers (e.g., A1234567).',
        drivers: "Driver\'s License: 5-20 characters (letters, numbers, dashes).",
        national: 'National/PhilSys: 10-16 digits (numbers only).',
        philhealth: 'PhilHealth: 12-14 digits (numbers only).',
        sss: 'SSS: 10-12 digits (numbers only).'
    };
    help.textContent = examples[type] || 'Select an ID type to see the expected format.';
}

// Re-validate when the ID type changes and update helper text
idType.addEventListener('change', () => {
    idNumber.dispatchEvent(new Event('input'));
    updateIdFormatHelp();
});

// Set initial helper text on load
updateIdFormatHelp();

idFile.addEventListener('change', () => {
    const file = idFile.files[0];
    const maxSize = 5 * 1024 * 1024;
    if (!file || file.size > maxSize || !file.type.match(/(image|pdf)/)) {
        fileError.classList.remove('d-none');
        idFile.classList.add('is-invalid');
        idFile.value = '';
    } else {
        fileError.classList.add('d-none');
        idFile.classList.remove('is-invalid');
    }
});

// --- Form submission with fetch ---
const patientForm = document.getElementById('patientForm');
const postRegisterModalEl = document.getElementById('postRegisterModal');
const postRegisterMessage = document.getElementById('postRegisterMessage');
const proceedLoginBtn = document.getElementById('proceedLoginBtn');
const registerAnotherBtn = document.getElementById('registerAnotherBtn');

function showPostRegisterModal(message) {
    if (postRegisterMessage) {
        postRegisterMessage.textContent = (message || 'Your account has been created.') + ' You will be redirected to the login page shortly.';
    }
    if (!postRegisterModalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(postRegisterModalEl);
    modal.show();
}

function resetRegistrationFormState() {
    if (patientForm) patientForm.reset();
    window.faceVerificationComplete = false;
    updateFaceVerificationUI();

    // Return to Step 1
    step2.classList.add('d-none');
    step1.classList.remove('d-none');
    stepTabs[1].classList.add('disabled');
    stepTabs[1].classList.remove('active');
    stepTabs[0].classList.add('active');
}

if (proceedLoginBtn) {
    proceedLoginBtn.addEventListener('click', () => {
        window.location.href = '/THESIS/LYINGIN/index.html?showLogin=1';
    });
}

if (registerAnotherBtn) {
    registerAnotherBtn.addEventListener('click', () => {
        resetRegistrationFormState();
        if (postRegisterModalEl) {
            const modal = bootstrap.Modal.getInstance(postRegisterModalEl) || bootstrap.Modal.getOrCreateInstance(postRegisterModalEl);
            modal.hide();
        }
    });
}

patientForm.addEventListener('submit', e => {
    e.preventDefault();

    if (idNumber.classList.contains('is-invalid') || idFile.classList.contains('is-invalid') || !idType.value) {
        alert('Please provide a valid ID before submitting.');
        return;
    }

    // Check face verification status
    if (!window.faceVerificationComplete) {
        alert('Please complete face verification before registering.');
        return;
    }

    const formData = new FormData(patientForm);

    // Use a root-absolute API path to avoid relative-path 404s when this script is loaded from different directories
    fetch('/THESIS/LYINGIN/auth/api/register-patient.php', {
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
            showPostRegisterModal(data.message || 'Registration successful.');
            // automatically forward to login after a short delay so user isn't stuck
            setTimeout(() => {
                window.location.href = '/THESIS/LYINGIN/index.html?showLogin=1';
            }, 4000);
        } else {
            alert(data.message || 'Registration failed. Please try again.');
        }
    })
    .catch(err => {
        console.error('Registration Error:', err);
        const message = (err && err.message) ? err.message : 'An unexpected error occurred. Check console for details.';
        alert(message);
        if (err && err.raw) console.log('Server response (raw):', err.raw);
    });
});

// ===== FACE VERIFICATION SYSTEM =====
window.faceVerificationComplete = false;
let capturedFaceImage = null;
let capturedFaceDescriptor = null;
let idPhotoDescriptor = null;
let modelsLoaded = false;
let verifiedEmail = null;

const useCameraBtn = document.getElementById('useCameraBtn');
const capturePhotoBtn = document.getElementById('capturePhotoBtn');
const previewImage = document.getElementById('previewImage');
const previewArea = document.getElementById('previewArea');
const verificationResult = document.getElementById('verificationResult');
const confirmFaceBtn = document.getElementById('confirmFaceBtn');
const videoStream = document.getElementById('videoStream');
const videoContainer = document.getElementById('videoContainer');
const faceVerificationStatus = document.getElementById('faceVerificationStatus');
let cameraStream = null;
let videoTrack = null;
let imageCapture = null;
let lightingInterval = null;
let torchOn = false;

// Load face-api models
async function loadFaceModels() {
    if (modelsLoaded) return;
    
    try {
        const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
        
        // Load tiny detector (fast) and SSD if available
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        try { await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL); } catch (e) { /* ignore */ }
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

// Extract face descriptor from image
async function extractFaceDescriptor(image) {
    const start = (typeof performance !== 'undefined') ? performance.now() : Date.now();
    try {
        let detection = null;
        try {
            detection = await faceapi.detectSingleFace(image, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.4 })).withFaceLandmarks().withFaceDescriptor();
        } catch (e) {
            detection = await faceapi.detectSingleFace(image).withFaceLandmarks().withFaceDescriptor();
        }

        const elapsed = Math.round(((typeof performance !== 'undefined') ? performance.now() : Date.now()) - start);

        if (!detection) throw new Error('No face detected in image');

        const score = detection.detection && typeof detection.detection.score === 'number' ? detection.detection.score : (detection.score || 0);
        window.lastDetectionTimeMs = elapsed;
        window.lastDetectionScore = score;

        const MIN_CONFIDENCE = 0.45;
        if (score < MIN_CONFIDENCE) {
            throw new Error(`Low detection confidence (${Math.round(score*100)}%). Try removing sunglasses, cleaning lens, improving lighting, or moving closer.`);
        }

        return detection.descriptor;
    } catch (error) {
        throw new Error(`Face detection failed: ${error.message}`);
    }
}

// Calculate similarity between two descriptors (0-1, where 1 is identical)
function calculateSimilarity(descriptor1, descriptor2) {
    if (!descriptor1 || !descriptor2) return 0;
    
    let sum = 0;
    for (let i = 0; i < descriptor1.length; i++) {
        sum += Math.pow(descriptor1[i] - descriptor2[i], 2);
    }
    const euclideanDistance = Math.sqrt(sum);
    // Convert distance to similarity (0-1)
    return Math.max(0, 1 - (euclideanDistance / 2));
}

// Show verification status
function showVerificationStatus(message, type = 'info') {
    const statusDiv = document.createElement('div');
    statusDiv.className = `verification-status status-${type}`;
    statusDiv.textContent = message;
    verificationResult.innerHTML = '';
    verificationResult.appendChild(statusDiv);
}

function getAverageBrightness(canvas) {
    const ctx = canvas.getContext('2d');
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
    } catch (e) { return 255; }
}

function checkLighting() {
    if (!videoStream || videoStream.readyState < 2) return;
    const temp = document.createElement('canvas');
    const vw = videoStream.videoWidth || 320;
    const vh = videoStream.videoHeight || 240;
    const w = Math.max(30, Math.round(vw * 0.2));
    const h = Math.max(30, Math.round(vh * 0.2));
    temp.width = w; temp.height = h;
    const ctx = temp.getContext('2d');
    try {
        ctx.drawImage(videoStream, Math.round((vw-w)/2), Math.round((vh-h)/2), w, h, 0, 0, w, h);
        const avg = getAverageBrightness(temp);
        const hint = document.getElementById('lightingHint');
        if (avg < 50) { hint.textContent = 'Low light detected. Please improve lighting or enable flash.'; capturePhotoBtn.disabled = true; }
        else { hint.textContent = 'Lighting looks good.'; capturePhotoBtn.disabled = false; }
    } catch (e) {}
}

async function toggleTorch() {
    if (!videoTrack || !videoTrack.applyConstraints) return;
    torchOn = !torchOn;
    try { await videoTrack.applyConstraints({ advanced: [{ torch: torchOn }] }); const btn = document.getElementById('torchToggleBtn'); btn.innerHTML = torchOn ? '<i class="bi bi-lightbulb-fill"></i> Flash On' : '<i class="bi bi-lightbulb"></i> Turn On Flash'; } catch (err) { console.warn('Torch toggle failed:', err); }
}

function enhanceCanvas(canvas) {
    const ctx = canvas.getContext('2d');
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imgData.data;
    let sum = 0;
    for (let i = 0; i < data.length; i += 4) { sum += (0.2126 * data[i] + 0.7152 * data[i+1] + 0.0722 * data[i+2]); }
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

// Update face verification UI
function updateFaceVerificationUI() {
    const statusDiv = document.getElementById('faceVerificationStatus');
    
    if (window.faceVerificationComplete) {
        statusDiv.innerHTML = '<div class="verification-status status-success"><i class="bi bi-check-circle"></i> Face verification complete!</div>';
    } else {
        statusDiv.innerHTML = '';
    }
}

// Camera flow
useCameraBtn.addEventListener('click', startCamera);

async function startCamera() {
    try {
        await loadFaceModels();
        videoContainer.style.display = 'block';
        previewArea.style.display = 'none';
        showVerificationStatus('Starting camera...', 'info');

        const constraints = { video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } }, audio: false };
        cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
        videoStream.srcObject = cameraStream;
        videoStream.play();

        videoTrack = cameraStream.getVideoTracks()[0];
        try { imageCapture = (typeof ImageCapture !== 'undefined' && videoTrack) ? new ImageCapture(videoTrack) : null; } catch (e) { imageCapture = null; }

        const capabilities = videoTrack.getCapabilities ? videoTrack.getCapabilities() : {};
        const torchBtn = document.getElementById('torchToggleBtn');
        if (capabilities.torch) {
            torchBtn.style.display = 'inline-block';
            torchBtn.onclick = toggleTorch;
        } else {
            torchBtn.style.display = 'none';
        }

        lightingInterval = setInterval(checkLighting, 600);

        capturePhotoBtn.style.display = 'inline-block';
        capturePhotoBtn.disabled = false;
        showVerificationStatus('Camera ready. Position your face and press Capture.', 'info');
    } catch (error) {
        console.error('Camera error:', error);
        showVerificationStatus('Unable to access camera. Please allow camera access or use a supported device.', 'error');
    }
} 

function stopCamera() {
    if (lightingInterval) { clearInterval(lightingInterval); lightingInterval = null; }
    if (cameraStream) {
        cameraStream.getTracks().forEach(t => t.stop());
        cameraStream = null;
    }
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

capturePhotoBtn.addEventListener('click', async () => {
    try {
        // Freeze and capture frame
        showVerificationStatus('Capturing photo...', 'info');
        capturePhotoBtn.disabled = true;

        const canvas = document.getElementById('canvas');
        // Downscale for faster detection (target width)
        const targetWidth = 320;
        const videoWidth = videoStream.videoWidth || 640;
        const videoHeight = videoStream.videoHeight || 480;
        const scale = videoWidth / targetWidth;
        canvas.width = targetWidth;
        canvas.height = Math.round(videoHeight / scale);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoStream, 0, 0, canvas.width, canvas.height);

        const avg = getAverageBrightness(canvas);
        if (avg < 50) {
            showVerificationStatus('Lighting too low. Please increase lighting or enable flash.', 'error');
            capturePhotoBtn.disabled = false;
            startCamera();
            return;
        }

        enhanceCanvas(canvas);

        const dataUrl = canvas.toDataURL('image/jpeg');

        // Stop camera immediately to freeze the UI and reduce CPU
        stopCamera();

        // Show preview (frozen frame)
        previewImage.src = dataUrl;
        previewArea.style.display = 'block';

        // Run face detection on a smaller frame to speed up processing
        const img = new Image();
        img.onload = async () => {
            try {
                showVerificationStatus('Detecting face...', 'info');
                showDetectionSpinner();
                capturedFaceDescriptor = await extractFaceDescriptor(img);
                hideDetectionSpinner();
                capturedFaceImage = img;

                if (window.lastDetectionTimeMs !== undefined && window.lastDetectionScore !== undefined) {
                    showVerificationStatus(`Face detected (score: ${Math.round(window.lastDetectionScore*100)}%, ${window.lastDetectionTimeMs} ms)`, 'success');
                } else {
                    showVerificationStatus('Face detected', 'success');
                }

                // Compare with ID photo if available
                if (idPhotoDescriptor) {
                    compareFaces();
                } else {
                    confirmFaceBtn.style.display = 'none';
                }
            } catch (err) {
                hideDetectionSpinner();
                showVerificationStatus(err.message, 'error');
                capturePhotoBtn.disabled = false;
                startCamera();
            }
        };
        img.src = dataUrl;
    } catch (err) {
        console.error('Capture error:', err);
        showVerificationStatus('Error capturing photo. Please try again.', 'error');
        capturePhotoBtn.disabled = false;
    }
}); 

// Initialize face models and start camera when modal opens
document.getElementById('faceVerificationModal').addEventListener('show.bs.modal', async () => {
    await loadFaceModels();
    startCamera();
});

// Stop camera when modal closes
document.getElementById('faceVerificationModal').addEventListener('hidden.bs.modal', stopCamera);

// Handle ID photo for comparison
idFile.addEventListener('change', async (event) => {
    const file = event.target.files[0];
    
    if (!file) return;
    
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
                    idPhotoDescriptor = await extractFaceDescriptor(img);
                    console.log('ID face extracted successfully');
                    
                    // If we already have a captured face, compare them
                    if (capturedFaceDescriptor) {
                        compareFaces();
                    }
                } catch (error) {
                    console.log('Could not extract face from ID photo:', error.message);
                    idPhotoDescriptor = null;
                }
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    } catch (error) {
        console.error('Error processing ID photo:', error);
    }
});

// Compare captured face with ID photo
function compareFaces() {
    if (!capturedFaceDescriptor || !idPhotoDescriptor) {
        showVerificationStatus('Both face photo and ID are required for verification.', 'error');
        confirmFaceBtn.style.display = 'none';
        return;
    }
    
    const similarity = calculateSimilarity(capturedFaceDescriptor, idPhotoDescriptor);
    const threshold = 0.50; // 50% similarity threshold
    const isMatching = similarity >= threshold;
    
    if (isMatching) {
        showVerificationStatus(
            `✓ Face Match Verified! (${Math.round(similarity * 100)}% match) Your face matches your ID.`,
            'success'
        );
        confirmFaceBtn.style.display = 'inline-block';
    } else {
        showVerificationStatus(
            `✗ Face Match Failed (${Math.round(similarity * 100)}% match - need ${Math.round(threshold * 100)}% minimum)\nThe face in your photo doesn't match your ID. Please upload a clearer photo.`,
            'error'
        );
        confirmFaceBtn.style.display = 'none';
    }
}

// Confirm face verification
confirmFaceBtn.addEventListener('click', () => {
    window.faceVerificationComplete = true;
    updateFaceVerificationUI();
    
    // Show success message
    const modal = bootstrap.Modal.getInstance(document.getElementById('faceVerificationModal'));
    modal.hide();
    
    alert('Face verification complete! You can now submit your registration.');
});

// Initialize face models when modal opens
document.getElementById('faceVerificationModal').addEventListener('show.bs.modal', loadFaceModels);
