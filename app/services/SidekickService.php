<?php
/**
 * MedCore Sidekick — Real-Time Patient Advocate Service
 * Listens to doctor-patient consultation dialogue, demystifies medical jargon,
 * suggests proactive questions to ask the doctor, and generates post-visit summaries.
 * Powered by Groq AI.
 */
require_once __DIR__ . '/../config/chatbot.php';

class SidekickService {

    /**
     * Analyze a speech snippet from a doctor consultation
     * Returns:
     * - translated_terms: list of medical terms with simple Bengali & English explanations
     * - suggested_questions: questions the patient should ask the doctor right now
     * - key_findings: detected symptoms, diagnoses, or medication instructions
     */
    public static function analyzeConsultationSpeech(string $speechText, array $previousContext = []): array {
        $apiKey = GROQ_API_KEY;
        if (empty($apiKey)) {
            return self::fallbackAnalysis($speechText);
        }

        $systemPrompt = <<<PROMPT
You are "MedCore Sidekick" — a compassionate, expert Patient Advocate and Medical Interpreter in Bangladesh.
You are listening to an ongoing doctor-patient consultation.
Your mission is to empower the patient:
1. Identify any complex medical terms, lab tests, drug classes, or clinical jargon mentioned by the doctor or patient.
2. Provide simple, reassuring, and clear explanations in BOTH Bengali (বাংলা) and English.
3. Suggest 2 to 3 smart, empowering questions that the patient should ask their doctor RIGHT NOW before leaving the room (e.g. regarding side effects, duration, food interactions, when to panic vs wait).
4. Extract any concrete clinical takeaways (symptoms, diagnoses, or prescribed instructions).

Return a strictly valid JSON object with the following structure:
{
  "has_terms": true/false,
  "terms": [
    {
      "term": "Medical term (e.g. Hypertension)",
      "explanation_bn": "সহজ বাংলায় ব্যাখ্যা (যেমন: রক্তের স্বাভাবিকের চেয়ে বেশি চাপ)",
      "explanation_en": "Simple English explanation (e.g. High blood pressure)",
      "importance": "HIGH|MEDIUM|LOW"
    }
  ],
  "suggested_questions": [
    {
      "question_bn": "ডাক্তারকে এই প্রশ্নটি করুন (বাংলায়)",
      "question_en": "Question in English",
      "reason": "কেন এই প্রশ্নটি জরুরি"
    }
  ],
  "key_findings": [
    "Short bullet point of clinical advice or instruction"
  ]
}

No markdown outside JSON. Return raw JSON only.
PROMPT;

        $userMessage = "Consultation speech segment:\n\"" . $speechText . "\"";

        $postData = [
            'model' => GROQ_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'temperature' => 0.2,
            'max_tokens' => 900,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init(GROQ_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($postData),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT        => 12
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            $parsed = json_decode($content, true);
            if (is_array($parsed) && isset($parsed['terms'])) {
                return $parsed;
            }
        }

        return self::fallbackAnalysis($speechText);
    }

    /**
     * Generate final consultation summary & next steps
     */
    public static function generateSummary(string $fullTranscript, array $meta = []): array {
        $apiKey = GROQ_API_KEY;
        $doctorName = $meta['doctor_name'] ?? 'Consultant Physician';
        $patientName = $meta['patient_name'] ?? 'Patient';
        $specialty = $meta['specialty'] ?? 'General Medicine';

        $systemPrompt = <<<PROMPT
You are "MedCore Sidekick" — the Patient's Personal Medical Advocate.
The appointment has just ended. You have the entire transcript of the consultation.
Generate a structured, crystal-clear, patient-friendly Appointment Summary & Action Plan in markdown format.

The summary MUST be bilingual (friendly Bengali and English) and contain:
1. **মূল রোগ বা সমস্যা (Primary Diagnosis & Concerns)**
2. **ওষুধ গ্রহণের নিয়মাবলী (Medication Schedule & Instructions)** — clarify before/after meals, morning/night, and what it does.
3. **প্রয়োজনীয় পরীক্ষা-নিরীক্ষা (Recommended Tests & Lab Tokens)**
4. **জীবনযাপন ও খাদ্যতালিকাগত পরামর্শ (Dietary & Lifestyle Advice)**
5. **জরুরি সতর্কবার্তা (Red Flag Symptoms — when to seek emergency care)**
6. **পরবর্তী সাক্ষাতের তারিখ (Follow-up Date)**

Keep tone compassionate, highly organized, and crystal clear.
PROMPT;

        $userMessage = "Doctor: {$doctorName} ({$specialty})\nPatient: {$patientName}\n\nFull Consultation Transcript:\n\"" . $fullTranscript . "\"";

        $summaryText = '';
        if (!empty($apiKey)) {
            $postData = [
                'model' => GROQ_MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage]
                ],
                'temperature' => 0.2,
                'max_tokens' => 1500
            ];

            $ch = curl_init(GROQ_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($postData),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT        => 12
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $summaryText = $data['choices'][0]['message']['content'] ?? '';
            }
        }

