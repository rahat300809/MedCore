<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/views/media.php';
$sectorImage = MC_IMG_HOSPITAL;
$sectorKicker = 'For Hospitals · OPD';
$sectorTitle = 'হাসপাতাল অপারেশন এক স্ক্রিনে পরিষ্কার';
$sectorSubtitle = 'ডাক্তার অ্যাফিলিয়েশন, ল্যাব নেটওয়ার্ক, রিসেপশন কিউ।';
$sectorFeatures = [
    ['title' => 'Roster control', 'body' => 'ডাক্তার approve / suspend ও ডিপার্টমেন্ট।'],
    ['title' => 'Reception desk', 'body' => 'Lobby check-in ও wait-time মনিটর।'],
    ['title' => 'Lab network', 'body' => 'মাল্টি-ল্যাব অ্যাফিলিয়েশন।'],
];
$sectorLogin = APP_URL . '/hospital/login.php';
$sectorLoginLabel = 'Hospital login';
require __DIR__ . '/../app/views/public/sector_page.php';
