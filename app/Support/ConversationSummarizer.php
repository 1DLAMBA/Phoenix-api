<?php

namespace App\Support;

class ConversationSummarizer
{
    /**
     * Summarize conversation messages, preserving recent messages
     * 
     * @param array $messages Array of messages with 'role' and 'content'
     * @param int $preserveRecent Number of recent messages to preserve (default: 10)
     * @return array Summarized messages
     */
    public static function summarize(array $messages, int $preserveRecent = 10): array
    {
        if (count($messages) <= $preserveRecent) {
            return $messages;
        }
        
        // Split messages into: older (to summarize) and recent (to preserve)
        $toSummarize = array_slice($messages, 0, -$preserveRecent);
        $recent = array_slice($messages, -$preserveRecent);
        
        // Create summary of older messages
        $summary = self::createSummary($toSummarize);
        
        // Combine summary with recent messages
        return array_merge([$summary], $recent);
    }
    
    /**
     * Create a summary message from older conversation
     */
    private static function createSummary(array $messages): array
    {
        $summary = "Previous conversation summary: ";
        
        $userMessages = [];
        $assistantMessages = [];
        
        foreach ($messages as $msg) {
            if (!isset($msg['role']) || !isset($msg['content'])) {
                continue;
            }
            
            $content = trim($msg['content']);
            if (empty($content)) {
                continue;
            }
            
            if ($msg['role'] === 'user') {
                $userMessages[] = $content;
            } elseif ($msg['role'] === 'assistant') {
                $assistantMessages[] = $content;
            }
        }
        
        // Extract key topics/facts
        $topics = self::extractKeyTopics($userMessages);
        $responses = self::extractKeyTopics($assistantMessages);
        
        if (!empty($topics)) {
            $summary .= "User discussed: " . implode(', ', array_slice($topics, 0, 5)) . ". ";
        }
        
        if (!empty($responses)) {
            $summary .= "Assistant provided information about: " . implode(', ', array_slice($responses, 0, 3)) . ". ";
        }
        
        $summary .= "Full conversation history available in previous messages.";
        
        return [
            'role' => 'system',
            'content' => $summary
        ];
    }
    
    /**
     * Extract key topics from messages (simple keyword extraction)
     */
    private static function extractKeyTopics(array $messages): array
    {
        $keywords = [
            'appointment', 'doctor', 'schedule', 'date', 'time',
            'symptom', 'pain', 'treatment', 'medication', 'medicine',
            'diagnosis', 'medical record', 'health', 'patient',
            'clinic', 'hospital', 'prescription', 'test', 'result'
        ];
        
        $found = [];
        $text = strtolower(implode(' ', $messages));
        
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $found[] = $keyword;
            }
        }
        
        return array_unique($found);
    }
    
    /**
     * Extract key medical facts from messages
     */
    public static function extractKeyFacts(array $messages): array
    {
        $facts = [];
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'user' && isset($msg['content'])) {
                $content = strtolower($msg['content']);
                
                // Look for appointment mentions
                if (preg_match('/appointment.*?(?:on|for|at)\s+([^,\.]+)/i', $msg['content'], $matches)) {
                    $facts[] = 'Appointment mentioned: ' . trim($matches[1]);
                }
                
                // Look for symptom mentions
                if (preg_match('/\b(?:symptom|pain|ache|feeling)\b.*?:\s*([^,\.]+)/i', $msg['content'], $matches)) {
                    $facts[] = 'Symptom: ' . trim($matches[1]);
                }
            }
        }
        
        return $facts;
    }
    
    /**
     * Preserve recent messages, removing older ones
     */
    public static function preserveRecent(array $messages, int $count): array
    {
        return array_slice($messages, -$count);
    }
}
