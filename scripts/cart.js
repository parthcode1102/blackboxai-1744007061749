// Add item to cart
function addToCart(event) {
    const button = event.target;
    const itemId = parseInt(button.dataset.id);
    const itemName = button.dataset.name;
    const itemPrice = parseFloat(button.dataset.price);
    
    // Check if item already exists in cart
    const existingItem = cart.find(item => item.id === itemId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: itemId,
            name: itemName,
            price: itemPrice,
            quantity: 1
        });
    }
    
    // Update UI and storage
    saveCart();
    updateCartCount();
    showToast(`${itemName} added to cart!`);
    
    // Add animation to button
    button.innerHTML = '<i class="fas fa-check"></i> Added!';
    button.classList.add('bg-green-500', 'hover:bg-green-600');
    setTimeout(() => {
        button.innerHTML = 'Add to Cart';
        button.classList.remove('bg-green-500', 'hover:bg-green-600');
        button.classList.add('bg-orange-500', 'hover:bg-orange-600');
    }, 1500);
}

// Remove item from cart
function removeFromCart(itemId) {
    cart = cart.filter(item => item.id !== itemId);
    saveCart();
    updateCartCount();
    showToast('Item removed from cart', 'error');
    
    // Reload cart display if on cart page
    if (window.location.pathname.includes('cart.html')) {
        displayCartItems();
    }
}

// Update item quantity
function updateQuantity(itemId, newQuantity) {
    const item = cart.find(item => item.id === itemId);
    if (item) {
        item.quantity = Math.max(1, newQuantity);
        saveCart();
        updateCartCount();
        
        // Update total if on cart page
        if (window.location.pathname.includes('cart.html')) {
            updateCartTotal();
        }
    }
}

// Save cart to localStorage
function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-md shadow-lg animate-fade-in-out ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white flex items-center`;
    
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Calculate cart total
function calculateTotal() {
    return cart.reduce((total, item) => total + (item.price * item.quantity), 0);
}

// For cart.html page
function displayCartItems() {
    const cartContainer = document.getElementById('cart-items');
    const emptyCart = document.getElementById('empty-cart');
    const cartWithItems = document.getElementById('cart-with-items');
    
    if (cart.length === 0) {
        emptyCart.classList.remove('hidden');
        cartWithItems.classList.add('hidden');
        return;
    }
    
    emptyCart.classList.add('hidden');
    cartWithItems.classList.remove('hidden');
    
    cartContainer.innerHTML = '';
    
    cart.forEach(item => {
        const cartItem = document.createElement('div');
        cartItem.className = 'flex items-center justify-between py-4 border-b';
        cartItem.innerHTML = `
            <div class="flex items-center">
                <img src="https://images.pexels.com/photos/1625235/pexels-photo-1625235.jpeg" 
                     alt="${item.name}" class="w-16 h-16 object-cover rounded">
                <div class="ml-4">
                    <h3 class="font-medium">${item.name}</h3>
                    <p class="text-orange-500 font-bold">$${item.price.toFixed(2)}</p>
                </div>
            </div>
            <div class="flex items-center">
                <button class="quantity-btn px-2 py-1 bg-gray-200 rounded" 
                        onclick="updateQuantity(${item.id}, ${item.quantity - 1})">
                    <i class="fas fa-minus"></i>
                </button>
                <span class="mx-2 w-8 text-center">${item.quantity}</span>
                <button class="quantity-btn px-2 py-1 bg-gray-200 rounded" 
                        onclick="updateQuantity(${item.id}, ${item.quantity + 1})">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="ml-4 text-red-500 hover:text-red-700" 
                        onclick="removeFromCart(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        cartContainer.appendChild(cartItem);
    });
    
    updateCartTotal();
}

// Update cart total display
function updateCartTotal() {
    const subtotal = calculateTotal();
    const tax = subtotal * 0.1; // 10% tax
    const total = subtotal + tax;
    
    document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `$${tax.toFixed(2)}`;
    document.getElementById('total').textContent = `$${total.toFixed(2)}`;
}

// Initialize cart on page load
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.pathname.includes('cart.html')) {
        displayCartItems();
    }
});

// Make functions available globally
window.addToCart = addToCart;
window.removeFromCart = removeFromCart;
window.updateQuantity = updateQuantity;
window.displayCartItems = displayCartItems;