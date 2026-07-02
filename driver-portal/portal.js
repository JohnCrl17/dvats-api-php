/**
 * DVATS: Driver Portal Core JS
 * Version: 2.3.0 (IosAlert Universal + Fixed)
 */

function checkExpiration(expiryDate) {
    const alertDiv = document.getElementById('licenseAlert');
    const msgP = document.getElementById('expiryMessage');
    if (!alertDiv || !msgP) return;
    if (!expiryDate) { alertDiv.classList.add('hidden'); return; }
    const expiry = new Date(expiryDate);
    const today = new Date();
    expiry.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    const diffTime = expiry - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    if (diffDays <= 10) {
        alertDiv.classList.remove('hidden');
        let message = "";
        if (diffDays < 0) {
            message = `Your driver license is EXPIRED (${expiryDate}). Please renew immediately at LTO.`;
            alertDiv.style.backgroundColor = "#fee2e2";
            alertDiv.style.color = "#b91c1c";
        } else if (diffDays === 0) {
            message = `Your license EXPIRES TODAY! (${expiryDate}). Visit LTO now!`;
            alertDiv.style.backgroundColor = "#fff1f2";
            alertDiv.style.color = "#be123c";
        } else {
            const weeks = Math.floor(diffDays / 7);
            const days = diffDays % 7;
            let timeParts = [];
            if (weeks > 0) timeParts.push(`${weeks} week${weeks > 1 ? "s" : ""}`);
            if (days > 0) timeParts.push(`${days} day${days > 1 ? "s" : ""}`);
            let timeText = timeParts.join(" and ");
            message = `Your license expires in ${timeText} (${expiryDate}). Please renew at LTO.`;
            alertDiv.style.backgroundColor = diffDays <= 3 ? "#fff1f2" : "#fef9c3";
            alertDiv.style.color = diffDays <= 3 ? "#be123c" : "#854d0e";
        }
        msgP.textContent = message;
    } else {
        alertDiv.classList.add('hidden');
    }
}

const API_BASE = "https://dvats-api-php.onrender.com";

document.addEventListener('DOMContentLoaded', () => {
    console.log("DVATS: System Initializing...");
    const isLoginPage = document.getElementById('loginForm') !== null;
    if (isLoginPage) { initLoginLogic(); } else { initDashboardLogic(); }
});

function initLoginLogic() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const licenseInput  = document.getElementById('license_no');
        const passwordInput = document.getElementById('password');
        const btn           = document.getElementById('loginBtn');
        const loader        = document.getElementById('loginLoader');
        const licenseVal    = licenseInput  ? licenseInput.value.trim()  : "";
        const passwordVal   = passwordInput ? passwordInput.value.trim() : "";

        if (!licenseVal || !passwordVal) {
            IosAlert.alert('Missing Fields', 'Please fill in all fields.');
            return;
        }

        if (loader) loader.style.display = 'block';
        if (btn)    btn.disabled = true;

        try {
            const response = await fetch(`${API_BASE}/mobile_login.php`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    `license_no=${encodeURIComponent(licenseVal)}&password=${encodeURIComponent(passwordVal)}`
            });
            const res = await response.json();
            if (res.success) {
                localStorage.setItem('driverInfo', JSON.stringify(res.driver));
                window.location.replace('home.html');
            } else {
                IosAlert.alert('Login Failed', res.message || 'Invalid credentials.');
            }
        } catch (err) {
            console.error("Login Error:", err);
            IosAlert.alert('Connection Error', 'Please check your internet connection and try again.');
        } finally {
            if (loader) loader.style.display = 'none';
            if (btn)    btn.disabled = false;
        }
    });
}

function initDashboardLogic() {
    const driverDataString = localStorage.getItem('driverInfo');
    if (!driverDataString || driverDataString === "undefined" || driverDataString === "null") {
        window.location.replace('index.html');
        return;
    }
    try {
        const driver = JSON.parse(driverDataString);
        if (!driver || !driver.client_id) throw new Error("Incomplete session");
        updateProfileUI(driver);
        fetchAllData(driver);
        if (driver.license_expiry) { checkExpiration(driver.license_expiry); }
        const bookingForm = document.getElementById('bookingForm');
        if (bookingForm) { bookingForm.onsubmit = handleBookingSubmit; }
    } catch (e) {
        console.error("Init Error:", e);
        localStorage.removeItem('driverInfo');
        window.location.replace('index.html');
    }
}

