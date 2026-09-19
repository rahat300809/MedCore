<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';

try {
    $db = getDB();
    $cnt = $db->query("SELECT COUNT(*) FROM patient_diagnostics")->fetchColumn();
    if ($cnt == 0) {
        $records = [
            [
                'diagnostic_uid' => 'DIAG-20260910-0001',
                'patient_id' => 1,
                'hospital_id' => 1,
                'ordered_by_doctor_id' => 1,
                'diagnostic_category' => 'RADIOLOGY',
                'modality' => 'XRAY',
                'title' => 'Chest X-Ray PA View (Digital Radiography)',
                'body_part' => 'Chest / Thorax',
                'clinical_indication' => 'Chronic dry cough and mild dyspnea on exertion for 3 weeks.',
                'findings' => 'Lung fields are clear bilaterally. No active focal consolidation, pleural effusion, or pneumothorax. Cardiothoracic ratio is normal (48%). Bony cage and soft tissues are unremarkable.',
                'impression' => 'Normal chest radiograph. No acute cardiopulmonary disease identified.',
                'test_date' => '2026-09-10',
                'report_date' => '2026-09-10',
                'reporting_specialist' => 'Dr. Farhana Ahmed, MBBS, MD (Radiology)',
                'status' => 'VERIFIED',
                'storage_type' => 'DRIVE_URL',
                'file_path' => null,
                'file_name' => 'Chest_XRay_PA_PT000001.jpg',
                'file_type' => 'image/jpeg',
                'drive_url' => 'https://drive.google.com/file/d/sample_chest_xray/view?usp=sharing',
                'verified_by' => 1
            ],
            [
                'diagnostic_uid' => 'DIAG-20260912-0002',
                'patient_id' => 1,
                'hospital_id' => 1,
                'ordered_by_doctor_id' => 1,
                'diagnostic_category' => 'RADIOLOGY',
                'modality' => 'MRI',
                'title' => 'Brain MRI with & without IV Gadolinium Contrast (1.5T)',
                'body_part' => 'Brain / Neurocranium',
                'clinical_indication' => 'Recurrent tension-type headaches, intermittent visual blurring.',
                'findings' => 'Axial T1, T2, FLAIR, DWI and post-contrast coronal sequences acquired. Normal gray-white matter differentiation. Ventricular system, basal cisterns and sulci are symmetric and age-appropriate. No mass effect, midline shift, abnormal leptomeningeal enhancement or restricted diffusion.',
                'impression' => 'Unremarkable Brain MRI. No evidence of intracranial space-occupying lesion, infarction, or acute demyelinating process.',
                'test_date' => '2026-09-12',
                'report_date' => '2026-09-13',
                'reporting_specialist' => 'Prof. Dr. M. A. Rashid, FCPS, FRCR (Neuroradiology)',
                'status' => 'VERIFIED',
                'storage_type' => 'DRIVE_URL',
                'file_path' => null,
                'file_name' => 'Brain_MRI_Neuro_PT000001.pdf',
                'file_type' => 'application/pdf',
                'drive_url' => 'https://drive.google.com/file/d/sample_brain_mri_series/view?usp=sharing',
                'verified_by' => 1
            ],
            [
                'diagnostic_uid' => 'DIAG-20260915-0003',
                'patient_id' => 1,
                'hospital_id' => 2,
                'ordered_by_doctor_id' => 2,
                'diagnostic_category' => 'RADIOLOGY',
                'modality' => 'ULTRASOUND',
                'title' => 'Whole Abdomen Ultrasound (Color Doppler)',
                'body_part' => 'Abdomen & Pelvis',
                'clinical_indication' => 'Right upper quadrant post-prandial discomfort.',
                'findings' => 'Liver is normal in size (13.8 cm) with smooth margins and normal parenchymal echogenicity. Gallbladder is well-distended with thin wall; no calculi or sludge detected. Common bile duct is normal caliber (4.2 mm). Spleen, pancreas, and kidneys are within normal limits. Urinary bladder is normal.',
                'impression' => 'Normal sonographic examination of the whole abdomen. No cholelithiasis or organomegaly.',
                'test_date' => '2026-09-15',
                'report_date' => '2026-09-15',
                'reporting_specialist' => 'Dr. Tariqul Islam, MBBS, D-Card, MD',
                'status' => 'COMPLETED',
                'storage_type' => 'DRIVE_URL',
                'file_path' => null,
                'file_name' => 'USG_Abdomen_Doppler.pdf',
                'file_type' => 'application/pdf',
                'drive_url' => 'https://drive.google.com/file/d/sample_ultrasound_report/view?usp=sharing',
                'verified_by' => 2
            ]
        ];

        $stmt = $db->prepare("
            INSERT INTO patient_diagnostics
            (diagnostic_uid, patient_id, hospital_id, ordered_by_doctor_id, diagnostic_category,
             modality, title, body_part, clinical_indication, findings, impression, test_date,
             report_date, reporting_specialist, status, storage_type, file_path, file_name,
             file_type, drive_url, verified_by, verified_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($records as $r) {
            $stmt->execute([
                $r['diagnostic_uid'], $r['patient_id'], $r['hospital_id'], $r['ordered_by_doctor_id'],
                $r['diagnostic_category'], $r['modality'], $r['title'], $r['body_part'],
                $r['clinical_indication'], $r['findings'], $r['impression'], $r['test_date'],
                $r['report_date'], $r['reporting_specialist'], $r['status'], $r['storage_type'],
                $r['file_path'], $r['file_name'], $r['file_type'], $r['drive_url'], $r['verified_by']
            ]);
        }
        echo "Seeded " . count($records) . " diagnostic imaging records!\n";
    } else {
        echo "Diagnostics records already exist ({$cnt} records)\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
