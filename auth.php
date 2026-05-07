<?php
session_start();
require_once __DIR__ . '/config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function login($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nom'] = $user['nom'];
        $_SESSION['role'] = $user['role'];
        
        // Mettre à jour la date de connexion
        $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = ?")->execute([$user['id']]);
        
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>