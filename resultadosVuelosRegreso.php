<?php
// 1. Incluir config y header (esto inicia la sesión)
require_once 'src/config.php';
require_once 'src/Vistas/header.php';

// 2. Seguridad: Proteger la página
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 3. Obtener resultados de la sesión
// ¡AQUÍ ESTÁ LA CORRECCIÓN! (Validamos con 'total_pasajeros')
if (!isset($_SESSION['resultados_regreso']) || !isset($_SESSION['busqueda']['total_pasajeros'])) {
    header("Location: index.php");
    exit;
}

$resultados = $_SESSION['resultados_regreso'];
$busqueda = $_SESSION['busqueda'];

// Datos del vuelo de IDA que ya seleccionó (para mostrarlos)
$seleccion_ida = $_SESSION['seleccion_ida']; 

?>

<div class="results-container container">
    <h2>Vuelos de Regreso Disponibles</h2>
    <p>
        Mostrando vuelos para 
        <!-- ¡AQUÍ ESTÁ LA CORRECCIÓN! (Línea 31 aprox) -->
        <strong><?php echo htmlspecialchars($busqueda['total_pasajeros']); ?> pasajero(s)</strong>
        en clase <strong><?php echo htmlspecialchars($busqueda['clase']); ?></strong>
        el <strong><?php echo htmlspecialchars($busqueda['fecha_regreso']); ?></strong>.
    </p>

    <!-- Mostramos un resumen del vuelo de ida seleccionado -->
    <div class="vuelo-item-resumen">
        <strong>Vuelo de Ida Seleccionado:</strong>
        ID #<?php echo htmlspecialchars($seleccion_ida['id_vuelo']); ?> | 
        Precio Base: $<?php echo number_format($seleccion_ida['precio_base'], 2); ?> MXN
    </div>

    <hr>

    <?php if (empty($resultados)): ?>
        
        <!-- Mensaje si no se encontraron vuelos -->
        <div class="vuelo-item">
            <h3>No se encontraron vuelos de regreso</h3>
            <p>No hay vuelos de regreso disponibles para esta ruta y fecha.</p>
            <a href="index.php">Cancelar y Volver a buscar</a>
        </div>

    <?php else: ?>

        <!-- Formulario que envuelve todos los resultados -->
        <form action="procesarReservaRedonda.php" method="POST">
            
            <?php foreach ($resultados as $vuelo): 
                
                // ¡Lógica de precio de clase! (solo para mostrar)
                $precio_base = $vuelo['precio_base'];
                $multiplicador_clase = 1.0;
                if ($busqueda['clase'] == 'Business') {
                    $multiplicador_clase = 1.8;
                } elseif ($busqueda['clase'] == 'Primera') {
                    $multiplicador_clase = 2.5;
                }
                $precio_ajustado = $precio_base * $multiplicador_clase;
            
            ?>
                <div class="vuelo-item">
                    <div class="vuelo-info">
                        <h3><?php echo htmlspecialchars($vuelo['origen_nombre']); ?> 
                            a 
                            <?php echo htmlspecialchars($vuelo['destino_nombre']); ?>
                        </h3>
                        <p>
                            <strong>Vuelo:</strong> <?php echo htmlspecialchars($vuelo['numero_vuelo']); ?>
                        </p>
                        <p>
                            <strong>Salida:</strong> <?php echo htmlspecialchars($vuelo['hora_salida']); ?> |
                            <strong>Llegada:</strong> <?php echo htmlspecialchars($vuelo['hora_llegada']); ?>
                        </p>
                    </div>
                    <div class="vuelo-precio">
                        <strong>
                            <!-- Mostramos el precio ya ajustado por clase -->
                            $<?php echo number_format($precio_ajustado, 2); ?> MXN
                        </strong>
                        <p>Precio por pasajero (Clase <?php echo htmlspecialchars($busqueda['clase']); ?>)</p>
                        
                        <label>
                            <input type="radio" name="vuelo_regreso_id" 
                                   value="<?php echo $vuelo['vuelo_id']; ?>" required>
                            Seleccionar este vuelo
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="boton-continuar">Continuar con la Reserva</button>
        
        </form>

    <?php endif; ?>

</div>

<?php
// 4. Incluir el Footer
require_once 'src/Vistas/footer.php';
?>