        if (empty($summaryText)) {
            $summaryText = self::fallbackSummary($fullTranscript, $doctorName, $specialty);
        }

        return [
            'success' => true,
            'summary_markdown' => $summaryText,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Short AI brief for doctors opening a consented patient record.
     * Bullet points: past illnesses, current medicines & purpose, alerts.
     */
    public static function doctorPatientBrief(array $summary): array {
        $patient = $summary['patient'] ?? [];
        $conditions = array_slice($summary['conditions'] ?? [], 0, 8);
        $activeMeds = array_slice($summary['active_meds'] ?? [], 0, 12);
        $allergies = array_slice($summary['allergies'] ?? [], 0, 8);
        $rxs = array_slice($summary['prescriptions'] ?? [], 0, 5);

        $payload = [
            'patient' => [
                'name' => $patient['full_name'] ?? '',
                'age' => $patient['age'] ?? null,
                'gender' => $patient['gender'] ?? '',
                'blood_group' => $patient['blood_group'] ?? '',
            ],
            'conditions' => array_map(static function ($c) {
                return [
                    'condition' => $c['condition_name'] ?? $c['diagnosis'] ?? ($c['title'] ?? 'Condition'),
                    'status' => $c['status'] ?? '',
                    'diagnosed' => $c['diagnosed_date'] ?? '',
                    'when' => $c['time_ago'] ?? '',
                ];
            }, $conditions),
            'active_medicines' => array_map(static function ($m) {
                return [
                    'name' => ($m['brand_name'] ?? '') ?: ($m['generic_name'] ?? 'Medicine'),
                    'generic' => $m['generic_name'] ?? '',
                    'dose' => trim(($m['dosage'] ?? '') . ' ' . ($m['strength'] ?? '')),
                    'frequency' => $m['frequency'] ?? '',
                ];
            }, $activeMeds),
            'allergies' => array_map(static function ($a) {
                return [
                    'name' => $a['allergy_name'] ?? '',
                    'severity' => $a['severity'] ?? '',
                ];
            }, $allergies),
            'recent_diagnoses' => array_map(static function ($rx) {
                return [
                    'diagnosis' => $rx['diagnosis'] ?? 'Consultation',
                    'when' => $rx['time_ago'] ?? '',
                    'medicines' => array_map(static function ($m) {
                        return ($m['medicine_name'] ?? $m['brand_name'] ?? $m['generic_name'] ?? 'Med');
                    }, array_slice($rx['medicines'] ?? [], 0, 6)),
                ];
            }, $rxs),
        ];

        $apiKey = GROQ_API_KEY;
        if (!empty($apiKey)) {
            $systemPrompt = <<<PROMPT
You are MedCore clinical co-pilot for Bangladesh doctors.
Given structured patient history, write a SHORT bedside briefing in clear bilingual bullets.
Return ONLY valid JSON:
{
  "headline_bn": "১ লাইনের সারাংশ",
  "headline_en": "One-line English headline",
  "bullets": [
    "• Past illness / diagnosis with rough timing (BN or EN mix OK)",
    "• Current medicine — what it is for",
    "• Allergy / red-flag if any",
    "• Key follow-up hint"
  ]
}
Rules:
- 5 to 8 bullets max.
- Each bullet starts with "• ".
- Focus on: আগে কী অসুখ ছিল, কী ওষুধ খাচ্ছে ও কেন, অ্যালার্জি।
- Be clinically careful; do not invent diagnoses not in the data.
- If data is sparse, say so briefly.
PROMPT;
            $postData = [
                'model' => GROQ_MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                ],
                'temperature' => 0.15,
                'max_tokens' => 900,
                'response_format' => ['type' => 'json_object'],
            ];
            $ch = curl_init(GROQ_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 12,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $content = $data['choices'][0]['message']['content'] ?? '';
                $parsed = json_decode($content, true);
                if (is_array($parsed) && !empty($parsed['bullets']) && is_array($parsed['bullets'])) {
                    return [
                        'success' => true,
                        'headline_bn' => $parsed['headline_bn'] ?? '',
                        'headline_en' => $parsed['headline_en'] ?? '',
                        'bullets' => array_values(array_filter(array_map('strval', $parsed['bullets']))),
                        'source' => 'groq',
                    ];
                }
            }
        }

        return self::fallbackDoctorBrief($payload);
    }

