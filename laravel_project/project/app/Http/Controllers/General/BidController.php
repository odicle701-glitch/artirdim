<?php

namespace App\Http\Controllers\General;

use App\Events\BidPlaced;
use App\Http\Controllers\Controller;
use App\Console\Commands\CloseAuctions;
use App\Models\Auction;
use App\Models\Bid;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class BidController extends Controller
{
    public function store(Request $request, Auction $auction)
    {
        if (! $auction->isActive()) {
            return response()->json(['message' => 'Bu müzayede aktif değil.'], 422);
        }

        if ($auction->user_id === auth()->id()) {
            return response()->json(['message' => 'Kendi ilanınıza teklif veremezsiniz.'], 422);
        }

        $minAmount = (float) $auction->current_price + (float) $auction->min_bid_increment;

        try {
            $request->validate([
                'amount' => "required|numeric|min:{$minAmount}",
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->errors()['amount'][0] ?? 'Geçersiz teklif.',
            ], 422);
        }

        $bid = Bid::create([
            'auction_id' => $auction->id,
            'user_id'    => auth()->id(),
            'amount'     => $request->amount,
            'ip_address' => $request->ip(),
        ]);

        $auction->update(['current_price' => $request->amount]);

        broadcast(new BidPlaced($bid))->toOthers();

        return response()->json([
            'bid_id'        => $bid->id,
            'bidder_id'     => (int) auth()->id(),
            'bidder_name'   => auth()->user()->name,
            'amount'        => (float) $bid->amount,
            'display_price' => number_format($bid->amount, 0, ',', '.') . ' ₺',
            'total_bids'    => $auction->fresh()->bids()->count(),
        ]);
    }

    public function show(Auction $auction)
    {
        $auction->increment('view_count');
        $auction->load(['images', 'cover', 'bids.user', 'category', 'user']);

        return view('auctionsnew', compact('auction'));
    }

    /**
     * Canlı durum (polling). WebSocket olmadan gerçek-zamanlı akış sağlar:
     * yeni teklifler, güncel fiyat, izleyici sayısı ve satış durumu.
     */
    public function liveState(Request $request, Auction $auction)
    {
        // Cron olmayan ortamda süresi dolan açık artırmaları fırsatçı kapat
        CloseAuctions::runThrottled(app(OrderService::class));

        $viewerCount = $this->trackViewer($request, $auction);

        $auction->refresh();

        $after = (int) $request->query('after', 0);

        $newBids = $auction->bids()
            ->reorder()
            ->where('bids.id', '>', $after)
            ->with('user:id,name')
            ->orderBy('bids.id')
            ->take(25)
            ->get()
            ->map(fn (Bid $b) => [
                'bid_id'        => $b->id,
                'bidder_id'     => (int) $b->user_id,
                'bidder_name'   => $b->user?->name ?? 'Kullanıcı',
                'amount'        => (float) $b->amount,
                'display_price' => number_format($b->amount, 0, ',', '.') . ' ₺',
            ]);

        $sold = null;
        if (in_array($auction->status, ['sold', 'ended'], true)) {
            $win = $auction->winning_bid_id
                ? Bid::with('user:id,name')->find($auction->winning_bid_id)
                : null;
            $sold = [
                'status'      => $auction->status,
                'buyer_name'  => $win?->user?->name,
                'display_price' => $win ? number_format($win->amount, 0, ',', '.') . ' ₺' : null,
            ];
        }

        return response()->json([
            'status'        => $auction->status,
            'is_live'       => (bool) $auction->is_live,
            'stream_mode'   => $auction->stream_mode,
            'current_price' => (float) $auction->current_price,
            'display_price' => number_format($auction->current_price, 0, ',', '.') . ' ₺',
            'total_bids'    => $auction->bids()->count(),
            'viewer_count'  => $viewerCount,
            'new_bids'      => $newBids,
            'sold'          => $sold,
            'server_time'   => now()->toIso8601String(),
            'ends_at'       => optional($auction->ends_at)->toIso8601String(),
        ]);
    }

    /** İzleyici presence'ini cache üzerinden takip eder (satıcı hariç). */
    private function trackViewer(Request $request, Auction $auction): int
    {
        $key = "auction:{$auction->id}:viewers";
        $viewers = Cache::get($key, []);

        $now = now()->timestamp;
        $me  = auth()->check() ? (string) auth()->id() : 'g:' . $request->session()->getId();

        $viewers[$me] = $now;

        // 15 sn'den eski izleyicileri düş
        $viewers = array_filter($viewers, fn ($ts) => ($now - $ts) <= 15);

        Cache::put($key, $viewers, now()->addMinute());

        $sellerKey = (string) $auction->user_id;
        $count = count(array_filter(array_keys($viewers), fn ($id) => $id !== $sellerKey));

        return $count;
    }
}
