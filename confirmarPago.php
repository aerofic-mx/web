<?php
/*
 * =================================
 * PROCESADOR DE CONFIRMACIÓN DE PAGO
 * =================================
 */

// 1. Iniciar sesión y cargar la BD
session_start();
require_once 'src/config.php'; // $pdo

// 2. Seguridad: Verificar login y que vengan datos por POST
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: index.php");
    exit;
}

// 3. Validar que la reserva de la sesión y del formulario coincidan
if (
    !isset($_POST['id_reserva']) ||
    !isset($_SESSION['id_reserva_activa']) ||
    $_POST['id_reserva'] != $_SESSION['id_reserva_activa']
) {
    // Intento de confirmar una reserva inválida o que no es la activa
    header("Location: index.php?error=confirmacion_invalida");
    exit;
}

$id_reserva = $_SESSION['id_reserva_activa'];
$id_usuario = $_SESSION['user_id'];

// 4. ¡LA ACCIÓN CLAVE! Actualizar la reserva
try {
    // Cambiamos el estado de 'pendiente' a 'confirmada'
    // Usamos id_usuario para doble seguridad (un usuario no puede confirmar la reserva de otro)
    $sql = "UPDATE reservas 
            SET estado = 'confirmada' 
            WHERE id = :id_reserva AND id_usuario = :id_usuario AND estado = 'pendiente'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id_reserva' => $id_reserva,
        'id_usuario' => $id_usuario
    ]);

    // 5. Verificar si la actualización fue exitosa
    if ($stmt->rowCount() > 0) {
        
        // ¡ÉXITO! La reserva está confirmada.
        
        // 6. Limpieza de Sesión
        // Borramos todos los datos de esta reserva para que no se
        // puedan reutilizar accidentalmente.
        unset($_SESSION['busqueda']);
        unset($_SESSION['resultados_ida']);
        unset($_SESSION['resultados_regreso']);
        unset($_SESSION['seleccion_ida']);
        unset($_SESSION['seleccion_regreso']);
        unset($_SESSION['ids_pasajeros']);
        
        // Dejamos el 'id_reserva_activa' por un momento para pasarlo
        // a la página de confirmación
        
        // 7. Redirigir a la página de éxito
        header("Location: confirmacion.php?reserva_id=" . $id_reserva);
        exit;
        
    } else {
        // La reserva no se actualizó (quizás ya estaba confirmada o no pertenecía al usuario)
        throw new Exception("No se pudo confirmar la reserva.");
    }

} catch (Exception $e) {
    // Manejar cualquier error
    header("Location: pago.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>