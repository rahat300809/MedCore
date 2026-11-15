<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/views/media.php';
$sectorImage = MC_IMG_DOCTOR;
$sectorKicker = 'For Doctors · BMDC';
$sectorTitle = 'চেম্বারে দ্রুত, নিরাপদ ক্লিনিক্যাল ওয়ার্কফ্লো';
$sectorSubtitle = 'OTP সম্মতি, লাইভ কিউ, ডিজিটাল Rx এবং Code Blue ACLS সহকারী।';
$sectorFeatures = [
    ['title' => 'OTP consent EMR', 'body' => '৩০ মিনিটের এনক্রিপ্টেড পেশেন্ট সেশন।'],
    ['title' => 'Code Blue ACLS', 'body' => 'কার্ডিয়াক অ্যারেস্টে টাইমার, ভয়েস লগ ও রান শিট।'],
    ['title' => 'Live queue', 'body' => 'STAT প্রায়োরিটি ও walk-in চেক-ইন।'],
    ['title' => 'Digital Rx pad', 'body' => 'প্রিন্ট/PDF রেডি প্রেসক্রিপশন ও ল্যাব টোকেন।'],
];
$sectorLogin = APP_URL . '/doctor/select-hospital.php';
$sectorLoginLabel = 'Doctor login';
$sectorSecondary = APP_URL . '/role-selection.php';
$sectorSecondaryLabel = 'সব পোর্টাল';
require __DIR__ . '/../app/views/public/sector_page.php';
