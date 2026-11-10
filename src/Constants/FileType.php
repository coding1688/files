<?php

namespace Coding1688\Files\Constants;

enum FileType: string
{
    case General = 'general';

    case ContentArticle = 'content_article';

    case ContentCover = 'content_cover';

    case Product = 'product';

    case ProductCover = 'product_cover';

    case BrandLogo = 'brand_logo';

    case Store = 'store';

    case UserAvatar = 'user_avatar';

    case System = 'system';

    case Carousel = 'carousel';

    /** @return list<string> */
    public static function values(): array
    {
        $all = array_column(self::cases(), 'value');
        $configured = config('files.types');

        if (! is_array($configured) || $configured === []) {
            return $all;
        }

        return array_values(array_intersect($all, array_map('strval', $configured)));
    }

    /** @return list<self> */
    public static function enabled(): array
    {
        $values = self::values();

        return array_values(array_filter(self::cases(), fn (self $case): bool => in_array($case->value, $values, true)));
    }

    public function label(): string
    {
        return __('common/file/const.'.$this->value);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::enabled());
    }
}
