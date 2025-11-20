<?php
// 1. Configuración y Sesión
require_once 'src/config.php';
require_once 'src/Vistas/header.php';

// 2. Seguridad
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ---------------------------------------------------------
// LÓGICA 1: PROCESAR GUARDADO (Si se envió el formulario de cambios)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_guardar'])) {
    
    $id_reserva = $_POST['id_reserva'];
    $id_usuario = $_SESSION['user_id'];
    $asientos_ida = $_POST['asiento_ida'] ?? [];
    $asientos_regreso = $_POST['asiento_regreso'] ?? [];
    $id_vuelo_ida = $_POST['id_vuelo_ida'];
    $id_vuelo_regreso = $_POST['id_vuelo_regreso'];

    try {
        $pdo->beginTransaction();

        // A. Validar propiedad de la reserva
        $stmt_check = $pdo->prepare("SELECT id FROM reservas WHERE id = :id AND id_usuario = :uid");
        $stmt_check->execute(['id' => $id_reserva, 'uid' => $id_usuario]);
        if (!$stmt_check->fetch()) {
            throw new Exception("Reserva no válida o no autorizada.");
        }

        // B. Borrar asientos anteriores (Limpieza)
        $stmt_del = $pdo->prepare("DELETE FROM asientos_reservados WHERE id_reserva = :id_reserva");
        $stmt_del->execute(['id_reserva' => $id_reserva]);

        // C. Insertar nuevos asientos
        $sql_ins = "INSERT INTO asientos_reservados (id_reserva, id_vuelo, id_pasajero, numero_asiento) VALUES (:r, :v, :p, :s)";
        $stmt_ins = $pdo->prepare($sql_ins);

        // Vuelo Ida
        foreach ($asientos_ida as $id_pasajero => $asiento) {
            if (!empty($asiento)) {
                $stmt_ins->execute(['r' => $id_reserva, 'v' => $id_vuelo_ida, 'p' => $id_pasajero, 's' => $asiento]);
            }
        }
        // Vuelo Regreso
        if ($asientos_regreso && $id_vuelo_regreso) {
            foreach ($asientos_regreso as $id_pasajero => $asiento) {
                if (!empty($asiento)) {
                    $stmt_ins->execute(['r' => $id_reserva, 'v' => $id_vuelo_regreso, 'p' => $id_pasajero, 's' => $asiento]);
                }
            }
        }

        $pdo->commit();
        
        // Éxito: Redirigimos con una variable en la URL para mostrar el mensaje allá
        echo "<script>window.location.href='index.php?status=success_asientos';</script>";
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = $e->getMessage();
        if ($e->getCode() == 23000) {
            $error_msg = "Uno de los asientos seleccionados ya ha sido ocupado. Por favor intenta de nuevo.";
        }
        // El error se mostrará abajo en el HTML
    }
}

// Si hay error de guardado, usamos el ID que ya tenemos. Si no, viene del POST de index.
$id_reserva_raw = $_POST['id_reserva'] ?? ''; 
$id_usuario = $_SESSION['user_id'];

// Limpieza del ID (Quitar "AERO-" y ceros)
$id_reserva = (int) ltrim(strtoupper($id_reserva_raw), 'AERO-0');

$error_visual = isset($error_msg) ? $error_msg : ""; 
$mostrar_formulario = false;

