<?php
/*
 * =================================
 * PROCESADOR DE GUARDADO DE ASIENTOS (Actualizado para Mapa Visual)
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

// 3. Validar datos POST y Sesión
if (
    !isset($_POST['id_reserva'], $_POST['asiento_ida']) ||
    !isset($_SESSION['id_reserva_activa'], $_SESSION['seleccion_ida'])
) {
    header("Location: seleccionarAsientos.php?error=datosincompletos");
    exit;
}

// 4. Obtener datos
$id_reserva = $_POST['id_reserva'];
$asientos_ida = $_POST['asiento_ida']; // Array asociativo [pasajero_id => asiento_num]
$asientos_regreso = isset($_POST['asiento_regreso']) ? $_POST['asiento_regreso'] : null;

$id_vuelo_ida = $_SESSION['seleccion_ida']['id_vuelo'];
$id_vuelo_regreso = isset($_SESSION['seleccion_regreso']) ? $_SESSION['seleccion_regreso']['id_vuelo'] : null;

// 5. Validaciones (Simplificadas)
if ($id_reserva != $_SESSION['id_reserva_activa']) {
    header("Location: seleccionarAsientos.php?error=incoherencia_datos");
    exit;
}
// Validación de que todos los asientos de ida estén asignados
foreach ($asientos_ida as $asiento) {
    if (empty($asiento)) {
        header("Location: seleccionarAsientos.php?error=asientos_ida_vacios");
        exit;
    }
}
// Validación para regreso (si aplica)
if ($asientos_regreso) {
    foreach ($asientos_regreso as $asiento) {
        if (empty($asiento)) {
            header("Location: seleccionarAsientos.php?error=asientos_regreso_vacios");
            exit;
        }
    }
}


// 6. Procesar la inserción con una TRANSACCIÓN
try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO asientos_reservados (id_reserva, id_vuelo, id_pasajero, numero_asiento)
            VALUES (:id_reserva, :id_vuelo, :id_pasajero, :numero_asiento)";
    $stmt = $pdo->prepare($sql);

    // --- Bucle 1: Insertar asientos de IDA ---
    foreach ($asientos_ida as $id_pasajero => $numero_asiento) {
        $stmt->execute([
            'id_reserva'     => $id_reserva,
            'id_vuelo'       => $id_vuelo_ida,
            'id_pasajero'    => $id_pasajero,
            'numero_asiento' => $numero_asiento
        ]);
    }

    // --- Bucle 2: Insertar asientos de REGRESO (si existen) ---
    if ($asientos_regreso) {
        foreach ($asientos_regreso as $id_pasajero => $numero_asiento) {
            $stmt->execute([
                'id_reserva'     => $id_reserva,
                'id_vuelo'       => $id_vuelo_regreso,
                'id_pasajero'    => $id_pasajero,
                'numero_asiento' => $numero_asiento
            ]);
        }
    }

    // 7. Si todo salió bien, confirmar
    $pdo->commit();

    // 8. Redirigir al siguiente paso: PAGO
    header("Location: pago.php");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() == 23000) {
        header("Location: seleccionarAsientos.php?error=asiento_ocupado");
    } else {
        header("Location: seleccionarAsientos.php?error=" . urlencode($e->getMessage()));
    }
    exit;
}
?>