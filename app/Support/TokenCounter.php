<?php

namespace App\Support;

class TokenCounter
{
    /**
     * Rough estimation: 1 token ≈ 4 characters for English text
     * This is a conservative estimate for OpenAI-style tokenizers
     */
    private const CHARS_PER_TOKEN = 4;
    
    /**
     * Estimate token count for a string
     */
    public static function estimate(string $text): int
    {
        if (empty($text)) {
            return 0;
        }
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        // Estimate based on character count
        return (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);
    }
    
    /**
     * Estimate tokens for an array of messages
     * Messages format: ['role' => 'user|assistant|system', 'content' => '...']
     */
    public static function estimateMessages(array $messages): int
    {
        $total = 0;
        
        foreach ($messages as $message) {
            // Count role token (typically 1-2 tokens)
            $total += 2;
            
            // Count content tokens
            if (isset($message['content']) && is_string($message['content'])) {
                $total += self::estimate($message['content']);
            }
            
            // Add overhead for message structure (approximately 3-4 tokens)
            $total += 4;
        }
        
        return $total;
    }
    
    /**
     * Check if messages should be truncated
     */
    public static function shouldTruncate(array $messages, int $limit = 6000): bool
    {
        return self::estimateMessages($messages) > $limit;
    }
    
    /**
     * Get recommended max tokens for response based on context size
     */
    public static function getRecommendedMaxTokens(int $contextTokens, int $totalLimit = 8192): int
    {
        // Reserve 20% of total limit for response
        $reservedForResponse = (int) ($totalLimit * 0.2);
        
        // Calculate available tokens
        $available = $totalLimit - $contextTokens;
        
        // Return the smaller of: reserved amount or available amount (with minimum of 100)
        return max(100, min($reservedForResponse, $available));
    }
}
