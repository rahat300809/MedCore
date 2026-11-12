<?php
require_once dirname(__DIR__) . '/media.php';

function mc_patient_nav(string $active = ''): array {
    $uid = $_SESSION['patient_uid'] ?? 'PT-000001';
    $items = [
        ['key' => 'dashboard', 'href' => APP_URL . '/patient/dashboard.php', 'label' => 'ড্যাশবোর্ড', 'hint' => 'সব এক নজরে', 'icon' => 'bi-grid-1x2-fill'],
        ['key' => 'prescriptions', 'href' => APP_URL . '/patient/prescriptions.php', 'label' => 'প্রেসক্রিপশন', 'hint' => 'ওষুধের তালিকা', 'icon' => 'bi-file-earmark-medical-fill'],
        ['key' => 'medications', 'href' => APP_URL . '/patient/medications.php', 'label' => 'ওষুধ', 'hint' => 'কখন কী খাবেন', 'icon' => 'bi-capsule'],
        ['key' => 'labs', 'href' => APP_URL . '/patient/lab-reports.php', 'label' => 'ল্যাব রিপোর্ট', 'hint' => 'টোকেন ও রেজাল্ট', 'icon' => 'bi-eyedropper'],
        ['key' => 'appointments', 'href' => APP_URL . '/patient/appointments.php', 'label' => 'অ্যাপয়েন্টমেন্ট', 'hint' => 'সিরিয়াল দেখুন', 'icon' => 'bi-calendar2-check-fill'],
        ['key' => 'consent', 'href' => APP_URL . '/patient/consent-requests.php', 'label' => 'সম্মতি', 'hint' => 'ডাক্তার অ্যাক্সেস', 'icon' => 'bi-shield-check'],
        ['key' => 'timeline', 'href' => APP_URL . '/patient/timeline.php', 'label' => 'টাইমলাইন', 'hint' => 'স্বাস্থ্য ইতিহাস', 'icon' => 'bi-clock-history'],
        ['key' => 'notifications', 'href' => APP_URL . '/patient/notifications.php', 'label' => 'নোটিফিকেশন', 'hint' => 'সতর্কতা ও আপডেট', 'icon' => 'bi-bell-fill'],
        ['key' => 'profile', 'href' => APP_URL . '/patient/profile.php', 'label' => 'প্রোফাইল', 'hint' => 'নিজের তথ্য', 'icon' => 'bi-person-badge'],
        ['key' => 'ice', 'href' => APP_URL . '/emergency.php?uid=' . urlencode($uid), 'label' => 'ICE কার্ড', 'hint' => 'জরুরি তথ্য', 'icon' => 'bi-heart-pulse-fill'],
    ];
    foreach ($items as &$item) {
        $item['active'] = ($item['key'] === $active);
    }
    return $items;
}

function mc_doctor_nav(string $active = '', int $waitingCount = 0): array {
    $items = [
        ['key' => 'dashboard', 'href' => APP_URL . '/doctor/dashboard.php', 'label' => 'ড্যাশবোর্ড', 'hint' => 'চেম্বার সারাংশ', 'icon' => 'bi-grid-1x2-fill'],
        ['key' => 'queue', 'href' => APP_URL . '/doctor/queue.php', 'label' => 'লাইভ কিউ', 'hint' => 'রোগী ডাকুন', 'icon' => 'bi-people-fill', 'badge' => $waitingCount > 0 ? $waitingCount : null],
        ['key' => 'search', 'href' => APP_URL . '/doctor/patient-search.php', 'label' => 'রোগী খুঁজুন', 'hint' => 'UID / Phone / NID', 'icon' => 'bi-search'],
        ['key' => 'consultations', 'href' => APP_URL . '/doctor/consultations.php', 'label' => 'কনসালটেশন', 'hint' => 'রোগী ইতিহাস', 'icon' => 'bi-stethoscope'],
        ['key' => 'prescriptions', 'href' => APP_URL . '/doctor/prescriptions.php', 'label' => 'প্রেসক্রিপশন', 'hint' => 'লিখুন / দেখুন', 'icon' => 'bi-file-earmark-plus-fill'],
        ['key' => 'reports', 'href' => APP_URL . '/doctor/reports.php', 'label' => 'রিপোর্ট', 'hint' => 'প্র্যাকটিস অ্যানালিটিক্স', 'icon' => 'bi-graph-up-arrow'],
        ['key' => 'affiliation', 'href' => APP_URL . '/doctor/request-affiliation.php', 'label' => 'অ্যাফিলিয়েশন', 'hint' => 'হাসপাতাল সংযোগ', 'icon' => 'bi-building-add'],
    ];
    foreach ($items as &$item) {
        $item['active'] = ($item['key'] === $active);
    }
    return $items;
}

