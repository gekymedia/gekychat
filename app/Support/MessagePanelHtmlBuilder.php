<?php

namespace App\Support;

use App\Helpers\DateHelper;
use App\Helpers\MessageHelper;
use App\Helpers\UrlHelper;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fast HTML builder for async messages-panel (no heavy Blade partials per message).
 */
class MessagePanelHtmlBuilder
{
    public static function render(Conversation $conversation, Collection $messages): string
    {
        $userId = (int) auth()->id();
        $html = '';
        $previousDate = null;

        foreach ($messages as $message) {
            if ($message->expires_at && $message->expires_at->isPast()) {
                continue;
            }

            $currentDate = $message->created_at->startOfDay();
            if ($previousDate === null || ! $currentDate->isSameDay($previousDate)) {
                $html .= '<div class="date-divider text-center my-3" data-date="' . e($message->created_at->format('Y-m-d')) . '">';
                $html .= '<span class="date-divider-text bg-bg px-3 py-1 rounded-pill text-muted small fw-semibold">';
                $html .= e(DateHelper::formatChatDate($message->created_at));
                $html .= '</span></div>';
            }
            $previousDate = $currentDate;

            $html .= self::renderMessage($message, $userId);
        }

        return $html;
    }

    private static function renderMessage(Message $message, int $userId): string
    {
        $isOwn = (int) $message->sender_id === $userId;
        $align = $isOwn ? 'justify-content-end' : 'justify-content-start';
        $bubble = $isOwn ? 'sent' : 'received';
        $id = (int) $message->id;

        $html = '<div class="message mb-3 d-flex ' . $align . ' position-relative"';
        $html .= ' data-message-id="' . $id . '"';
        $html .= ' data-message-date="' . e($message->created_at->toIso8601String()) . '"';
        $html .= ' data-context="direct" data-from-me="' . ($isOwn ? '1' : '0') . '" role="listitem">';
        $html .= '<div class="message-bubble ' . $bubble . ' position-relative"><div class="message-content">';

        if ($message->deleted_for_everyone_at) {
            $html .= '<div class="message-text text-muted fst-italic">';
            $html .= $isOwn ? 'You deleted this message' : 'This message was deleted';
            $html .= '</div>';
        } else {
            if ($message->reply_to && $message->replyTo) {
                $replyOwn = (int) $message->replyTo->sender_id === $userId;
                $replyName = $replyOwn ? 'You' : e($message->replyTo->sender->name ?? 'User');
                $html .= '<div class="reply-preview mb-2 p-2 rounded border-start border-3 border-primary bg-light" data-reply-to="' . (int) $message->replyTo->id . '">';
                $html .= '<small class="fw-semibold text-primary d-block">' . $replyName . '</small>';
                $html .= '<small class="text-muted">' . e(Str::limit($message->replyTo->body ?? '', 80)) . '</small></div>';
            }

            if ($message->forwarded_from_id || ($message->is_forwarded ?? false)) {
                $html .= '<div class="forwarded-header mb-1"><small class="muted"><i class="bi bi-forward-fill me-1"></i>Forwarded</small></div>';
            }

            $body = $message->body ?? '';
            $isEncrypted = (bool) ($message->is_encrypted ?? false);
            $isViewOnce = (bool) ($message->is_view_once ?? false);

            if ($isViewOnce) {
                $html .= '<div class="view-once-container my-1 p-2 rounded border" data-message-id="' . $id . '"><small class="fw-semibold">View once message</small></div>';
            } elseif (trim($body) !== '' && (! $isEncrypted || $isOwn) && ! $message->location_data && ! $message->contact_data && ! $message->call_data && (($message->type ?? '') !== 'poll')) {
                $html .= '<div class="message-text">' . MessageHelper::formatPanelBody($body, $isEncrypted, $isOwn, false) . '</div>';
            }

            if ($message->attachments->isNotEmpty() && ! $isViewOnce) {
                $html .= '<div class="attachments-container mt-2">';
                foreach ($message->attachments as $attachment) {
                    $html .= self::renderAttachment($attachment);
                }
                $html .= '</div>';
            }

            if ($message->location_data) {
                $html .= '<div class="message-text text-muted small"><i class="bi bi-geo-alt me-1"></i>Location</div>';
            }
            if ($message->contact_data) {
                $html .= '<div class="message-text text-muted small"><i class="bi bi-person me-1"></i>Contact</div>';
            }
            if ($message->call_data) {
                $html .= '<div class="message-text text-muted small"><i class="bi bi-telephone me-1"></i>Call</div>';
            }
            if (($message->type ?? '') === 'poll') {
                $html .= '<div class="message-text text-muted small"><i class="bi bi-bar-chart me-1"></i>Poll</div>';
            }
        }

        $html .= '</div><div class="message-footer d-flex justify-content-between align-items-center mt-1">';
        $html .= '<small class="muted message-time"><time datetime="' . e($message->created_at->toIso8601String()) . '">';
        $html .= e($message->created_at->format('h:i A')) . '</time></small>';
        if ($isOwn && ! $message->deleted_for_everyone_at) {
            $html .= '<div class="status-indicator" data-message-id="' . $id . '"><i class="bi bi-check2 muted" title="Sent"></i></div>';
        }
        $html .= '</div></div></div>';

        return $html;
    }

    private static function renderAttachment($attachment): string
    {
        $filePath = $attachment->file_path ?? $attachment->path ?? null;
        $fileName = e($attachment->original_name ?? $attachment->file_name ?? 'file');
        $mime = strtolower($attachment->mime_type ?? '');
        $fileUrl = isset($attachment->url)
            ? e($attachment->url)
            : ($filePath ? e(UrlHelper::secureStorageUrl($filePath)) : '#');

        if (str_contains($mime, 'image/')) {
            return '<div class="attachment-item"><img src="' . $fileUrl . '" alt="' . $fileName . '" class="img-fluid rounded media-img" loading="lazy" style="max-width:300px;max-height:300px;object-fit:cover;"></div>';
        }
        if (str_contains($mime, 'video/')) {
            return '<div class="attachment-item"><video controls class="img-fluid rounded media-video" preload="metadata" style="max-width:300px;max-height:300px;"><source src="' . $fileUrl . '" type="' . e($attachment->mime_type) . '"></video></div>';
        }
        if (str_contains($mime, 'audio/')) {
            return '<div class="attachment-item"><audio controls preload="metadata" style="max-width:280px;"><source src="' . $fileUrl . '" type="' . e($attachment->mime_type) . '"></audio></div>';
        }

        return '<div class="attachment-item"><a href="' . $fileUrl . '" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-2 text-decoration-none"><i class="bi bi-paperclip"></i><span>' . $fileName . '</span></a></div>';
    }
}
