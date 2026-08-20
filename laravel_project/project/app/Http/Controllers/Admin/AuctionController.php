<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Category;
use App\Notifications\AuctionStatusNotification;
use Illuminate\Http\Request;

class AuctionController extends Controller
{
    public function index(Request $request)
    {
        $auctions = Auction::with(['user', 'category'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all'      => Auction::count(),
            'draft'    => Auction::where('status', 'draft')->count(),
            'active'   => Auction::where('status', 'active')->count(),
            'rejected' => Auction::where('status', 'rejected')->count(),
            'ended'    => Auction::where('status', 'ended')->count(),
        ];

        return view('admin.auctions.index', compact('auctions', 'counts'));
    }

    public function show(Auction $auction)
    {
        $auction->load(['user', 'category', 'images', 'bids.user']);
        return view('admin.auctions.show', compact('auction'));
    }

    public function edit(Auction $auction)
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.auctions.edit', compact('auction', 'categories'));
    }

    public function update(Request $request, Auction $auction)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:120',
            'description'       => 'required|string|max:5000',
            'category_id'       => 'nullable|exists:categories,id',
            'condition'         => 'required|in:new,used,refurbished',
            'location'          => 'nullable|string|max:100',
            'starting_price'    => 'required|numeric|min:1',
            'min_bid_increment' => 'required|numeric|min:1',
            'reserve_price'     => 'nullable|numeric|min:0',
            'buy_now_price'     => 'nullable|numeric|min:0',
            'starts_at'         => 'required|date',
            'ends_at'           => 'required|date|after:starts_at',
            'status'            => 'required|in:draft,active,rejected,ended,cancelled,sold',
        ]);

        $auction->update($data);

        return redirect()
            ->route('admin.auctions.show', $auction)
            ->with('success', 'İlan güncellendi.');
    }

    public function approve(Auction $auction)
    {
        $auction->update(['status' => 'active']);

        $auction->user->notify(
            new AuctionStatusNotification($auction, 'approved')
        );

        return back()->with('success', "\"{$auction->title}\" onaylandı.");
    }

    public function reject(Request $request, Auction $auction)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $auction->update(['status' => 'rejected']);

        $auction->user->notify(
            new AuctionStatusNotification($auction, 'rejected', $request->reason)
        );

        return back()->with('success', "\"{$auction->title}\" reddedildi.");
    }

    public function destroy(Auction $auction)
    {
        $title = $auction->title;
        $auction->delete();

        $msg = "\"{$title}\" silindi.";

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['message' => $msg]);
        }

        return redirect()
            ->route('admin.auctions.index')
            ->with('success', $msg);
    }
}
