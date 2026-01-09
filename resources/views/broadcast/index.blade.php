@extends('layouts.app')

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="border-bottom bg-card p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0 fw-semibold">Broadcast Lists</h5>
                <small class="text-muted">Send messages to multiple contacts at once</small>
            </div>
            <button class="btn btn-primary btn-sm" id="create-broadcast-btn">
                <i class="bi bi-plus-circle me-1"></i>Create Broadcast List
            </button>
        </div>
    </div>

    <div class="flex-grow-1 overflow-auto p-3">
        <div id="broadcast-lists-container">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading broadcast lists...</p>
            </div>
        </div>
    </div>
</div>

<!-- Create Broadcast Modal -->
<div class="modal fade" id="create-broadcast-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Broadcast List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="create-broadcast-form">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" id="broadcast-name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (optional)</label>
                        <textarea class="form-control" id="broadcast-description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Recipients *</label>
                        <div id="recipients-list" class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-muted small mb-0">Loading contacts...</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-broadcast-btn">Create</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadBroadcastLists();

    document.getElementById('create-broadcast-btn')?.addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('create-broadcast-modal'));
        loadContactsForModal();
        modal.show();
    });

    document.getElementById('save-broadcast-btn')?.addEventListener('click', function() {
        createBroadcastList();
    });
});

function loadBroadcastLists() {
    fetch('/api/v1/broadcast-lists', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('broadcast-lists-container');
        if (data.data && data.data.length > 0) {
            container.innerHTML = data.data.map(list => `
                <div class="card mb-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${escapeHtml(list.name)}</h6>
                                ${list.description ? `<p class="text-muted small mb-1">${escapeHtml(list.description)}</p>` : ''}
                                <small class="text-muted">${list.recipient_count} ${list.recipient_count === 1 ? 'recipient' : 'recipients'}</small>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="sendBroadcast(${list.id})">Send Message</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="editBroadcast(${list.id})">Edit</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteBroadcast(${list.id})">Delete</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-campaign display-1 text-muted"></i>
                    <p class="mt-3 text-muted">No broadcast lists yet</p>
                    <button class="btn btn-primary" onclick="document.getElementById('create-broadcast-btn').click()">
                        Create Broadcast List
                    </button>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading broadcast lists:', error);
        document.getElementById('broadcast-lists-container').innerHTML = `
            <div class="alert alert-danger">
                Failed to load broadcast lists. Please try again.
            </div>
        `;
    });
}

function loadContactsForModal() {
    fetch('/api/v1/contacts', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('recipients-list');
        if (data.data && data.data.length > 0) {
            const registeredContacts = data.data.filter(c => c.contact_user_id || c.is_registered);
            container.innerHTML = registeredContacts.map(contact => `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${contact.contact_user_id || contact.user_id}" id="contact-${contact.id}">
                    <label class="form-check-label" for="contact-${contact.id}">
                        ${escapeHtml(contact.display_name || contact.name || contact.phone || 'Unknown')}
                    </label>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="text-muted small mb-0">No registered contacts available</p>';
        }
    })
    .catch(error => {
        console.error('Error loading contacts:', error);
        document.getElementById('recipients-list').innerHTML = '<p class="text-danger small">Failed to load contacts</p>';
    });
}

function createBroadcastList() {
    const name = document.getElementById('broadcast-name').value.trim();
    const description = document.getElementById('broadcast-description').value.trim();
    const checkboxes = document.querySelectorAll('#recipients-list input[type="checkbox"]:checked');
    const recipientIds = Array.from(checkboxes).map(cb => parseInt(cb.value)).filter(id => !isNaN(id));

    if (!name) {
        alert('Please enter a name');
        return;
    }

    if (recipientIds.length === 0) {
        alert('Please select at least one recipient');
        return;
    }

    const btn = document.getElementById('save-broadcast-btn');
    btn.disabled = true;
    btn.textContent = 'Creating...';

    fetch('/api/v1/broadcast-lists', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            name: name,
            description: description || null,
            recipients: recipientIds
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.data) {
            bootstrap.Modal.getInstance(document.getElementById('create-broadcast-modal')).hide();
            document.getElementById('create-broadcast-form').reset();
            loadBroadcastLists();
            showToast('Broadcast list created successfully', 'success');
        } else {
            throw new Error(data.message || 'Failed to create broadcast list');
        }
    })
    .catch(error => {
        console.error('Error creating broadcast list:', error);
        alert('Failed to create broadcast list: ' + error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Create';
    });
}

function sendBroadcast(id) {
    // Navigate to send broadcast screen
    window.location.href = `/broadcast-lists/${id}/send`;
}

function editBroadcast(id) {
    // Navigate to edit broadcast screen
    window.location.href = `/broadcast-lists/${id}/edit`;
}

function deleteBroadcast(id) {
    if (!confirm('Are you sure you want to delete this broadcast list?')) {
        return;
    }

    fetch(`/api/v1/broadcast-lists/${id}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (response.ok) {
            loadBroadcastLists();
            showToast('Broadcast list deleted', 'success');
        } else {
            throw new Error('Failed to delete broadcast list');
        }
    })
    .catch(error => {
        console.error('Error deleting broadcast list:', error);
        alert('Failed to delete broadcast list');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    // Use existing toast system if available, otherwise use alert
    if (window.showToast) {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}
</script>
@endpush
