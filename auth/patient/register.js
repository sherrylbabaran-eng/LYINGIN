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
    // Use OpenStreetMap Nominatim API for reverse geocoding
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
    
    fetch(url, {
        headers: { 'User-Agent': 'PatientRegistration' }
    })
    .then(res => res.json())
    .then(data => {
        if (!data || !data.address) {
            throw new Error('No address data received');
        }
        
        // Build complete address from address components
        const addr = data.address;
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
        
        const address = parts.length > 0 ? parts.join(', ') : data.display_name;
        addressField.value = address;
    })
    .catch(err => {
        console.error('Geocoding error:', err);
        // Retry once on error
        setTimeout(() => {
            fetch(url, {
                headers: { 'User-Agent': 'PatientRegistration' }
            })
            .then(res => res.json())
            .then(data => {
                const address = data.display_name || `Location: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                addressField.value = address;
            })
            .catch(() => {
                addressField.value = `Location detected (${lat.toFixed(4)}, ${lng.toFixed(4)}) - Please verify address`;
            });
        }, 1000);
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

nextBtn.addEventListener('click', () => {
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
    drivers: /^[A-Z0-9\-]{8,15}$/i,
    national: /^[0-9]{12}$/,
    philhealth: /^[0-9]{12}$/,
    sss: /^[0-9]{10}$/
};

idNumber.addEventListener('input', () => {
    const type = idType.value;
    const value = idNumber.value.trim();
    if (!type || !idRules[type]?.test(value)) {
        idError.classList.remove('d-none');
        idNumber.classList.add('is-invalid');
    } else {
        idError.classList.add('d-none');
        idNumber.classList.remove('is-invalid');
    }
});

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

    fetch('../api/register-patient.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(data => Promise.reject(data));
        }
        return res.json();
    })
    .then(data => {
        alert(data.message);
        if (data.status === 'success') {
            patientForm.reset();
            window.faceVerificationComplete = false;
            updateFaceVerificationUI();
            // Return to Step 1
            step2.classList.add('d-none');
            step1.classList.remove('d-none');
            stepTabs[1].classList.add('disabled');
            stepTabs[1].classList.remove('active');
            stepTabs[0].classList.add('active');
        }
    })
    .catch(err => {
        console.error('Registration Error:', err);
        const message = err.message || 'An unexpected error occurred. Check console for details.';
        alert(message);
    });
});

// ===== FACE VERIFICATION SYSTEM =====
window.faceVerificationComplete = false;
let capturedFaceImage = null;
let capturedFaceDescriptor = null;
let idPhotoDescriptor = null;
let modelsLoaded = false;

const facePhotoInput = document.getElementById('facePhoto');
const uploadFaceBtn = document.getElementById('uploadFaceBtn');
const useCameraBtn = document.getElementById('useCameraBtn');
const previewImage = document.getElementById('previewImage');
const previewArea = document.getElementById('previewArea');
const verificationResult = document.getElementById('verificationResult');
const confirmFaceBtn = document.getElementById('confirmFaceBtn');
const videoStream = document.getElementById('videoStream');
const videoContainer = document.getElementById('videoContainer');
const faceVerificationStatus = document.getElementById('faceVerificationStatus');

// Load face-api models
async function loadFaceModels() {
    if (modelsLoaded) return;
    
    try {
        const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
        
        await Promise.all([
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceDetectionNet.loadFromUri(MODEL_URL),
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
    try {
        const detections = await faceapi.detectAllFaces(image).withFaceLandmarks().withFaceDescriptors();
        
        if (detections.length === 0) {
            throw new Error('No face detected in image');
        }
        
        if (detections.length > 1) {
            throw new Error('Multiple faces detected. Please provide a photo with only your face');
        }
        
        return detections[0].descriptor;
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

// Update face verification UI
function updateFaceVerificationUI() {
    const statusDiv = document.getElementById('faceVerificationStatus');
    
    if (window.faceVerificationComplete) {
        statusDiv.innerHTML = '<div class="verification-status status-success"><i class="bi bi-check-circle"></i> Face verification complete!</div>';
    } else {
        statusDiv.innerHTML = '';
    }
}

// Handle face photo upload
uploadFaceBtn.addEventListener('click', () => {
    facePhotoInput.click();
});

facePhotoInput.addEventListener('change', async (event) => {
    const file = event.target.files[0];
    
    if (!file) return;
    
    // Validate file
    if (!file.type.match(/image\/(jpeg|png|gif|webp)/)) {
        showVerificationStatus('Invalid file format. Please upload JPG, PNG, GIF, or WebP image.', 'error');
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
        showVerificationStatus('File size too large. Maximum 10MB allowed.', 'error');
        return;
    }
    
    try {
        await loadFaceModels();
        showVerificationStatus('Processing face photo...', 'info');
        
        const reader = new FileReader();
        reader.onload = async (e) => {
            const img = new Image();
            img.onload = async () => {
                try {
                    // Extract face descriptor
                    capturedFaceDescriptor = await extractFaceDescriptor(img);
                    capturedFaceImage = img;
                    
                    // Show preview
                    previewImage.src = img.src;
                    previewArea.style.display = 'block';
                    uploadFaceBtn.style.display = 'none';
                    useCameraBtn.style.display = 'inline-block';
                    
                    // Compare with ID photo if available
                    if (idPhotoDescriptor) {
                        compareFaces();
                    } else {
                        showVerificationStatus('Face photo captured. Now please upload your ID to verify.', 'info');
                        confirmFaceBtn.style.display = 'none';
                    }
                } catch (error) {
                    showVerificationStatus(error.message, 'error');
                    facePhotoInput.value = '';
                }
            };
            img.onerror = () => {
                showVerificationStatus('Error loading image. Please try another file.', 'error');
                facePhotoInput.value = '';
            };
            img.src = e.target.result;
        };
        reader.onerror = () => {
            showVerificationStatus('Error reading file. Please try again.', 'error');
        };
        reader.readAsDataURL(file);
    } catch (error) {
        showVerificationStatus(error.message, 'error');
    }
});

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
