<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/views/media.php';
$sectorImage = MC_IMG_HOSPITAL;
$sectorKicker = 'For Hospitals · OPD';
$sectorTitle = 'হাসপাতাল অপারেশন এক স্ক্রিনে পরিষ্কার';
$sectorSubtitle = 'রিসেপশন কিউ, ডাক্তার রোস্টার, ল্যাব নেটওয়ার্ক এবং ER Code Blue।';
$sectorFeatures = [
    ['title' => 'Reception desk', 'body' => 'Lobby check-in, সিরিয়াল ও wait-time মনিটর।'],
    ['title' => 'Code Blue ER', 'body' => 'AHA ACLS কো-পাইলট — টাইমার, ইন্টারভেনশন লগ, রিপোর্ট।'],
    ['title' => 'Roster control', 'body' => 'ডাক্তার approve / suspend ও ডিপার্টমেন্ট।'],
    ['title' => 'Lab network', 'body' => 'মাল্টি-ল্যাব অ্যাফিলিয়েশন ও টোকেন সিঙ্ক।'],
];
$sectorLogin = APP_URL . '/hospital/login.php';
$sectorLoginLabel = 'Hospital login';
$sectorSecondary = APP_URL . '/hospital/code-blue.php';
$sectorSecondaryLabel = 'Code Blue ER';
require __DIR__ . '/../app/views/public/sector_page.php';
