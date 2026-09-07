<!-- JARVIS CHATBOT FLOTANTE -->
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>

/* BOTÓN FLOTANTE */
#jarvis-btn {
  position: fixed;
  bottom: 25px;
  right: 105px;
  background: #235f46;
  color: white;
  width: 65px;
  height: 65px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  cursor: pointer;
  box-shadow: 0 4px 10px rgba(0,0,0,.3);
  z-index: 9999;
}

/* CONTENEDOR */
#jarvis-box {
  position: fixed;
  bottom: 100px;
  right: 105px;
  width: 370px;
  height: 520px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0,0,0,.3);
  display: none;
  z-index: 9998;
  overflow: hidden;
}

/* ---- TU DISEÑO ORIGINAL ---- */

body {
  font-family: "Inter", sans-serif;
}

.chat-container {
  display: flex;
  flex-direction: column;
  height: 500px;
}

.chat-header {
  background: #f8f9fa;
  padding: 15px 20px;
  font-weight: 600;
  font-size: 1.2rem;
  border-bottom: 1px solid #dee2e6;
}

.chat-messages {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
}

.message {
  display: flex;
  margin-bottom: 15px;
  align-items: flex-start;
}

.message.user {
  justify-content: flex-end;
}

.message .bubble {
  display: block;
  background: #f1f3f5;
  color: #212529;
  border-radius: 18px 18px 18px 0;
  padding: 12px 16px;
  max-width: 90%;
  white-space: pre-wrap;
  overflow-wrap: break-word;
  font-size: 0.95rem;
}

.message.user .bubble {
  background: #235f46;
  color: white;
  border-radius: 18px 18px 0 18px;
}

.avatar {
  width: 35px;
  margin-right: 10px;
}

.chat-input {
  padding: 10px 15px;
  border-top: 1px solid #dee2e6;
  display: flex;
  gap: 10px;
  background: #f8f9fa;
}

.typing {
  font-style: italic;
  color: #6c757d;
}
</style>
</head>

<body>


<!-- BOTÓN FLOTANTE -->
<div id="jarvis-btn">💬</div>

<!-- CAJA DEL CHAT -->
<div id="jarvis-box">

<div class="chat-container">
  <div class="chat-header">💬 VIOLETA - Asistente IA</div>

  <div class="chat-messages"></div>

  <div class="chat-input">
    <input type="text" id="mensaje" placeholder="Escribe tu mensaje..." autocomplete="off">
    <button id="enviar" class="btn btn-primary">Enviar</button>
  </div>
</div>

</div> <!-- fin jarvis-box -->


<script>
$(document).ready(function(){

  // ABRIR Y CERRAR EL CHAT
  $("#jarvis-btn").click(() => $("#jarvis-box").toggle());

  const chatBox = $(".chat-messages");

  function appendUserMessage(msg) {
    chatBox.append(`
      <div class="message user">
        <div>
          <div class="bubble">${msg}</div>
        </div>
      </div>
    `);
    chatBox.scrollTop(chatBox[0].scrollHeight);
  }

  function formatMessage(msg) {
    return msg.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
  }

  function appendBotMessage(msg) {
    chatBox.append(`
      <div class="message bot">
        <img class="avatar" src="https://cdn-icons-png.flaticon.com/512/4712/4712035.png">
        <div>
          <div class="bubble">${formatMessage(msg)}</div>
        </div>
      </div>
    `);
    chatBox.scrollTop(chatBox[0].scrollHeight);
  }

  function appendTyping() {
    chatBox.append(`
      <div class="message bot typing" id="typing">
        <img class="avatar" src="https://cdn-icons-png.flaticon.com/512/4712/4712035.png">
        <div class="bubble">VIOLETA está escribiendo...</div>
      </div>
    `);
    chatBox.scrollTop(chatBox[0].scrollHeight);
  }

  function removeTyping() { $("#typing").remove(); }


  $("#enviar").click(function(){
    let mensaje = $("#mensaje").val().trim();
    if(!mensaje) return;

    appendUserMessage(mensaje);
    $("#mensaje").val("");
    appendTyping();

    $.ajax({
      url: "../ia/chatbot.php",
      type: "POST",
      data: { mensaje: mensaje },
      dataType: "text",
      success: function(res){
        removeTyping();
        try {
          let data = JSON.parse(res.trim().substring(res.lastIndexOf('{')));
          appendBotMessage(data.respuesta || "Respuesta no válida.");
        } catch(e) {
          appendBotMessage("ERROR: Respuesta ilegible del servidor.");
        }
      },
      error: function(){
        removeTyping();
        appendBotMessage("ERROR: No se pudo conectar al servidor.");
      }
    });
  });

  $("#mensaje").keypress(function(e){
    if(e.which == 13){ $("#enviar").click(); return false; }
  });

});
</script>

</body>
</html>
