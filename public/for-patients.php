<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/views/media.php';
$sectorImage = MC_IMG_PATIENT;
$sectorKicker = 'For Patients · নাগরিক';
$sectorTitle = 'আপনার স্বাস্থ্য রেকর্ড — আপনার নিয়ন্ত্রণে';
$sectorSubtitle = 'প্রেসক্রিপশন, সিরিয়াল, ল্যাব টোকেন, Sidekick AI ও ICE কার্ড এক জায়গায়।';
$sectorFeatures = [
    ['title' => 'Unified EHR', 'body' => 'সব হাসপাতাল ও চেম্বারের ইতিহাস এক UID-তে।'],
    ['title' => 'Sidekick advocate', 'body' => 'কথোপকথন শোনে, টার্ম ব্যাখ্যা করে, প্রশ্ন সাজেস্ট করে, সারাংশ দেয়।'],
    ['title' => 'Medicine guide', 'body' => 'কোন ওষুধ কোন কাজের জন্য — AI সহজ বাংলায় ব্যাখ্যা।'],
    ['title' => 'ICE emergency card', 'body' => 'লগইন ছাড়াই রক্তের গ্রুপ ও জরুরি যোগাযোগ।'],
];
$sectorLogin = APP_URL . '/patient/login.php';
$sectorLoginLabel = 'Patient login';
$sectorSecondary = APP_URL . '/book-doctor.php';
$sectorSecondaryLabel = 'সিরিয়াল বুক করুন';
require __DIR__ . '/../app/views/public/sector_page.php';
