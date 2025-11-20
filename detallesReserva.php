<?php
// 1. Incluir config y header (inicia sesión)
require_once 'src/config.php';
require_once 'src/Vistas/header.php';

// 2. Seguridad: Proteger la página
if (!isset($_SESSION['user_id']) || !isset($_SESSION['id_reserva_activa'])) {
    header("Location: login.php?error=no_autorizado");
    exit;
}

// 3. Obtener datos de la sesión y BD
try {
    $id_reserva = $_SESSION['id_reserva_activa'];

    // Consultamos la reserva que acabamos de crear
    $stmt_res = $pdo->prepare("SELECT * FROM reservas WHERE id = :id_reserva AND id_usuario = :id_usuario");
    $stmt_res->execute(['id_reserva' => $id_reserva, 'id_usuario' => $_SESSION['user_id']]);
    $reserva = $stmt_res->fetch();

    if (!$reserva) {
        throw new Exception("Reserva no encontrada.");
    }

    // Calculamos los pasajeros que pagan (Adultos + Niños)
    $pasajeros_que_pagan = $reserva['adultos'] + $reserva['ninos'];

    // Obtenemos los detalles del vuelo de ida
    $stmt_ida = $pdo->prepare("
        SELECT v.hora_salida, a_origen.ciudad as origen_ciudad, a_destino.ciudad as destino_ciudad
        FROM vuelos v
        JOIN aeropuertos a_origen ON v.aeropuerto_origen_id = a_origen.id
        JOIN aeropuertos a_destino ON v.aeropuerto_destino_id = a_destino.id
        WHERE v.id = :id_vuelo_ida
    ");
    $stmt_ida->execute(['id_vuelo_ida' => $reserva['id_vuelo_ida']]);
    $vuelo_ida = $stmt_ida->fetch();

} catch (Exception $e) {
    // Si hay un error, lo mostramos
    echo '<div class="error-container error">Error al cargar la reserva: ' . $e->getMessage() . '</div>';
    require_once 'src/Vistas/footer.php';
    exit;
}
?>

<div class="detalles-container container">
    <h2>Detalles de tu Viaje</h2>

    <fieldset class="resumen-card">
        <legend>Resumen del Vuelo</legend>
        <div class="tarifa-detalles">
            <p><strong>Ruta:</strong> <?php echo htmlspecialchars($vuelo_ida['origen_ciudad']); ?> a <?php echo htmlspecialchars($vuelo_ida['destino_ciudad']); ?></p>
            <p><strong>Salida:</strong> <?php echo htmlspecialchars($vuelo_ida['hora_salida']); ?></p>
            <p><strong>Clase:</strong> <?php echo htmlspecialchars($reserva['clase']); ?></p>
            <p><strong>Pasajeros:</strong> <?php echo htmlspecialchars($reserva['total_pasajeros']); ?> </p>
            <p><strong>Subtotal Vuelo:</strong> $<span id="precioBaseReserva"><?php echo number_format($reserva['precio_final'], 2); ?></span> MXN</p>
        </div>
    </fieldset>

    <h3 style="text-align: center; margin-top: 2rem;">Selecciona tu tarifa</h3>
    <section class="tarifa-container">
        <div class="tarifa">
            <h3>Zero</h3>
            <ul>
                <li>Artículo personal</li>
                <li>Web check-in (72 h antes)</li>
                <li>Sin equipaje de mano ni documentado</li>
            </ul>
            <p class="precio">$0 MXN</p>
            <button onclick="seleccionarTarifa('Zero', 0)">Seleccionar Zero</button>
        </div>
        <div class="tarifa">
            <h3>Light</h3>
            <ul>
                <li>Artículo personal</li>
                <li>Web check-in (72 h antes)</li>
                <li>10 kg en maleta de mano</li>
            </ul>
            <p class="precio">+$603 MXN / pasajero</p>
            <button onclick="seleccionarTarifa('Light', 603)">Seleccionar Light</button>
        </div>
        <div class="tarifa">
            <h3>Smart</h3>
            <ul>
                <li>Artículo personal</li>
                <li>Web check-in (10 días antes)</li>
                <li>15 kg en maleta de mano</li>
                <li>25 kg de equipaje documentado</li>
            </ul>
            <p class="precio">+$1,250 MXN / pasajero</p>
            <button onclick="seleccionarTarifa('Smart', 1250)">Seleccionar Smart</button>
        </div>
    </section>

    <section id="detalleTotal" style="display: none;">
        <h3>Tu selección</h3>
        <p id="tarifaSeleccionada"></p>
        <p id="costoAdicional"></p>
        <p id="costoTotal"></p>

        <form action="guardarTarifa.php" method="POST">
            <input type="hidden" name="id_reserva" value="<?php echo $reserva['id']; ?>">
            <input type="hidden" name="tarifa_seleccionada" id="tarifaSeleccionadaInput">
            <input type="hidden" name="precio_adicional_total" id="precioAdicionalInput">
            
            <button type="submit" id="continuar" class="boton-continuar">Continuar a Selección de Asientos</button>
        </form>
    </section>
</div>

<script>
// JS adaptado para nuestro proyecto
function seleccionarTarifa(tarifa, precioTarifaPorPasajero) {
    // Obtenemos los valores que PHP inyectó en la página
    var precioBaseVuelo = parseFloat(document.getElementById("precioBaseReserva").textContent.replace(/,/g, ''));
    var pasajerosQuePagan = <?php echo $pasajeros_que_pagan; ?>;
    
    // Calculamos el costo adicional
    var costoAdicionalTotal = precioTarifaPorPasajero * pasajerosQuePagan;
    
    // Calculamos el nuevo total
    var costoTotal = precioBaseVuelo + costoAdicionalTotal;

    // Mostrar detalles
    document.getElementById("detalleTotal").style.display = "block";
    document.getElementById("tarifaSeleccionada").textContent = "Tarifa seleccionada: " + tarifa;
    document.getElementById("costoAdicional").textContent = "Costo adicional de tarifa: $" + costoAdicionalTotal.toFixed(2) + " MXN";
    document.getElementById("costoTotal").textContent = "Nuevo costo total: $" + costoTotal.toFixed(2) + " MXN";

    // Llenar campos ocultos del formulario
    document.getElementById("tarifaSeleccionadaInput").value = tarifa;
    document.getElementById("precioAdicionalInput").value = costoAdicionalTotal;
}
</script>

<?php
// 4. Incluir el Footer
require_once 'src/Vistas/footer.php';
?>