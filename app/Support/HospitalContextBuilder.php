<?php

namespace App\Support;

use App\Models\User;
use App\Models\Client;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\OtherProfessional;
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
        } elseif ($user->user_type === 'other_professional') {
            // Ensure otherProfessionals relationship is loaded
            if (!$user->relationLoaded('otherProfessionals')) {
                $user->load('otherProfessionals');
            }
            
            $professional = $user->otherProfessionals;

            if ($professional) {
                // Professional-specific context
                $context['professional_profile'] = $this->buildOtherProfessionalProfile($professional);
                $context['professional_appointments'] = $this->buildOtherProfessionalAppointments($professional);
                $context['professional_clients'] = $this->buildOtherProfessionalClients($professional);
            } else {
                // Handle case where professional record doesn't exist yet
                $context['professional_profile'] = [];
                $context['professional_appointments'] = ['upcoming' => [], 'upcoming_count' => 0, 'today_count' => 0];
                $context['professional_clients'] = ['count' => 0, 'recent' => []];
            }
            
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

        // Add current date/time context FIRST
        $parts[] = $this->getCurrentDateTimeContext();
        $parts[] = ""; // Blank line for readability

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
        } elseif ($role === 'other_professional') {
            $prof = $context['professional_profile'] ?? [];
            $type = $prof['professional_type'] ?? 'Professional';
            $specialization = $prof['specialization'] ?? 'General';
            $parts[] = "The USER you are talking to is {$user['name']}, a {$type} specializing in {$specialization}.";
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

        // Appointments (enhanced with relative times - for clients)
        if (isset($context['appointments'])) {
            $apts = $context['appointments'];
            
            if (!empty($apts['upcoming'])) {
                $parts[] = "\nThe user has {$apts['upcoming_count']} upcoming appointment(s):";
                
                foreach (array_slice($apts['upcoming'], 0, 3) as $idx => $apt) {
                    $providerLabel = $apt['provider_name'];
                    $aptInfo = sprintf(
                        "%d. %s at %s (%s) with %s (%s)",
                        $idx + 1,
                        $apt['date'],
                        $apt['time'],
                        $apt['relative'],
                        $providerLabel,
                        $apt['provider_specialization']
                    );
                    
                    if (!empty($apt['symptoms'])) {
                        $aptInfo .= " - Reason: " . Str::limit($apt['symptoms'], 60);
                    }
                    
                    $aptInfo .= " [Status: {$apt['status']}]";
                    $parts[] = "   " . $aptInfo;
                }
            } else {
                $parts[] = "The user has NO upcoming appointments scheduled.";
            }
            
            // Add recent past appointments for context
            if (!empty($apts['past_recent'])) {
                $lastApt = $apts['past_recent'][0];
                $parts[] = "The user's last appointment was on {$lastApt['date']} ({$lastApt['relative']}) with {$lastApt['provider_name']}.";
            }
        }

        // Doctor profile (for doctors) - make it clear these are the USER's details
        if (isset($context['doctor_profile'])) {
            $doc = $context['doctor_profile'];
            if (!empty($doc['availability'])) {
                $parts[] = "The user's availability status: {$doc['availability']}.";
            }
            
            // Always show appointment information for doctors
            if (isset($context['doctor_appointments'])) {
                $appts = $context['doctor_appointments'];
                $upcomingCount = $appts['upcoming_count'] ?? 0;
                
                if ($upcomingCount > 0) {
                    $parts[] = "\nThe user has {$upcomingCount} upcoming appointments";
                    
                    if (!empty($appts['today_count'])) {
                        $parts[] = "({$appts['today_count']} today).";
                    } else {
                        $parts[] = "(none today).";
                    }
                    
                    if (!empty($appts['upcoming'])) {
                        $parts[] = "Next appointments:";
                        foreach (array_slice($appts['upcoming'], 0, 3) as $idx => $apt) {
                            $aptInfo = sprintf(
                                "%d. %s at %s (%s) - Patient: %s",
                                $idx + 1,
                                $apt['date'],
                                $apt['time'],
                                $apt['relative'],
                                $apt['client']
                            );
                            
                            if (!empty($apt['symptoms'])) {
                                $aptInfo .= " - Symptoms: " . Str::limit($apt['symptoms'], 60);
                            }
                            
                            if (!empty($apt['status'])) {
                                $aptInfo .= " [Status: {$apt['status']}]";
                            }
                            
                            $parts[] = "   " . $aptInfo;
                        }
                    }
                } else {
                    $parts[] = "\nThe user has NO upcoming appointments scheduled.";
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

        // Other Professional profile
        if (isset($context['professional_profile'])) {
            if (!empty($context['professional_appointments']['upcoming_count'])) {
                $parts[] = "\nThe user has {$context['professional_appointments']['upcoming_count']} upcoming appointments";
                
                if (!empty($context['professional_appointments']['today_count'])) {
                    $parts[] = "({$context['professional_appointments']['today_count']} today).";
                } else {
                    $parts[] = "(none today).";
                }
                
                if (!empty($context['professional_appointments']['upcoming'])) {
                    $parts[] = "Next appointments:";
                    foreach (array_slice($context['professional_appointments']['upcoming'], 0, 3) as $idx => $apt) {
                        $aptInfo = sprintf(
                            "%d. %s at %s (%s) - Patient: %s",
                            $idx + 1,
                            $apt['date'],
                            $apt['time'],
                            $apt['relative'],
                            $apt['client']
                        );
                        
                        if (!empty($apt['symptoms'])) {
                            $aptInfo .= " - Symptoms: " . Str::limit($apt['symptoms'], 60);
                        }
                        
                        if (!empty($apt['status'])) {
                            $aptInfo .= " [Status: {$apt['status']}]";
                        }
                        
                        $parts[] = "   " . $aptInfo;
                    }
                }
            }

            if (!empty($context['professional_clients']['count'])) {
                $parts[] = "The user has {$context['professional_clients']['count']} assigned clients.";
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
     * Build appointment context with enhanced date/time accuracy and timezone awareness
     */
    private function buildAppointmentContext(Client $client): array
    {
        $now = Carbon::now('Europe/Berlin');
        
        $upcoming = Appointment::where('client_id', $client->id)
            ->where('date_time', '>', $now)
            ->where('status', '!=', 'cancelled')
            ->with([
                'doctor' => function($query) {
                    $query->with('user');
                },
                'otherProfessional' => function($query) {
                    $query->with('user');
                }
            ])
            ->orderBy('date_time', 'asc')
            ->limit(3)
            ->get()
            ->map(function($apt) use ($now) {
                $aptDate = Carbon::parse($apt->date_time)->setTimezone('Europe/Berlin');
                
                // Resolve provider name and specialization - check both doctor and otherProfessional
                $providerName = 'Unknown';
                $providerSpec = 'Unknown';
                
                if ($apt->doctor_id && $apt->doctor && $apt->doctor->user) {
                    $providerName = "Dr. " . $apt->doctor->user->name;
                    $providerSpec = $apt->doctor->specialization ?? 'Unknown';
                } elseif ($apt->other_professional_id && $apt->otherProfessional && $apt->otherProfessional->user) {
                    $providerName = $apt->otherProfessional->user->name;
                    $providerSpec = $apt->otherProfessional->specialization ?? $apt->otherProfessional->professional_type ?? 'Professional';
                }

                return [
                    'id' => $apt->id,
                    'date' => $aptDate->format('l, F j, Y'), // e.g., "Monday, January 10, 2026"
                    'time' => $aptDate->format('g:i A'), // e.g., "2:30 PM"
                    'datetime_full' => $aptDate->format('Y-m-d H:i:s'),
                    'relative' => $this->getRelativeTime($aptDate, $now),
                    'days_until' => $now->diffInDays($aptDate),
                    'hours_until' => $now->diffInHours($aptDate),
                    'provider_name' => $providerName,
                    'provider_specialization' => $providerSpec,
                    'symptoms' => $apt->symptoms,
                    'status' => $apt->status,
                ];
            })
            ->toArray();

        // Also get past appointments for context
        $past = Appointment::where('client_id', $client->id)
            ->where('date_time', '<=', $now)
            ->where('status', '!=', 'cancelled')
            ->with([
                'doctor' => function($query) {
                    $query->with('user');
                },
                'otherProfessional' => function($query) {
                    $query->with('user');
                }
            ])
            ->orderBy('date_time', 'desc')
            ->limit(2)
            ->get()
            ->map(function($apt) use ($now) {
                $aptDate = Carbon::parse($apt->date_time)->setTimezone('Europe/Berlin');
                
                // Resolve provider name
                $providerName = 'Unknown';
                if ($apt->doctor_id && $apt->doctor && $apt->doctor->user) {
                    $providerName = "Dr. " . $apt->doctor->user->name;
                } elseif ($apt->other_professional_id && $apt->otherProfessional && $apt->otherProfessional->user) {
                    $providerName = $apt->otherProfessional->user->name;
                }

                return [
                    'date' => $aptDate->format('l, F j, Y'),
                    'time' => $aptDate->format('g:i A'),
                    'relative' => $aptDate->diffForHumans($now),
                    'provider_name' => $providerName,
                    'status' => $apt->status,
                ];
            })
            ->toArray();

        return [
            'upcoming' => $upcoming,
            'upcoming_count' => count($upcoming),
            'past_recent' => $past,
            'has_appointments' => count($upcoming) > 0,
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
     * Build doctor appointments context with enhanced accuracy
     */
    private function buildDoctorAppointments(Doctor $doctor): array
    {
        $now = Carbon::now('Europe/Berlin');
        
        $upcoming = Appointment::where('doctor_id', $doctor->id)
            ->where('date_time', '>', $now)
            ->where('status', '!=', 'cancelled')
            ->with('client.user')
            ->orderBy('date_time', 'asc')
            ->limit(5)
            ->get()
            ->map(function($apt) use ($now) {
                $aptDate = Carbon::parse($apt->date_time)->setTimezone('Europe/Berlin');
                
                return [
                    'id' => $apt->id,
                    'date' => $aptDate->format('l, F j, Y'),
                    'time' => $aptDate->format('g:i A'),
                    'relative' => $this->getRelativeTime($aptDate, $now),
                    'client' => $apt->client->user->name ?? 'Unknown',
                    'symptoms' => $apt->symptoms,
                    'status' => $apt->status,
                ];
            })
            ->toArray();

        return [
            'upcoming' => $upcoming,
            'upcoming_count' => Appointment::where('doctor_id', $doctor->id)
                ->where('date_time', '>', $now)
                ->where('status', '!=', 'cancelled')
                ->count(),
            'today_count' => Appointment::where('doctor_id', $doctor->id)
                ->whereDate('date_time', $now->toDateString())
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
     * Build other professional profile context
     */
    private function buildOtherProfessionalProfile(OtherProfessional $professional): array
    {
        return [
            'professional_type' => $professional->professional_type,
            'specialization' => $professional->specialization,
            'license_number' => $professional->license_number,
        ];
    }

    /**
     * Build other professional appointments context
     */
    private function buildOtherProfessionalAppointments(OtherProfessional $professional): array
    {
        $now = Carbon::now('Europe/Berlin');
        
        // Ensure we have a valid professional ID
        if (!$professional || !$professional->id) {
            return [
                'upcoming' => [],
                'upcoming_count' => 0,
                'today_count' => 0,
            ];
        }
        
        $upcoming = Appointment::where('other_professional_id', $professional->id)
            ->where('date_time', '>', $now)
            ->where('status', '!=', 'cancelled')
            ->with(['client.user', 'doctor.user', 'otherProfessional.user'])
            ->orderBy('date_time', 'asc')
            ->limit(5)
            ->get()
            ->map(function($apt) use ($now) {
                $aptDate = Carbon::parse($apt->date_time)->setTimezone('Europe/Berlin');
                
                // Safely get client name
                $clientName = 'Unknown';
                if ($apt->client && $apt->client->user) {
                    $clientName = $apt->client->user->name;
                }
                
                return [
                    'id' => $apt->id,
                    'date' => $aptDate->format('l, F j, Y'),
                    'time' => $aptDate->format('g:i A'),
                    'relative' => $this->getRelativeTime($aptDate, $now),
                    'client' => $clientName,
                    'symptoms' => $apt->symptoms,
                    'status' => $apt->status,
                ];
            })
            ->toArray();

        return [
            'upcoming' => $upcoming,
            'upcoming_count' => Appointment::where('other_professional_id', $professional->id)
                ->where('date_time', '>', $now)
                ->where('status', '!=', 'cancelled')
                ->count(),
            'today_count' => Appointment::where('other_professional_id', $professional->id)
                ->whereDate('date_time', $now->toDateString())
                ->where('status', '!=', 'cancelled')
                ->count(),
        ];
    }

    /**
     * Build other professional clients context
     */
    private function buildOtherProfessionalClients(OtherProfessional $professional): array
    {
        // Get clients from recent appointments since direct assignment column might not exist
        $clientIds = Appointment::where('other_professional_id', $professional->id)
            ->distinct()
            ->limit(20)
            ->pluck('client_id');

        $clients = Client::whereIn('id', $clientIds)
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
     * Build system instructions with enhanced guidelines and examples
     */
    private function buildSystemInstructions(): string
    {
        return <<<'PROMPT'
You are an AI assistant helping the user navigate the Phoenix Hospital Management System. The user is talking to YOU - you are NOT the user. The context above describes THE USER, not you.

CRITICAL ACCURACY RULES:
1. ONLY use information explicitly provided in the context above
2. NEVER make up or hallucinate appointments, dates, or medical information
3. If you don't have information, say "I don't have that information" or "Let me check the system"
4. When discussing dates/times, ALWAYS reference the current date/time provided at the top of this prompt
5. Use the relative time descriptions provided (e.g., "tomorrow at 2:00 PM", "in 3 days")
6. If asked about appointments not listed above, clearly state "I don't see any appointments matching that description"

DATE/TIME HANDLING:
- The current date and time are provided at the very top of this prompt with timezone (Europe/Berlin)
- All appointment dates and times are listed with their relative descriptions
- When user asks "when is my next appointment", use the FIRST appointment in the upcoming list
- When user asks about "tomorrow" or specific days, calculate based on the current date provided
- Always include both the full date AND the relative time (e.g., "Monday, January 13, 2026 at 2:00 PM (in 3 days)")

IMPORTANT: Always remember you are an AI assistant. The user is a human (doctor/nurse/client/other_professional) who needs your help.

For CLIENT users:
- Help them schedule appointments
- Help them find appropriate doctors based on their symptoms
- Help them understand their medical records
- Help them message doctors
- Provide general health guidance (ALWAYS with disclaimers)
- NEVER diagnose - always recommend consulting a doctor
- Always prioritize their assigned doctor in suggestions
- If they have NO appointments, suggest scheduling one instead of making up appointment data

For DOCTOR users:
- Help them manage their upcoming appointments and schedule
- Provide information about their assigned clients
- Assist with medical record management
- Provide administrative support for patient care
- Reference their appointment details and client information when relevant
- Help them prioritize based on appointment times and urgency

For NURSE users:
- Help them manage their assigned clients
- Provide administrative support
- Assist with patient care coordination

For OTHER PROFESSIONAL users (e.g., Public Health Officers, Physiologists, etc.):
- Help them manage their upcoming appointments and schedule
- Provide information about their clients/patients
- Assist with appointment management and patient care coordination
- Reference their appointment details and client information when relevant
- Help them prioritize based on appointment times and urgency
- Treat them similar to doctors in terms of appointment management capabilities

General guidelines:
- Be empathetic and professional
- Reference the user's context (their appointments, their clients, their records) when appropriate
- Include medical disclaimers for health topics when discussing with clients
- Remember: You are assisting the user, you are not the user
- Use bullet points and clear formatting for lists
- Be concise but thorough

EXAMPLES OF CORRECT RESPONSES:

User: "When is my next appointment?"
Good: "Your next appointment is on Monday, January 13, 2026 at 2:00 PM (in 3 days) with Dr. Smith (Cardiology). The appointment is for chest pain symptoms and the status is confirmed."
Bad: "You have an appointment next week" (too vague, missing details)

User: "Do I have any appointments tomorrow?"
Good (if yes): "Yes, you have an appointment tomorrow at 10:00 AM with Dr. Johnson (Dermatology)."
Good (if no): "No, you don't have any appointments scheduled for tomorrow. Would you like to schedule one?"
Bad: "I think you might have one" (uncertain, not factual)

User: "What appointments do I have?"
Good (if none): "You currently have no upcoming appointments scheduled. Would you like help scheduling an appointment?"
Bad (if none): "You probably have some appointments coming up" (making up information)

MEDICAL DISCLAIMER (use when providing health information to clients):
"Please note: This is general information only and not medical advice. For proper diagnosis and treatment, please consult with your doctor."
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

    /**
     * Get relative time description (e.g., "tomorrow", "in 3 days")
     */
    private function getRelativeTime(Carbon $date, Carbon $now): string
    {
        $diffInDays = $now->diffInDays($date, false);
        $diffInHours = $now->diffInHours($date, false);
        
        // Same day
        if ($date->isSameDay($now)) {
            if ($diffInHours < 1) {
                $minutes = $now->diffInMinutes($date, false);
                return "in {$minutes} minutes";
            }
            return "today at " . $date->format('g:i A');
        }
        
        // Tomorrow
        if ($date->isTomorrow()) {
            return "tomorrow at " . $date->format('g:i A');
        }
        
        // Within a week
        if ($diffInDays >= 0 && $diffInDays <= 7) {
            if ($diffInDays == 1) {
                return "tomorrow";
            }
            return "in {$diffInDays} days (" . $date->format('l') . ")";
        }
        
        // More than a week
        if ($diffInDays > 7 && $diffInDays <= 30) {
            $weeks = ceil($diffInDays / 7);
            return "in {$weeks} week" . ($weeks > 1 ? 's' : '');
        }
        
        // More than a month
        return $date->format('F j, Y');
    }

    /**
     * Get current date/time context for system prompt
     */
    private function getCurrentDateTimeContext(): string
    {
        $now = Carbon::now('Europe/Berlin');
        
        return sprintf(
            "Current date and time: %s (timezone: %s). Today is %s.",
            $now->format('l, F j, Y \a\t g:i A'),
            'Europe/Berlin',
            $now->format('l')
        );
    }
}