async function handleBookingSubmit(e) {
    if (e) e.preventDefault();
    const service = document.getElementById('serviceType')?.value || "Appointment";
    closeBookingModal();
    const confirmed = await IosAlert.confirm(
        'Redirect to LTO Portal',
        `You are being redirected to the Official LTO LTMS Portal for ${service}. Continue?`
    );
    if (confirmed) { window.open('https://portal.lto.gov.ph/login', '_blank'); }
    return false;
}

async function handleProfileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    const driver = JSON.parse(localStorage.getItem('driverInfo'));
    if (!driver?.client_id) return;
    const formData = new FormData();
    formData.append('profile_image', file);
    formData.append('client_id', driver.client_id);
    try {
        const response = await fetch(`${API_BASE}/upload_profile.php`, { method: 'POST', body: formData });
        const result   = await response.json();
        if (result.success) {
            const display  = document.getElementById('profileDisplay');
            const icon     = document.getElementById('defaultUserIcon');
            const fullPath = result.new_path.startsWith('http') ? result.new_path : `${API_BASE}/${result.new_path}`;
            setProfileImage(display, icon, fullPath);
            driver.profile_path = result.new_path;
            localStorage.setItem('driverInfo', JSON.stringify(driver));
            IosAlert.toast('Profile photo updated!');
        } else {
            IosAlert.alert('Upload Failed', result.message || 'Could not upload photo.');
        }
    } catch (error) {
        console.error("Upload Error:", error);
        IosAlert.alert('Server Error', 'An error occurred during upload. Please try again.');
    }
}

// Centralized profile image setter — handles base64 AND URL correctly
function setProfileImage(display, icon, src) {
    if (!display || !src) return;
    const isBase64 = src.startsWith('data:image');
    const imgSrc   = isBase64 ? src : `${src}${src.includes('?') ? '&' : '?'}t=${Date.now()}`;
    const existingImg = display.querySelector('img.profile-img');
    if (existingImg) {
        existingImg.src = imgSrc;
    } else {
        const img = document.createElement('img');
        img.className  = 'profile-img';
        img.src        = imgSrc;
        img.style.cssText = 'width:100%; height:100%; object-fit:cover; border-radius:9999px; position:absolute; inset:0;';
        img.onerror = () => { img.remove(); if (icon) icon.style.display = ''; };
        display.style.position = 'relative';
        display.style.overflow = 'hidden';
        display.appendChild(img);
    }
    if (icon) icon.style.display = 'none';
}

function toggleNewPass() {
    const input = document.getElementById('fp_newpass');
    const icon  = document.getElementById('eyeNewPass');
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function updateProfileUI(driver) {
    const rawName   = driver.fullname || driver.name || "Driver";
    let firstName   = rawName.includes(',') ? rawName.split(',')[1].trim().split(' ')[0] : rawName.split(' ')[0];
    const elWelcome = document.getElementById('welcomeName');
    const elFull    = document.getElementById('profFullName');
    const elLic     = document.getElementById('profLicense');
    const elDOB     = document.getElementById('profDOB');
    const elExpiry  = document.getElementById('profExpiry');
    if (elWelcome) elWelcome.textContent = firstName.toUpperCase();
    if (elFull)    elFull.textContent    = rawName;
    if (elLic)     elLic.textContent     = driver.license_no || "---";
    if (elDOB)     elDOB.textContent     = driver.date_of_birth || "N/A";
    if (elExpiry) {
        elExpiry.textContent = driver.license_expiry || "N/A";
        const expDate = new Date(driver.license_expiry);
        if (expDate < new Date()) { elExpiry.classList.replace('text-blue-600', 'text-red-500'); }
    }
    const display = document.getElementById('profileDisplay');
    const icon    = document.getElementById('defaultUserIcon');
    if (driver.profile_path && display) {
        const isBase64 = driver.profile_path.startsWith('data:image');
        const isHttp   = driver.profile_path.startsWith('http');
        const src = isBase64 ? driver.profile_path
                  : isHttp   ? driver.profile_path
                  : `${API_BASE}/${driver.profile_path}`;
        setProfileImage(display, icon, src);
    }
    const elQR = document.getElementById('profQR');
    if (elQR && driver.qr_image) {
        elQR.src = driver.qr_image.startsWith('data:image')
            ? driver.qr_image
            : `${API_BASE}/uploads/qrcodes/${driver.qr_image}`;
    }
}

async function fetchAllData(driver) {
    const clientId = driver.client_id;
    console.log("Fetching data for Client ID:", clientId);
    try {
        const response = await fetch(
            `${API_BASE}/mobile_dashboard.php?client_id=${clientId}`,
            { headers: { 'ngrok-skip-browser-warning': 'true' } }
        );
        const data = await response.json();
        console.log("API Response:", data);
        if (data.success) {
            renderViolations(data.violations     || []);
            renderAppointments(data.appointments || []);
            renderNotifications(data.notifications || []);
            updateStats(data.violations || []);
            if (data.driver) {
                const display = document.getElementById('profileDisplay');
                const icon    = document.getElementById('defaultUserIcon');
                const src     = data.driver.face_data || data.driver.profile_path;
                console.log("PROFILE SRC:", src);
                if (src && display) { setProfileImage(display, icon, src); }
                else { console.warn("No profile image found."); }
            }
        } else {
            console.warn("API returned unsuccessful response:", data.error || data.message);
        }
    } catch (error) {
        console.error("FetchAllData Error:", error);
    }
}

function renderViolations(list) {
    const containerIds = ['recentList', 'fullViolationList'];
    const html = list.length === 0
        ? '<div class="text-center py-10 opacity-30 text-[10px] font-bold uppercase">No Violations Found</div>'
        : list.map(v => `
            <div onclick="viewViolationDetails(${JSON.stringify(v).replace(/"/g, '&quot;')})"
            class="card-ios flex items-center justify-between border border-slate-50 cursor-pointer hover:bg-slate-50 transition-all overflow-hidden">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="w-10 h-10 shrink-0 ${v.status?.toUpperCase() === 'PAID' ? 'bg-emerald-50 text-emerald-500' : 'bg-orange-50 text-orange-500'} rounded-2xl flex items-center justify-center">
                    <i class="fas fa-file-invoice text-xs"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h5 class="font-black text-[10px] uppercase truncate">${v.violation_name}</h5>
                    <p class="text-[8px] text-slate-400 font-bold">${v.created_at}</p>
                </div>
            </div>
            <div class="text-right shrink-0 min-w-[70px]">
                <p class="font-black text-xs">&#8369;${parseFloat(v.fine_amount || 0).toLocaleString()}</p>
                <span class="text-[7px] font-black uppercase ${v.status?.toUpperCase() === 'PAID' ? 'text-emerald-500' : 'text-orange-500'}">${v.status}</span>
            </div>
            </div>`).join('');
    containerIds.forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = html; });
}

