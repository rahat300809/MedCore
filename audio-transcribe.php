<?php
/**
 * Session-safe STT entry (routed via api/index.php like portal pages).
 * Prefer this over /api/audio-transcribe.php on Vercel so login cookies share one runtime path.
 */
require __DIR__ . '/api/audio-transcribe.php';
