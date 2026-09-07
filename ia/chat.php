<?php
$apiKey = 'TU_API_KEY_AQUI';
$mensajeUsuario = 'Hola, ¿cómo estás?';

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://api.ejemplo.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gpt-4o',
    'messages' => [['role' => 'user', 'content' => $mensajeUsuario]],
    'max_tokens' => 50
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

$respuesta = curl_exec($ch);
curl_close($ch);

if ($respuesta === false) {
    echo 'Error en la solicitud cURL: ' . curl_error($ch);
} else {
    $datos = json_decode($respuesta, true);
    echo 'Respuesta de la IA: ' . $datos['choices'][0]['message']['content'];
}
?>
