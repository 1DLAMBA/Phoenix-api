<?php

namespace App\Support;

class SpecializationAliases
{
    public static function aliasesFor(string $specialization): array
    {
        $spec = strtolower(trim($specialization));
        $map = [
            'cardiology' => ['Cardiology', 'Cardiologist', 'Heart'],
            'dermatology' => ['Dermatology', 'Dermatologist', 'Skin'],
            'neurology' => ['Neurology', 'Neurologist', 'Neuro'],
            'obstetrics' => ['Obstetrics', 'OB', 'Obstetrician', 'Obstetrics and Gynecology', 'OBGYN', 'OB/GYN'],
            'gynecology' => ['Gynecology', 'Gynaecology', 'Gynecologist', 'Obstetrics and Gynecology', 'OBGYN', 'OB/GYN'],
            'pediatrics' => ['Pediatrics', 'Pediatrician', 'Child Health'],
            'orthopedics' => ['Orthopedics', 'Orthopaedics', 'Orthopedic Surgeon', 'Bone & Joint'],
            'ophthalmology' => ['Ophthalmology', 'Ophthalmologist', 'Eye'],
            'endocrinology' => ['Endocrinology', 'Endocrinologist', 'Hormone'],
            'pulmonology' => ['Pulmonology', 'Pulmonologist', 'Respiratory'],
            'nephrology' => ['Nephrology', 'Nephrologist', 'Renal'],
            'gastroenterology' => ['Gastroenterology', 'Gastroenterologist', 'GI'],
            'hepatology' => ['Hepatology', 'Hepatologist', 'Liver'],
            'urology' => ['Urology', 'Urologist'],
            'oncology' => ['Oncology', 'Oncologist', 'Cancer'],
            'rheumatology' => ['Rheumatology', 'Rheumatologist'],
            'hematology' => ['Hematology', 'Hematologist', 'Blood'],
            'infectious disease' => ['Infectious Disease', 'Infectious Diseases', 'ID Specialist'],
            'allergy and immunology' => ['Allergy and Immunology', 'Allergist', 'Immunologist'],
            'otolaryngology' => ['Otolaryngology', 'ENT', 'Ear Nose Throat', 'Otorhinolaryngology'],
            'psychiatry' => ['Psychiatry', 'Psychiatrist', 'Mental Health'],
            'general surgery' => ['General Surgery', 'General Surgeon'],
            'vascular surgery' => ['Vascular Surgery', 'Vascular Surgeon'],
            'geriatrics' => ['Geriatrics', 'Geriatrician', 'Elderly Care'],
            'sports medicine' => ['Sports Medicine', 'Sports Physician'],
            'dentistry' => ['Dentistry', 'Dentist', 'Dental'],
            'physiotherapy' => ['Physiotherapy', 'Physio', 'Physical Therapy', 'Rehabilitation'],
            'counseling' => ['Counseling', 'Counsellor', 'Counselor', 'Therapist'],
            'public health' => ['Public Health', 'Public Health Officer', 'Community Health', 'Epidemiology']
        ];

        return $map[$spec] ?? [$specialization];
    }
}

