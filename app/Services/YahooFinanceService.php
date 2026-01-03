<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YahooFinanceService
{
    private string $baseUrl = 'https://finance.yahoo.com/quote/';
    private ?string $lastError = null;
    
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
    
    public function fetchStockData(string $symbol): ?array
    {
        $this->lastError = null;
        
        try {
            $url = $this->baseUrl . $symbol . '/';
            
            // Usa lo stesso metodo dello script funzionante: file_get_contents con stream_context
            $options = [
                "http" => [
                    "method" => "GET",
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.88 Safari/537.36\r\n" .
                                "Accept-Language: en-US,en;q=0.5\r\n" .
                                "Accept-Encoding: gzip\r\n",
                    "timeout" => 30,
                    "ignore_errors" => true
                ],
                "ssl" => [
                    "verify_peer" => env('APP_ENV') === 'production',
                    "verify_peer_name" => env('APP_ENV') === 'production',
                ]
            ];
            
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                $this->lastError = "Impossibile scaricare la pagina da Yahoo Finance";
                Log::error("Yahoo Finance download failed for {$symbol}");
                return null;
            }
            
            // Decomprimi se è gzip (come nello script funzionante)
            $html = "";
            if (substr($response, 0, 2) === "\x1f\x8b") {
                $html = gzdecode($response);
            } else {
                $html = $response;
            }
            
            if (empty($html)) {
                $this->lastError = "Risposta vuota da Yahoo Finance";
                Log::error("Yahoo Finance empty response for {$symbol}");
                return null;
            }
            
            $parsed = $this->parseHtml($html, $symbol);
            
            // Verifica se abbiamo almeno alcuni dati
            if ($parsed['current_price'] === null && $parsed['name'] === null) {
                $this->lastError = "Impossibile estrarre dati dalla pagina. Yahoo Finance potrebbe aver cambiato la struttura HTML.";
                Log::warning("Yahoo Finance parsing failed for {$symbol} - no data extracted");
            }
            
            return $parsed;
            
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            Log::error("Error fetching stock data for {$symbol}: " . $e->getMessage());
            return null;
        }
    }

    private function parseHtml(string $html, string $symbol): array
    {
        // Usa lo stesso approccio dello script funzionante: carica HTML con DOMDocument
        libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($document);
        
        // Estrai tutti i fin-streamer e cerca i dati nei loro attributi
        $data = [
            'symbol' => $symbol,
            'name' => null,
            'current_price' => null,
            'change' => null,
            'change_percent' => null,
            'raw_data' => []
        ];
        
        // Metodo 1: Cerca per data-testid (più affidabile per GOOG e altre azioni principali)
        $mainPriceNode = $xpath->query('//span[@data-testid="qsp-price"]');
        if ($mainPriceNode->length > 0) {
            $priceText = trim($mainPriceNode[0]->textContent);
            $price = $this->parseNumber($priceText);
            if ($price !== null && $price > 0.01 && $price < 100000) {
                $data['current_price'] = $price;
            }
        }
        
        // Metodo 2: Cerca fin-streamer con data-symbol corrispondente (fallback)
        if (empty($data['current_price'])) {
            $mainPriceNode = $xpath->query('//fin-streamer[@data-symbol="' . $symbol . '" and @data-field="regularMarketPrice"]');
            if ($mainPriceNode->length > 0) {
                $attrs = $this->getNodeAttributes($mainPriceNode[0]);
                if (!empty($attrs['data-value'])) {
                    $price = (float)$attrs['data-value'];
                    if ($price > 0.01 && $price < 100000) {
                        $data['current_price'] = $price;
                    }
                }
            }
        }
        
        // Estrai change e changePercent dal div che contiene tutto il testo (metodo più affidabile)
        $priceContainer = $xpath->query('//div[contains(@class, "price") or contains(@class, "container") or contains(@class, "bottom")]');
        foreach ($priceContainer as $container) {
            $containerText = trim($container->textContent);
            // Pattern: "315.32  +1.52  +(0.48%)   At close: January 2 at 4:00:01 PM EST"
            // Pattern più flessibile per catturare: prezzo, variazione, percentuale
            if (preg_match('/(\d+\.\d+)\s+([\+\-]?\d+\.\d+)\s+\(?([\+\-]?\(?\d+\.\d+%?\)?)\)?\s+At close:/i', $containerText, $matches)) {
                if (empty($data['current_price'])) {
                    $data['current_price'] = (float)$matches[1];
                }
                if (empty($data['change'])) {
                    $data['change'] = (float)$matches[2];
                }
                if (empty($data['change_percent'])) {
                    $percentStr = str_replace(['(', ')', '%', '+'], '', $matches[3]);
                    $data['change_percent'] = (float)$percentStr;
                }
                break;
            }
            // Pattern alternativo senza "At close": "315.32  +1.52  +(0.48%)"
            if (preg_match('/^(\d+\.\d+)\s+([\+\-]?\d+\.\d+)\s+\(?([\+\-]?\(?\d+\.\d+%?\)?)\)?$/i', $containerText, $matches)) {
                if (empty($data['current_price'])) {
                    $data['current_price'] = (float)$matches[1];
                }
                if (empty($data['change'])) {
                    $data['change'] = (float)$matches[2];
                }
                if (empty($data['change_percent'])) {
                    $percentStr = str_replace(['(', ')', '%', '+'], '', $matches[3]);
                    $data['change_percent'] = (float)$percentStr;
                }
            }
        }
        
        // Cerca anche change e changePercent con data-symbol corrispondente (fallback)
        if (empty($data['change'])) {
            $mainChangeNode = $xpath->query('//fin-streamer[@data-symbol="' . $symbol . '" and @data-field="regularMarketChange"]');
            if ($mainChangeNode->length > 0) {
                $attrs = $this->getNodeAttributes($mainChangeNode[0]);
                if (!empty($attrs['data-value'])) {
                    $data['change'] = (float)$attrs['data-value'];
                }
            }
        }
        
        if (empty($data['change_percent'])) {
            $mainChangePercentNode = $xpath->query('//fin-streamer[@data-symbol="' . $symbol . '" and @data-field="regularMarketChangePercent"]');
            if ($mainChangePercentNode->length > 0) {
                $attrs = $this->getNodeAttributes($mainChangePercentNode[0]);
                if (!empty($attrs['data-value'])) {
                    $data['change_percent'] = (float)$attrs['data-value'];
                }
            }
        }
        
        // Estrai dati after hours usando data-testid
        $afterHoursPriceNode = $xpath->query('//span[@data-testid="qsp-post-price"]');
        if ($afterHoursPriceNode->length > 0) {
            $priceText = trim($afterHoursPriceNode[0]->textContent);
            $price = $this->parseNumber($priceText);
            if ($price !== null && $price > 0.01 && $price < 100000) {
                $afterHoursData['price'] = $price;
            }
        }
        
        // Estrai dati after hours dal div che contiene tutto il testo
        $priceContainer = $xpath->query('//div[contains(@class, "price") or contains(@class, "container")]');
        foreach ($priceContainer as $container) {
            $containerText = trim($container->textContent);
            // Pattern: "315.20  -0.12  (-0.04%)   After hours: 7:59:59 PM EST"
            if (preg_match('/(\d+\.\d+)\s+([\+\-]?\d+\.\d+)\s+\(?([\+\-]?\(?\d+\.\d+%?\)?)\)?\s+After hours:/i', $containerText, $matches)) {
                if (empty($afterHoursData['price'])) {
                    $afterHoursData['price'] = (float)$matches[1];
                }
                if (empty($afterHoursData['change'])) {
                    $afterHoursData['change'] = (float)$matches[2];
                }
                if (empty($afterHoursData['change_percent'])) {
                    $percentStr = str_replace(['(', ')', '%', '+'], '', $matches[3]);
                    $afterHoursData['change_percent'] = (float)$percentStr;
                }
                break;
            }
        }
        
        // Poi estrai tutti gli altri fin-streamer per after hours (fallback)
        $finStreamers = $xpath->query('//fin-streamer');
        $foundRegularMarket = !empty($data['current_price']);
        
        foreach ($finStreamers as $node) {
            $attrs = $this->getNodeAttributes($node);
            $text = trim($node->textContent);
            
            // Cerca nei data-field per identificare i valori
            if (isset($attrs['data-field'])) {
                $field = $attrs['data-field'];
                $dataValue = $attrs['data-value'] ?? null;
                $dataSymbol = $attrs['data-symbol'] ?? null;
                
                // Preferisci i dati con data-symbol corrispondente al simbolo cercato
                $isRelevant = ($dataSymbol === $symbol || empty($dataSymbol));
                
                // Estrai SOLO i dati di chiusura (regularMarket) - ignora postMarket per il prezzo principale
                switch ($field) {
                    case 'regularMarketPrice':
                        // Prendi solo se ha data-value e se non l'abbiamo già preso
                        if (!$foundRegularMarket && !empty($dataValue) && $isRelevant) {
                            $price = (float)$dataValue;
                            // Verifica che sia un prezzo ragionevole (tra 0.01 e 100000)
                            if ($price > 0.01 && $price < 100000) {
                                $data['current_price'] = $price;
                                $foundRegularMarket = true;
                            }
                        }
                        break;
                    case 'regularMarketChange':
                        // Prendi solo se non l'abbiamo già e se ha data-symbol corrispondente
                        if (empty($data['change']) && !empty($dataValue) && ($dataSymbol === $symbol || empty($dataSymbol))) {
                            $data['change'] = (float)$dataValue;
                        } elseif (empty($data['change']) && !empty($text)) {
                            $parsed = $this->parseNumber($text);
                            if ($parsed !== null) {
                                $data['change'] = $parsed;
                            }
                        }
                        break;
                    case 'regularMarketChangePercent':
                        // Prendi solo se non l'abbiamo già e se ha data-symbol corrispondente
                        if (empty($data['change_percent']) && !empty($dataValue) && ($dataSymbol === $symbol || empty($dataSymbol))) {
                            $data['change_percent'] = (float)$dataValue;
                        } elseif (empty($data['change_percent']) && !empty($text)) {
                            $cleaned = str_replace(['%', '+', '(', ')'], '', $text);
                            $parsed = $this->parseNumber($cleaned);
                            if ($parsed !== null) {
                                $data['change_percent'] = $parsed;
                            }
                        }
                        break;
                    case 'postMarketPrice':
                        if (!empty($dataValue)) {
                            $price = (float)$dataValue;
                            if ($price > 0.01 && $price < 100000) {
                                $afterHoursData['price'] = $price;
                            }
                        } elseif (!empty($text)) {
                            $parsed = $this->parseNumber($text);
                            if ($parsed !== null && $parsed > 0.01 && $parsed < 100000) {
                                $afterHoursData['price'] = $parsed;
                            }
                        }
                        break;
                    case 'postMarketChange':
                        if (!empty($dataValue)) {
                            $afterHoursData['change'] = (float)$dataValue;
                        } elseif (!empty($text)) {
                            $parsed = $this->parseNumber($text);
                            if ($parsed !== null) {
                                $afterHoursData['change'] = $parsed;
                            }
                        }
                        break;
                    case 'postMarketChangePercent':
                        if (!empty($dataValue)) {
                            $afterHoursData['change_percent'] = (float)$dataValue;
                        } elseif (!empty($text)) {
                            $cleaned = str_replace(['%', '+', '(', ')'], '', $text);
                            $parsed = $this->parseNumber($cleaned);
                            if ($parsed !== null) {
                                $afterHoursData['change_percent'] = $parsed;
                            }
                        }
                        break;
                }
            }
        }
        
        // Salva i dati after hours se presenti
        if (!empty($afterHoursData)) {
            $data['after_hours'] = $afterHoursData;
        }
        
        // Se non abbiamo trovato il prezzo regularMarket, prova a cercare nel JSON
        if (!$foundRegularMarket || $data['current_price'] === null) {
            // Cerca anche nei fin-streamer senza data-field ma con data-symbol che corrisponde
            $priceNodes = $xpath->query('//fin-streamer[@data-symbol="' . $symbol . '" and @data-field="regularMarketPrice"]');
            if ($priceNodes->length > 0) {
                $priceNode = $priceNodes[0];
                $priceAttrs = $this->getNodeAttributes($priceNode);
                if (!empty($priceAttrs['data-value'])) {
                    $price = (float)$priceAttrs['data-value'];
                    if ($price > 0.01 && $price < 100000) {
                        $data['current_price'] = $price;
                        $foundRegularMarket = true;
                    }
                }
            }
        }
        
        // Estrai il nome dall'h1 o dai meta tag
        $data['name'] = $this->extractName($xpath);
        
        // Estrai data/ora di chiusura e after hours dai testi della pagina
        $this->extractMarketTimes($xpath, $data);
        
        // Se non abbiamo trovato dati nei fin-streamer, prova con root.App.main
        if ($data['current_price'] === null) {
            $jsonData = $this->extractJsonData($html);
            if ($jsonData && !empty($jsonData['current_price'])) {
                $data['current_price'] = $jsonData['current_price'];
                $data['change'] = $jsonData['change'] ?? $data['change'];
                $data['change_percent'] = $jsonData['change_percent'] ?? $data['change_percent'];
                if (empty($data['name']) && !empty($jsonData['name'])) {
                    $data['name'] = $jsonData['name'];
                }
                // Estrai anche dati after hours dal JSON se presenti
                if (!empty($jsonData['after_hours'])) {
                    $data['after_hours'] = array_merge($data['after_hours'] ?? [], $jsonData['after_hours']);
                }
            }
        }
        
        // Estrai anche dati after hours dal JSON se non li abbiamo ancora
        if (empty($data['after_hours'])) {
            $jsonData = $this->extractJsonData($html);
            if (!empty($jsonData['after_hours'])) {
                $data['after_hours'] = $jsonData['after_hours'];
            }
        }
        
        // Aggiungi timestamp di quando sono stati recuperati i dati
        $data['fetched_at'] = now()->toIso8601String();

        return $data;
    }
    
    private function getNodeAttributes($node): array
    {
        $attrs = [];
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $attrs[$attr->name] = $attr->value;
            }
        }
        return $attrs;
    }
    
    private function extractJsonData(string $html): ?array
    {
        $data = [];
        
        // Cerca root.App.main nei tag script (metodo più affidabile)
        // Yahoo Finance usa spesso questa struttura: root.App.main = {...}
        if (preg_match('/root\.App\.main\s*=\s*({.+?});/s', $html, $matches)) {
            $jsonStr = $matches[1];
            try {
                $jsonData = json_decode($jsonStr, true);
                if ($jsonData && is_array($jsonData)) {
                    // Naviga nella struttura JSON di Yahoo Finance
                    $quoteData = $this->extractFromYahooJson($jsonData);
                    if ($quoteData && !empty($quoteData['current_price'])) {
                        return $quoteData;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to parse root.App.main JSON: " . $e->getMessage());
            }
        }
        
        // Cerca anche in altri pattern JSON comuni
        // Pattern: window.__PRELOADED_STATE__ o simili
        if (preg_match('/"QuoteSummaryStore":\s*({.+?})/s', $html, $matches)) {
            try {
                $jsonStr = '{' . $matches[1] . '}';
                $jsonData = json_decode($jsonStr, true);
                if ($jsonData) {
                    $quoteData = $this->extractFromYahooJson($jsonData);
                    if ($quoteData && !empty($quoteData['current_price'])) {
                        return $quoteData;
                    }
                }
            } catch (\Exception $e) {
                // Ignora errori di parsing
            }
        }
        
        // Metodo alternativo: regex per estrarre valori raw direttamente dall'HTML
        // Estrai prezzo con regex più flessibile - cerca pattern come "regularMarketPrice":{"raw":123.45}
        if (preg_match('/"regularMarketPrice"\s*:\s*\{[^}]*"raw"\s*:\s*([0-9.]+)/', $html, $matches)) {
            $data['current_price'] = (float)$matches[1];
        } elseif (preg_match('/"regularMarketPrice"\s*:\s*([0-9.]+)/', $html, $matches)) {
            $data['current_price'] = (float)$matches[1];
        }
        
        // Estrai variazione
        if (preg_match('/"regularMarketChange"\s*:\s*\{[^}]*"raw"\s*:\s*([0-9.\-]+)/', $html, $matches)) {
            $data['change'] = (float)$matches[1];
        } elseif (preg_match('/"regularMarketChange"\s*:\s*([0-9.\-]+)/', $html, $matches)) {
            $data['change'] = (float)$matches[1];
        }
        
        // Estrai variazione percentuale
        if (preg_match('/"regularMarketChangePercent"\s*:\s*\{[^}]*"raw"\s*:\s*([0-9.\-]+)/', $html, $matches)) {
            $data['change_percent'] = (float)$matches[1];
        } elseif (preg_match('/"regularMarketChangePercent"\s*:\s*([0-9.\-]+)/', $html, $matches)) {
            $data['change_percent'] = (float)$matches[1];
        }
        
        // Estrai nome - solo se non abbiamo già un nome valido
        if (empty($data['name'])) {
            if (preg_match('/"longName"\s*:\s*"([^"]{3,})"/', $html, $matches)) {
                $name = $matches[1];
                // Filtra nomi che sembrano non essere nomi di aziende
                if (!preg_match('/^(guce|click|here|more|cookie|privacy)$/i', $name)) {
                    $data['name'] = $name;
                }
            } elseif (preg_match('/"shortName"\s*:\s*"([^"]{3,})"/', $html, $matches)) {
                $name = $matches[1];
                if (!preg_match('/^(guce|click|here|more|cookie|privacy)$/i', $name)) {
                    $data['name'] = $name;
                }
            }
        }
        
        // Prova con attributi data-value nei fin-streamer (metodo più affidabile per HTML moderno)
        if (empty($data['current_price'])) {
            // Cerca tutti i fin-streamer con data-field="regularMarketPrice"
            if (preg_match_all('/<fin-streamer[^>]*data-field="regularMarketPrice"[^>]*>/i', $html, $priceMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($priceMatches[0] as $match) {
                    $pos = $match[1];
                    $tag = substr($html, $pos, 200); // Prendi 200 caratteri dopo il tag
                    if (preg_match('/data-value="([0-9.]+)"/', $tag, $valueMatch)) {
                        $data['current_price'] = (float)$valueMatch[1];
                        break;
                    }
                }
            }
        }
        
        if (empty($data['change'])) {
            if (preg_match_all('/<fin-streamer[^>]*data-field="regularMarketChange"[^>]*>/i', $html, $changeMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($changeMatches[0] as $match) {
                    $pos = $match[1];
                    $tag = substr($html, $pos, 200);
                    if (preg_match('/data-value="([0-9.\-]+)"/', $tag, $valueMatch)) {
                        $data['change'] = (float)$valueMatch[1];
                        break;
                    }
                }
            }
        }
        
        if (empty($data['change_percent'])) {
            if (preg_match_all('/<fin-streamer[^>]*data-field="regularMarketChangePercent"[^>]*>/i', $html, $percentMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($percentMatches[0] as $match) {
                    $pos = $match[1];
                    $tag = substr($html, $pos, 200);
                    if (preg_match('/data-value="([0-9.\-]+)"/', $tag, $valueMatch)) {
                        $data['change_percent'] = (float)$valueMatch[1];
                        break;
                    }
                }
            }
        }
        
        // Restituisci solo se abbiamo almeno il prezzo
        return !empty($data['current_price']) ? $data : null;
    }
    
    private function extractFromYahooJson(array $jsonData): ?array
    {
        $data = [];
        
        // Prova diversi percorsi nella struttura JSON di Yahoo Finance
        $paths = [
            // Percorso principale
            ['context', 'dispatcher', 'stores', 'QuoteSummaryStore', 'quoteSummary', 'result', 0],
            // Percorso alternativo
            ['QuoteSummaryStore', 'quoteSummary', 'result', 0],
            // Percorso semplificato
            ['quoteSummary', 'result', 0],
        ];
        
        $quote = null;
        foreach ($paths as $path) {
            $current = $jsonData;
            $found = true;
            foreach ($path as $key) {
                if (isset($current[$key])) {
                    $current = $current[$key];
                } else {
                    $found = false;
                    break;
                }
            }
            if ($found && is_array($current)) {
                $quote = $current;
                break;
            }
        }
        
        if ($quote) {
            $priceInfo = $quote['price'] ?? null;
            
            if ($priceInfo && is_array($priceInfo)) {
                // Estrai nome
                if (isset($priceInfo['longName']) && !empty($priceInfo['longName'])) {
                    $data['name'] = $priceInfo['longName'];
                } elseif (isset($priceInfo['shortName']) && !empty($priceInfo['shortName'])) {
                    $data['name'] = $priceInfo['shortName'];
                }
                
                // Estrai prezzo
                if (isset($priceInfo['regularMarketPrice'])) {
                    if (is_array($priceInfo['regularMarketPrice']) && isset($priceInfo['regularMarketPrice']['raw'])) {
                        $data['current_price'] = (float)$priceInfo['regularMarketPrice']['raw'];
                    } elseif (is_numeric($priceInfo['regularMarketPrice'])) {
                        $data['current_price'] = (float)$priceInfo['regularMarketPrice'];
                    }
                }
                
                // Estrai variazione
                if (isset($priceInfo['regularMarketChange'])) {
                    if (is_array($priceInfo['regularMarketChange']) && isset($priceInfo['regularMarketChange']['raw'])) {
                        $data['change'] = (float)$priceInfo['regularMarketChange']['raw'];
                    } elseif (is_numeric($priceInfo['regularMarketChange'])) {
                        $data['change'] = (float)$priceInfo['regularMarketChange'];
                    }
                }
                
                // Estrai variazione percentuale
                if (isset($priceInfo['regularMarketChangePercent'])) {
                    if (is_array($priceInfo['regularMarketChangePercent']) && isset($priceInfo['regularMarketChangePercent']['raw'])) {
                        $data['change_percent'] = (float)$priceInfo['regularMarketChangePercent']['raw'];
                    } elseif (is_numeric($priceInfo['regularMarketChangePercent'])) {
                        $data['change_percent'] = (float)$priceInfo['regularMarketChangePercent'];
                    }
                }
                
                // Estrai dati after hours se presenti
                if (isset($priceInfo['postMarketPrice'])) {
                    $afterHours = [];
                    if (is_array($priceInfo['postMarketPrice']) && isset($priceInfo['postMarketPrice']['raw'])) {
                        $afterHours['price'] = (float)$priceInfo['postMarketPrice']['raw'];
                    } elseif (is_numeric($priceInfo['postMarketPrice'])) {
                        $afterHours['price'] = (float)$priceInfo['postMarketPrice'];
                    }
                    
                    if (isset($priceInfo['postMarketChange']['raw'])) {
                        $afterHours['change'] = (float)$priceInfo['postMarketChange']['raw'];
                    }
                    
                    if (isset($priceInfo['postMarketChangePercent']['raw'])) {
                        $afterHours['change_percent'] = (float)$priceInfo['postMarketChangePercent']['raw'];
                    }
                    
                    if (!empty($afterHours)) {
                        $data['after_hours'] = $afterHours;
                    }
                }
            }
        }
        
        // Se abbiamo almeno il prezzo, restituisci i dati
        return !empty($data['current_price']) ? $data : null;
    }

    private function extractName(DOMXPath $xpath): ?string
    {
        // Cerca nei tag h1 (come nello script funzionante)
        $selectors = [
            '//h1[@data-testid="quote-header-name"]',
            '//h1[contains(@class, "quote-header")]',
            '//h1[contains(@class, "yf-")]',
            '//h1',
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = trim($nodes[0]->textContent);
                // Filtra testi troppo corti o che sembrano non essere nomi di aziende
                if (!empty($text) && strlen($text) > 3) {
                    // Filtra parole comuni che non sono nomi
                    if (preg_match('/^(guce|click|here|more|cookie|privacy|accept|reject)$/i', $text)) {
                        continue;
                    }
                    // Rimuovi il simbolo se presente (es. "Apple Inc. (AAPL)" -> "Apple Inc.")
                    $text = preg_replace('/\s*\([A-Z]+\)\s*$/', '', $text);
                    if (strlen($text) > 3) {
                        return $text;
                    }
                }
            }
        }
        
        // Cerca nei meta tag
        $metaSelectors = [
            '//meta[@property="og:title"]/@content',
            '//meta[@name="title"]/@content',
        ];
        
        foreach ($metaSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = trim($nodes[0]->value);
                if (!empty($text) && strlen($text) > 3) {
                    $text = preg_replace('/\s*\([A-Z]+\)\s*$/', '', $text);
                    if (strlen($text) > 3) {
                        return $text;
                    }
                }
            }
        }
        
        return null;
    }

    private function extractPrice(DOMXPath $xpath): ?float
    {
        // Prova diversi selettori
        $selectors = [
            '//fin-streamer[@data-field="regularMarketPrice"]',
            '//fin-streamer[@data-symbol and @data-field="regularMarketPrice"]',
            '//fin-streamer[contains(@class, "regularMarketPrice")]',
            '//span[@data-field="regularMarketPrice"]',
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $node = $nodes[0];
                // Prova prima l'attributo data-value
                $value = $node->getAttribute('data-value');
                if (empty($value)) {
                    $value = trim($node->textContent);
                }
                $parsed = $this->parseNumber($value);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }
        return null;
    }

    private function extractChange(DOMXPath $xpath): ?float
    {
        $selectors = [
            '//fin-streamer[@data-field="regularMarketChange"]',
            '//span[@data-field="regularMarketChange"]',
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $node = $nodes[0];
                $value = $node->getAttribute('data-value');
                if (empty($value)) {
                    $value = trim($node->textContent);
                }
                $parsed = $this->parseNumber($value);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }
        return null;
    }

    private function extractChangePercent(DOMXPath $xpath): ?float
    {
        $selectors = [
            '//fin-streamer[@data-field="regularMarketChangePercent"]',
            '//span[@data-field="regularMarketChangePercent"]',
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $node = $nodes[0];
                $value = $node->getAttribute('data-value');
                if (empty($value)) {
                    $value = trim($node->textContent);
                }
                $cleaned = str_replace(['%', '+'], '', $value);
                $parsed = $this->parseNumber($cleaned);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
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
    
    private function extractMarketTimes(DOMXPath $xpath, array &$data): void
    {
        // Cerca nel div che contiene tutto il testo con le date
        $priceContainer = $xpath->query('//div[contains(@class, "price") or contains(@class, "container")]');
        foreach ($priceContainer as $container) {
            $containerText = trim($container->textContent);
            
            // Pattern per "At close: January 2 at 4:00:01 PM EST"
            if (preg_match('/At close:\s*((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d+\s+at\s+\d+:\d+:\d+\s+(?:AM|PM)\s+(?:EST|PST|EDT|PDT|CST|MST))/i', $containerText, $matches)) {
                $data['market_close_time'] = trim($matches[1]);
            }
            
            // Pattern per "After hours: 7:59:59 PM EST" o "After hours: January 2 at 7:59:59 PM EST"
            if (preg_match('/After hours:\s*((?:(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d+\s+at\s+)?\d+:\d+:\d+\s+(?:AM|PM)\s+(?:EST|PST|EDT|PDT|CST|MST))/i', $containerText, $matches)) {
                if (!isset($data['after_hours'])) {
                    $data['after_hours'] = [];
                }
                $data['after_hours']['time'] = trim($matches[1]);
            }
        }
        
        // Fallback: cerca in tutti i testi
        if (empty($data['market_close_time'])) {
            $allText = $xpath->evaluate('string(.)');
            if (preg_match('/At close:\s*((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d+\s+at\s+\d+:\d+:\d+\s+(?:AM|PM)\s+(?:EST|PST|EDT|PDT|CST|MST))/i', $allText, $matches)) {
                $data['market_close_time'] = trim($matches[1]);
            }
        }
        
        if (empty($data['after_hours']['time'])) {
            $allText = $xpath->evaluate('string(.)');
            if (preg_match('/After hours:\s*((?:(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d+\s+at\s+)?\d+:\d+:\d+\s+(?:AM|PM)\s+(?:EST|PST|EDT|PDT|CST|MST))/i', $allText, $matches)) {
                if (!isset($data['after_hours'])) {
                    $data['after_hours'] = [];
                }
                $data['after_hours']['time'] = trim($matches[1]);
            }
        }
    }
}