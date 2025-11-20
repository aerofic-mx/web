<?php
// 1. Incluir config y header (inicia sesión)
require_once 'src/config.php';

/*
 * =================================
 * BLOQUE DE PROCESAMIENTO DE ACCIONES
 * =================================
 */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])){
    
    session_start(); // Iniciar sesión para procesar la acción
    
    if(!isset($_SESSION['user_id'])){
        header("Location: login.php");
        exit;
    }

    $id_reserva = $_POST['id_reserva_procesar'];
    $id_usuario = $_SESSION['user_id'];
    $accion = $_POST['accion'];

    try{
        if($accion == 'confirmar_checkin'){
            
            // --- ACCIÓN: CONFIRMAR (CHECK-IN) 
            $sql_update = "UPDATE reservas 
                           SET estado = 'checked_in' 
                           WHERE id = :id_reserva 
                             AND id_usuario = :id_usuario 
                             AND estado = 'confirmada'";
            
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute(['id_reserva' => $id_reserva, 'id_usuario' => $id_usuario]);

            if($stmt_update->rowCount() > 0){
                header("Location: index.php?status=checkin_exitoso");
                exit;
            }else{
                throw new Exception("No se pudo realizar el check-in. La reserva debe estar 'confirmada' (pagada) primero.");
            }

        }elseif ($accion == 'cancelar'){
            
            // --- ACCIÓN: CANCELAR ---
            $sql_borrar = "DELETE FROM reservas 
                           WHERE id = :id_reserva AND id_usuario = :id_usuario";
            
            $stmt_borrar = $pdo->prepare($sql_borrar);
            $stmt_borrar->execute(['id_reserva' => $id_reserva, 'id_usuario' => $id_usuario]);

            if ($stmt_borrar->rowCount() > 0) {
                header("Location: index.php?status=cancelacion_exitosa");
                exit;
            } else {
                throw new Exception("No se pudo cancelar la reserva (ID: $id_reserva).");
            }
        }

    } catch (Exception $e) {
        header("Location: checkIn.php?id_reserva=" . $_POST['id_reserva_procesar'] . "&error=" . urlencode($e->getMessage()));
        exit;
    }
}

/*
 * =================================
 * BLOQUE DE VISUALIZACIÓN
 * =================================
 */

// Incluir el header
require_once 'src/Vistas/header.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$reserva_detalles = null;
$error_visual = null;

$id_reserva_post = isset($_POST['id_reserva']) ? $_POST['id_reserva'] : (isset($_GET['id_reserva']) ? $_GET['id_reserva'] : null);
$id_usuario = $_SESSION['user_id'];

if(isset($_GET['error'])){
    $error_visual = htmlspecialchars($_GET['error']);
}

if($id_reserva_post){
    $id_limpio = (int) ltrim(strtoupper($id_reserva_post), 'AERO-0');

    if($id_limpio > 0){
        try{
            // Buscamos la reserva en la tabla 'reservas'
            $sql_buscar = "
                SELECT 
                    r.*, 
                    v_ida.numero_vuelo as vuelo_ida_num,
                    a_origen.ciudad as origen_ciudad,
                    a_destino.ciudad as destino_ciudad
                FROM reservas r
                JOIN vuelos v_ida ON r.id_vuelo_ida = v_ida.id
                JOIN aeropuertos a_origen ON v_ida.aeropuerto_origen_id = a_origen.id
                JOIN aeropuertos a_destino ON v_ida.aeropuerto_destino_id = a_destino.id
                WHERE 
                    r.id = :id_reserva 
                    AND r.id_usuario = :id_usuario";
            
            $stmt_buscar = $pdo->prepare($sql_buscar);
            $stmt_buscar->execute(['id_reserva' => $id_limpio, 'id_usuario' => $id_usuario]);
            $reserva_detalles = $stmt_buscar->fetch();

            if(!$reserva_detalles){
                $error_visual = "Reserva no encontrada (ID: $id_limpio). Verifica que el código sea correcto y te pertenezca.";
            }

        }catch (Exception $e){
            $error_visual = "Error de base de datos: " . $e->getMessage();
        }
    }else{
        $error_visual = "El formato del ID de reserva no es válido. (Ej: AERO-000123)";
    }
}else{
    $error_visual = "No se proporcionó un ID de reserva.";
}
?>

<div class="checkin-container container">
    <h2>Gestión de Reserva / Check-in</h2>

    <?php if ($error_visual): ?>
        <p class="error"><?php echo $error_visual; ?></p>
        <a href="index.php">Volver al inicio</a>
    <?php endif; ?>

    <?php if ($reserva_detalles): ?>
        <fieldset class="resumen-card">
            <legend>Detalles de la Reserva</legend>
            <p><strong>Código:</strong> <?php echo 'AERO-' . str_pad($reserva_detalles['id'], 6, '0', STR_PAD_LEFT); ?></p>
            <p><strong>Vuelo:</strong> <?php echo htmlspecialchars($reserva_detalles['vuelo_ida_num']); ?></p>
            <p><strong>Ruta:</strong> <?php echo htmlspecialchars($reserva_detalles['origen_ciudad']); ?> a <?php echo htmlspecialchars($reserva_detalles['destino_ciudad']); ?></p>
            <p><strong>Pasajeros:</strong> <?php echo htmlspecialchars($reserva_detalles['total_pasajeros']); ?></p>
            <p>
                <strong>Estado:</strong> 
                <strong style="text-transform: capitalize; color: <?php echo ($reserva_detalles['estado'] == 'confirmada' || $reserva_detalles['estado'] == 'checked_in') ? 'green' : '#e76f51'; ?>;">
                    <?php echo htmlspecialchars($reserva_detalles['estado']); ?>
                </strong>
            </p>
            <p><strong>Total:</strong> $<?php echo number_format($reserva_detalles['precio_final'], 2); ?> MXN</p>
        </fieldset>

        <form action="checkIn.php" method="POST" class="gestion-form-acciones">
            <p>¿Qué deseas hacer con esta reserva?</p>
            
            <input type="hidden" name="id_reserva_procesar" value="<?php echo $reserva_detalles['id']; ?>">
            
            <div class="gestion-botones">
                
                <?php if ($reserva_detalles['estado'] == 'confirmada'): ?>
                    <button type="submit" name="accion" value="confirmar_checkin" class="boton-checkin">
                        Confirmar Check-in
                    </button>
                <?php elseif ($reserva_detalles['estado'] == 'checked_in'): ?>
                    <button type="button" class="boton-checkin" disabled>Check-in Realizado</button>
                <?php else: // (estado == 'pendiente') ?>
                    <button type="button" class="boton-checkin" disabled>Paga para hacer Check-in</button>
                <?php endif; ?>

                <button type="submit" name="accion" value="cancelar" class="boton-modificar" 
                        onclick="return confirm('¿Estás seguro de que quieres cancelar y eliminar permanentemente esta reserva?');">
                    Cancelar Reserva
                </button>
            </div>
        </form>

    <?php endif; ?>
</div>

<?php
// 5. Incluir el Footer
require_once 'src/Vistas/footer.php';
?>