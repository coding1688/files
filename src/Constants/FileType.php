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
        return array_column(self::cases(), 'value');
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
        ], self::cases());
    }
}
