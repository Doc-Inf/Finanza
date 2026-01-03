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
            'symbol' => 'required|string|max:10'
        ]);

        $symbol = strtoupper($request->symbol);

        // Cerca o crea lo stock
        $stock = Stock::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol]
        );

        // Aggiungi alla watchlist dell'utente
        auth()->user()->stocks()->syncWithoutDetaching($stock->id);

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

    private function updateStockData(Stock $stock): void
    {
        $data = $this->yahooFinance->fetchStockData($stock->symbol);

        if ($data) {
            $stock->update([
                'name' => $data['name'] ?? $stock->name,
                'current_price' => $data['current_price'],
                'change' => $data['change'],
                'change_percent' => $data['change_percent'],
                'data' => $data['raw_data'],
                'last_updated' => now()
            ]);
        }
    }
}