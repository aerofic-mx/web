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
if (!isset($_SESSION['resultados_ida']) || !isset($_SESSION['busqueda']['total_pasajeros'])) {
    header("Location: index.php");
    exit;
}

$resultados = $_SESSION['resultados_ida'];
$busqueda = $_SESSION['busqueda'];

?>

<div class="results-container">
    <h2>Vuelos de Ida Disponibles</h2>
    <p>
        Mostrando vuelos para 
        <strong><?php echo htmlspecialchars($busqueda['total_pasajeros']); ?> pasajero(s)</strong>
        en clase <strong><?php echo htmlspecialchars($busqueda['clase']); ?></strong>
        el <strong><?php echo htmlspecialchars($busqueda['fecha_salida']); ?></strong>.
    </p>

    <hr>

    <?php if (empty($resultados)): ?>
        
        <div class="vuelo-item">
            <h3>No se encontraron vuelos</h3>
            <p>No hay vuelos disponibles que coincidan con tu búsqueda. Intenta con otra fecha u otro destino.</p>
            <a href="index.php">Volver a buscar</a>
        </div>

    <?php else: ?>

        <form action="procesarReserva.php" method="POST">
            
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
                            <strong>Vuelo:</strong> <?php echo htmlspecialchars($vuelo['numero_vuelo']); ?> |
                            <strong>Avión:</strong> <?php echo htmlspecialchars($vuelo['avion_modelo']); ?>
                        </p>
                        <p>
                            <strong>Salida:</strong> <?php echo htmlspecialchars($vuelo['hora_salida']); ?> |
                            <strong>Llegada:</strong> <?php echo htmlspecialchars($vuelo['hora_llegada']); ?>
                        </p>
                    </div>
                    <div class="vuelo-precio">
                        <strong>
                            $<?php echo number_format($precio_ajustado, 2); ?> MXN
                        </strong>
                        <p>Precio por pasajero (Clase <?php echo htmlspecialchars($busqueda['clase']); ?>)</p>
                        
                        <label>
                            <input type="radio" name="vuelo_ida_id" 
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