    private static function fallbackDoctorBrief(array $payload): array {
        $bullets = [];
        foreach ($payload['allergies'] as $a) {
            if (($a['name'] ?? '') === '') continue;
            $sev = $a['severity'] ?? '';
            $bullets[] = '• Allergy: ' . $a['name'] . ($sev ? " ({$sev})" : '');
        }
        foreach ($payload['conditions'] as $c) {
            $name = $c['condition'] ?? 'Condition';
            $when = $c['when'] ?: ($c['diagnosed'] ?? '');
            $bullets[] = '• Past / chronic: ' . $name . ($when ? " — {$when}" : '');
        }
        foreach ($payload['recent_diagnoses'] as $rx) {
            $meds = !empty($rx['medicines']) ? ' · Rx: ' . implode(', ', $rx['medicines']) : '';
            $bullets[] = '• ' . ($rx['diagnosis'] ?: 'Visit') . ($rx['when'] ? ' (' . $rx['when'] . ')' : '') . $meds;
        }
        foreach ($payload['active_medicines'] as $m) {
            $bullets[] = '• Taking: ' . $m['name'] . ($m['frequency'] ? ' — ' . $m['frequency'] : '') . ' (active)';
        }
        if (empty($bullets)) {
            $bullets[] = '• Limited prior history in MedCore for this patient yet.';
        }
        $bullets = array_slice($bullets, 0, 8);
        $p = $payload['patient']['name'] ?? 'Patient';
        return [
            'success' => true,
            'headline_bn' => $p . ' — সংক্ষিপ্ত ক্লিনিক্যাল সারাংশ',
            'headline_en' => 'Quick clinical brief',
            'bullets' => $bullets,
            'source' => 'fallback',
        ];
    }

