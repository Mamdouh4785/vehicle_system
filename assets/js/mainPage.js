// mainPage.js - العقل المدبر للنظام

const htmlPage = document.documentElement;
let currentLang  = localStorage.getItem('lang')  || 'ar';
let currentTheme = localStorage.getItem('theme') || 'light';

const translations = {
    ar: {
        langBtn: 'English',
        themeDark: '🌙 الوضع الداكن',
        themeLight: '☀️ الوضع الفاتح',
        loginTitle: 'تسجيل الدخول',
        loginSub: 'مرحباً بك في نظام إدارة ومتابعة صيانة المركبات',
        userL: 'اسم المستخدم',
        passL: 'كلمة المرور',
        logBtn: 'دخول',
        userP: 'أدخل اسم المستخدم',
        passP: 'أدخل كلمة المرور'
    },
    en: {
        langBtn: 'عربي',
        themeDark: '🌙 Dark Mode',
        themeLight: '☀️ Light Mode',
        loginTitle: 'Login',
        loginSub: 'Welcome to Vehicle Maintenance System',
        userL: 'Username',
        passL: 'Password',
        logBtn: 'Login',
        userP: 'Enter Username',
        passP: 'Enter Password'
    }
};

function applySettings() {
    const t = translations[currentLang];

    // تطبيق الثيم
    if (currentTheme === 'dark') {
        htmlPage.setAttribute('data-theme', 'dark');
    } else {
        htmlPage.removeAttribute('data-theme');
    }

    // تطبيق اللغة والاتجاه
    htmlPage.setAttribute('dir',  currentLang === 'ar' ? 'rtl' : 'ltr');
    htmlPage.setAttribute('lang', currentLang);

    // تحديث أزرار التحكم
    const langBtn  = document.getElementById('langBtn');
    const themeBtn = document.getElementById('themeBtn');
    if (langBtn)  langBtn.textContent  = t.langBtn;
    if (themeBtn) themeBtn.innerHTML   = currentTheme === 'dark' ? t.themeLight : t.themeDark;

    // ترجمة صفحة تسجيل الدخول
    const loginHeader = document.getElementById('loginHeader');
    if (loginHeader) {
        loginHeader.textContent = t.loginTitle;
        const sub = document.getElementById('subHeader');    if(sub) sub.textContent = t.loginSub;
        const ul  = document.getElementById('userLabel');    if(ul)  ul.textContent  = t.userL;
        const pl  = document.getElementById('passLabel');    if(pl)  pl.textContent  = t.passL;
        const lb  = document.getElementById('loginBtnText'); if(lb)  lb.textContent  = t.logBtn;
        const up  = document.getElementById('username');     if(up)  up.placeholder  = t.userP;
        const pp  = document.getElementById('password');     if(pp)  pp.placeholder  = t.passP;
    }

    // ترجمة العناصر data-label-ar / data-label-en
    document.querySelectorAll('[data-label-ar][data-label-en]').forEach(el => {
        const text = currentLang === 'ar' ? el.getAttribute('data-label-ar') : el.getAttribute('data-label-en');
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            el.placeholder = text;
        } else {
            el.textContent = text;
        }
    });

    // ترجمة العناصر data-ar / data-en
    document.querySelectorAll('[data-ar][data-en]').forEach(el => {
        el.textContent = currentLang === 'ar' ? el.getAttribute('data-ar') : el.getAttribute('data-en');
    });

    // ترجمة بطاقات السيارات
    document.querySelectorAll('.car-card').forEach(card => {
        const modelTitle  = card.querySelector('.car-model');
        const statusBadge = card.querySelector('.status-badge');
        if (modelTitle)  modelTitle.textContent  = currentLang === 'ar' ? card.getAttribute('data-model-ar')  : card.getAttribute('data-model-en');
        if (statusBadge) statusBadge.textContent = currentLang === 'ar' ? card.getAttribute('data-status-ar') : card.getAttribute('data-status-en');
    });
}

window.toggleTheme = function() {
    currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('theme', currentTheme);
    applySettings();
};

window.toggleLanguage = function() {
    currentLang = currentLang === 'ar' ? 'en' : 'ar';
    localStorage.setItem('lang', currentLang);
    applySettings();
};

// تطبيق فوري قبل DOMContentLoaded لمنع الرمشة
applySettings();

document.addEventListener('DOMContentLoaded', () => {
    applySettings();
    const langBtn  = document.getElementById('langBtn');
    const themeBtn = document.getElementById('themeBtn');
    if (langBtn)  langBtn.onclick  = window.toggleLanguage;
    if (themeBtn) themeBtn.onclick = window.toggleTheme;
});