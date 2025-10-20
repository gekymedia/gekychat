{{-- resources/views/chat/shared/image_modal.blade.php --}}
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-0">
        <img id="modalImage" src="" alt="Full size image" class="img-fluid" style="max-height: 80vh;">
      </div>
      <div class="modal-footer border-0 justify-content-center">
        <a href="#" id="downloadImage" class="btn btn-outline-light btn-sm" download>
          <i class="bi bi-download me-1"></i> Download
        </a>
        <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1"></i> Close
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const imageModal = document.getElementById('imageModal');
  if (imageModal) {
    imageModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const imageSrc = button.getAttribute('data-image-src');
      const modalImage = document.getElementById('modalImage');
      const downloadLink = document.getElementById('downloadImage');
      
      modalImage.src = imageSrc;
      downloadLink.href = imageSrc;
      
      // Set download filename if available
      const fileName = button.getAttribute('data-file-name');
      if (fileName) {
        downloadLink.download = fileName;
      }
    });
  }
});
</script>