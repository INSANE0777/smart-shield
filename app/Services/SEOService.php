<?php

namespace App\Services;

use App\Models\AsinData;
use Illuminate\Support\Str;

class SEOService
{
    /**
     * Generate comprehensive SEO data for product pages optimized for AI crawlers.
     */
    public function generateProductSEOData(AsinData $asinData): array
    {
        $baseData = $this->generateBaseSEOData($asinData);
        $aiOptimizedData = $this->generateAIOptimizedData($asinData);
        $structuredData = $this->generateStructuredData($asinData);

        return array_merge($baseData, $aiOptimizedData, $structuredData);
    }

    /**
     * Generate base SEO metadata.
     */
    private function generateBaseSEOData(AsinData $asinData): array
    {
        $title = $this->generateOptimizedTitle($asinData);
        $description = $this->generateOptimizedDescription($asinData);
        $keywords = $this->generateKeywords($asinData);

        return [
            'meta_title'         => $title,
            'meta_description'   => $description,
            'keywords'           => $keywords,
            'canonical_url'      => $this->generateCanonicalUrl($asinData),
            'social_title'       => $this->generateSocialTitle($asinData),
            'social_description' => $this->generateSocialDescription($asinData),
            'trust_score'        => $this->calculateTrustScore($asinData),
            'review_summary'     => $this->generateReviewSummary($asinData),
        ];
    }

    /**
     * Generate the canonical URL for a product page.
     * Uses the SEO-friendly /amazon/{country}/{asin}/{slug} format.
     */
    private function generateCanonicalUrl(AsinData $asinData): string
    {
        $country = $asinData->country ?? 'us';
        $asin = $asinData->asin;

        // Generate URL slug from product title for SEO-friendly URLs
        if ($asinData->product_title) {
            $slug = Str::slug(Str::limit($asinData->product_title, 50, ''));

            return "/amazon/{$country}/{$asin}/{$slug}";
        }

        // Fallback to non-slug URL if no product title
        return "/amazon/{$country}/{$asin}";
    }

    /**
     * Generate AI-optimized metadata for better AI search visibility.
     */
    private function generateAIOptimizedData(AsinData $asinData): array
    {
        return [
            'ai_summary'           => $this->generateAISummary($asinData),
            'ai_keywords'          => $this->generateAIKeywords($asinData),
            'question_answers'     => $this->generateQuestionAnswers($asinData),
            'analysis_methodology' => $this->getAnalysisMethodology(),
            'data_freshness'       => ($asinData->updated_at ?? now())->toISOString(),
            'confidence_score'     => $this->calculateConfidenceScore($asinData),
        ];
    }

    /**
     * Generate structured data schemas optimized for AI understanding.
     */
    private function generateStructuredData(AsinData $asinData): array
    {
        return [
            'product_schema'  => $this->generateProductSchema($asinData),
            'analysis_schema' => $this->generateAnalysisSchema($asinData),
            'dataset_schema'  => $this->generateDatasetSchema($asinData),
            'faq_schema'      => $this->generateFAQSchema($asinData),
            'how_to_schema'   => $this->generateHowToSchema($asinData),
        ];
    }

    /**
     * Generate optimized title for search engines and AI.
     */
    private function generateOptimizedTitle(AsinData $asinData): string
    {
        $productTitle = $asinData->product_title ?? 'Amazon Product';
        $shortTitle = Str::limit($productTitle, 30, '');
        $grade = $asinData->grade ?? 'N/A';
        $fakePercentage = $asinData->fake_percentage ?? 0;

        // Special handling for Grade U (No Reviews)
        if ($grade === 'U') {
            return "{$shortTitle} Review Analysis: No Reviews Found | Grade {$grade} | SMART SHIELD UI";
        }

        // AI-optimized title structure for better understanding
        if ($fakePercentage > 50) {
            return "{$shortTitle} Review Analysis: {$fakePercentage}% Fake Reviews Detected | Grade {$grade} | SMART SHIELD UI";
        } elseif ($fakePercentage > 20) {
            return "{$shortTitle} Authenticity Check: {$fakePercentage}% Suspicious Reviews | Grade {$grade} Analysis";
        } else {
            return "{$shortTitle} Review Verification: {$fakePercentage}% Fake | Grade {$grade} Trust Score";
        }
    }

