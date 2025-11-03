@extends('layouts.app')

@section('title', 'Quick Replies - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container-fluid h-100">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-card border-bottom border-border py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h1 class="h4 mb-0 fw-bold text-text">Quick Replies</h1>
                    <p class="text-muted mb-0">Save and reuse frequently sent messages</p>
                </div>
                <button class="btn btn-wa btn-sm" data-bs-toggle="modal" data-bs-target="#addQuickReplyModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Quick Reply
                </button>
            </div>
        </div>

        <div class="card-body bg-bg">
            {{-- Status Messages --}}
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div id="ajax-alerts-container"></div>

            {{-- Quick Replies List --}}
            <div id="quick-replies-container">
                @if($quickReplies->count() > 0)
                    <div class="list-group list-group-flush" id="quick-replies-list">
                        @foreach($quickReplies as $reply)
                            <div class="list-group-item quick-reply-item d-flex align-items-center px-0 py-3 bg-card border-border" 
                                 data-reply-id="{{ $reply->id }}">
                                
                                {{-- Drag Handle --}}
                                <div class="drag-handle me-3 text-muted cursor-grab">
                                    <i class="bi bi-grip-vertical"></i>
                                </div>

                                {{-- Reply Content --}}
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0 fw-semibold text-text">{{ $reply->title }}</h6>
                                        <div class="d-flex align-items-center gap-2">
                                            <small class="text-muted">
                                                <i class="bi bi-chat me-1"></i>
                                                Used {{ $reply->usage_count }} times
                                            </small>
                                            @if($reply->last_used_at)
                                                <small class="text-muted">
                                                    Last used {{ $reply->last_used_at->diffForHumans() }}
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                    <p class="mb-0 text-muted quick-reply-message">{{ $reply->message }}</p>
                                </div>

                                {{-- Actions --}}
                                <div class="dropdown ms-3">
                                    <button class="btn btn-sm btn-outline-secondary border-0 text-text" type="button" 
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                                        <li>
                                            <button class="dropdown-item text-text edit-quick-reply-btn" 
                                                    data-id="{{ $reply->id }}"
                                                    data-title="{{ $reply->title }}"
                                                    data-message="{{ $reply->message }}">
                                                <i class="bi bi-pencil me-2"></i>Edit
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-text copy-message-btn" 
                                                    data-message="{{ $reply->message }}">
                                                <i class="bi bi-clipboard me-2"></i>Copy Message
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider border-border"></li>
                                        <li>
                                            <button class="dropdown-item text-danger delete-quick-reply-btn" 
                                                    data-id="{{ $reply->id }}" 
                                                    data-title="{{ $reply->title }}">
                                                <i class="bi bi-trash me-2"></i>Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- Empty State --}}
                    <div class="text-center py-5" id="empty-state">
                        <div class="empty-state-icon mb-4">
                            <i class="bi bi-chat-square-text display-1 text-muted"></i>
                        </div>
                        <h4 class="text-muted mb-3">No quick replies yet</h4>
                        <p class="text-muted mb-4">Create your first quick reply to save time when chatting</p>
                        <button class="btn btn-wa" data-bs-toggle="modal" data-bs-target="#addQuickReplyModal">
                            <i class="bi bi-plus-lg me-2"></i>Create Your First Quick Reply
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Add/Edit Quick Reply Modal --}}
<div class="modal fade" id="quickReplyModal" tabindex="-1" aria-labelledby="quickReplyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-card border-border">
            <div class="modal-header border-border">
                <h5 class="modal-title fw-bold text-text" id="quickReplyModalLabel">Add Quick Reply</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickReplyForm" method="POST">
                @csrf
                <div id="form-method" style="display: none;"></div>
                
                <div class="modal-body text-text">
                    <div class="mb-3">
                        <label for="title" class="form-label text-text">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-input-bg border-input-border text-text" id="title" name="title" 
                               placeholder="e.g., Welcome Message, Busy Response" required maxlength="100">
                        <div class="form-text text-end text-muted">
                            <span id="title-char-count">100 characters left</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label text-text">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-input-bg border-input-border text-text" id="message" name="message" 
                                  rows="4" placeholder="Enter the message content..." required maxlength="1000"></textarea>
                        <div class="form-text text-end text-muted">
                            <span id="message-char-count">1000 characters left</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-border">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-wa" id="submitQuickReplyBtn">
                        <i class="bi bi-plus-lg me-1"></i> Save Quick Reply
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.quick-reply-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    margin-bottom: 4px;
}

.quick-reply-item:hover {
    background-color: color-mix(in srgb, var(--wa-green) 5%, transparent);
    transform: translateX(2px);
}

.quick-reply-item.sortable-ghost {
    opacity: 0.4;
    background-color: color-mix(in srgb, var(--wa-green) 10%, transparent);
}

