<?php

namespace App\Services;

class PromptGenerationService
{
    /**
     * Generate review analysis prompt for any LLM provider.
     *
     * @param array  $reviews       Array of review data
     * @param string $format        'single' for single prompt, 'chat' for system/user messages
     * @param int    $maxTextLength Maximum characters per review text
     *
     * @return array Formatted prompt data
     */
    public static function generateReviewAnalysisPrompt(
        array $reviews,
        string $format = 'single',
        int $maxTextLength = 300
    ): array {
        $coreInstructions = self::getCoreAnalysisInstructions();
        $reviewsText = self::formatReviewsForAnalysis($reviews, $maxTextLength);
        $responseFormat = self::getResponseFormatInstructions();

        if ($format === 'chat') {
            return [
                'system' => $coreInstructions,
                'user'   => $reviewsText."\n\n".$responseFormat,
            ];
        }

        return [
            'prompt' => $coreInstructions."\n\n".$reviewsText."\n\n".$responseFormat,
        ];
    }

    /**
     * Get the core analysis instructions used by all providers.
     *
     * These instructions are designed to be BALANCED and ACCURATE, not biased toward
     * finding fake reviews. Genuine reviews are the norm; fake reviews are the exception.
     */
    private static function getCoreAnalysisInstructions(): string
    {
        return "Analyze reviews for authenticity on a 0-100 scale (0=definitely genuine, 100=definitely fake).\n\n".
               'IMPORTANT: Be ACCURATE and BALANCED. Most reviews on established products are genuine. '.
               'High ratings for quality products are NORMAL, not suspicious. '.
               "A product with 90%+ 5-star reviews is not automatically fake - quality products earn good reviews.\n\n".
               "STRONG GENUINE SIGNALS (reduce score significantly):\n".
               "- Verified purchase (-20): Strong authenticity indicator\n".
               "- Detailed personal experience (-25): Specific scenarios, family context, comparisons to alternatives\n".
               "- Specific product knowledge (-20): Technical details, feature-specific feedback, measurements\n".
               "- Balanced perspective (-15): Mentions both pros AND cons, even in positive reviews\n".
               "- Constructive criticism (-15): Specific complaints or suggestions for improvement\n".
               "- Long, detailed review (-10): Reviews over 100 words with substance\n\n".
               "FAKE SIGNALS (increase score):\n".
               "- Generic praise only (+15): 'Great product!' with no specifics\n".
               "- Unverified purchase (+5): Minor concern, not definitive\n".
               "- Marketing language (+20): Reads like ad copy, promotional phrases\n".
               "- Repetitive patterns (+25): Same phrases across multiple reviews\n".
               "- No personal context (+10): No indication of actual product use\n\n".
               "SCORING GUIDELINES:\n".
               "0-20: Clearly genuine - detailed, personal, specific\n".
               "21-40: Likely genuine - some authentic signals present\n".
               "41-60: Uncertain - mixed signals, insufficient information\n".
               "61-80: Suspicious - multiple fake indicators\n".
               "81-100: Likely fake - clear manipulation patterns\n\n".
               'DEFAULT TO GENUINE when uncertain. Err on the side of authenticity.';
    }

    /**
     * Format reviews for analysis with efficient structure.
     * Uses compact format to reduce token usage by ~12-15% without content loss.
     */
    private static function formatReviewsForAnalysis(array $reviews, int $maxTextLength): string
    {
        $reviewsText = '';

        foreach ($reviews as $review) {
            $verified = isset($review['meta_data']['verified_purchase']) && $review['meta_data']['verified_purchase']
                ? 'V'
                : 'U';
            $rating = $review['rating'] ?? '?';

            $text = '';
            if (isset($review['review_text'])) {
                $text = self::cleanUtf8Text(substr($review['review_text'], 0, $maxTextLength));
            } elseif (isset($review['text'])) {
                $text = self::cleanUtf8Text(substr($review['text'], 0, $maxTextLength));
            }

            // Compact format: ID|V/U|Rating★|Text
            // Saves ~30 chars per review vs "Review ID (Verified, Rating★): Text"
            $reviewsText .= "{$review['id']}|{$verified}|{$rating}★|{$text}\n";
        }

        return $reviewsText;
    }

