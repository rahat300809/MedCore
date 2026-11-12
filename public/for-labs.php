<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/views/media.php';
$sectorImage = MC_IMG_LAB;
$sectorKicker = 'For Labs · Diagnostics';
$sectorTitle = 'ল্যাব টোকেন থেকে রিপোর্ট পর্যন্ত';
$sectorSubtitle = 'LT-XXXXX দিয়ে অর্ডার খুলুন, ফলাফল দিন, EMR-এ অটো সিঙ্ক হোক।';
$sectorFeatures = [
    ['title' => 'Token search', 'body' => 'প্রেসক্রিপশনের শর্ট টোকেন দিয়ে অর্ডার ও রোগী তথ্য খুলুন।'],
    ['title' => 'Result entry', 'body' => 'সংখ্যাসূচক ভ্যালু বা PDF/ইমেজ আপলোড করুন।'],
    ['title' => 'Auto sync', 'body' => 'সেভ হলেই রোগী পোর্টালে রিপোর্ট দেখা যায়।'],
];
$sectorSecondary = APP_URL . '/role-selection.php';
$sectorSecondaryLabel = 'সব পোর্টাল';
$sectorLogin = APP_URL . '/lab/login.php';
$sectorLoginLabel = 'Lab login';
require __DIR__ . '/../app/views/public/sector_page.php';
