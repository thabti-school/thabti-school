const ADMIN_PASSWORD = 'Thabti@2023';
let records = [];
let userRole = null;
let idCardFile = null;
let appointmentLetterFile = null;

const formView = document.getElementById('formView');
const successView = document.getElementById('successView');
const recordsView = document.getElementById('recordsView');
const loginView = document.getElementById('loginView');
const passwordView = document.getElementById('passwordView');
const leaveForm = document.getElementById('leaveForm');
const submitBtn = document.getElementById('submitBtn');
const submitBtnText = document.getElementById('submitBtnText');
const submitBtnLoading = document.getElementById('submitBtnLoading');
const backFromFormBtn = document.getElementById('backFromFormBtn');
const newRequestBtn = document.getElementById('newRequestBtn');
const backToLoginFromSuccessBtn = document.getElementById('backToLoginFromSuccessBtn');
const submittedInfo = document.getElementById('submittedInfo');
const guardianLoginBtn = document.getElementById('guardianLoginBtn');
const adminLoginBtn = document.getElementById('adminLoginBtn');
const adminLogoutBtn = document.getElementById('adminLogoutBtn');
const adminRecordsList = document.getElementById('adminRecordsList');
const searchInput = document.getElementById('searchInput');
const filterStatus = document.getElementById('filterStatus');
const totalRequests = document.getElementById('totalRequests');
const pendingRequests = document.getElementById('pendingRequests');
const approvedRequests = document.getElementById('approvedRequests');
const whatsappOpenedRequests = document.getElementById('whatsappOpenedRequests');
const adminPasswordForm = document.getElementById('adminPasswordForm');
const adminPassword = document.getElementById('adminPassword');
const passwordError = document.getElementById('passwordError');
const backFromPasswordBtn = document.getElementById('backFromPasswordBtn');
const printBtn = document.getElementById('printBtn');

const idCardBtn = document.getElementById('idCardBtn');
const idCardFileInput = document.getElementById('idCardFile');
const idCardText = document.getElementById('idCardText');
const idCardPreview = document.getElementById('idCardPreview');
const idCardFileName = document.getElementById('idCardFileName');
const idCardRemove = document.getElementById('idCardRemove');

const appointmentLetterBtn = document.getElementById('appointmentLetterBtn');
const appointmentLetterFileInput = document.getElementById('appointmentLetterFile');
const appointmentLetterText = document.getElementById('appointmentLetterText');
const appointmentLetterPreview = document.getElementById('appointmentLetterPreview');
const appointmentLetterFileName = document.getElementById('appointmentLetterFileName');
const appointmentLetterRemove = document.getElementById('appointmentLetterRemove');

function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
  const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';

  toast.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-2 z-50`;
  toast.innerHTML = `<span class="text-lg">${icon}</span><span>${message}</span>`;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-20px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function showView(view) {
  loginView.classList.add('hidden');
  formView.classList.add('hidden');
  successView.classList.add('hidden');
  recordsView.classList.add('hidden');
  passwordView.classList.add('hidden');

  if (view === 'login') loginView.classList.remove('hidden');
  if (view === 'form') formView.classList.remove('hidden');
  if (view === 'success') successView.classList.remove('hidden');
  if (view === 'records') recordsView.classList.remove('hidden');

  if (view === 'password') {
    passwordView.classList.remove('hidden');
    adminPassword.value = '';
    passwordError.classList.add('hidden');
  }
}

async function fetchRecords() {
  const response = await fetch('api.php?action=list');
  const result = await response.json();

  if (!result.success) {
    throw new Error(result.message || 'تعذر تحميل السجلات');
  }

  records = result.data || [];
}

function updateStatistics() {
  totalRequests.textContent = records.length;
  pendingRequests.textContent = records.filter(r => r.status === 'معلق').length;
  approvedRequests.textContent = records.filter(r => r.status === 'موافق عليه').length;

  if (whatsappOpenedRequests) {
    whatsappOpenedRequests.textContent = records.filter(r => Number(r.whatsapp_opened) === 1).length;
  }
}

function fileBadge(path, label) {
  if (!path) return '';
  return `<a href="${path}" target="_blank" class="px-3 py-1 bg-blue-200 text-blue-800 rounded-full text-xs font-semibold">${label}</a>`;
}

function normalizePhone(phone) {
  let cleaned = String(phone || '').replace(/\D/g, '');

  if (cleaned.startsWith('968')) {
    return cleaned;
  }

  if (cleaned.startsWith('0')) {
    cleaned = cleaned.substring(1);
  }

  if (cleaned.length === 8) {
    cleaned = '968' + cleaned;
  }

  return cleaned;
}

function openWhatsApp(phone, message) {
  const cleaned = normalizePhone(phone);

  if (!cleaned) {
    showToast('رقم الهاتف غير صالح', 'error');
    return;
  }

  const url = `https://wa.me/${cleaned}?text=${encodeURIComponent(message)}`;
  window.open(url, '_blank');
}

