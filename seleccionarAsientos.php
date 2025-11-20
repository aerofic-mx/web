<?php
// 1. Incluir config y header (inicia sesión)
require_once 'src/config.php';
require_once 'src/Vistas/header.php';

// 2. Seguridad: Proteger la página
if (!isset($_SESSION['user_id']) || !isset($_SESSION['id_reserva_activa'])) {
    header("Location: login.php");
    exit;
}

// 3. Obtener datos clave de la sesión y BD
$id_reserva = $_SESSION['id_reserva_activa'];
$ids_pasajeros = $_SESSION['ids_pasajeros']; // Array de IDs de pasajeros

try {
    // Obtenemos la reserva para encontrar los IDs de vuelo
    $stmt_res = $pdo->prepare("SELECT id_vuelo_ida, id_vuelo_regreso FROM reservas WHERE id = :id_reserva");
    $stmt_res->execute(['id_reserva' => $id_reserva]);
    $reserva = $stmt_res->fetch();

    $id_vuelo_ida = $reserva['id_vuelo_ida'];
    $id_vuelo_regreso = $reserva['id_vuelo_regreso'];

    // --- Lógica para Asientos de IDA ---
    
    // 4. Obtener la capacidad del avión del vuelo de IDA
    $stmt_avion_ida = $pdo->prepare("
        SELECT a.capacidad_total 
        FROM aviones a
        JOIN vuelos v ON v.id_avion = a.id
        WHERE v.id = :id_vuelo
    ");
    $stmt_avion_ida->execute(['id_vuelo' => $id_vuelo_ida]);
    $capacidad_ida = $stmt_avion_ida->fetchColumn();
    $filas_ida = ceil($capacidad_ida / 6); // 6 asientos por fila (A-F)

    // 5. Obtener los asientos ya ocupados en el vuelo de IDA
    $stmt_ocupados_ida = $pdo->prepare("SELECT numero_asiento FROM asientos_reservados WHERE id_vuelo = :id_vuelo");
    $stmt_ocupados_ida->execute(['id_vuelo' => $id_vuelo_ida]);
    $ocupados_ida = $stmt_ocupados_ida->fetchAll(PDO::FETCH_COLUMN, 0);

    
    // --- Lógica para Asientos de REGRESO (si existe) ---
    $capacidad_regreso = 0;
    $filas_regreso = 0;
    $ocupados_regreso = [];

    if ($id_vuelo_regreso) {
        $stmt_avion_reg = $pdo->prepare("
            SELECT a.capacidad_total 
            FROM aviones a
            JOIN vuelos v ON v.id_avion = a.id
            WHERE v.id = :id_vuelo
        ");
        $stmt_avion_reg->execute(['id_vuelo' => $id_vuelo_regreso]);
        $capacidad_regreso = $stmt_avion_reg->fetchColumn();
        $filas_regreso = ceil($capacidad_regreso / 6);

        $stmt_ocupados_reg = $pdo->prepare("SELECT numero_asiento FROM asientos_reservados WHERE id_vuelo = :id_vuelo");
        $stmt_ocupados_reg->execute(['id_vuelo' => $id_vuelo_regreso]);
        $ocupados_regreso = $stmt_ocupados_reg->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    // 6. Obtener datos de los pasajeros (sus nombres)
    $sql_pasajeros = "SELECT id, nombre, apellido FROM pasajeros WHERE id IN (";
    $placeholders = implode(',', array_fill(0, count($ids_pasajeros), '?'));
    $sql_pasajeros .= $placeholders . ")";
    $stmt_pasajeros = $pdo->prepare($sql_pasajeros);
    $stmt_pasajeros->execute($ids_pasajeros);
    $pasajeros = $stmt_pasajeros->fetchAll();

} catch (Exception $e) {
    echo "Error al cargar datos de asientos: " . $e->getMessage();
    exit;
}

/**
 * Función para renderizar el mapa de asientos
 *
 * @param int $filas Número de filas del avión
 * @param array $ocupados Array de asientos ocupados (ej. ['1A', '2B'])
 * @param string $tipoVuelo 'ida' o 'regreso'
 */
function renderizarMapaAsientos($filas, $ocupados, $tipoVuelo) {
    $letras = ['A', 'B', 'C', 'D', 'E', 'F'];
    echo '<div class="cabin-map" data-vuelo-tipo="' . $tipoVuelo . '">';
    
    // Renderizar cabecera de letras (A, B, C...)
    echo '<div class="seat-row header">';
    echo '<div class="seat-label"></div>'; // Espacio para el número de fila
    foreach ($letras as $i => $letra) {
        echo '<div class="seat-label">' . $letra . '</div>';
        if ($i == 2) { // Pasillo
            echo '<div class="aisle-label"></div>';
        }
    }
    echo '</div>';

    // Renderizar filas de asientos
    for ($i = 1; $i <= $filas; $i++) {
        echo '<div class="seat-row">';
        echo '<div class="seat-label">' . $i . '</div>'; // Número de fila
        foreach ($letras as $j => $letra) {
            $asiento_num = $i . $letra;
            $clase = 'seat';
            $disabled = '';
            
            if (in_array($asiento_num, $ocupados)) {
                $clase .= ' occupied'; // Ocupado por otra persona
                $disabled = 'disabled';
            }

            echo '<button type="button" class="' . $clase . '" data-seat-num="' . $asiento_num . '" ' . $disabled . '></button>';
            
            if ($j == 2) { // Pasillo después de C
                echo '<div class="aisle"></div>';
            }
        }
        echo '</div>';
    }
    echo '</div>'; // fin .cabin-map
}

?>

<div class="asientos-container container">
    <h2>Selección de Asientos</h2>

    <form id="formAsientos" action="guardarAsientos.php" method="POST">
        <input type="hidden" name="id_reserva" value="<?php echo $id_reserva; ?>">

        <!-- 1. Lista de Pasajeros -->
        <fieldset class="resumen-card">
            <legend>1. Selecciona un pasajero</legend>
            <p>Haz clic en un pasajero y luego selecciona un asiento en el mapa.</p>
            <div class="passenger-list">
                <?php foreach ($pasajeros as $pasajero): ?>
                    <div class="passenger-list-item" data-pasajero-id="<?php echo $pasajero['id']; ?>">
                        <div class="passenger-name"><?php echo htmlspecialchars($pasajero['nombre'] . ' ' . $pasajero['apellido']); ?></div>
                        
                        <!-- Asignaciones de asientos (JS las llenará) -->
                        <div class="passenger-seat-assignment">
                            <strong>Ida:</strong> 
                            <span id="display_ida_pasajero_<?php echo $pasajero['id']; ?>">No seleccionado</span>
                            <!-- Input oculto que se enviará -->
                            <input type="hidden" id="asiento_ida_pasajero_<?php echo $pasajero['id']; ?>" 
                                   name="asiento_ida[<?php echo $pasajero['id']; ?>]">
                        </div>
                        <?php if ($id_vuelo_regreso): ?>
                        <div class="passenger-seat-assignment">
                            <strong>Regreso:</strong> 
                            <span id="display_regreso_pasajero_<?php echo $pasajero['id']; ?>">No seleccionado</span>
                            <input type="hidden" id="asiento_regreso_pasajero_<?php echo $pasajero['id']; ?>" 
                                   name="asiento_regreso[<?php echo $pasajero['id']; ?>]">
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <!-- 2. Mapa de Asientos (Vuelo de Ida) -->
        <fieldset class="resumen-card">
            <legend>2. Asientos Vuelo de Ida</legend>
            <div class="seat-legend">
                <div class="seat available"></div> Disponible
                <div class="seat occupied"></div> Ocupado
                <div class="seat selected"></div> Tu Selección
            </div>
            <?php renderizarMapaAsientos($filas_ida, $ocupados_ida, 'ida'); ?>
        </fieldset>

        <!-- 3. Mapa de Asientos (Vuelo de Regreso) -->
        <?php if ($id_vuelo_regreso): ?>
            <fieldset class="resumen-card">
                <legend>3. Asientos Vuelo de Regreso</legend>
                <div class="seat-legend">
                    <div class="seat available"></div> Disponible
                    <div class="seat occupied"></div> Ocupado
                    <div class="seat selected"></div> Tu Selección
                </div>
                <?php renderizarMapaAsientos($filas_regreso, $ocupados_regreso, 'regreso'); ?>
            </fieldset>
        <?php endif; ?>

        <button type="submit" id="btnConfirmarAsientos" class="boton-continuar">Confirmar Asientos e Ir al Pago</button>
    </form>
</div>

<?php
// 4. Incluir el Footer (donde se carga main.js)
require_once 'src/Vistas/footer.php';
?>