    /**
     * Explain prescribed medicines for the patient portal (what each drug is for).
     * @param array $meds list of {generic_name, brand_name, dosage, frequency, strength, dosage_form, drug_class?}
     */
    public static function explainMedicines(array $meds): array {
        if (empty($meds)) {
            return ['success' => true, 'explanations' => []];
        }

        $apiKey = GROQ_API_KEY;
        $payload = array_map(static function ($m) {
            return [
                'name' => trim(($m['brand_name'] ?? '') ?: ($m['generic_name'] ?? 'Medicine')),
                'generic' => $m['generic_name'] ?? '',
                'dose' => trim(($m['dosage'] ?? '') . ' ' . ($m['strength'] ?? '')),
                'frequency' => $m['frequency'] ?? '',
                'form' => $m['dosage_form'] ?? '',
                'class' => $m['drug_class'] ?? '',
            ];
        }, $meds);

        if (!empty($apiKey)) {
            $systemPrompt = <<<PROMPT
You are MedCore Sidekick — a patient advocate pharmacist educator for Bangladesh.
For each medicine, explain in simple language WHY the doctor prescribed it and HOW to take it safely.
Return ONLY valid JSON:
{
  "explanations": [
    {
      "name": "exact medicine name from input",
      "purpose_bn": "এই ওষুধ কেন খাচ্ছেন — সহজ বাংলায় ১–২ বাক্য",
      "purpose_en": "Why prescribed — 1 short English sentence",
      "how_to_take_bn": "কখন/কীভাবে খাবেন (খাওয়ার আগে/পরে, সময়)",
      "caution_bn": "সতর্কতা — সংক্ষেপে",
      "icon_hint": "heart|sugar|infection|pain|stomach|lung|other"
    }
  ]
}
PROMPT;
            $postData = [
                'model' => GROQ_MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => json_encode(['medicines' => $payload], JSON_UNESCAPED_UNICODE)],
                ],
                'temperature' => 0.2,
                'max_tokens' => 1400,
                'response_format' => ['type' => 'json_object'],
            ];
            $ch = curl_init(GROQ_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 14,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $content = $data['choices'][0]['message']['content'] ?? '';
                $parsed = json_decode($content, true);
                if (is_array($parsed) && isset($parsed['explanations'])) {
                    return ['success' => true, 'explanations' => $parsed['explanations'], 'source' => 'groq'];
                }
            }
        }

        return ['success' => true, 'explanations' => self::fallbackMedicineExplanations($payload), 'source' => 'fallback'];
    }

    private static function fallbackMedicineExplanations(array $payload): array {
        $out = [];
        foreach ($payload as $m) {
            $name = $m['name'] ?: 'Medicine';
            $g = strtolower($m['generic'] . ' ' . $name . ' ' . ($m['class'] ?? ''));
            $purposeBn = 'ডাক্তার আপনার চিকিৎসার জন্য এই ওষুধটি নির্ধারণ করেছেন।';
            $purposeEn = 'Your doctor prescribed this as part of your treatment plan.';
            $hint = 'other';
            if (preg_match('/amlo|losartan|atenolol|bisoprolol|pressure|hypertens|ace|arb|beta/', $g)) {
                $purposeBn = 'এটি রক্তচাপ নিয়ন্ত্রণে সাহায্য করে — হৃদপিণ্ড ও রক্তনালীর চাপ কমায়।';
                $purposeEn = 'Helps control blood pressure and protect the heart.';
                $hint = 'heart';
            } elseif (preg_match('/metformin|glimepiride|insulin|gliclazide|diabetes|glucose/', $g)) {
                $purposeBn = 'এটি রক্তের শর্করা (সুগার) নিয়ন্ত্রণে সাহায্য করে।';
                $purposeEn = 'Helps control blood sugar levels.';
                $hint = 'sugar';
            } elseif (preg_match('/amox|azithro|cipro|cef|antibiotic|moxiflox/', $g)) {
                $purposeBn = 'এটি ব্যাকটেরিয়া সংক্রমণ প্রতিরোধ/চিকিৎসার অ্যান্টিবায়োটিক।';
                $purposeEn = 'Antibiotic to treat bacterial infection.';
                $hint = 'infection';
            } elseif (preg_match('/omeprazole|esomeprazole|pantoprazole|ranitidine|antacid/', $g)) {
                $purposeBn = 'এটি পেটের অ্যাসিড কমায় — গ্যাস্ট্রিক/আলসার সুরক্ষায়।';
                $purposeEn = 'Reduces stomach acid for gastric protection.';
                $hint = 'stomach';
            } elseif (preg_match('/paracetamol|ibuprofen|naproxen|diclofenac|tramadol|pain/', $g)) {
                $purposeBn = 'এটি ব্যথা ও জ্বর কমাতে সাহায্য করে।';
                $purposeEn = 'Helps relieve pain and/or fever.';
                $hint = 'pain';
            } elseif (preg_match('/atorvastatin|rosuvastatin|statin|lipid|cholesterol/', $g)) {
                $purposeBn = 'এটি রক্তের ক্ষতিকর কোলেস্টেরল কমায়।';
                $purposeEn = 'Lowers harmful cholesterol in the blood.';
                $hint = 'heart';
            } elseif (preg_match('/salbutamol|montelukast|inhal|asthma|budesonide/', $g)) {
                $purposeBn = 'এটি শ্বাসনালী খুলে শ্বাসকষ্ট কমাতে সাহায্য করে।';
                $purposeEn = 'Helps open airways and ease breathing.';
                $hint = 'lung';
            }
            $freq = $m['frequency'] ?: 'ডাক্তারের নির্দেশমতো';
            $out[] = [
                'name' => $name,
                'purpose_bn' => $purposeBn,
                'purpose_en' => $purposeEn,
                'how_to_take_bn' => 'মাত্রা: ' . ($m['dose'] ?: 'প্রেসক্রিপশন অনুযায়ী') . ' · সময়: ' . $freq . '। নিয়মিত খান, নিজে নিজে বন্ধ করবেন না।',
                'caution_bn' => 'অ্যালার্জি, বমি, তীব্র পার্শ্বপ্রতিক্রিয়া হলে ডাক্তারকে জানান।',
                'icon_hint' => $hint,
            ];
        }
        return $out;
    }

    private static function fallbackAnalysis(string $speechText): array {
        $terms = [];
        $questions = [];
        $t = strtolower($speechText);

        $dictionary = [
            'hypertension' => ['উচ্চ রক্তচাপ — রক্তনালীর দেওয়ালে অতিরিক্ত চাপ', 'High blood pressure'],
            'pressure' => ['রক্তচাপ (Blood Pressure)', 'Blood pressure measure'],
            'diabetes' => ['ডায়াবেটিস — রক্তে অতিরিক্ত গ্লুকোজ বা শর্করা', 'High blood sugar condition'],
            'ecg' => ['ইসিজি — হৃদপিণ্ডের বৈদ্যুতিক কার্যকলাপ পরিমাপ', 'Electrocardiogram of heart electrical activity'],
            'echo' => ['ইকোকার্ডিওগ্রাম — হৃদপিণ্ডের আল্ট্রাসাউন্ড পরীক্ষা', 'Echocardiogram heart ultrasound'],
            'antibiotic' => ['অ্যান্টিবায়োটিক — ব্যাকটেরিয়ার সংক্রমণ প্রতিরোধী ওষুধ', 'Medicine to treat bacterial infections'],
            'statin' => ['স্ট্যাটিন — রক্তের ক্ষতিকর কোলেস্টেরল কমানোর ওষুধ', 'Cholesterol-lowering medication'],
            'creatinine' => ['ক্রিয়েটিনিন — কিডনি বা বৃক্কের কার্যক্ষমতা মাপক রক্ত পরীক্ষা', 'Kidney function marker in blood'],
            'contraindication' => ['নিষিদ্ধ বা ক্ষতিকর অবস্থা — যা নির্দিষ্ট রোগীর ক্ষেত্রে দেওয়া নিষেধ', 'Condition making treatment inadvisable'],
            'dyspnea' => ['শ্বাসকষ্ট — স্বাভাবিক শ্বাস নিতে কষ্ট হওয়া', 'Difficulty breathing or shortness of breath']
        ];

        foreach ($dictionary as $word => $exp) {
            if (strpos($t, $word) !== false) {
                $terms[] = [
                    'term' => ucfirst($word),
                    'explanation_bn' => $exp[0],
                    'explanation_en' => $exp[1],
                    'importance' => 'HIGH'
                ];
            }
        }

        if (strpos($t, 'medicine') !== false || strpos($t, 'tablet') !== false || strpos($t, 'ওষুধ') !== false) {
            $questions[] = [
                'question_bn' => 'এই ওষুধটি কি খালি পেটে খাব নাকি ভরা পেটে?',
                'question_en' => 'Should I take this medication before or after meals?',
                'reason' => 'ওষুধের কার্যকারিতা বজায় রাখতে ও গ্যাস্ট্রিক সমস্যা এড়াতে'
            ];
            $questions[] = [
                'question_bn' => 'কতদিন একটানা এই ওষুধ খেতে হবে? নিজে নিজে কি বন্ধ করা যাবে?',
                'question_en' => 'How long should I take this course? Can I stop when feeling better?',
                'reason' => 'অ্যান্টিবায়োটিক বা প্রেসারের ওষুধ হঠাৎ বন্ধের ঝুঁকি এড়াতে'
            ];
        } else {
            $questions[] = [
                'question_bn' => 'আমার এই সমস্যার মূল কারণ কী এবং আমি দৈনন্দিন জীবনে কী পরিবর্তন আনব?',
                'question_en' => 'What is the root cause and what lifestyle changes should I make?',
                'reason' => 'রোগ প্রতিরোধ ও স্থায়ী সুস্থতার জন্য'
            ];
        }

        return [
            'has_terms' => !empty($terms),
            'terms' => $terms,
            'suggested_questions' => $questions,
            'key_findings' => ['ডাক্তারের সাথে আলোচনা চলছে। প্রয়োজনীয় তথ্য রেকর্ড করা হচ্ছে।']
        ];
    }

    private static function fallbackSummary(string $transcript, string $doc, string $spec): string {
        return <<<MD
# MedCore Sidekick — অ্যাপয়েন্টমেন্ট সারাংশ ও পরবর্তী পদক্ষেপ

**পরামর্শকারী চিকিৎসক:** {$doc} ({$spec})  
**তারিখ ও সময়:** `date('d M Y, h:i A')`  
**অ্যাডভোকেট মোড:** রিয়েল-টাইম পেশেন্ট ভয়েস রেকর্ডার

---

### ১. প্রধান স্বাস্থ্য পর্যবেক্ষণ (Key Observations)
- ডাক্তার রোগীর বর্তমান উপসর্গ ও পূর্ববর্তী চিকিৎসার ইতিহাস পর্যালোচনা করেছেন।
- জীবনযাপন ও নিয়মিত খাদ্যাভ্যাস সম্পর্কে গুরুত্বপূর্ণ নির্দেশনা প্রদান করেছেন।

### ২. ওষুধ ও প্রেসক্রিপশন নির্দেশিকা (Medication Instructions)
- প্রেসক্রিপশনে উল্লেখিত ওষুধগুলো নিয়মিত ও সুনির্দিষ্ট সময়ে গ্রহণ করুন।
- কোনো ওষুধ বাদ পড়লে বা পার্শ্বপ্রতিক্রিয়া দেখা দিলে তাৎক্ষণিক চেম্বারে যোগাযোগ করুন।
- রক্তচাপ বা ডায়াবেটিসের ওষুধ ডাক্তারের পরামর্শ ছাড়া কখনো নিজে নিজে বন্ধ করবেন না।

### ৩. জরুরি সতর্ক সংকেত (Red Flag Symptoms)
- তীব্র বুকে ব্যথা, শ্বাসকষ্ট, অথবা হঠাৎ দৃষ্টিশক্তি বা কথা জড়ানো দেখা দিলে **তাৎক্ষণিক ৯৯৯ বা নিকটস্থ জরুরি বিভাগে (Emergency) যান**।

### ৪. পরবর্তী পদক্ষেপ (Next Steps)
1. প্রেসক্রিপশন অনুযায়ী ওষুধ সংগ্রহ করুন।
2. কোনো ডায়াগনস্টিক পরীক্ষা থাকলে MedCore অনুমোদিত ল্যাব থেকে টোকেন দিয়ে রিপোর্ট করুন।
3. আগামী পরামর্শের জন্য MedCore পোর্টাল থেকে ফলো-আপ সিরিয়াল বুক করুন।
MD;
    }
}
