<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;

class AdminProductExportService
{
    /** @var list<string> */
    private const HEADERS = [
        'ID',
        'Title',
        'SKU',
        'Type',
        'Listing Type',
        'Category',
        'Vendor',
        'Price',
        'Discounted Price',
        'Currency',
        'Stock',
        'Status',
        'Visibility',
        'Verified',
        'Promoted',
        'Promotion Plan',
        'Promotion Ends',
        'Pageviews',
        'Created At',
        'Updated At',
    ];

    /**
     * @param  callable(Builder): void  $applyFilters
     */
    public function export(string $format, callable $applyFilters): StreamedResponse
    {
        return match ($format) {
            'xml' => $this->xml($applyFilters),
            'excel', 'xlsx' => $this->excel($applyFilters),
            default => $this->csv($applyFilters),
        };
    }

    /**
     * @param  callable(Builder): void  $applyFilters
     */
    public function csv(callable $applyFilters): StreamedResponse
    {
        $filename = $this->filename('csv');

        return response()->streamDownload(function () use ($applyFilters): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::HEADERS);

            $this->eachProduct($applyFilters, function (Product $product) use ($handle): void {
                fputcsv($handle, $this->rowValues($product));
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  callable(Builder): void  $applyFilters
     */
    public function xml(callable $applyFilters): StreamedResponse
    {
        $filename = $this->filename('xml');

        return response()->streamDownload(function () use ($applyFilters): void {
            $writer = new XMLWriter;
            $writer->openURI('php://output');
            $writer->startDocument('1.0', 'UTF-8');
            $writer->startElement('root');

            $this->eachProduct($applyFilters, function (Product $product) use ($writer): void {
                $writer->startElement('item');
                foreach ($this->rowMap($product) as $key => $value) {
                    $writer->writeElement($key, $value);
                }
                $writer->endElement();
            });

            $writer->endElement();
            $writer->endDocument();
            $writer->flush();
        }, $filename, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @param  callable(Builder): void  $applyFilters
     */
    public function excel(callable $applyFilters): StreamedResponse
    {
        $filename = $this->filename('xlsx');

        return response()->streamDownload(function () use ($applyFilters): void {
            echo '<?xml version="1.0"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Worksheet ss:Name="Products"><Table>';

            echo '<Row>';
            foreach (self::HEADERS as $header) {
                echo '<Cell><Data ss:Type="String">'.$this->escapeSpreadsheet($header).'</Data></Cell>';
            }
            echo '</Row>';

            $this->eachProduct($applyFilters, function (Product $product): void {
                echo '<Row>';
                foreach ($this->rowValues($product) as $value) {
                    $type = is_numeric($value) && $value !== '' ? 'Number' : 'String';
                    echo '<Cell><Data ss:Type="'.$type.'">'.$this->escapeSpreadsheet((string) $value).'</Data></Cell>';
                }
                echo '</Row>';
            });

            echo '</Table></Worksheet></Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  callable(Builder): void  $applyFilters
     * @param  callable(Product): void  $callback
     */
    private function eachProduct(callable $applyFilters, callable $callback): void
    {
        $query = Product::query()
            ->with(['translations', 'vendor.vendorProfile', 'category.translations'])
            ->orderByDesc('id');

        $applyFilters($query);

        $query->chunkById(200, function ($products) use ($callback): void {
            foreach ($products as $product) {
                /** @var Product $product */
                $callback($product);
            }
        });
    }

    /**
     * @return list<string|int|null>
     */
    private function rowValues(Product $product): array
    {
        return array_values($this->rowMap($product));
    }

    /**
     * @return array<string, string|int|null>
     */
    private function rowMap(Product $product): array
    {
        $translation = $product->translations->firstWhere('locale', 'en')
            ?? $product->translations->first();

        return [
            'id' => $product->id,
            'title' => $translation?->title,
            'sku' => $product->sku,
            'type' => $product->type,
            'listing_type' => $product->listing_type,
            'category' => $product->category?->translations->firstWhere('locale', 'en')?->name
                ?? $product->category?->translations->first()?->name,
            'vendor' => $product->vendor?->vendorProfile?->shop_name ?? $product->vendor?->name,
            'price' => $product->price,
            'price_discounted' => $product->price_discounted,
            'currency' => $product->currency_code,
            'stock' => $product->type === 'digital' ? 'in_stock' : $product->stock,
            'status' => $product->status,
            'visibility' => $product->visibility,
            'verified' => $product->is_verified ? 'yes' : 'no',
            'promoted' => $product->is_promoted ? 'yes' : 'no',
            'special_offer' => $product->is_special_offer ? 'yes' : 'no',
            'promotion_plan' => $product->promote_plan,
            'promotion_ends' => $product->promoted_until?->toIso8601String(),
            'pageviews' => $product->pageviews,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    private function filename(string $extension): string
    {
        return 'products-'.now()->format('Y-m-d-His').'.'.$extension;
    }

    private function escapeSpreadsheet(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