    /**
     * Generate AI-optimized description using enhanced analysis content.
     */
    private function generateOptimizedDescription(AsinData $asinData): string
    {
        $productTitle = $asinData->product_title ?? 'this Amazon product';
        $grade = $asinData->grade ?? 'N/A';
        $fakePercentage = $asinData->fake_percentage ?? 0;
        $reviewCount = count($asinData->getReviewsArray());
        $adjustedRating = $asinData->adjusted_rating ?? 0;

        // Try to extract product insights from enhanced explanation
        $productInsights = $this->extractProductInsights($asinData);

        if ($grade === 'U') {
            $description = "AI analysis for {$productTitle} shows no reviews currently available on Amazon. ";
            $description .= "Authenticity status: Grade {$grade} (Unanalyzable). ";
        } else {
            $description = "AI-powered analysis of {$productTitle} reveals {$fakePercentage}% fake reviews out of {$reviewCount} analyzed. ";
            $description .= "Authenticity grade: {$grade}. Adjusted rating: {$adjustedRating}/5 stars after removing suspicious reviews. ";
        }

        // Add product insights if available for SEO enhancement
        if ($productInsights) {
            $description .= $productInsights.' ';
        }

        $description .= 'Comprehensive review authenticity analysis using machine learning and natural language processing. ';
        $description .= 'Get the real story behind Amazon product reviews with detailed fake review detection and trust scoring.';

        return $description;
    }

    /**
     * Generate AI-focused summary using enhanced analysis content.
     */
    private function generateAISummary(AsinData $asinData): string
    {
        // Use the enhanced explanation if available
        if (!empty($asinData->explanation)) {
            // Clean and format the explanation for AI consumption
            $enhancedSummary = $this->formatExplanationForAI($asinData->explanation);
            if (strlen($enhancedSummary) > 100) {
                return $enhancedSummary;
            }
        }

        if ($asinData->grade === 'U') {
            return "This product was analyzed but currently has no reviews on Amazon. Without review data, our AI cannot determine review authenticity patterns. This is typical for new product releases or items that haven't received customer feedback yet.";
        }

        // Fallback to basic summary
        $summary = 'This analysis examines the authenticity of Amazon product reviews using advanced AI techniques. ';
        $summary .= "Key findings: {$asinData->fake_percentage}% of reviews identified as potentially fake or manipulated. ";
        $summary .= "Overall authenticity grade: {$asinData->grade}. ";
        $summary .= 'The analysis considers review patterns, language authenticity, reviewer behavior, and statistical anomalies. ';
        $summary .= 'This data helps consumers make informed purchasing decisions based on genuine customer feedback.';

        return $summary;
    }

    /**
     * Generate keywords optimized for AI search.
     */
    private function generateKeywords(AsinData $asinData): string
    {
        $baseKeywords = [
            'amazon review analysis',
            'fake review detector',
            'product authenticity',
            'review verification',
            'ai review analysis',
            'amazon trust score',
            'fake review percentage',
            'product review grade',
            'review authenticity check',
            'amazon product analysis',
        ];

        // Add product-specific keywords
        if ($asinData->product_title) {
            $productWords = explode(' ', strtolower($asinData->product_title));
            $productKeywords = array_filter($productWords, function ($word) {
                return strlen($word) > 3 && !in_array($word, ['the', 'and', 'for', 'with']);
            });
            $baseKeywords = array_merge($baseKeywords, array_slice($productKeywords, 0, 5));
        }

        // Add country-specific keywords
        $countryKeywords = [
            "amazon {$asinData->country} reviews",
            "amazon {$asinData->country} analysis",
            "fake reviews {$asinData->country}",
        ];

        return implode(', ', array_merge($baseKeywords, $countryKeywords));
    }

    /**
     * Generate AI-specific keywords for better discoverability.
     */
    private function generateAIKeywords(AsinData $asinData): array
    {
        return [
            'artificial intelligence review analysis',
            'machine learning fake detection',
            'natural language processing reviews',
            'automated review verification',
            'ai-powered authenticity scoring',
            'algorithmic review assessment',
            'computational review analysis',
            'data-driven product evaluation',
            'statistical review anomaly detection',
            'intelligent review filtering',
        ];
    }

