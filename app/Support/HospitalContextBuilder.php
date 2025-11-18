<?php

namespace App\Support;

use App\Models\User;
use App\Models\Client;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Message;
use App\Models\AiConversation;
use Carbon\Carbon;
use Illuminate\Support\Str;

class HospitalContextBuilder
{
    private ?int $currentConversationId = null;

    /**
     * Build comprehensive context for a user
     */
    public function buildForUser(User $user, ?string $userMessage = null, ?int $conversationId = null): array
    {
        $this->currentConversationId = $conversationId;

        $context = [
            'user' => $this->buildUserProfile($user),
        ];

        // Add role-specific context
        if ($user->user_type === 'client' && $user->clients) {
            $client = $user->clients;

            // Medical context (only if health-related)
            if ($this->isHealthRelated($userMessage)) {
                $context['medical'] = $this->buildMedicalContext($client);
            }

            // Appointment context
            $context['appointments'] = $this->buildAppointmentContext($client);

            // Relationship context
            $context['relationships'] = $this->buildRelationshipContext($user, $client);

            // Conversation memory
            $context['memory'] = $this->buildConversationMemory($user);
        } elseif ($user->user_type === 'doctor' && $user->doctors) {
            $doctor = $user->doctors;
            
            // Doctor-specific context
            $context['doctor_profile'] = $this->buildDoctorProfile($doctor);
            $context['doctor_appointments'] = $this->buildDoctorAppointments($doctor);
            $context['doctor_clients'] = $this->buildDoctorClients($doctor);
            $context['doctor_records'] = $this->buildDoctorMedicalRecords($doctor);
            
            // Conversation memory
            $context['memory'] = $this->buildConversationMemory($user);
        } elseif ($user->user_type === 'nurse' && $user->nurses) {
            $nurse = $user->nurses;
            
            // Nurse-specific context
            $context['nurse_profile'] = $this->buildNurseProfile($nurse);
            $context['nurse_clients'] = $this->buildNurseClients($nurse);
            
            // Conversation memory
            $context['memory'] = $this->buildConversationMemory($user);
        }

        // Doctor suggestions (only for clients)
        if ($user->user_type === 'client') {
            $context['doctors'] = $this->buildDoctorSuggestions($user, null, $userMessage);
        }

        // System instructions
        $context['instructions'] = $this->buildSystemInstructions();

        return $context;
    }

