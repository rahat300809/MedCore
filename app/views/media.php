<?php
/** Local photography for page atmospheres (no hotlink breakage) */
if (!defined('MC_MEDIA_LOADED')) {
    define('MC_MEDIA_LOADED', true);
    $mcMediaBase = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/assets/media';
    define('MC_IMG_HOME', $mcMediaBase . '/home.jpg');
    define('MC_IMG_PATIENT', $mcMediaBase . '/patient.jpg');
    define('MC_IMG_DOCTOR', $mcMediaBase . '/doctor.jpg');
    define('MC_IMG_HOSPITAL', $mcMediaBase . '/hospital.jpg');
    define('MC_IMG_LAB', $mcMediaBase . '/lab.jpg');
    define('MC_IMG_EMERGENCY', $mcMediaBase . '/emergency.jpg');
    define('MC_IMG_BOOKING', $mcMediaBase . '/doctor.jpg');
    define('MC_IMG_LOGIN', $mcMediaBase . '/hospital.jpg');
}
