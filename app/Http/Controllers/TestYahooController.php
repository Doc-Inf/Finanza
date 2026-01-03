<?php

namespace App\Http\Controllers;

use App\Services\YahooFinanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TestYahooController extends Controller
{
    public function __construct(private YahooFinanceService $yahooFinance)
    {
    }

    public function index()
    {
        return Inertia::render('TestYahoo');
    }

    public function fetch(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string|max:10'
        ]);

        $symbol = strtoupper($request->symbol);
        
        try {
            $data = $this->yahooFinance->fetchStockData($symbol);
            
            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'symbol' => $symbol,
                    'error' => 'Il servizio non è riuscito a recuperare i dati. Verifica che il simbolo sia corretto e che Yahoo Finance sia raggiungibile.',
                    'debug' => $this->yahooFinance->getLastError()
                ], 200);
            }
            
            // Verifica se abbiamo dati validi
            $hasData = !empty($data['current_price']) || !empty($data['name']);
            
            if (!$hasData) {
                return response()->json([
                    'success' => false,
                    'data' => $data,
                    'symbol' => $symbol,
                    'error' => 'Dati recuperati ma tutti i valori sono vuoti. Yahoo Finance potrebbe aver cambiato la struttura della pagina.',
                    'debug' => $this->yahooFinance->getLastError() ?? 'Nessun errore specifico'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'symbol' => $symbol
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'symbol' => $symbol,
                'error' => 'Errore durante il recupero dei dati: ' . $e->getMessage()
            ], 200);
        }
    }
}

