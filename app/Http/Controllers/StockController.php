<?php
// app/Http/Controllers/StockController.php
namespace App\Http\Controllers;

use App\Models\Stock;
use App\Services\YahooFinanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockController extends Controller
{
    public function __construct(private YahooFinanceService $yahooFinance)
    {
    }

    public function index()
    {
        $userStocks = auth()->user()
            ->stocks()
            ->orderBy('symbol')
            ->get();

        return Inertia::render('Stocks/Index', [
            'stocks' => $userStocks
        ]);
    }

    public function create()
    {
        return Inertia::render('Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string|max:10',
            'purchase_price' => 'nullable|numeric|min:0'
        ]);

        $symbol = strtoupper($request->symbol);

        // Cerca o crea lo stock
        $stock = Stock::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol]
        );

        // Aggiungi alla watchlist dell'utente con prezzo di acquisto
        $pivotData = [];
        if ($request->filled('purchase_price')) {
            $pivotData['purchase_price'] = $request->purchase_price;
        }

        // Se lo stock esiste già, aggiorna il pivot, altrimenti lo crea
        if (auth()->user()->stocks()->where('stock_id', $stock->id)->exists()) {
            auth()->user()->stocks()->updateExistingPivot($stock->id, $pivotData);
        } else {
            auth()->user()->stocks()->attach($stock->id, $pivotData);
        }

        // Aggiorna i dati
        $this->updateStockData($stock);

        return redirect()->route('dashboard')
            ->with('success', "Azione {$symbol} aggiunta alla tua watchlist");
    }

    public function destroy(Stock $stock)
    {
        auth()->user()->stocks()->detach($stock->id);

        return redirect()->route('dashboard')
            ->with('success', 'Azione rimossa dalla watchlist');
    }

    public function refresh(Stock $stock)
    {
        $this->updateStockData($stock);

        return back()->with('success', 'Dati aggiornati');
    }

    public function updatePurchasePrice(Request $request, Stock $stock)
    {
        $request->validate([
            'purchase_price' => 'nullable|numeric|min:0'
        ]);

        $pivotData = [];
        if ($request->filled('purchase_price')) {
            $pivotData['purchase_price'] = $request->purchase_price;
        } else {
            $pivotData['purchase_price'] = null;
        }

        auth()->user()->stocks()->updateExistingPivot($stock->id, $pivotData);

        return back()->with('success', 'Prezzo di acquisto aggiornato');
    }

    private function updateStockData(Stock $stock): void
    {
        $data = $this->yahooFinance->fetchStockData($stock->symbol);

        if ($data) {
            // Assicurati di usare i dati di chiusura (regularMarket), non after hours
            $price = $data['current_price'] ?? null;
            $change = $data['change'] ?? null;
            $changePercent = $data['change_percent'] ?? null;
            
            // Se abbiamo dati after hours, salvali nel campo data JSON
            $jsonData = [
                'raw_data' => $data['raw_data'] ?? [],
                'market_close_time' => $data['market_close_time'] ?? null,
                'after_hours' => $data['after_hours'] ?? null,
                'fetched_at' => $data['fetched_at'] ?? now()->toIso8601String(),
            ];
            
            $stock->update([
                'name' => $data['name'] ?? $stock->name,
                'current_price' => $price,
                'change' => $change,
                'change_percent' => $changePercent,
                'data' => $jsonData,
                'last_updated' => now()
            ]);
        }
    }
}