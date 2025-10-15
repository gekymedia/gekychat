<?php

namespace App\Services;

use App\Events\GroupMessageSent;
use App\Events\MessageSent as DmMessageSent; // adjust if your event class is named differently
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\Message;
use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ForwardService
{
    /**
     * Forward a DM message to mixed targets (DM conversations and/or groups).
     * @param Message $original
     * @param User    $actor
     * @param array   $targets [ ['type'=>'conversation','id'=>...], ['type'=>'group','id'=>...] ]
     * @return array ['conversations'=>[Message,...],'groups'=>[GroupMessage,...]]
     */
    public function forwardDmToTargets(Message $original, User $actor, array $targets): array
    {
        $results = ['conversations' => [], 'groups' => []];

        // Base forward chain for cross-type delivery
        $baseChain = $original->forward_chain ?? [];
        array_unshift($baseChain, [
            'id'           => $original->id,
            'sender'       => $original->sender->name ?? $original->sender->phone ?? 'User',
            'body'         => Str::limit((string) ($original->body ?? ''), 100),
            'timestamp'    => optional($original->created_at)?->toIso8601String(),
            'is_encrypted' => (bool) ($original->is_encrypted ?? false),
            'source'       => 'dm',
        ]);

        foreach ($targets as $t) {
            if (($t['type'] ?? '') === 'conversation') {
                $conv = Conversation::find($t['id'] ?? 0);
                if (!$conv || !in_array($actor->getAuthIdentifier(), [$conv->user_one_id, $conv->user_two_id])) {
                    continue;
                }

                // Same-type: preserve forwarded_from_id & use original buildForwardChain()
                $forwardChain = $original->buildForwardChain();

                $msg = Message::create([
                    'conversation_id'   => $conv->id,
                    'sender_id'         => $actor->getAuthIdentifier(),
                    'body'              => (string)($original->body ?? ''),
                    'forwarded_from_id' => $original->id,
                    'forward_chain'     => $forwardChain,
                ]);

                $this->copyAttachments($original, $msg);

                $msg->load(['sender','attachments','forwardedFrom','reactions.user']);
                broadcast(new DmMessageSent($msg))->toOthers();
                $results['conversations'][] = $msg;

            } elseif (($t['type'] ?? '') === 'group') {
                $group = Group::with('members:id')->find($t['id'] ?? 0);
                if (!$group || !$group->members()->where('user_id', $actor->getAuthIdentifier())->exists()) {
                    continue;
                }

                // Cross-type: cannot set forwarded_from_id; embed neutral chain
                $gm = $group->messages()->create([
                    'sender_id'         => $actor->getAuthIdentifier(),
                    'body'              => (string)($original->body ?? ''),
                    'forwarded_from_id' => null,
                    'forward_chain'     => $baseChain,
                    'delivered_at'      => now(),
                ]);

                $this->copyAttachments($original, $gm);

                $gm->load(['sender','attachments','reactions.user']);
                broadcast(new GroupMessageSent($gm))->toOthers();
                $results['groups'][] = $gm;
            }
        }

        return $results;
    }

    /**
     * Forward a Group message to mixed targets (groups and/or DM conversations).
     * @param GroupMessage $original
     * @param User         $actor
     * @param array        $targets
     * @return array ['groups'=>[GroupMessage,...],'conversations'=>[Message,...]]
     */
    public function forwardGroupToTargets(GroupMessage $original, User $actor, array $targets): array
    {
        $results = ['groups' => [], 'conversations' => []];

        $baseChain = $original->forward_chain ?? [];
        array_unshift($baseChain, [
            'id'           => $original->id,
            'sender'       => $original->sender->name ?? $original->sender->phone ?? 'User',
            'body'         => Str::limit((string) ($original->body ?? ''), 100),
            'timestamp'    => optional($original->created_at)?->toIso8601String(),
            'is_encrypted' => false,
            'source'       => 'group',
        ]);

        foreach ($targets as $t) {
            if (($t['type'] ?? '') === 'group') {
                $group = Group::with('members:id')->find($t['id'] ?? 0);
                if (!$group || !$group->members()->where('user_id', $actor->getAuthIdentifier())->exists()) {
                    continue;
                }

                // Same-type: preserve forwarded_from_id & use original buildForwardChain()
                $forwardChain = $original->buildForwardChain();

                $msg = $group->messages()->create([
                    'sender_id'         => $actor->getAuthIdentifier(),
                    'body'              => (string)($original->body ?? ''),
                    'forwarded_from_id' => $original->id,
                    'forward_chain'     => $forwardChain,
                    'delivered_at'      => now(),
                ]);

                $this->copyAttachments($original, $msg);

                $msg->load(['sender','attachments','forwardedFrom','reactions.user']);
                broadcast(new GroupMessageSent($msg))->toOthers();
                $results['groups'][] = $msg;

            } elseif (($t['type'] ?? '') === 'conversation') {
                $conv = Conversation::find($t['id'] ?? 0);
                if (!$conv || !in_array($actor->getAuthIdentifier(), [$conv->user_one_id, $conv->user_two_id])) {
                    continue;
                }

                // Cross-type to DM: forwarded_from_id must be null; embed neutral chain
                $m = Message::create([
                    'conversation_id'   => $conv->id,
                    'sender_id'         => $actor->getAuthIdentifier(),
                    'body'              => (string)($original->body ?? ''),
                    'forwarded_from_id' => null,
                    'forward_chain'     => $baseChain,
                ]);

                $this->copyAttachments($original, $m);

                $m->load(['sender','attachments','reactions.user']);
                broadcast(new DmMessageSent($m))->toOthers();
                $results['conversations'][] = $m;
            }
        }

        return $results;
    }

    /**
     * Copy attachments from a source (Message|GroupMessage) to a destination (Message|GroupMessage).
     * Keeps original filenames/mime/size but makes a new physical copy on the 'public' disk.
     */
    protected function copyAttachments($source, $destination): void
    {
        if (!$source->relationLoaded('attachments')) {
            $source->load('attachments');
        }
        if ($source->attachments->isEmpty()) return;

        foreach ($source->attachments as $att) {
            $ext     = pathinfo($att->file_path, PATHINFO_EXTENSION);
            $newPath = 'attachments/' . uniqid('fwd_', true) . ($ext ? ('.'.$ext) : '');

            // best-effort copy; skip if missing
            try {
                Storage::disk('public')->copy($att->file_path, $newPath);
            } catch (\Throwable $e) {
                continue;
            }

            $destination->attachments()->create([
                'user_id'       => $destination->sender_id,
                'file_path'     => $newPath,
                'original_name' => $att->original_name,
                'mime_type'     => $att->mime_type,
                'size'          => $att->size,
            ]);
        }
    }
}