    /**
     * Get response format instructions - aggregate analysis with optional examples.
     *
     * Instructions emphasize balanced analysis and recognition of genuine review patterns.
     */
    private static function getResponseFormatInstructions(): string
    {
        return 'Respond with JSON:\n'.
               '{\n'.
               '  "fake_percentage": <number 0-100>,\n'.
               '  "confidence": <"high"|"medium"|"low">,\n'.
               '  "explanation": "<comprehensive 3-4 paragraph BALANCED analysis>",\n'.
               '  "fake_examples": [\n'.
               '    {\n'.
               '      "review_number": <1-based index>,\n'.
               '      "text": "<brief excerpt>",\n'.
               '      "reason": "<specific reason why this appears fake>"\n'.
               '    }\n'.
               '  ],\n'.
               '  "genuine_examples": [\n'.
               '    {\n'.
               '      "review_number": <1-based index>,\n'.
               '      "text": "<brief excerpt>",\n'.
               '      "reason": "<why this appears genuine - personal context, specific details, etc.>"\n'.
               '    }\n'.
               '  ],\n'.
               '  "key_patterns": ["<pattern1>", "<pattern2>"],\n'.
               '  "product_insights": "<2-3 sentence analysis based on genuine reviews>"\n'.
               '}\n\n'.
               'CRITICAL ANALYSIS GUIDELINES:\n'.
               '1. Recognize that high ratings for quality products are NORMAL, not evidence of manipulation\n'.
               '2. Detailed reviews with personal context are strong genuine indicators\n'.
               '3. Verified purchases significantly increase authenticity likelihood\n'.
               '4. Only flag reviews as fake when there are CLEAR manipulation patterns\n\n'.
               'EXPLANATION STRUCTURE (4 paragraphs separated by \\n\\n):\n'.
               'PARAGRAPH 1: Overall authenticity assessment - lead with what percentage appear GENUINE, verification rates\n'.
               'PARAGRAPH 2: Evidence of authenticity - specific examples of genuine signals (personal stories, detailed experiences, balanced perspectives)\n'.
               'PARAGRAPH 3: Any concerns found - only if genuine manipulation patterns exist, not just high ratings\n'.
               'PARAGRAPH 4: Balanced summary - acknowledge both genuine indicators and any concerns\n\n'.
               'IMPORTANT: If most reviews show genuine characteristics, the fake_percentage should be LOW (under 30%).\n'.
               'Do NOT penalize products simply for having many positive reviews.\n\n'.
               'For product_insights: Describe the product based on genuine review experiences, focusing on real user feedback.';
    }

    /**
     * Clean text to ensure valid UTF-8 encoding for JSON serialization.
     */
    private static function cleanUtf8Text(string $text): string
    {
        // Remove or replace invalid UTF-8 sequences
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Remove null bytes and other problematic characters
        $text = str_replace(["\0", "\x1A"], '', $text);

        // Ensure string is valid UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        return trim($text);
    }

    /**
     * Generate provider-specific system message for chat-based models.
     *
     * These messages are designed to be BALANCED and ACCURATE, encouraging the AI
     * to recognize genuine reviews as the default case, not the exception.
     *
     * @param string $providerName Provider identifier (openai, deepseek, etc.)
     *
     * @return string Optimized system message for the provider
     */
    public static function getProviderSystemMessage(string $providerName): string
    {
        $baseMessage = 'You are an expert Amazon review authenticity analyst. '.
                      'Your goal is ACCURACY, not finding fakes. Most reviews are genuine. '.
                      'Score 0-100 where 0=definitely genuine, 100=definitely fake. '.
                      'Weight genuine signals strongly: verified purchases, detailed experiences, specific product knowledge, balanced perspectives (mentioning pros AND cons). '.
                      'High ratings for quality products are NORMAL. Default to genuine when uncertain. '.
                      'Return ONLY JSON: [{"id":"X","score":Y}]';

        // Provider-specific optimizations
        switch (strtolower($providerName)) {
            case 'openai':
                return $baseMessage;
            case 'deepseek':
                return 'You are an expert Amazon review authenticity analyst focused on ACCURACY. '.
                       'CRITICAL: Most reviews are genuine. High ratings for quality products are normal and expected. '.
                       'A detailed review with personal context, specific product knowledge, or balanced perspective is almost certainly genuine. '.
                       'Only flag reviews as fake (score 60+) when there are CLEAR manipulation patterns: '.
                       'generic praise without specifics, marketing language, or repetitive phrases across reviews. '.
                       'Score guidelines: 0-25=genuine, 26-45=likely genuine, 46-60=uncertain, 61-80=suspicious, 81-100=likely fake. '.
                       'Default to lower scores when uncertain. Return ONLY JSON: [{"id":"X","score":Y}]';
            case 'ollama':
            default:
                return $baseMessage;
        }
    }

    /**
     * Get provider-specific text length limits.
     */
    public static function getProviderTextLimit(string $providerName): int
    {
        switch (strtolower($providerName)) {
            case 'openai':
                return 400; // OpenAI can handle longer text efficiently
            case 'groq':
                return 400; // Groq handles context well
            case 'deepseek':
                return 300; // DeepSeek optimized for shorter text
            case 'ollama':
                return 300; // Ollama local models prefer shorter text
            default:
                return 300; // Conservative default
        }
    }

    /**
     * Validate that reviews array has the expected structure.
     */
    public static function validateReviewsStructure(array $reviews): bool
    {
        if (empty($reviews)) {
            return false;
        }

        foreach ($reviews as $review) {
            if (!isset($review['id'])) {
                return false;
            }

            if (!isset($review['review_text']) && !isset($review['text'])) {
                return false;
            }
        }

        return true;
    }
}
