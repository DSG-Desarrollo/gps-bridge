<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Clase Database - Gestión de conexión a base de datos usando patrón Singleton
 * 
 * Esta clase maneja la conexión única a la base de datos MySQL que almacena
 * la información de las unidades para consultar la API de Wialon.
 * 
 * Utiliza el patrón Singleton para garantizar una única instancia de conexión
 * durante todo el ciclo de vida de la aplicación, optimizando recursos.
 * 
 * @package App\Core
 * @author Tu nombre
 * @version 1.0.0
 */
class Database
{
    /**
     * Instancia única de la clase (patrón Singleton)
     * 
     * @var Database|null
     */
    private static ?Database $instance = null;

    /**
     * Conexión PDO a la base de datos
     * 
     * @var PDO
     */
    private PDO $connection;

    /**
     * Constructor privado para prevenir instanciación directa
     * 
     * Carga la configuración desde el archivo de configuración,
     * valida los parámetros y establece la conexión a MySQL.
     * 
     * @throws RuntimeException Si la configuración es inválida o la conexión falla
     */
    private function __construct()
    {
        $configPath = __DIR__ . '/../../config/database.php';

        // Validar que el archivo de configuración existe
        if (!file_exists($configPath)) {
            throw new RuntimeException("El archivo de configuración de base de datos no existe: {$configPath}");
        }

        $config = require $configPath;

        // Validar configuración requerida
        $this->validateConfig($config);

        // Construir el DSN (Data Source Name) para MySQL
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;port=%s;charset=utf8mb4",
            $config['host'],
            $config['database'],
            $config['port']
        );

        try {
            // Establecer conexión con configuración de seguridad
            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    // Lanzar excepciones en caso de errores SQL
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    
                    // Retornar resultados como arrays asociativos por defecto
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    
                    // Deshabilitar emulación de prepared statements para mayor seguridad
                    PDO::ATTR_EMULATE_PREPARES => false,
                    
                    // Establecer timeout de conexión
                    PDO::ATTR_TIMEOUT => 5,
                    
                    // Usar conexiones persistentes para mejorar rendimiento
                    PDO::ATTR_PERSISTENT => false
                ]
            );

            // Log de conexión exitosa (opcional, comentar en producción)
            // error_log("Conexión a base de datos establecida correctamente");

        } catch (PDOException $e) {
            // Registrar el error en el log del servidor
            error_log("Error de conexión a base de datos: " . $e->getMessage());
            
            // Lanzar excepción con mensaje genérico (no exponer detalles sensibles)
            throw new RuntimeException(
                "No se pudo establecer conexión con la base de datos. " .
                "Por favor, verifica la configuración y que el servidor esté disponible."
            );
        }
    }

    /**
     * Valida que la configuración contenga todos los parámetros necesarios
     * 
     * @param array $config Configuración de base de datos
     * @throws RuntimeException Si falta algún parámetro requerido
     * @return void
     */
    private function validateConfig(array $config): void
    {
        $requiredKeys = ['host', 'database', 'port', 'username', 'password'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new RuntimeException("Falta el parámetro de configuración requerido: {$key}");
            }
            
            if (empty($config[$key]) && $key !== 'password') {
                throw new RuntimeException("El parámetro de configuración '{$key}' no puede estar vacío");
            }
        }
    }

    /**
     * Obtiene la instancia única de la clase (patrón Singleton)
     * 
     * Si no existe una instancia previa, crea una nueva.
     * Esto garantiza que solo exista una conexión a la base de datos.
     * 
     * @return Database Instancia única de Database
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Obtiene el objeto PDO de conexión a la base de datos
     * 
     * Este método permite acceder a la conexión PDO para ejecutar
     * consultas relacionadas con las unidades de Wialon.
     * 
     * @return PDO Objeto de conexión PDO activo
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Verifica si la conexión a la base de datos está activa
     * 
     * @return bool True si la conexión está activa, false en caso contrario
     */
    public function isConnected(): bool
    {
        try {
            return $this->connection->query('SELECT 1') !== false;
        } catch (PDOException $e) {
            error_log("Error al verificar conexión: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Previene la clonación de la instancia
     * 
     * @return void
     */
    private function __clone(): void
    {
        // Método vacío para prevenir clonación
    }

    /**
     * Previene la deserialización de la instancia
     * 
     * @throws RuntimeException Siempre
     * @return void
     */
    public function __wakeup(): void
    {
        throw new RuntimeException("No se puede deserializar un Singleton");
    }

    /**
     * Cierra la conexión a la base de datos
     * 
     * Aunque PDO cierra automáticamente al destruir el objeto,
     * este método permite cerrar explícitamente si es necesario.
     * 
     * @return void
     */
    public function disconnect(): void
    {
        $this->connection = null;
        self::$instance = null;
    }
}