function renderAppointments(list) {
    const container = document.getElementById('appointmentList');
    if (!container) return;
    container.innerHTML = list.length === 0
        ? '<div class="text-center py-10 opacity-30 text-[10px] font-bold uppercase">No Scheduled Appointments</div>'
        : list.map(app => `
            <div class="card-ios flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center"><i class="fas fa-calendar-day text-xs"></i></div>
                    <div>
                        <h5 class="font-black text-slate-700 uppercase text-[10px]">${app.purpose}</h5>
                        <p class="text-[8px] font-bold text-blue-600 uppercase italic">${app.appointment_date}</p>
                    </div>
                </div>
                <span class="px-3 py-1 bg-slate-50 text-slate-400 rounded-full text-[7px] font-black uppercase">${app.status}</span>
            </div>`).join('');
}

function renderNotifications(list) {
    const container = document.getElementById('notifList');
    const dot       = document.getElementById('notifDot');
    if (!container) return;
    if (list.length === 0) {
        container.innerHTML = '<div class="py-20 text-center text-slate-400 text-[10px] font-bold uppercase">Inbox is empty</div>';
        if (dot) dot.classList.add('hidden');
        return;
    }
    if (dot) dot.classList.remove('hidden');
    container.innerHTML = list.map(n => `
        <div class="card-ios flex gap-4 items-start ${n.is_read == 0 ? 'border-l-4 border-blue-500' : 'opacity-80'}">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center flex-shrink-0"><i class="fas fa-bell text-xs"></i></div>
            <div class="flex-1">
                <h4 class="font-bold text-[11px] text-slate-800 uppercase">${n.title}</h4>
                <p class="text-[10px] text-slate-500 mt-1">${n.description}</p>
                <span class="text-[8px] font-black text-slate-300 uppercase mt-2 block">${n.created_at}</span>
            </div>
        </div>`).join('');
}

function openBookingModal() {
    const modal   = document.getElementById('bookingModal');
    const content = document.getElementById('bookingContent');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    setTimeout(() => { if (content) content.classList.add('active'); }, 10);
}

function closeBookingModal() {
    const modal   = document.getElementById('bookingModal');
    const content = document.getElementById('bookingContent');
    if (content) content.classList.remove('active');
    setTimeout(() => { if (modal) { modal.classList.add('hidden'); modal.style.display = 'none'; } }, 300);
}

