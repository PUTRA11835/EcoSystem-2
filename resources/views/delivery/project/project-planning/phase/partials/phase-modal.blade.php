{{-- ✅ FULL RESPONSIVE PHASE CONFIGURATION MODAL --}}
<!-- Phase Configuration Modal (Vertical Only - Fixed) -->
<div id="phaseConfigModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl md:max-w-4xl flex flex-col max-h-[90vh]">
            <!-- Header -->
            <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
                <div>
                    <h3 class="text-xl font-bold text-gray-900" id="modal-title">Konfigurasi Fase Proyek</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Manage project phases and weights</p>
                </div>
                <button onclick="closePhaseConfigModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-4 sm:px-6 py-4 sm:py-5 overflow-y-auto flex-1">
                <!-- Current Phases -->
                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Fase Aktif</h4>
                        <div id="verticalPhasesList" class="space-y-2">
                            @foreach($verticalPhases as $phase)
                                <div class="phase-item bg-white p-3 rounded border border-gray-200" 
                                     data-phase-id="{{ $phase->id }}"
                                     data-original-weight="{{ $phase->weight }}"
                                     data-original-visible="{{ $phase->is_visible ? '1' : '0' }}"
                                     data-orientation="vertical">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                        <div class="flex items-center space-x-3 flex-1 min-w-0">
                                            <span class="drag-handle cursor-move flex-shrink-0">
                                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                                </svg>
                                            </span>
                                            <div class="w-3 h-3 rounded flex-shrink-0" style="background-color: {{ $phase->color }}"></div>
                                            <span class="font-medium text-sm truncate">{{ $phase->name }}</span>

                                            @if($phase->is_golive_phase)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 flex-shrink-0">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                                    </svg>
                                                    Go-Live
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center space-x-2 flex-shrink-0">
                                            <input type="number" 
                                                   value="{{ $phase->weight }}"
                                                   oninput="markPhaseAsModified({{ $phase->id }})"
                                                   class="phase-weight-input w-20 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   min="0" max="100" step="0.1"
                                                   data-phase-id="{{ $phase->id }}">
                                            <span class="text-sm text-gray-500">%</span>

                                            <button onclick="toggleGoLivePhase({{ $phase->id }})" 
                                                    data-phase-id="{{ $phase->id }}"
                                                    class="golive-btn p-1 {{ $phase->is_golive_phase ? 'text-green-600' : 'text-gray-400' }}"
                                                    title="{{ $phase->is_golive_phase ? 'Go-Live Phase' : 'Mark as Go-Live Phase' }}">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                            
                                            <button onclick="togglePhaseVisibilityUI({{ $phase->id }})" 
                                                    data-phase-id="{{ $phase->id }}"
                                                    class="visibility-btn p-1 {{ $phase->is_visible ? 'text-blue-600' : 'text-gray-400' }}"
                                                    title="{{ $phase->is_visible ? 'Visible' : 'Hidden' }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path class="eye-open {{ $phase->is_visible ? '' : 'hidden' }}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    <path class="eye-closed {{ $phase->is_visible ? 'hidden' : '' }}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                                </svg>
                                            </button>
                                            
                                            <button onclick="removePhaseUI({{ $phase->id }})" 
                                                    class="p-1 text-red-600 hover:text-red-800"
                                                    title="Remove Phase">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Total Weight Indicator -->
                        <div class="mt-3 flex justify-between items-center text-sm">
                            <span class="text-gray-600">Total Bobot:</span>
                            <span id="verticalTotalWeight" class="font-bold">0%</span>
                        </div>
                        
                        <!-- Warning if not 100% -->
                        <div id="weightWarning" class="hidden mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs sm:text-sm text-yellow-800">
                            ⚠️ Total bobot harus 100% untuk dapat menyimpan konfigurasi
                        </div>
                    </div>

                    <!-- Add New Phase -->
                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Tambah Fase Vertikal Baru</h4>
                        <div class="space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <input type="text" 
                                    id="verticalPhaseName"
                                    placeholder="Nama fase baru..."
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                
                                <div class="flex items-center space-x-2">
                                    <label class="text-sm text-gray-600">Warna:</label>
                                    <input type="color" 
                                        id="verticalPhaseColor"
                                        value="#3B82F6"
                                        class="h-10 w-16 border border-gray-300 rounded cursor-pointer"
                                        title="Pilih warna fase">
                                </div>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <input type="number" 
                                    id="verticalPhaseWeight"
                                    placeholder="Bobot (%) *"
                                    class="w-full sm:w-32 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    min="0" max="100" step="0.1">

                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" 
                                        id="verticalPhaseIsGoLive"
                                        class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                                    <span class="text-sm text-gray-700">Go-Live Phase</span>
                                </label>
                                
                                <button onclick="addNewPhaseToList()" 
                                        class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                                    <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Tambah ke List
                                </button>
                            </div>
                            
                            <p class="text-xs text-gray-500">
                                💡 Fase akan ditambahkan ke list di atas. Klik "Simpan Konfigurasi" untuk menyimpan semua perubahan.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                <button onclick="closePhaseConfigModal()"
                        class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Batal
                </button>
                <button onclick="saveAllPhaseChanges()"
                        id="saveConfigBtn"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all shadow-sm">
                    Simpan Konfigurasi
                </button>
            </div>
        </div>