    /**
     * Generate question-answer pairs for AI understanding.
     */
    private function generateQuestionAnswers(AsinData $asinData): array
    {
        $grade = $asinData->grade ?? 'N/A';
        $isUnanalyzable = ($grade === 'U');
        $reviewCount = count($asinData->getReviewsArray());

        $questions = [
            [
                'question' => 'What percentage of reviews are fake for this product?',
                'answer'   => $isUnanalyzable
                    ? "Currently, we cannot determine the fake review percentage as no reviews were found on Amazon for this product."
                    : "{$asinData->fake_percentage}% of the analyzed reviews appear to be fake or inauthentic.",
            ],
            [
                'question' => 'What is the authenticity grade for this product?',
                'answer'   => $isUnanalyzable
                    ? "The product is graded as {$grade} (Unanalyzable) because there are no customer reviews available to analyze."
                    : "The product receives a grade of {$asinData->grade} based on review authenticity analysis.",
            ],
            [
                'question' => 'How many reviews were analyzed?',
                'answer'   => $isUnanalyzable
                    ? "No reviews were analyzed because this product currently has no customer feedback on Amazon."
                    : "{$reviewCount} reviews were analyzed for authenticity patterns.",
            ],
            [
                'question' => 'What is the adjusted rating after removing fake reviews?',
                'answer'   => $isUnanalyzable
                    ? "An adjusted rating is not available for products with zero reviews."
                    : "After removing suspicious reviews, the adjusted rating is {$asinData->adjusted_rating} out of 5 stars.",
            ],
            [
                'question' => 'How does this analysis help consumers?',
                'answer'   => 'This analysis helps consumers identify products with manipulated reviews, enabling more informed purchasing decisions based on genuine customer feedback.',
            ],
        ];

        // Add price-related Q&A if price analysis is available
        if ($asinData->hasPriceAnalysis()) {
            $priceAnalysis = $asinData->price_analysis;

            if (!empty($priceAnalysis['msrp_analysis']['amazon_price_assessment'])) {
                $questions[] = [
                    'question' => 'Is this product priced fairly on Amazon?',
                    'answer'   => "Based on AI price analysis, this product is {$priceAnalysis['msrp_analysis']['amazon_price_assessment']}.",
                ];
            }

            if (!empty($priceAnalysis['market_comparison']['price_positioning'])) {
                $questions[] = [
                    'question' => 'How is this product positioned in the market?',
                    'answer'   => "This product is positioned as a {$priceAnalysis['market_comparison']['price_positioning']} option in its category.",
                ];
            }

            if (!empty($priceAnalysis['summary'])) {
                $questions[] = [
                    'question' => 'What does the AI price analysis reveal?',
                    'answer'   => $priceAnalysis['summary'],
                ];
            }
        }

        return $questions;
    }

    /**
     * Generate analysis methodology description.
     */
    private function getAnalysisMethodology(): array
    {
        return [
            'techniques' => [
                'Natural Language Processing',
                'Machine Learning Classification',
                'Statistical Pattern Analysis',
                'Reviewer Behavior Analysis',
                'Temporal Pattern Detection',
                'Linguistic Authenticity Scoring',
            ],
            'data_sources' => [
                'Amazon product reviews',
                'Reviewer profiles and history',
                'Review timing patterns',
                'Language and sentiment analysis',
                'Cross-product review patterns',
            ],
            'accuracy'         => 'High confidence scoring based on multiple validation techniques',
            'update_frequency' => 'Analysis updated when new reviews are detected',
        ];
    }

    /**
     * Calculate trust score for the product.
     */
    private function calculateTrustScore(AsinData $asinData): int
    {
        $fakePercentage = $asinData->fake_percentage ?? 0;
        $grade = $asinData->grade ?? 'F';

        // Convert grade to numeric score
        $gradeScores = ['A' => 95, 'B' => 85, 'C' => 75, 'D' => 65, 'F' => 45, 'U' => 0];
        $gradeScore = $gradeScores[$grade] ?? 50;

        // Adjust based on fake percentage
        $trustScore = max(0, $gradeScore - ($fakePercentage * 0.5));

        return (int) round($trustScore);
    }

