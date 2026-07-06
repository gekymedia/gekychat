@php
    use App\Helpers\MessageHelper;
    use App\Helpers\UrlHelper;

    $isOwn = (int) $message->sender_id === (int) auth()->id();
    $messageId = $message->id;
    $body = $message->body ?? '';
    $isEncrypted = (bool) ($message->is_encrypted ?? false);
    $hasAttachments = $message->attachments->isNotEmpty();
    $hasReply = $message->reply_to && $message->replyTo;
    $isViewOnce = (bool) ($message->is_view_once ?? false);
    $processedBody = MessageHelper::formatPanelBody($body, $isEncrypted, $isOwn);
@endphp

@unless ($message->expires_at && $message->expires_at->isPast())
    <div class="message mb-3 d-flex {{ $isOwn ? 'justify-content-end' : 'justify-content-start' }} position-relative"
         data-message-id="{{ $messageId }}"
         data-message-date="{{ $message->created_at->toIso8601String() }}"
         data-context="direct"
         data-from-me="{{ $isOwn ? '1' : '0' }}"
         role="listitem">

        <div class="message-bubble {{ $isOwn ? 'sent' : 'received' }} position-relative">
            <div class="message-content">
                @if ($message->deleted_for_everyone_at)
                    <div class="message-text text-muted fst-italic">
                        {{ $isOwn ? 'You deleted this message' : 'This message was deleted' }}
                    </div>
                @else
                    @if ($hasReply && $message->replyTo)
                        <div class="reply-preview mb-2 p-2 rounded border-start border-3 border-primary bg-light"
                             data-reply-to="{{ $message->replyTo->id }}">
                            <small class="fw-semibold text-primary d-block">
                                {{ (int) $message->replyTo->sender_id === (int) auth()->id() ? 'You' : ($message->replyTo->sender->name ?? 'User') }}
                            </small>
                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($message->replyTo->body ?? '', 80) }}</small>
                        </div>
                    @endif

                    @if ($message->forwarded_from_id || ($message->is_forwarded ?? false))
                        <div class="forwarded-header mb-1"><small class="muted"><i class="bi bi-forward-fill me-1"></i>Forwarded</small></div>
                    @endif

                    @if ($isViewOnce)
                        <div class="view-once-container my-1 p-2 rounded border" data-message-id="{{ $messageId }}">
                            <small class="fw-semibold">View once message</small>
                        </div>
                    @elseif (!empty(trim($body)) && (!$isEncrypted || $isOwn) && !$message->location_data && !$message->contact_data && !$message->call_data && (($message->type ?? '') !== 'poll'))
                        <div class="message-text">{!! $processedBody !!}</div>
                    @endif

                    @if ($hasAttachments && !$isViewOnce)
                        <div class="attachments-container mt-2">
                            @foreach ($message->attachments as $attachment)
                                @php
                                    $filePath = $attachment->file_path ?? $attachment->path ?? null;
                                    $fileName = $attachment->original_name ?? $attachment->file_name ?? 'file';
                                    $mime = strtolower($attachment->mime_type ?? '');
                                    $fileUrl = isset($attachment->url)
                                        ? $attachment->url
                                        : ($filePath ? UrlHelper::secureStorageUrl($filePath) : '#');
                                    $isImage = str_contains($mime, 'image/');
                                    $isVideo = str_contains($mime, 'video/');
                                    $isAudio = str_contains($mime, 'audio/');
                                @endphp
                                <div class="attachment-item">
                                    @if ($isImage)
                                        <img src="{{ $fileUrl }}" alt="{{ $fileName }}" class="img-fluid rounded media-img" loading="lazy" style="max-width:300px;max-height:300px;object-fit:cover;">
                                    @elseif ($isVideo)
                                        <video controls class="img-fluid rounded media-video" preload="metadata" style="max-width:300px;max-height:300px;">
                                            <source src="{{ $fileUrl }}" type="{{ $attachment->mime_type }}">
                                        </video>
                                    @elseif ($isAudio)
                                        <audio controls preload="metadata" style="max-width:280px;">
                                            <source src="{{ $fileUrl }}" type="{{ $attachment->mime_type }}">
                                        </audio>
                                    @else
                                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                                            <i class="bi bi-paperclip"></i><span>{{ $fileName }}</span>
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($message->location_data)
                        <div class="message-text text-muted small"><i class="bi bi-geo-alt me-1"></i>Location</div>
                    @endif
                    @if ($message->contact_data)
                        <div class="message-text text-muted small"><i class="bi bi-person me-1"></i>Contact</div>
                    @endif
                    @if ($message->call_data)
                        <div class="message-text text-muted small"><i class="bi bi-telephone me-1"></i>Call</div>
                    @endif
                    @if (($message->type ?? '') === 'poll')
                        <div class="message-text text-muted small"><i class="bi bi-bar-chart me-1"></i>Poll</div>
                    @endif
                @endif
            </div>

            <div class="message-footer d-flex justify-content-between align-items-center mt-1">
                <small class="muted message-time">
                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('h:i A') }}</time>
                </small>
                @if ($isOwn && !$message->deleted_for_everyone_at)
                    <div class="status-indicator" data-message-id="{{ $messageId }}">
                        <i class="bi bi-check2 muted" title="Sent"></i>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endunless