if ($id_reserva > 0) {
    try {
        // 1. Buscar Reserva
        $stmt_res = $pdo->prepare("SELECT * FROM reservas WHERE id = :id AND id_usuario = :id_usuario");
        $stmt_res->execute(['id' => $id_reserva, 'id_usuario' => $id_usuario]);
        $reserva = $stmt_res->fetch();

        if (!$reserva) {
            throw new Exception("No se encontró la reserva.");
        }
        if ($reserva['estado'] == 'pendiente' || $reserva['estado'] == 'cancelada') {
            throw new Exception("No se pueden modificar asientos de una reserva '" . ucfirst($reserva['estado']) . "'.");
        }

        $id_vuelo_ida = $reserva['id_vuelo_ida'];
        $id_vuelo_regreso = $reserva['id_vuelo_regreso'];

        // 2. Funciones Helper para obtener datos del avión
        function getCapacidad($pdo, $id) {
            $s = $pdo->prepare("SELECT a.capacidad_total FROM aviones a JOIN vuelos v ON v.id_avion = a.id WHERE v.id = ?");
            $s->execute([$id]); 
            return $s->fetchColumn();
        }
        function getOcupadosOtros($pdo, $id_vuelo, $mi_reserva) {
            $s = $pdo->prepare("SELECT numero_asiento FROM asientos_reservados WHERE id_vuelo = ? AND id_reserva != ?");
            $s->execute([$id_vuelo, $mi_reserva]);
            return $s->fetchAll(PDO::FETCH_COLUMN, 0);
        }

        // Datos Ida
        $filas_ida = ceil(getCapacidad($pdo, $id_vuelo_ida) / 6);
        $ocupados_ida_otros = getOcupadosOtros($pdo, $id_vuelo_ida, $id_reserva);

        // Datos Regreso
        $filas_regreso = 0;
        $ocupados_regreso_otros = [];
        if ($id_vuelo_regreso) {
            $filas_regreso = ceil(getCapacidad($pdo, $id_vuelo_regreso) / 6);
            $ocupados_regreso_otros = getOcupadosOtros($pdo, $id_vuelo_regreso, $id_reserva);
        }

        // 3. Obtener Pasajeros y sus asientos ACTUALES
        $sql_pas = "SELECT p.id, p.nombre, p.apellido,
                    (SELECT numero_asiento FROM asientos_reservados ar WHERE ar.id_pasajero = p.id AND ar.id_vuelo = :vi) as asiento_ida,
                    (SELECT numero_asiento FROM asientos_reservados ar WHERE ar.id_pasajero = p.id AND ar.id_vuelo = :vr) as asiento_regreso
                    FROM pasajeros p WHERE p.id_reserva = :r";
        $stmt_pas = $pdo->prepare($sql_pas);
        $stmt_pas->execute(['r' => $id_reserva, 'vi' => $id_vuelo_ida, 'vr' => $id_vuelo_regreso]);
        $pasajeros = $stmt_pas->fetchAll();

        // Extraer mis asientos para marcarlos en verde
        $mis_asientos_ida = array_column($pasajeros, 'asiento_ida');
        $mis_asientos_regreso = array_column($pasajeros, 'asiento_regreso');

        $mostrar_formulario = true;

    } catch (Exception $e) {
        $error_visual = $e->getMessage();
    }
} else {
    $error_visual = "Código de reserva inválido.";
}

// Función mapa
function renderizarMapa($filas, $ocupados, $mios, $tipo) {
    $letras = ['A','B','C','D','E','F'];
    echo '<div class="cabin-map" data-vuelo-tipo="'.$tipo.'">';
    // Header
    echo '<div class="seat-row header"><div class="seat-label"></div>';
    foreach ($letras as $i => $l) { 
        echo '<div class="seat-label">'.$l.'</div>'; 
        if($i==2) echo '<div class="aisle-label"></div>'; 
    }
    echo '</div>';
    // Filas
    for ($i=1; $i<=$filas; $i++) {
        echo '<div class="seat-row"><div class="seat-label">'.$i.'</div>';
        foreach ($letras as $j => $l) {
            $num = $i.$l;
            $clase = 'seat';
            $disabled = '';
            if (in_array($num, $ocupados)) { $clase .= ' occupied'; $disabled = 'disabled'; }
            elseif (in_array($num, $mios)) { $clase .= ' selected'; } // Marca mis asientos actuales
            
            echo '<button type="button" class="'.$clase.'" data-seat-num="'.$num.'" '.$disabled.'></button>';
            if ($j==2) echo '<div class="aisle"></div>';
        }
        echo '</div>';
    }
    echo '</div>';
}
?>