    /**
     * Calculate confidence score for the analysis.
     */
    private function calculateConfidenceScore(AsinData $asinData): int
    {
        $reviewCount = count($asinData->getReviewsArray());
        $hasProductData = $asinData->have_product_data;

        $baseConfidence = 70;

        // More reviews = higher confidence
        if ($reviewCount > 100) {
            $baseConfidence += 20;
        } elseif ($reviewCount > 50) {
            $baseConfidence += 15;
        } elseif ($reviewCount > 20) {
            $baseConfidence += 10;
        } elseif ($reviewCount > 10) {
            $baseConfidence += 5;
        }

        // Product data availability increases confidence
        if ($hasProductData) {
            $baseConfidence += 10;
        }

        return min(100, $baseConfidence);
    }

    /**
     * Generate product schema for structured data with enhanced content.
     */
    private function generateProductSchema(AsinData $asinData): array
    {
        $reviewCount = count($asinData->getReviewsArray());

        $schema = [
            '@context'           => 'https://schema.org',
            '@type'              => 'Product',
            'name'               => $asinData->product_title ?? 'Amazon Product',
            'description'        => $this->generateEnhancedProductDescription($asinData),
            'image'              => $asinData->product_image_url,
            'sku'                => $asinData->asin,
            'gtin'               => $asinData->asin,
            'brand'              => ['@type' => 'Brand', 'name' => 'Amazon'],
            'additionalProperty' => $this->generateProductProperties($asinData),
        ];

        // Only include aggregateRating if there are reviews (Google requires positive values)
        if ($reviewCount > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $asinData->adjusted_rating ?? 0,
                'bestRating'  => 5,
                'worstRating' => 1,
                'ratingCount' => $reviewCount,
                'reviewCount' => $reviewCount,
            ];
        }

