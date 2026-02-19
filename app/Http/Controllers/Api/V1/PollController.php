<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\GroupMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PollController extends Controller
{
    /**
     * GET /api/v1/polls/{messageId}
     *
     * Returns poll data with current vote counts for a message.
     */
    public function show(Request $request, int $messageId)
    {
        $user = $request->user();

        $poll = DB::table('message_polls')
            ->where('message_id', $messageId)
            ->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found'], 404);
        }

        return response()->json($this->formatPoll($poll, $user->id));
    }

    /**
     * POST /api/v1/polls/{pollId}/vote
     *
     * Cast or retract a vote on a poll option.
     * Body: { option_id: int } or { option_ids: [int] } for multi-select.
     */
    public function vote(Request $request, int $pollId)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'option_id'  => 'nullable|integer|exists:message_poll_options,id',
            'option_ids' => 'nullable|array',
            'option_ids.*' => 'integer|exists:message_poll_options,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $poll = DB::table('message_polls')->where('id', $pollId)->first();
        if (!$poll) {
            return response()->json(['message' => 'Poll not found'], 404);
        }

        if ($poll->closes_at && now()->gt($poll->closes_at)) {
            return response()->json(['message' => 'This poll has closed'], 403);
        }

        $optionIds = $request->input('option_ids')
            ?? ($request->input('option_id') ? [$request->input('option_id')] : []);

        if (!$poll->allow_multiple && count($optionIds) > 1) {
            return response()->json(['message' => 'This poll only allows one vote'], 422);
        }

        DB::transaction(function () use ($pollId, $optionIds, $user, $poll) {
            // Remove previous votes in this poll
            DB::table('message_poll_votes')
                ->where('poll_id', $pollId)
                ->where('user_id', $user->id)
                ->delete();

            // Insert new votes
            foreach ($optionIds as $optionId) {
                // Verify option belongs to this poll
                $valid = DB::table('message_poll_options')
                    ->where('id', $optionId)
                    ->where('poll_id', $pollId)
                    ->exists();

                if ($valid) {
                    DB::table('message_poll_votes')->insertOrIgnore([
                        'poll_id'    => $pollId,
                        'option_id'  => $optionId,
                        'user_id'    => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $updatedPoll = DB::table('message_polls')->where('id', $pollId)->first();
        return response()->json($this->formatPoll($updatedPoll, $user->id));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formatPoll(object $poll, int $userId): array
    {
        $options = DB::table('message_poll_options')
            ->where('poll_id', $poll->id)
            ->orderBy('sort_order')
            ->get();

        $totalVotes = DB::table('message_poll_votes')
            ->where('poll_id', $poll->id)
            ->count();

        $myVotes = DB::table('message_poll_votes')
            ->where('poll_id', $poll->id)
            ->where('user_id', $userId)
            ->pluck('option_id')
            ->toArray();

        $formattedOptions = $options->map(function ($opt) use ($poll, $totalVotes, $myVotes, $userId) {
            $voteCount = DB::table('message_poll_votes')
                ->where('option_id', $opt->id)
                ->count();

            $percentage = $totalVotes > 0 ? round(($voteCount / $totalVotes) * 100) : 0;

            // In anonymous polls, only show voters for your own votes
            $voters = [];
            if (!$poll->is_anonymous) {
                $voters = DB::table('message_poll_votes as mpv')
                    ->join('users as u', 'u.id', '=', 'mpv.user_id')
                    ->where('mpv.option_id', $opt->id)
                    ->select('u.id', 'u.name', 'u.avatar_path')
                    ->limit(10)
                    ->get()
                    ->toArray();
            }

            return [
                'id'         => $opt->id,
                'text'       => $opt->text,
                'vote_count' => $voteCount,
                'percentage' => $percentage,
                'is_voted'   => in_array($opt->id, $myVotes),
                'voters'     => $voters,
            ];
        });

        return [
            'id'             => $poll->id,
            'message_id'     => $poll->message_id,
            'question'       => $poll->question,
            'allow_multiple' => (bool) $poll->allow_multiple,
            'is_anonymous'   => (bool) $poll->is_anonymous,
            'closes_at'      => $poll->closes_at,
            'is_closed'      => $poll->closes_at && now()->gt($poll->closes_at),
            'total_votes'    => $totalVotes,
            'options'        => $formattedOptions,
        ];
    }
}
