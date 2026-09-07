<?php
//require_once __DIR__ . '/../pages/seccion.php';

    // Incluir el archivo de configuración de la base de datos
    require_once __DIR__ . '/../db/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir los datos del formulario
    $fecha_hecho = $_POST["fecha_hecho"];
    $nombre_victima = $_POST["nombre_victima"];
    $apellido_paterno = $_POST["apellido_paterno"];
    $apellido_materno = $_POST["apellido_materno"];
    $lugar_origen = $_POST["lugar_origen"];
    $edad = $_POST["edad"];
    $ocupacion = $_POST["ocupacion"];
    $calle = $_POST["calle"];
    $numero = $_POST["numero"];
    $municipio = $_POST["municipio"];
    $region = $_POST["region"];
    $estado = $_POST["estado"];
    $clave_municipio = $_POST["clave_municipio"];
    $alerta_genero = $_POST["alerta_genero"];
    $id_caso_anual = $_POST["id_caso_anual"];
    $num_averiguacion = $_POST["num_averiguacion"];
    $situacion_juridica = $_POST["situacion_juridica"];
    $desaparecida = $_POST["desaparecida"];
    $fecha_desaparicion = $_POST["fecha_desaparicion"];
    $lugar_cuerpo = $_POST["lugar_cuerpo"];
    $descripcion_cuerpo = $_POST["descripcion_cuerpo"];
    $forma_muerte = $_POST["forma_muerte"];
    $tipo_arma = $_POST["tipo_arma"];
    $causas = $_POST["causas"];
    $descendencia = $_POST["descendencia"];
    $num_descendencia = $_POST["num_descendencia"];
    $nombre_agresor = $_POST["nombre_agresor"];
    $parentesco_agresor = $_POST["parentesco_agresor"];
    $fuente_periodistica = $_POST["fuente_periodistica"];
    $autor_nota = $_POST["autor_nota"];
    $link_nota = $_POST["link_nota"];
    $latitud = $_POST["latitud"];
    $longitud = $_POST["longitud"];
    $sexenio = $_POST["sexenio"];
    $numa = $_POST["numa"];



    try {
        
        // Consulta SQL para insertar los datos en la tabla Feminicidios
        $sql = "INSERT INTO feminicidios (FechaHecho, NombreVictima, ApellidoPaterno, ApellidoMaterno, LugarOrigen, edad, Ocupacion, Calle, Numero, Municipio, Region, Estado, ClaveMunicipio, AlertaGenero, IDCasoAnual, NumAveriguacion, SituacionJuridica, Desaparecida, FechaDesaparicion, LugarEncontradoCuerpo, DescripcionCuerpo, FormaMuerte, TipoArma, Causas, Descendencia, NumDescendencia, NombreAgresor, ParentescoAgresor, FuentePeriodistica, AutorNota, LinkNota, Latitud, Longitud, Sexenio, Numa) 
                VALUES (:fecha_hecho, :nombre_victima, :apellido_paterno, :apellido_materno, :lugar_origen, :edad, :ocupacion, :calle, :numero, :municipio, :region, :estado, :clave_municipio, :alerta_genero, :id_caso_anual, :num_averiguacion, :situacion_juridica, :desaparecida, :fecha_desaparicion, :lugar_cuerpo, :descripcion_cuerpo, :forma_muerte, :tipo_arma, :causas, :descendencia, :num_descendencia, :nombre_agresor, :parentesco_agresor, :fuente_periodistica, :autor_nota, :link_nota, :latitud, :longitud, :sexenio, :numa)";
        
        // Preparar la consulta
        $stmt = $conn->prepare($sql);
        
        // Vincular parámetros
        $stmt->bindParam(':fecha_hecho', $fecha_hecho);
        $stmt->bindParam(':nombre_victima', $nombre_victima);
        $stmt->bindParam(':apellido_paterno', $apellido_paterno);
        $stmt->bindParam(':apellido_materno', $apellido_materno);
        $stmt->bindParam(':lugar_origen', $lugar_origen);
        $stmt->bindParam(':edad', $edad);
        $stmt->bindParam(':ocupacion', $ocupacion);
        $stmt->bindParam(':calle', $calle);
        $stmt->bindParam(':numero', $numero);
        $stmt->bindParam(':municipio', $municipio);
        $stmt->bindParam(':region', $region);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':clave_municipio', $clave_municipio);
        $stmt->bindParam(':alerta_genero', $alerta_genero);
        $stmt->bindParam(':id_caso_anual', $id_caso_anual);
        $stmt->bindParam(':num_averiguacion', $num_averiguacion);
        $stmt->bindParam(':situacion_juridica', $situacion_juridica);
        $stmt->bindParam(':desaparecida', $desaparecida);
        $stmt->bindParam(':fecha_desaparicion', $fecha_desaparicion);
        $stmt->bindParam(':lugar_cuerpo', $lugar_cuerpo);
        $stmt->bindParam(':descripcion_cuerpo', $descripcion_cuerpo);
        $stmt->bindParam(':forma_muerte', $forma_muerte);
        $stmt->bindParam(':tipo_arma', $tipo_arma);
        $stmt->bindParam(':causas', $causas);
        $stmt->bindParam(':descendencia', $descendencia);
        $stmt->bindParam(':num_descendencia', $num_descendencia);
        $stmt->bindParam(':nombre_agresor', $nombre_agresor);
        $stmt->bindParam(':parentesco_agresor', $parentesco_agresor);
        $stmt->bindParam(':fuente_periodistica', $fuente_periodistica);
        $stmt->bindParam(':autor_nota', $autor_nota);
        $stmt->bindParam(':link_nota', $link_nota);
        $stmt->bindParam(':latitud', $latitud);
        $stmt->bindParam(':longitud', $longitud);
        $stmt->bindParam(':sexenio', $sexenio);
        $stmt->bindParam(':numa', $numa);

        
        // Ejecutar la consulta
        $stmt->execute();
          header("Location: ../pages/ver-feminicidio.php?status=success");
exit;
    } catch(PDOException $e) {
        // Manejar errores de manera adecuada
         header("Location: ../pages/ver-feminicidio.php?status=error&msg=" . urlencode($e->getMessage()));
exit();
    }

    // Cerrar la conexión a la base de datos
    $conn = null;
}
?>