    /**
     * Format context as a concise system prompt for LLM
     */
    public function formatSystemPrompt(array $context): string
    {
        $parts = [];

        // Start with clear statement about the user
        $user = $context['user'];
        $role = $user['role'];
        
        if ($role === 'doctor') {
            $doc = $context['doctor_profile'] ?? [];
            $specialization = $doc['specialization'] ?? 'Unknown';
            $parts[] = "The USER you are talking to is Dr. {$user['name']}, a doctor specializing in {$specialization}.";
        } elseif ($role === 'nurse') {
            $nurse = $context['nurse_profile'] ?? [];
            $specialization = $nurse['specialization'] ?? 'Unknown';
            $parts[] = "The USER you are talking to is {$user['name']}, a nurse specializing in {$specialization}.";
        } else {
            $parts[] = "The USER you are talking to is {$user['name']}, a client/patient.";
            if (isset($user['age'])) {
                $parts[] = "The user is {$user['age']} years old.";
            }
        }

        // Medical context (if present - for clients)
        if (isset($context['medical'])) {
            $med = $context['medical'];
            if (!empty($med['allergies'])) {
                $parts[] = "The user has allergies: " . implode(', ', $med['allergies']);
            }
            if (!empty($med['recent'])) {
                $parts[] = "The user's recent medical history: " . implode('; ', array_slice($med['recent'], 0, 2));
            }
        }

        // Appointments (concise - for clients)
        if (isset($context['appointments'])) {
            $apts = $context['appointments'];
            if (!empty($apts['upcoming'])) {
                $next = $apts['upcoming'][0];
                $status = isset($next['status']) ? " (status: {$next['status']})" : '';
                $parts[] = "The user has an upcoming appointment on {$next['date']} with {$next['doctor']}{$status}.";
            }
        }

        // Doctor profile (for doctors) - make it clear these are the USER's details
        if (isset($context['doctor_profile'])) {
            $doc = $context['doctor_profile'];
            if (!empty($doc['availability'])) {
                $parts[] = "The user's availability status: {$doc['availability']}.";
            }
            if (!empty($context['doctor_appointments']['upcoming_count'])) {
                $parts[] = "The user has {$context['doctor_appointments']['upcoming_count']} upcoming appointments.";
                if (!empty($context['doctor_appointments']['upcoming'])) {
                    $next = $context['doctor_appointments']['upcoming'][0];
                    $status = isset($next['status']) ? ", status: {$next['status']}" : '';
                    $parts[] = "The user's next appointment is on {$next['date']} with patient {$next['client']} (symptoms: {$next['symptoms']}{$status}).";
                }
            }
            if (!empty($context['doctor_clients']['count'])) {
                $parts[] = "The user has {$context['doctor_clients']['count']} assigned clients.";
            }
            if (!empty($context['doctor_records']['count'])) {
                $parts[] = "The user has created {$context['doctor_records']['count']} medical records.";
            }
        }

        // Nurse profile (for nurses) - make it clear these are the USER's details
        if (isset($context['nurse_profile'])) {
            if (!empty($context['nurse_clients']['count'])) {
                $parts[] = "The user has {$context['nurse_clients']['count']} assigned clients.";
            }
        }

        // Assigned doctor (for clients)
        if (isset($context['relationships']['assigned_doctor'])) {
            $doc = $context['relationships']['assigned_doctor'];
            $parts[] = "The user's assigned doctor is Dr. {$doc['name']} ({$doc['specialization']}).";
        }

        // Available doctors (concise - only for clients)
        if (!empty($context['doctors'])) {
            $doctorList = array_map(function($d) {
                $marker = ($d['is_assigned'] ?? false) ? ' [ASSIGNED]' : '';
                return "Dr. {$d['name']} ({$d['specialization']}){$marker}";
            }, array_slice($context['doctors'], 0, 5));
            $parts[] = "Available doctors in the system: " . implode(', ', $doctorList);
        }

        // Instructions
        $parts[] = "\n" . $context['instructions'];

        return implode("\n", $parts);
    }

    /**
     * Build user profile with calculated fields
     */
    private function buildUserProfile(User $user): array
    {
        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->user_type,
        ];

        if ($user->user_type === 'client' && $user->clients) {
            $client = $user->clients;
            if ($client->date_of_birth) {
                $profile['age'] = Carbon::parse($client->date_of_birth)->age;
            }
        }

