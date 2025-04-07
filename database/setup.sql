-- Database schema for Rangoli Ice Cream
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS items (
  item_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  category ENUM('Ice Cream', 'Shake', 'Snack', 'Combo') NOT NULL,
  image_url VARCHAR(255),
  is_available BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS orders (
  order_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  customer_name VARCHAR(255) NOT NULL,
  contact_number VARCHAR(20) NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  status ENUM('Pending', 'Confirmed', 'Processing', 'Ready', 'Completed') DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  item_id INT NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(item_id)
);

-- Sample data
INSERT INTO items (name, description, price, category, image_url) VALUES
('Vanilla Dream', 'Classic vanilla ice cream with rich flavor', 4.99, 'Ice Cream', 'https://images.pexels.com/photos/1625235/pexels-photo-1625235.jpeg'),
('Chocolate Fudge', 'Decadent chocolate ice cream with fudge swirls', 5.49, 'Ice Cream', 'https://images.pexels.com/photos/372851/pexels-photo-372851.jpeg'),
('Strawberry Swirl', 'Creamy strawberry ice cream with real fruit', 5.29, 'Ice Cream', 'https://images.pexels.com/photos/6605313/pexels-photo-6605313.jpeg'),
('Mango Shake', 'Refreshing mango milkshake', 6.99, 'Shake', 'https://images.pexels.com/photos/918581/pexels-photo-918581.jpeg'),
('Cheese Corn', 'Sweet corn with cheese topping', 3.99, 'Snack', 'https://images.pexels.com/photos/5638593/pexels-photo-5638593.jpeg');