</div>

{{-- ✅ CONFIRMATION MODAL --}}
<div id="confirmDeleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center p-4" aria-labelledby="confirm-title" role="dialog" aria-modal="true">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
            <!-- Header -->
            <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900" id="confirm-title">Konfirmasi Hapus Fase</h3>
                <button onclick="closeConfirmDeleteModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-5">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm text-gray-700 mb-2">
                            Apakah Anda yakin ingin menghapus fase ini dari proyek?
                        </p>
                        <div id="confirmDeletePhaseName" class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-center space-x-2">
                                <div id="confirmDeletePhaseColor" class="w-3 h-3 rounded flex-shrink-0"></div>
                                <span class="font-medium text-sm" id="confirmDeletePhaseText"></span>
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-xs text-yellow-800 flex items-start">
                                <svg class="w-4 h-4 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span>Fase akan ditandai untuk dihapus. Klik "Simpan Konfigurasi" untuk menerapkan perubahan.</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
                <button onclick="closeConfirmDeleteModal()"
                        class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Batal
                </button>
                <button onclick="confirmDeletePhase()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Ya, Hapus Fase
                </button>
            </div>
        </div>
</div>

{{-- ============================================================================ --}}
{{-- STYLES --}}
{{-- ============================================================================ --}}
<style>

.phase-item.bg-red-50 {
    position: relative;
}

.phase-item.bg-red-50::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 10px,
        rgba(239, 68, 68, 0.05) 10px,
        rgba(239, 68, 68, 0.05) 20px
    );
    pointer-events: none;
    border-radius: 0.5rem;
}

/* Smooth transitions for modal */
#confirmDeleteModal {
    transition: opacity 0.3s ease-in-out;
}

#confirmDeleteModal .inline-block {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Mobile improvements for confirmation modal */
@media (max-width: 640px) {
    #confirmDeleteModal .inline-block {
        margin: 1rem;
    }
}

/* Sortable ghost styling */
.sortable-ghost {
    opacity: 0.4;
    background: #f3f4f6;
}

/* Modified phase item styling */
.phase-item.ring-2 {
    animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse-ring {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.8;
    }
}

/* Color picker styling */
input[type="color"] {
    cursor: pointer;
}

input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 0;
}

input[type="color"]::-webkit-color-swatch {
    border: none;
    border-radius: 4px;
}

/* Mobile improvements */
@media (max-width: 640px) {
    #phaseConfigModal .inline-block {
        margin: 1rem;
        max-height: calc(100vh - 2rem);
    }
    
    input, select, textarea {
        font-size: 16px; /* Prevent zoom on iOS */
    }
}
</style>

{{-- ============================================================================ --}}
{{-- JAVASCRIPT --}}
{{-- ============================================================================ --}}
<script>
// ============================================================================
// PHASE CONFIGURATION - BATCH UPDATE SYSTEM (FIXED)
// ============================================================================

