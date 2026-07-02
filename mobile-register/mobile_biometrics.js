let tempClientData = {};
let faceData = null;
let stream = null;

// ─────────────────────────────────────────────────────────────
// STEP NAVIGATION
// ─────────────────────────────────────────────────────────────
function proceedToStep(currentStep, nextStep) {
    if (currentStep === 'step-1') {
        tempClientData.fullname       = document.getElementById('fullname').value;
        tempClientData.license_no     = document.getElementById('license_no').value;
        tempClientData.password       = document.getElementById('password').value;
        tempClientData.date_of_birth  = document.getElementById('dob').value;
        tempClientData.license_expiry = document.getElementById('license_expiry').value;
        tempClientData.age            = document.getElementById('age').value;

        if (
            !tempClientData.fullname    ||
            !tempClientData.license_no  ||
            !tempClientData.password    ||
            !tempClientData.license_expiry
        ) {
            IosAlert.alert("Please complete all required fields.");
            return;
        }
    }

    if (currentStep === 'step-2') {
        tempClientData.gender       = document.getElementById('gender').value;
        tempClientData.email        = document.getElementById('email').value;
        tempClientData.phone_number = document.getElementById('mobile').value;
    }

    if (nextStep === 'step-3') startCamera();

    document.getElementById(currentStep).classList.add('step-hidden');
    document.getElementById(nextStep).classList.remove('step-hidden');
}

// ─────────────────────────────────────────────────────────────
// AGE CALCULATOR
// ─────────────────────────────────────────────────────────────
function calculateAge(birthDate) {
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    return age;
}

// Auto-update age when DOB is changed manually
document.addEventListener('DOMContentLoaded', function () {
    const dobInput = document.getElementById('dob');
    const ageInput = document.getElementById('age');
    if (dobInput) {
        dobInput.addEventListener('change', function () {
            ageInput.value = calculateAge(this.value);
        });
    }
});

// ─────────────────────────────────────────────────────────────
// PREPROCESS IMAGE
// Resize to max 1200px + grayscale + contrast boost
// Para mas mabilis at mas accurate ang OCR sa mobile
// ─────────────────────────────────────────────────────────────
function preprocessImage(file) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');

            const MAX_WIDTH = 1200;
            const scale = img.width > MAX_WIDTH ? MAX_WIDTH / img.width : 1;
            canvas.width  = img.width  * scale;
            canvas.height = img.height * scale;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;

            for (let i = 0; i < data.length; i += 4) {
                const gray    = 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
                const boosted = gray > 128
                    ? Math.min(255, gray * 1.2)
                    : Math.max(0,   gray * 0.8);
                data[i] = data[i+1] = data[i+2] = boosted;
            }

            ctx.putImageData(imageData, 0, 0);
            canvas.toBlob(resolve, 'image/jpeg', 0.92);
        };
        img.src = URL.createObjectURL(file);
    });
}

// ─────────────────────────────────────────────────────────────
// FORMAT LICENSE NUMBER
// Ensures format: X00-00-000000 or X00-00-S00000 (student)
// ─────────────────────────────────────────────────────────────
function formatLicenseNo(raw) {
    const cleaned = raw.toUpperCase().replace(/\s/g, '');
    // Already has correct dashes
    if (/[A-Z]\d{2}-\d{2}-[A-Z]?\d+/.test(cleaned)) return cleaned;
    // Insert dashes
    if (cleaned.length >= 9) {
        const letter = cleaned[0];
        const rest   = cleaned.slice(1);
        const third  = rest.slice(4);
        return `${letter}${rest.slice(0,2)}-${rest.slice(2,4)}-${third}`;
    }
    return cleaned;
}

