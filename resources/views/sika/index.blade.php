@extends('layouts.app')

@section('title', 'Sika Wallet - ' . config('app.name'))

@push('styles')
<style>
    .wallet-card {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(37, 211, 102, 0.3);
    }
    .pack-card {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid transparent;
    }
    .pack-card:hover {
        transform: translateY(-4px);
        border-color: var(--geky-green, #25D366);
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }
    .pack-card.selected {
        border-color: var(--geky-green, #25D366);
        background: rgba(37, 211, 102, 0.1);
    }
    .transaction-item {
        transition: background 0.2s;
    }
    .transaction-item:hover {
        background: var(--bg-hover, rgba(0,0,0,0.02));
    }
    .quick-action-btn {
        transition: all 0.2s ease;
    }
    .quick-action-btn:hover {
        transform: translateY(-2px);
    }
    .balance-amount {
        font-size: 3rem;
        font-weight: 700;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    @media (max-width: 768px) {
        .balance-amount {
            font-size: 2.5rem;
        }
    }
</style>
@endpush

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="chat-header border-bottom p-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-0"><i class="bi bi-coin text-warning me-2"></i>Sika Wallet</h4>
                <small class="text-muted">Manage your coins</small>
            </div>
        </div>
    </div>
    
    <div class="flex-grow-1 overflow-auto p-3 p-md-4">
        <div class="container" style="max-width: 900px;">
            <!-- Balance Card -->
            <div class="wallet-card mb-4 p-4">
                <div class="text-white text-center py-3">
                    <p class="mb-2 opacity-75 text-uppercase" style="letter-spacing: 1px; font-size: 0.85rem;">Your Balance</p>
                    <div class="balance-amount mb-2">
                        <i class="bi bi-coin me-2"></i>
                        <span id="wallet-balance">{{ number_format($wallet->balance ?? 0) }}</span>
                    </div>
                    <p class="mb-0 opacity-75">Sika Coins</p>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <button class="btn btn-outline-primary w-100 py-3 quick-action-btn" onclick="document.getElementById('coin-packs-section').scrollIntoView({behavior: 'smooth'})">
                        <i class="bi bi-plus-circle d-block mb-2" style="font-size: 28px;"></i>
                        <span class="fw-medium">Buy Coins</span>
                    </button>
                </div>
                <div class="col-6 col-md-3">
                    <button class="btn btn-outline-success w-100 py-3 quick-action-btn" data-bs-toggle="modal" data-bs-target="#sendCoinsModal">
                        <i class="bi bi-send d-block mb-2" style="font-size: 28px;"></i>
                        <span class="fw-medium">Send</span>
                    </button>
                </div>
                <div class="col-6 col-md-3">
                    <button class="btn btn-outline-warning w-100 py-3 quick-action-btn" data-bs-toggle="modal" data-bs-target="#giftCoinsModal">
                        <i class="bi bi-gift d-block mb-2" style="font-size: 28px;"></i>
                        <span class="fw-medium">Gift</span>
                    </button>
                </div>
                <div class="col-6 col-md-3">
                    <button class="btn btn-outline-secondary w-100 py-3 quick-action-btn" onclick="document.getElementById('transactions-section').scrollIntoView({behavior: 'smooth'})">
                        <i class="bi bi-clock-history d-block mb-2" style="font-size: 28px;"></i>
                        <span class="fw-medium">History</span>
                    </button>
                </div>
            </div>
            
            <!-- Coin Packs -->
            <div class="card mb-4" id="coin-packs-section">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0"><i class="bi bi-bag-fill text-primary me-2"></i>Buy Coin Packs</h5>
                </div>
                <div class="card-body">
                    @if($packs->isEmpty())
                    <p class="text-muted text-center py-4">No coin packs available at the moment.</p>
                    @else
                    <div class="row g-3" id="coin-packs">
                        @foreach($packs as $pack)
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card h-100 pack-card position-relative" data-pack-id="{{ $pack->id }}" data-pack-price="{{ $pack->price_ghs }}" data-pack-coins="{{ $pack->coins }}">
                                <div class="card-body text-center py-4">
                                    @if(($pack->bonus_coins ?? 0) > 0)
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                        +{{ $pack->bonus_coins }} Bonus!
                                    </span>
                                    @endif
                                    <i class="bi bi-coin text-warning" style="font-size: 40px;"></i>
                                    <h3 class="mt-3 mb-1">{{ number_format($pack->coins) }}</h3>
                                    <p class="text-muted mb-3 small">coins</p>
                                    <h5 class="text-primary mb-0 fw-bold">
                                        GHS {{ number_format($pack->price_ghs, 2) }}
                                    </h5>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Transaction History -->
            <div class="card" id="transactions-section">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history text-secondary me-2"></i>Transaction History</h5>
                    <select class="form-select form-select-sm w-auto" id="transaction-filter">
                        <option value="">All Transactions</option>
                        <option value="credit">Credits Only</option>
                        <option value="debit">Debits Only</option>
                    </select>
                </div>
                <div class="card-body p-0">
                    <div id="transactions-list">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="text-muted mt-2 mb-0">Loading transactions...</p>
                        </div>
                    </div>
                    <div id="load-more-container" class="text-center py-3 border-top" style="display: none;">
                        <button class="btn btn-outline-primary btn-sm" id="load-more-btn">Load More</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Send Coins Modal -->
<div class="modal fade" id="sendCoinsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-send text-success me-2"></i>Send Coins</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="send-coins-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient</label>
                        <input type="text" class="form-control" id="recipient-search" placeholder="Search by name or phone..." autocomplete="off">
                        <input type="hidden" name="to_user_id" id="recipient-id">
                        <div id="recipient-results" class="list-group mt-2 position-absolute w-100" style="display: none; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                        <div id="selected-recipient" class="mt-2" style="display: none;">
                            <span class="badge bg-success p-2">
                                <i class="bi bi-person-check me-1"></i>
                                <span id="selected-recipient-name"></span>
                                <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="clearRecipient()"></button>
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-coin text-warning"></i></span>
                            <input type="number" name="coins" class="form-control" min="1" required placeholder="Enter amount">
                        </div>
                        <small class="text-muted">Your balance: <span id="send-balance">{{ number_format($wallet->balance ?? 0) }}</span> coins</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note (optional)</label>
                        <input type="text" name="note" class="form-control" maxlength="100" placeholder="Add a note...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send me-1"></i> Send Coins
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Gift Coins Modal -->
<div class="modal fade" id="giftCoinsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-gift text-warning me-2"></i>Gift Coins</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="gift-coins-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient</label>
                        <input type="text" class="form-control" id="gift-recipient-search" placeholder="Search by name or phone..." autocomplete="off">
                        <input type="hidden" name="to_user_id" id="gift-recipient-id">
                        <div id="gift-recipient-results" class="list-group mt-2 position-absolute w-100" style="display: none; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                        <div id="gift-selected-recipient" class="mt-2" style="display: none;">
                            <span class="badge bg-warning text-dark p-2">
                                <i class="bi bi-person-check me-1"></i>
                                <span id="gift-selected-recipient-name"></span>
                                <button type="button" class="btn-close ms-2" style="font-size: 0.6rem;" onclick="clearGiftRecipient()"></button>
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gift Amount</label>
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <button type="button" class="btn btn-outline-warning gift-preset" data-amount="10">10</button>
                            <button type="button" class="btn btn-outline-warning gift-preset" data-amount="50">50</button>
                            <button type="button" class="btn btn-outline-warning gift-preset" data-amount="100">100</button>
                            <button type="button" class="btn btn-outline-warning gift-preset" data-amount="500">500</button>
                            <button type="button" class="btn btn-outline-warning gift-preset" data-amount="1000">1000</button>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-coin text-warning"></i></span>
                            <input type="number" name="coins" class="form-control" min="1" required placeholder="Or enter custom amount">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message (optional)</label>
                        <input type="text" name="note" class="form-control" maxlength="100" placeholder="Say something nice...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-gift me-1"></i> Send Gift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Purchase Confirmation Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="bi bi-coin text-warning" style="font-size: 48px;"></i>
                <h4 class="mt-3" id="purchase-coins">0</h4>
                <p class="text-muted">coins</p>
                <h5 class="text-primary" id="purchase-price">GHS 0.00</h5>
                <p class="text-muted small mt-3">Amount will be deducted from your Priority Bank wallet.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-purchase-btn">
                    <i class="bi bi-check-lg me-1"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let hasMorePages = false;
    let selectedPackId = null;
    
    loadTransactions();
    
    // Pack selection
    document.querySelectorAll('.pack-card').forEach(card => {
        card.addEventListener('click', function() {
            selectedPackId = this.dataset.packId;
            const coins = this.dataset.packCoins;
            const price = this.dataset.packPrice;
            
            document.getElementById('purchase-coins').textContent = Number(coins).toLocaleString();
            document.getElementById('purchase-price').textContent = 'GHS ' + Number(price).toFixed(2);
            
            const modal = new bootstrap.Modal(document.getElementById('purchaseModal'));
            modal.show();
        });
    });
    
    // Confirm purchase
    document.getElementById('confirm-purchase-btn')?.addEventListener('click', async function() {
        if (!selectedPackId) return;
        
        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
        
        try {
            const response = await fetch('/api/sika/purchase/initiate', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    pack_id: parseInt(selectedPackId),
                    idempotency_key: crypto.randomUUID()
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('purchaseModal')).hide();
                showToast('Coins purchased successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to purchase coins', 'danger');
            }
        } catch (error) {
            console.error('Error purchasing pack:', error);
            showToast('Failed to purchase coins. Please try again.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
    
    // Transaction filter
    document.getElementById('transaction-filter').addEventListener('change', function() {
        currentPage = 1;
        loadTransactions(this.value);
    });
    
    // Load more
    document.getElementById('load-more-btn')?.addEventListener('click', function() {
        currentPage++;
        loadTransactions(document.getElementById('transaction-filter').value, true);
    });
    
    // Send coins form
    document.getElementById('send-coins-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        await transferCoins(new FormData(this), 'transfer');
    });
    
    // Gift coins form
    document.getElementById('gift-coins-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        await transferCoins(new FormData(this), 'gift');
    });
    
    // Gift preset buttons
    document.querySelectorAll('.gift-preset').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelector('#gift-coins-form input[name="coins"]').value = this.dataset.amount;
            document.querySelectorAll('.gift-preset').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Recipient search (Send)
    let searchTimeout;
    document.getElementById('recipient-search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchRecipients(this.value, 'recipient'), 300);
    });
    
    // Recipient search (Gift)
    document.getElementById('gift-recipient-search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchRecipients(this.value, 'gift-recipient'), 300);
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#recipient-search') && !e.target.closest('#recipient-results')) {
            document.getElementById('recipient-results').style.display = 'none';
        }
        if (!e.target.closest('#gift-recipient-search') && !e.target.closest('#gift-recipient-results')) {
            document.getElementById('gift-recipient-results').style.display = 'none';
        }
    });
});