function mc_hospital_nav(string $active = '', int $pendingAffils = 0): array {
    $items = [
        ['key' => 'dashboard', 'href' => APP_URL . '/hospital/dashboard.php', 'label' => 'ড্যাশবোর্ড', 'hint' => 'OPD সারাংশ', 'icon' => 'bi-grid-1x2-fill'],
        ['key' => 'appointments', 'href' => APP_URL . '/hospital/appointments.php', 'label' => 'রিসেপশন', 'hint' => 'চেক-ইন ও কিউ', 'icon' => 'bi-calendar2-check'],
        ['key' => 'doctors', 'href' => APP_URL . '/hospital/doctors.php', 'label' => 'ডাক্তার রোস্টার', 'hint' => 'অ্যাফিলিয়েটেড ডাক্তার', 'icon' => 'bi-person-badge-fill', 'badge' => $pendingAffils > 0 ? $pendingAffils : null],
        ['key' => 'affiliations', 'href' => APP_URL . '/hospital/affiliations.php', 'label' => 'অ্যাফিলিয়েশন', 'hint' => 'অনুমোদন ও ইতিহাস', 'icon' => 'bi-clock-history'],
        ['key' => 'diagnostics', 'href' => APP_URL . '/hospital/diagnostics.php', 'label' => 'ডায়াগনস্টিকস', 'hint' => 'ইমেজিং ও রিপোর্ট', 'icon' => 'bi-file-earmark-medical'],
        ['key' => 'labs', 'href' => APP_URL . '/hospital/laboratories.php', 'label' => 'ল্যাব নেটওয়ার্ক', 'hint' => 'অনুমোদিত ল্যাব', 'icon' => 'bi-eyedropper'],
        ['key' => 'departments', 'href' => APP_URL . '/hospital/departments.php', 'label' => 'ডিপার্টমেন্ট', 'hint' => 'বিভাগ', 'icon' => 'bi-diagram-3'],
        ['key' => 'consultations', 'href' => APP_URL . '/hospital/consultations.php', 'label' => 'কনসালটেশন', 'hint' => 'পরামর্শ রেকর্ড', 'icon' => 'bi-stethoscope'],
        ['key' => 'settings', 'href' => APP_URL . '/hospital/settings.php', 'label' => 'সেটিংস', 'hint' => 'হাসপাতাল তথ্য', 'icon' => 'bi-gear'],
    ];
    foreach ($items as &$item) {
        $item['active'] = ($item['key'] === $active);
    }
    return $items;
}

function mc_lab_nav(string $active = ''): array {
    $items = [
        ['key' => 'dashboard', 'href' => APP_URL . '/lab/dashboard.php', 'label' => 'ড্যাশবোর্ড', 'hint' => 'আজকের কাজ', 'icon' => 'bi-grid-1x2-fill'],
        ['key' => 'search', 'href' => APP_URL . '/lab/search-token.php', 'label' => 'টোকেন সার্চ', 'hint' => 'LT-XXXXX', 'icon' => 'bi-search'],
        ['key' => 'upload', 'href' => APP_URL . '/lab/upload-results.php', 'label' => 'রিপোর্ট আপলোড', 'hint' => 'PDF / values', 'icon' => 'bi-upload'],
        ['key' => 'reports', 'href' => APP_URL . '/lab/reports.php', 'label' => 'অর্ডার লিস্ট', 'hint' => 'সব টোকেন', 'icon' => 'bi-list-ul'],
    ];
    foreach ($items as &$item) {
        $item['active'] = ($item['key'] === $active);
    }
    return $items;
}

function mc_admin_nav(string $active = '', int $pendingDoctors = 0, int $pendingHospitals = 0, int $pendingAffils = 0): array {
    $items = [
        ['key' => 'dashboard', 'href' => APP_URL . '/admin/dashboard.php', 'label' => 'System Dashboard', 'icon' => 'bi-grid-1x2-fill'],
        ['key' => 'doctors', 'href' => APP_URL . '/admin/doctors.php', 'label' => 'Doctors', 'icon' => 'bi-person-badge-fill', 'badge' => $pendingDoctors > 0 ? $pendingDoctors : null],
        ['key' => 'hospitals', 'href' => APP_URL . '/admin/hospitals.php', 'label' => 'Hospitals', 'icon' => 'bi-building-fill-check', 'badge' => $pendingHospitals > 0 ? $pendingHospitals : null],
        ['key' => 'laboratories', 'href' => APP_URL . '/admin/laboratories.php', 'label' => 'Laboratories', 'icon' => 'bi-eyedropper'],
        ['key' => 'affiliations', 'href' => APP_URL . '/admin/affiliations.php', 'label' => 'Affiliations', 'icon' => 'bi-diagram-3-fill', 'badge' => $pendingAffils > 0 ? $pendingAffils : null],
        ['key' => 'patients', 'href' => APP_URL . '/admin/patients.php', 'label' => 'Patients', 'icon' => 'bi-people-fill'],
        ['key' => 'audit', 'href' => APP_URL . '/admin/audit-logs.php', 'label' => 'Audit Logs', 'icon' => 'bi-shield-check'],
        ['key' => 'chatbot', 'href' => APP_URL . '/admin/chatbot-settings.php', 'label' => 'Chatbot AI', 'icon' => 'bi-robot'],
    ];
    foreach ($items as &$item) {
        $item['active'] = ($item['key'] === $active);
    }
    return $items;
}

/**
 * Start a competition portal page (sets common vars + opens shell)
 */
function mc_portal_begin(array $cfg): void {
    $GLOBALS['portalRole'] = $cfg['role'];
    $GLOBALS['portalRoleBn'] = $cfg['roleBn'];
    $GLOBALS['portalAccent'] = $cfg['accent'];
    $GLOBALS['portalUser'] = $cfg['user'];
    $GLOBALS['portalMeta'] = $cfg['meta'];
    $GLOBALS['portalBanner'] = $cfg['banner'] ?? null;
    $GLOBALS['portalTitle'] = $cfg['title'];
    $GLOBALS['portalSubtitle'] = $cfg['subtitle'] ?? '';
    $GLOBALS['portalNav'] = $cfg['nav'];
    $GLOBALS['pageTitle'] = ($cfg['title'] ?? 'MedCore') . ' — MedCore';
    extract($GLOBALS, EXTR_SKIP);
    require dirname(__DIR__) . '/layout/portal_shell_start.php';
}

function mc_portal_end(): void {
    require dirname(__DIR__) . '/layout/portal_shell_end.php';
}
