{{--
    The optional photo of the inventory / asset modals: a square tile that previews the picture (the form must be
    multipart). The saved photo is put into the tile by invOpen(); the ✕ marks it for removal (`remove_photo` = 1).
    Behaviour: components/modal-helpers.
    Parameters: $label · $hint
--}}
<div data-photo-field class="shrink-0">
    <span class="block text-xs font-semibold text-gray-600 mb-1">{{ $label }}</span>
    <label class="relative block w-28 h-28 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-gray-300 hover:bg-gray-100 cursor-pointer overflow-hidden transition-colors"
        title="{{ $hint }}">
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input class="sr-only" aria-label="{{ $label }}">
        <img data-photo-img src="" alt="" class="hidden absolute inset-0 w-full h-full object-cover">
        <span data-photo-empty class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-gray-400 text-[11px] font-medium">
            <i class="fas fa-camera text-xl"></i> Add photo
        </span>
        <button type="button" data-photo-clear title="Remove photo" aria-label="Remove photo"
            class="hidden absolute top-1 right-1 w-6 h-6 rounded-full bg-black/60 hover:bg-black/80 text-white text-[10px] items-center justify-center">
            <i class="fas fa-xmark"></i>
        </button>
    </label>
    <input type="hidden" name="remove_photo" value="0" data-photo-remove>
    <p data-photo-error class="text-[11px] text-red-600 mt-1 max-w-28"></p>
</div>
