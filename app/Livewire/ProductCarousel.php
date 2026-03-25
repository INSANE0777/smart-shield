<?php

namespace App\Livewire;

use App\Models\AsinData;
use Livewire\Component;

/**
 * Livewire component for displaying a carousel of recently processed products.
 */
class ProductCarousel extends Component
{
    public $products = [];
    public $isLoading = true;

    public function mount()
    {
        $this->loadProducts();
    }

    public function loadProducts()
    {
        $this->isLoading = true;

        // Get recently processed products that have both OpenAI analysis and product data
        $this->products = AsinData::where('have_product_data', true)
            ->whereNotNull('openai_result')
            ->whereNotNull('product_title')
            ->whereNotNull('product_image_url')
            ->orderBy('product_data_scraped_at', 'desc')
            ->take(8) // Show 8 products in carousel
            ->get()
            ->map(function ($product) {
                return [
                    'asin'            => $product->asin,
                    'title'           => $product->product_title,
                    'image_url'       => $product->product_image_url,
                    'fake_percentage' => $product->fake_percentage,
                    'grade'           => $product->grade,
                    'grade_color'     => $this->getGradeColor($product->grade),
                    'adjusted_rating' => $product->adjusted_rating,
                    'amazon_rating'   => $product->amazon_rating,
                    'seo_url'         => $product->seo_url,
                    'processed_at'    => $product->product_data_scraped_at,
                    'trust_score'     => $this->calculateTrustScore($product),
                ];
            })
            ->toArray();

        $this->isLoading = false;
    }

    private function getGradeColor($grade)
    {
        return match ($grade) {
            'A'     => 'text-green-600 bg-green-50 border-green-200',
            'B'     => 'text-blue-600 bg-blue-50 border-blue-200',
            'C'     => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'D'     => 'text-orange-600 bg-orange-50 border-orange-200',
            'F'     => 'text-red-600 bg-red-50 border-red-200',
            default => 'text-gray-600 bg-gray-50 border-gray-200',
        };
    }

    private function calculateTrustScore($product)
    {
        $fakePercentage = $product->fake_percentage ?? 0;
        $baseScore = 100 - $fakePercentage;

        // Get review count for adjustment
        $reviews = $product->getReviewsArray();
        $reviewCount = count($reviews);

        // Adjust based on review volume (more reviews = more reliable)
        if ($reviewCount >= 100) {
            $adjustment = 5;
        } elseif ($reviewCount >= 50) {
            $adjustment = 3;
        } elseif ($reviewCount >= 20) {
            $adjustment = 0;
        } else {
            $adjustment = -5; // Penalize low review counts
        }

        return max(0, min(100, $baseScore + $adjustment));
    }

    public function render()
    {
        return view('livewire.product-carousel');
    }
}
