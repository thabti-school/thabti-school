// =======================
// إعدادات عامة
// =======================

const API_URL = "api.php";

// =======================
// تحميل الطلبات
// =======================

async function loadRequests() {
    try {
        const res = await fetch(`${API_URL}?action=list`);
        const data = await res.json();

        const container = document.getElementById("adminRecordsList");
        container.innerHTML = "";

        if (!data.success || data.data.length === 0) {
            container.innerHTML = `<p class="text-center text-slate-400 py-8">لا توجد طلبات حتى الآن</p>`;
            return;
        }

        data.data.forEach(request => {
            container.innerHTML += renderRequestCard(request);
        });

        updateStats(data.data);

    } catch (err) {
        console.error(err);
    }
}

// =======================
// تصميم الكارد
// =======================

function renderRequestCard(request) {
    return `
    <div class="p-4 bg-white rounded-xl shadow border">

        <div class="flex justify-between mb-2">
            <h3 class="font-bold">${request.student_name}</h3>
            <span class="text-sm text-gray-500">${request.status}</span>
        </div>

        <p>الصف: ${request.grade} / ${request.section}</p>
        <p>وقت الخروج: ${request.exit_time}</p>
        <p>السبب: ${request.reason}</p>
        <p>المستلم: ${request.receiver_name}</p>
        <p>الهاتف: ${request.phone}</p>

        <div class="flex gap-2 mt-4">

            <button onclick='approveRequestFull(${JSON.stringify(request)})'
                class="bg-green-600 text-white px-3 py-2 rounded-lg">
                موافقة + واتساب
            </button>

            <button onclick='rejectRequestFull(${JSON.stringify(request)})'
                class="bg-red-500 text-white px-3 py-2 rounded-lg">
                رفض + واتساب
            </button>

        </div>
    </div>
    `;
}

// =======================
// الموافقة الكاملة
// =======================

async function approveRequestFull(request) {
    try {
        const res = await fetch(`${API_URL}?action=approve`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: request.id })
        });

        const result = await res.json();

        if (!result.success) {
            alert("فشل التحديث");
            return;
        }

        const msg = buildApprovalMessage(request);
        openWhatsApp(request.phone, msg);

        loadRequests();

    } catch (err) {
        alert("خطأ");
    }
}

// =======================
// الرفض الكامل
// =======================

async function rejectRequestFull(request) {
    try {
        await fetch(`${API_URL}?action=reject`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: request.id })
        });

        const msg = buildRejectMessage(request);
        openWhatsApp(request.phone, msg);

        loadRequests();

    } catch (err) {
        alert("خطأ");
    }
}

// =======================
// رسالة الموافقة
// =======================

function buildApprovalMessage(d) {
    return `مرحبًا،
تمت الموافقة على طلب الاستئذان للطالبة ${d.student_name}

الصف: ${d.grade}
الشعبة: ${d.section}
وقت الخروج: ${d.exit_time}
السبب: ${d.reason}

المستلم: ${d.receiver_name}
صلة القرابة: ${d.relationship}

شكرًا لتعاونكم 🌷`;
}

// =======================
// رسالة الرفض
// =======================

function buildRejectMessage(d) {
    return `مرحبًا،
نعتذر تم رفض طلب الاستئذان للطالبة ${d.student_name}

يرجى مراجعة إدارة المدرسة.

شكرًا لكم 🌷`;
}

// =======================
// فتح واتساب
// =======================

function openWhatsApp(phone, message) {

    if (!phone) {
        alert("لا يوجد رقم");
        return;
    }

    phone = phone.replace(/\D/g, "");

    if (phone.startsWith("0")) {
        phone = "968" + phone.substring(1);
    }

    if (!phone.startsWith("968")) {
        phone = "968" + phone;
    }

    const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;

    window.open(url, "_blank");
}

// =======================
// تحديث الإحصائيات
// =======================

function updateStats(data) {

    let total = data.length;
    let pending = data.filter(r => r.status === "pending").length;
    let approved = data.filter(r => r.status === "approved").length;

    document.getElementById("totalRequests").innerText = total;
    document.getElementById("pendingRequests").innerText = pending;
    document.getElementById("approvedRequests").innerText = approved;
}

// =======================
// منع sleep (Ping)
// =======================

setInterval(() => {
    fetch("ping.php").catch(() => {});
}, 4 * 60 * 1000);

// =======================
// بدء التشغيل
// =======================

window.onload = () => {
    loadRequests();
};
