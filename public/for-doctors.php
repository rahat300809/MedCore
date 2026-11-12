<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/views/media.php';
$sectorImage = MC_IMG_DOCTOR;
$sectorKicker = 'For Doctors · BMDC';
$sectorTitle = 'চেম্বারে দ্রুত, নিরাপদ ক্লিনিক্যাল ওয়ার্কফ্লো';
$sectorSubtitle = 'রোগী খুঁজুন, OTP সম্মতি নিন, কিউ চালান, প্রেসক্রাইব করুন।';
$sectorFeatures = [
    ['title' => 'OTP consent', 'body' => '৩০ মিনিটের এনক্রিপ্টেড সেশন।'],
    ['title' => 'Live queue', 'body' => 'STAT প্রায়োরিটি ও walk-in omnibar।'],
    ['title' => 'Lab tokens', 'body' => 'প্রেসক্রিপশন থেকে শর্ট ডায়াগনস্টিক টোকেন।'],
];
$sectorLogin = APP_URL . '/doctor/select-hospital.php';
$sectorLoginLabel = 'Doctor login';
$sectorSecondary = APP_URL . '/role-selection.php';
$sectorSecondaryLabel = 'সব পোর্টাল';
require __DIR__ . '/../app/views/public/sector_page.php';