        // Add price/offers if we have price data
        if (!empty($asinData->price)) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => $asinData->price,
                'priceCurrency' => $asinData->currency ?? 'USD',
                'availability'  => 'https://schema.org/InStock',
                'url'           => "https://www.amazon.{$this->getAmazonDomain($asinData->country)}/dp/{$asinData->asin}",
            ];
        }

        // Add enhanced analysis as a review for better AI understanding
        if (!empty($asinData->explanation)) {
            $schema['review'] = [
                '@type'        => 'Review',
                'reviewRating' => [
                    '@type'       => 'Rating',
                    'ratingValue' => $this->gradeToNumericRating($asinData->grade ?? 'N/A'),
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ],
                'author' => [
                    '@type' => 'Organization',
                    'name'  => 'SMART SHIELD UI - AI Review Analysis',
                ],
                'reviewBody'    => $this->formatExplanationForAI($asinData->explanation),
                'datePublished' => ($asinData->updated_at ?? now())->toISOString(),
                'headline'      => 'AI-Powered Fake Review Analysis - Grade '.($asinData->grade ?? 'N/A'),
            ];
        }

        return $schema;
    }

    /**
     * Generate analysis schema for AI understanding.
     */
    private function generateAnalysisSchema(AsinData $asinData): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'AnalysisNewsArticle',
            'headline'    => $this->generateOptimizedTitle($asinData),
            'description' => $this->generateOptimizedDescription($asinData),
            'author'      => [
                '@type' => 'Organization',
                'name'  => 'SMART SHIELD UI',
                'url'   => url('/'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => 'SMART SHIELD UI',
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => url('/img/nullfake.svg'),
                ],
            ],
            'datePublished' => ($asinData->updated_at ?? now())->toISOString(),
            'dateModified'  => ($asinData->updated_at ?? now())->toISOString(),
            'about'         => [
                '@type'      => 'Product',
                'name'       => $asinData->product_title ?? 'Amazon Product',
                'identifier' => $asinData->asin,
            ],
            'mentions' => [
                ['@type' => 'Thing', 'name' => 'Fake Reviews'],
                ['@type' => 'Thing', 'name' => 'Review Analysis'],
                ['@type' => 'Thing', 'name' => 'Amazon Product Reviews'],
                ['@type' => 'Thing', 'name' => 'Artificial Intelligence'],
                ['@type' => 'Thing', 'name' => 'Machine Learning'],
            ],
        ];
    }

    /**
     * Generate dataset schema for AI crawlers.
     */
    private function generateDatasetSchema(AsinData $asinData): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Dataset',
            'name'        => 'Amazon Product Review Analysis Data',
            'description' => 'Comprehensive fake review analysis dataset for '.($asinData->product_title ?? 'Amazon Product'),
            'creator'     => [
                '@type'       => 'Organization',
                'name'        => 'SMART SHIELD UI',
                'description' => 'AI-powered Amazon review authenticity analysis platform',
            ],
            'temporalCoverage' => ($asinData->updated_at ?? now())->toISOString(),
            'spatialCoverage'  => strtoupper($asinData->country ?? 'US'),
            'variableMeasured' => [
                [
                    '@type'       => 'PropertyValue',
                    'name'        => 'fake_review_percentage',
                    'value'       => $asinData->fake_percentage ?? 0,
                    'unitText'    => 'percent',
                    'description' => 'Percentage of reviews identified as potentially fake or inauthentic',
                ],
                [
                    '@type'       => 'PropertyValue',
                    'name'        => 'authenticity_grade',
                    'value'       => $asinData->grade ?? 'N/A',
                    'description' => 'Letter grade (A-F) representing overall review authenticity',
                ],
                [
                    '@type'       => 'PropertyValue',
                    'name'        => 'adjusted_rating',
                    'value'       => $asinData->adjusted_rating ?? 0,
                    'unitText'    => 'stars',
                    'description' => 'Product rating adjusted for fake review removal',
                ],
            ],
            'license'   => 'https://creativecommons.org/licenses/by/4.0/',
            'isBasedOn' => [
                '@type'      => 'Product',
                'name'       => $asinData->product_title ?? 'Amazon Product',
                'identifier' => $asinData->asin,
            ],
        ];
    }

    /**
     * Generate FAQ schema for common questions.
     */
    private function generateFAQSchema(AsinData $asinData): array
    {
        $qaData = $this->generateQuestionAnswers($asinData);

        $faqItems = array_map(function ($qa) {
            return [
                '@type'          => 'Question',
                'name'           => $qa['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $qa['answer'],
                ],
            ];
        }, $qaData);

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $faqItems,
        ];
    }

    /**
     * Generate HowTo schema for understanding the analysis process.
     */
    private function generateHowToSchema(AsinData $asinData): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            'name'        => 'How to Analyze Amazon Product Reviews for Authenticity',
            'description' => 'Step-by-step process for detecting fake reviews using AI analysis',
            'step'        => [
                [
                    '@type' => 'HowToStep',
                    'name'  => 'Data Collection',
                    'text'  => 'Collect product reviews and metadata from Amazon',
                ],
                [
                    '@type' => 'HowToStep',
                    'name'  => 'AI Analysis',
                    'text'  => 'Apply machine learning algorithms to detect patterns indicative of fake reviews',
                ],
                [
                    '@type' => 'HowToStep',
                    'name'  => 'Scoring',
                    'text'  => 'Calculate authenticity scores and assign letter grades based on analysis results',
                ],
                [
                    '@type' => 'HowToStep',
                    'name'  => 'Verification',
                    'text'  => 'Cross-validate results using multiple detection techniques for accuracy',
                ],
            ],
            'totalTime' => 'PT5M',
            'tool'      => [
                [
                    '@type' => 'HowToTool',
                    'name'  => 'Artificial Intelligence',
                ],
                [
                    '@type' => 'HowToTool',
                    'name'  => 'Natural Language Processing',
                ],
                [
                    '@type' => 'HowToTool',
                    'name'  => 'Machine Learning',
                ],
            ],
        ];
    }

    /**
     * Generate social media optimized title.
     */
    private function generateSocialTitle(AsinData $asinData): string
    {
        $productTitle = Str::limit($asinData->product_title ?? 'Amazon Product', 40, '');
        $fakePercentage = $asinData->fake_percentage ?? 0;
        $grade = $asinData->grade ?? 'N/A';

        return "Review Analysis: {$productTitle} - {$fakePercentage}% Fake Reviews | Grade {$grade}";
    }

    /**
     * Generate social media optimized description.
     */
    private function generateSocialDescription(AsinData $asinData): string
    {
        $grade = $asinData->grade ?? 'N/A';

        if ($grade === 'U') {
            return "Analysis complete: Currently no reviews available for analysis. We will continue to monitor this product for authentic customer feedback.";
        }

        $reviewCount = count($asinData->getReviewsArray());
        $fakePercentage = $asinData->fake_percentage ?? 0;

        return "AI analysis of {$reviewCount} reviews reveals {$fakePercentage}% are potentially fake. Get the real story behind Amazon product reviews with our comprehensive authenticity analysis.";
    }

    /**
     * Generate review summary for meta description using enhanced analysis.
     */
    private function generateReviewSummary(AsinData $asinData): string
    {
        $grade = $asinData->grade ?? 'N/A';

        if ($grade === 'U') {
            return "Currently no reviews are available on Amazon for analysis. Grade U authenticity status means we cannot determine review patterns yet.";
        }

        $reviewCount = count($asinData->getReviewsArray());
        $fakePercentage = $asinData->fake_percentage ?? 0;

        // Use the enhanced explanation if available, otherwise fallback to basic summary
        if (!empty($asinData->explanation)) {
            // Extract first sentence from enhanced explanation for meta description
            $sentences = explode('.', $asinData->explanation);
            $firstSentence = trim($sentences[0] ?? '');

            if (strlen($firstSentence) > 20 && strlen($firstSentence) < 150) {
                return $firstSentence.". Grade {$grade} authenticity analysis with AI-powered detection.";
            }
        }

        return "Analysis of {$reviewCount} reviews shows {$fakePercentage}% fake reviews. Authenticity grade: {$grade}. AI-powered detection helps identify genuine customer feedback.";
    }

    /**
     * Generate home page SEO data.
     */
    public function generateHomeSEOData(): array
    {
        return [
            'meta_title'       => 'SMART SHIELD UI - AI-Powered Amazon Review Analysis | Detect Fake Reviews Instantly',
            'meta_description' => 'Discover fake Amazon reviews with AI analysis across 14+ countries. Get authenticity grades, trust scores, and adjusted ratings. Analyze US, Canada, Germany, France, UK, Japan, and more.',
            'keywords'         => 'amazon review analysis, fake review detector, ai review analysis, amazon authenticity checker, product review trust, amazon international, review analyzer, ai fake review, e-commerce trust, product authenticity',
            'structured_data'  => $this->generateHomeStructuredData(),
            'ai_summary'       => 'SMART SHIELD UI is an AI-powered platform that analyzes Amazon product reviews for authenticity across multiple countries. Using advanced machine learning and natural language processing, it detects fake, AI-generated, or manipulated reviews to help consumers make informed purchasing decisions.',
            'question_answers' => [
                [
                    'question' => 'How does AI detect fake Amazon reviews?',
                    'answer'   => 'Our AI uses natural language processing, pattern recognition, and machine learning to analyze review text, reviewer behavior, timing patterns, and linguistic authenticity markers to identify potentially fake or manipulated reviews.',
                ],
                [
                    'question' => 'Which Amazon countries are supported?',
                    'answer'   => 'We support Amazon analysis for US, Canada, Germany, France, UK, Italy, Spain, Japan, Australia, Mexico, India, Singapore, Brazil, Netherlands, and more international Amazon domains.',
                ],
                [
                    'question' => 'What is a review authenticity grade?',
                    'answer'   => 'Our grading system (A-F) represents overall review authenticity. Grade A indicates highly authentic reviews, while Grade F suggests significant fake review presence. Grade U means unanalyzable due to insufficient data.',
                ],
            ],
        ];
    }

    /**
     * Generate home page structured data.
     */
    private function generateHomeStructuredData(): array
    {
        return [
            '@context'            => 'https://schema.org',
            '@type'               => 'WebApplication',
            'name'                => 'SMART SHIELD UI - Amazon Review Analysis',
            'description'         => 'AI-powered Amazon review analyzer that detects fake, AI-generated, or suspicious reviews across multiple countries',
            'url'                 => url('/'),
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem'     => 'Web Browser',
            'offers'              => [
                '@type'         => 'Offer',
                'price'         => '0',
                'priceCurrency' => 'USD',
            ],
            'featureList' => [
                'AI-powered fake review detection',
                'Multi-country Amazon support',
                'Authenticity grading system',
                'Trust score calculation',
                'Adjusted rating computation',
                'Real-time analysis',
            ],
            'creator' => [
                '@type' => 'Organization',
                'name'  => 'SMART SHIELD UI',
            ],
        ];
    }

    /**
     * Extract product insights from enhanced explanation for SEO.
     */
    private function extractProductInsights(AsinData $asinData): ?string
    {
        if (empty($asinData->explanation)) {
            return null;
        }

        // Look for "Product Analysis:" section in enhanced explanation
        if (strpos($asinData->explanation, 'Product Analysis:') !== false) {
            $parts = explode('Product Analysis:', $asinData->explanation);
            if (count($parts) > 1) {
                $insights = trim(explode("\n", $parts[1])[0]); // Get first line after "Product Analysis:"
                if (strlen($insights) > 20 && strlen($insights) < 200) {
                    return $insights;
                }
            }
        }

        return null;
    }

    /**
     * Format explanation content for AI consumption.
     */
    private function formatExplanationForAI(string $explanation): string
    {
        // Clean up the explanation for AI/meta tag use - convert paragraph breaks to spaces
        $formatted = str_replace(["\n\n", "\n"], [' ', ' '], $explanation);
        $formatted = preg_replace('/\s+/', ' ', $formatted); // Normalize whitespace
        $formatted = trim($formatted);

        // Limit length for meta descriptions (150-160 chars is optimal)
        if (strlen($formatted) > 300) {
            $sentences = explode('.', $formatted);
            $result = '';
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (strlen($result.$sentence.'. ') <= 280) {
                    $result .= $sentence.'. ';
                } else {
                    break;
                }
            }

            return trim($result);
        }

        return $formatted;
    }

    /**
     * Generate enhanced product description combining review analysis and product insights.
     */
    private function generateEnhancedProductDescription(AsinData $asinData): string
    {
        $baseDescription = $this->generateReviewSummary($asinData);
        $productInsights = $this->extractProductInsights($asinData);

        if ($productInsights) {
            return $baseDescription.' '.$productInsights;
        }

        return $baseDescription;
    }

    /**
     * Convert letter grade to numeric rating for structured data.
     */
    private function gradeToNumericRating(string $grade): int
    {
        return match ($grade) {
            'A'     => 5,
            'B'     => 4,
            'C'     => 3,
            'D'     => 2,
            'F'     => 1,
            'U'     => 0,
            default => 3
        };
    }

    /**
     * Generate product properties including price analysis data.
     */
    private function generateProductProperties(AsinData $asinData): array
    {
        $properties = $this->getBaseProductProperties($asinData);

        if ($asinData->hasPriceAnalysis()) {
            $properties = array_merge($properties, $this->getPriceAnalysisProperties($asinData));
        }

        return $properties;
    }

    /**
     * Get base product properties for structured data.
     */
    private function getBaseProductProperties(AsinData $asinData): array
    {
        return [
            ['@type' => 'PropertyValue', 'name' => 'Fake Review Percentage', 'value' => ($asinData->fake_percentage ?? 0).'%'],
            ['@type' => 'PropertyValue', 'name' => 'Authenticity Grade', 'value' => $asinData->grade ?? 'N/A'],
            ['@type' => 'PropertyValue', 'name' => 'Trust Score', 'value' => $this->calculateTrustScore($asinData).'/100'],
        ];
    }

    /**
     * Get price analysis properties for structured data.
     */
    private function getPriceAnalysisProperties(AsinData $asinData): array
    {
        $properties = [];
        $priceAnalysis = $asinData->price_analysis;

        if (!empty($priceAnalysis['msrp_analysis']['estimated_msrp'])) {
            $properties[] = ['@type' => 'PropertyValue', 'name' => 'Estimated MSRP', 'value' => $priceAnalysis['msrp_analysis']['estimated_msrp']];
        }

        if (!empty($priceAnalysis['msrp_analysis']['amazon_price_assessment'])) {
            $properties[] = ['@type' => 'PropertyValue', 'name' => 'Price Assessment', 'value' => $priceAnalysis['msrp_analysis']['amazon_price_assessment']];
        }

        if (!empty($priceAnalysis['market_comparison']['price_positioning'])) {
            $properties[] = ['@type' => 'PropertyValue', 'name' => 'Price Positioning', 'value' => $priceAnalysis['market_comparison']['price_positioning']];
        }

        return $properties;
    }

    /**
     * Get Amazon domain from country code.
     */
    private function getAmazonDomain(string $country): string
    {
        $domains = [
            'us' => 'com',
            'gb' => 'co.uk',
            'uk' => 'co.uk',
            'ca' => 'ca',
            'de' => 'de',
            'fr' => 'fr',
            'it' => 'it',
            'es' => 'es',
            'jp' => 'co.jp',
            'au' => 'com.au',
            'mx' => 'com.mx',
            'in' => 'in',
            'br' => 'com.br',
            'nl' => 'nl',
        ];

        return $domains[strtolower($country)] ?? 'com';
    }
}

