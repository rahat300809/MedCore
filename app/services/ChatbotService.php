<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chatbot.php';

class ChatbotService {

    /**
     * Get live dynamic context from MedCore database
     */
    public static function getLiveContext(): array {
        $groupedDoctors = [];
        $specialties = ['Internal Medicine', 'Cardiology', 'Gastroenterology', 'Pediatrics', 'Neurology'];

        try {
            if (extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite')) {
                $db = getDB();

                // 1. Fetch Verified Doctors & Hospital Affiliations
                $stmtDocs = $db->query("
                    SELECT d.id, d.full_name, d.specialization, d.qualification, d.consultation_fee,
                           d.rating_score, d.rating_count, d.experience_years,
                           h.id AS hospital_id, h.name AS hospital_name, h.city AS hospital_city
                    FROM doctors d
                    JOIN doctor_hospitals dh ON dh.doctor_id = d.id AND dh.status = 'APPROVED'
                    JOIN hospitals h ON h.id = dh.hospital_id
                    WHERE d.verification_status = 'VERIFIED'
                    ORDER BY d.rating_score DESC, d.rating_count DESC
                    LIMIT 25
                ");
                $doctors = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

                // Group by doctor
                foreach ($doctors as $doc) {
                    $docId = $doc['id'];
                    if (!isset($groupedDoctors[$docId])) {
                        $groupedDoctors[$docId] = [
                            'id'             => $doc['id'],
                            'name'           => $doc['full_name'],
                            'specialization' => $doc['specialization'],
                            'qualification'  => $doc['qualification'],
                            'fee'            => (float)$doc['consultation_fee'],
                            'rating'         => (float)$doc['rating_score'],
                            'reviews'        => (int)$doc['rating_count'],
                            'experience'     => (int)$doc['experience_years'],
                            'hospitals'      => []
                        ];
                    }
                    $groupedDoctors[$docId]['hospitals'][] = [
                        'id'   => $doc['hospital_id'],
                        'name' => $doc['hospital_name'],
                        'city' => $doc['hospital_city']
                    ];
                }

                // 2. Fetch Active Specializations
                $stmtSpecs = $db->query("
                    SELECT DISTINCT specialization 
                    FROM doctors 
                    WHERE verification_status = 'VERIFIED' AND specialization IS NOT NULL AND specialization != ''
                ");
                $dbSpecs = $stmtSpecs->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($dbSpecs)) {
                    $specialties = $dbSpecs;
                }
            } else {
                throw new Exception("Local PHP PDO driver not loaded");
            }
        } catch (\Throwable $e) {
            // Fallback list of sample verified doctors if DB cannot be connected
            $groupedDoctors = [
                1 => [
                    'id' => 1, 'name' => 'Dr. Farhana Rahman', 'specialization' => 'Internal Medicine',
                    'qualification' => 'MBBS, FCPS (Medicine)', 'fee' => 1000, 'rating' => 4.9, 'reviews' => 120, 'experience' => 12,
                    'hospitals' => [['id' => 1, 'name' => 'ABC General Hospital', 'city' => 'Dhaka']]
                ],
                2 => [
                    'id' => 2, 'name' => 'Dr. Kamrul Karim', 'specialization' => 'Cardiology',
                    'qualification' => 'MBBS, MD (Cardiology)', 'fee' => 1200, 'rating' => 4.8, 'reviews' => 95, 'experience' => 15,
                    'hospitals' => [['id' => 1, 'name' => 'ABC General Hospital', 'city' => 'Dhaka'], ['id' => 2, 'name' => 'XYZ Medical Center', 'city' => 'Dhaka']]
                ],
                3 => [
                    'id' => 3, 'name' => 'Dr. Masum Hasan', 'specialization' => 'Gastroenterology',
                    'qualification' => 'MBBS, FCPS (Gastro)', 'fee' => 800, 'rating' => 4.7, 'reviews' => 80, 'experience' => 10,
                    'hospitals' => [['id' => 1, 'name' => 'ABC General Hospital', 'city' => 'Dhaka']]
                ],
                4 => [
                    'id' => 4, 'name' => 'Dr. Ayesha Siddiqua', 'specialization' => 'Pediatrics',
                    'qualification' => 'MBBS, DCH, FCPS (Pediatrics)', 'fee' => 900, 'rating' => 4.9, 'reviews' => 110, 'experience' => 11,
                    'hospitals' => [['id' => 1, 'name' => 'ABC General Hospital', 'city' => 'Dhaka']]
                ],
                5 => [
                    'id' => 5, 'name' => 'Prof. Dr. Tariqul Islam', 'specialization' => 'Neurology',
                    'qualification' => 'MBBS, MD (Neurology), PhD', 'fee' => 1500, 'rating' => 5.0, 'reviews' => 150, 'experience' => 20,
                    'hospitals' => [['id' => 1, 'name' => 'ABC General Hospital', 'city' => 'Dhaka']]
                ]
            ];
        }

        // 3. User Session Context
        $isLoggedIn = isset($_SESSION['role']) && $_SESSION['role'] === 'PATIENT';
        $patientContext = [
            'is_logged_in' => $isLoggedIn,
            'name'         => $_SESSION['name'] ?? null,
            'patient_uid'  => $_SESSION['patient_uid'] ?? null,
            'patient_id'   => $_SESSION['patient_id'] ?? null
        ];

        return [
            'doctors'     => array_values($groupedDoctors),
            'specialties' => $specialties,
            'patient'     => $patientContext
        ];
    }

    /**
     * Builds the complete prompt with live database knowledge
     */
    public static function buildFullSystemPrompt(): string {
        $basePrompt = getChatbotActiveSystemPrompt();
        $context = self::getLiveContext();

        $patientInfo = $context['patient']['is_logged_in'] 
            ? "Patient is LOGGED IN as \"{$context['patient']['name']}\" (ID: {$context['patient']['patient_uid']}). They can confirm appointments directly."
            : "Patient is NOT LOGGED IN (Guest user). You must instruct them to login to confirm booking, and provide login/register guidance.";

        $doctorsList = "";
        foreach ($context['doctors'] as $d) {
            $hospNames = implode(', ', array_column($d['hospitals'], 'name'));
            $firstHospId = $d['hospitals'][0]['id'] ?? 1;
            $doctorsList .= "- Dr. {$d['name']} | Specialization: {$d['specialization']} | Hospital(s): {$hospNames} | Rating: {$d['rating']}★ ({$d['reviews']} reviews) | Fee: {$d['fee']} BDT | Doctor ID: {$d['id']} | Hospital ID: {$firstHospId}\n";
        }

        $specialtiesList = implode(', ', $context['specialties']);

        $injectedKnowledge = <<<CONTEXT

=== LIVE MEDCORE CONTEXT (REAL-TIME DATABASE) ===
[Current User Status]
{$patientInfo}

[Available Verified Medical Categories in MedCore]
{$specialtiesList}

[Verified Doctors List (With IDs for booking actions)]
{$doctorsList}

[Direct Action Link Helpers]
- Patient Login URL: "/patient/login.php"
- Patient Register URL: "/patient/register.php"
- Book Doctor Portal: "/book-doctor.php"
- Emergency ICE Profile: "/emergency.php"
- Security & Consent: "/security.php"

When suggesting a doctor, provide their name, hospital, fee, and rating. If the user wants to book, tell them what to do based on their login status, and you may output an actionable tag in your message like:
[BOOK_DOCTOR: doctor_id, hospital_id, doctor_name]
This tag will be automatically converted into a clickable interactive booking card for the user!
==================================================
CONTEXT;

        return $basePrompt . "\n\n" . $injectedKnowledge;
    }

    /**
     * Call Groq API with user messages
     */
    public static function chat(array $messages): array {
        $apiKey = GROQ_API_KEY;
        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'Groq API Key is not configured in system settings.'
            ];
        }