async function loadTransactions(direction = '', append = false) {
    const list = document.getElementById('transactions-list');
    const loadMoreContainer = document.getElementById('load-more-container');
    
    if (!append) {
        list.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2 mb-0">Loading transactions...</p>
            </div>
        `;
    }
    
    try {
        let url = '/api/sika/transactions?per_page=20';
        if (direction) url += `&direction=${direction}`;
        if (append) url += `&page=${currentPage}`;
        
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        const transactions = data.data || [];
        const pagination = data.pagination || {};
        
        if (transactions.length === 0 && !append) {
            list.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 48px;"></i>
                    <p class="text-muted mt-3 mb-0">No transactions yet</p>
                </div>
            `;
            loadMoreContainer.style.display = 'none';
            return;
        }
        
        const html = transactions.map(tx => {
            const isCredit = tx.direction === 'credit';
            const icon = isCredit ? 'bi-arrow-down-circle-fill text-success' : 'bi-arrow-up-circle-fill text-danger';
            const sign = isCredit ? '+' : '-';
            const colorClass = isCredit ? 'text-success' : 'text-danger';
            const date = new Date(tx.created_at);
            
            return `
                <div class="d-flex align-items-center p-3 border-bottom transaction-item">
                    <div class="me-3">
                        <i class="bi ${icon}" style="font-size: 28px;"></i>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <strong class="d-block text-truncate">${escapeHtml(tx.description || tx.type || 'Transaction')}</strong>
                        <small class="text-muted">${date.toLocaleDateString()} ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</small>
                    </div>
                    <div class="text-end ms-3">
                        <strong class="${colorClass}" style="font-size: 1.1rem;">
                            ${sign}${Number(tx.coins).toLocaleString()}
                        </strong>
                        <br>
                        <small class="text-muted">coins</small>
                    </div>
                </div>
            `;
        }).join('');
        
        if (append) {
            list.innerHTML += html;
        } else {
            list.innerHTML = html;
        }
        
        // Show/hide load more
        hasMorePages = pagination.current_page < pagination.last_page;
        loadMoreContainer.style.display = hasMorePages ? 'block' : 'none';
        
    } catch (error) {
        console.error('Error loading transactions:', error);
        if (!append) {
            list.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 48px;"></i>
                    <p class="text-danger mt-3 mb-0">Failed to load transactions</p>
                    <button class="btn btn-outline-primary btn-sm mt-2" onclick="loadTransactions()">Retry</button>
                </div>
            `;
        }
    }
}

async function transferCoins(formData, type) {
    const toUserId = formData.get('to_user_id');
    const coins = formData.get('coins');
    const note = formData.get('note');
    
    if (!toUserId) {
        showToast('Please select a recipient', 'warning');
        return;
    }
    
    if (!coins || coins < 1) {
        showToast('Please enter a valid amount', 'warning');
        return;
    }
    
    const modalId = type === 'gift' ? 'giftCoinsModal' : 'sendCoinsModal';
    const submitBtn = document.querySelector(`#${modalId} button[type="submit"]`);
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
    
    try {
        const endpoint = type === 'gift' ? '/api/sika/gift' : '/api/sika/transfer';
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                to_user_id: parseInt(toUserId),
                coins: parseInt(coins),
                note: note || null,
                idempotency_key: crypto.randomUUID()
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
            showToast(type === 'gift' ? 'Gift sent successfully!' : 'Coins sent successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to send coins', 'danger');
        }
    } catch (error) {
        console.error('Error sending coins:', error);
        showToast('Failed to send coins. Please try again.', 'danger');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

async function searchRecipients(query, prefix) {
    const resultsDiv = document.getElementById(`${prefix}-results`);
    
    if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`/api/contacts?search=${encodeURIComponent(query)}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        let contacts = data.data || data || [];
        
        // Filter to only registered users
        contacts = contacts.filter(c => c.contact_user_id || c.user_id || c.is_registered);
        
        // Filter by search query
        if (query) {
            const q = query.toLowerCase();
            contacts = contacts.filter(c => {
                const name = (c.display_name || c.user_name || c.name || '').toLowerCase();
                const phone = (c.phone || c.normalized_phone || '').toLowerCase();
                return name.includes(q) || phone.includes(q);
            });
        }
        
        if (contacts.length > 0) {
            resultsDiv.innerHTML = contacts.slice(0, 10).map(c => {
                const userId = c.contact_user_id || c.user_id;
                const name = c.display_name || c.user_name || c.name || 'Unknown';
                const phone = c.phone || c.normalized_phone || '';
                const avatar = c.avatar_url || '/images/default-avatar.png';
                
                return `
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center" 
                            onclick="selectRecipient(${userId}, '${escapeHtml(name)}', '${prefix}')">
                        <img src="${escapeHtml(avatar)}" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.src='/images/default-avatar.png'">
                        <div>
                            <strong>${escapeHtml(name)}</strong>
                            ${phone ? `<br><small class="text-muted">${escapeHtml(phone)}</small>` : ''}
                        </div>
                    </button>
                `;
            }).join('');
            resultsDiv.style.display = 'block';
        } else {
            resultsDiv.innerHTML = '<div class="list-group-item text-muted">No registered contacts found</div>';
            resultsDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('Error searching recipients:', error);
        resultsDiv.style.display = 'none';
    }
}

function selectRecipient(id, name, prefix) {
    document.getElementById(`${prefix}-id`).value = id;
    document.getElementById(`${prefix}-search`).value = '';
    document.getElementById(`${prefix}-results`).style.display = 'none';
    
    const selectedDiv = document.getElementById(`${prefix === 'gift-recipient' ? 'gift-' : ''}selected-recipient`);
    const nameSpan = document.getElementById(`${prefix === 'gift-recipient' ? 'gift-' : ''}selected-recipient-name`);
    
    nameSpan.textContent = name;
    selectedDiv.style.display = 'block';
}

function clearRecipient() {
    document.getElementById('recipient-id').value = '';
    document.getElementById('recipient-search').value = '';
    document.getElementById('selected-recipient').style.display = 'none';
}

function clearGiftRecipient() {
    document.getElementById('gift-recipient-id').value = '';
    document.getElementById('gift-recipient-search').value = '';
    document.getElementById('gift-selected-recipient').style.display = 'none';
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed shadow-lg`;
    toast.style.cssText = 'bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px; max-width: 90%;';
    toast.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'danger' ? 'bi-exclamation-circle' : 'bi-info-circle'} me-2"></i>
            <span>${escapeHtml(message)}</span>
        </div>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush
@endsection