// Track all changes (will be saved when user clicks "Simpan Konfigurasi")
window.phaseChanges = {
    modified: new Set(),      // Phase IDs that have been modified
    toCreate: [],             // New phases to create
    toDelete: new Set(),      // Phase IDs to delete
    weightChanges: {},        // { phaseId: newWeight }
    visibilityChanges: {},    // { phaseId: newVisibility }
    goLiveChanges: {},        // { phaseId: isGoLive }
    orderChanges: []          // New order sequence
};

/**
 * ✅ Helper: Check if ID is a new temporary ID
 */
function isNewPhaseId(id) {
    return typeof id === 'string' && id.startsWith('new_');
}

/**
 * ✅ Helper: Convert ID to string for comparison
 */
function normalizePhaseId(id) {
    return String(id);
}

/**
 * ✅ Initialize Sortable for drag & drop reordering
 */
function initializeSortable() {
    const verticalList = document.getElementById('verticalPhasesList');
    if (verticalList && typeof Sortable !== 'undefined') {
        new Sortable(verticalList, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                console.log('🔄 Phase order changed');
                markOrderAsChanged();
                calculateTotalWeights();
            }
        });
    }
}

/**
 * ✅ FIXED: Calculate and display total weights (exclude deleted items)
 */
function calculateTotalWeights() {
    let verticalTotal = 0;
    document.querySelectorAll('#verticalPhasesList .phase-item').forEach(item => {
        const phaseId = item.dataset.phaseId;
        
        // Skip deleted phases
        if (window.phaseChanges.toDelete.has(normalizePhaseId(phaseId))) {
            return;
        }
        
        const weightInput = item.querySelector('.phase-weight-input');
        if (weightInput) {
            verticalTotal += parseFloat(weightInput.value) || 0;
        }
    });
    
    const verticalTotalElement = document.getElementById('verticalTotalWeight');
    const warningElement = document.getElementById('weightWarning');
    const saveBtn = document.getElementById('saveConfigBtn');
    
    if (verticalTotalElement) {
        verticalTotalElement.textContent = verticalTotal.toFixed(1) + '%';
        
        const isValid = Math.abs(verticalTotal - 100) < 0.1;
        
        // Update color
        verticalTotalElement.classList.remove('text-green-600', 'text-red-600', 'text-gray-700');
        verticalTotalElement.classList.add(isValid ? 'text-green-600' : 'text-red-600');
        
        // Show/hide warning
        if (warningElement) {
            if (isValid) {
                warningElement.classList.add('hidden');
            } else {
                warningElement.classList.remove('hidden');
            }
        }
        
        // Disable save button if not valid
        if (saveBtn) {
            if (isValid) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                saveBtn.disabled = true;
                saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
    }
}

/**
 * ✅ FIXED: Mark phase as modified when weight is changed
 */
window.markPhaseAsModified = function(phaseId) {
    console.log('📝 Phase modified:', phaseId);
    
    phaseId = normalizePhaseId(phaseId);
    
    const phaseItem = document.querySelector(`.phase-item[data-phase-id="${phaseId}"]`);
    if (!phaseItem) return;
    
    const weightInput = phaseItem.querySelector('.phase-weight-input');
    const newWeight = parseFloat(weightInput.value) || 0;
    const originalWeight = parseFloat(phaseItem.dataset.originalWeight);
    
    // Skip if this is a new phase (will be handled in toCreate)
    if (isNewPhaseId(phaseId)) {
        const newPhase = window.phaseChanges.toCreate.find(p => p.tempId === phaseId);
        if (newPhase) {
            newPhase.weight = newWeight;
        }
        calculateTotalWeights();
        return;
    }
    
    // Track change for existing phases
    window.phaseChanges.modified.add(phaseId);
    window.phaseChanges.weightChanges[phaseId] = newWeight;
    
    // Visual feedback
    if (Math.abs(newWeight - originalWeight) > 0.01) {
        phaseItem.classList.add('ring-2', 'ring-yellow-400');
    } else {
        phaseItem.classList.remove('ring-2', 'ring-yellow-400');
        window.phaseChanges.modified.delete(phaseId);
        delete window.phaseChanges.weightChanges[phaseId];
    }
    
    calculateTotalWeights();
};

/**
 * ✅ Toggle Go-Live phase
 */
window.toggleGoLivePhase = function(phaseId) {
    console.log('🎯 Toggle Go-Live phase:', phaseId);
    
    phaseId = normalizePhaseId(phaseId);
    
    const phaseItem = document.querySelector(`.phase-item[data-phase-id="${phaseId}"]`);
    const btn = phaseItem.querySelector('.golive-btn');
    
    const currentlyGoLive = btn.classList.contains('text-green-600');
    const newGoLiveStatus = !currentlyGoLive;
    
    // Toggle UI
    if (newGoLiveStatus) {
        btn.classList.remove('text-gray-400');
        btn.classList.add('text-green-600');
        btn.title = 'Go-Live Phase';
    } else {
        btn.classList.remove('text-green-600');
        btn.classList.add('text-gray-400');
        btn.title = 'Mark as Go-Live Phase';
    }
    
    // Skip new phases
    if (isNewPhaseId(phaseId)) {
        return;
    }
    
    // Track change
    window.phaseChanges.modified.add(phaseId);
    window.phaseChanges.goLiveChanges[phaseId] = newGoLiveStatus;
    
    // Visual feedback
    phaseItem.classList.add('ring-2', 'ring-yellow-400');
};

/**
 * ✅ FIXED: Toggle phase visibility (UI only)
 */
window.togglePhaseVisibilityUI = function(phaseId) {
    console.log('👁️ Toggle visibility UI:', phaseId);
    
    phaseId = normalizePhaseId(phaseId);
    
    const phaseItem = document.querySelector(`.phase-item[data-phase-id="${phaseId}"]`);
    const btn = phaseItem.querySelector('.visibility-btn');
    const eyeOpen = btn.querySelector('.eye-open');
    const eyeClosed = btn.querySelector('.eye-closed');
    
    const currentlyVisible = !eyeOpen.classList.contains('hidden');
    const newVisibility = !currentlyVisible;
    
    // Toggle UI
    if (newVisibility) {
        eyeOpen.classList.remove('hidden');
        eyeClosed.classList.add('hidden');
        btn.classList.remove('text-gray-400');
        btn.classList.add('text-blue-600');
    } else {
        eyeOpen.classList.add('hidden');
        eyeClosed.classList.remove('hidden');
        btn.classList.remove('text-blue-600');
        btn.classList.add('text-gray-400');
    }
    
    // Skip new phases
    if (isNewPhaseId(phaseId)) {
        return;
    }
    
    // Track change
    window.phaseChanges.modified.add(phaseId);
    window.phaseChanges.visibilityChanges[phaseId] = newVisibility;
    
    // Visual feedback
    phaseItem.classList.add('ring-2', 'ring-yellow-400');
};

/**
 * ✅ FIXED: Remove phase (mark for deletion)
 */
let pendingDeletePhaseId = null;

/**
 * ✅ Show confirmation modal before deleting phase
 */
window.removePhaseUI = function(phaseId) {
    phaseId = normalizePhaseId(phaseId);
    
    const phaseItem = document.querySelector(`.phase-item[data-phase-id="${phaseId}"]`);
    if (!phaseItem) return;
    
    // Get phase details
    const phaseName = phaseItem.querySelector('.font-medium').textContent.trim();
    const phaseColorDiv = phaseItem.querySelector('.w-3.h-3.rounded');
    const phaseColor = phaseColorDiv ? phaseColorDiv.style.backgroundColor : '#3B82F6';
    
    // Store pending delete ID
    pendingDeletePhaseId = phaseId;
    
    // Update modal content
    document.getElementById('confirmDeletePhaseText').textContent = phaseName;
    document.getElementById('confirmDeletePhaseColor').style.backgroundColor = phaseColor;
    
    // Show modal
    document.getElementById('confirmDeleteModal').classList.remove('hidden');
};

/**
 * ✅ Close confirmation modal
 */
window.closeConfirmDeleteModal = function() {
    document.getElementById('confirmDeleteModal').classList.add('hidden');
    pendingDeletePhaseId = null;
};

/**
 * ✅ Confirm and execute phase deletion
 */
window.confirmDeletePhase = function() {
    if (!pendingDeletePhaseId) return;
    
    console.log('🗑️ Mark phase for deletion:', pendingDeletePhaseId);
    
    const phaseId = pendingDeletePhaseId;
    const phaseItem = document.querySelector(`.phase-item[data-phase-id="${phaseId}"]`);
    
    if (phaseItem) {
        // If it's a new phase, just remove from toCreate and DOM
        if (isNewPhaseId(phaseId)) {
            window.phaseChanges.toCreate = window.phaseChanges.toCreate.filter(p => p.tempId !== phaseId);
            phaseItem.remove();
            
            if (typeof showNotification === 'function') {
                showNotification('New phase removed from list', 'success');
            }
        } else {
            // Mark existing phase for deletion
            window.phaseChanges.toDelete.add(phaseId);
            
            // Visual feedback - marked for deletion
            phaseItem.style.opacity = '0.5';
            phaseItem.style.textDecoration = 'line-through';
            phaseItem.classList.add('bg-red-50', 'ring-2', 'ring-red-400');
            
            // Disable all inputs and buttons
            const weightInput = phaseItem.querySelector('.phase-weight-input');
            if (weightInput) weightInput.disabled = true;
            
            const buttons = phaseItem.querySelectorAll('button');
            buttons.forEach(btn => btn.disabled = true);
            
            // Add deleted badge
            const badgeContainer = phaseItem.querySelector('.flex.items-center.space-x-3');
            if (badgeContainer && !badgeContainer.querySelector('.deleted-badge')) {
                const deletedBadge = document.createElement('span');
                deletedBadge.className = 'deleted-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 flex-shrink-0';
                deletedBadge.innerHTML = `
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Akan Dihapus
                `;
                badgeContainer.appendChild(deletedBadge);
            }
            
            if (typeof showNotification === 'function') {
                showNotification('Phase marked for deletion. Click "Save Configuration" to apply.', 'warning');
            }
        }
    }
    
    calculateTotalWeights();
    closeConfirmDeleteModal();
};

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const confirmModal = document.getElementById('confirmDeleteModal');
        if (confirmModal && !confirmModal.classList.contains('hidden')) {
            closeConfirmDeleteModal();
        }
    }
});

