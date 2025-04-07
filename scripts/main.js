// Global variables
let cart = JSON.parse(localStorage.getItem('cart')) || [];
let currentUser = JSON.parse(localStorage.getItem('currentUser')) || null;

// DOM elements
const menuItemsContainer = document.getElementById('menu-items');
const categoryButtons = document.querySelectorAll('.category-btn');
const cartCount = document.getElementById('cart-count');
const authButtons = document.getElementById('auth-buttons');

// Initialize the page
document.addEventListener('DOMContentLoaded', () => {
    checkAuthStatus();
    fetchMenuItems();
    updateCartCount();
    
    // Category filter event listeners
    categoryButtons.forEach(button => {
        button.addEventListener('click', () => {
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            const category = button.dataset.category;
            fetchMenuItems(category === 'all' ? 'all' : category);
        });
    });
});

// Check authentication status
function checkAuthStatus() {
    if (currentUser) {
        authButtons.innerHTML = `
            <span class="mr-2">Hi, ${currentUser.name}</span>
            <button onclick="logout()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition">
                Logout
            </button>
        `;
    }
}

// Fetch menu items from backend
async function fetchMenuItems(category = 'all') {
    try {
        menuItemsContainer.innerHTML = `
            <div class="text-center py-10 col-span-full">
                <i class="fas fa-spinner fa-spin text-3xl text-orange-500"></i>
                <p class="mt-2 text-gray-500">Loading menu...</p>
            </div>
        `;
        
        const response = await fetch(`../backend/get_items.php?category=${category}`);
        const items = await response.json();
        
        if (items.length === 0) {
            menuItemsContainer.innerHTML = `
                <div class="text-center py-10 col-span-full">
                    <i class="fas fa-ice-cream text-3xl text-orange-500"></i>
                    <p class="mt-2 text-gray-500">No items found in this category</p>
                </div>
            `;
        } else {
            displayMenuItems(items);
        }
    } catch (error) {
        console.error('Error fetching menu items:', error);
        menuItemsContainer.innerHTML = `
            <div class="text-center py-10 col-span-full">
                <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
                <p class="mt-2 text-gray-500">Failed to load menu. Please try again.</p>
            </div>
        `;
    }
}

// Display menu items
function displayMenuItems(items) {
    menuItemsContainer.innerHTML = '';
    
    items.forEach(item => {
        const itemCard = document.createElement('div');
        itemCard.className = 'bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition flex flex-col';
        itemCard.innerHTML = `
            <img src="${item.image_url}" alt="${item.name}" class="w-full h-48 object-cover">
            <div class="p-4 flex-grow">
                <h3 class="text-lg font-semibold">${item.name}</h3>
                <p class="text-gray-600 text-sm mt-1">${item.description || 'Delicious treat'}</p>
                <div class="flex justify-between items-center mt-3">
                    <span class="text-orange-500 font-bold">$${item.price.toFixed(2)}</span>
                    <button class="add-to-cart bg-orange-500 text-white px-3 py-1 rounded-md hover:bg-orange-600 transition" 
                            data-id="${item.item_id}" data-name="${item.name}" data-price="${item.price}">
                        Add to Cart
                    </button>
                </div>
            </div>
        `;
        menuItemsContainer.appendChild(itemCard);
    });

    // Add event listeners to all "Add to Cart" buttons
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', addToCart);
    });
}

// Logout function
function logout() {
    localStorage.removeItem('currentUser');
    localStorage.removeItem('cart');
    window.location.href = 'index.html';
}

// Update cart count in header
function updateCartCount() {
    const count = cart.reduce((total, item) => total + item.quantity, 0);
    cartCount.textContent = count;
}