<?php
// 1. Incluir config y header (inicia sesión)
require_once 'src/config.php';
require_once 'src/Vistas/header.php';

// 2. Seguridad: Proteger la página
if (!isset($_SESSION['user_id']) || !isset($_SESSION['id_reserva_activa'])) {
    header("Location: login.php?error=no_autorizado");
    exit;
}

// 3. Obtener todos los datos de la sesión y BD
try {
    $id_reserva = $_SESSION['id_reserva_activa'];

    $stmt_res = $pdo->prepare("SELECT * FROM reservas WHERE id = :id_reserva AND id_usuario = :id_usuario");
    $stmt_res->execute(['id_reserva' => $id_reserva, 'id_usuario' => $_SESSION['user_id']]);
    $reserva = $stmt_res->fetch();
    if (!$reserva) throw new Exception("Reserva no encontrada.");

    $pasajeros_pagan = $reserva['adultos'] + $reserva['ninos'];

    // --- ¡CORREGIDO! Obtenemos TODOS los detalles del vuelo de IDA + TUA ---
    $stmt_ida = $pdo->prepare("
        SELECT 
            v.numero_vuelo, v.hora_salida,
            a_origen.nombre as origen_nombre, a_origen.tua as tua_origen,
            a_destino.nombre as destino_nombre
        FROM vuelos v
        JOIN aeropuertos a_origen ON v.aeropuerto_origen_id = a_origen.id
        JOIN aeropuertos a_destino ON v.aeropuerto_destino_id = a_destino.id
        WHERE v.id = :id_vuelo_ida
    ");
    $stmt_ida->execute(['id_vuelo_ida' => $reserva['id_vuelo_ida']]);
    $vuelo_ida = $stmt_ida->fetch();
    
    $tua_ida = $vuelo_ida['tua_origen'] * $pasajeros_pagan;
    $tua_regreso = 0;
    $vuelo_regreso = null;

    if ($reserva['id_vuelo_regreso']) {
        // --- ¡CORREGIDO! Obtenemos TODOS los detalles del vuelo de REGRESO + TUA ---
        $stmt_reg = $pdo->prepare("
            SELECT 
                v.numero_vuelo, v.hora_salida,
                a_origen.nombre as origen_nombre, a_origen.tua as tua_origen,
                a_destino.nombre as destino_nombre
            FROM vuelos v
            JOIN aeropuertos a_origen ON v.aeropuerto_origen_id = a_origen.id
            JOIN aeropuertos a_destino ON v.aeropuerto_destino_id = a_destino.id
            WHERE v.id = :id_vuelo_regreso
        ");
        $stmt_reg->execute(['id_vuelo_regreso' => $reserva['id_vuelo_regreso']]);
        $vuelo_regreso = $stmt_reg->fetch();
        
        $tua_regreso = $vuelo_regreso['tua_origen'] * $pasajeros_pagan;
    }

    $tua_total = $tua_ida + $tua_regreso;
    
    // Desglose de precios
    $precio_tarifa_adicional = $reserva['precio_tarifa_adicional'];
    $precio_base_vuelo = $reserva['precio_final'] - $precio_tarifa_adicional - $tua_total;
    $precio_final_calculado = $reserva['precio_final']; // Usamos el total de la BD

    // Obtenemos pasajeros y asientos
    $stmt_pasajeros = $pdo->prepare("
        SELECT 
            p.nombre, p.apellido,
            (SELECT numero_asiento FROM asientos_reservados ar_ida WHERE ar_ida.id_pasajero = p.id AND ar_ida.id_vuelo = :id_vuelo_ida) as asiento_ida,
            (SELECT numero_asiento FROM asientos_reservados ar_reg WHERE ar_reg.id_pasajero = p.id AND ar_reg.id_vuelo = :id_vuelo_regreso) as asiento_regreso
        FROM pasajeros p
        WHERE p.id_reserva = :id_reserva
    ");
    $stmt_pasajeros->execute([
        'id_reserva' => $id_reserva,
        'id_vuelo_ida' => $reserva['id_vuelo_ida'],
        'id_vuelo_regreso' => $reserva['id_vuelo_regreso']
    ]);
    $pasajeros_con_asientos = $stmt_pasajeros->fetchAll();

} catch (Exception $e) {
    echo "Error al cargar el resumen de la reserva: " . $e->getMessage();
    echo '<p><a href="index.php">Volver al inicio</a></p>';
    require_once 'src/Vistas/footer.php';
    exit;
}
?>

<div class="pago-container container">
    <h2>Resumen de tu Reserva (ID: <?php echo 'AERO-' . str_pad($reserva['id'], 6, '0', STR_PAD_LEFT); ?>)</h2>

    <fieldset class="resumen-card">
        <legend>Vuelo de Ida</legend>
        <p><strong>Ruta:</strong> <?php echo htmlspecialchars($vuelo_ida['origen_nombre'] . ' a ' . $vuelo_ida['destino_nombre']); ?></p>
        <p><strong>Vuelo:</strong> <?php echo htmlspecialchars($vuelo_ida['numero_vuelo']); ?></p>
        <p><strong>Salida:</strong> <?php echo htmlspecialchars($vuelo_ida['hora_salida']); ?></p>
    </fieldset>

    <?php if ($vuelo_regreso): ?>
    <fieldset class="resumen-card">
        <legend>Vuelo de Regreso</legend>
        <p><strong>Ruta:</strong> <?php echo htmlspecialchars($vuelo_regreso['origen_nombre'] . ' a ' . $vuelo_regreso['destino_nombre']); ?></p>
        <p><strong>Vuelo:</strong> <?php echo htmlspecialchars($vuelo_regreso['numero_vuelo']); ?></p>
        <p><strong>Salida:</strong> <?php echo htmlspecialchars($vuelo_regreso['hora_salida']); ?></p>
    </fieldset>
    <?php endif; ?>


    <fieldset class="resumen-card">
        <legend>Tarifa y Pasajeros</legend>
        <div class="tarifa-detalles">
            <p><strong>Clase:</strong> <span class="tag-clase"><?php echo htmlspecialchars($reserva['clase']); ?></span></p>
            <p><strong>Tarifa:</strong> <span class="tag-tarifa"><?php echo htmlspecialchars($reserva['tarifa']); ?></span></p>
        </div>
        <h4>Asignación de Asientos</h4>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Pasajero</th>
                        <th>Asiento Ida</th>
                        <?php if ($reserva['id_vuelo_regreso']): ?>
                            <th>Asiento Regreso</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pasajeros_con_asientos as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?></td>
                        <td><?php echo htmlspecialchars($p['asiento_ida']); ?></td>
                        <?php if ($reserva['id_vuelo_regreso']): ?>
                            <td><?php echo htmlspecialchars($p['asiento_regreso']); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </fieldset>

    <div class="total-y-pago">
        <div class="total-resumen">
            <h3>Detalles de Pago</h3>
            <ul class="desglose-precio">
                <li>
                    <span>Precio Base Vuelo (Clase <?php echo htmlspecialchars($reserva['clase']); ?>)</span>
                    <strong>$<?php echo number_format($precio_base_vuelo, 2); ?></strong>
                </li>
                <li>
                    <span>Tarifa '<?php echo htmlspecialchars($reserva['tarifa']); ?>' (x<?php echo $pasajeros_pagan; ?>)</span>
                    <strong>$<?php echo number_format($precio_tarifa_adicional, 2); ?></strong>
                </li>
                <li>
                    <span>TUA (Tarifa de Uso de Aeropuerto)</span>
                    <strong>$<?php echo number_format($tua_total, 2); ?></strong>
                </li>
            </ul>
            <div class="total-final">
                <span>Total a Pagar (MXN)</span>
                <strong>$<?php echo number_format($precio_final_calculado, 2); ?></strong>
            </div>
        </div>

        <form action="confirmarPago.php" method="POST" class="form-pago">
            <h3>Pago con Tarjeta</h3>
            
            <input type="hidden" name="id_reserva" value="<?php echo $id_reserva; ?>">
            <input type="hidden" name="sim_tarjeta" id="tarjeta_real">

            <div class="form-group">
                <label for="tarjeta_numero">Número de Tarjeta</label>
                <input type="text" id="tarjeta_numero" name="sim_tarjeta_visible" required 
                       placeholder="1234 5678 1234 5678"
                       oninput="validarTarjeta()"
                       onblur="enmascararTarjeta()">
            </div>
            
            <div class="form-group-inline">
                <div>
                    <label for="fecha_vencimiento">Fecha Exp (MM/AA)</label>
                    <input type="text" id="fecha_vencimiento" name="sim_fecha" required 
                           placeholder="MM/AA" 
                           oninput="validarFecha()">
                </div>
                <div>
                    <label for="cvv">CVV</label>
                    <input type="text" id="cvv" name="sim_cvv" required 
                           placeholder="123" 
                           oninput="validarCVV()">
                </div>
            </div>
            
            <button type="submit" id="btn_pagar" class="boton-pagar" disabled>
                Pagar y Confirmar Reserva
            </button>
        </form>
    </div>
</div>

<?php
// 4. Incluir el Footer (que ya incluye /public/js/main.js)
require_once 'src/Vistas/footer.php';
?>