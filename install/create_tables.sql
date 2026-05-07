-- install/create_tables.sql

CREATE DATABASE IF NOT EXISTS inox_pharma;
USE inox_pharma;

-- Table des produits
CREATE TABLE IF NOT EXISTS produits (
    code_cip VARCHAR(20) PRIMARY KEY,
    libelle VARCHAR(255) NOT NULL,
    prix_cession DECIMAL(10,2) NOT NULL DEFAULT 0,
    prix_public DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des clients
CREATE TABLE IF NOT EXISTS clients (
    code_client VARCHAR(20) PRIMARY KEY,
    designation VARCHAR(255) NOT NULL,
    province VARCHAR(100),
    agence VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des ventes éclatées
CREATE TABLE IF NOT EXISTS ventes_eclatees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code_cip VARCHAR(20) NOT NULL,
    code_client VARCHAR(20) NOT NULL,
    mois DATE NOT NULL,
    qte_livree INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_cip) REFERENCES produits(code_cip) ON DELETE CASCADE,
    FOREIGN KEY (code_client) REFERENCES clients(code_client) ON DELETE CASCADE,
    INDEX idx_mois (mois),
    INDEX idx_produit (code_cip),
    INDEX idx_client (code_client)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;