function toggleQRModal(show) {
    const modal   = document.getElementById('qrModal');
    const content = document.getElementById('qrModalContent');
    if (!modal || !content) return;
    if (show) {
        modal.classList.remove('hidden');
        setTimeout(() => { content.classList.remove('scale-90', 'opacity-0'); content.classList.add('scale-100', 'opacity-100'); }, 10);
    } else {
        content.classList.remove('scale-100', 'opacity-100');
        content.classList.add('scale-90', 'opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); }, 300);
    }
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    const target = document.getElementById(tabId);
    if (target) target.classList.add('active');
    document.querySelectorAll('.nav-item').forEach(n => {
        n.classList.remove('active');
        if (n.id === `nav-${tabId}`) n.classList.add('active');
    });
}

function updateStats(violations) {
    let unpaidTotal = 0;
    violations.forEach(v => { if (v.status?.toUpperCase() !== 'PAID') unpaidTotal += parseFloat(v.fine_amount || 0); });
    const elFines = document.getElementById('totalFines');
    const elCount = document.getElementById('countViolations');
    if (elFines) elFines.textContent = `₱ ${unpaidTotal.toLocaleString()}.00`;
    if (elCount) elCount.textContent = violations.length;
}

function viewViolationDetails(v) {
    const modalBody = document.getElementById('modalBody');
    document.getElementById('modalTicketRef').textContent = `TICKET #${v.id}`;
    modalBody.innerHTML = `
        <div class="space-y-2">
            <p class="text-xs font-bold text-slate-400 uppercase">Violation</p>
            <p class="font-black text-sm">${v.violation_name}</p>
            <p class="text-xs font-bold text-slate-400 uppercase mt-4">Fine Amount</p>
            <p class="font-black text-lg">&#8369;${parseFloat(v.fine_amount).toLocaleString()}</p>
        </div>
        <div class="mt-6">
            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Driver's Concern / Comment</label>
            <textarea id="driverComment" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-xs outline-none" rows="3" placeholder="Enter your concern here..."></textarea>
            <button onclick="submitComment(${v.id})" class="mt-3 w-full py-3 bg-blue-600 text-white rounded-xl text-xs font-bold">Submit Comment</button>
        </div>`;
    document.getElementById('ticketModal').style.display = 'flex';
    setTimeout(() => { document.getElementById('ticketModalContent').classList.add('active'); }, 10);
}

async function submitComment(violationId) {
    const comment = document.getElementById('driverComment').value.trim();
    if (!comment) {
        IosAlert.alert('Empty Comment', 'Please enter a comment before submitting.');
        return;
    }
    try {
        const response = await fetch(`${API_BASE}/submit_comment.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ violation_id: violationId, comment: comment })
        });
        const res = await response.json();
        if (res.success) {
            IosAlert.toast('Comment submitted successfully!');
            closeTicketModal();
        } else {
            IosAlert.alert('Failed', 'Could not submit comment. Please try again.');
        }
    } catch (err) {
        console.error("COMMENT ERROR:", err);
        IosAlert.alert('Server Error', 'Connection failed. Please try again.');
    }
}

function openForgotModal()  { document.getElementById('forgotModal').style.display = 'flex'; }
function closeForgotModal() { document.getElementById('forgotModal').style.display = 'none'; }

async function sendOTP() {
    const license = document.getElementById('fp_license').value.trim();
    const email   = document.getElementById('fp_email').value.trim();
    const btn     = document.getElementById('btnSendOTP');
    const text    = document.getElementById('btnText');
    if (!license || !email) { showToast('Please fill in all fields.', 'error'); return; }
    btn.disabled  = true;
    text.innerHTML = '<span class="flex items-center gap-2 justify-center"><svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4"/><path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v8z"/></svg>Sending OTP...</span>';
    try {
        const res  = await fetch(`${API_BASE}/forgot_password.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ license_no: license, email: email }) });
        const data = await res.json();
        if (data.success) {
            showToast('OTP sent! Check your email.', 'success');
            const otpSec = document.getElementById('otpSection');
            otpSec.classList.remove('hidden');
            otpSec.style.opacity   = '0';
            otpSec.style.transform = 'translateY(10px)';
            otpSec.style.transition = 'all 0.4s ease';
            setTimeout(() => { otpSec.style.opacity = '1'; otpSec.style.transform = 'translateY(0)'; }, 10);
            btn.disabled   = true;
            text.innerHTML = '<span class="flex items-center gap-1 justify-center"><i class="fas fa-check"></i> OTP Sent</span>';
            btn.classList.replace('bg-blue-600', 'bg-green-600');
        } else {
            showToast(data.message || 'Failed to send OTP.', 'error');
            btn.disabled   = false;
            text.innerHTML = 'Send OTP';
        }
    } catch (err) {
        showToast('Connection error. Try again.', 'error');
        btn.disabled   = false;
        text.innerHTML = 'Send OTP';
    }
}

