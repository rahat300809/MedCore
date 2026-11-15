<?php
/**
 * MedCore Audio → Text
 * Pattern inspired by Carleslc/AudioToText (Whisper STT).
 * Production backend: Groq Whisper (whisper-large-v3-turbo).
 */
require_once __DIR__ . '/../config/chatbot.php';

class AudioToTextService {

    public const WHISPER_URL = 'https://api.groq.com/openai/v1/audio/transcriptions';
    public const WHISPER_MODEL = 'whisper-large-v3-turbo';

    /**
     * Transcribe an uploaded audio file path to text.
     *
     * @return array{success:bool,text?:string,model?:string,message?:string}
     */
    public static function transcribeFile(string $tmpPath, string $fileName = 'audio.webm', string $mimeType = 'audio/webm', ?string $language = null): array {
        $apiKey = GROQ_API_KEY;
        if ($apiKey === '' || $apiKey === false) {
            return ['success' => false, 'message' => 'Groq API key not configured.'];
        }
        if (!is_readable($tmpPath)) {
            return ['success' => false, 'message' => 'Audio file unreadable.'];
        }

        $cfile = new CURLFile($tmpPath, $mimeType ?: 'audio/webm', $fileName ?: 'audio.webm');
        $postData = [
            'file'            => $cfile,
            'model'           => self::WHISPER_MODEL,
            'response_format' => 'json',
            'temperature'     => '0.0',
        ];
        if ($language) {
            $postData['language'] = $language;
        }

        $ch = curl_init(self::WHISPER_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_TIMEOUT        => 45,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $text = trim((string)($data['text'] ?? ''));
            return [
                'success' => true,
                'text'    => $text,
                'model'   => self::WHISPER_MODEL,
                'engine'  => 'whisper',
            ];
        }

        return [
            'success' => false,
            'message' => 'Whisper transcription failed (' . $httpCode . '): ' . ($curlErr ?: substr((string)$response, 0, 180)),
        ];
    }

    /**
     * Clean / punctuate raw ASR text with Groq chat before clinical parsing.
     */
    public static function refineTranscript(string $rawText, string $mode = 'clinical'): string {
        $rawText = trim($rawText);
        if ($rawText === '' || empty(GROQ_API_KEY)) {
            return $rawText;
        }

        $system = $mode === 'code_blue'
            ? 'You clean noisy resuscitation-room speech-to-text. Fix drug names (Epinephrine, Amiodarone), joules, CPR terms. Keep clinical meaning. Return ONLY the cleaned transcript, no commentary.'
            : 'You clean doctor-patient consultation speech-to-text. Fix medical terms, keep Bengali and English as spoken. Return ONLY the cleaned transcript.';

        $postData = [
            'model' => GROQ_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $rawText],
            ],
            'temperature' => 0.1,
            'max_tokens' => 600,
        ];

        $ch = curl_init(GROQ_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($postData),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . GROQ_API_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $out = trim((string)($data['choices'][0]['message']['content'] ?? ''));
            if ($out !== '') {
                return $out;
            }
        }
        return $rawText;
    }
}
