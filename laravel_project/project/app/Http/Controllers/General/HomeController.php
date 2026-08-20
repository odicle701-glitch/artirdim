<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Category;
use App\Models\User;
use App\Models\Bid;
use Illuminate\Http\Request;

class HomeController extends Controller
{
     public function index(Request $request)
    {
        $query = Auction::with(['cover', 'category'])
            ->withCount('bids');

        // Kategori filtresi
        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        // Durum filtresi
        if ($request->status === 'ended') {
            $query->where('status', 'ended');
        } elseif ($request->status === 'active') {
            $query->where('status', 'active')->where('ends_at', '>', now());
        } else {
            // Varsayılan: aktif + biten
            $query->whereIn('status', ['active', 'ended']);
        }

        // Metin arama
        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        // Sıralama
        match ($request->sort) {
            'ending' => $query->where('status', 'active')->orderBy('ends_at'),
            'new'    => $query->orderByDesc('created_at'),
            'price'  => $query->orderByDesc('current_price'),
            default  => $query->orderByDesc('bids_count'),  // popüler
        };

        $activeAuctions = $query->take(24)->get();

        // Son eklenenler (filtre yokken göster)
        $recentAuctions = collect();
        if (!$request->hasAny(['q', 'category', 'status', 'sort'])) {
            $recentAuctions = Auction::with(['cover', 'category'])
                ->withCount('bids')
                ->whereIn('status', ['active', 'ended'])
                ->orderByDesc('created_at')
                ->take(8)
                ->get();
        }

        // Kategoriler
        $categories = Category::withCount([
            'auctions' => fn($q) => $q->whereIn('status', ['active', 'ended'])
        ])->having('auctions_count', '>', 0)->orderByDesc('auctions_count')->get();

        // İstatistikler (ileride kullanmak istersen)
        $stats = [
            'total_auctions'  => Auction::count(),
            'active_auctions' => Auction::where('status', 'active')->count(),
            'total_bids'      => Bid::count(),
            'total_users'     => User::count(),
        ];

        return view('index', compact(
            'activeAuctions',
            'recentAuctions',
            'categories',
            'stats'
        ));
    }
}