async function verifyOTP() {
    const license = document.getElementById('fp_license').value.trim();
    const otp     = document.getElementById('fp_otp').value.trim();
    const newpass = document.getElementById('fp_newpass').value.trim();
    const btn     = document.getElementById('btnVerifyOTP');
    if (!otp)                          { showToast('Please enter the OTP.', 'error'); return; }
    if (!newpass || newpass.length < 6) { showToast('Password must be at least 6 characters.', 'error'); return; }
    btn.disabled   = true;
    btn.innerHTML  = '<span class="flex items-center gap-2 justify-center"><svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="white" stroke-width="4"/><path class="opacity-75" fill="white" d="M4 12a8 8 0 018-8v8z"/></svg>Verifying...</span>';
    try {
        const res  = await fetch(`${API_BASE}/reset_password.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ license_no: license, otp: otp, new_password: newpass }) });
        const data = await res.json();
        if (data.success) {
            btn.innerHTML = '<span class="flex items-center gap-2 justify-center"><i class="fas fa-check-circle"></i> Password Reset!</span>';
            btn.classList.replace('bg-green-600', 'bg-emerald-500');
            showToast('Password reset successfully!', 'success');
            setTimeout(() => { closeForgotModal(); resetForgotModal(); }, 1500);
        } else {
            showToast(data.message || 'Invalid OTP or expired.', 'error');
            btn.disabled  = false;
            btn.innerHTML = 'Verify & Reset';
        }
    } catch (err) {
        showToast('Connection error. Try again.', 'error');
        btn.disabled  = false;
        btn.innerHTML = 'Verify & Reset';
    }
}

function resetForgotModal() {
    document.getElementById('fp_license').value = '';
    document.getElementById('fp_email').value   = '';
    document.getElementById('fp_otp').value     = '';
    document.getElementById('fp_newpass').value = '';
    const otpSec = document.getElementById('otpSection');
    otpSec.classList.add('hidden');
    otpSec.style.opacity   = '1';
    otpSec.style.transform = 'none';
    const btn  = document.getElementById('btnSendOTP');
    const text = document.getElementById('btnText');
    btn.disabled = false;
    btn.classList.replace('bg-green-600', 'bg-blue-600');
    text.innerHTML = 'Send OTP';
    const verifyBtn = document.getElementById('btnVerifyOTP');
    if (verifyBtn) {
        verifyBtn.disabled  = false;
        verifyBtn.innerHTML = 'Verify & Reset';
        verifyBtn.classList.replace('bg-emerald-500', 'bg-green-600');
    }
}

function showToast(message, type = 'info') {
    const existing = document.getElementById('dvatsToast');
    if (existing) existing.remove();
    const colors = { success: 'bg-emerald-500', error: 'bg-red-500', info: 'bg-blue-500' };
    const toast  = document.createElement('div');
    toast.id        = 'dvatsToast';
    toast.className = `fixed top-5 left-1/2 -translate-x-1/2 z-[99999] px-5 py-3 rounded-2xl text-white text-xs font-bold shadow-xl flex items-center gap-2 ${colors[type]} transition-all`;
    toast.style.opacity   = '0';
    toast.style.transform = 'translateX(-50%) translateY(-10px)';
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.transition = 'all 0.3s ease'; toast.style.opacity = '1'; toast.style.transform = 'translateX(-50%) translateY(0)'; }, 10);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(-50%) translateY(-10px)'; setTimeout(() => toast.remove(), 300); }, 3000);
}

function closeTicketModal() {
    const modal   = document.getElementById('ticketModal');
    const content = document.getElementById('ticketModalContent');
    if (content) content.classList.remove('active');
    setTimeout(() => { if (modal) modal.style.display = 'none'; }, 300);
}

function openHelpCenter() { window.open('https://lto.gov.ph/contact-us.html', '_blank'); }

// ✅ Logout — using IosAlert.confirm
async function logout() {
    const confirmed = await IosAlert.confirm(
        'Logout',
        'Are you sure you want to logout?',
        {
            confirmText:  'Logout',
            cancelText:   'Cancel',
            confirmStyle: 'danger',
            icon:         'danger',
        }
    );
    if (confirmed) {
        localStorage.clear();
        window.location.replace('index.html');
    }
}