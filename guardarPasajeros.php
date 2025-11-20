<?php
/*
 * =================================
 * PROCESADOR DE GUARDADO DE PASAJEROS (Actualizado)
 * =================================
 */

// 1. Iniciar sesión y cargar la BD
session_start();
require_once 'src/config.php'; // $pdo

// 2. Seguridad
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: index.php");
    exit;
}

// 3. Validar que los datos POST y de SESIÓN necesarios existan
// ¡AQUÍ ESTÁ LA CORRECCIÓN! (Validamos con 'total_pasajeros')
if (!isset($_POST['id_reserva'], $_POST['nombre'], $_POST['apellido'], $_SESSION['id_reserva_activa'], $_SESSION['busqueda']['total_pasajeros'])) {
    header("Location: registrarPasajeros.php?error=datosincompletos");
    exit;
}

// 4. Obtener datos
$id_reserva = $_POST['id_reserva'];
$nombres = $_POST['nombre'];     // Array
$apellidos = $_POST['apellido']; // Array

// 5. Validaciones de seguridad y coherencia
// ¡AQUÍ ESTÁ LA CORRECCIÓN! (Validamos con 'total_pasajeros')
if (
    $id_reserva != $_SESSION['id_reserva_activa'] ||
    count($nombres) != $_SESSION['busqueda']['total_pasajeros'] || // <-- CORREGIDO
    count($nombres) != count($apellidos)
) {
    header("Location: registrarPasajeros.php?error=incoherencia_datos");
    exit;
}

// 6. Procesar la inserción con una TRANSACCIÓN
try {
    // Iniciar la transacción
    $pdo->beginTransaction();

    // Preparar la consulta
    $sql = "INSERT INTO pasajeros (id_reserva, nombre, apellido) 
            VALUES (:id_reserva, :nombre, :apellido)";
    $stmt = $pdo->prepare($sql);

    $ids_pasajeros_creados = []; // Array para guardar los IDs

    // 7. Recorrer los arrays y ejecutar la consulta por cada pasajero
    for ($i = 0; $i < count($nombres); $i++) {
        
        $nombre = trim($nombres[$i]);
        $apellido = trim($apellidos[$i]);

        if (empty($nombre) || empty($apellido)) {
            throw new Exception("El nombre y apellido son obligatorios para todos los pasajeros.");
        }

        $stmt->execute([
            'id_reserva' => $id_reserva,
            'nombre'     => $nombre,
            'apellido'   => $apellido
        ]);

        $ids_pasajeros_creados[] = $pdo->lastInsertId();
    }

    // 8. Si todo salió bien, confirmar
    $pdo->commit();

    // 9. Guardar los IDs de los pasajeros en la sesión
    $_SESSION['ids_pasajeros'] = $ids_pasajeros_creados;

    // 10. Redirigir al siguiente paso
    header("Location: seleccionarAsientos.php");
    exit;

} catch (Exception $e) {
    // 11. Si algo falló, revertir
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: registrarPasajeros.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>