function buildApproveMessage(record) {
  return `مرحباً،
تمت الموافقة على طلب الاستئذان الخاص بالطالبة ${record.student_name}.

الصف: ${record.grade}
الشعبة: ${record.section}
وقت الخروج: ${record.exit_time}
سبب الاستئذان: ${record.reason}
اسم المستلم: ${record.receiver_name}
صلة القرابة: ${record.relationship}

نشكر تواصلكم مع إدارة المدرسة.`;
}

function buildRejectMessage(record) {
  return `مرحباً،
نود إشعاركم بأنه تم رفض طلب الاستئذان الخاص بالطالبة ${record.student_name}.

الصف: ${record.grade}
الشعبة: ${record.section}
وقت الخروج المطلوب: ${record.exit_time}
سبب الاستئذان: ${record.reason}

للاستفسار يرجى التواصل مع إدارة المدرسة.`;
}

function buildDirectMessage(record) {
  if (record.status === 'موافق عليه') {
    return buildApproveMessage(record);
  }

  if (record.status === 'مرفوض') {
    return buildRejectMessage(record);
  }

  return `مرحباً،
هذا إشعار بخصوص طلب الاستئذان للطالبة ${record.student_name}.

الصف: ${record.grade}
الشعبة: ${record.section}
وقت الخروج: ${record.exit_time}
سبب الاستئذان: ${record.reason}
اسم المستلم: ${record.receiver_name}
صلة القرابة: ${record.relationship}

يرجى متابعة الطلب مع إدارة المدرسة.`;
}

function copyMessageToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    showToast('تم نسخ الرسالة', 'success');
  }).catch(() => {
    showToast('تعذر نسخ الرسالة', 'error');
  });
}

