@extends('layouts.app')

@section('title', 'Devices & Sessions - ' . config('app.name', 'GekyChat'))

@section('content')
<div class="container-fluid h-100">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-card border-bottom border-border py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h1 class="h4 mb-0 fw-bold text-text">Devices & Sessions</h1>
                    <p class="text-muted mb-0">Manage your active sessions across devices</p>
                </div>
                <button class="btn btn-outline-danger btn-sm" id="logoutAllOtherBtn">
                    <i class="bi bi-laptop me-1"></i> Log Out All Other Devices
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

            {{-- Current Device --}}
            <div class="mb-4">
                <h6 class="fw-semibold text-text mb-3">Current Session</h6>
                @php
                    $currentSession = $sessions->where('session_id', $currentSessionId)->first();
                    // If no current session found, create one
                    if (!$currentSession && $sessions->isNotEmpty()) {
                        $currentSession = $sessions->first();
                    }
                @endphp
                @if($currentSession)
                    <div class="card bg-success bg-opacity-10 border-success border-opacity-25">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-success bg-opacity-25 rounded-circle p-3">
                                        <i class="bi bi-laptop text-success" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 text-text">
                                            {{ $currentSession->platform }} • {{ $currentSession->browser }}
                                            <span class="badge bg-success ms-2">Current</span>
                                        </h6>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <small class="text-muted">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                {{ $currentSession->location ?? 'Unknown location' }}
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>
                                                Active now
                                            </small>
                                            <small class="text-muted">
                                                <i class="bi bi-wifi me-1"></i>
                                                {{ $currentSession->ip_address }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="text-success d-block">
                                        <i class="bi bi-check-circle me-1"></i>
                                        This device
                                    </small>
                                    <small class="text-muted">
                                        Last activity: Now
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card bg-card border-border">
                        <div class="card-body text-center py-4">
                            <i class="bi bi-laptop display-4 text-muted mb-3"></i>
                            <p class="text-muted mb-0">Current session information not available</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Other Active Sessions --}}
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-semibold text-text mb-0">Other Active Sessions</h6>
                    <small class="text-muted">
                        {{ $sessions->where('session_id', '!=', $currentSessionId)->count() }} devices
                    </small>
                </div>

                @if($sessions->where('session_id', '!=', $currentSessionId)->count() > 0)
                    <div class="list-group list-group-flush" id="sessions-list">
                        @foreach($sessions->where('session_id', '!=', $currentSessionId) as $session)
                            <div class="list-group-item session-item d-flex align-items-center px-0 py-3 bg-card border-border" 
                                 data-session-id="{{ $session->session_id }}">
                                
                                {{-- Device Icon --}}
                                <div class="me-3">
                                    <span style="font-size: 1.5rem;">{{ $session->getDeviceIcon() }}</span>
                                </div>

                                {{-- Session Info --}}
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <h6 class="mb-0 fw-semibold text-text">
                                            {{ $session->platform }} • {{ $session->browser }}
                                            @if($session->is_active)
                                                <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 ms-2">Active</span>
                                            @else
                                                <span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-50 ms-2">Inactive</span>
                                            @endif
                                        </h6>
                                        <div class="d-flex align-items-center gap-2">
                                            <small class="text-muted">
                                                {{ $session->last_activity_human }}
                                            </small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt me-1"></i>
                                            {{ $session->location ?? 'Unknown location' }}
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi {{ $session->getPlatformIcon() }} me-1"></i>
                                            {{ $session->device_type }}
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-wifi me-1"></i>
                                            {{ $session->ip_address }}
                                        </small>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="dropdown ms-3">
                                    <button class="btn btn-sm btn-outline-secondary border-0 text-text" type="button" 
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end bg-card border-border">
                                        <li>
                                            <button class="dropdown-item text-danger logout-session-btn" 
                                                    data-session-id="{{ $session->session_id }}"
                                                    data-platform="{{ $session->platform }}"
                                                    data-browser="{{ $session->browser }}">
                                                <i class="bi bi-power me-2"></i>Log Out This Device
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- No Other Sessions --}}
                    <div class="card bg-card border-border">
                        <div class="card-body text-center py-5">
                            <div class="empty-state-icon mb-4">
                                <i class="bi bi-phone display-1 text-muted"></i>
                            </div>
                            <h5 class="text-muted mb-3">No other active sessions</h5>
                            <p class="text-muted mb-0">You're only logged in on this device</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Session Security Tips --}}
            <div class="card bg-warning bg-opacity-10 border-warning border-opacity-25">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi bi-shield-check text-warning mt-1"></i>
                        <div>
                            <h6 class="fw-semibold text-text mb-2">Session Security</h6>
                            <ul class="list-unstyled text-muted small mb-0">
                                <li class="mb-1"><i class="bi bi-check-circle me-2 text-success"></i> Regularly review your active sessions</li>
                                <li class="mb-1"><i class="bi bi-check-circle me-2 text-success"></i> Log out from devices you no longer use</li>
                                <li class="mb-1"><i class="bi bi-check-circle me-2 text-success"></i> Use strong, unique passwords</li>
                                <li class="mb-0"><i class="bi bi-check-circle me-2 text-success"></i> Enable two-factor authentication if available</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.session-item {
    transition: all 0.2s ease;
    border-radius: 8px;
    margin-bottom: 4px;
}

