<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/chatbot.php';

class ChatbotService {

    /**
     * Get live dynamic context from MedCore database
     */
    public static function getLiveContext(?array $userOverride = null): array {
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
        if ($userOverride !== null && is_array($userOverride)) {
            $patientContext = [
                'is_logged_in' => !empty($userOverride['is_logged_in']),
                'name'         => $userOverride['name'] ?? null,
                'patient_uid'  => $userOverride['patient_uid'] ?? null,
                'patient_id'   => $userOverride['patient_id'] ?? null
            ];
        } else {
            $isLoggedIn = isset($_SESSION['role']) && $_SESSION['role'] === 'PATIENT';
            $patientContext = [
                'is_logged_in' => $isLoggedIn,
                'name'         => $_SESSION['name'] ?? null,
                'patient_uid'  => $_SESSION['patient_uid'] ?? null,
                'patient_id'   => $_SESSION['patient_id'] ?? null
            ];
        }

        return [
            'doctors'     => array_values($groupedDoctors),
            'specialties' => $specialties,
            'patient'     => $patientContext
        ];
    }

    /**
     * Builds the complete prompt with live database knowledge
     */
    public static function buildFullSystemPrompt(?array $userContext = null): string {
        $basePrompt = getChatbotActiveSystemPrompt();
        $context = self::getLiveContext($userContext);

        $isLoggedIn  = !empty($context['patient']['is_logged_in']);
        $patientName = $context['patient']['name'] ?: 'নাগরিক';
        $patientUid  = $context['patient']['patient_uid'] ?: '';

        if ($isLoggedIn) {
            $patientInfo = <<<AUTH
*** CRITICAL USER AUTHENTICATION STATUS: CURRENTLY LOGGED IN ***
- Active User Name: "{$patientName}"
- Citizen Health UID: "{$patientUid}"
- Current Status: FULLY AUTHENTICATED CITIZEN SESSION ACTIVE.
- ABSOLUTE PROHIBITION: DO NOT TELL THIS USER TO LOG IN! NEVER SAY "লগইন করুন" OR "পেশেন্ট পোর্টালে লগইন করতে হবে", because this user IS ALREADY LOGGED IN.
- When they choose or confirm a doctor serial, address them warmly as "জনাব {$patientName}", confirm their appointment request, and directly output the actionable tag:
  [BOOK_DOCTOR: doctor_id, hospital_id, doctor_name]
  This allows them to pick their date/slot immediately.
AUTH;
        } else {
            $patientInfo = <<<GUEST
*** USER AUTHENTICATION STATUS: GUEST VISITOR (NOT LOGGED IN) ***
- Current Status: Guest / Unauthenticated visitor.
- CONVERSATION FLOW RULES:
  1. DO NOT ask the user to log in during normal chats! Talk freely, kindly, and naturally. Listen to their symptoms, suggest medical departments, recommend doctors with their fees, ratings, hospitals, and consultation schedules without asking for login.
  2. ONLY when the user explicitly confirms that they want to book a serial with a doctor (e.g. "বুকিং দিন", "সিরিয়াল বুক করে দিন", "কনফার্ম করতে চাই"), ONLY AT THAT SPECIFIC MOMENT explain politely:
     "আপনার সিরিয়ালটি চূড়ান্তভাবে নিশ্চিত করতে এবং সেন্ট্রাল ডিজিটাল হেলথ রেকর্ডে সংরক্ষণ করতে অনুগ্রহ করে একবার নাগরিক পোর্টালে লগইন করুন।"
     And output the tag: [BOOK_DOCTOR: doctor_id, hospital_id, doctor_name]
GUEST;
        }

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

When suggesting a doctor, provide their name, hospital, fee, and rating. If the user wants to book, follow the user status instructions above, and you may output an actionable tag in your message like:
[BOOK_DOCTOR: doctor_id, hospital_id, doctor_name]
This tag will be automatically converted into a clickable interactive booking card for the user!
==================================================
CONTEXT;

        return $basePrompt . "\n\n" . $injectedKnowledge;
    }

    /**
     * Call Groq API with user messages
     */
    public static function chat(array $messages, ?array $userContext = null): array {
        $apiKey = GROQ_API_KEY;
        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'Groq API Key is not configured in system settings.'
            ];
        }

        $systemPrompt = self::buildFullSystemPrompt($userContext);

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
                return self::chatFallback($apiMessages, $apiKey, $userContext);
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
            'patient'   => self::getLiveContext($userContext)['patient']
        ];
    }

    /**
     * Fallback to gpt-oss-20b if gpt-oss-120b has issues
     */
    private static function chatFallback(array $apiMessages, string $apiKey, ?array $userContext = null): array {
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
                'patient' => self::getLiveContext($userContext)['patient']
            ];
        }

        return [
            'success' => false,
            'message' => 'Chatbot service is temporarily unavailable.'
        ];
    }
}