<!-- VISTA HTML -->
<div class="asientos-container container">
    
    <?php if ($error_visual): ?>
        <div class="container-mensaje">
            <h2 class="titulo-error">Error</h2>
            <p class="mensaje-texto"><?php echo htmlspecialchars($error_visual); ?></p>
            <a href="index.php" class="btn-volver">Volver al Inicio</a>
        </div>
    <?php endif; ?>

    <?php if ($mostrar_formulario): ?>
        <h2>Modificar Asientos</h2>
        <p style="text-align:center; color:#666;">Reserva: <strong><?php echo htmlspecialchars($id_reserva_raw); ?></strong></p>

        <!-- Formulario que se envía a SÍ MISMO -->
        <form id="formAsientos" action="modificarAsientos.php" method="POST">
            <!-- Campo oculto para activar la lógica de guardado -->
            <input type="hidden" name="accion_guardar" value="1">
            
            <input type="hidden" name="id_reserva" value="<?php echo $id_reserva; ?>">
            <input type="hidden" name="id_vuelo_ida" value="<?php echo $id_vuelo_ida; ?>">
            <input type="hidden" name="id_vuelo_regreso" value="<?php echo $id_vuelo_regreso; ?>">

            <!-- 1. Lista de Pasajeros -->
            <fieldset class="resumen-card">
                <legend>1. Selecciona un pasajero</legend>
                <div class="passenger-list">
                    <?php foreach ($pasajeros as $p): ?>
                        <div class="passenger-list-item" data-pasajero-id="<?php echo $p['id']; ?>">
                            <div class="passenger-name"><?php echo htmlspecialchars($p['nombre'].' '.$p['apellido']); ?></div>
                            
                            <!-- IDA -->
                            <div class="passenger-seat-assignment">
                                <strong>Ida:</strong> 
                                <span id="display_ida_pasajero_<?php echo $p['id']; ?>">
                                    <?php echo $p['asiento_ida'] ? $p['asiento_ida'] : 'Sin asiento'; ?>
                                </span>
                                <input type="hidden" id="asiento_ida_pasajero_<?php echo $p['id']; ?>" 
                                       name="asiento_ida[<?php echo $p['id']; ?>]" 
                                       value="<?php echo $p['asiento_ida']; ?>">
                            </div>

                            <!-- REGRESO -->
                            <?php if ($id_vuelo_regreso): ?>
                            <div class="passenger-seat-assignment">
                                <strong>Regreso:</strong> 
                                <span id="display_regreso_pasajero_<?php echo $p['id']; ?>">
                                    <?php echo $p['asiento_regreso'] ? $p['asiento_regreso'] : 'Sin asiento'; ?>
                                </span>
                                <input type="hidden" id="asiento_regreso_pasajero_<?php echo $p['id']; ?>" 
                                       name="asiento_regreso[<?php echo $p['id']; ?>]" 
                                       value="<?php echo $p['asiento_regreso']; ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <!-- 2. Mapa IDA -->
            <fieldset class="resumen-card">
                <legend>2. Asientos Vuelo de Ida</legend>
                <div class="seat-legend">
                    <div class="seat available"></div> Disponible
                    <div class="seat occupied"></div> Ocupado (Otros)
                    <div class="seat selected"></div> Tu Selección
                </div>
                <?php renderizarMapa($filas_ida, $ocupados_ida_otros, $mis_asientos_ida, 'ida'); ?>
            </fieldset>

            <!-- 3. Mapa REGRESO -->
            <?php if ($id_vuelo_regreso): ?>
                <fieldset class="resumen-card">
                    <legend>3. Asientos Vuelo de Regreso</legend>
                    <div class="seat-legend">
                        <div class="seat available"></div> Disponible
                        <div class="seat occupied"></div> Ocupado
                        <div class="seat selected"></div> Tu Selección
                    </div>
                    <?php renderizarMapa($filas_regreso, $ocupados_regreso_otros, $mis_asientos_regreso, 'regreso'); ?>
                </fieldset>
            <?php endif; ?>

            <button type="submit" id="btnConfirmarAsientos" class="boton-continuar">Guardar Cambios</button>
        </form>

    <?php endif; ?>
</div>

<?php require_once 'src/Vistas/footer.php'; ?>