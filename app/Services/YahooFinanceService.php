<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YahooFinanceService
{
    private string $baseUrl = 'https://finance.yahoo.com/quote/';
    
    public function fetchStockData(string $symbol): ?array
    {
        try {
            $url = $this->baseUrl . $symbol . '/';
            
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip'
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            
            return $this->parseHtml($html, $symbol);
            
        } catch (\Exception $e) {
            Log::error("Error fetching stock data for {$symbol}: " . $e->getMessage());
            return null;
        }
    }

    private function parseHtml(string $html, string $symbol): array
    {
        libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($document);
        
        $data = [
            'symbol' => $symbol,
            'name' => $this->extractName($xpath),
            'current_price' => $this->extractPrice($xpath),
            'change' => $this->extractChange($xpath),
            'change_percent' => $this->extractChangePercent($xpath),
            'raw_data' => $this->extractAllFinStreamers($xpath)
        ];

        return $data;
    }

    private function extractName(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//h1[contains(@class, "yf-xxbei9")]');
        return $nodes->length > 0 ? trim($nodes[0]->textContent) : null;
    }

    private function extractPrice(DOMXPath $xpath): ?float
    {
        $nodes = $xpath->query('//fin-streamer[@data-symbol and @data-field="regularMarketPrice"]');
        if ($nodes->length > 0) {
            $value = trim($nodes[0]->textContent);
            return $this->parseNumber($value);
        }
        return null;
    }

    private function extractChange(DOMXPath $xpath): ?float
    {
        $nodes = $xpath->query('//fin-streamer[@data-field="regularMarketChange"]');
        if ($nodes->length > 0) {
            $value = trim($nodes[0]->textContent);
            return $this->parseNumber($value);
        }
        return null;
    }

    private function extractChangePercent(DOMXPath $xpath): ?float
    {
        $nodes = $xpath->query('//fin-streamer[@data-field="regularMarketChangePercent"]');
        if ($nodes->length > 0) {
            $value = trim($nodes[0]->textContent);
            return $this->parseNumber(str_replace(['%', '+'], '', $value));
        }
        return null;
    }

    private function extractAllFinStreamers(DOMXPath $xpath): array
    {
        $results = [];
        $nodes = $xpath->query('//fin-streamer');
        
        foreach ($nodes as $node) {
            $attrs = [];
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    $attrs[$attr->name] = $attr->value;
                }
            }
            
            $results[] = [
                'text' => trim($node->textContent),
                'attributes' => $attrs
            ];
        }
        
        return $results;
    }

    private function parseNumber(string $value): ?float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', $value);
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }
}