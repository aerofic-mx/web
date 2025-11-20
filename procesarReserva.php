<?php
/*
 * =================================
 * PROCESADOR DE RESERVA (ENRUTADOR) (Corregido)
 * =================================
 */

// 1. Iniciar sesión y cargar la BD
session_start();
require_once 'src/config.php'; // $pdo

// 2. Seguridad
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['vuelo_ida_id'])) {
    header("Location: index.php");
    exit;
}

// 3. Obtener datos
$id_vuelo_ida = $_POST['vuelo_ida_id'];
$busqueda = $_SESSION['busqueda'];
$id_usuario = $_SESSION['user_id'];

// 4. Leer los nuevos datos de la sesión
$clase = $busqueda['clase'];
$adultos = $busqueda['adultos'];
$ninos = $busqueda['ninos'];
$bebes = $busqueda['bebes'];
$total_pasajeros = $busqueda['total_pasajeros']; 

try {
    // 5. Obtener precio de ida
    $sql_precio_ida = "SELECT precio_base FROM vuelos WHERE id = :id_vuelo";
    $stmt_ida = $pdo->prepare($sql_precio_ida);
    $stmt_ida->execute(['id_vuelo' => $id_vuelo_ida]);
    $vuelo_ida = $stmt_ida->fetch();

    if (!$vuelo_ida) {
        throw new Exception("El vuelo de ida seleccionado no es válido.");
    }

    // 6. Guardar selección
    $_SESSION['seleccion_ida'] = [
        'id_vuelo' => $id_vuelo_ida,
        'precio_base' => $vuelo_ida['precio_base']
    ];

    // 7. Decidir ruta
    if ($busqueda['tipo_vuelo'] == 'sencillo') {
        
        // --- CASO 1: VUELO SENCILLO ---

        // 8. Calcular precio basado en la clase
        $multiplicador_clase = 1.0; // Turista
        if ($clase == 'Business') {
            $multiplicador_clase = 1.8; 
        } elseif ($clase == 'Primera') {
            $multiplicador_clase = 2.5; 
        }

        $precio_base_ajustado = $vuelo_ida['precio_base'] * $multiplicador_clase;
        
        // El precio final es el precio ajustado por (adultos + niños)
        $precio_final = $precio_base_ajustado * ($adultos + $ninos);

        // 9. Crear la reserva 'pendiente'
        $sql_reserva = "INSERT INTO reservas 
                            (id_usuario, id_vuelo_ida, total_pasajeros, precio_final, clase, adultos, ninos, bebes)
                        VALUES 
                            (:id_usuario, :id_vuelo_ida, :total_pasajeros, :precio_final, :clase, :adultos, :ninos, :bebes)";
        
        $stmt_reserva = $pdo->prepare($sql_reserva);
        $stmt_reserva->execute([
            'id_usuario'       => $id_usuario,
            'id_vuelo_ida'     => $id_vuelo_ida,
            'total_pasajeros'  => $total_pasajeros,
            'precio_final'     => $precio_final,
            'clase'            => $clase,
            'adultos'          => $adultos,
            'ninos'            => $ninos,
            'bebes'            => $bebes
        ]);

        // 10. Guardar ID de reserva
        $_SESSION['id_reserva_activa'] = $pdo->lastInsertId();

        // 11. Redirigir
        header("Location: detallesReserva.php");
        exit;

    } else {
        
        // --- CASO 2: VUELO REDONDO ---
        $sql_regreso = "SELECT 
                            v.id AS vuelo_id, v.numero_vuelo, v.hora_salida, v.hora_llegada, v.precio_base,
                            a_origen.nombre AS origen_nombre, a_destino.nombre AS destino_nombre
                        FROM vuelos AS v
                        JOIN aeropuertos AS a_origen ON v.aeropuerto_origen_id = a_origen.id
                        JOIN aeropuertos AS a_destino ON v.aeropuerto_destino_id = a_destino.id
                        WHERE 
                            v.aeropuerto_origen_id = :origen_id_regreso 
                            AND v.aeropuerto_destino_id = :destino_id_regreso
                            AND DATE(v.hora_salida) = :fecha_regreso";

        $stmt_regreso = $pdo->prepare($sql_regreso);
        $stmt_regreso->execute([
            'origen_id_regreso'  => $busqueda['destino_id'],
            'destino_id_regreso' => $busqueda['origen_id'],
            'fecha_regreso'      => $busqueda['fecha_regreso']
        ]);

        $_SESSION['resultados_regreso'] = $stmt_regreso->fetchAll();
        header("Location: resultadosVuelosRegreso.php");
        exit;
    }

} catch (Exception $e) {
    // ¡ESTA ES LA LÍNEA CORREGIDA!
    // Simplemente muestra el mensaje de error.
    die("Error al procesar la reserva: " . $e->getMessage());
}
?>