        return $profile;
    }

    /**
     * Build medical context for a client
     */
    private function buildMedicalContext(Client $client): array
    {
        $records = MedicalRecord::where('client_id', $client->id)
            ->with('doctor.user')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        $allergies = [];
        $recent = [];

        foreach ($records as $record) {
            if ($record->allergies) {
                $allergies = array_merge($allergies, array_filter(explode(',', $record->allergies)));
            }
            $recent[] = $record->diagnosis ?: 'No diagnosis';
        }

        return [
            'allergies' => array_unique($allergies),
            'recent' => array_slice($recent, 0, 3),
        ];
    }

    /**
     * Build appointment context
     */
    private function buildAppointmentContext(Client $client): array
    {
        $upcoming = Appointment::where('client_id', $client->id)
            ->where('date_time', '>', now())
            ->where('status', '!=', 'cancelled')
            ->with('doctor.user')
            ->orderBy('date_time', 'asc')
            ->limit(2)
            ->get()
            ->map(function($apt) {
                return [
                    'date' => Carbon::parse($apt->date_time)->format('M d, Y H:i'),
                    'doctor' => $apt->doctor->user->name ?? 'Unknown',
                    'symptoms' => Str::limit($apt->symptoms, 50),
                    'status' => $apt->status,
                ];
            })
            ->toArray();

        return [
            'upcoming' => $upcoming,
        ];
    }

    /**
     * Build relationship context
     */
    private function buildRelationshipContext(User $user, Client $client): array
    {
        $context = [];

        // Assigned doctor
        if ($client->assigned_doctor_id) {
            $doctor = Doctor::with('user')->find($client->assigned_doctor_id);
            if ($doctor && $doctor->user) {
                $context['assigned_doctor'] = [
                    'id' => $doctor->id,
                    'name' => $doctor->user->name,
                    'specialization' => $doctor->specialization,
                    'availability' => $doctor->availability,
                ];
            }
        }

        // Recent messages with assigned doctor (if exists)
        if (isset($context['assigned_doctor'])) {
            $doctor = Doctor::with('user')->find($context['assigned_doctor']['id']);
            
            if ($doctor && $doctor->user) {
                $recentMessages = Message::where(function($q) use ($user, $doctor) {
                    $q->where('sender_id', $user->id)
                      ->where('receiver_id', $doctor->user->id);
                })->orWhere(function($q) use ($user, $doctor) {
                    $q->where('sender_id', $doctor->user->id)
                      ->where('receiver_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();

                if ($recentMessages->isNotEmpty()) {
                    $context['recent_messages'] = $recentMessages->count() . ' recent messages';
                }
            }
        }

        return $context;
    }

    /**
     * Build conversation memory from previous AI conversations
     */
    private function buildConversationMemory(User $user): array
    {
        $conversations = AiConversation::where('user_id', $user->id)
            ->when($this->currentConversationId, function($q) {
                $q->where('id', '!=', $this->currentConversationId);
            })
            ->orderBy('updated_at', 'desc')
            ->limit(3)
            ->get();

        if ($conversations->isEmpty()) {
            return [];
        }

        $topics = $conversations->map(function($convo) {
            return $convo->title ?: Str::limit($convo->last_message ?? '', 30);
        })->toArray();

        return [
            'previous_topics' => $topics,
        ];
    }

    /**
     * Build enhanced doctor suggestions
     */
    private function buildDoctorSuggestions(User $user, ?string $specialization, ?string $message): ?array
    {
        // Infer specialization if not provided
        if (empty($specialization) && $message) {
            $specialization = SpecializationInference::infer($message);
        }

        if (empty($specialization)) {
            return null;
        }

        // Get matching doctors
        $aliases = SpecializationAliases::aliasesFor($specialization);
        $doctors = Doctor::with('user')
            ->where(function ($q) use ($aliases) {
                foreach ($aliases as $alias) {
                    $q->orWhere('specialization', 'LIKE', '%' . $alias . '%');
                }
            })
            ->limit(8)
            ->get();

        if ($doctors->isEmpty()) {
            return null;
        }

        $client = $user->clients;
        
        // Enhance with relationships and history
        $enhanced = $doctors->map(function($doctor) use ($client) {
            $data = [
                'id' => $doctor->id,
                'name' => $doctor->user->name ?? 'Unknown',
                'specialization' => $doctor->specialization,
                'availability' => $doctor->availability,
            ];

            // Check if assigned doctor
            if ($client && $client->assigned_doctor_id === $doctor->id) {
                $data['is_assigned'] = true;
            }

            // Count previous appointments
            if ($client) {
                $apptCount = Appointment::where('client_id', $client->id)
                    ->where('doctor_id', $doctor->id)
                    ->count();
                if ($apptCount > 0) {
                    $data['previous_appointments'] = $apptCount;
                }
            }

            return $data;
        })
        ->sortByDesc(function($doctor) {
            $priority = 0;
            if ($doctor['is_assigned'] ?? false) $priority += 100;
            if (isset($doctor['previous_appointments'])) $priority += $doctor['previous_appointments'];
            return $priority;
        })
        ->values()
        ->toArray();

        return $enhanced;
    }

    /**
     * Build doctor profile context
     */
    private function buildDoctorProfile(Doctor $doctor): array
    {
        return [
            'specialization' => $doctor->specialization,
            'availability' => $doctor->availability,
            'license_number' => $doctor->license_number,
        ];
    }

    /**
     * Build doctor appointments context
     */
    private function buildDoctorAppointments(Doctor $doctor): array
    {
        $upcoming = Appointment::where('doctor_id', $doctor->id)
            ->where('date_time', '>', now())
            ->where('status', '!=', 'cancelled')
            ->with('client.user')
            ->orderBy('date_time', 'asc')
            ->limit(3)
            ->get()
            ->map(function($apt) {
                return [
                    'date' => Carbon::parse($apt->date_time)->format('M d, Y H:i'),
                    'client' => $apt->client->user->name ?? 'Unknown',
                    'symptoms' => Str::limit($apt->symptoms, 50),
                    'status' => $apt->status,
                ];
            })
            ->toArray();

        return [
            'upcoming' => $upcoming,
            'upcoming_count' => Appointment::where('doctor_id', $doctor->id)
                ->where('date_time', '>', now())
                ->where('status', '!=', 'cancelled')
                ->count(),
        ];
    }

    /**
     * Build doctor clients context
     */
    private function buildDoctorClients(Doctor $doctor): array
    {
        $clients = Client::where('assigned_doctor_id', $doctor->id)
            ->with('user')
            ->get();

        return [
            'count' => $clients->count(),
            'recent' => $clients->take(3)->map(function($client) {
                return $client->user->name ?? 'Unknown';
            })->toArray(),
        ];
    }

    /**
     * Build doctor medical records context
     */
    private function buildDoctorMedicalRecords(Doctor $doctor): array
    {
        $recent = MedicalRecord::where('assigned_doctor_id', $doctor->id)
            ->with('client.user')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function($record) {
                return [
                    'client' => $record->client->user->name ?? 'Unknown',
                    'diagnosis' => Str::limit($record->diagnosis ?? 'No diagnosis', 40),
                    'date' => $record->created_at->format('M d, Y'),
                ];
            })
            ->toArray();

        return [
            'recent' => $recent,
            'count' => MedicalRecord::where('assigned_doctor_id', $doctor->id)->count(),
        ];
    }

    /**
     * Build nurse profile context
     */
    private function buildNurseProfile($nurse): array
    {
        return [
            'specialization' => $nurse->specialization,
            'license_number' => $nurse->license_number,
        ];
    }

    /**
     * Build nurse clients context
     */
    private function buildNurseClients($nurse): array
    {
        $clients = Client::where('assigned_nurse_id', $nurse->id)
            ->with('user')
            ->get();

        return [
            'count' => $clients->count(),
            'recent' => $clients->take(3)->map(function($client) {
                return $client->user->name ?? 'Unknown';
            })->toArray(),
        ];
    }

    /**
     * Build system instructions
     */
    private function buildSystemInstructions(): string
    {
        return <<<'PROMPT'
You are an AI assistant helping the user navigate the Phoenix Hospital Management System. The user is talking to YOU - you are NOT the user. The context above describes THE USER, not you.

IMPORTANT: Always remember you are an AI assistant. The user is a human (doctor/nurse/client) who needs your help.

For CLIENT users:
- Help them schedule appointments
- Help them find appropriate doctors
- Help them understand their medical records
- Help them message doctors
- Provide general health guidance (with disclaimers)
- NEVER diagnose - always recommend consulting a doctor
- Always prioritize their assigned doctor in suggestions

For DOCTOR users:
- Help them manage their upcoming appointments and schedule
- Provide information about their assigned clients
- Assist with medical record management
- Provide administrative support for patient care
- Reference their appointment details and client information when relevant

For NURSE users:
- Help them manage their assigned clients
- Provide administrative support
- Assist with patient care coordination

General guidelines:
- Be empathetic and professional
- Reference the user's context (their appointments, their clients, their records) when appropriate
- Include medical disclaimers for health topics when discussing with clients
- Remember: You are assisting the user, you are not the user
PROMPT;
    }

    /**
     * Check if message is health-related
     */
    private function isHealthRelated(?string $message): bool
    {
        if (empty($message)) {
            return false;
        }

        $healthKeywords = [
            'pain', 'symptom', 'diagnosis', 'treatment', 'medicine', 'medication',
            'allergy', 'allergic', 'sick', 'illness', 'disease', 'condition',
            'doctor', 'appointment', 'medical', 'health', 'hospital'
        ];

        $lower = strtolower($message);
        foreach ($healthKeywords as $keyword) {
            if (strpos($lower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}