        $systemPrompt = self::buildFullSystemPrompt();

        // Build messages array
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Sanitize and append up to 10 latest messages
        $recentMessages = array_slice($messages, -10);
        foreach ($recentMessages as $m) {
            $role = in_array($m['role'] ?? '', ['user', 'assistant']) ? $m['role'] : 'user';
            $content = trim($m['content'] ?? '');
            if (!empty($content)) {
                $truncated = function_exists('mb_substr') ? mb_substr($content, 0, 1000) : substr($content, 0, 1000);
                $apiMessages[] = [
                    'role'    => $role,
                    'content' => $truncated
                ];
            }
        }

        $payload = [
            'model'       => GROQ_MODEL,
            'messages'    => $apiMessages,
            'temperature' => 0.35,
            'max_tokens'  => 1024
        ];

        // Send HTTP Request using cURL or file_get_contents fallback
        $jsonPayload = json_encode($payload);
        $responseBody = null;
        $httpCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init(GROQ_API_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'User-Agent: MedCore-AI-Agent/1.0'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Authorization: Bearer {$apiKey}\r\n" .
                                 "Content-Type: application/json\r\n" .
                                 "User-Agent: MedCore-AI-Agent/1.0\r\n",
                    'content' => $jsonPayload,
                    'timeout' => 20,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            $responseBody = @file_get_contents(GROQ_API_URL, false, $context);
            if (isset($http_response_header) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0] ?? '', $m)) {
                $httpCode = (int)$m[1];
            }
        }

        if (!$responseBody || $httpCode < 200 || $httpCode >= 300) {
            // Attempt fallback to smaller model if 120b was rate-limited or failed
            if ($httpCode === 404 || $httpCode === 429) {
                return self::chatFallback($apiMessages, $apiKey);
            }
            return [
                'success' => false,
                'message' => 'Groq API communication failed (HTTP ' . $httpCode . '). Response: ' . substr($responseBody ?: '', 0, 150)
            ];
        }

        $decoded = json_decode($responseBody, true);
        $replyText = $decoded['choices'][0]['message']['content'] ?? '';

        if (empty($replyText)) {
            return [
                'success' => false,
                'message' => 'Empty response received from AI model.'
            ];
        }

        return [
            'success'   => true,
            'reply'     => $replyText,
            'patient'   => self::getLiveContext()['patient']
        ];
    }

    /**
     * Fallback to gpt-oss-20b if gpt-oss-120b has issues
     */
    private static function chatFallback(array $apiMessages, string $apiKey): array {
        $payload = [
            'model'       => 'openai/gpt-oss-20b',
            'messages'    => $apiMessages,
            'temperature' => 0.35,
            'max_tokens'  => 800
        ];
        $jsonPayload = json_encode($payload);

        $ch = curl_init(GROQ_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: MedCore-AI-Agent/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $responseBody = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($responseBody, true);
        $replyText = $decoded['choices'][0]['message']['content'] ?? '';

        if (!empty($replyText)) {
            return [
                'success' => true,
                'reply'   => $replyText,
                'patient' => self::getLiveContext()['patient']
            ];
        }

        return [
            'success' => false,
            'message' => 'Chatbot service is temporarily unavailable.'
        ];
    }
}
