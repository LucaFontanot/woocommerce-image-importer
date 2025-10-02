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
        </div>
        <div class="wii-current-options-container">
            <h4>Upload options</h4>
            <label for="wii-watermark-option">Add watermark:</label>
            <input type="checkbox" id="wii-watermark-option" class="wii-watermark-option">
            <br><br>
            <span>Price:</span>
            <strong><?php echo get_woocommerce_currency_symbol(); ?></strong>
            <input type="number" id="wii-price-input" class="wii-price-input" value="0.00" min="0" step="0.01">
            <br><br>
            <label for="wii-quality-select">Quality:</label>
            <select id="wii-quality-select" class="wii-quality-select">
                <option value="100">100%</option>
                <option value="90">90%</option>
                <option value="80">80%</option>
                <option value="70">70%</option>
                <option value="60">60%</option>
                <option value="50">50%</option>
                <option value="40">40%</option>
                <option value="30" selected>30%</option>
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
                <?php foreach ($tags as $tag): ?>
                    <label style="display:inline-block; margin-right:10px;">
                        <input type="checkbox" value="<?php echo esc_attr($tag['id']); ?>"> <?php echo esc_html($tag['label']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <br>
            <button id="wii-upload-button" class="wii-queue-button-select wii-upload-button" disabled>
                Upload and create
            </button>
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
        const successDiv = document.getElementById('wii-upload-success');
        const errorDiv = document.getElementById('wii-upload-error');
        let queue = [];
        let selectedImage = null;

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
            const uploadBtn = document.getElementById('wii-upload-button');
            if (!selectedImage) {
                currentDiv.innerHTML = '<em>No image selected</em>';
                uploadBtn.disabled = true;
                return;
            }
            currentDiv.innerHTML = '';
            const img = document.createElement('img');
            img.src = selectedImage.url;
            img.classList.add('wii-current-image');
            currentDiv.appendChild(img);
            uploadBtn.disabled = false;
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
        uploadBtn.addEventListener('click', () => {
            if (!selectedImage) return;
            const price = parseFloat(document.getElementById('wii-price-input').value) || 0.00;
            const watermark = document.getElementById('wii-watermark-option').checked;
            const quality = document.getElementById('wii-quality-select').value;
            const category = document.getElementById('wii-category-select').value;
            const tagsDiv = document.getElementById('wii-tags-select');
            const tagCheckboxes = tagsDiv.querySelectorAll('input[type="checkbox"]:checked');
            const tags = Array.from(tagCheckboxes).map(cb => cb.value);

            const formData = new FormData();
            formData.append('action', 'wii_upload_image');
            formData.append('image', selectedImage.file);
            formData.append('watermark', watermark ? '1' : '0');
            formData.append('quality', quality);
            formData.append('category', category);
            formData.append('price', price.toFixed(2));
            formData.append('tags', tags.join(","));
            formData.append('_wpnonce', '<?php echo wp_create_nonce('wii_upload_image_nonce'); ?>');

            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            successDiv.style.display = 'none';
            errorDiv.style.display = 'none';

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        successDiv.style.display = 'block';
                        // Remove from queue
                        queue = queue.filter(item => item !== selectedImage);
                        nextInQueue();
                        renderQueue();
                    } else {
                        console.error('Upload failed:', data);
                        errorDiv.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Upload error:', err);
                    errorDiv.style.display = 'block';
                })
                .finally(() => {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload and create';
                });
        })

        renderQueue();
    })();
</script>
<style>
    .wii-queue-grid {
        margin-top: 20px;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        max-height: 200px;
        overflow-y: auto;
        background-color: #fff;
        border: 1px solid #ccc;
        padding: 10px;
    }

    .wii-dropzone {
        border: 2px dashed #aaa;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        background: #fafafa;
    }

    .wii-upload-container {
        max-width: 800px;
        width: 90vw;
        margin: 30px auto;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .wii-queue-grid-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        margin-bottom: 0;
    }

    .wii-queue-image {
        max-width: 120px;
        max-height: 120px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .wii-queue-button-select {
        padding: 6px 12px;
        background-color: #0073aa;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin: 1px;
    }

    .wii-queue-button-remove {
        padding: 6px 12px;
        background-color: #d63638;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin: 1px;
    }

    .wii-clear {
        width: 100%;
    }

    .wii-current-container {
        margin-top: 20px;
        min-height: 50px;
        border: 1px solid #ccc;
        padding: 10px;
        background-color: #e6e6e6;
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 10px;
    }

    .wii-current-image {
        max-width: 300px;
        max-height: 300px;
    }

    .wii-upload-button{
        background-color: #28a745;
        width: 100%;
    }

    .wii-upload-button:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
    }

    .wii-upload-success {
        display: none;
        margin-top: 10px;
        padding: 10px;
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        border-radius: 4px;
    }

    .wii-upload-error {
        display: none;
        margin-top: 10px;
        padding: 10px;
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
    }
</style>
