-- Benutzer-Tabelle
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Kategorien-Tabelle
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('income', 'expense')),
    color VARCHAR(7) DEFAULT '#e6a309',
    icon VARCHAR(50) DEFAULT 'circle',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Transaktionen-Tabelle
CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('income', 'expense')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Standard-Kategorien einfügen
INSERT OR IGNORE INTO categories (id, user_id, name, type, color, icon) VALUES 
(1, 1, 'Lebensmittel', 'expense', '#f0b753', 'shopping-cart'),
(2, 1, 'Transport', 'expense', '#f4c16c', 'car'),
(3, 1, 'Gehalt', 'income', '#e6a309', 'dollar-sign'),
(4, 1, 'Freelancing', 'income', '#ebad36', 'briefcase');