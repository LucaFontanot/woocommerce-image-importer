<?php

if (!defined('ABSPATH')) {
    exit;
}

$product_categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
]);

// Ottieni tutti i tag prodotto
$product_tags = get_terms([
        'taxonomy' => 'product_tag',
        'hide_empty' => false,
]);

$categories = [];
foreach ($product_categories as $cat) {
    $categories[] = [
            'id' => $cat->term_id,
            'label' => $cat->name,
    ];
}

$tags = [];
foreach ($product_tags as $tag) {
    $tags[] = [
            'id' => $tag->term_id,
            'label' => $tag->name,
    ];
}
?>
<div class="wii-upload-container">
    <h2>Import Woocommerce images</h2>
    <div id="wii-dropzone" class="wii-dropzone">
        Drop the images here or <input type="file" id="wii-file-input" accept="image/*" multiple
                                       style="display:inline;">
    </div>
    <div style="text-align: center">
        <input type="checkbox" id="wii-autofind-checkbox" checked>
        <label for="wii-autofind-checkbox">Auto select tags and category based on name file</label>
    </div>
    <div id="wii-queue" class="wii-queue-grid"></div>
    <button id="wii-clear" class="wii-queue-button-remove wii-clear">
        Clear Queue
    </button>
    <div id="wii-upload-success" class="wii-upload-success">
        <p>
            Image uploaded successfully!
        </p>
    </div>
    <div id="wii-upload-error" class="wii-upload-error">
        <p>
            Error uploading image!
        </p>
    </div>
    <div class="wii-current-container">
        <div class="wii-current-image-container">
            <strong>Current Image</strong>
            <div id="wii-current-image" style="margin-top:10px;">
                <em>No image selected</em>
            </div>
            <br>
            <label for="wii-current-filename">Product name:</label>
            <input type="text" id="wii-current-filename" class="wii-current-filename" placeholder="Product name">
        </div>
        <div class="wii-current-options-container">
            <h4>Image options</h4>
            <label for="wii-watermark-option">Add watermark:</label>
            <input type="checkbox" id="wii-watermark-option" class="wii-watermark-option">
            <input type="file" id="wii-watermark-file" accept="image/*" class="wii-watermark-file">
            <br><hr>
            <h4>Product options</h4>
            <span>Price:</span>
            <strong><?php echo get_woocommerce_currency_symbol(); ?></strong>
            <input type="number" id="wii-price-input" class="wii-price-input" value="30.00" min="0" step="0.01">
            <br><br>
            <label for="wii-quality-select">Quality:</label>
            <select id="wii-quality-select" class="wii-quality-select">
                <option value="100">100%</option>
                <option value="90">90%</option>
                <option value="80">80%</option>
                <option value="70" selected>70%</option>
                <option value="60">60%</option>
                <option value="50">50%</option>
                <option value="40">40%</option>
                <option value="30">30%</option>
                <option value="20">20%</option>
                <option value="10">10%</option>
            </select>
            <br><br>
            <label for="wii-category-select">Category:</label>
            <select id="wii-category-select" class="wii-category-select">
                <option value="0">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat['id']); ?>"><?php echo esc_html($cat['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <br><br>
            <div id="wii-tags-select" class="wii-category-select">
                <strong>Tags:</strong><br>
                <select id="wii-tags-autocomplete" multiple style="width: 100%;">
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo esc_attr($tag['id']); ?>"><?php echo esc_html($tag['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <br>
        </div>
        <div class="wii-current-options-container">
            <button id="wii-upload-button" class="wii-queue-button-select wii-upload-button" disabled>
                Upload and create
            </button>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; align-items: center;">
                <button id="wii-all-button" class="wii-queue-button-select wii-all-button" disabled>
                    Upload all
                </button>
                <button id="wii-cancel-button" class="wii-queue-button-select wii-cancel-button" disabled>
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        const dropzone = document.getElementById('wii-dropzone');
        const fileInput = document.getElementById('wii-file-input');
        const queueDiv = document.getElementById('wii-queue');
        const clearBtn = document.getElementById('wii-clear');
        const uploadBtn = document.getElementById('wii-upload-button');
        const uploadAllBtn = document.getElementById('wii-all-button');
        const autoFindCheckbox = document.getElementById('wii-autofind-checkbox');
        const cancelBtn = document.getElementById('wii-cancel-button');
        const labelInput = document.getElementById('wii-current-filename');
        const successDiv = document.getElementById('wii-upload-success');
        const errorDiv = document.getElementById('wii-upload-error');
        const categorySelect = document.getElementById('wii-category-select');
        const watermarkFileInput = document.getElementById('wii-watermark-file');
        let tagsSelect = null;
        let queue = [];
        let selectedImage = null;
        let isBulkUploading = false; // Stato per upload multiplo
        let bulkUploadIndex = 0; // Indice per l'upload multiplo

        function findTagsInName(filename) {
            const tags = <?php echo json_encode($tags); ?>;
            const foundTags = [];
            const lowerFilename = filename.toLowerCase();
            tags.forEach(tag => {
                if (lowerFilename.includes(tag.label.toLowerCase())) {
                    foundTags.push(tag.id);
                }
            });
            return foundTags;
        }

        function findCategoryInName(filename) {
            const categories = <?php echo json_encode($categories); ?>;
            const lowerFilename = filename.toLowerCase();
            for (let i = 0; i < categories.length; i++) {
                if (lowerFilename.includes(categories[i].label.toLowerCase())) {
                    return categories[i].id;
                }
            }
            return 0;
        }

        function renderQueue() {
            queueDiv.innerHTML = '';
            if (queue.length === 0) {
                queueDiv.innerHTML = '<em>No images in queue</em>';
                return;
            }
            queue.forEach((item, idx) => {
                const wrapper = document.createElement('div');
                wrapper.classList.add('wii-queue-grid-wrapper');
                const img = document.createElement('img');
                img.src = item.url;
                img.classList.add('wii-queue-image');
                const buttonsDiv = document.createElement('div');
                const selectBtn = document.createElement('button');
                selectBtn.classList.add('wii-queue-button-select');
                selectBtn.textContent = 'Select';
                selectBtn.addEventListener('click', function () {
                    selectedImage = item;
                    renderSelection();
                })
                const removeBtn = document.createElement('button');
                removeBtn.classList.add('wii-queue-button-remove');
                removeBtn.textContent = 'Remove';
                removeBtn.addEventListener('click', function () {
                    queue.splice(idx, 1);
                    if (selectedImage === item) {
                        nextInQueue();
                    }
                    renderQueue();
                })
                wrapper.appendChild(img);
                buttonsDiv.appendChild(selectBtn);
                buttonsDiv.appendChild(removeBtn);
                wrapper.appendChild(buttonsDiv);
                queueDiv.appendChild(wrapper);
            });
        }

        function renderSelection() {
            const currentDiv = document.getElementById('wii-current-image');
            if (!selectedImage) {
                currentDiv.innerHTML = '<em>No image selected</em>';
                uploadBtn.disabled = true;
                uploadAllBtn.disabled = true;
                return;
            }
            currentDiv.innerHTML = '';
            const img = document.createElement('img');
            img.src = selectedImage.url;
            img.classList.add('wii-current-image');
            currentDiv.appendChild(img);
            if (autoFindCheckbox.checked) {
                const tags = findTagsInName(selectedImage.file.name);
                const category = findCategoryInName(selectedImage.file.name);
                tagsSelect.val(tags).trigger('change');
                categorySelect.value = category;
            }
            labelInput.value = selectedImage.file.name.replace(/\.[^/.]+$/, "");
            uploadBtn.disabled = false;
            uploadAllBtn.disabled = false;
        }

        function nextInQueue(){
            if(queue.length === 0){
                selectedImage = null;
            }else{
                selectedImage = queue[0];
            }
            renderSelection();
        }

        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (!file.type.startsWith('image/')) return;
                const url = URL.createObjectURL(file);
                queue.push({file, url});
            });
            renderQueue();
            nextInQueue()
        }

        dropzone.addEventListener('dragover', e => {
            e.preventDefault();
            dropzone.style.background = '#e0e0e0';
        });
        dropzone.addEventListener('dragleave', e => {
            e.preventDefault();
            dropzone.style.background = '#fafafa';
        });
        dropzone.addEventListener('drop', e => {
            e.preventDefault();
            dropzone.style.background = '#fafafa';
            handleFiles(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', e => {
            handleFiles(e.target.files);
            fileInput.value = '';
        });
        clearBtn.addEventListener('click', () => {
            queue = [];
            renderQueue();
            nextInQueue();
        });
        // Inizializza Select2 per i tag
        jQuery(document).ready(function($) {
            $('#wii-tags-autocomplete').select2({
                placeholder: 'Seleziona i tag',
                allowClear: true,
                width: 'resolve'
            });
            tagsSelect = $('#wii-tags-autocomplete');
        });

        // Funzione per upload di una singola immagine (riutilizzabile)
        function uploadSingleImage(item, onSuccess, onError, onFinally) {
            const price = parseFloat(document.getElementById('wii-price-input').value) || 0.00;
            const watermark = document.getElementById('wii-watermark-option').checked;
            const quality = document.getElementById('wii-quality-select').value;
            const label = labelInput.value.trim();
            const category = categorySelect.value;
            const tags = tagsSelect.val() || [];
            const watermarkFile = watermarkFileInput.files[0] || null;

            if (watermark && !watermarkFile) {
                if (onError) onError('Please select a watermark file!');
                if (onFinally) onFinally();
                return;
            }

            const formData = new FormData();
            formData.append('action', 'wii_upload_image');
            formData.append('watermark', watermark ? '1' : '0');
            formData.append('quality', quality);
            formData.append('category', category);
            formData.append('price', price.toFixed(2));
            formData.append('tags', tags.join(","));
            formData.append('label', label);
            formData.append('watermark_file', watermarkFile);
            formData.append('image', item.file);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (onSuccess) onSuccess();
                    } else {
                        let errorMessage = 'Error uploading image!';
                        if (data && data.data && data.data.message){
                            errorMessage = data.data.message;
                        }
                        if (onError) onError(errorMessage);
                    }
                })
                .catch(err => {
                    let errorMessage = 'Error uploading image!';
                    if (err && err.response && err.response.data && err.response.data.message){
                        errorMessage = err.response.data.message;
                    }
                    if (onError) onError(errorMessage);
                })
                .finally(() => {
                    if (onFinally) onFinally();
                });
        }

        // Logica per Upload All
        function uploadAllImages() {
            if (queue.length === 0) return;
            isBulkUploading = true;
            bulkUploadIndex = 0;
            uploadAllBtn.disabled = true;
            uploadBtn.disabled = true;
            cancelBtn.disabled = false;
            successDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            processNextBulkUpload();
        }

        function processNextBulkUpload() {
            if (!isBulkUploading) return;
            if (bulkUploadIndex >= queue.length) {
                isBulkUploading = false;
                uploadAllBtn.disabled = false;
                uploadBtn.disabled = false;
                cancelBtn.disabled = true;
                nextInQueue();
                renderQueue();
                successDiv.style.display = 'block';
                errorDiv.style.display = 'none';
                return;
            }
            // Seleziona l'immagine corrente
            selectedImage = queue[bulkUploadIndex];
            renderSelection();
            uploadSingleImage(selectedImage, function() {
                // Successo: rimuovi dalla coda
                queue.splice(bulkUploadIndex, 1);
                // Non incrementare bulkUploadIndex perché la coda si accorcia
                processNextBulkUpload();
            }, function(errorMessage) {
                // Errore: mostra errore e interrompi
                isBulkUploading = false;
                uploadAllBtn.disabled = false;
                uploadBtn.disabled = false;
                cancelBtn.disabled = true;
                errorDiv.style.display = 'block';
                errorDiv.querySelector('p').textContent = errorMessage;
            }, function() {
                // Niente da fare qui, gestito sopra
            });
        }

        // Logica per Cancel
        cancelBtn.addEventListener('click', function() {
            isBulkUploading = false;
            uploadAllBtn.disabled = false;
            uploadBtn.disabled = false;
            cancelBtn.disabled = true;
        });

        // Bottone Upload All
        uploadAllBtn.addEventListener('click', function() {
            if (isBulkUploading) return;
            uploadAllImages();
        });

        uploadBtn.addEventListener('click', () => {
            if (!selectedImage) return;
            uploadBtn.disabled = true;
            uploadAllBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            successDiv.style.display = 'none';
            errorDiv.style.display = 'none';
            uploadSingleImage(selectedImage, function() {
                successDiv.style.display = 'block';
                queue = queue.filter(item => item !== selectedImage);
                nextInQueue();
                renderQueue();
            }, function(errorMessage) {
                errorDiv.style.display = 'block';
                errorDiv.querySelector('p').textContent = errorMessage;
            }, function() {
                uploadBtn.disabled = false;
                uploadAllBtn.disabled = false;
                uploadBtn.textContent = 'Upload and create';
            });
        })

        renderQueue();
    })();
</script>
<style>

</style>
