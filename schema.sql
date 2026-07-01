CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title TEXT,
    link VARCHAR(255) UNIQUE,
    status INT DEFAULT 0,
    created_date DATE,
    source VARCHAR(255)
);

CREATE INDEX idx_news_date_status ON news (created_date, status);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255),
    role VARCHAR(50) DEFAULT 'user'
);

CREATE TABLE IF NOT EXISTS user_news_status (
    user_id INT,
    news_id INT,
    status INT DEFAULT 0,
    PRIMARY KEY (user_id, news_id)
);

CREATE TABLE IF NOT EXISTS action_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    news_id INT,
    action_type VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password_hash, role) 
VALUES ('admin', '$2y$10$qK.82V2Z0E8z4SzTesOfQOW2l7blBVSxhUgtSIfCbe6WibFcqu5G2', 'admin');
