<?php

namespace App\Enums;

/**
 * What a gallery photo shows. The order of the cases is the recommended gallery
 * order (front → angle → side → on model → top → case). Mirrored by the
 * `ImageViewType` union in resources/js/types/product.ts.
 */
enum ImageViewType: string
{
    case Front = 'front';
    case Angle = 'angle';
    case Side = 'side';
    case ModelFemale = 'model_female';
    case ModelMale = 'model_male';
    case Top = 'top';
    case Case = 'case';
    case Other = 'other';

    public function label(): string
    {
        return __('gallery.view_types.'.$this->value);
    }

    /** @return array<string, string> value => label, for admin selects. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
