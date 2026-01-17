<?php

namespace App\Support;

class SpecializationInference
{
    public static function infer(?string $text): ?string
    {
        if (empty($text)) return null;
        $map = [
            // Cardiology
            'heart' => 'Cardiology', 'cardio' => 'Cardiology', 'chest pain' => 'Cardiology', 'palpitation' => 'Cardiology', 'hypertension' => 'Cardiology', 'high blood pressure' => 'Cardiology', 'angina' => 'Cardiology', 'heart attack' => 'Cardiology', 'mi ' => 'Cardiology',

            // Dermatology
            'skin' => 'Dermatology', 'rash' => 'Dermatology', 'derma' => 'Dermatology', 'acne' => 'Dermatology', 'eczema' => 'Dermatology', 'psoriasis' => 'Dermatology', 'itch' => 'Dermatology', 'hives' => 'Dermatology',

            // Neurology
            'brain' => 'Neurology', 'neuro' => 'Neurology', 'seizure' => 'Neurology', 'epilep' => 'Neurology', 'stroke' => 'Neurology', 'migraine' => 'Neurology', 'headache' => 'Neurology', 'numbness' => 'Neurology', 'weakness' => 'Neurology', 'parkinson' => 'Neurology',

            // Obstetrics & Gynecology
            'pregnan' => 'Obstetrics', 'pregnancy' => 'Obstetrics', 'prenatal' => 'Obstetrics', 'obgyn' => 'Gynecology', 'gyneco' => 'Gynecology', 'menstrual' => 'Gynecology', 'period pain' => 'Gynecology', 'fertility' => 'Gynecology', 'ovarian' => 'Gynecology', 'uterine' => 'Gynecology',

            // Pediatrics
            'child' => 'Pediatrics', 'pediatric' => 'Pediatrics', 'toddler' => 'Pediatrics', 'baby' => 'Pediatrics', 'infant' => 'Pediatrics', 'newborn' => 'Pediatrics', 'school-age' => 'Pediatrics',

            // Orthopedics
            'bone' => 'Orthopedics', 'fracture' => 'Orthopedics', 'sprain' => 'Orthopedics', 'joint pain' => 'Orthopedics', 'back pain' => 'Orthopedics', 'spine' => 'Orthopedics', 'knee pain' => 'Orthopedics', 'shoulder pain' => 'Orthopedics',

            // Ophthalmology
            'eye' => 'Ophthalmology', 'vision' => 'Ophthalmology', 'cataract' => 'Ophthalmology', 'glaucoma' => 'Ophthalmology', 'red eye' => 'Ophthalmology', 'conjunctivitis' => 'Ophthalmology',

            // Endocrinology
            'diabetes' => 'Endocrinology', 'thyroid' => 'Endocrinology', 'hormone' => 'Endocrinology', 'endocrine' => 'Endocrinology', 'pcos' => 'Endocrinology', 'pcod' => 'Endocrinology',

            // Pulmonology
            'lung' => 'Pulmonology', 'lungs' => 'Pulmonology', 'asthma' => 'Pulmonology', 'breath' => 'Pulmonology', 'shortness of breath' => 'Pulmonology', 'cough' => 'Pulmonology', 'pneumonia' => 'Pulmonology', 'copd' => 'Pulmonology',

            // Nephrology
            'kidney' => 'Nephrology', 'renal' => 'Nephrology', 'dialysis' => 'Nephrology', 'proteinuria' => 'Nephrology',

            // Gastroenterology & Hepatology
            'stomach' => 'Gastroenterology', 'ulcer' => 'Gastroenterology', 'diarrhea' => 'Gastroenterology', 'constipation' => 'Gastroenterology', 'ibs' => 'Gastroenterology',
            'liver' => 'Hepatology', 'hepatitis' => 'Hepatology', 'cirrhosis' => 'Hepatology',

            // Urology
            'urine' => 'Urology', 'urinary' => 'Urology', 'uti' => 'Urology', 'bladder' => 'Urology', 'prostate' => 'Urology', 'erectile' => 'Urology', 'incontinence' => 'Urology', 'kidney stone' => 'Urology', 'stones' => 'Urology',

            // Oncology
            'cancer' => 'Oncology', 'tumor' => 'Oncology', 'chemotherapy' => 'Oncology', 'chemo' => 'Oncology', 'oncology' => 'Oncology',

            // Rheumatology
            'arthritis' => 'Rheumatology', 'joint swelling' => 'Rheumatology', 'autoimmune' => 'Rheumatology', 'lupus' => 'Rheumatology', 'rheumatoid' => 'Rheumatology', 'gout' => 'Rheumatology',

            // Hematology
            'anemia' => 'Hematology', 'blood disorder' => 'Hematology', 'leukemia' => 'Hematology', 'sickle' => 'Hematology', 'clot' => 'Hematology',

            // Infectious Disease
            'infection' => 'Infectious Disease', 'infected' => 'Infectious Disease', 'hiv' => 'Infectious Disease', 'malaria' => 'Infectious Disease', 'tb' => 'Infectious Disease', 'tuberc' => 'Infectious Disease', 'sepsis' => 'Infectious Disease', 'fever' => 'Infectious Disease',

            // Allergy & Immunology
            'allergy' => 'Allergy and Immunology', 'allergic' => 'Allergy and Immunology', 'hay fever' => 'Allergy and Immunology', 'rhinitis' => 'Allergy and Immunology', 'immunology' => 'Allergy and Immunology',

            // ENT / Otolaryngology
            'ear' => 'Otolaryngology', 'nose' => 'Otolaryngology', 'throat' => 'Otolaryngology', 'sinus' => 'Otolaryngology', 'tonsil' => 'Otolaryngology', 'hearing' => 'Otolaryngology', 'otitis' => 'Otolaryngology', 'tinnitus' => 'Otolaryngology',

            // Psychiatry
            'depression' => 'Psychiatry', 'anxiety' => 'Psychiatry', 'bipolar' => 'Psychiatry', 'schizophrenia' => 'Psychiatry', 'mental health' => 'Psychiatry', 'insomnia' => 'Psychiatry',

            // General & Vascular Surgery
            'appendicitis' => 'General Surgery', 'hernia' => 'General Surgery', 'gallbladder' => 'General Surgery', 'cholecyst' => 'General Surgery', 'surgery' => 'General Surgery',
            'varicose' => 'Vascular Surgery', 'peripheral artery' => 'Vascular Surgery', 'aneurysm' => 'Vascular Surgery',

            // Dentistry (Teeth)
            'tooth' => 'Dentistry', 'teeth' => 'Dentistry', 'dental' => 'Dentistry', 'toothache' => 'Dentistry', 'cavity' => 'Dentistry', 'gum' => 'Dentistry', 'gingivitis' => 'Dentistry', 'orthodont' => 'Dentistry',

            // Other
            'elderly' => 'Geriatrics', 'dementia' => 'Geriatrics', 'alzheimer' => 'Geriatrics',
            'sports injury' => 'Sports Medicine', 'athlete' => 'Sports Medicine', 'concussion' => 'Sports Medicine',
        ];
        $lower = strtolower($text);
        foreach ($map as $needle => $spec) {
            if (strpos($lower, $needle) !== false) return $spec;
        }
        return null;
    }
    
    /**
     * Classify query type for temperature adjustment
     * Returns: 'appointment', 'medical', or 'general'
     */
    public static function classifyQueryType(?string $text): string
    {
        if (empty($text)) {
            return 'general';
        }
        
        $lower = strtolower($text);
        
        // Appointment-related keywords
        $appointmentKeywords = [
            'appointment', 'schedule', 'book', 'cancel', 'reschedule',
            'when is my', 'what time', 'next appointment', 'upcoming appointment',
            'do i have an appointment', 'appointment with', 'set up appointment'
        ];
        
        foreach ($appointmentKeywords as $keyword) {
            if (strpos($lower, $keyword) !== false) {
                return 'appointment';
            }
        }
        
        // Medical-related keywords
        $medicalKeywords = [
            'symptom', 'pain', 'ache', 'diagnosis', 'treatment', 'medicine', 'medication',
            'disease', 'illness', 'condition', 'should i take', 'what medication',
            'what should i do for', 'how to treat', 'side effect', 'dosage',
            'prescription', 'medical advice', 'health advice', 'cure', 'therapy'
        ];
        
        foreach ($medicalKeywords as $keyword) {
            if (strpos($lower, $keyword) !== false) {
                return 'medical';
            }
        }
        
        return 'general';
    }
}