/**
 * ✅ Mark order as changed
 */
function markOrderAsChanged() {
    console.log('🔄 Marking order as changed');
    const phases = [];
    
    document.querySelectorAll('#verticalPhasesList .phase-item').forEach((item, index) => {
        const phaseId = normalizePhaseId(item.dataset.phaseId);
        
        // Skip new phases and deleted phases
        if (!isNewPhaseId(phaseId) && !window.phaseChanges.toDelete.has(phaseId)) {
            phases.push({
                id: phaseId,
                sequence: index
            });
        }
    });
    
    window.phaseChanges.orderChanges = phases;
}

/**
 * ✅ Add new phase to the list (not saved yet)
 */
window.addNewPhaseToList = function() {
    const nameInput = document.getElementById('verticalPhaseName');
    const weightInput = document.getElementById('verticalPhaseWeight');
    const colorInput = document.getElementById('verticalPhaseColor');
    const goLiveCheckbox = document.getElementById('verticalPhaseIsGoLive');
    
    const name = nameInput.value.trim();
    const weight = parseFloat(weightInput.value) || 0;
    const color = colorInput.value;
    const isGoLive = goLiveCheckbox.checked;
    
    // Validation
    if (!name) {
        if (typeof showNotification === 'function') {
            showNotification('Phase name cannot be empty', 'error');
        }
        nameInput.focus();
        return;
    }
    
    if (weight <= 0 || weight > 100) {
        if (typeof showNotification === 'function') {
            showNotification('Weight must be between 0.1 and 100', 'error');
        }
        weightInput.focus();
        return;
    }
    
    console.log('➕ Adding new phase to list:', { name, weight, color, isGoLive });
    
    // Generate temporary ID
    const tempId = 'new_' + Date.now();
    
    // Add to pending creates
    window.phaseChanges.toCreate.push({
        tempId: tempId,
        name: name,
        weight: weight,
        color: color,
        orientation: 'vertical',
        is_golive_phase: isGoLive
    });

    const goLiveBadge = isGoLive ? `
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 flex-shrink-0">
            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
            </svg>
            Go-Live
        </span>
    ` : '';
    
    // Add to UI
    const phasesList = document.getElementById('verticalPhasesList');
    const newPhaseHtml = `
        <div class="phase-item bg-white p-3 rounded border border-green-200 ring-2 ring-green-400" 
             data-phase-id="${tempId}"
             data-original-weight="0"
             data-original-visible="1"
             data-orientation="vertical">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center space-x-3 flex-1 min-w-0">
                    <span class="drag-handle cursor-move flex-shrink-0">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                        </svg>
                    </span>
                    <div class="w-3 h-3 rounded flex-shrink-0" style="background-color: ${color}"></div>
                    <span class="font-medium text-sm truncate">${escapeHtml(name)}</span>
                    ${goLiveBadge}
                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded flex-shrink-0">Baru</span>
                </div>
                <div class="flex items-center space-x-2 flex-shrink-0">
                    <input type="number" 
                           value="${weight}"
                           oninput="markPhaseAsModified('${tempId}')"
                           class="phase-weight-input w-20 px-2 py-1 text-sm border border-gray-300 rounded"
                           min="0" max="100" step="0.1"
                           data-phase-id="${tempId}">
                    <span class="text-sm text-gray-500">%</span>
                    
                    <button onclick="removePhaseUI('${tempId}')" 
                            class="p-1 text-red-600 hover:text-red-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    phasesList.insertAdjacentHTML('beforeend', newPhaseHtml);
    
    // Clear inputs
    nameInput.value = '';
    weightInput.value = '';
    colorInput.value = '#3B82F6';
    goLiveCheckbox.checked = false;
    
    // Recalculate
    calculateTotalWeights();
    
    if (typeof showNotification === 'function') {
        showNotification('Phase added to list. Click "Save Configuration" to save.', 'success');
    }
};

/**
 * ✅ FIXED: Save all changes - Batch update
 */
window.saveAllPhaseChanges = async function() {
    console.log('💾 Saving all phase changes...', window.phaseChanges);
    
    // Validate total weight (exclude deleted)
    let totalWeight = 0;
    document.querySelectorAll('#verticalPhasesList .phase-item').forEach(item => {
        const phaseId = normalizePhaseId(item.dataset.phaseId);
        if (!window.phaseChanges.toDelete.has(phaseId)) {
            const weightInput = item.querySelector('.phase-weight-input');
            totalWeight += parseFloat(weightInput.value) || 0;
        }
    });
    
    if (Math.abs(totalWeight - 100) >= 0.1) {
        if (typeof showNotification === 'function') {
            showNotification('Total weight must be 100% before saving', 'error');
        }
        return;
    }
    
    // Show loading
    const saveBtn = document.getElementById('saveConfigBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<svg class="animate-spin h-5 w-5 inline-block" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Menyimpan...';
    saveBtn.disabled = true;
    
    try {
        // 1. Delete phases
        for (const phaseId of window.phaseChanges.toDelete) {
            if (isNewPhaseId(phaseId)) continue;
            
            console.log('🗑️ Deleting phase:', phaseId);
            await axios.delete(`/planning/${projectId}/phases/${phaseId}`, {
                data: { _token: '{{ csrf_token() }}' }
            });
        }
        
        // 2. Create new phases
        for (const newPhase of window.phaseChanges.toCreate) {
            console.log('➕ Creating new phase:', newPhase.name);
            
            const createResponse = await axios.post('{{ route("planning.phases.create", $project) }}', {
                name: newPhase.name,
                orientation: newPhase.orientation,
                color: newPhase.color,
                _token: '{{ csrf_token() }}'
            });
            
            if (createResponse.data.success) {
                await axios.post(`/planning/${projectId}/phases/add`, {
                    phase_id: createResponse.data.phase.id,
                    weight: newPhase.weight,
                    orientation: newPhase.orientation,
                    is_golive_phase: newPhase.is_golive_phase,
                    _token: '{{ csrf_token() }}'
                });
            }
        }
        
        // 3. Update weights, visibility, and go-live for modified phases
        for (const phaseId of window.phaseChanges.modified) {
            if (isNewPhaseId(phaseId)) continue;
            if (window.phaseChanges.toDelete.has(phaseId)) continue;
            
            console.log('✏️ Updating phase:', phaseId);
            
            const updates = {};
            
            if (window.phaseChanges.weightChanges[phaseId] !== undefined) {
                updates.weight = window.phaseChanges.weightChanges[phaseId];
            }

            if (window.phaseChanges.goLiveChanges[phaseId] !== undefined) {
                updates.is_golive_phase = window.phaseChanges.goLiveChanges[phaseId];
            }
            
            if (Object.keys(updates).length > 0) {
                await axios.put(`/planning/${projectId}/phases/${phaseId}`, {
                    ...updates,
                    _token: '{{ csrf_token() }}'
                });
            }
            
            // Update visibility separately if changed
            if (window.phaseChanges.visibilityChanges[phaseId] !== undefined) {
                await axios.post(`/planning/${projectId}/phases/${phaseId}/toggle`, {
                    _token: '{{ csrf_token() }}'
                });
            }
        }
        
        // 4. Update order if changed
        if (window.phaseChanges.orderChanges.length > 0) {
            console.log('🔄 Updating order:', window.phaseChanges.orderChanges);

            await axios.post(`/planning/${projectId}/phases/reorder`, {
                phases: window.phaseChanges.orderChanges,
                _token: '{{ csrf_token() }}'
            });
        }
        
        // 5. Update project from planning (removed - handled automatically by backend)

        if (typeof showNotification === 'function') {
            showNotification('Phase configuration saved successfully!', 'success');
        }
        
        // Reset changes
        window.phaseChanges = {
            modified: new Set(),
            toCreate: [],
            toDelete: new Set(),
            weightChanges: {},
            visibilityChanges: {},
            goLiveChanges: {},
            orderChanges: []
        };
        
        // Close modal and reload
        setTimeout(() => {
            closePhaseConfigModal();
            location.reload();
        }, 1000);
        
    } catch (error) {
        console.error('❌ Error saving phase configuration:', error);
        if (typeof showNotification === 'function') {
            showNotification(
                '❌ Gagal menyimpan: ' + (error.response?.data?.message || error.message),
                'error'
            );
        }
        
        // Restore button
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
};

/**
 * ✅ Close modal and reset
 */
window.closePhaseConfigModal = function() {
    console.log('❌ Closing phase configuration modal');
    
    const modal = document.getElementById('phaseConfigModal');
    if (modal) {
        // Check if there are unsaved changes
        const hasChanges = window.phaseChanges.toCreate.length > 0 || 
                          window.phaseChanges.toDelete.size > 0 || 
                          window.phaseChanges.modified.size > 0;
        
        if (hasChanges) {
            if (!confirm('Ada perubahan yang belum disimpan. Apakah Anda yakin ingin menutup?')) {
                return;
            }
        }
        
        modal.classList.add('hidden');
        
        // Clear inputs
        const inputs = ['verticalPhaseName', 'verticalPhaseWeight', 'verticalPhaseColor', 'verticalPhaseIsGoLive'];
        inputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                if (el.type === 'checkbox') {
                    el.checked = false;
                } else if (id === 'verticalPhaseColor') {
                    el.value = '#3B82F6';
                } else {
                    el.value = '';
                }
            }
        });
        
        // Reset changes
        window.phaseChanges = {
            modified: new Set(),
            toCreate: [],
            toDelete: new Set(),
            weightChanges: {},
            visibilityChanges: {},
            goLiveChanges: {},
            orderChanges: []
        };
    }
};

/**
 * ✅ Utility: Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * ✅ Initialize phase config modal
 */
function initializePhaseConfigModal() {
    initializeSortable();
    calculateTotalWeights();
    console.log('✅ Phase configuration modal initialized');
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initializePhaseConfigModal);
</script>