<?php
    ini_set('memory_limit', '512M');
    set_time_limit(120);

    $azione = "GOOG";
    $url = "https://finance.yahoo.com/quote/$azione/";
    $localFile = __DIR__ . "/pagina_yahoo_$azione.html";

    // 1. Scarica la pagina HTML e la salva in un file locale
    $options = [
        "http" => [
            "method" => "GET",
            // Usa uno User-Agent più comune e aggiungi l'intestazione Accept-Language
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.88 Safari/537.36\r\n" .
                        "Accept-Language: en-US,en;q=0.5\r\n" .
                        "Accept-Encoding: gzip\r\n"
                        
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $html = "";

    // Decomprimi se è gzip
    if (substr($response, 0, 2) === "\x1f\x8b") {
        $html = gzdecode($response);
    } else {
        $html = $response;
    }

    if ($html === false) {
        die("Errore durante il download della pagina.");
    }

    file_put_contents($localFile, $html);

    // 2. Carica il file HTML localmente con DOMDocument
    libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $document->loadHTMLFile($localFile);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($document);

    // funzione utilitaria per ottenere gli attributi di un nodo
    function getNodeAttributes(DOMNode $node): array {
        $attrs = [];
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $attrs[$attr->name] = $attr->value;
            }
        }
        return $attrs;
    }

    // raccogliamo risultati
    $results = [];

    // 1. Estrai tutti i <fin-streamer> (usati da Yahoo per molti valori)  
    $nodes = $xpath->query('//fin-streamer');
    foreach ($nodes as $node) {
        /** @var DOMElement $node */
        $attrs = getNodeAttributes($node);
        $text = trim($node->textContent);
        $results[] = [
            'tag' => 'fin-streamer',
            'text' => $text,
            'attributes' => $attrs
        ];
    }

    // 2. Estrai spans/divs/td che contengono numeri o percentuali – filtro generico
    $nodes2 = $xpath->query('//span | //div | //td');
    foreach ($nodes2 as $node) {
        $text = trim($node->textContent);
        // seleziona solo se il testo sembra “dato azionario” (numero, %, con cifra decimale, ecc.)
        if (preg_match('/^[\d\.\,]+(%?)/', $text)) {
            $attrs = getNodeAttributes($node);
            $results[] = [
                'tag' => $node->nodeName,
                'text' => $text,
                'attributes' => $attrs
            ];
        }
    }

    // 3. Estrai tutti i <script> che contengono “root.App.main” – potenzialmente JSON interno
    $scriptNodes = $xpath->query('//script');
    foreach ($scriptNodes as $node) {
        $scriptText = $node->textContent;
        if (strpos($scriptText, 'root.App.main') !== false) {
            $results[] = [
                'tag' => 'script',
                'type' => 'rootAppMain',
                'script' => $scriptText
            ];
        }
    }

    // (Opzionale) 4. estrai i link <a> con href che contengono “/quote/” ecc.
    $linkNodes = $xpath->query('//a[contains(@href, "/quote/")]');
    foreach ($linkNodes as $node) {
        $attrs = getNodeAttributes($node);
        $text = trim($node->textContent);
        $results[] = [
            'tag' => 'a',
            'text' => $text,
            'attributes' => $attrs
        ];
    }

    // Ora $results contiene un array con molti elementi: tag, testo, attributi
    // Puoi salvare in JSON, in DB o elaborarlo
    echo "<pre>";
    echo htmlspecialchars(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "</pre>";

    file_put_contents("risultatiJson.txt", htmlspecialchars(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));