.quick-reply-item.sortable-chosen {
    background-color: color-mix(in srgb, var(--wa-green) 8%, transparent);
    border-left: 3px solid var(--wa-green);
}

.drag-handle {
    cursor: grab;
    opacity: 0.5;
    transition: opacity 0.2s ease;
}

.drag-handle:hover {
    opacity: 1;
}

.quick-reply-message {
    line-height: 1.4;
    white-space: pre-wrap;
}

.empty-state-icon {
    opacity: 0.5;
}

/* Character counter styles */
.char-counter-warning {
    color: #dc3545;
    font-weight: 600;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // CSRF token setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    let currentEditId = null;

    // Initialize Sortable for drag and drop reordering
    if (document.getElementById('quick-replies-list')) {
        new Sortable(document.getElementById('quick-replies-list'), {
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            animation: 150,
            onEnd: function(evt) {
                const order = [];
                $('#quick-replies-list .quick-reply-item').each(function(index) {
                    order.push($(this).data('reply-id'));
                });
                
                $.ajax({
                    url: '{{ route("settings.quick-replies.reorder") }}',
                    method: 'POST',
                    data: { order: order },
                    success: function(response) {
                        showAlert('success', 'Quick replies reordered successfully');
                    },
                    error: function() {
                        showAlert('error', 'Failed to reorder quick replies');
                        // Reload to reset order
                        window.location.reload();
                    }
                });
            }
        });
    }

    // Character counters
    $('#title').on('input', function() {
        const maxLength = 100;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        $('#title-char-count').text(`${remaining} characters left`)
            .toggleClass('char-counter-warning', remaining < 20);
    });

    $('#message').on('input', function() {
        const maxLength = 1000;
        const currentLength = $(this).val().length;
        const remaining = maxLength - currentLength;
        $('#message-char-count').text(`${remaining} characters left`)
            .toggleClass('char-counter-warning', remaining < 50);
    });

    // Add Quick Reply Modal
    $(document).on('click', '[data-bs-target="#addQuickReplyModal"]', function() {
        currentEditId = null;
        $('#quickReplyModalLabel').text('Add Quick Reply');
        $('#quickReplyForm').attr('action', '{{ route("settings.quick-replies.store") }}');
        $('#form-method').html(''); // Clear any hidden method fields
        $('#title, #message').val('');
        $('#submitQuickReplyBtn').html('<i class="bi bi-plus-lg me-1"></i> Save Quick Reply');
        $('#title-char-count').text('100 characters left').removeClass('char-counter-warning');
        $('#message-char-count').text('1000 characters left').removeClass('char-counter-warning');
        
        // Show the modal (we'll use the same modal for both add and edit)
        $('#quickReplyModal').modal('show');
    });

    // Edit Quick Reply
    $(document).on('click', '.edit-quick-reply-btn', function() {
        currentEditId = $(this).data('id');
        const title = $(this).data('title');
        const message = $(this).data('message');

        $('#quickReplyModalLabel').text('Edit Quick Reply');
        $('#quickReplyForm').attr('action', `/settings/quick-replies/${currentEditId}`);
        $('#form-method').html('@method("PUT")');
        $('#title').val(title);
        $('#message').val(message);
        $('#submitQuickReplyBtn').html('<i class="bi bi-check me-1"></i> Update Quick Reply');

        // Update character counters
        $('#title').trigger('input');
        $('#message').trigger('input');

        $('#quickReplyModal').modal('show');
    });

    // Quick Reply Form Submission
    $('#quickReplyForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const url = $(this).attr('action');
        const method = $('#form-method').find('input[name="_method"]').val() || 'POST';
        
        const submitBtn = $('#submitQuickReplyBtn');
        submitBtn.prop('disabled', true).html('<i class="bi bi-arrow-repeat spinner me-1"></i> Saving...');

        $.ajax({
            url: url,
            method: method,
            data: formData,
            success: function(response) {
                $('#quickReplyModal').modal('hide');
                showAlert('success', response.message);
                
                if (method === 'POST') {
                    // Add new quick reply to the list
                    addQuickReplyToDOM(response.quick_reply);
                } else {
                    // Update existing quick reply in the list
                    updateQuickReplyInDOM(response.quick_reply);
                }
                
                updateEmptyState();
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    const firstError = Object.values(errors)[0][0];
                    showAlert('error', firstError);
                } else {
                    showAlert('error', 'Failed to save quick reply. Please try again.');
                }
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(method === 'POST' ? 
                    '<i class="bi bi-plus-lg me-1"></i> Save Quick Reply' : 
                    '<i class="bi bi-check me-1"></i> Update Quick Reply');
            }
        });
    });

    // Delete Quick Reply
    $(document).on('click', '.delete-quick-reply-btn', function() {
        const replyId = $(this).data('id');
        const replyTitle = $(this).data('title');
        
        if (confirm(`Are you sure you want to delete "${replyTitle}"? This action cannot be undone.`)) {
            const $replyItem = $(`[data-reply-id="${replyId}"]`);
            
            $.ajax({
                url: `/settings/quick-replies/${replyId}`,
                method: 'DELETE',
                beforeSend: function() {
                    $replyItem.css('opacity', '0.5');
                },
                success: function(response) {
                    $replyItem.slideUp(300, function() {
                        $(this).remove();
                        updateEmptyState();
                    });
                    showAlert('success', 'Quick reply deleted successfully!');
                },
                error: function() {
                    $replyItem.css('opacity', '1');
                    showAlert('error', 'Failed to delete quick reply. Please try again.');
                }
            });
        }
    });

    // Copy Message to Clipboard
    $(document).on('click', '.copy-message-btn', function() {
        const message = $(this).data('message');
        
        navigator.clipboard.writeText(message).then(function() {
            showAlert('success', 'Message copied to clipboard!');
        }).catch(function() {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = message;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showAlert('success', 'Message copied to clipboard!');
        });
    });

    // Modal cleanup
    $('#quickReplyModal').on('hidden.bs.modal', function() {
        currentEditId = null;
        $('#quickReplyForm')[0].reset();
        $('#title-char-count').text('100 characters left').removeClass('char-counter-warning');
        $('#message-char-count').text('1000 characters left').removeClass('char-counter-warning');
    });

    // Helper Functions
    function addQuickReplyToDOM(quickReply) {
        const replyHtml = `
            <div class="list-group-item quick-reply-item d-flex align-items-center px-0 py-3 bg-card border-border" 
                 data-reply-id="${quickReply.id}">
                
                <div class="drag-handle me-3 text-muted cursor-grab">
                    <i class="bi bi-grip-vertical"></i>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="mb-0 fw-semibold text-text">${quickReply.title}</h6>
                        <div class="d-flex align-items-center gap-2">
                            <small class="text-muted">
                                <i class="bi bi-chat me-1"></i>
                                Used ${quickReply.usage_count} times
                            </small>
                        </div>
                    </div>
                    <p class="mb-0 text-muted quick-reply-message">${quickReply.message}</p>
                </div>

                <div class="dropdown ms-3">
                    <button class="btn btn-sm btn-outline-secondary border-0 text-text" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                        <li>
                            <button class="dropdown-item text-text edit-quick-reply-btn" 
                                    data-id="${quickReply.id}"
                                    data-title="${quickReply.title}"
                                    data-message="${quickReply.message}">
                                <i class="bi bi-pencil me-2"></i>Edit
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item text-text copy-message-btn" 
                                    data-message="${quickReply.message}">
                                <i class="bi bi-clipboard me-2"></i>Copy Message
                            </button>
                        </li>
                        <li><hr class="dropdown-divider border-border"></li>
                        <li>
                            <button class="dropdown-item text-danger delete-quick-reply-btn" 
                                    data-id="${quickReply.id}" 
                                    data-title="${quickReply.title}">
                                <i class="bi bi-trash me-2"></i>Delete
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        `;
        
        if ($('#quick-replies-list').length) {
            $('#quick-replies-list').prepend(replyHtml);
        } else {
            $('#quick-replies-container').html(`<div class="list-group list-group-flush" id="quick-replies-list">${replyHtml}</div>`);
        }
        
        $('#empty-state').addClass('d-none');
    }

    function updateQuickReplyInDOM(quickReply) {
        const $replyItem = $(`[data-reply-id="${quickReply.id}"]`);
        $replyItem.find('.fw-semibold').text(quickReply.title);
        $replyItem.find('.quick-reply-message').text(quickReply.message);
        $replyItem.find('.edit-quick-reply-btn')
            .data('title', quickReply.title)
            .data('message', quickReply.message);
        $replyItem.find('.copy-message-btn').data('message', quickReply.message);
        $replyItem.find('.delete-quick-reply-btn').data('title', quickReply.title);
        $replyItem.find('.text-muted small:first').html(`<i class="bi bi-chat me-1"></i>Used ${quickReply.usage_count} times`);
    }

    function updateEmptyState() {
        if ($('.quick-reply-item').length === 0) {
            $('#empty-state').removeClass('d-none');
            $('#quick-replies-list').remove();
        } else {
            $('#empty-state').addClass('d-none');
        }
    }

    function showAlert(type, message, duration = 5000) {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const icon = {
            'success': 'bi-check-circle-fill',
            'error': 'bi-exclamation-triangle-fill',
            'warning': 'bi-exclamation-circle-fill',
            'info': 'bi-info-circle-fill'
        }[type] || 'bi-info-circle-fill';
        
        const alertId = 'alert-' + Date.now();
        const alertHtml = `
            <div id="${alertId}" class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="bi ${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#ajax-alerts-container').html(alertHtml);
        
        setTimeout(() => {
            $(`#${alertId}`).alert('close');
        }, duration);
    }
});
</script>
@endpush