.session-item:hover {
    background-color: color-mix(in srgb, var(--wa-green) 5%, transparent);
}

.empty-state-icon {
    opacity: 0.5;
}

/* Security tips card */
.bg-warning.bg-opacity-10 {
    background: color-mix(in srgb, var(--warning) 10%, transparent) !important;
}

.border-warning.border-opacity-25 {
    border-color: color-mix(in srgb, var(--warning) 25%, transparent) !important;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // CSRF token setup - get from meta tag or generate
    let csrfToken = $('meta[name="csrf-token"]').attr('content');
    if (!csrfToken) {
        // Fallback: get from Laravel's csrf_token() helper
        csrfToken = '{{ csrf_token() }}';
    }
    
    // Ensure CSRF token is available for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
    
    // Also add a hidden input for form-based requests if needed
    if ($('input[name="_token"]').length === 0) {
        $('body').append(`<input type="hidden" name="_token" value="${csrfToken}">`);
    }

    // Log out a specific session
    $(document).on('click', '.logout-session-btn', function() {
        const sessionId = $(this).data('session-id');
        const platform = $(this).data('platform');
        const browser = $(this).data('browser');
        
        if (confirm(`Are you sure you want to log out from ${platform} • ${browser}? This will terminate that session immediately.`)) {
            const $sessionItem = $(`[data-session-id="${sessionId}"]`);
            
            $.ajax({
                url: `/settings/devices/${sessionId}`,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                beforeSend: function() {
                    $sessionItem.css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.redirect) {
                        // This was the current session, redirect to login
                        window.location.href = response.redirect;
                    } else {
                        $sessionItem.slideUp(300, function() {
                            $(this).remove();
                            updateSessionsCount();
                            checkNoSessionsState();
                        });
                        showAlert('success', response.message);
                    }
                },
                error: function(xhr) {
                    $sessionItem.css('opacity', '1');
                    const error = xhr.responseJSON?.message || 'Failed to log out session';
                    showAlert('error', error);
                }
            });
        }
    });

    // Log out all other sessions
    $('#logoutAllOtherBtn').on('click', function() {
        if (confirm('Are you sure you want to log out all other devices? This will terminate all sessions except this one.')) {
            const $btn = $(this);
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<i class="bi bi-arrow-repeat spinner me-1"></i> Logging out...');

            $.ajax({
                url: '{{ route("settings.devices.logout-all-other") }}',
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    $('#sessions-list').slideUp(300, function() {
                        $(this).remove();
                        checkNoSessionsState();
                    });
                    showAlert('success', response.message);
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || 'Failed to log out sessions';
                    showAlert('error', error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    });

    // Helper Functions
    function updateSessionsCount() {
        const otherSessionsCount = $('.session-item').length;
        $('h6.fw-semibold.text-text').next('small').text(`${otherSessionsCount} devices`);
    }

    function checkNoSessionsState() {
        if ($('.session-item').length === 0) {
            // Show no sessions message
            if (!$('#no-other-sessions').length) {
                $('#sessions-list').parent().html(`
                    <div class="card bg-card border-border">
                        <div class="card-body text-center py-5">
                            <div class="empty-state-icon mb-4">
                                <i class="bi bi-phone display-1 text-muted"></i>
                            </div>
                            <h5 class="text-muted mb-3">No other active sessions</h5>
                            <p class="text-muted mb-0">You're only logged in on this device</p>
                        </div>
                    </div>
                `);
            }
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

    // Update session activity periodically (every 5 minutes)
    setInterval(() => {
        $.ajax({
            url: '{{ route("settings.devices.update-activity") }}',
            method: 'POST',
            error: function() {
                console.log('Failed to update session activity');
            }
        });
    }, 300000); // 5 minutes
});
</script>
@endpush