function showWhatsAppModal(record, actionType) {
  if (!record || !record.phone) {
    showToast('رقم الهاتف غير متوفر', 'error');
    return;
  }

  const message = actionType === 'approve'
    ? buildApproveMessage(record)
    : actionType === 'reject'
      ? buildRejectMessage(record)
      : buildDirectMessage(record);

  const oldModal = document.getElementById('whatsappModalOverlay');
  if (oldModal) oldModal.remove();

  const modal = document.createElement('div');
  modal.id = 'whatsappModalOverlay';
  modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
  modal.innerHTML = `
    <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-lg w-full" dir="rtl">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-800">
          ${actionType === 'approve' ? 'رسالة الموافقة' : actionType === 'reject' ? 'رسالة الرفض' : 'رسالة واتساب'}
        </h2>
        <button id="closeWhatsAppModalX" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">×</button>
      </div>

      <div class="mb-4 p-4 bg-slate-50 rounded-lg border border-slate-200 max-h-64 overflow-y-auto">
        <p class="text-sm text-gray-700 whitespace-pre-wrap">${message}</p>
      </div>

      <div class="grid grid-cols-2 gap-3 mb-4">
        <button id="copyWhatsAppMessageBtn"
          class="p-3 rounded-lg bg-blue-500 hover:bg-blue-600 text-white font-semibold transition-colors">
          نسخ الرسالة
        </button>

        <button id="openWhatsAppBtn"
          class="p-3 rounded-lg bg-green-500 hover:bg-green-600 text-white font-semibold transition-colors">
          فتح واتساب
        </button>
      </div>

      <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg mb-4">
        <p class="text-xs text-amber-800">
          سيتم فتح واتساب ويب مع رسالة جاهزة. بعد ذلك اضغطي إرسال من واتساب.
        </p>
      </div>

      <button id="closeWhatsAppModalBtn"
        class="w-full p-3 rounded-lg bg-slate-200 hover:bg-slate-300 text-gray-800 font-semibold transition-colors">
        إغلاق
      </button>
    </div>
  `;

  document.body.appendChild(modal);

  document.getElementById('copyWhatsAppMessageBtn').addEventListener('click', () => {
    copyMessageToClipboard(message);
  });

  document.getElementById('openWhatsAppBtn').addEventListener('click', () => {
    openWhatsApp(record.phone, message);
  });

  document.getElementById('closeWhatsAppModalBtn').addEventListener('click', () => {
    modal.remove();
  });

  document.getElementById('closeWhatsAppModalX').addEventListener('click', () => {
    modal.remove();
  });

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

function whatsappStatusBadge(record) {
  if (record.status === 'معلق') {
    return '<span class="px-3 py-1 bg-slate-200 text-slate-700 rounded-full text-xs font-semibold">بانتظار القرار</span>';
  }

  if (Number(record.whatsapp_opened) === 1) {
    const statusColor = record.status === 'موافق عليه'
      ? 'bg-green-200 text-green-800'
      : 'bg-red-200 text-red-800';

    const statusText = record.status === 'موافق عليه'
      ? 'تم فتح رسالة واتساب للموافقة'
      : 'تم فتح رسالة واتساب للرفض';

    return `<span class="px-3 py-1 ${statusColor} rounded-full text-xs font-semibold">${statusText}</span>`;
  }

  return '';
}

function getRecordById(id) {
  return records.find(r => Number(r.id) === Number(id));
}

function renderAdminRecordsList() {
  updateStatistics();

  const searchTerm = searchInput.value.toLowerCase().trim();
  const statusFilter = filterStatus.value;

  const filtered = records.filter(r => {
    const matchName = !searchTerm || (r.student_name || '').toLowerCase().includes(searchTerm);
    const matchStatus = !statusFilter || r.status === statusFilter;
    return matchName && matchStatus;
  });

  if (filtered.length === 0) {
    adminRecordsList.innerHTML = '<p class="text-center text-slate-400 py-8">لا توجد طلبات</p>';
    return;
  }

  adminRecordsList.innerHTML = filtered.map(record => {
    const isPending = record.status === 'معلق';
    const hasAttachments = record.id_card_file || record.appointment_letter_file;

    return `
      <div class="record-card bg-slate-50 rounded-2xl p-4 border border-slate-100">
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p class="text-xs text-slate-500 mb-1">الطالب</p>
            <h3 class="font-bold text-gray-800">${record.student_name || ''}</h3>
            <p class="text-sm text-slate-600">${record.grade || ''} - ${record.section || ''}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500 mb-1">رقم الهاتف</p>
            <h3 class="font-bold text-gray-800">${record.phone || ''}</h3>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
          <div><span class="text-slate-500">السبب:</span> <span class="font-medium">${record.reason || ''}</span></div>
          <div><span class="text-slate-500">وقت الخروج:</span> <span class="font-medium">${record.exit_time || ''}</span></div>
          <div><span class="text-slate-500">المستلم:</span> <span class="font-medium">${record.receiver_name || ''}</span></div>
          <div><span class="text-slate-500">الصلة:</span> <span class="font-medium">${record.relationship || ''}</span></div>
        </div>

        <div class="mb-4 flex gap-2 flex-wrap">
          ${whatsappStatusBadge(record)}
        </div>

        ${hasAttachments ? `
          <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <p class="text-xs text-blue-700 font-semibold mb-2">📎 المرفقات:</p>
            <div class="flex gap-2 flex-wrap">
              ${fileBadge(record.id_card_file, '📷 البطاقة الشخصية')}
              ${fileBadge(record.appointment_letter_file, '📄 رسالة الموعد')}
            </div>
          </div>
        ` : ''}

        <div class="flex gap-2 pt-4 border-t border-slate-200 flex-wrap">
          ${isPending ? `
            <button onclick="approveRequest(${record.id})"
              class="flex-1 p-2 rounded-lg bg-green-500 hover:bg-green-600 text-white text-sm font-semibold transition-colors">
              موافقة
            </button>

            <button onclick="rejectRequest(${record.id})"
              class="flex-1 p-2 rounded-lg bg-red-500 hover:bg-red-600 text-white text-sm font-semibold transition-colors">
              رفض
            </button>
          ` : `
            <button onclick="reopenWhatsApp(${record.id})"
              class="flex-1 p-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold transition-colors">
              إعادة فتح واتساب
            </button>
          `}

          <button onclick="sendWhatsAppDirectly(${record.id})"
            class="flex-1 p-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-semibold transition-colors">
            إرسال واتساب
          </button>

          <button onclick="deleteRecord(${record.id})"
            class="flex-1 p-2 rounded-lg bg-slate-300 hover:bg-slate-400 text-gray-800 text-sm font-semibold transition-colors">
            حذف
          </button>
        </div>
      </div>
    `;
  }).join('');
}

window.approveRequest = async function(id) {
  try {
    const response = await fetch('api.php?action=approve', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || 'فشل تنفيذ الموافقة');
    }

    await fetchRecords();
    renderAdminRecordsList();

    const record = getRecordById(id);
    if (record) {
      showWhatsAppModal(record, 'approve');
    }

    showToast('تمت الموافقة على الطلب', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
};

window.rejectRequest = async function(id) {
  try {
    const response = await fetch('api.php?action=reject', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || 'فشل تنفيذ الرفض');
    }

    await fetchRecords();
    renderAdminRecordsList();

    const record = getRecordById(id);
    if (record) {
      showWhatsAppModal(record, 'reject');
    }

    showToast('تم رفض الطلب', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
};

window.reopenWhatsApp = function(id) {
  const record = getRecordById(id);

  if (!record) {
    showToast('تعذر العثور على السجل', 'error');
    return;
  }

  if (!record.phone) {
    showToast('رقم الهاتف غير متوفر', 'error');
    return;
  }

  if (record.status === 'معلق') {
    showToast('لا يمكن فتح واتساب قبل اتخاذ القرار', 'error');
    return;
  }

  const actionType = record.status === 'موافق عليه' ? 'approve' : 'reject';
  showWhatsAppModal(record, actionType);
};

window.sendWhatsAppDirectly = function(id) {
  const record = getRecordById(id);

  if (!record) {
    showToast('تعذر العثور على السجل', 'error');
    return;
  }

  if (!record.phone) {
    showToast('رقم الهاتف غير متوفر', 'error');
    return;
  }

  showWhatsAppModal(record, 'direct');
};

window.deleteRecord = async function(id) {
  if (!confirm('هل تريد حذف هذا السجل؟')) return;

  try {
    const response = await fetch('api.php?action=delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || 'فشل حذف السجل');
    }

    await fetchRecords();
    renderAdminRecordsList();
    showToast('تم حذف السجل', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
};

idCardBtn.addEventListener('click', e => {
  e.preventDefault();
  idCardFileInput.click();
});

idCardFileInput.addEventListener('change', e => {
  const file = e.target.files[0];
  if (file) {
    idCardFile = file;
    idCardText.textContent = file.name;
    idCardFileName.textContent = `✓ ${file.name}`;
    idCardPreview.classList.remove('hidden');
  }
});

idCardRemove.addEventListener('click', e => {
  e.preventDefault();
  idCardFile = null;
  idCardFileInput.value = '';
  idCardText.textContent = 'اختر صورة البطاقة';
  idCardPreview.classList.add('hidden');
});

appointmentLetterBtn.addEventListener('click', e => {
  e.preventDefault();
  appointmentLetterFileInput.click();
});

appointmentLetterFileInput.addEventListener('change', e => {
  const file = e.target.files[0];
  if (file) {
    appointmentLetterFile = file;
    appointmentLetterText.textContent = file.name;
    appointmentLetterFileName.textContent = `✓ ${file.name}`;
    appointmentLetterPreview.classList.remove('hidden');
  }
});

appointmentLetterRemove.addEventListener('click', e => {
  e.preventDefault();
  appointmentLetterFile = null;
  appointmentLetterFileInput.value = '';
  appointmentLetterText.textContent = 'اختر رسالة الموعد';
  appointmentLetterPreview.classList.add('hidden');
});

leaveForm.addEventListener('submit', async e => {
  e.preventDefault();

  const formData = new FormData();
  formData.append('student_name', document.getElementById('studentName').value.trim());
  formData.append('grade', document.getElementById('grade').value.trim());
  formData.append('section', document.getElementById('section').value.trim());
  formData.append('phone', document.getElementById('phone').value.trim());
  formData.append('reason', document.getElementById('reason').value.trim());
  formData.append('exit_time', document.getElementById('exitTime').value.trim());
  formData.append('receiver_name', document.getElementById('receiverName').value.trim());
  formData.append('relationship', document.getElementById('relationship').value.trim());

  if (idCardFileInput.files[0]) {
    formData.append('id_card_file', idCardFileInput.files[0]);
  }

  if (appointmentLetterFileInput.files[0]) {
    formData.append('appointment_letter_file', appointmentLetterFileInput.files[0]);
  }

  submitBtn.disabled = true;
  submitBtnText.classList.add('hidden');
  submitBtnLoading.classList.remove('hidden');

  try {
    const response = await fetch('api.php?action=create', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || 'فشل الإرسال');
    }

    submittedInfo.innerHTML = `
      <div class="flex justify-between"><span class="text-slate-500">الطالب:</span><span class="font-semibold">${document.getElementById('studentName').value.trim()}</span></div>
      <div class="flex justify-between"><span class="text-slate-500">الصف:</span><span class="font-semibold">${document.getElementById('grade').value.trim()} - ${document.getElementById('section').value.trim()}</span></div>
      <div class="flex justify-between"><span class="text-slate-500">السبب:</span><span class="font-semibold">${document.getElementById('reason').value.trim()}</span></div>
      <div class="flex justify-between"><span class="text-slate-500">وقت الخروج:</span><span class="font-semibold">${document.getElementById('exitTime').value.trim()}</span></div>
      <div class="flex justify-between"><span class="text-slate-500">المستلم:</span><span class="font-semibold">${document.getElementById('receiverName').value.trim()} (${document.getElementById('relationship').value.trim()})</span></div>
    `;

    leaveForm.reset();
    idCardFile = null;
    appointmentLetterFile = null;
    idCardFileInput.value = '';
    appointmentLetterFileInput.value = '';
    idCardText.textContent = 'اختر صورة البطاقة';
    appointmentLetterText.textContent = 'اختر رسالة الموعد';
    idCardPreview.classList.add('hidden');
    appointmentLetterPreview.classList.add('hidden');

    showView('success');
    showToast('تم تقديم الطلب بنجاح', 'success');

    await fetchRecords();
  } catch (error) {
    showToast(error.message, 'error');
  } finally {
    submitBtn.disabled = false;
    submitBtnText.classList.remove('hidden');
    submitBtnLoading.classList.add('hidden');
  }
});

guardianLoginBtn.addEventListener('click', () => {
  userRole = 'guardian';
  showView('form');
});

adminLoginBtn.addEventListener('click', () => showView('password'));
backFromFormBtn.addEventListener('click', () => showView('login'));
backToLoginFromSuccessBtn.addEventListener('click', () => showView('login'));
backFromPasswordBtn.addEventListener('click', () => showView('login'));
newRequestBtn.addEventListener('click', () => showView('form'));

adminLogoutBtn.addEventListener('click', () => {
  userRole = null;
  showView('login');
});

adminPasswordForm.addEventListener('submit', async e => {
  e.preventDefault();

  if (adminPassword.value !== ADMIN_PASSWORD) {
    passwordError.textContent = 'كلمة المرور غير صحيحة!';
    passwordError.classList.remove('hidden');
    return;
  }

  userRole = 'admin';
  showView('records');

  try {
    await fetchRecords();
    renderAdminRecordsList();
  } catch (error) {
    showToast(error.message, 'error');
  }
});

searchInput.addEventListener('input', renderAdminRecordsList);
filterStatus.addEventListener('change', renderAdminRecordsList);

printBtn.addEventListener('click', () => {
  const searchTerm = searchInput.value.toLowerCase().trim();
  const statusFilter = filterStatus.value;

  const filtered = records.filter(r => {
    const matchName = !searchTerm || (r.student_name || '').toLowerCase().includes(searchTerm);
    const matchStatus = !statusFilter || r.status === statusFilter;
    return matchName && matchStatus;
  });

  if (filtered.length === 0) {
    showToast('لا توجد سجلات للطباعة', 'error');
    return;
  }

  const printContent = `
    <html dir="rtl" lang="ar">
    <head>
      <meta charset="UTF-8">
      <title>طباعة سجل الاستئذان</title>
      <style>
        * { font-family: Arial, sans-serif; }
        body { padding: 20px; background: white; color: #333; }
        h1 { text-align: center; color: #1f2937; margin-bottom: 10px; }
        .print-date { text-align: center; color: #6b7280; margin-bottom: 20px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #4f46e5; color: white; padding: 12px; text-align: right; border: 1px solid #e5e7eb; font-weight: bold; }
        td { padding: 10px 12px; border: 1px solid #e5e7eb; text-align: right; }
        tr:nth-child(even) { background: #f9fafb; }
        .status-pending { background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; }
        .status-approved { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; }
        .status-rejected { background: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; }
      </style>
    </head>
    <body>
      <h1>📋 سجل الاستئذان الإلكتروني</h1>
      <p class="print-date">تاريخ الطباعة: ${new Date().toLocaleDateString('ar-SA')} | الوقت: ${new Date().toLocaleTimeString('ar-SA')}</p>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>اسم الطالب</th>
            <th>الصف</th>
            <th>رقم الهاتف</th>
            <th>السبب</th>
            <th>وقت الخروج</th>
            <th>المستلم</th>
            <th>الصلة</th>
            <th>المرفقات</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody>
          ${filtered.map((r, i) => {
            let statusClass = 'status-pending';
            if (r.status === 'موافق عليه') statusClass = 'status-approved';
            else if (r.status === 'مرفوض') statusClass = 'status-rejected';

            return `
              <tr>
                <td>${i + 1}</td>
                <td>${r.student_name}</td>
                <td>${r.grade} - ${r.section}</td>
                <td>${r.phone}</td>
                <td>${r.reason}</td>
                <td>${r.exit_time}</td>
                <td>${r.receiver_name}</td>
                <td>${r.relationship}</td>
                <td>${(r.id_card_file || r.appointment_letter_file) ? '✓ موجودة' : '—'}</td>
                <td><span class="${statusClass}">${r.status}</span></td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
    </body>
    </html>
  `;

  const printWindow = window.open('', '', 'width=1200,height=800');
  printWindow.document.write(printContent);
  printWindow.document.close();

  setTimeout(() => {
    printWindow.focus();
    printWindow.print();
  }, 250);
});