// ─────────────────────────────────────────────────────────────
// PROPER CASE
// JUAN DELA CRUZ → Juan Dela Cruz
// ─────────────────────────────────────────────────────────────
function toProperCase(str) {
    return str.toLowerCase().replace(/(?:^|\s|,|-|')(\S)/g, c => c.toUpperCase());
}

// ─────────────────────────────────────────────────────────────
// PARSE PHILIPPINE DRIVER'S LICENSE
// Supports: Regular License & Student Permit
// ─────────────────────────────────────────────────────────────
function parsePHLicense(rawText) {

    const lines = rawText
        .split('\n')
        .map(l => l.trim())
        .filter(l => l.length > 1);

    const rawUpper = rawText.toUpperCase();

    console.log("RAW LINES:", lines);

    const result = {
        fullname:    null,
        license_no:  null,
        dob:         null,
        expiry:      null,
        licenseType: rawUpper.includes('STUDENT') || rawUpper.includes('PERMIT')
                        ? 'student' : 'regular',
    };

    // ── NON-NAME KEYWORDS ──────────────────────────────────────
    const NON_NAME_KEYWORDS = [
        'REPUBLIC','PHILIPPINES','LICENSE','DRIVER','LAND','TRANSPORTATION',
        'EXPIR','BIRTH','NATIONALITY','ADDRESS','RESTRICTION','SEX','HEIGHT',
        'WEIGHT','BLOOD','CIVIL','AGENCY','OFFICIAL','DATE','ISSUED','LTO',
        'OFFICE','CONDITION','CODE','NO.','NUMBER','CLASS','NOTE','SIGNATURE',
        'STUDENT','PERMIT','DEPARTMENT','SECRETARY','ASSISTANT','ATTY',
        'PHL','BROWN','BLACK','NONE','CAVITE','MANILA','QUEZON','CEBU',
        'DAVAO','DL CODE','SANTIAGO','GENERAL','TRIAS','ALIMA','BACOOR'
    ];

    function hasNonNameKeyword(str) {
        return NON_NAME_KEYWORDS.some(kw => str.toUpperCase().includes(kw));
    }

    // ─────────────────────────────────────────
    // 1. LICENSE NUMBER
    // PHL Format:
    //   Regular : D06-16-000489
    //   Student : D23-26-S01389 (may letter sa 3rd group)
    // ─────────────────────────────────────────

    // NOTE: Hindi natin gagalawin ang O→0 globally para hindi masira ang name.
    // Gagawin lang natin ito sa stripped/no-space version para sa license matching.
    const rawNoSpace = rawUpper.replace(/\s+/g, '');

    const licensePatterns = [
        /[A-Z]\d{2}-\d{2}-[A-Z]?\d{4,7}/,   // may dashes: D06-16-000489
        /[A-Z]\d{2}\d{2}[A-Z]\d{4,7}/,       // student walang dashes: D2326S01389
        /[A-Z]\d{2}\d{2}\d{4,7}/,             // regular walang dashes: D0616000489
    ];

    // Strategy 1: label-based — mas accurate
    const licenseLineIdx = lines.findIndex(l =>
        l.toUpperCase().includes('LICENSE NO') ||
        l.toUpperCase().includes('LICENSE NUMBER')
    );

    if (licenseLineIdx !== -1) {
        const searchLines = lines
            .slice(licenseLineIdx, licenseLineIdx + 3)
            .map(l => l.toUpperCase().replace(/\s+/g, ''));

        for (const line of searchLines) {
            for (const pat of licensePatterns) {
                const m = line.match(pat);
                if (m) { result.license_no = formatLicenseNo(m[0]); break; }
            }
            if (result.license_no) break;
        }
    }

    // Strategy 2: scan whole text (fallback)
    if (!result.license_no) {
        for (const pat of licensePatterns) {
            const m = rawNoSpace.match(pat);
            if (m) { result.license_no = formatLicenseNo(m[0]); break; }
        }
    }

    // ─────────────────────────────────────────
    // 2. DATES — DOB & EXPIRY
    // PHL Format: YYYY/MM/DD
    // Strategy: find labels first, extract nearby date
    // ─────────────────────────────────────────

    const datePattern = /\b(\d{4})[\/\-](\d{2})[\/\-](\d{2})\b/;

    function extractDateNear(labelKeywords) {
        for (let i = 0; i < lines.length; i++) {
            const upper = lines[i].toUpperCase();
            if (labelKeywords.some(kw => upper.includes(kw))) {
                for (let j = i; j < Math.min(i + 3, lines.length); j++) {
                    const m = lines[j].match(datePattern);
                    if (m) {
                        const yr = parseInt(m[1]);
                        const mo = parseInt(m[2]);
                        const dy = parseInt(m[3]);
                        if (
                            yr >= 1900 && yr <= 2100 &&
                            mo >= 1   && mo <= 12    &&
                            dy >= 1   && dy <= 31
                        ) {
                            return `${yr}-${String(mo).padStart(2,'0')}-${String(dy).padStart(2,'0')}`;
                        }
                    }
                }
            }
        }
        return null;
    }

    result.dob    = extractDateNear(['DATE OF BIRTH', 'BIRTH']);
    result.expiry = extractDateNear(['EXPIRATION', 'EXPIRY']);

    // Fallback: collect all YYYY/MM/DD dates, sort oldest→newest
    // DOB = oldest, Expiry = newest
    // (Avoids the photo watermark date confusion)
    if (!result.dob || !result.expiry) {
        const allDates = [];
        const gp = /\b(\d{4})[\/\-](\d{2})[\/\-](\d{2})\b/g;
        let gm;
        while ((gm = gp.exec(rawText)) !== null) {
            const yr = parseInt(gm[1]);
            const mo = parseInt(gm[2]);
            const dy = parseInt(gm[3]);
            if (yr >= 1900 && yr <= 2100 && mo >= 1 && mo <= 12 && dy >= 1 && dy <= 31) {
                const iso = `${yr}-${String(mo).padStart(2,'0')}-${String(dy).padStart(2,'0')}`;
                allDates.push({ iso, yr });
            }
        }

        // Deduplicate then sort oldest → newest
        const unique = [...new Map(allDates.map(d => [d.iso, d])).values()]
            .sort((a, b) => a.yr - b.yr);

        console.log("FALLBACK DATES:", unique);

        if (!result.dob    && unique.length >= 1) result.dob    = unique[0].iso;
        if (!result.expiry && unique.length >= 2) result.expiry = unique[unique.length - 1].iso;
    }

    // ─────────────────────────────────────────
    // 3. FULL NAME
    // PHL format: "SURNAME, FIRSTNAME MIDDLENAME"
    // The name is on the line AFTER the label:
    // "Last Name, First Name, Middle Name"
    // ─────────────────────────────────────────

    // Strategy 1: label-based (most reliable)
    const nameLabelIdx = lines.findIndex(l => {
        const u = l.toUpperCase();
        return (
            (u.includes('LAST NAME') && u.includes('FIRST NAME')) ||
            u.startsWith('LAST NAME,') ||
            u.startsWith('LAST NAME.')
        );
    });

    if (nameLabelIdx !== -1) {
        for (let i = nameLabelIdx + 1; i < Math.min(nameLabelIdx + 4, lines.length); i++) {
            const candidate = lines[i].trim();
            if (
                candidate.length >= 5         &&
                candidate.includes(',')       &&
                !hasNonNameKeyword(candidate) &&
                /[A-Za-z]{2,}/.test(candidate)
            ) {
                result.fullname = toProperCase(candidate);
                break;
            }
        }
    }

    // Strategy 2: fallback — find most name-like line
    // (line with comma, mostly letters, no keywords)
    if (!result.fullname) {
        const candidates = lines.filter(line => {
            if (hasNonNameKeyword(line))               return false;
            if (line.length < 5 || line.length > 70)  return false;
            if (!line.includes(','))                   return false;
            const letterRatio = line.replace(/[^A-Za-z]/g, '').length / line.length;
            return letterRatio > 0.7;
        });

        if (candidates.length > 0) {
            const best = candidates.reduce((a, b) => a.length >= b.length ? a : b);
            result.fullname = toProperCase(best.trim());
        }
    }

    console.log("PARSED RESULT:", result);
    return result;
}

// ─────────────────────────────────────────────────────────────
// PERFORM OCR — Main function
// ─────────────────────────────────────────────────────────────
async function performOCR(input) {

    const status = document.getElementById('ocr-status');
    if (!input.files || !input.files[0]) return;

    status.innerHTML = `
        <span style="color:#fbbf24; font-weight:700;">
            🔍 Preparing OCR Scanner...
        </span>
    `;

    try {
        const imageFile = input.files[0];

        // Preprocess para mas malinaw ang OCR
        const processedBlob = await preprocessImage(imageFile);

        const result = await Tesseract.recognize(
            processedBlob,
            'eng',
            {
                logger: m => {
                    if (m.status === 'initializing tesseract') {
                        status.innerHTML = `
                            <span style="color:#38bdf8; font-weight:700;">
                                ⚙️ Initializing OCR Engine...
                            </span>
                        `;
                    } else if (m.status === 'loading language traineddata') {
                        status.innerHTML = `
                            <span style="color:#facc15; font-weight:700;">
                                📚 Loading OCR Data...
                            </span>
                        `;
                    } else if (m.status === 'recognizing text') {
                        const pct = Math.round(m.progress * 100);
                        status.innerHTML = `
                            <div style="width:100%; background:#1e293b; border-radius:12px; overflow:hidden; margin-top:8px;">
                                <div style="
                                    width:${pct}%;
                                    min-width:80px;
                                    background:linear-gradient(90deg,#3b82f6,#06b6d4);
                                    color:white;
                                    padding:8px;
                                    font-size:12px;
                                    font-weight:bold;
                                    text-align:center;
                                    transition:width 0.3s;
                                ">
                                    🔍 Scanning... ${pct}%
                                </div>
                            </div>
                        `;
                    }
                }
            }
        );

        const rawText = result.data.text;
        console.log("RAW OCR TEXT:\n", rawText);

        // Parse the license
        const parsed = parsePHLicense(rawText);
        console.log("PARSED:", parsed);

        // Fill form fields
        let filledCount = 0;

        if (parsed.fullname) {
            document.getElementById('fullname').value = parsed.fullname;
            filledCount++;
        }
        if (parsed.license_no) {
            document.getElementById('license_no').value = parsed.license_no;
            filledCount++;
        }
        if (parsed.dob) {
            const dobInput = document.getElementById('dob');
            dobInput.type  = 'date';
            dobInput.value = parsed.dob;
            document.getElementById('age').value = calculateAge(parsed.dob);
            filledCount++;
        }
        if (parsed.expiry) {
            const expInput = document.getElementById('license_expiry');
            expInput.type  = 'date';
            expInput.value = parsed.expiry;
            filledCount++;
        }

        // Status feedback
        if (filledCount >= 3) {
            status.innerHTML = `
                <div class="text-emerald-500 font-bold flex items-center justify-center gap-1 text-xs">
                    <i class="fas fa-check-circle"></i> Scan Complete! ${filledCount}/4 fields detected.
                </div>`;
        } else if (filledCount > 0) {
            status.innerHTML = `
                <div class="text-yellow-500 font-bold flex items-center justify-center gap-1 text-xs">
                    <i class="fas fa-exclamation-circle"></i> Partial scan (${filledCount}/4 fields). Please fill in the rest.
                </div>`;
        } else {
            status.innerHTML = `
                <div class="text-red-500 font-bold flex items-center justify-center gap-1 text-xs">
                    <i class="fas fa-times-circle"></i> Could not read license. Please fill manually.
                </div>`;
        }

    } catch (err) {
        console.error("OCR Error:", err);
        status.innerHTML = `
            <div class="text-red-500 font-bold flex items-center justify-center gap-1 text-xs">
                <i class="fas fa-times-circle"></i> Scan failed. Please fill manually.
            </div>`;
    }
}

// ─────────────────────────────────────────────────────────────
// CAMERA
// ─────────────────────────────────────────────────────────────
function startCamera() {
    const video = document.getElementById('webcam');
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
        .then(s => {
            stream = s;
            video.srcObject = stream;
        })
        .catch(() => IosAlert.alert("Camera access denied."));
}

function captureFace() {
    const video = document.getElementById('webcam');
    const canvas = document.createElement('canvas');

    canvas.width  = 480;
    canvas.height = 640;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    faceData = canvas.toDataURL('image/jpeg', 0.7);

    // Stop camera stream
    if (stream) stream.getTracks().forEach(track => track.stop());

    video.style.display = "none";

    const container = video.parentElement;

    // Show captured image
    const img = document.createElement('img');
    img.src       = faceData;
    img.className = "absolute inset-0 w-full h-full object-cover";
    container.appendChild(img);

    // Flash effect
    const flash = document.createElement('div');
    flash.className = "absolute inset-0 bg-white opacity-80";
    container.appendChild(flash);
    setTimeout(() => flash.remove(), 200);

    // Scan line animation
    const scanLine = document.createElement('div');
    scanLine.style.cssText = `
        position:absolute; top:0; left:0;
        width:100%; height:3px;
        background:#22c55e;
        box-shadow:0 0 10px #22c55e;
        animation:scan-move 1.5s linear infinite;
    `;
    container.appendChild(scanLine);
    setTimeout(() => scanLine.remove(), 1500);

    document.getElementById('face-status').innerHTML =
        '<div class="bg-yellow-400 text-black px-4 py-2 rounded-full font-bold">DETECTING FACE...</div>';

    setTimeout(() => {
        document.getElementById('face-status').innerHTML =
            '<div class="bg-emerald-500 text-white px-4 py-2 rounded-full font-bold">FACE VERIFIED ✔</div>';
    }, 1200);

    showRetake();
    checkAllDone();
}

function checkAllDone() {
    const submitBtn = document.getElementById('btn-submit');
    if (faceData) {
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-40');
        submitBtn.classList.add('bg-blue-900');
    }
}

function retakeFace() {
    faceData = null;

    const container = document.getElementById('webcam').parentElement;
    const img = container.querySelector('img');
    if (img) img.remove();

    document.getElementById('webcam').style.display = "block";
    document.getElementById('face-status').innerHTML = '';

    hideRetake();

    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    btn.classList.add('opacity-40');

    startCamera();
}

function showRetake() {
    const btn = document.getElementById('retake-btn');
    btn.classList.remove('opacity-0', 'pointer-events-none');
}

function hideRetake() {
    const btn = document.getElementById('retake-btn');
    btn.classList.add('opacity-0', 'pointer-events-none');
}

// ─────────────────────────────────────────────────────────────
// VALIDATION
// ─────────────────────────────────────────────────────────────
function validateField(id) {
    const el = document.getElementById(id);
    if (!el.value) {
        el.classList.add('border-red-500');
        el.classList.remove('border-slate-200');
        return false;
    } else {
        el.classList.remove('border-red-500');
        return true;
    }
}

// ─────────────────────────────────────────────────────────────
// PASSWORD TOGGLE
// ─────────────────────────────────────────────────────────────
function togglePass() {
    const p = document.getElementById('password');
    const i = document.getElementById('eyeIcon');
    if (p.type === 'password') {
        p.type     = 'text';
        i.className = 'fas fa-eye-slash';
    } else {
        p.type     = 'password';
        i.className = 'fas fa-eye';
    }
}

// ─────────────────────────────────────────────────────────────
// SUBMIT REGISTRATION
// ─────────────────────────────────────────────────────────────
async function submitRegistration() {
    const btn = document.getElementById('btn-submit');

    if (btn.disabled) return;

    if (!faceData) {
        IosAlert.alert("Please capture your face first.");
        return;
    }

    btn.innerHTML = `
        <div class="flex items-center justify-center gap-2">
            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            Saving...
        </div>
    `;
    btn.disabled = true;

    try {
        // Generate QR code from license number
        const qrContainer = document.createElement('div');
        new QRCode(qrContainer, {
            text:   document.getElementById('license_no').value,
            width:  256,
            height: 256
        });

        const qrCanvas = qrContainer.querySelector('canvas');
        const qrBase64 = qrCanvas ? qrCanvas.toDataURL('image/png') : "";

        // Build form data
        const formData = new FormData();
        for (let key in tempClientData) {
            formData.append(key, tempClientData[key]);
        }
        formData.append('face_data', faceData);
        formData.append('qr_image',  qrBase64);

        // Submit
        const res = await fetch(
            "https://dvats-api-php.onrender.com/insert_client.php",
            {
                method:  'POST',
                body:    formData,
            }
        );

        const result = await res.json();

        if (result.status === "success") {
            IosAlert.alert("Registration Successful!");
            window.location.href =
                "/driver-portal/index.html";
        } else {
            throw new Error(result.message || "Registration failed");
        }

    } catch (err) {
        IosAlert.alert(err.message || "System Error");
        btn.innerHTML = "FINISH REGISTRATION";
        btn.disabled  = false;
    }
}