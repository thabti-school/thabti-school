<!doctype html>
<html lang="ar" dir="rtl" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>سجل الاستئذان الإلكتروني</title>
  <link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2563eb">
<link rel="apple-touch-icon" href="icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="استئذان">
  <script src="https://cdn.tailwindcss.com/3.4.17"></script>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Noto Kufi Arabic', sans-serif; }
    body { box-sizing: border-box; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    @keyframes checkmark { 0% { stroke-dashoffset: 100; } 100% { stroke-dashoffset: 0; } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .animate-scale-in { animation: scaleIn 0.3s ease-out forwards; }
    .checkmark-circle { stroke-dasharray: 166; stroke-dashoffset: 166; animation: checkmark 0.6s ease-out 0.2s forwards; }
    .checkmark-check { stroke-dasharray: 48; stroke-dashoffset: 48; animation: checkmark 0.3s ease-out 0.8s forwards; }
    .input-field { transition: all 0.2s ease; }
    .input-field:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15); }
    .submit-btn { transition: all 0.2s ease; }
    .submit-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3); }
    .submit-btn:active:not(:disabled) { transform: translateY(0); }
    .record-card { transition: all 0.2s ease; }
    .record-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); }
  </style>
</head>
<body class="h-full bg-gradient-to-br from-slate-50 to-slate-200 overflow-auto">
  <div id="app" class="w-full min-h-full flex items-center justify-center p-4 py-8">
    <div id="loginView" class="w-full max-w-md animate-fade-in-up">
      <div class="bg-white rounded-3xl shadow-xl p-8 space-y-6">
        <div class="text-center space-y-3">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg mb-2">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
          </div>
          <h1 class="text-2xl font-bold text-gray-800">نظام الاستئذان</h1>
          <p class="text-sm text-slate-500">اختر نوع الدخول</p>
        </div>
        <div class="space-y-3">
          <button type="button" id="guardianLoginBtn" class="w-full p-4 rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 text-white font-bold text-lg shadow-lg hover:from-blue-700 hover:to-blue-800 transition-all flex items-center justify-center gap-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
            دخول ولي الأمر
          </button>
          <button type="button" id="adminLoginBtn" class="w-full p-4 rounded-xl bg-gradient-to-r from-orange-600 to-orange-700 text-white font-bold text-lg shadow-lg hover:from-orange-700 hover:to-orange-800 transition-all flex items-center justify-center gap-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
            دخول الإدارة
          </button>
        </div>
      </div>
    </div>

    <div id="passwordView" class="w-full max-w-md animate-fade-in-up hidden">
      <div class="bg-white rounded-3xl shadow-xl p-8 space-y-6">
        <div class="text-center space-y-3">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl shadow-lg mb-2">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
          </div>
          <h1 class="text-2xl font-bold text-gray-800">دخول الإدارة</h1>
          <p class="text-sm text-slate-500">أدخل كلمة المرور للوصول</p>
        </div>
        <form id="adminPasswordForm" class="space-y-5">
          <div class="space-y-2">
            <label for="adminPassword" class="block text-sm font-semibold text-gray-700">كلمة المرور</label>
            <input type="password" id="adminPassword" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-orange-500 focus:bg-white outline-none" placeholder="أدخل كلمة المرور">
          </div>
          <div id="passwordError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl text-sm"></div>
          <button type="submit" class="w-full p-4 rounded-xl bg-gradient-to-r from-orange-600 to-orange-700 text-white font-bold text-lg shadow-lg hover:from-orange-700 hover:to-orange-800 transition-all">دخول</button>
        </form>
        <button id="backFromPasswordBtn" class="w-full p-3 rounded-xl border-2 border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          العودة
        </button>
      </div>
    </div>

    <div id="formView" class="w-full max-w-2xl animate-fade-in-up hidden">
      <div class="bg-white rounded-3xl shadow-xl border-0 overflow-hidden">
        <div class="p-8 space-y-6">
          <div class="text-center space-y-3">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg mb-2">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
            <h1 id="mainTitle" class="text-2xl font-bold text-gray-800">سجل الاستئذان الإلكتروني</h1>
            <p id="subtitle" class="text-sm text-slate-500">نظام حديث لتسجيل طلبات خروج الطلاب</p>
          </div>
          <form id="leaveForm" class="space-y-5" enctype="multipart/form-data">
            <div class="space-y-2"><label for="studentName" class="block text-sm font-semibold text-gray-700">اسم الطالبة</label><input type="text" id="studentName" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none" placeholder="أدخل اسم الطالب"></div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2"><label for="grade" class="block text-sm font-semibold text-gray-700">الصف</label><input type="text" id="grade" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none" placeholder="مثال: الخامس"></div>
              <div class="space-y-2"><label for="section" class="block text-sm font-semibold text-gray-700">الشعبة</label><input type="text" id="section" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none" placeholder="مثال: ب"></div>
            </div>
            <div class="space-y-2"><label for="phone" class="block text-sm font-semibold text-gray-700">رقم الهاتف</label><input type="tel" id="phone" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none" placeholder="XXXX XXXX" dir="ltr"></div>
            <div class="space-y-2"><label for="reason" class="block text-sm font-semibold text-gray-700">سبب الاستئذان</label><select id="reason" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none cursor-pointer"><option value="العودة للمنزل">العودة للمنزل</option><option value="موعد مستشفى">موعد مستشفى</option><option value="ظرف طارئ">ظرف طارئ</option><option value="موعد رسمي">موعد رسمي</option><option value="أخرى">أخرى</option></select></div>
            <div class="space-y-2"><label for="exitTime" class="block text-sm font-semibold text-gray-700">وقت الخروج</label><input type="time" id="exitTime" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none"></div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2"><label for="receiverName" class="block text-sm font-semibold text-gray-700">اسم الشخص المستلم</label><input type="text" id="receiverName" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none" placeholder="اسم المستلم"></div>
              <div class="space-y-2"><label for="relationship" class="block text-sm font-semibold text-gray-700">صلة القرابة</label><select id="relationship" required class="input-field w-full p-4 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none cursor-pointer"><option value="">اختر صلة القرابة</option><option value="الأب">الأب</option><option value="الأم">الأم</option><option value="الأخ">الأخ</option><option value="الأخت">الأخت</option><option value="الجد">الجد</option><option value="الجدة">الجدة</option><option value="العم">العم</option><option value="العمة">العمة</option><option value="الخال">الخال</option><option value="الخالة">الخالة</option><option value="أخرى">أخرى</option></select></div>
            </div>
            <div class="space-y-4 pt-4 border-t-2 border-slate-100">
              <p class="text-sm font-semibold text-gray-700">📎 المرفقات الإضافية</p>
              <div class="space-y-2">
                <label for="idCardFile" class="block text-sm font-semibold text-gray-700">📷 صورة البطاقة الشخصية</label>
                <div class="relative">
                  <input type="file" id="idCardFile" accept="image/*,.pdf" class="hidden">
                  <button type="button" id="idCardBtn" class="w-full p-4 rounded-xl border-2 border-dashed border-indigo-300 bg-indigo-50 hover:bg-indigo-100 transition-colors flex items-center justify-center gap-3 text-indigo-600 font-semibold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg><span id="idCardText">اختر صورة البطاقة</span>
                  </button>
                  <div id="idCardPreview" class="mt-2 hidden"><div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-200"><span id="idCardFileName" class="text-sm text-green-700 font-semibold">✓ تم اختيار الملف</span><button type="button" id="idCardRemove" class="text-red-500 hover:text-red-700 font-bold">✕</button></div></div>
                </div>
              </div>
              <div class="space-y-2">
                <label for="appointmentLetterFile" class="block text-sm font-semibold text-gray-700">📄 رسالة الموعد</label>
                <div class="relative">
                  <input type="file" id="appointmentLetterFile" accept="image/*,.pdf,.doc,.docx" class="hidden">
                  <button type="button" id="appointmentLetterBtn" class="w-full p-4 rounded-xl border-2 border-dashed border-purple-300 bg-purple-50 hover:bg-purple-100 transition-colors flex items-center justify-center gap-3 text-purple-600 font-semibold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg><span id="appointmentLetterText">اختر رسالة الموعد</span>
                  </button>
                  <div id="appointmentLetterPreview" class="mt-2 hidden"><div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-200"><span id="appointmentLetterFileName" class="text-sm text-green-700 font-semibold">✓ تم اختيار الملف</span><button type="button" id="appointmentLetterRemove" class="text-red-500 hover:text-red-700 font-bold">✕</button></div></div>
                </div>
              </div>
            </div>
            <button type="submit" id="submitBtn" class="submit-btn w-full p-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold text-lg shadow-lg hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50 disabled:cursor-not-allowed mt-6"><span id="submitBtnText">تقديم الطلب</span><span id="submitBtnLoading" class="hidden">جارٍ الإرسال...</span></button>
          </form>
          <button id="backFromFormBtn" class="w-full p-3 rounded-xl border-2 border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            العودة
          </button>
        </div>
      </div>
    </div>

    <div id="successView" class="w-full max-w-xl hidden">
      <div class="bg-white rounded-3xl shadow-xl p-8 text-center animate-scale-in">
        <div class="mb-6">
          <svg class="w-24 h-24 mx-auto" viewBox="0 0 52 52"><circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none" stroke="#22c55e" stroke-width="2" /><path class="checkmark-check" fill="none" stroke="#22c55e" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M14 27l7 7 16-16" /></svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-3">تم تقديم الطلب بنجاح!</h2>
        <p class="text-slate-500 mb-6">سيتم مراجعة طلبك من قبل الإدارة</p>
        <div id="submittedInfo" class="bg-slate-50 rounded-2xl p-4 mb-6 text-right space-y-2"></div>
        <button id="newRequestBtn" class="w-full p-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold text-lg shadow-lg hover:from-indigo-700 hover:to-purple-700 transition-all mb-3">طلب جديد</button>
        <button id="backToLoginFromSuccessBtn" class="w-full p-3 rounded-xl border-2 border-slate-200 text-slate-600 font-semibold hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          العودة
        </button>
      </div>
    </div>

    <div id="recordsView" class="w-full max-w-5xl hidden">
      <div class="bg-white rounded-3xl shadow-xl overflow-hidden animate-fade-in-up">
        <div class="p-6 border-b border-slate-100">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h2 class="text-2xl font-bold text-gray-800">لوحة التحكم</h2>
              <p class="text-slate-500 text-sm">إدارة طلبات الاستئذان</p>
            </div>
            <div class="flex gap-2">
              <button id="printBtn" class="p-3 rounded-xl bg-green-50 hover:bg-green-100 transition-colors" title="طباعة السجلات"><svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg></button>
              <button id="adminLogoutBtn" class="p-3 rounded-xl hover:bg-slate-100 transition-colors"><svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg></button>
            </div>
          </div>
          <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-2xl p-4"><p class="text-slate-600 text-sm mb-1">إجمالي الطلبات</p><p id="totalRequests" class="text-3xl font-bold text-indigo-600">0</p></div>
            <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-2xl p-4"><p class="text-slate-600 text-sm mb-1">قيد الانتظار</p><p id="pendingRequests" class="text-3xl font-bold text-amber-600">0</p></div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-4"><p class="text-slate-600 text-sm mb-1">موافق عليه</p><p id="approvedRequests" class="text-3xl font-bold text-green-600">0</p></div>
            <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-2xl p-4"><p class="text-slate-600 text-sm mb-1">رسائل واتساب المفتوحة</p><p id="whatsappOpenedRequests" class="text-3xl font-bold text-emerald-600">0</p></div>
          </div>
          <div class="flex gap-3"><input type="text" id="searchInput" placeholder="ابحث عن اسم الطالب..." class="input-field flex-1 p-3 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none"><select id="filterStatus" class="input-field p-3 rounded-xl border-2 border-slate-200 bg-slate-50 focus:border-indigo-500 focus:bg-white outline-none cursor-pointer"><option value="">جميع الحالات</option><option value="معلق">معلق</option><option value="موافق عليه">موافق عليه</option><option value="مرفوض">مرفوض</option></select></div>
        </div>
        <div id="adminRecordsList" class="p-6 space-y-4 max-h-96 overflow-y-auto"><p class="text-center text-slate-400 py-8">لا توجد طلبات حتى الآن</p></div>
      </div>
    </div>
  </div>

  <script src="app.js"></script>
</body>
</html>
