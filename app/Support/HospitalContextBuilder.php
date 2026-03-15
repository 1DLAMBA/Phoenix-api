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
use App\Models\Assignments;
use App\Models\Notification;
use App\Models\Admin;
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
        $flags = $this->resolveQueryFlags($userMessage);

        $context = [
            'user' => $this->buildUserProfile($user),
            'query_type' => $flags['query_type'],
        ];

        if ($user->user_type === 'client' && $user->clients) {
            $client = $user->clients;
            $isGeneral = $flags['query_type'] === 'general';

            if ($flags['needs_medical']) {
                $context['medical'] = $this->buildMedicalContext($client);
            }

            if ($flags['needs_appointments']) {
                $context['appointments'] = $this->buildAppointmentContext($client);
            } else {
                $context['appointment_summary'] = $this->buildClientAppointmentSummary($client);
            }

            $context['relationships'] = $this->buildRelationshipContext($user, $client);

            if ($flags['needs_assignments']) {
                $context['assignments'] = $this->buildAssignmentContext($user);
            }

            if ($flags['needs_notifications']) {
                $context['notifications'] = $this->buildNotificationSummary($user);
            }

            if ($flags['needs_suggestions']) {
                $context['providers'] = $this->buildDoctorSuggestions($user, null, $userMessage);
            }

            if (!$isGeneral) {
                $context['memory'] = $this->buildConversationMemory($user);
            }
        } elseif ($user->user_type === 'doctor' && $user->doctors) {
            $doctor = $user->doctors;

            $context['doctor_profile'] = $this->buildDoctorProfile($doctor);

            if ($flags['needs_appointments']) {
                $context['doctor_appointments'] = $this->buildDoctorAppointments($doctor);
            } else {
                $context['doctor_schedule_summary'] = $this->buildDoctorAppointmentSummary($doctor);
            }

            $context['doctor_clients'] = $this->buildDoctorClients($doctor, $flags['query_type'] !== 'general');

            if ($flags['needs_records']) {
                $context['doctor_records'] = $this->buildDoctorMedicalRecords($doctor);
            }

            if ($flags['needs_assignments']) {
                $context['assignments'] = $this->buildAssignmentContext($user);
            }

            if ($flags['needs_notifications']) {
                $context['notifications'] = $this->buildNotificationSummary($user);
            }
        } elseif ($user->user_type === 'nurse' && $user->nurses) {
            $nurse = $user->nurses;

            $context['nurse_profile'] = $this->buildNurseProfile($nurse);
            $context['nurse_clients'] = $this->buildNurseClients($nurse, $flags['query_type'] !== 'general');

            if ($flags['needs_assignments']) {
                $context['assignments'] = $this->buildAssignmentContext($user);
            }

            if ($flags['needs_notifications']) {
                $context['notifications'] = $this->buildNotificationSummary($user);
            }
        } elseif ($user->user_type === 'other_professional') {
            if (!$user->relationLoaded('otherProfessionals')) {
                $user->load('otherProfessionals');
            }

            $professional = $user->otherProfessionals;

            if ($professional) {
                $context['professional_profile'] = $this->buildOtherProfessionalProfile($professional);

                if ($flags['needs_appointments']) {
                    $context['professional_appointments'] = $this->buildOtherProfessionalAppointments($professional);
                } else {
                    $context['professional_schedule_summary'] = $this->buildOtherProfessionalAppointmentSummary($professional);
                }

                $context['professional_clients'] = $this->buildOtherProfessionalClients($professional, $flags['query_type'] !== 'general');

                if ($flags['needs_records']) {
                    $context['professional_records'] = $this->buildOtherProfessionalMedicalRecords($professional);
                }
            } else {
                $context['professional_profile'] = [];
                $context['professional_appointments'] = ['upcoming' => [], 'upcoming_count' => 0, 'today_count' => 0];
                $context['professional_clients'] = ['count' => 0, 'recent' => []];
            }

            if ($flags['needs_notifications']) {
                $context['notifications'] = $this->buildNotificationSummary($user);
            }
        } elseif ($user->user_type === 'admin') {
            $context['admin_summary'] = $this->buildAdminSummary();
        }

        $context['instructions'] = $this->buildSystemInstructions($user->user_type, $flags);

        return $context;
    }

    /**
     * Format context as a concise system prompt for LLM
     */
    public function formatSystemPrompt(array $context): string
    {
        $parts = [];
        $parts[] = $this->getCurrentDateTimeContext();
        $parts[] = '';

        $user = $context['user'];
        $role = $user['role'];
        $parts[] = "USER: {$user['name']} | role={$role}" . (isset($user['age']) ? " | age={$user['age']}" : '');

        if (isset($context['medical'])) {
            $med = $context['medical'];
            $parts[] = "MEDICAL: allergies=" . (!empty($med['allergies']) ? implode(', ', $med['allergies']) : 'none');
            $parts[] = "MEDICAL_RECENT: " . (!empty($med['recent']) ? implode(' | ', array_slice($med['recent'], 0, 2)) : 'none');
            $parts[] = "DATA_BOUNDARY_MEDICAL: This is the complete medical summary available.";
        }

        if (isset($context['appointments'])) {
            $apts = $context['appointments'];
            $parts[] = "APPOINTMENTS: upcoming_count=" . ($apts['upcoming_count'] ?? 0);
            if (!empty($apts['upcoming'])) {
                foreach (array_slice($apts['upcoming'], 0, 3) as $idx => $apt) {
                    $parts[] = sprintf(
                        "%d) %s, %s (%s) | %s, %s | %s | %s",
                        $idx + 1,
                        $apt['date'],
                        $apt['time'],
                        $apt['relative'],
                        $apt['provider_name'],
                        $apt['provider_specialization'],
                        Str::limit($apt['symptoms'] ?? 'no symptoms provided', 40),
                        $apt['status'] ?? 'unknown'
                    );
                }
            } else {
                $parts[] = "NO_APPOINTMENTS: No upcoming appointments were found.";
            }
            $parts[] = "DATA_BOUNDARY_APPOINTMENTS: This is the complete appointment list. Do not reference unlisted appointments.";
        } elseif (isset($context['appointment_summary'])) {
            $summary = $context['appointment_summary'];
            $parts[] = "APPOINTMENT_SUMMARY: upcoming={$summary['upcoming_count']} | today={$summary['today_count']}";
            $parts[] = "DATA_BOUNDARY_APPOINTMENTS: Summary only. Do not infer specific appointment details.";
        }

        if (isset($context['doctor_profile'])) {
            $parts[] = "DOCTOR_PROFILE: specialization=" . ($context['doctor_profile']['specialization'] ?? 'Unknown') .
                " | availability=" . ($context['doctor_profile']['availability'] ?? 'Unknown');
        }
        if (isset($context['doctor_appointments'])) {
            $appts = $context['doctor_appointments'];
            $parts[] = "DOCTOR_APPOINTMENTS: upcoming={$appts['upcoming_count']} | today={$appts['today_count']}";
            foreach (array_slice($appts['upcoming'] ?? [], 0, 3) as $idx => $apt) {
                $parts[] = sprintf(
                    "%d) %s, %s (%s) | patient=%s | %s | %s",
                    $idx + 1,
                    $apt['date'],
                    $apt['time'],
                    $apt['relative'],
                    $apt['client'],
                    Str::limit($apt['symptoms'] ?? 'no symptoms provided', 40),
                    $apt['status'] ?? 'unknown'
                );
            }
            $parts[] = "DATA_BOUNDARY_DOCTOR_APPOINTMENTS: This is the complete doctor appointment list.";
        } elseif (isset($context['doctor_schedule_summary'])) {
            $summary = $context['doctor_schedule_summary'];
            $parts[] = "DOCTOR_SCHEDULE_SUMMARY: upcoming={$summary['upcoming_count']} | today={$summary['today_count']}";
            $parts[] = "DATA_BOUNDARY_DOCTOR_APPOINTMENTS: Summary only. No detailed doctor appointments provided.";
        }

        if (isset($context['professional_profile'])) {
            $parts[] = "PROFESSIONAL_PROFILE: type=" . ($context['professional_profile']['professional_type'] ?? 'Unknown') .
                " | specialization=" . ($context['professional_profile']['specialization'] ?? 'Unknown');
        }
        if (isset($context['professional_appointments'])) {
            $appts = $context['professional_appointments'];
            $parts[] = "PROFESSIONAL_APPOINTMENTS: upcoming={$appts['upcoming_count']} | today={$appts['today_count']}";
            foreach (array_slice($appts['upcoming'] ?? [], 0, 3) as $idx => $apt) {
                $parts[] = sprintf(
                    "%d) %s, %s (%s) | patient=%s | %s | %s",
                    $idx + 1,
                    $apt['date'],
                    $apt['time'],
                    $apt['relative'],
                    $apt['client'],
                    Str::limit($apt['symptoms'] ?? 'no symptoms provided', 40),
                    $apt['status'] ?? 'unknown'
                );
            }
            $parts[] = "DATA_BOUNDARY_PROFESSIONAL_APPOINTMENTS: This is the complete professional appointment list.";
        } elseif (isset($context['professional_schedule_summary'])) {
            $summary = $context['professional_schedule_summary'];
            $parts[] = "PROFESSIONAL_SCHEDULE_SUMMARY: upcoming={$summary['upcoming_count']} | today={$summary['today_count']}";
            $parts[] = "DATA_BOUNDARY_PROFESSIONAL_APPOINTMENTS: Summary only. No detailed appointments provided.";
        }

        if (isset($context['doctor_clients']['count'])) {
            $parts[] = "DOCTOR_CLIENTS: count={$context['doctor_clients']['count']}";
        }
        if (isset($context['nurse_clients']['count'])) {
            $parts[] = "NURSE_CLIENTS: count={$context['nurse_clients']['count']}";
        }
        if (isset($context['professional_clients']['count'])) {
            $parts[] = "PROFESSIONAL_CLIENTS: count={$context['professional_clients']['count']}";
        }

        if (isset($context['doctor_records']['count'])) {
            $parts[] = "DOCTOR_RECORDS: count={$context['doctor_records']['count']}";
        }
        if (isset($context['professional_records']['count'])) {
            $parts[] = "PROFESSIONAL_RECORDS: count={$context['professional_records']['count']}";
        }

        if (isset($context['relationships']['assigned_doctor'])) {
            $doc = $context['relationships']['assigned_doctor'];
            $parts[] = "RELATIONSHIP_DOCTOR: Dr. {$doc['name']} | {$doc['specialization']} | availability={$doc['availability']}";
        }
        if (isset($context['relationships']['assigned_nurse'])) {
            $nurse = $context['relationships']['assigned_nurse'];
            $parts[] = "RELATIONSHIP_NURSE: {$nurse['name']} | {$nurse['specialization']}";
        }
        if (!empty($context['relationships'])) {
            $parts[] = "DATA_BOUNDARY_RELATIONSHIPS: Only listed care-team relationships exist.";
        }

        if (isset($context['assignments'])) {
            $assignment = $context['assignments'];
            $parts[] = "ASSIGNMENTS: count={$assignment['count']}";
            foreach (array_slice($assignment['recent'] ?? [], 0, 3) as $idx => $item) {
                $parts[] = sprintf(
                    "%d) doctor=%s | nurse=%s | client=%s | status=%s | note=%s",
                    $idx + 1,
                    $item['doctor'] ?? 'N/A',
                    $item['nurse'] ?? 'N/A',
                    $item['client'] ?? 'N/A',
                    $item['status'] ?? 'unknown',
                    Str::limit($item['message'] ?? 'none', 40)
                );
            }
            $parts[] = "DATA_BOUNDARY_ASSIGNMENTS: This is the complete assignment data available.";
        }

        if (isset($context['notifications'])) {
            $notify = $context['notifications'];
            $parts[] = "NOTIFICATIONS: unread={$notify['unread_count']} | total={$notify['total_count']}";
            if (!empty($notify['recent_titles'])) {
                $parts[] = "NOTIFICATION_RECENT: " . implode(' | ', array_slice($notify['recent_titles'], 0, 3));
            }
            $parts[] = "DATA_BOUNDARY_NOTIFICATIONS: Do not invent notifications outside this list.";
        }

        if (array_key_exists('providers', $context)) {
            if (!empty($context['providers'])) {
                $parts[] = "PROVIDERS_MATCHED: count=" . count($context['providers']);
                foreach (array_slice($context['providers'], 0, 5) as $idx => $provider) {
                    $providerType = $provider['provider_type'] ?? 'unknown';
                    $prefix = match ($providerType) {
                        'doctor' => 'Dr. ',
                        'nurse' => 'Nurse ',
                        default => '',
                    };
                    $parts[] = sprintf(
                        "%d) %s%s | %s | type=%s%s",
                        $idx + 1,
                        $prefix,
                        $provider['name'],
                        $provider['specialization'] ?? 'General',
                        $providerType,
                        ($provider['is_assigned'] ?? false) ? ' | ASSIGNED' : ''
                    );
                }
            } else {
                $parts[] = "PROVIDERS_MATCHED: none";
            }
            $parts[] = "DATA_BOUNDARY_PROVIDERS: These are the only matched providers. Do not mention unlisted providers.";
        }

        if (isset($context['admin_summary'])) {
            $admin = $context['admin_summary'];
            $parts[] = "ADMIN_SUMMARY: users={$admin['users_count']} | clients={$admin['clients_count']} | doctors={$admin['doctors_count']} | nurses={$admin['nurses_count']} | professionals={$admin['other_professionals_count']} | appointments_today={$admin['appointments_today']} | upcoming_appointments={$admin['upcoming_appointments']}";
            $parts[] = "DATA_BOUNDARY_ADMIN: This is a summary only. Do not infer additional admin metrics.";
        }

        $parts[] = "END_OF_USER_DATA: Everything above is the complete available system data. Never invent, assume, exaggerate, or fabricate doctors, appointments, notifications, or records.";
        $parts[] = "";
        $parts[] = $context['instructions'];

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
                    'date' => $aptDate->format('D M j Y'),
                    'time' => $aptDate->format('g:i A'),
                    'relative' => $this->getRelativeTime($aptDate, $now),
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
                    'date' => $aptDate->format('D M j Y'),
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

        // Assigned nurse
        if ($client->assigned_nurse_id) {
            $nurse = Nurse::with('user')->find($client->assigned_nurse_id);
            if ($nurse && $nurse->user) {
                $context['assigned_nurse'] = [
                    'id' => $nurse->id,
                    'name' => $nurse->user->name,
                    'specialization' => $nurse->specialization,
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

        // Recent messages with assigned nurse (if exists)
        if (isset($context['assigned_nurse'])) {
            $nurse = Nurse::with('user')->find($context['assigned_nurse']['id']);

            if ($nurse && $nurse->user) {
                $recentMessages = Message::where(function($q) use ($user, $nurse) {
                    $q->where('sender_id', $user->id)
                      ->where('receiver_id', $nurse->user->id);
                })->orWhere(function($q) use ($user, $nurse) {
                    $q->where('sender_id', $nurse->user->id)
                      ->where('receiver_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();

                if ($recentMessages->isNotEmpty()) {
                    $context['recent_nurse_messages'] = $recentMessages->count() . ' recent messages';
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
    private function buildDoctorSuggestions(User $user, ?string $specialization, ?string $message): array
    {
        // Infer specialization if not provided
        if (empty($specialization) && $message) {
            $specialization = SpecializationInference::infer($message);
        }

        if (empty($specialization)) {
            return [];
        }

        // Get matching doctors, nurses, and other professionals
        $aliases = SpecializationAliases::aliasesFor($specialization);
        $doctors = Doctor::with('user')
            ->where(function ($q) use ($aliases) {
                foreach ($aliases as $alias) {
                    $q->orWhere('specialization', 'LIKE', '%' . $alias . '%');
                }
            })
            ->limit(8)
            ->get();

        $nurses = Nurse::with('user')
            ->where(function ($q) use ($aliases) {
                foreach ($aliases as $alias) {
                    $q->orWhere('specialization', 'LIKE', '%' . $alias . '%');
                }
            })
            ->limit(8)
            ->get();

        $otherProfessionals = OtherProfessional::with('user')
            ->where(function ($q) use ($aliases) {
                foreach ($aliases as $alias) {
                    $q->orWhere('specialization', 'LIKE', '%' . $alias . '%')
                      ->orWhere('professional_type', 'LIKE', '%' . $alias . '%');
                }
            })
            ->limit(8)
            ->get();

        $client = $user->clients;
        
        // Enhance with relationships and history
        $doctorData = $doctors->map(function($doctor) use ($client) {
            $data = [
                'id' => $doctor->id,
                'name' => $doctor->user->name ?? 'Unknown',
                'specialization' => $doctor->specialization,
                'availability' => $doctor->availability,
                'provider_type' => 'doctor',
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
        });

        $nurseData = $nurses->map(function($nurse) use ($client) {
            $data = [
                'id' => $nurse->id,
                'name' => $nurse->user->name ?? 'Unknown',
                'specialization' => $nurse->specialization ?: 'General Nursing',
                'availability' => null,
                'provider_type' => 'nurse',
            ];

            if ($client && $client->assigned_nurse_id === $nurse->id) {
                $data['is_assigned'] = true;
            }

            return $data;
        });

        $professionalData = $otherProfessionals->map(function($professional) use ($client) {
            $data = [
                'id' => $professional->id,
                'name' => $professional->user->name ?? 'Unknown',
                'specialization' => $professional->specialization ?: ($professional->professional_type ?? 'General'),
                'availability' => null,
                'provider_type' => 'other_professional',
            ];

            if ($client) {
                $apptCount = Appointment::where('client_id', $client->id)
                    ->where('other_professional_id', $professional->id)
                    ->count();
                if ($apptCount > 0) {
                    $data['previous_appointments'] = $apptCount;
                }
            }

            return $data;
        });

        return $doctorData
        ->concat($nurseData)
        ->concat($professionalData)
        ->sortByDesc(function($doctor) {
            $priority = 0;
            if ($doctor['is_assigned'] ?? false) $priority += 100;
            if (isset($doctor['previous_appointments'])) $priority += $doctor['previous_appointments'];
            return $priority;
        })
        ->values()
        ->toArray();
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
                    'date' => $aptDate->format('D M j Y'),
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
    private function buildDoctorClients(Doctor $doctor, bool $includeRecent = true): array
    {
        $clients = Client::where('assigned_doctor_id', $doctor->id)
            ->with('user')
            ->get();

        return [
            'count' => $clients->count(),
            'recent' => $includeRecent ? $clients->take(3)->map(function($client) {
                return $client->user->name ?? 'Unknown';
            })->toArray() : [],
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
    private function buildNurseClients($nurse, bool $includeRecent = true): array
    {
        $clients = Client::where('assigned_nurse_id', $nurse->id)
            ->with('user')
            ->get();

        return [
            'count' => $clients->count(),
            'recent' => $includeRecent ? $clients->take(3)->map(function($client) {
                return $client->user->name ?? 'Unknown';
            })->toArray() : [],
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
                    'date' => $aptDate->format('D M j Y'),
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
    private function buildOtherProfessionalClients(OtherProfessional $professional, bool $includeRecent = true): array
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
            'recent' => $includeRecent ? $clients->take(3)->map(function($client) {
                return $client->user->name ?? 'Unknown';
            })->toArray() : [],
        ];
    }

    /**
     * Build lightweight appointment summary for client role.
     */
    private function buildClientAppointmentSummary(Client $client): array
    {
        $now = Carbon::now('Europe/Berlin');

        return [
            'upcoming_count' => Appointment::where('client_id', $client->id)
                ->where('date_time', '>', $now)
                ->where('status', '!=', 'cancelled')
                ->count(),
            'today_count' => Appointment::where('client_id', $client->id)
                ->whereDate('date_time', $now->toDateString())
                ->where('status', '!=', 'cancelled')
                ->count(),
        ];
    }

    /**
     * Build lightweight doctor appointment summary.
     */
    private function buildDoctorAppointmentSummary(Doctor $doctor): array
    {
        $now = Carbon::now('Europe/Berlin');

        return [
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
     * Build lightweight other professional appointment summary.
     */
    private function buildOtherProfessionalAppointmentSummary(OtherProfessional $professional): array
    {
        $now = Carbon::now('Europe/Berlin');

        return [
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
     * Build assignments context based on user role.
     */
    private function buildAssignmentContext(User $user): array
    {
        $query = Assignments::with(['doctor.user', 'nurse.user', 'client.user'])
            ->orderBy('updated_at', 'desc');

        if ($user->user_type === 'doctor' && $user->doctors) {
            $query->where('assigned_doctor_id', $user->doctors->id);
        } elseif ($user->user_type === 'nurse' && $user->nurses) {
            $query->where('assigned_nurse_id', $user->nurses->id);
        } elseif ($user->user_type === 'client' && $user->clients) {
            $query->where('assigned_client_id', $user->clients->id);
        } elseif ($user->user_type !== 'admin') {
            return ['count' => 0, 'recent' => []];
        }

        $rows = $query->limit(5)->get();

        return [
            'count' => $rows->count(),
            'recent' => $rows->map(function ($assignment) {
                return [
                    'doctor' => $assignment->doctor->user->name ?? 'Unknown',
                    'nurse' => $assignment->nurse->user->name ?? 'Unknown',
                    'client' => $assignment->client->user->name ?? 'Unknown',
                    'status' => $assignment->status ?? 'unknown',
                    'message' => $assignment->assignment_message,
                ];
            })->toArray(),
        ];
    }

    /**
     * Build notification summary context.
     */
    private function buildNotificationSummary(User $user): array
    {
        $base = Notification::where('user_id', $user->id);
        $recent = (clone $base)->orderBy('created_at', 'desc')->limit(3)->get(['title']);

        return [
            'unread_count' => (clone $base)->whereNull('read_at')->count(),
            'total_count' => (clone $base)->count(),
            'recent_titles' => $recent->pluck('title')->filter()->values()->toArray(),
        ];
    }

    /**
     * Build other professional medical records context.
     */
    private function buildOtherProfessionalMedicalRecords(OtherProfessional $professional): array
    {
        $recent = MedicalRecord::where('other_professional_id', $professional->id)
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
            'count' => MedicalRecord::where('other_professional_id', $professional->id)->count(),
        ];
    }

    /**
     * Build admin summary context.
     */
    private function buildAdminSummary(): array
    {
        $now = Carbon::now('Europe/Berlin');

        return [
            'users_count' => User::count(),
            'clients_count' => Client::count(),
            'doctors_count' => Doctor::count(),
            'nurses_count' => Nurse::count(),
            'other_professionals_count' => OtherProfessional::count(),
            'admins_count' => Admin::count(),
            'appointments_today' => Appointment::whereDate('date_time', $now->toDateString())->count(),
            'upcoming_appointments' => Appointment::where('date_time', '>', $now)
                ->where('status', '!=', 'cancelled')
                ->count(),
        ];
    }

    /**
     * Build query flags for conditional context loading.
     */
    private function resolveQueryFlags(?string $message): array
    {
        $queryType = SpecializationInference::classifyQueryType($message);

        $flags = [
            'query_type' => $queryType,
            'needs_appointments' => in_array($queryType, ['appointment'], true),
            'needs_medical' => in_array($queryType, ['medical', 'records'], true) || $this->isHealthRelated($message),
            'needs_records' => in_array($queryType, ['medical', 'records'], true),
            'needs_assignments' => $queryType === 'assignment',
            'needs_notifications' => $queryType === 'notification',
            'needs_suggestions' => in_array($queryType, ['medical', 'records', 'appointment'], true),
        ];

        $flags['needs_appointments'] = $flags['needs_appointments'] || $this->hasAnyKeyword($message, [
            'appointment', 'book', 'schedule', 'reschedule', 'cancel',
        ]);
        $flags['needs_assignments'] = $flags['needs_assignments'] || $this->hasAnyKeyword($message, [
            'assignment', 'assigned', 'care team', 'team',
        ]);
        $flags['needs_notifications'] = $flags['needs_notifications'] || $this->hasAnyKeyword($message, [
            'notification', 'notifications', 'alert', 'unread', 'update',
        ]);
        $flags['needs_suggestions'] = $flags['needs_suggestions'] || $this->hasAnyKeyword($message, [
            'doctor', 'nurse', 'specialist', 'physio', 'therapy', 'counsel',
        ]);

        return $flags;
    }

    /**
     * Keyword checker helper.
     */
    private function hasAnyKeyword(?string $message, array $keywords): bool
    {
        if (empty($message)) {
            return false;
        }

        $lower = strtolower($message);
        foreach ($keywords as $keyword) {
            if (strpos($lower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build concise role-aware system instructions.
     */
    private function buildSystemInstructions(string $role, array $flags): string
    {
        $roleInstruction = match ($role) {
            'client' => 'Role focus: help with appointments, provider selection, and record understanding; never diagnose.',
            'doctor' => 'Role focus: support schedule, clients, and records accurately.',
            'nurse' => 'Role focus: support assigned clients, assignments, and care coordination.',
            'other_professional' => 'Role focus: support appointments, clients, and care coordination like a clinical professional.',
            'admin' => 'Role focus: support operational summaries and administrative insights.',
            default => 'Role focus: provide safe, concise system guidance.',
        };

        $queryHint = "Current query type: {$flags['query_type']}.";

        return implode("\n", [
            'You are the Phoenix hospital assistant. The user is a human; you are not the user.',
            'Only use facts present in the provided data block.',
            'If data is missing, explicitly say you do not have that information.',
            'Do not invent or exaggerate doctors, appointments, records, assignments, or notifications.',
            'Use concise bullet points when listing multiple items.',
            'For health topics with clients, include a brief medical disclaimer.',
            $roleInstruction,
            $queryHint,
        ]);
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

