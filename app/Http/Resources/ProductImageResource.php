<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Gallery photo as consumed by the React product page. The frontend never builds
 * image URLs itself — everything it needs (srcset sources, thumbnail, size) is here.
 *
 * @mixin ProductImage
 */
class ProductImageResource extends JsonResource
{
    /** `sizes` for the gallery's srcset: full width on mobile, the gallery column on desktop. */
    public const SIZES = '(min-width: 1024px) 720px, 100vw';

    public function toArray(Request $request): array
    {
        $sources = $this->sources();

        return [
            'id' => $this->id,
            'alt' => $this->alt,
            'viewType' => $this->view_type->value,
            'width' => $this->width,
            'height' => $this->height,
            'isPrimary' => $this->is_primary,
            // Keys are widths; JSON objects keep them as strings ("480": url).
            'sources' => [
                'webp' => (object) $sources['webp'],
                'jpg' => (object) $sources['jpg'],
            ],
            'thumb' => $this->thumbUrl(),
        ];
    }
}
