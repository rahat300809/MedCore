<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/views/media.php';
$sectorImage = MC_IMG_PATIENT;
$sectorKicker = 'For Patients · নাগরিক';
$sectorTitle = 'আপনার স্বাস্থ্য রেকর্ড — আপনার নিয়ন্ত্রণে';
$sectorSubtitle = 'প্রেসক্রিপশন, সিরিয়াল, ল্যাব টোকেন ও ICE কার্ড এক জায়গায়।';
$sectorFeatures = [
    ['title' => 'Lifetime EHR', 'body' => 'সব হাসপাতাল ও চেম্বারের ইতিহাস এক UID-তে।'],
    ['title' => 'Consent control', 'body' => 'ডাক্তার অ্যাক্সেস OTP দিয়ে অনুমোদন বা বাতিল।'],
    ['title' => 'Lab tokens', 'body' => 'LT-XXXXX টোকেন ও রিপোর্ট সরাসরি পোর্টালে।'],
];
$sectorLogin = APP_URL . '/patient/login.php';
$sectorLoginLabel = 'Patient login';
$sectorSecondary = APP_URL . '/book-doctor.php';
$sectorSecondaryLabel = 'সিরিয়াল বুক করুন';
require __DIR__ . '/../app/views/public/sector_page.php';
