{{-- Modal prompting user to link their Google contacts on first login --}}
<div class="modal fade" id="google-contact-modal" tabindex="-1" aria-labelledby="googleContactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="googleContactModalLabel">Link your Google contacts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Link your Google account to find contacts who are already on GekyChat. This will import your Google contacts and highlight those who are registered on the platform.</p>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <a href="{{ route('contacts.index') }}" class="btn btn-primary">Okay</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Later</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const showModal = @json(session('show_google_contact_modal', false));
            if (showModal) {
                const modalEl = document.getElementById('google-contact-modal');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
                // Clear the session flag via AJAX to avoid showing again. We hit a simple route to clear.
                fetch('/clear-google-modal-flag', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                }).catch(() => {});
            }
        });
    </script>
@endpush