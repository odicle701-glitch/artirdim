<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\FollowedNotification;

class FollowController extends Controller
{
    public function toggle(User $user)
    {
        $me = auth()->id();

        if ($me === $user->id) {
            return response()->json(['error' => 'Kendinizi takip edemezsiniz.'], 403);
        }

        $existing = Follow::where('follower_id', $me)
            ->where('following_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            Follow::create([
                'follower_id' => $me,
                'following_id' => $user->id,
            ]);

            $user->notify(new FollowedNotification(auth()->user()));

            $following = true;
        }

        $followerCount = Follow::where('following_id', $user->id)->count();

        return response()->json([
            'following' => $following,
            'follower_count' => $followerCount,
        ]);
    }

    public function followers(string $username)
    {
        $user = User::where('username', $username)->firstOrFail();
        $followers = Follow::where('following_id', $user->id)->with('follower')->latest()->paginate(24);
        $followerCount = Follow::where('following_id', $user->id)->count();
        $followingCount = Follow::where('follower_id', $user->id)->count();
        $type = 'followers';

        return view('profile.follow-list', compact('user', 'followers', 'followerCount', 'followingCount', 'type'));
    }

    public function following(string $username)
    {
        $user = User::where('username', $username)->firstOrFail();
        $followers = Follow::where('follower_id', $user->id)->with('following')->latest()->paginate(24);
        $followerCount = Follow::where('following_id', $user->id)->count();
        $followingCount = Follow::where('follower_id', $user->id)->count();
        $type = 'following';

        return view('profile.follow-list', compact('user', 'followers', 'followerCount', 'followingCount', 'type'));
    }
}
