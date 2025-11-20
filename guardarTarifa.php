<?php
/*
 * =================================
 * GUARDAR TARIFA Y CREAR PASAJEROS FANTASMA (Corregido)
 * =================================
 */

// 1. Iniciar sesión y cargar la BD
session_start();
require_once 'src/config.php'; // $pdo

// 2. Seguridad
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['id_reserva'])) {
    header("Location: index.php");
    exit;
}

// 3. Obtener datos
$id_reserva = $_POST['id_reserva'];
$tarifa = $_POST['tarifa_seleccionada'];
$precio_adicional = (float)$_POST['precio_adicional_total'];
$id_usuario = $_SESSION['user_id'];

// 4. Validar que la reserva sea nuestra
if ($id_reserva != $_SESSION['id_reserva_activa']) {
    header("Location: index.php?error=reserva_invalida");
    exit;
}

try {
    // 5. Iniciar Transacción
    $pdo->beginTransaction();

    // 6. Actualizar la reserva (¡AQUÍ ESTÁ LA CORRECCIÓN!)
    // Se usan dos placeholders únicos: :precio_add_col y :precio_add_calc
    $sql_update = "UPDATE reservas 
                   SET tarifa = :tarifa, 
                       precio_tarifa_adicional = :precio_add_col,
                       precio_final = precio_final + :precio_add_calc
                   WHERE id = :id_reserva AND id_usuario = :id_usuario";
    
    $stmt_update = $pdo->prepare($sql_update);
    
    // Ahora pasamos 5 valores que coinciden con los 5 placeholders
    $stmt_update->execute([
        'tarifa'           => $tarifa,
        'precio_add_col'   => $precio_adicional, // Para la columna precio_tarifa_adicional
        'precio_add_calc'  => $precio_adicional, // Para el cálculo de precio_final
        'id_reserva'       => $id_reserva,
        'id_usuario'       => $id_usuario
    ]);

    // 7. Obtener el número total de pasajeros de la reserva
    $stmt_get = $pdo->prepare("SELECT total_pasajeros FROM reservas WHERE id = :id_reserva");
    $stmt_get->execute(['id_reserva' => $id_reserva]);
    $reserva = $stmt_get->fetch();
    $total_pasajeros = $reserva['total_pasajeros'];

    // 8. Crear "Pasajeros Fantasma" para la selección de asientos
    $sql_pasajero = "INSERT INTO pasajeros (id_reserva, nombre, apellido) 
                     VALUES (:id_reserva, :nombre, :apellido)";
    $stmt_pasajero = $pdo->prepare($sql_pasajero);
    
    $ids_pasajeros_creados = [];
    
    for ($i = 1; $i <= $total_pasajeros; $i++) {
        $stmt_pasajero->execute([
            'id_reserva' => $id_reserva,
            'nombre'     => "Pasajero $i",
            'apellido'   => "" // Dejamos el apellido vacío
        ]);
        $ids_pasajeros_creados[] = $pdo->lastInsertId();
    }
    
    // 9. Confirmar Transacción
    $pdo->commit();

    // 10. Guardar los IDs de los pasajeros en la sesión (para el siguiente paso)
    $_SESSION['ids_pasajeros'] = $ids_pasajeros_creados;

    // 11. Redirigir al siguiente paso
    header("Location: seleccionarAsientos.php");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: detallesReserva.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>