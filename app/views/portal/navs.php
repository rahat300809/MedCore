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
        ['key' => 'profile', 'href' => APP_URL . '/patient/profile.php', 'label' => 'প্রোফাইল', 'hint' => 'নিজের তথ্য', 'icon' => 'bi-person-badge'],
        ['key' => 'ice', 'href' => APP_URL . '/emergency.php?uid=' . urlencode($uid), 'label' => 'ICE কার্ড', 'hint' => 'জরুরি তথ্য', 'icon' => 'bi-heart-pulse-fill'],
    ];
    foreach ($items as &$item) {
        $item['active'] = ($item['key'] === $active);
    }
    return $items;
}

function mc_doctor_nav(string $active = ''): array {
    $items = [
        ['key' => 'dashboard', 'href' => APP_URL . '/doctor/dashboard.php', 'label' => 'ড্যাশবোর্ড', 'hint' => 'চেম্বার সারাংশ', 'icon' => 'bi-grid-1x2-fill'],
        ['key' => 'queue', 'href' => APP_URL . '/doctor/queue.php', 'label' => 'লাইভ কিউ', 'hint' => 'রোগী ডাকুন', 'icon' => 'bi-people-fill'],
        ['key' => 'search', 'href' => APP_URL . '/doctor/patient-search.php', 'label' => 'রোগী খুঁজুন', 'hint' => 'UID / Phone', 'icon' => 'bi-search'],
        ['key' => 'prescriptions', 'href' => APP_URL . '/doctor/prescriptions.php', 'label' => 'প্রেসক্রিপশন', 'hint' => 'লিখুন / দেখুন', 'icon' => 'bi-file-earmark-plus-fill'],
        ['key' => 'reports', 'href' => APP_URL . '/doctor/reports.php', 'label' => 'রিপোর্ট', 'hint' => 'ল্যাব ফলাফল', 'icon' => 'bi-clipboard2-pulse'],
    ];
    foreach ($items as &$item) {
        $item['active'] = ($item['key'] === $active);
    }
    return $items;
}

function mc_hospital_nav(string $active = ''): array {
    $items = [
        ['key' => 'dashboard', 'href' => APP_URL . '/hospital/dashboard.php', 'label' => 'ড্যাশবোর্ড', 'hint' => 'OPD সারাংশ', 'icon' => 'bi-grid-1x2-fill'],
        ['key' => 'appointments', 'href' => APP_URL . '/hospital/appointments.php', 'label' => 'রিসেপশন', 'hint' => 'চেক-ইন', 'icon' => 'bi-calendar2-check'],
        ['key' => 'doctors', 'href' => APP_URL . '/hospital/doctors.php', 'label' => 'ডাক্তার', 'hint' => 'রোস্টার', 'icon' => 'bi-stethoscope'],
        ['key' => 'labs', 'href' => APP_URL . '/hospital/laboratories.php', 'label' => 'ল্যাব', 'hint' => 'নেটওয়ার্ক', 'icon' => 'bi-eyedropper'],
        ['key' => 'departments', 'href' => APP_URL . '/hospital/departments.php', 'label' => 'ডিপার্টমেন্ট', 'hint' => 'বিভাগ', 'icon' => 'bi-diagram-3'],
        ['key' => 'settings', 'href' => APP_URL . '/hospital/settings.php', 'label' => 'সেটিংস', 'hint' => 'হাসপাতাল', 'icon' => 'bi-gear'],
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

/**
 * Start a competition portal page (sets common vars + opens shell)
 */
function mc_portal_begin(array $cfg): void {
    $GLOBALS['portalRole'] = $cfg['role'];
    $GLOBALS['portalRoleBn'] = $cfg['roleBn'];
    $GLOBALS['portalAccent'] = $cfg['accent'];
    $GLOBALS['portalUser'] = $cfg['user'];
    $GLOBALS['portalMeta'] = $cfg['meta'];
    $GLOBALS['portalBanner'] = $cfg['banner'];
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
