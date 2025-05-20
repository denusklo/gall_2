<!-- resources/js/components/Gallery/CategorySelector.vue -->
<template>
    <div class="category-selector">
        <div class="category-list">
            <div class="category-item" :class="{ active: !activeCategory }" @click="selectCategory(null)">
                <span class="category-name">All Images</span>
                <span class="category-count">{{ totalImages }}</span>
            </div>

            <div v-for="category in categories" :key="category.id" class="category-item"
                :class="{ active: activeCategory === category.id }" @click="selectCategory(category.id)">
                <span class="category-name">{{ category.name }}</span>
                <span class="category-count">{{ category.galleries_count }}</span>
            </div>
        </div>

        <div class="category-actions">
            <button @click="showAddCategory = true" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-plus"></i> Add Category
            </button>
            <button v-if="activeCategory" @click="showEditCategory = true" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-edit"></i> Edit
            </button>
        </div>

        <!-- Add Category Modal -->
        <div v-if="showAddCategory" class="category-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add New Category</h3>
                    <button @click="showAddCategory = false" class="close-btn">&times;</button>
                </div>

                <div class="modal-body">
                    <form @submit.prevent="createCategory">
                        <div class="form-group">
                            <label for="category-name">Category Name</label>
                            <input type="text" id="category-name" v-model="newCategory.name" class="form-control"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="category-description">Description (optional)</label>
                            <textarea id="category-description" v-model="newCategory.description" class="form-control"
                                rows="3"></textarea>
                        </div>

                        <div class="modal-footer">
                            <button type="button" @click="showAddCategory = false" class="btn btn-secondary">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" :disabled="!newCategory.name">
                                Create Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Category Modal -->
        <div v-if="showEditCategory" class="category-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Category</h3>
                    <button @click="showEditCategory = false" class="close-btn">&times;</button>
                </div>

                <div class="modal-body">
                    <form @submit.prevent="updateCategory">
                        <div class="form-group">
                            <label for="edit-category-name">Category Name</label>
                            <input type="text" id="edit-category-name" v-model="editCategoryData.name"
                                class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="edit-category-description">Description (optional)</label>
                            <textarea id="edit-category-description" v-model="editCategoryData.description"
                                class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group danger-zone">
                            <h4>Danger Zone</h4>
                            <p>Deleting a category cannot be undone.</p>
                            <button type="button" @click="confirmDeleteCategory" class="btn btn-danger"
                                :disabled="currentCategory && currentCategory.galleries_count > 0">
                                Delete Category
                            </button>
                            <small v-if="currentCategory && currentCategory.galleries_count > 0" class="text-danger">
                                Cannot delete category with images. Move or delete images first.
                            </small>
                        </div>

                        <div class="modal-footer">
                            <button type="button" @click="showEditCategory = false" class="btn btn-secondary">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" :disabled="!editCategoryData.name">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useCategoryStore } from '../../stores/category';
import { useGalleryStore } from '../../stores/gallery';

const emit = defineEmits(['category-changed']);

const categoryStore = useCategoryStore();
const galleryStore = useGalleryStore();

const activeCategory = ref(null);
const showAddCategory = ref(false);
const showEditCategory = ref(false);
const newCategory = ref({ name: '', description: '' });
const editCategoryData = ref({ name: '', description: '' });

const categories = computed(() => categoryStore.categories);
const totalImages = computed(() => {
    if (!Array.isArray(categories.value)) {
        return 0; // Return 0 if categories.value is not an array
    }
    return categories.value.reduce((total, category) => {
        return total + (category.galleries_count || 0);
    }, 0);
});

const currentCategory = computed(() => {
    if (!activeCategory.value) return null;
    return categories.value.find(cat => cat.id === activeCategory.value);
});

watch(currentCategory, (newVal) => {
    if (newVal) {
        editCategoryData.value = {
            name: newVal.name,
            description: newVal.description || ''
        };
    }
});

// Initialize store data
const initialize = async () => {
    try {
        if (categoryStore.categories.length === 0) {
            await categoryStore.fetchCategories();
        }
        console.log('Categories after fetch:', categoryStore.categories);
        console.log('Type of categories:', typeof categoryStore.categories);
        console.log('Is array?', Array.isArray(categoryStore.categories));
    } catch (error) {
        console.error('Error initializing:', error);
    }
};

// Call initialize once on component creation
initialize();

const selectCategory = (categoryId) => {
    activeCategory.value = categoryId;
    galleryStore.setActiveCategory(categoryId);
    emit('category-changed', categoryId);
};

const createCategory = async () => {
    try {
        await categoryStore.createCategory({
            name: newCategory.value.name,
            description: newCategory.value.description
        });

        newCategory.value = { name: '', description: '' };
        showAddCategory.value = false;
    } catch (error) {
        alert('Failed to create category. Please try again.');
    }
};

const updateCategory = async () => {
    if (!activeCategory.value) return;

    try {
        await categoryStore.updateCategory(activeCategory.value, {
            name: editCategoryData.value.name,
            description: editCategoryData.value.description
        });

        showEditCategory.value = false;
    } catch (error) {
        alert('Failed to update category. Please try again.');
    }
};

const confirmDeleteCategory = async () => {
    if (!activeCategory.value) return;

    // Double-check that there are no images in this category
    if (currentCategory.value && currentCategory.value.galleries_count > 0) {
        alert('Cannot delete category with images. Move or delete images first.');
        return;
    }

    if (confirm(`Are you sure you want to delete the category "${currentCategory.value.name}"? This cannot be undone.`)) {
        try {
            await categoryStore.deleteCategory(activeCategory.value);

            activeCategory.value = null;
            galleryStore.setActiveCategory(null);
            emit('category-changed', null);
            showEditCategory.value = false;
        } catch (error) {
            alert('Failed to delete category. Please try again.');
        }
    }
};
</script>

<style scoped>
.category-selector {
    margin-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background-color: white;
}

.category-list {
    max-height: 300px;
    overflow-y: auto;
    border-bottom: 1px solid #ddd;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f2f2f2;
    transition: background-color 0.2s;
}

.category-item:last-child {
    border-bottom: none;
}

.category-item:hover {
    background-color: #f9f9f9;
}

.category-item.active {
    background-color: #e9f5ff;
    font-weight: 500;
}

.category-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.category-count {
    background-color: #f0f0f0;
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 0.8rem;
    min-width: 30px;
    text-align: center;
}

.category-actions {
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.btn {
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 0.8rem;
}

.btn-outline-primary {
    color: #007bff;
    border: 1px solid #007bff;
    background-color: transparent;
}

.btn-outline-primary:hover {
    background-color: #007bff;
    color: white;
}

.btn-outline-secondary {
    color: #6c757d;
    border: 1px solid #6c757d;
    background-color: transparent;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    color: white;
}

.category-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 15px;
}

.modal-footer {
    padding: 15px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}

.form-group {
    margin-bottom: 15px;
}

.form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.danger-zone {
    margin-top: 30px;
    padding: 15px;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    background-color: #f8d7da;
}

.danger-zone h4 {
    color: #721c24;
    margin-top: 0;
    margin-bottom: 10px;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-danger:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.text-danger {
    color: #dc3545;
    display: block;
    margin-top: 5px;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    border: none;
}

.btn-primary {
    background-color: #007bff;
    color: white;
    border: none;
}

.btn-primary:hover {
    background-color: #0069d9;
}
</style>