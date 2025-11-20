<?php
// 1. Incluir config y header (inicia sesión)
require_once 'src/config.php';
require_once 'src/Vistas/header.php';

// 2. Seguridad: Proteger la página
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 3. Validar que tengamos un ID de reserva
if (!isset($_GET['reserva_id'])) {
    header("Location: index.php?error=no_id_confirmacion");
    exit;
}

$id_reserva_confirmada = $_GET['reserva_id'];
$id_usuario = $_SESSION['user_id'];

// 4. Limpiar el ID de la sesión (su trabajo ha terminado)
// Esto evita que el usuario pueda recargar la página de pago.
unset($_SESSION['id_reserva_activa']);

// 5. Obtener los datos de la reserva CONFIRMADA
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.precio_final, v.numero_vuelo as vuelo_ida
        FROM reservas r
        JOIN vuelos v ON r.id_vuelo_ida = v.id
        WHERE 
            r.id = :id_reserva 
            AND r.id_usuario = :id_usuario 
            AND r.estado = 'confirmada'
    ");
    $stmt->execute([
        'id_reserva' => $id_reserva_confirmada,
        'id_usuario' => $id_usuario
    ]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        throw new Exception("No se pudo encontrar tu confirmación de reserva.");
    }

} catch (Exception $e) {
    echo '<div class="error-container">Error: ' . $e->getMessage() . '</div>';
    require_once 'src/Vistas/footer.php';
    exit;
}
?>

<div class="confirmacion-container">
    <h2>¡Gracias por tu compra, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
    
    <div class="confirmacion-card">
        <h3>Tu reserva está confirmada.</h3>
        <p>Guarda esta información, es tu comprobante de viaje.</p>
        
        <div class="confirmacion-detalles">
            <p>
                <strong>Código de Confirmación:</strong> 
                <span><?php echo 'AERO-' . str_pad($reserva['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </p>
            <p>
                <strong>Vuelo Principal:</strong> 
                <span><?php echo htmlspecialchars($reserva['vuelo_ida']); ?></span>
            </p>
            <p>
                <strong>Monto Total Pagado:</strong> 
                <span>$<?php echo number_format($reserva['precio_final'], 2); ?> MXN</span>
            </p>
        </div>
        
        <a href="index.php" class="boton-continuar">Buscar un nuevo vuelo</a>
    </div>
</div>

<?php
// 6. Incluir el Footer
require_once 'src/Vistas/footer.php';
?>