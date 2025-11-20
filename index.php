<?php
// 1. Incluir la conexión a la BD y el Header
require_once 'src/config.php';       // $pdo
require_once 'src/Vistas/header.php'; // HTML <head> y <nav>

// 2. GUARDIA DE SEGURIDAD
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 3. Lógica de la página: Obtener aeropuertos
$aeropuertos = [];
try {
    $stmt = $pdo->query("SELECT id, codigo_iata, nombre, ciudad FROM aeropuertos ORDER BY ciudad");
    $aeropuertos = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error al cargar aeropuertos: " . $e->getMessage();
}
?>

<div class="search-container emirates-style">
    <!-- NOTIFICACIÓN FLOTANTE (TOAST) -->
    <!-- Se muestra solo si la URL tiene ?status=success_asientos -->
    <?php if (isset($_GET['status']) && $_GET['status'] == 'success_asientos'): ?>
        <div id="toast-notification" class="toast success show">
            <div class="toast-content">
                <i class="fa-solid fa-circle-check toast-icon"></i>
                <div class="toast-message">
                    <span class="text text-1">¡Éxito!</span>
                    <span class="text text-2">Tus asientos han sido actualizados correctamente.</span>
                </div>
            </div>
            <div class="toast-progress"></div>
        </div>
    <?php endif; ?>

    <div class="tab-container">
        <button id="btnMostrarBuscar" class="tab-button active">
            <i class="fa-solid fa-plane-departure"></i> Buscar Vuelos
        </button>
        <button id="btnMostrarGestionar" class="tab-button">
            <i class="fa-solid fa-briefcase"></i> Gestionar Vuelos
        </button>
    </div>

    <div id="seccionBuscar" class="tab-content">
        <h2>Busque su próximo vuelo</h2>
        
        <form action="buscarVuelos.php" method="POST" class="flight-search-form">
            
            <!-- Popover para Aeropuertos -->
            <div class="form-group-inline">
                <div class="form-group airport-selector-group">
                    <label for="origen-trigger">Aeropuerto de salida:</label>
                    <button type="button" id="origen-trigger" class="input-like-button popover-trigger" data-target-popover="origen-popover">
                        <span id="origen-display-text">Selecciona un origen</span>
                        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <input type="hidden" name="origen_id" id="origen_id" value="" required>
                    <div id="origen-popover" class="airport-popover popover-panel">
                        <input type="text" class="airport-search" data-target-list="origen-list" placeholder="Buscar aeropuerto...">
                        <ul id="origen-list" class="airport-list">
                            <?php foreach ($aeropuertos as $a): ?>
                                <li data-value="<?php echo $a['id']; ?>" data-text="<?php echo htmlspecialchars($a['ciudad']) . " (" . $a['codigo_iata'] . ")"; ?>">
                                    <strong><?php echo htmlspecialchars($a['ciudad']); ?></strong> (<?php echo $a['codigo_iata']; ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="form-group airport-selector-group">
                    <label for="destino-trigger">Aeropuerto de llegada:</label>
                    <button type="button" id="destino-trigger" class="input-like-button popover-trigger" data-target-popover="destino-popover">
                        <span id="destino-display-text">Selecciona un destino</span>
                        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <input type="hidden" name="destino_id" id="destino_id" value="" required>
                    <div id="destino-popover" class="airport-popover popover-panel">
                        <input type="text" class="airport-search" data-target-list="destino-list" placeholder="Buscar aeropuerto...">
                        <ul id="destino-list" class="airport-list">
                            <?php foreach ($aeropuertos as $a): ?>
                                <li data-value="<?php echo $a['id']; ?>" data-text="<?php echo htmlspecialchars($a['ciudad']) . " (" . $a['codigo_iata'] . ")"; ?>">
                                    <strong><?php echo htmlspecialchars($a['ciudad']); ?></strong> (<?php echo $a['codigo_iata']; ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Fechas -->
            <div class="form-group-inline fechas-vuelo">
                <div class="form-group">
                    <label for="fecha_salida">Salida:</label>
                    <input type="text" id="fecha_salida" name="fecha_salida" class="datepicker" 
                           placeholder="Selecc. Fecha" required 
                           autocomplete="off">
                </div>
                <div class="form-group" id="campo_fecha_regreso" style="display: none;">
                    <label for="fecha_regreso">Regreso:</label>
                    <input type="text" id="fecha_regreso" name="fecha_regreso" class="datepicker" 
                           placeholder="Selecc. Fecha" 
                           autocomplete="off">
                </div>
            </div>

            <!-- Pasajeros y Clase -->
            <div class="form-group-inline pasajeros-clase">
                <!-- Pasajeros -->
                <div class="form-group passenger-selector-group">
                    <label for="passenger-trigger">Pasajeros</label>
                    <button type="button" id="passenger-trigger" class="input-like-button popover-trigger" data-target-popover="passenger-popover">
                        <span id="passenger-display-text">1 Adulto</span>
                        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <div id="passenger-popover" class="passenger-popover popover-panel">
                        <div class="passenger-row">
                            <div><label for="input_adultos">Adulto</label><span class="age-range">Edad 12+</span></div>
                            <div class="stepper-input">
                                <button type="button" class="stepper-btn decrement" data-target="input_adultos">-</button>
                                <input type="number" id="input_adultos" value="1" min="1" max="9" data-min="1" data-max="9" readonly>
                                <button type="button" class="stepper-btn increment" data-target="input_adultos">+</button>
                            </div>
                        </div>
                        <div class="passenger-row">
                            <div><label for="input_ninos">Niño</label><span class="age-range">Edad 2-11</span></div>
                            <div class="stepper-input">
                                <button type="button" class="stepper-btn decrement" data-target="input_ninos">-</button>
                                <input type="number" id="input_ninos" value="0" min="0" max="9" data-min="0" data-max="9" readonly>
                                <button type="button" class="stepper-btn increment" data-target="input_ninos">+</button>
                            </div>
                        </div>
                        <div class="passenger-row">
                            <div><label for="input_bebes">Bebé</label><span class="age-range">Menos de 2 años</span></div>
                            <div class="stepper-input">
                                <button type="button" class="stepper-btn decrement" data-target="input_bebes">-</button>
                                <input type="number" id="input_bebes" value="0" min="0" max="9" data-min="0" data-max="9" readonly>
                                <button type="button" class="stepper-btn increment" data-target="input_bebes">+</button>
                            </div>
                        </div>
                        <p class="passenger-note">Puede reservar para un máximo de 9 pasajeros.</p>
                    </div>
                    <input type="hidden" id="adultos" name="adultos" value="1">
                    <input type="hidden" id="ninos" name="ninos" value="0">
                    <input type="hidden" id="bebes" name="bebes" value="0">
                </div>
                
                <!-- Clase -->
                <div class="form-group airport-selector-group">
                    <label for="clase-trigger">Clase</label>
                    <button type="button" id="clase-trigger" class="input-like-button popover-trigger" data-target-popover="clase-popover">
                        <span id="clase-display-text">Turista</span>
                        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <input type="hidden" name="clase" id="clase_id" value="Turista" required>
                    <div id="clase-popover" class="airport-popover popover-panel">
                        <ul id="clase-list" class="airport-list">
                            <li data-value="Turista" data-text="Turista"><strong>Turista</strong></li>
                            <li data-value="Business" data-text="Business"><strong>Business</strong></li>
                            <li data-value="Primera" data-text="Primera Clase"><strong>Primera Clase</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Tipo de Vuelo -->
            <div class="form-group-inline tipo-vuelo-wrapper">
                <div class="form-group airport-selector-group">
                    <label for="tipo_vuelo-trigger">Tipo de Vuelo:</label>
                    <button type="button" id="tipo_vuelo-trigger" class="input-like-button popover-trigger" data-target-popover="tipo_vuelo-popover">
                        <span id="tipo_vuelo-display-text">Viaje Sencillo</span>
                        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <input type="hidden" name="tipo_vuelo" id="tipo_vuelo_id" value="sencillo" required>
                    <div id="tipo_vuelo-popover" class="airport-popover popover-panel">
                        <ul id="tipo_vuelo-list" class="airport-list">
                            <li data-value="sencillo" data-text="Viaje Sencillo"><strong>Viaje Sencillo</strong></li>
                            <li data-value="redondo" data-text="Viaje Redondo"><strong>Viaje Redondo</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Botón de Búsqueda -->
            <button type="submit" class="boton-buscar-vuelos">Buscar Vuelos</button>
        </form>
    </div>

    <div id="seccionGestionar" class="tab-content" style="display: none;">
        <h2>Gestiona tu reserva</h2>
        
        <form method="POST" class="gestion-form">
            
            <div class="form-group">
                <label for="usuario_gestion">Nombre de Usuario:</label>
                <!-- Pre-llenamos el nombre y lo ponemos como solo lectura -->
                <input type="text" id="usuario_gestion" name="username" 
                       value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="id_reserva_gestion">Código de Reserva (ID):</label>
                <!-- El usuario debe ingresar el ID que le dimos en la confirmación -->
                <input type="text" id="id_reserva_gestion" name="id_reserva" 
                       placeholder="Ej: AERO-000123" required>
            </div>
            
            <div class="gestion-botones">
                <!-- 
                  El atributo 'formaction' le dice a CADA botón a qué archivo
                  debe enviar el formulario.
                -->
                <button type="submit" formaction="checkIn.php" class="boton-checkin">
                    Check-in
                </button>
                <button type="submit" formaction="modificarAsientos.php" class="boton-modificar">
                    Cambiar Asientos
                </button>
            </div>

        </form>
    </div>

</div>
<?php
// 5. Incluir el Footer
require_once 'src/Vistas/footer.php';
?>