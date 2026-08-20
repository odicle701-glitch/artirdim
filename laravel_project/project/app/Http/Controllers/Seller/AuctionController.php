<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionImage;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AuctionController extends Controller
{
    public function index()
    {
        $allAuctions = auth()->user()->auctions();

        $counts = [
            'all' => (clone $allAuctions)->count(),
            'draft' => (clone $allAuctions)->where('status', 'draft')->count(),
            'active' => (clone $allAuctions)->where('status', 'active')->count(),
            'rejected' => (clone $allAuctions)->where('status', 'rejected')->count(),
            'ended' => (clone $allAuctions)->where('status', 'ended')->count(),
        ];

        $auctions = (clone $allAuctions)
            ->when(request('search'), fn ($q, $v) => $q->where('title', 'like', "%$v%"))
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('seller.auctions.index', compact('auctions', 'counts'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();

        return view('seller.auctions.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'required|string|max:5000',
            'starting_price' => 'required|numeric|min:1',
            'reserve_price' => 'nullable|numeric|min:0',
            'buy_now_price' => 'nullable|numeric|min:0',
            'min_bid_increment' => 'required|numeric|min:1',
            'condition' => 'required|in:new,used,refurbished',
            'location' => 'nullable|string|max:100',
            'starts_at' => 'required|date|after_or_equal:now',
            'ends_at' => 'required|date|after:starts_at',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|max:4096',
        ]);

        DB::transaction(function () use ($data, $request) {
            $auction = Auction::create(array_merge($data, [
                'user_id' => auth()->id(),
                'current_price' => $data['starting_price'],
                'status' => now()->lt($data['starts_at']) ? 'draft' : 'active',
            ]));

            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('auctions/'.$auction->id, 'public');
                AuctionImage::create([
                    'auction_id' => $auction->id,
                    'path' => $path,
                    'is_cover' => $i === 0,
                    'sort_order' => $i,
                ]);
            }
        });

        return redirect()->route('seller.auctions.index', auth()->user())
            ->with('profile_success', 'İlanın yayına alındı! 🎉');
    }

    public function show(Auction $auction)
    {
        abort_unless(
            auth()->id() === $auction->user_id || auth()->user()->hasRole('admin'),
            403
        );

        $auction->increment('view_count');
        $auction->load('user', 'category', 'images', 'bids.user');
        $relatedAuctions = Auction::where('category_id', $auction->category_id)
            ->where('id', '!=', $auction->id)
            ->where('status', 'active')
            ->limit(4)->get();

        return view('seller.auctions.show', compact('auction', 'relatedAuctions'));
    }

    public function edit(Auction $auction)
    {
        abort_unless(auth()->id() === $auction->user_id, 403);
        $categories = Category::orderBy('name')->get();

        return view('seller.auctions.edit', compact('auction', 'categories'));
    }

    public function update(Request $request, Auction $auction)
    {
        abort_unless(auth()->id() === $auction->user_id, 403);

        $rules = [
            'title' => 'required|string|max:120',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'required|string|max:5000',
            'min_bid_increment' => 'required|numeric|min:1',
            'reserve_price' => 'nullable|numeric|min:0',
            'buy_now_price' => 'nullable|numeric|min:0',
            'condition' => 'required|in:new,used,refurbished',
            'location' => 'nullable|string|max:100',
            'ends_at' => 'required|date|after:starts_at',
            'new_images.*' => 'nullable|image|max:4096',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'exists:auction_images,id',
        ];

        if ($auction->bidCount() === 0) {
            $rules['starting_price'] = 'required|numeric|min:1';
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($data, $request, $auction) {
            $auction->update($data);

            if ($request->filled('delete_images')) {
                $toDelete = AuctionImage::whereIn('id', $request->delete_images)
                    ->where('auction_id', $auction->id)->get();
                foreach ($toDelete as $img) {
                    Storage::disk('public')->delete($img->path);
                    $img->delete();
                }
                if (! $auction->fresh()->images()->where('is_cover', true)->exists()) {
                    $auction->images()->oldest()->first()?->update(['is_cover' => true]);
                }
            }

            if ($request->hasFile('new_images')) {
                $nextOrder = $auction->images()->max('sort_order') + 1;
                foreach ($request->file('new_images') as $file) {
                    $path = $file->store('auctions/'.$auction->id, 'public');
                    AuctionImage::create([
                        'auction_id' => $auction->id,
                        'path' => $path,
                        'is_cover' => $auction->images()->count() === 0,
                        'sort_order' => $nextOrder++,
                    ]);
                }
            }
        });

        return redirect()->route('seller.auctions.show', $auction)
            ->with('profile_success', 'İlan güncellendi.');
    }

    public function destroy(Auction $auction)
    {
        abort_unless(
            auth()->id() === $auction->user_id || auth()->user()->hasRole('admin'),
            403
        );
        $auction->update(['status' => 'cancelled']);
        $auction->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['message' => 'İlan kaldırıldı.']);
        }

        return back()->with('profile_success', 'İlan kaldırıldı.');
    }
}
