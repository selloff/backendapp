<?php

namespace App\Modules\Selloff\Content\Support;

final class RssXmlRenderer
{
    /**
     * @param  list<array{title: string, link: string, guid: string, description: string, pubDate: string, creator: string, imageUrl: string|null, imageSize: string|null, imageMime: string}>  $items
     */
    public static function render(
        string $feedName,
        string $feedUrl,
        string $pageDescription,
        ?string $copyright,
        array $items,
    ): string {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<rss version="2.0"',
            'xmlns:dc="http://purl.org/dc/elements/1.1/"',
            'xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"',
            'xmlns:admin="http://webns.net/mvcb/"',
            'xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"',
            'xmlns:content="http://purl.org/rss/1.0/modules/content/">',
            '<channel>',
            '<title>'.self::escape($feedName).'</title>',
            '<link>'.self::escape($feedUrl).'</link>',
            '<description>'.self::escape($pageDescription).'</description>',
            '<dc:language>en</dc:language>',
        ];

        if ($copyright !== null && $copyright !== '') {
            $lines[] = '<dc:rights>'.self::escape($copyright).'</dc:rights>';
        }

        foreach ($items as $item) {
            $lines[] = '<item>';
            $lines[] = '<title>'.self::escape($item['title']).'</title>';
            $lines[] = '<link>'.self::escape($item['link']).'</link>';
            $lines[] = '<guid isPermaLink="true">'.self::escape($item['guid']).'</guid>';
            $lines[] = '<description><![CDATA['.$item['description'].']]></description>';

            if (! empty($item['imageUrl'])) {
                $length = $item['imageSize'] !== null ? ' length="'.self::escape($item['imageSize']).'"' : '';
                $lines[] = '<enclosure url="'.self::escape($item['imageUrl']).'"'.$length.' type="'.self::escape($item['imageMime']).'"/>';
            }

            $lines[] = '<pubDate>'.self::escape($item['pubDate']).'</pubDate>';
            $lines[] = '<dc:creator>'.self::escape($item['creator']).'</dc:creator>';
            $lines[] = '</item>';
        }

        $lines[] = '</channel>';
        $lines[] = '</rss>';

        return implode("\n", $lines);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
