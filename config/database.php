<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=crm_albinet;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Retorna arrays associativos por padrão
            PDO::ATTR_EMULATE_PREPARES   => false,           // Usa prepared statements reais
        ]
    );
} catch (PDOException $e) {
    // Em produção, regista o $e->getMessage() num ficheiro de log e mostra algo genérico:
    die("Erro técnico: Não foi possível estabelecer ligação à base de dados.");
}